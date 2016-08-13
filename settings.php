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
 * @copyright 2016 Geiser Chalco (http://github.com/geiser)
 * @copyright 2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/scripting_forum/lib.php');

    $settings->add(new admin_setting_configselect('scripting_forum_displaymode',
            get_string('displaymode', 'scripting_forum'),
            get_string('configdisplaymode', 'scripting_forum'),
            FORUM_MODE_NESTED, scripting_forum_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('scripting_forum_replytouser',
            get_string('replytouser', 'scripting_forum'),
            get_string('configreplytouser', 'scripting_forum'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('scripting_forum_shortpost',
            get_string('shortpost', 'scripting_forum'),
            get_string('configshortpost', 'scripting_forum'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('scripting_forum_longpost',
            get_string('longpost', 'scripting_forum'),
            get_string('configlongpost', 'scripting_forum'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('scripting_forum_manydiscussions',
            get_string('manydiscussions', 'scripting_forum'),
            get_string('configmanydiscussions', 'scripting_forum'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->scripting_forum_maxbytes)) {
            $maxbytes = $CFG->scripting_forum_maxbytes;
        }
        $settings->add(new admin_setting_configselect('scripting_forum_maxbytes',
                get_string('maxattachmentsize', 'scripting_forum'),
                get_string('configmaxbytes', 'scripting_forum'), 512000,
                get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all scripting_forums
    $settings->add(new admin_setting_configtext('scripting_forum_maxattachments',
            get_string('maxattachments', 'scripting_forum'),
            get_string('configmaxattachments', 'scripting_forum'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[FORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'scripting_forum');
    $options[FORUM_TRACKING_OFF] = get_string('trackingoff', 'scripting_forum');
    $options[FORUM_TRACKING_FORCED] = get_string('trackingon', 'scripting_forum');
    $settings->add(new admin_setting_configselect('scripting_forum_trackingtype',
            get_string('trackingtype', 'scripting_forum'),
            get_string('configtrackingtype', 'scripting_forum'),
            FORUM_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('scripting_forum_trackreadposts',
            get_string('trackscripting_forum', 'scripting_forum'),
            get_string('configtrackreadposts', 'scripting_forum'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('scripting_forum_allowforcedreadtracking',
            get_string('forcedreadtracking', 'scripting_forum'),
            get_string('forcedreadtracking_desc', 'scripting_forum'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('scripting_forum_oldpostdays',
            get_string('oldpostdays', 'scripting_forum'),
            get_string('configoldpostdays', 'scripting_forum'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('scripting_forum_usermarksread',
            get_string('usermarksread', 'scripting_forum'),
            get_string('configusermarksread', 'scripting_forum'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('scripting_forum_cleanreadtime',
            get_string('cleanreadtime', 'scripting_forum'),
            get_string('configcleanreadtime', 'scripting_forum'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime',
            get_string('digestmailtime', 'scripting_forum'),
            get_string('configdigestmailtime', 'scripting_forum'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'scripting_forum').'<br />'.
               get_string('configenablerssfeedsdisabled2', 'admin');
    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'scripting_forum');
    }
    $settings->add(new admin_setting_configselect('scripting_forum_enablerssfeeds',
        get_string('enablerssfeeds', 'admin'),
        $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'scripting_forum'),
            2 => get_string('posts', 'scripting_forum')
        );
        $settings->add(new admin_setting_configselect('scripting_forum_rsstype',
                get_string('rsstypedefault', 'scripting_forum'),
                get_string('configrsstypedefault', 'scripting_forum'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('scripting_forum_rssarticles',
                get_string('rssarticles', 'scripting_forum'),
                get_string('configrssarticlesdefault', 'scripting_forum'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('scripting_forum_enabletimedposts',
            get_string('timedposts', 'scripting_forum'),
            get_string('configenabletimedposts', 'scripting_forum'), 1));
}

