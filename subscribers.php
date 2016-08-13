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
 * This file is used to display and organise scripting_forum subscribers
 *
 * @package   mod_scripting_forum
 * @copyright 2016 Geiser Chalco     {@link http://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id    = required_param('id',PARAM_INT);           // scripting_forum
$group = optional_param('group',0,PARAM_INT);      // change of group
$edit  = optional_param('edit',-1,PARAM_BOOL);     // Turn editing on and off

$url = new moodle_url('/mod/scripting_forum/subscribers.php', array('id'=>$id));
if ($group !== 0) {
    $url->param('group', $group);
}
if ($edit !== 0) {
    $url->param('edit', $edit);
}
$PAGE->set_url($url);

$scripting_forum = $DB->get_record('scripting_forum', array('id'=>$id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$scripting_forum->course), '*', MUST_EXIST);
if (! $cm = get_coursemodule_from_instance('scripting_forum', $scripting_forum->id, $course->id)) {
    $cm->id = 0;
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
if (!has_capability('mod/scripting_forum:viewsubscribers', $context)) {
    print_error('nopermissiontosubscribe', 'scripting_forum');
}

unset($SESSION->fromdiscussion);

$params = array(
    'context' => $context,
    'other' => array('scripting_forumid' => $scripting_forum->id),
);
$event = \mod_scripting_forum\event\subscribers_viewed::create($params);
$event->trigger();

$scripting_forumoutput = $PAGE->get_renderer('mod_scripting_forum');
$currentgroup = groups_get_activity_group($cm);
$options = array('scripting_forumid'=>$scripting_forum->id,
        'currentgroup'=>$currentgroup, 'context'=>$context);
$existingselector = new mod_scripting_forum_existing_subscriber_selector('existingsubscribers',
        $options);
$subscriberselector = new mod_scripting_forum_potential_subscriber_selector('potentialsubscribers',
        $options);
$subscriberselector->set_existing_subscribers($existingselector->find_users(''));

if (data_submitted()) {
    require_sesskey();
    $subscribe = (bool)optional_param('subscribe', false, PARAM_RAW);
    $unsubscribe = (bool)optional_param('unsubscribe', false, PARAM_RAW);
    /** It has to be one or the other, not both or neither */
    if (!($subscribe xor $unsubscribe)) {
        print_error('invalidaction');
    }
    if ($subscribe) {
        $users = $subscriberselector->get_selected_users();
        foreach ($users as $user) {
            if (!\mod_scripting_forum\subscriptions::subscribe_user($user->id, $scripting_forum)) {
                print_error('cannotaddsubscriber', 'scripting_forum', '', $user->id);
            }
        }
    } else if ($unsubscribe) {
        $users = $existingselector->get_selected_users();
        foreach ($users as $user) {
            if (!\mod_scripting_forum\subscriptions::unsubscribe_user($user->id, $scripting_forum)) {
                print_error('cannotremovesubscriber', 'scripting_forum', '', $user->id);
            }
        }
    }
    $subscriberselector->invalidate_selected_users();
    $existingselector->invalidate_selected_users();
    $subscriberselector->set_existing_subscribers($existingselector->find_users(''));
}

$strsubscribers = get_string("subscribers", "scripting_forum");
$PAGE->navbar->add($strsubscribers);
$PAGE->set_title($strsubscribers);
$PAGE->set_heading($COURSE->fullname);
if (has_capability('mod/scripting_forum:managesubscriptions', $context)
    && \mod_scripting_forum\subscriptions::is_forcesubscribed($scripting_forum) === false) {
    if ($edit != -1) {
        $USER->subscriptionsediting = $edit;
    }
    $PAGE->set_button(scripting_forum_update_subscriptions_button($course->id, $id));
} else {
    unset($USER->subscriptionsediting);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('scripting_forum', 'scripting_forum').' '.$strsubscribers);
if (empty($USER->subscriptionsediting)) {
    $subscribers = \mod_scripting_forum\subscriptions::fetch_subscribed_users($scripting_forum,
                $currentgroup, $context);
    if (\mod_scripting_forum\subscriptions::is_forcesubscribed($scripting_forum)) {
        $subscribers = mod_scripting_forum_filter_hidden_users($cm, $context, $subscribers);
    }
    echo $scripting_forumoutput->subscriber_overview($subscribers, $scripting_forum, $course);
} else {
    echo $scripting_forumoutput->subscriber_selection_form($existingselector, $subscriberselector);
}
echo $OUTPUT->footer();

/**
 * Filters a list of users for whether they can see a given activity.
 * If the course module is hidden (closed-eye icon), then only users who have
 * the permission to view hidden activities will appear in the output list.
 *
 * @todo MDL-48625 This filtering should be handled in core libraries instead.
 *
 * @param stdClass $cm the course module record of the activity.
 * @param context_module $context the activity context, to save re-fetching it.
 * @param array $users the list of users to filter.
 * @return array the filtered list of users.
 */
function mod_scripting_forum_filter_hidden_users(stdClass $cm,
        context_module $context, array $users) {
    if ($cm->visible) {
        return $users;
    } else {
        // Filter for users that can view hidden activities.
        $filteredusers = array();
        $hiddenviewers = get_users_by_capability($context, 'moodle/course:viewhiddenactivities');
        foreach ($hiddenviewers as $hiddenviewer) {
            if (array_key_exists($hiddenviewer->id, $users)) {
                $filteredusers[$hiddenviewer->id] = $users[$hiddenviewer->id];
            }
        }
        return $filteredusers;
    }
}

