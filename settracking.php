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
 * Set tracking option for the sforum.
 *
 * @package   mod_sforum
 * @copyright 2015 geiser
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id         = required_param('id',PARAM_INT);                           // The sforum to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

require_sesskey();

if (! $sforum = $DB->get_record("sforum", array("id" => $id))) {
    print_error('invalidsforumid', 'sforum');
}

if (! $course = $DB->get_record("course", array("id" => $sforum->course))) {
    print_error('invalidcoursemodule');
}

if (! $cm = get_coursemodule_from_instance("sforum", $sforum->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);
$returnpageurl = new moodle_url('/mod/sforum/' .
        $returnpage, array('id' => $course->id, 'f' => $sforum->id));
$returnto = sforum_go_back_to($returnpageurl);

if (!sforum_tp_can_track_sforums($sforum)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->sforum = format_string($sforum->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('sforumid' => $sforum->id),
);

if (sforum_tp_is_tracked($sforum) ) {
    if (sforum_tp_stop_tracking($sforum->id)) {
        $event = \mod_sforum\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nownottracking", "sforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }

} else { // subscribe
    if (sforum_tp_start_tracking($sforum->id)) {
        $event = \mod_sforum\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nowtracking", "sforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}

