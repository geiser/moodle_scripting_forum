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
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_scripting_forum_get_scripting_forums_by_courses' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'get_scripting_forums_by_courses',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Returns a list of scripting_forum instances in a provided set of courses, if
            no courses are provided then all the scripting_forum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/scripting_forum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_scripting_forum_get_scripting_forum_discussion_posts' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'get_scripting_forum_discussion_posts',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Returns a list of scripting_forum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/scripting_forum:viewdiscussion, mod/scripting_forum:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_scripting_forum_get_scripting_forum_discussions_paginated' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'get_scripting_forum_discussions_paginated',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Returns a list of scripting_forum discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/scripting_forum:viewdiscussion, mod/scripting_forum:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_scripting_forum_view_scripting_forum' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'view_scripting_forum',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/scripting_forum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_scripting_forum_view_scripting_forum_discussion' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'view_scripting_forum_discussion',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Trigger the scripting_forum discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/scripting_forum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_scripting_forum_add_discussion_post' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/scripting_forum:replypost',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_scripting_forum_add_discussion' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Add a new discussion into an existing scripting_forum.',
        'type' => 'write',
        'capabilities' => 'mod/scripting_forum:startdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_scripting_forum_can_add_discussion' => array(
        'classname' => 'mod_scripting_forum_external',
        'methodname' => 'can_add_discussion',
        'classpath' => 'mod/scripting_forum/externallib.php',
        'description' => 'Check if the current user can add discussions in the given scripting_forum (and optionally for the given group).',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);

