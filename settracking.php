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
 * Set tracking option for the scripting_forum.
 *
 * @package   mod_scripting_forum
 * @copyright 2015 geiser
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id         = required_param('id',PARAM_INT);                           // The scripting_forum to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

require_sesskey();

if (! $scripting_forum = $DB->get_record("scripting_forum", array("id" => $id))) {
    print_error('invalidscripting_forumid', 'scripting_forum');
}

if (! $course = $DB->get_record("course", array("id" => $scripting_forum->course))) {
    print_error('invalidcoursemodule');
}

if (! $cm = get_coursemodule_from_instance("scripting_forum", $scripting_forum->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);
$returnpageurl = new moodle_url('/mod/scripting_forum/' .
        $returnpage, array('id' => $course->id, 'f' => $scripting_forum->id));
$returnto = scripting_forum_go_back_to($returnpageurl);

if (!scripting_forum_tp_can_track_scripting_forums($scripting_forum)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->scripting_forum = format_string($scripting_forum->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('scripting_forumid' => $scripting_forum->id),
);

if (scripting_forum_tp_is_tracked($scripting_forum) ) {
    if (scripting_forum_tp_stop_tracking($scripting_forum->id)) {
        $event = \mod_scripting_forum\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nownottracking", "scripting_forum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }

} else { // subscribe
    if (scripting_forum_tp_start_tracking($scripting_forum->id)) {
        $event = \mod_scripting_forum\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nowtracking", "scripting_forum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}

