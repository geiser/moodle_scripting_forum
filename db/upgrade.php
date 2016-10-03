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
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_sforum_upgrade($oldversion=0) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $result = true;

    if ($result && $oldversion < 2016092101) {
        $table = new xmldb_table('sforum_steps');
        $field = new xmldb_field('idnumber', XMLDB_TYPE_CHAR, '100', NULL, NULL, NULL, NULL, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2016092101, 'sforum');
    }
    if ($result && $oldversion < 2016092800) {
        $table = new xmldb_table('sforum_steps');
        $field = new xmldb_field('idnumber', XMLDB_TYPE_CHAR, '100', NULL, NULL, NULL, NULL, 'id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'alias');
        }
        upgrade_mod_savepoint(true, 2016092800, 'sforum');
    }
    if ($result && $oldversion < 2016092900) {
        $table = new xmldb_table('sforum');
        $field = new xmldb_field('completionsteps', XMLDB_TYPE_CHAR, '255', NULL, NULL, NULL, NULL, 'completionposts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('sforum_steps');
        $field = new xmldb_field('dependon', XMLDB_TYPE_CHAR, '255', NULL, NULL, NULL, NULL, 'description');
        $dbman->change_field_type($table, $field);
        $field = new xmldb_field('nextsteps', XMLDB_TYPE_CHAR, '255', NULL, NULL, NULL, NULL, 'dependon');
        $dbman->change_field_type($table, $field);
        upgrade_mod_savepoint(true, 2016092900, 'sforum');
    }
    if ($result && $oldversion < 2016100100) {
        $table = new xmldb_table('sforum_transitions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fromid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('toid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('forid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'individual');
        $table->add_field('optional', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('sforum_performed_transitions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('post', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('transition', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('post', XMLDB_KEY_FOREIGN, array('post'), 'sforum_posts', array('id'));
        $table->add_key('transition', XMLDB_KEY_FOREIGN, array('transition'), 'sforum_transitions', array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        $table = new xmldb_table('sforum_next_transitions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('discussion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('transition', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('discussion', XMLDB_KEY_FOREIGN, array('discussion'), 'sforum_discussions', array('id'));
        $table->add_key('transition', XMLDB_KEY_FOREIGN, array('transition'), 'sforum_transitions', array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        if ($dbman->field_exists('sforum_steps', 'deleted')) {
            $steps = $DB->get_records('sforum_steps', array('deleted'=>0));
            if (!empty($steps)) {    
                foreach ($steps as $step) {
                    if (!empty($step->dependon)) {
                        foreach (explode(',',$step->dependon) as $from) {
                            $transition = new stdClass();
                            $transition->fromid = $from;
                            $transition->toid = $step->id;
                            $transition->forid = $step->groupid;
                            if (!$DB->record_exists('sforum_transitions', array('fromid'=>$from, 'toid'=>$step->id))) {
                                $DB->insert_record('sforum_transitions', $transition);
                            }
                        }
                    }
                }
                foreach ($steps as $step) {
                    if (!empty($steps->nextsteps)) {
                        foreach (explode(',',$step->nextsteps) as $to) {
                            $transition = new stdClass();
                            $transition->fromid = $step->id;
                            $transition->toid = $to;
                            $transition->forid = $DB->get_field('sforum_steps', 'groupid', array('id'=>$to->id));
                            if (!$DB->record_exists('sforum_transitions', array('fromid'=>$from, 'toid'=>$step->id))) {
                                $DB->insert_record('sforum_transitions', $transition);
                            }
                        }
                    }
                }
            }
            $DB->delete_records('sforum_steps', array('deleted'=>1));
        }

        $table = new xmldb_table('sforum_performed_steps');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        
        $table = new xmldb_table('sforum_steps');
        $field = new xmldb_field('dependon');
        if ($dbman->field_exists($table, $field)) $dbman->drop_field($table, $field);
        $field = new xmldb_field('nextsteps');
        if ($dbman->field_exists($table, $field)) $dbman->drop_field($table, $field);
        $field = new xmldb_field('optional');
        if ($dbman->field_exists($table, $field)) $dbman->drop_field($table, $field);
        $field = new xmldb_field('deleted');
        if ($dbman->field_exists($table, $field)) $dbman->drop_field($table, $field);
        $key = new xmldb_key('discussion', XMLDB_KEY_FOREIGN, array('groupid'), 'groups', array('id'));
        $dbman->drop_key($table, $key);
        $field = new xmldb_field('groupid');
        if ($dbman->field_exists($table, $field)) $dbman->drop_field($table, $field);

        upgrade_mod_savepoint(true, 2016100100, 'sforum');
    }

    if ($result && $oldversion < 2016100108) {
        $table = new xmldb_table('sforum_transitions');
        $field = new xmldb_field('forum', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $key = new xmldb_key('forum', XMLDB_KEY_FOREIGN, array('forum'), 'sforum', array('id'));
        $dbman->add_key($table, $key);

        foreach ($DB->get_records('sforum_transitions') as $transition) {
            $transition->forum = $DB->get_field('sforum_steps', 'forum', array('id'=>$transition->toid), MUST_EXIST);
            $DB->update_record('sforum_transitions', $transition);
        }

        upgrade_mod_savepoint(true, 2016100108, 'sforum');
    }
    
    return $result;
}

