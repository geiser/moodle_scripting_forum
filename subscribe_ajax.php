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
 * Subscribe to or unsubscribe from a scripting_forum discussion.
 *
 * @package    mod_scripting_forum
 * @copyright  2016 Geiser Chalco <geiser@usp.br>
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');
require_once($CFG->dirroot . '/mod/scripting_forum/lib.php');

$scripting_forumid = required_param('scripting_forumid', PARAM_INT); // The scripting_forum to subscribe or unsubscribe
$discussionid   = optional_param('discussionid', null, PARAM_INT);   // The discussionid to subscribe
$includetext    = optional_param('includetext', false, PARAM_BOOL);

$scripting_forum = $DB->get_record('scripting_forum',
        array('id' => $scripting_forumid), '*', MUST_EXIST);
$course         = $DB->get_record('course',
        array('id' => $scripting_forum->course), '*', MUST_EXIST);
if (!$discussion = $DB->get_record('scripting_forum_discussions',
        array('id' => $discussionid, 'scripting_forum' => $scripting_forumid))) {
    print_error('invaliddiscussionid', 'scripting_forum');
}
$cm             = get_coursemodule_from_instance('scripting_forum', $scripting_forum->id, $course->id, false, MUST_EXIST);
$context        = context_module::instance($cm->id);

require_sesskey();
require_login($course, false, $cm);
require_capability('mod/scripting_forum:viewdiscussion', $context);

$return = new stdClass();

if (is_guest($context, $USER)) {
    // is_guest should be used here as this also checks
    // whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    throw new moodle_exception('noguestsubscribe', 'mod_scripting_forum');
}

if (!\mod_scripting_forum\subscriptions::is_subscribable($scripting_forum)) {
    // Nothing to do. We won't actually output any content here though.
    echo json_encode($return);
    die;
}

if (\mod_scripting_forum\subscriptions::is_subscribed($USER->id,
        $scripting_forum, $discussion->id, $cm)) {
    // The user is subscribed, unsubscribe them.
    \mod_scripting_forum\subscriptions::unsubscribe_user_from_discussion($USER->id,
                $discussion, $context);
} else {
    // The user is unsubscribed, subscribe them.
    \mod_scripting_forum\subscriptions::subscribe_user_to_discussion($USER->id,
                $discussion, $context);
}

// Now return the updated subscription icon.
$return->icon = scripting_forum_get_discussion_subscription_icon($scripting_forum,
        $discussion->id, null, $includetext);
echo json_encode($return);
die;

