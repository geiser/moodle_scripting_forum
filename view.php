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
 * @package   mod_sforum
 * @copyright 2016 Geiser Chalco  {@link http://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
$f           = optional_param('f', 0, PARAM_INT);        // Forum ID
$mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single sforum)
$showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
$changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
$page        = optional_param('page', 0, PARAM_INT);     // which page to show
$search      = optional_param('search', '', PARAM_CLEAN);// search string

$params = array();
if ($id) {
    $params['id'] = $id;
} else {
    $params['f'] = $f;
}
if ($page) {
    $params['page'] = $page;
}
if ($search) {
    $params['search'] = $search;
}
$PAGE->set_url('/mod/sforum/view.php', $params);

if ($id) {
    if (! $cm = get_coursemodule_from_id('sforum', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }
    if (! $sforum = $DB->get_record("sforum", array("id" => $cm->instance))) {
        print_error('invalidsforumid', 'sforum');
    }
    if ($sforum->type == 'single') {
        $PAGE->set_pagetype('mod-sforum-discuss');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strsforums = get_string("modulenameplural", "sforum");
    $strsforum = get_string("modulename", "sforum");
} else if ($f) {
    if (! $sforum = $DB->get_record("sforum", array("id" => $f))) {
        print_error('invalidsforumid', 'sforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $sforum->course))) {
        print_error('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance("sforum", $sforum->id, $course->id)) {
        print_error('missingparameter');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strsforums = get_string("modulenameplural", "sforum");
    $strsforum = get_string("modulename", "sforum");
} else {
    print_error('missingparameter');
}

if (!$PAGE->button) {
    $PAGE->set_button(sforum_search_form($course, $search));
}

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

if (!empty($CFG->enablerssfeeds) && !empty($CFG->sforum_enablerssfeeds)
            && $sforum->rsstype && $sforum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");
    $rsstitle = format_string($course->shortname, true,
            array('context' => context_course::instance($course->id))).': '.format_string($sforum->name);
    rss_add_http_header($context, 'mod_sforum', $sforum, $rsstitle);
}

// Redirect into the discussion if you are student and has only one discussion
$context = context_module::instance($cm->id);
$roles = get_user_roles($context, $USER->id, true);
foreach ($roles as $role) {
    if ($role->shortname == 'student') {
        $objs = sforum_get_discussions($cm);
        if (count($objs) == 1) {
            $dis = array_pop($objs);
            redirect(new moodle_url('/mod/sforum/discuss.php', array('d'=>$dis->discussion)));
        }
        break;
    }
}

/// Print header.
$PAGE->set_title($sforum->name);
$PAGE->add_body_class('sforumtype-'.$sforum->type);
$PAGE->set_heading($course->fullname);

/// Some capability checks.
if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!has_capability('mod/sforum:viewdiscussion', $context)) {
    notice(get_string('noviewdiscussionspermission', 'sforum'));
}

// Mark viewed and trigger the course_module_viewed event.
sforum_view($sforum, $course, $cm, $context);

echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($sforum->name), 2);
    if (!empty($sforum->intro) && $sforum->type != 'single' && $sforum->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('sforum', $sforum, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/sforum/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion sforum, we need to print the display
    // mode control.
    if ($sforum->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('sforum_discussions', array('forum'=>$sforum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("sforum_displaymode", $mode);
            }
            $displaymode = get_user_preferences("sforum_displaymode", $CFG->sforum_displaymode);
            sforum_print_mode_form($sforum->id, $displaymode, $sforum->type);
        }
    }

    if (!empty($sforum->blockafter) && !empty($sforum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $sforum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$sforum->blockperiod);
        echo $OUTPUT->notification(get_string('thissforumisthrottled', 'sforum', $a));
    }

    if ($sforum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','sforum'));
    }

    switch ($sforum->type) {
        case 'single': 
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'sforum'));
            }
            if (! $post = sforum_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'sforum');
            }
            if ($mode) {
                set_user_preference("sforum_displaymode", $mode);
            }

            $canreply    = sforum_user_can_post($sforum, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/sforum:rate', $context);
            $displaymode = get_user_preferences("sforum_displaymode", $CFG->sforum_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            sforum_print_discussion($course, $cm, $sforum, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (sforum_user_can_post_discussion($sforum, null, -1, $cm)) {
                print_string("allowsdiscussions", "sforum");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                sforum_print_latest_discussions($course, $sforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                sforum_print_latest_discussions($course, $sforum, -1, 'header', '', -1, -1, $page, $CFG->sforum_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                sforum_print_latest_discussions($course, $sforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                sforum_print_latest_discussions($course, $sforum, -1, 'header', '', -1, -1, $page, $CFG->sforum_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                sforum_print_latest_discussions($course, $sforum, 0, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, -1, 0, $cm);
            } else {
                sforum_print_latest_discussions($course, $sforum, -1, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, $page,
                    $CFG->sforum_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                sforum_print_latest_discussions($course, $sforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                sforum_print_latest_discussions($course, $sforum, -1, 'header', '', -1, -1, $page, $CFG->sforum_manydiscussions, $cm);
            }


            break;
}

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_sforum-subscriptiontoggle', 'Y.M.mod_sforum.subscriptiontoggle.init');

echo $OUTPUT->footer($course);
