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

defined('MOODLE_INTERNAL') || die();

global $DB; // TODO: this is a hack, we should really do something with the SQL in SQL tables.

$logs = array(
        
array('module' => 'sforum', 'action' => 'add', 'mtable' => 'sforum', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'update', 'mtable' => 'sforum', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'add discussion', 'mtable' => 'sforum_discussions', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'add post', 'mtable' => 'sforum_posts', 'field' => 'subject'),
array('module' => 'sforum', 'action' => 'update post', 'mtable' => 'sforum_posts', 'field' => 'subject'),
array('module' => 'sforum', 'action' => 'user report', 'mtable' => 'user',
      'field'  => $DB->sql_concat('firstname', "' '", 'lastname')),
array('module' => 'sforum', 'action' => 'move discussion', 'mtable' => 'sforum_discussions', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'view subscribers', 'mtable' => 'sforum', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'view discussion', 'mtable' => 'sforum_discussions', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'view sforum', 'mtable' => 'sforum', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'subscribe', 'mtable' => 'sforum', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'unsubscribe', 'mtable' => 'sforum', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'pin discussion', 'mtable' => 'sforum_discussions', 'field' => 'name'),
array('module' => 'sforum', 'action' => 'unpin discussion', 'mtable' => 'sforum_discussions', 'field' => 'name'),

);

