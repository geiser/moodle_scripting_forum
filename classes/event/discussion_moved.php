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
 * The mod_scriptingforum discussion moved event.
 *
 * @package    mod_scriptingforum
 * @copyright  2016 Geiser Chalco <geiser@usp.br>
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scriptingforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scriptingforum discussion moved event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int fromscriptingforumid: The id of the scriptingforum the discussion is being moved from.
 *      - int toscriptingforumid: The id of the scriptingforum the discussion is being moved to.
 * }
 *
 * @package    mod_scriptingforum
 * @since      Moodle 2.7
 * @copyright  2016 Geiser Chalco <geiser@usp.br>
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_moved extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'scriptingforum_discussions';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has moved the discussion with id '$this->objectid' from the " .
            "scriptingforum with id '{$this->other['fromscriptingforumid']}' to the scriptingforum with id '{$this->other['toscriptingforumid']}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdiscussionmoved', 'mod_scriptingforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/scriptingforum/discuss.php', array('d' => $this->objectid));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'scriptingforum', 'move discussion', 'discuss.php?d=' . $this->objectid,
            $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['fromscriptingforumid'])) {
            throw new \coding_exception('The \'fromscriptingforumid\' value must be set in other.');
        }

        if (!isset($this->other['toscriptingforumid'])) {
            throw new \coding_exception('The \'toscriptingforumid\' value must be set in other.');
        }

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'scriptingforum_discussions', 'restore' => 'scriptingforum_discussion');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['fromscriptingforumid'] = array('db' => 'scriptingforum', 'restore' => 'scriptingforum');
        $othermapped['toscriptingforumid'] = array('db' => 'scriptingforum', 'restore' => 'scriptingforum');

        return $othermapped;
    }
}

