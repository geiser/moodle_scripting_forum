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
 * @copyright 1999 Geiser Chalco  {@link http://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forum ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single scripting_forum)
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
    $PAGE->set_url('/mod/scripting_forum/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('scripting_forum', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $scripting_forum = $DB->get_record("scripting_forum", array("id" => $cm->instance))) {
            print_error('invalidscripting_forumid', 'scripting_forum');
        }
        if ($scripting_forum->type == 'single') {
            $PAGE->set_pagetype('mod-scripting_forum-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strscripting_forums = get_string("modulenameplural", "scripting_forum");
        $strscripting_forum = get_string("modulename", "scripting_forum");
    } else if ($f) {

        if (! $scripting_forum = $DB->get_record("scripting_forum", array("id" => $f))) {
            print_error('invalidscripting_forumid', 'scripting_forum');
        }
        if (! $course = $DB->get_record("course", array("id" => $scripting_forum->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("scripting_forum", $scripting_forum->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strscripting_forums = get_string("modulenameplural", "scripting_forum");
        $strscripting_forum = get_string("modulename", "scripting_forum");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(scripting_forum_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->scripting_forum_enablerssfeeds) && $scripting_forum->rsstype && $scripting_forum->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($scripting_forum->name);
        rss_add_http_header($context, 'mod_scripting_forum', $scripting_forum, $rsstitle);
    }

/// Print header.

    $PAGE->set_title($scripting_forum->name);
    $PAGE->add_body_class('scripting_forumtype-'.$scripting_forum->type);
    $PAGE->set_heading($course->fullname);

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/scripting_forum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'scripting_forum'));
    }

    // Mark viewed and trigger the course_module_viewed event.
    scripting_forum_view($scripting_forum, $course, $cm, $context);

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($scripting_forum->name), 2);
    if (!empty($scripting_forum->intro) && $scripting_forum->type != 'single' && $scripting_forum->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('scripting_forum', $scripting_forum, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/scripting_forum/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion scripting_forum, we need to print the display
    // mode control.
    if ($scripting_forum->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('scripting_forum_discussions', array('scripting_forum'=>$scripting_forum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("scripting_forum_displaymode", $mode);
            }
            $displaymode = get_user_preferences("scripting_forum_displaymode", $CFG->scripting_forum_displaymode);
            scripting_forum_print_mode_form($scripting_forum->id, $displaymode, $scripting_forum->type);
        }
    }

    if (!empty($scripting_forum->blockafter) && !empty($scripting_forum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $scripting_forum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$scripting_forum->blockperiod);
        echo $OUTPUT->notification(get_string('thisscripting_forumisthrottled', 'scripting_forum', $a));
    }

    if ($scripting_forum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','scripting_forum'));
    }

    switch ($scripting_forum->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'scripting_forum'));
            }
            if (! $post = scripting_forum_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'scripting_forum');
            }
            if ($mode) {
                set_user_preference("scripting_forum_displaymode", $mode);
            }

            $canreply    = scripting_forum_user_can_post($scripting_forum, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/scripting_forum:rate', $context);
            $displaymode = get_user_preferences("scripting_forum_displaymode", $CFG->scripting_forum_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            scripting_forum_print_discussion($course, $cm, $scripting_forum, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (scripting_forum_user_can_post_discussion($scripting_forum, null, -1, $cm)) {
                print_string("allowsdiscussions", "scripting_forum");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                scripting_forum_print_latest_discussions($course, $scripting_forum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                scripting_forum_print_latest_discussions($course, $scripting_forum, -1, 'header', '', -1, -1, $page, $CFG->scripting_forum_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                scripting_forum_print_latest_discussions($course, $scripting_forum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                scripting_forum_print_latest_discussions($course, $scripting_forum, -1, 'header', '', -1, -1, $page, $CFG->scripting_forum_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                scripting_forum_print_latest_discussions($course, $scripting_forum, 0, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, -1, 0, $cm);
            } else {
                scripting_forum_print_latest_discussions($course, $scripting_forum, -1, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, $page,
                    $CFG->scripting_forum_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                scripting_forum_print_latest_discussions($course, $scripting_forum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                scripting_forum_print_latest_discussions($course, $scripting_forum, -1, 'header', '', -1, -1, $page, $CFG->scripting_forum_manydiscussions, $cm);
            }


            break;
    }

    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_scripting_forum-subscriptiontoggle', 'Y.M.mod_scripting_forum.subscriptiontoggle.init');

    echo $OUTPUT->footer($course);
