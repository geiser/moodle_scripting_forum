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

    return $result;
}

