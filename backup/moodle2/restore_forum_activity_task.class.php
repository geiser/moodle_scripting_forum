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
 * @package    mod_scriptingforum
 * @subpackage backup-moodle2
 * @copyright  2016 Geiser Chalco {@link http://github.com/geiser}
 * @copyright  2010 Eloy Lafuente {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/scriptingforum/backup/moodle2/restore_scriptingforum_stepslib.php'); // Because it exists (must)

/**
 * scriptingforum restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_scriptingforum_activity_task extends restore_activity_task {

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
        $this->add_step(new restore_scriptingforum_activity_structure_step('scriptingforum_structure', 'scriptingforum.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('scriptingforum', array('intro'), 'scriptingforum');
        $contents[] = new restore_decode_content('scriptingforum_posts', array('message'), 'scriptingforum_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of scriptingforums in course
        $rules[] = new restore_decode_rule('FORUMINDEX', '/mod/scriptingforum/index.php?id=$1', 'course');
        // Forum by cm->id and scriptingforum->id
        $rules[] = new restore_decode_rule('FORUMVIEWBYID', '/mod/scriptingforum/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('FORUMVIEWBYF', '/mod/scriptingforum/view.php?f=$1', 'scriptingforum');
        // Link to scriptingforum discussion
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEW', '/mod/scriptingforum/discuss.php?d=$1', 'scriptingforum_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWPARENT', '/mod/scriptingforum/discuss.php?d=$1&parent=$2',
                                           array('scriptingforum_discussion', 'scriptingforum_post'));
        $rules[] = new restore_decode_rule('FORUMDISCUSSIONVIEWINSIDE', '/mod/scriptingforum/discuss.php?d=$1#$2',
                                           array('scriptingforum_discussion', 'scriptingforum_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * scriptingforum logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('scriptingforum', 'add',
                'view.php?id={course_module}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'update',
                'view.php?id={course_module}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'view',
                'view.php?id={course_module}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'view scriptingforum',
                'view.php?id={course_module}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'mark read',
                'view.php?f={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'start tracking',
                'view.php?f={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'stop tracking',
                'view.php?f={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'subscribe',
                'view.php?f={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'unsubscribe',
                'view.php?f={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'subscriber',
                'subscribers.php?id={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'subscribers',
                'subscribers.php?id={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'view subscribers',
                'subscribers.php?id={scriptingforum}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'add discussion',
                'discuss.php?d={scriptingforum_discussion}', '{scriptingforum_discussion}');
        $rules[] = new restore_log_rule('scriptingforum', 'view discussion',
                'discuss.php?d={scriptingforum_discussion}', '{scriptingforum_discussion}');
        $rules[] = new restore_log_rule('scriptingforum', 'move discussion',
                'discuss.php?d={scriptingforum_discussion}', '{scriptingforum_discussion}');
        $rules[] = new restore_log_rule('scriptingforum', 'delete discussi',
                'view.php?id={course_module}', '{scriptingforum}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('scriptingforum', 'delete discussion',
                'view.php?id={course_module}', '{scriptingforum}');
        $rules[] = new restore_log_rule('scriptingforum', 'add post',
                'discuss.php?d={scriptingforum_discussion}&parent={scriptingforum_post}',
                '{scriptingforum_post}');
        $rules[] = new restore_log_rule('scriptingforum', 'update post',
                'discuss.php?d={scriptingforum_discussion}#p{scriptingforum_post}&parent={scriptingforum_post}', '{scriptingforum_post}');
        $rules[] = new restore_log_rule('scriptingforum', 'update post',
                'discuss.php?d={scriptingforum_discussion}&parent={scriptingforum_post}',
                '{scriptingforum_post}');
        $rules[] = new restore_log_rule('scriptingforum', 'prune post',
                'discuss.php?d={scriptingforum_discussion}', '{scriptingforum_post}');
        $rules[] = new restore_log_rule('scriptingforum', 'delete post',
                'discuss.php?d={scriptingforum_discussion}', '[post]');

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

        $rules[] = new restore_log_rule('scriptingforum', 'view scriptingforums',
                'index.php?id={course}', null);
        $rules[] = new restore_log_rule('scriptingforum', 'subscribeall',
                'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('scriptingforum', 'unsubscribeall',
                'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('scriptingforum', 'user report',
                'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('scriptingforum', 'search',
                'search.php?id={course}&search=[searchenc]', '[search]');
        return $rules;
    }
}

