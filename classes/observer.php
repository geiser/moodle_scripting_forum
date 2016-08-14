<?php
// This file is base on part of Moodle - http://moodle.org/
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
 * Event observers used in scriptingforum.
 *
 * @package    mod_scriptingforum
 * @copyright  2016 Geiser Chalco <geiser@usp.br>
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_scriptingforum.
 */
class mod_scriptingforum_observer {

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        // NOTE: this has to be as fast as possible.
        // Get user enrolment info from event.
        $cp = (object)$event->other['userenrolment'];
        if ($cp->lastenrol) {
            if (!$scriptingforums = $DB->get_records('scriptingforum',
                        array('course' => $cp->courseid), '', 'id')) {
                return;
            }
            list($scriptingforumselect, $params) = $DB->get_in_or_equal(array_keys($scriptingforums), SQL_PARAMS_NAMED);
            $params['userid'] = $cp->userid;

            $DB->delete_records_select('scriptingforum_digests',
                    'userid = :userid AND scriptingforum '.$scriptingforumselect, $params);
            $DB->delete_records_select('scriptingforum_subscriptions',
                    'userid = :userid AND scriptingforum '.$scriptingforumselect, $params);
            $DB->delete_records_select('scriptingforum_track_prefs',
                    'userid = :userid AND scriptingforumid '.$scriptingforumselect, $params);
            $DB->delete_records_select('scriptingforum_read',
                    'userid = :userid AND scriptingforumid '.$scriptingforumselect, $params);
        }
    }

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $CFG, $DB;

        $context = context::instance_by_id($event->contextid, MUST_EXIST);

        // If contextlevel is course then only subscribe user. Role assignment
        // at course level means user is enroled in course and can subscribe to scriptingforum.
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // Forum lib required for the constant used below.
        require_once($CFG->dirroot . '/mod/scriptingforum/lib.php');

        $userid = $event->relateduserid;
        $sql = "SELECT f.id, f.course as course, cm.id AS cmid, f.forcesubscribe
                  FROM {scriptingforum} f
                  JOIN {course_modules} cm ON (cm.instance = f.id)
                  JOIN {modules} m ON (m.id = cm.module)
                  LEFT JOIN {scriptingforum_subscriptions} fs ON
                  (fs.scriptingforum = f.id AND fs.userid = :userid)
                 WHERE f.course = :courseid
                   AND f.forcesubscribe = :initial
                   AND m.name = 'scriptingforum'
                   AND fs.id IS NULL";
        $params = array('courseid' => $context->instanceid,
                'userid' => $userid, 'initial' => FORUM_INITIALSUBSCRIBE);

        $scriptingforums = $DB->get_records_sql($sql, $params);
        foreach ($scriptingforums as $scriptingforum) {
            // If user doesn't have allowforcesubscribe capability then don't subscribe.
            $modcontext = context_module::instance($scriptingforum->cmid);
            if (has_capability('mod/scriptingforum:allowforcesubscribe', $modcontext, $userid)) {
                \mod_scriptingforum\subscriptions::subscribe_user($userid,
                        $scriptingforum, $modcontext);
            }
        }
    }

    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG;

        if ($event->other['modulename'] === 'scriptingforum') {
            // Include the scriptingforum library to make use of
            // the scriptingforum_instance_created function.
            require_once($CFG->dirroot . '/mod/scriptingforum/lib.php');

            $scriptingforum = $event->get_record_snapshot('scriptingforum',
                    $event->other['instanceid']);
            scriptingforum_instance_created($event->get_context(), $scriptingforum);
        }
    }
}

