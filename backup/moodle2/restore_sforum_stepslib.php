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
 * @package    mod_sforum
 * @subpackage backup-moodle2
 * @copyright  2016 Geiser Chalco {@link http://github.com/geiser}
 * @copyright  2010 Eloy Lafuente {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_sforum_activity_task
 */

/**
 * Structure step to restore one sforum activity
 */
class restore_sforum_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('sforum',
                '/activity/sforum');
        if ($userinfo) {
            $paths[] = new restore_path_element('sforum_discussion',
               '/activity/sforum/discussions/discussion');
            $paths[] = new restore_path_element('sforum_post',
               '/activity/sforum/discussions/discussion/posts/post');
            $paths[] = new restore_path_element('sforum_discussion_sub',
               '/activity/sforum/discussions/discussion/discussion_subs/discussion_sub');
            $paths[] = new restore_path_element('sforum_rating',
               '/activity/sforum/discussions/discussion/posts/post/ratings/rating');
            $paths[] = new restore_path_element('sforum_subscription',
               '/activity/sforum/subscriptions/subscription');
            $paths[] = new restore_path_element('sforum_digest',
               '/activity/sforum/digests/digest');
            $paths[] = new restore_path_element('sforum_read',
               '/activity/sforum/readposts/read');
            $paths[] = new restore_path_element('sforum_track',
               '/activity/sforum/trackedprefs/track');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_sforum($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->assesstimestart = $this->apply_date_offset($data->assesstimestart);
        $data->assesstimefinish = $this->apply_date_offset($data->assesstimefinish);
        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }

        $newitemid = $DB->insert_record('sforum', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_sforum_discussion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->sforum = $this->get_new_parentid('sforum');
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newitemid = $DB->insert_record('sforum_discussions', $data);
        $this->set_mapping('sforum_discussion', $oldid, $newitemid);
    }

    protected function process_sforum_post($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('sforum_discussion');
        $data->created = $this->apply_date_offset($data->created);
        $data->modified = $this->apply_date_offset($data->modified);
        $data->userid = $this->get_mappingid('user', $data->userid);
        // If post has parent, map it (it has been already restored)
        if (!empty($data->parent)) {
            $data->parent = $this->get_mappingid('sforum_post', $data->parent);
        }

        $newitemid = $DB->insert_record('sforum_posts', $data);
        $this->set_mapping('sforum_post', $oldid, $newitemid, true);

        // If !post->parent, it's the 1st post. Set it in discussion
        if (empty($data->parent)) {
            $DB->set_field('sforum_discussions', 'firstpost',
                        $newitemid, array('id' => $data->discussion));
        }
    }

    protected function process_sforum_rating($data) {
        global $DB;

        $data = (object)$data;

        // Cannot use ratings API, cause, it's missing
        // the ability to specify times (modified/created)
        $data->contextid = $this->task->get_contextid();
        $data->itemid    = $this->get_new_parentid('sforum_post');
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->rating = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // We need to check that component and ratingarea are both set here.
        if (empty($data->component)) {
            $data->component = 'mod_sforum';
        }
        if (empty($data->ratingarea)) {
            $data->ratingarea = 'post';
        }

        $newitemid = $DB->insert_record('rating', $data);
    }

    protected function process_sforum_subscription($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sforum = $this->get_new_parentid('sforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('sforum_subscriptions', $data);
        $this->set_mapping('sforum_subscription', $oldid, $newitemid, true);
    }

    protected function process_sforum_discussion_sub($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('sforum_discussion');
        $data->sforum = $this->get_new_parentid('sforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('sforum_discussion_subs', $data);
        $this->set_mapping('sforum_discussion_sub', $oldid, $newitemid, true);
    }

    protected function process_sforum_digest($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sforum = $this->get_new_parentid('sforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('sforum_digests', $data);
    }

    protected function process_sforum_read($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sforumid = $this->get_new_parentid('sforum');
        $data->discussionid = $this->get_mappingid('sforum_discussion',
                $data->discussionid);
        $data->postid = $this->get_mappingid('sforum_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('sforum_read', $data);
    }

    protected function process_sforum_track($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sforumid = $this->get_new_parentid('sforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('sforum_track_prefs', $data);
    }

    protected function after_execute() {
        // Add sforum related files, no need
        // to match by itemname (just internally handled context)
        $this->add_related_files('mod_sforum', 'intro', null);

        // Add post related files, matching by itemname = 'sforum_post'
        $this->add_related_files('mod_sforum', 'post', 'sforum_post');
        $this->add_related_files('mod_sforum', 'attachment', 'sforum_post');
    }

    protected function after_restore() {
        global $DB;

        // If the sforum is of type 'single' and no discussion has been ignited
        // (non-userinfo backup/restore) create the discussion here, using sforum
        // information as base for the initial post.
        $sforumid = $this->task->get_activityid();
        $sforumrec = $DB->get_record('sforum', array('id' => $sforumid));
        if ($sforumrec->type == 'single' &&
                !$DB->record_exists('sforum_discussions',
                        array('sforum' => $sforumid))) {
            // Create single discussion/lead post from sforum data
            $sd = new stdClass();
            $sd->course   = $sforumrec->course;
            $sd->sforum    = $sforumrec->id;
            $sd->name     = $sforumrec->name;
            $sd->assessed = $sforumrec->assessed;
            $sd->message  = $sforumrec->intro;
            $sd->messageformat = $sforumrec->introformat;
            $sd->messagetrust  = true;
            $sd->mailnow  = false;
            $sdid = sforum_add_discussion($sd, null, null, $this->task->get_userid());
            // Mark the post as mailed
            $DB->set_field ('sforum_posts','mailed', '1', array('discussion' => $sdid));
            // Copy all the files from mod_foum/intro to mod_sforum/post
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->task->get_contextid(),
                    'mod_sforum', 'intro');
            foreach ($files as $file) {
                $newfilerecord = new stdClass();
                $newfilerecord->filearea = 'post';
                $newfilerecord->itemid   = $DB->get_field('sforum_discussions',
                        'firstpost', array('id' => $sdid));
                $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }
    }
}

