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
 * The mod_sforum post updated event.
 *
 * @package    mod_sforum
 * @copyright  2014 Geiser Chalco <geiser@usp.br>
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_sforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_sforum post updated event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int discussionid: The discussion id the post is part of.
 *      - int sforumid: The sforum id the post is part of.
 *      - string sforumtype: The type of sforum the post is part of.
 * }
 *
 * @package    mod_sforum
 * @since      Moodle 2.7
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_updated extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'sforum_posts';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has updated the post with id '$this->objectid' in the discussion with " .
            "id '{$this->other['discussionid']}' in the sforum with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpostupdated', 'mod_sforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        if ($this->other['sforumtype'] == 'single') {
            // Single discussion sforums are an exception. We show
            // the sforum itself since it only has one discussion
            // thread.
            $url = new \moodle_url('/mod/sforum/view.php', array('f' => $this->other['sforumid']));
        } else {
            $url = new \moodle_url('/mod/sforum/discuss.php', array('d' => $this->other['discussionid']));
        }
        $url->set_anchor('p'.$this->objectid);
        return $url;
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        // The legacy log table expects a relative path to /mod/sforum/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/mod/sforum/'));

        return array($this->courseid, 'sforum', 'update post', $logurl, $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['discussionid'])) {
            throw new \coding_exception('The \'discussionid\' value must be set in other.');
        }

        if (!isset($this->other['sforumid'])) {
            throw new \coding_exception('The \'sforumid\' value must be set in other.');
        }

        if (!isset($this->other['sforumtype'])) {
            throw new \coding_exception('The \'sforumtype\' value must be set in other.');
        }

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'sforum_posts', 'restore' => 'sforum_post');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['sforumid'] = array('db' => 'sforum', 'restore' => 'sforum');
        $othermapped['discussionid'] = array('db' => 'sforum_discussions', 'restore' => 'sforum_discussion');

        return $othermapped;
    }
}
