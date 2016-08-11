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

defined('MOODLE_INTERNAL') || die();

global $DB; // TODO: this is a hack, we should really do something with the SQL in SQL tables.

$logs = array(
        
array('module' => 'scripting_forum', 'action' => 'add', 'mtable' => 'scripting_forum', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'update', 'mtable' => 'scripting_forum', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'add discussion', 'mtable' => 'scripting_forum_discussions', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'add post', 'mtable' => 'scripting_forum_posts', 'field' => 'subject'),
array('module' => 'scripting_forum', 'action' => 'update post', 'mtable' => 'scripting_forum_posts', 'field' => 'subject'),
array('module' => 'scripting_forum', 'action' => 'user report', 'mtable' => 'user',
      'field'  => $DB->sql_concat('firstname', "' '", 'lastname')),
array('module' => 'scripting_forum', 'action' => 'move discussion', 'mtable' => 'scripting_forum_discussions', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'view subscribers', 'mtable' => 'scripting_forum', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'view discussion', 'mtable' => 'scripting_forum_discussions', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'view scripting_forum', 'mtable' => 'scripting_forum', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'subscribe', 'mtable' => 'scripting_forum', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'unsubscribe', 'mtable' => 'scripting_forum', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'pin discussion', 'mtable' => 'scripting_forum_discussions', 'field' => 'name'),
array('module' => 'scripting_forum', 'action' => 'unpin discussion', 'mtable' => 'scripting_forum_discussions', 'field' => 'name'),

);

