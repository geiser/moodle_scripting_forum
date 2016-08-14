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
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_sforum_get_sforums_by_courses' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'get_sforums_by_courses',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Returns a list of sforum instances in a provided set of courses, if
            no courses are provided then all the sforum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/sforum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sforum_get_sforum_discussion_posts' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'get_sforum_discussion_posts',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Returns a list of sforum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/sforum:viewdiscussion, mod/sforum:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sforum_get_sforum_discussions_paginated' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'get_sforum_discussions_paginated',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Returns a list of sforum discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/sforum:viewdiscussion, mod/sforum:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sforum_view_sforum' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'view_sforum',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/sforum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sforum_view_sforum_discussion' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'view_sforum_discussion',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Trigger the sforum discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/sforum:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sforum_add_discussion_post' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/sforum:replypost',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sforum_add_discussion' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Add a new discussion into an existing sforum.',
        'type' => 'write',
        'capabilities' => 'mod/sforum:startdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_sforum_can_add_discussion' => array(
        'classname' => 'mod_sforum_external',
        'methodname' => 'can_add_discussion',
        'classpath' => 'mod/sforum/externallib.php',
        'description' => 'Check if the current user can add discussions in the given sforum (and optionally for the given group).',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);

