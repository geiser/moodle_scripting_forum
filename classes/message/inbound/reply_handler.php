<?php
// This file is based on part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A Handler to process replies to scriptingforum posts.
 *
 * @package    mod_scriptingforum
 * @subpackage core_message
 * @copyright  2016 Geiser Chalco
 * @copyright  2014 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scriptingforum\message\inbound;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/scriptingforum/lib.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * A Handler to process replies to scriptingforum posts.
 *
 * @copyright  2014 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reply_handler extends \core\message\inbound\handler {

    /**
     * Return a description for the current handler.
     *
     * @return string
     */
    public function get_description() {
        return get_string('reply_handler', 'mod_scriptingforum');
    }

    /**
     * Return a short name for the current handler.
     * This appears in the admin pages as a human-readable name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('reply_handler_name', 'mod_scriptingforum');
    }

    /**
     * Process a message received and validated by the Inbound Message processor.
     *
     * @throws \core\message\inbound\processing_failed_exception
     * @param \stdClass $messagedata The Inbound Message record
     * @param \stdClass $messagedata The message data packet
     * @return bool Whether the message was successfully processed.
     */
    public function process_message(\stdClass $record, \stdClass $messagedata) {
        global $DB, $USER;

        // Load the post being replied to.
        $post = $DB->get_record('scriptingforum_posts', array('id' => $record->datavalue));
        if (!$post) {
            mtrace("--> Unable to find a post matching with id {$record->datavalue}");
            return false;
        }

        // Load the discussion that this post is in.
        $discussion = $DB->get_record('scriptingforum_discussions',
                array('id' => $post->discussion));
        if (!$post) {
            mtrace("--> Unable to find the discussion for post {$record->datavalue}");
            return false;
        }

        // Load the other required data.
        $scriptingforum = $DB->get_record('scriptingforum',
                array('id' => $discussion->scriptingforum));
        $course = $DB->get_record('course', array('id' => $scriptingforum->course));
        $cm = get_fast_modinfo($course->id)->instances['scriptingforum'][$scriptingforum->id];
        $modcontext = \context_module::instance($cm->id);
        $usercontext = \context_user::instance($USER->id);

        // Make sure user can post in this discussion.
        $canpost = true;
        if (!scriptingforum_user_can_post($scriptingforum,
                $discussion, $USER, $cm, $course, $modcontext)) {
            $canpost = false;
        }

        if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
            $groupmode = $cm->groupmode;
        } else {
            $groupmode = $course->groupmode;
        }
        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($discussion->groupid == -1) {
                $canpost = false;
            } else {
                if (!groups_is_member($discussion->groupid)) {
                    $canpost = false;
                }
            }
        }

        if (!$canpost) {
            $data = new \stdClass();
            $data->scriptingforum = $scriptingforum;
            throw new \core\message\inbound\processing_failed_exception('messageinboundnopostscriptingforum', 'mod_scriptingforum', $data);
        }

        // And check the availability.
        if (!\core_availability\info_module::is_user_visible($cm)) {
            $data = new \stdClass();
            $data->scriptingforum = $scriptingforum;
            throw new \core\message\inbound\processing_failed_exception('messageinboundscriptingforumhidden', 'mod_scriptingforum', $data);
        }

        // Before we add this we must check that the user will not exceed the blocking threshold.
        // This should result in an appropriate reply.
        $thresholdwarning = scriptingforum_check_throttling($scriptingforum, $cm);
        if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
            $data = new \stdClass();
            $data->scriptingforum = $scriptingforum;
            $data->message = get_string($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->additional);
            throw new \core\message\inbound\processing_failed_exception('messageinboundthresholdhit', 'mod_scriptingforum', $data);
        }

        $subject = clean_param($messagedata->envelope->subject, PARAM_TEXT);
        $restring = get_string('re', 'scriptingforum');
        if (strpos($subject, $discussion->name)) {
            // The discussion name is mentioned in the e-mail subject. This is probably just the standard reply. Use the
            // standard reply subject instead.
            $newsubject = $restring . ' ' . $discussion->name;
            mtrace("--> Note: Post subject matched discussion name. Optimising from {$subject} to {$newsubject}");
            $subject = $newsubject;
        } else if (strpos($subject, $post->subject)) {
            // The replied-to post's subject is mentioned in the e-mail subject.
            // Use the previous post's subject instead of the e-mail subject.
            $newsubject = $post->subject;
            if (!strpos($restring, $post->subject)) {
                // The previous post did not contain a re string, add it.
                $newsubject = $restring . ' ' . $newsubject;
            }
            mtrace("--> Note: Post subject matched original post subject. Optimising from {$subject} to {$newsubject}");
            $subject = $newsubject;
        }

        $addpost = new \stdClass();
        $addpost->course          = $course->id;
        $addpost->scriptingforum = $scriptingforum->id;
        $addpost->discussion      = $discussion->id;
        $addpost->modified        = $messagedata->timestamp;
        $addpost->subject         = $subject;
        $addpost->parent          = $post->id;
        $addpost->itemid          = file_get_unused_draft_itemid();

        list ($message, $format)  = self::remove_quoted_text($messagedata);
        $addpost->message = $message;
        $addpost->messageformat = $format;

        // We don't trust text coming from e-mail.
        $addpost->messagetrust = false;

        // Add attachments to the post.
        if (!empty($messagedata->attachments['attachment']) && count($messagedata->attachments['attachment'])) {
            $attachmentcount = count($messagedata->attachments['attachment']);
            if (empty($scriptingforum->maxattachments) || $scriptingforum->maxbytes == 1 ||
                    !has_capability('mod/scriptingforum:createattachment', $modcontext)) {
                // Attachments are not allowed.
                mtrace("--> User does not have permission to attach files in this scriptingforum. Rejecting e-mail.");

                $data = new \stdClass();
                $data->scriptingforum = $scriptingforum;
                $data->attachmentcount = $attachmentcount;
                throw new \core\message\inbound\processing_failed_exception('messageinboundattachmentdisallowed', 'mod_scriptingforum', $data);
            }

            if ($scriptingforum->maxattachments < $attachmentcount) {
                // Too many attachments.
                mtrace("--> User attached {$attachmentcount} files when only {$scriptingforum->maxattachments} where allowed. "
                     . " Rejecting e-mail.");

                $data = new \stdClass();
                $data->scriptingforum = $scriptingforum;
                $data->attachmentcount = $attachmentcount;
                throw new \core\message\inbound\processing_failed_exception('messageinboundfilecountexceeded', 'mod_scriptingforum', $data);
            }

            $filesize = 0;
            $addpost->attachments  = file_get_unused_draft_itemid();
            foreach ($messagedata->attachments['attachment'] as $attachment) {
                mtrace("--> Processing {$attachment->filename} as an attachment.");
                $this->process_attachment('*', $usercontext, $addpost->attachments, $attachment);
                $filesize += $attachment->filesize;
            }

            if ($scriptingforum->maxbytes < $filesize) {
                // Too many attachments.
                mtrace("--> User attached {$filesize} bytes of files when only {$scriptingforum->maxbytes} where allowed. "
                     . "Rejecting e-mail.");
                $data = new \stdClass();
                $data->scriptingforum = $scriptingforum;
                $data->maxbytes = display_size($scriptingforum->maxbytes);
                $data->filesize = display_size($filesize);
                throw new \core\message\inbound\processing_failed_exception('messageinboundfilesizeexceeded', 'mod_scriptingforum', $data);
            }
        }

        // Process any files in the message itself.
        if (!empty($messagedata->attachments['inline'])) {
            foreach ($messagedata->attachments['inline'] as $attachment) {
                mtrace("--> Processing {$attachment->filename} as an inline attachment.");
                $this->process_attachment('*', $usercontext, $addpost->itemid, $attachment);

                // Convert the contentid link in the message.
                $draftfile = \moodle_url::make_draftfile_url($addpost->itemid, '/', $attachment->filename);
                $addpost->message = preg_replace('/cid:' . $attachment->contentid . '/', $draftfile, $addpost->message);
            }
        }

        // Insert the message content now.
        $addpost->id = scriptingforum_add_new_post($addpost, true);

        // Log the new post creation.
        $params = array(
            'context' => $modcontext,
            'objectid' => $addpost->id,
            'other' => array(
                'discussionid'  => $discussion->id,
                'scriptingforumid'       => $scriptingforum->id,
                'scriptingforumtype'     => $scriptingforum->type,
            )
        );
        $event = \mod_scriptingforum\event\post_created::create($params);
        $event->add_record_snapshot('scriptingforum_posts', $addpost);
        $event->add_record_snapshot('scriptingforum_discussions', $discussion);
        $event->trigger();

        // Update completion state.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && ($scriptingforum->completionreplies || $scriptingforum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);

            mtrace("--> Updating completion status for user {$USER->id} in scriptingforum {$scriptingforum->id} for post {$addpost->id}.");
        }

        mtrace("--> Created a post {$addpost->id} in {$discussion->id}.");
        return $addpost;
    }

    /**
     * Process attachments included in a message.
     *
     * @param string[] $acceptedtypes String The mimetypes of the acceptable attachment types.
     * @param \context_user $context context_user The context of the user creating this attachment.
     * @param int $itemid int The itemid to store this attachment under.
     * @param \stdClass $attachment stdClass The Attachment data to store.
     * @return \stored_file
     */
    protected function process_attachment($acceptedtypes, \context_user $context, $itemid, \stdClass $attachment) {
        global $USER, $CFG;

        // Create the file record.
        $record = new \stdClass();
        $record->filearea   = 'draft';
        $record->component  = 'user';

        $record->itemid     = $itemid;
        $record->license    = $CFG->sitedefaultlicense;
        $record->author     = fullname($USER);
        $record->contextid  = $context->id;
        $record->userid     = $USER->id;

        // All files sent by e-mail should have a flat structure.
        $record->filepath   = '/';

        $record->filename = $attachment->filename;

        mtrace("--> Attaching {$record->filename} to " .
               "/{$record->contextid}/{$record->component}/{$record->filearea}/" .
               "{$record->itemid}{$record->filepath}{$record->filename}");

        $fs = get_file_storage();
        return $fs->create_file_from_string($record, $attachment->content);
    }

    /**
     * Return the content of any success notification to be sent.
     * Both an HTML and Plain Text variant must be provided.
     *
     * @param \stdClass $messagedata The message data.
     * @param \stdClass $handlerresult The record for the newly created post.
     * @return \stdClass with keys `html` and `plain`.
     */
    public function get_success_message(\stdClass $messagedata, $handlerresult) {
        $a = new \stdClass();
        $a->subject = $handlerresult->subject;
        $discussionurl = new \moodle_url('/mod/scriptingforum/discuss.php', array('d' => $handlerresult->discussion));
        $discussionurl->set_anchor('p' . $handlerresult->id);
        $a->discussionurl = $discussionurl->out();

        $message = new \stdClass();
        $message->plain = get_string('postbymailsuccess', 'mod_scriptingforum', $a);
        $message->html = get_string('postbymailsuccess_html', 'mod_scriptingforum', $a);
        return $message;
    }
}

