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
 * @package   mod_scriptingforum
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB; // TODO: this is a hack, we should really do something with the SQL in SQL tables.

$logs = array(
        
array('module' => 'scriptingforum', 'action' => 'add', 'mtable' => 'scriptingforum', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'update', 'mtable' => 'scriptingforum', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'add discussion', 'mtable' => 'scriptingforum_discussions', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'add post', 'mtable' => 'scriptingforum_posts', 'field' => 'subject'),
array('module' => 'scriptingforum', 'action' => 'update post', 'mtable' => 'scriptingforum_posts', 'field' => 'subject'),
array('module' => 'scriptingforum', 'action' => 'user report', 'mtable' => 'user',
      'field'  => $DB->sql_concat('firstname', "' '", 'lastname')),
array('module' => 'scriptingforum', 'action' => 'move discussion', 'mtable' => 'scriptingforum_discussions', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'view subscribers', 'mtable' => 'scriptingforum', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'view discussion', 'mtable' => 'scriptingforum_discussions', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'view scriptingforum', 'mtable' => 'scriptingforum', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'subscribe', 'mtable' => 'scriptingforum', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'unsubscribe', 'mtable' => 'scriptingforum', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'pin discussion', 'mtable' => 'scriptingforum_discussions', 'field' => 'name'),
array('module' => 'scriptingforum', 'action' => 'unpin discussion', 'mtable' => 'scriptingforum_discussions', 'field' => 'name'),

);

