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
 * @package    mod_sforum
 * @subpackage backup-moodle2
 * @copyright  2016 Geiser Chalco {@link http://github.com/geiser}
 * @copyright  2010 Eloy Lafuente {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/sforum/backup/moodle2/restore_sforum_stepslib.php'); // Because it exists (must)

/**
 * sforum restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_sforum_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_sforum_activity_structure_step('sforum_structure', 'sforum.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('sforum', array('intro'), 'sforum');
        $contents[] = new restore_decode_content('sforum_posts', array('message'), 'sforum_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of sforums in course
        $rules[] = new restore_decode_rule('FORUMINDEX', '/mod/sforum/index.php?id=$1', 'course');
        // Forum by cm->id and sforum->id
        $rules[] = new restore_decode_rule('FORUMVIEWBYID', '/mod/sforum/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('FORUMVIEWBYF', '/mod/sforum/view.php?f=$1', 'sforum');
        // Link to sforum discussion
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEW', '/mod/sforum/discuss.php?d=$1', 'sforum_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWPARENT', '/mod/sforum/discuss.php?d=$1&parent=$2',
                                           array('sforum_discussion', 'sforum_post'));
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWINSIDE', '/mod/sforum/discuss.php?d=$1#$2',
                                           array('sforum_discussion', 'sforum_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * sforum logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('sforum', 'add',
                'view.php?id={course_module}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'update',
                'view.php?id={course_module}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'view',
                'view.php?id={course_module}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'view sforum',
                'view.php?id={course_module}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'mark read',
                'view.php?f={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'start tracking',
                'view.php?f={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'stop tracking',
                'view.php?f={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'subscribe',
                'view.php?f={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'unsubscribe',
                'view.php?f={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'subscriber',
                'subscribers.php?id={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'subscribers',
                'subscribers.php?id={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'view subscribers',
                'subscribers.php?id={sforum}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'add discussion',
                'discuss.php?d={sforum_discussion}', '{sforum_discussion}');
        $rules[] = new restore_log_rule('sforum', 'view discussion',
                'discuss.php?d={sforum_discussion}', '{sforum_discussion}');
        $rules[] = new restore_log_rule('sforum', 'move discussion',
                'discuss.php?d={sforum_discussion}', '{sforum_discussion}');
        $rules[] = new restore_log_rule('sforum', 'delete discussi',
                'view.php?id={course_module}', '{sforum}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('sforum', 'delete discussion',
                'view.php?id={course_module}', '{sforum}');
        $rules[] = new restore_log_rule('sforum', 'add post',
                'discuss.php?d={sforum_discussion}&parent={sforum_post}',
                '{sforum_post}');
        $rules[] = new restore_log_rule('sforum', 'update post',
                'discuss.php?d={sforum_discussion}#p{sforum_post}&parent={sforum_post}', '{sforum_post}');
        $rules[] = new restore_log_rule('sforum', 'update post',
                'discuss.php?d={sforum_discussion}&parent={sforum_post}',
                '{sforum_post}');
        $rules[] = new restore_log_rule('sforum', 'prune post',
                'discuss.php?d={sforum_discussion}', '{sforum_post}');
        $rules[] = new restore_log_rule('sforum', 'delete post',
                'discuss.php?d={sforum_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('sforum', 'view sforums',
                'index.php?id={course}', null);
        $rules[] = new restore_log_rule('sforum', 'subscribeall',
                'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('sforum', 'unsubscribeall',
                'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('sforum', 'user report',
                'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('sforum', 'search',
                'search.php?id={course}&search=[searchenc]', '[search]');
        return $rules;
    }
}

