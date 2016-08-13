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
 * @package   mod_scripting_forum
 * @copyright 2016 Geiser Chalco (http://github.com/geiser)
 * @copyright 2008 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$confirm = optional_param('confirm', false, PARAM_BOOL);

$PAGE->set_url('/mod/scripting_forum/unsubscribeall.php');

// Do not autologin guest. Only proper users can have scripting_forum subscriptions.
require_login(null, false);
$PAGE->set_context(context_user::instance($USER->id));

$return = $CFG->wwwroot.'/';

if (isguestuser()) {
    redirect($return);
}

$strunsubscribeall = get_string('unsubscribeall', 'scripting_forum');
$PAGE->navbar->add(get_string('modulename', 'scripting_forum'));
$PAGE->navbar->add($strunsubscribeall);
$PAGE->set_title($strunsubscribeall);
$PAGE->set_heading($COURSE->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strunsubscribeall);

if (data_submitted() and $confirm and confirm_sesskey()) {
    $scripting_forums = \mod_scripting_forum\subscriptions::get_unsubscribable_scripting_forums();

    foreach($scripting_forums as $scripting_forum) {
        \mod_scripting_forum\subscriptions::unsubscribe_user($USER->id,
                    $scripting_forum, context_module::instance($scripting_forum->cm), true);
    }
    $DB->delete_records('scripting_forum_discussion_subs', array('userid' => $USER->id));
    $DB->set_field('user', 'autosubscribe', 0, array('id'=>$USER->id));

    echo $OUTPUT->box(get_string('unsubscribealldone', 'scripting_forum'));
    echo $OUTPUT->continue_button($return);
    echo $OUTPUT->footer();
    die;

} else {
    $count = new stdClass();
    $count->scripting_forums = count(\mod_scripting_forum\subscriptions::get_unsubscribable_scripting_forums());
    $count->discussions = $DB->count_records('scripting_forum_discussion_subs',
            array('userid' => $USER->id));

    if ($count->scripting_forums || $count->discussions) {
        if ($count->scripting_forums && $count->discussions) {
            $msg = get_string('unsubscribeallconfirm', 'scripting_forum', $count);
        } else if ($count->scripting_forums) {
            $msg = get_string('unsubscribeallconfirmscripting_forums', 'scripting_forum', $count);
        } else if ($count->discussions) {
            $msg = get_string('unsubscribeallconfirmdiscussions', 'scripting_forum', $count);
        }
        echo $OUTPUT->confirm($msg, new moodle_url('unsubscribeall.php', array('confirm'=>1)), $return);
        echo $OUTPUT->footer();
        die;

    } else {
        echo $OUTPUT->box(get_string('unsubscribeallempty', 'scripting_forum'));
        echo $OUTPUT->continue_button($return);
        echo $OUTPUT->footer();
        die;
    }
}

