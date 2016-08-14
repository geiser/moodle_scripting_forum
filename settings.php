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
 * @copyright 2016 Geiser Chalco (http://github.com/geiser)
 * @copyright 2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/sforum/lib.php');

    $settings->add(new admin_setting_configselect('sforum_displaymode',
            get_string('displaymode', 'sforum'),
            get_string('configdisplaymode', 'sforum'),
            FORUM_MODE_NESTED, sforum_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('sforum_replytouser',
            get_string('replytouser', 'sforum'),
            get_string('configreplytouser', 'sforum'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('sforum_shortpost',
            get_string('shortpost', 'sforum'),
            get_string('configshortpost', 'sforum'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('sforum_longpost',
            get_string('longpost', 'sforum'),
            get_string('configlongpost', 'sforum'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('sforum_manydiscussions',
            get_string('manydiscussions', 'sforum'),
            get_string('configmanydiscussions', 'sforum'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->sforum_maxbytes)) {
            $maxbytes = $CFG->sforum_maxbytes;
        }
        $settings->add(new admin_setting_configselect('sforum_maxbytes',
                get_string('maxattachmentsize', 'sforum'),
                get_string('configmaxbytes', 'sforum'), 512000,
                get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all sforums
    $settings->add(new admin_setting_configtext('sforum_maxattachments',
            get_string('maxattachments', 'sforum'),
            get_string('configmaxattachments', 'sforum'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[FORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'sforum');
    $options[FORUM_TRACKING_OFF] = get_string('trackingoff', 'sforum');
    $options[FORUM_TRACKING_FORCED] = get_string('trackingon', 'sforum');
    $settings->add(new admin_setting_configselect('sforum_trackingtype',
            get_string('trackingtype', 'sforum'),
            get_string('configtrackingtype', 'sforum'),
            FORUM_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('sforum_trackreadposts',
            get_string('tracksforum', 'sforum'),
            get_string('configtrackreadposts', 'sforum'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('sforum_allowforcedreadtracking',
            get_string('forcedreadtracking', 'sforum'),
            get_string('forcedreadtracking_desc', 'sforum'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('sforum_oldpostdays',
            get_string('oldpostdays', 'sforum'),
            get_string('configoldpostdays', 'sforum'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('sforum_usermarksread',
            get_string('usermarksread', 'sforum'),
            get_string('configusermarksread', 'sforum'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('sforum_cleanreadtime',
            get_string('cleanreadtime', 'sforum'),
            get_string('configcleanreadtime', 'sforum'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime',
            get_string('digestmailtime', 'sforum'),
            get_string('configdigestmailtime', 'sforum'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'sforum').'<br />'.
               get_string('configenablerssfeedsdisabled2', 'admin');
    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'sforum');
    }
    $settings->add(new admin_setting_configselect('sforum_enablerssfeeds',
        get_string('enablerssfeeds', 'admin'),
        $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'sforum'),
            2 => get_string('posts', 'sforum')
        );
        $settings->add(new admin_setting_configselect('sforum_rsstype',
                get_string('rsstypedefault', 'sforum'),
                get_string('configrsstypedefault', 'sforum'), 0, $options));

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
        $settings->add(new admin_setting_configselect('sforum_rssarticles',
                get_string('rssarticles', 'sforum'),
                get_string('configrssarticlesdefault', 'sforum'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('sforum_enabletimedposts',
            get_string('timedposts', 'sforum'),
            get_string('configenabletimedposts', 'sforum'), 1));
}

