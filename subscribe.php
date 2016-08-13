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
 * Subscribe to or unsubscribe from a scripting_forum or
 * manage scripting_forum subscription mode
 *
 * This script can be used by either individual users to subscribe to or
 * unsubscribe from a scripting_forum (no 'mode' param provided),
 * or by scripting_forum managers to control the subscription mode (by 'mode' param).
 * This script can be called from a link in email so the sesskey is not
 * required parameter. However, if sesskey is missing, the user has to go
 * through a confirmation page that redirects the user back with the
 * sesskey.
 *
 * @package   mod_scripting_forum
 * @copyright  2016 Geiser Chalco  {@link http://github.com/geiser}
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/scripting_forum/lib.php');

$id             = required_param('id', PARAM_INT);         // Setting subscription on.
$mode           = optional_param('mode', null, PARAM_INT); // The subscription mode.
$user           = optional_param('user', 0, PARAM_INT);    // The useridr, defaults to $USER.
$discussionid   = optional_param('d', null, PARAM_INT);    // The discussionid to subscribe.
$sesskey        = optional_param('sesskey', null, PARAM_RAW);
$returnurl      = optional_param('returnurl', null, PARAM_RAW);

$url = new moodle_url('/mod/scripting_forum/subscribe.php', array('id'=>$id));
if (!is_null($mode)) {
    $url->param('mode', $mode);
}
if ($user !== 0) {
    $url->param('user', $user);
}
if (!is_null($sesskey)) {
    $url->param('sesskey', $sesskey);
}
if (!is_null($discussionid)) {
    $url->param('d', $discussionid);
    if (!$discussion = $DB->get_record('scripting_forum_discussions',
            array('id' => $discussionid, 'scripting_forum' => $id))) {
        print_error('invaliddiscussionid', 'scripting_forum');
    }
}
$PAGE->set_url($url);

$scripting_forum = $DB->get_record('scripting_forum', array('id' => $id), '*', MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $scripting_forum->course), '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('scripting_forum',
           $scripting_forum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

if ($user) {
    require_sesskey();
    if (!has_capability('mod/scripting_forum:managesubscriptions', $context)) {
        print_error('nopermissiontosubscribe', 'scripting_forum');
    }
    $user = $DB->get_record('user', array('id' => $user), '*', MUST_EXIST);
} else {
    $user = $USER;
}

if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode = $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

$issubscribed = \mod_scripting_forum\subscriptions::is_subscribed($user->id,
        $scripting_forum, $discussionid, $cm);

// For a user to subscribe when a groupmode is set, they must have access to at least one group.
if ($groupmode && !$issubscribed && !has_capability('moodle/site:accessallgroups', $context)) {
    if (!groups_get_all_groups($course->id, $USER->id)) {
        print_error('cannotsubscribe', 'scripting_forum');
    }
}

require_login($course, false, $cm);

if (is_null($mode) and !is_enrolled($context, $USER, '', true)) {   // Guests and visitors can't subscribe - only enrolled
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    if (isguestuser()) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('subscribeenrolledonly', 'scripting_forum').
                '<br /><br />'.get_string('liketologin'), get_login_url(),
                new moodle_url('/mod/scripting_forum/view.php', array('f'=>$id)));
        echo $OUTPUT->footer();
        exit;
    } else {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/scripting_forum/view.php', array('f'=>$id)),
                get_string('subscribeenrolledonly', 'scripting_forum'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
}

$returnto = optional_param('backtoindex',0,PARAM_INT)
    ? "index.php?id=".$course->id
    : "view.php?f=$id";

if ($returnurl) {
    $returnto = $returnurl;
}

if (!is_null($mode) and
    has_capability('mod/scripting_forum:managesubscriptions', $context)) {
    require_sesskey();
    switch ($mode) {
        case FORUM_CHOOSESUBSCRIBE : // 0
            \mod_scripting_forum\subscriptions::set_subscription_mode($scripting_forum->id, FORUM_CHOOSESUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyonecannowchoose', 'scripting_forum'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case FORUM_FORCESUBSCRIBE : // 1
            \mod_scripting_forum\subscriptions::set_subscription_mode($scripting_forum->id, FORUM_FORCESUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyoneisnowsubscribed', 'scripting_forum'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case FORUM_INITIALSUBSCRIBE : // 2
            if ($scripting_forum->forcesubscribe <> FORUM_INITIALSUBSCRIBE) {
                $users = \mod_scripting_forum\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email', '');
                foreach ($users as $user) {
                    \mod_scripting_forum\subscriptions::subscribe_user($user->id,
                                $scripting_forum, $context);
                }
            }
            \mod_scripting_forum\subscriptions::set_subscription_mode($scripting_forum->id, FORUM_INITIALSUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyoneisnowsubscribed', 'scripting_forum'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case FORUM_DISALLOWSUBSCRIBE : // 3
            \mod_scripting_forum\subscriptions::set_subscription_mode($scripting_forum->id, FORUM_DISALLOWSUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('noonecansubscribenow', 'scripting_forum'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        default:
            print_error(get_string('invalidforcesubscribe', 'scripting_forum'));
    }
}

if (\mod_scripting_forum\subscriptions::is_forcesubscribed($scripting_forum)) {
    redirect(
            $returnto,
            get_string('everyoneisnowsubscribed', 'scripting_forum'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
}

$info = new stdClass();
$info->name  = fullname($user);
$info->scripting_forum = format_string($scripting_forum->name);

if ($issubscribed) {
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/scripting_forum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->scripting_forum = format_string($scripting_forum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmunsubscribediscussion', 'scripting_forum', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmunsubscribe',
                        'scripting_forum', format_string($scripting_forum->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid === null) {
        if (\mod_scripting_forum\subscriptions::unsubscribe_user($user->id,
                    $scripting_forum, $context, true)) {
            redirect(
                    $returnto,
                    get_string('nownotsubscribed', 'scripting_forum', $info),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
        } else {
            print_error('cannotunsubscribe', 'scripting_forum', get_local_referer(false));
        }
    } else {
        if (\mod_scripting_forum\subscriptions::unsubscribe_user_from_discussion($user->id,
                    $discussion, $context)) {
            $info->discussion = $discussion->name;
            redirect(
                    $returnto,
                    get_string('discussionnownotsubscribed', 'scripting_forum', $info),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
        } else {
            print_error('cannotunsubscribe', 'scripting_forum', get_local_referer(false));
        }
    }

} else {  // subscribe
    if (\mod_scripting_forum\subscriptions::subscription_disabled($scripting_forum)
                && !has_capability('mod/scripting_forum:managesubscriptions', $context)) {
        print_error('disallowsubscribe', 'scripting_forum', get_local_referer(false));
    }
    if (!has_capability('mod/scripting_forum:viewdiscussion', $context)) {
        print_error('noviewdiscussionspermission', 'scripting_forum', get_local_referer(false));
    }
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/scripting_forum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->scripting_forum = format_string($scripting_forum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmsubscribediscussion',
                    'scripting_forum', $a), $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmsubscribe',
                        'scripting_forum', format_string($scripting_forum->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid == null) {
        \mod_scripting_forum\subscriptions::subscribe_user($user->id,
                    $scripting_forum, $context, true);
        redirect(
                $returnto,
                get_string('nowsubscribed', 'scripting_forum', $info),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        $info->discussion = $discussion->name;
        \mod_scripting_forum\subscriptions::subscribe_user_to_discussion($user->id,
                $discussion, $context);
        redirect(
                $returnto,
                get_string('discussionnowsubscribed', 'scripting_forum', $info),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

