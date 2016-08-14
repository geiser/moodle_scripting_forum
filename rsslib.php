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
 * This file adds support to rss feeds generation
 *
 * @package   mod_sforum
 * @category rss
 * @copyright 2016 Geiser Chalco http://github.com/geiser.git
 * @copyright 2001 Eloy Lafuente (stronk7) http://contiento.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* Include the core RSS lib */
require_once($CFG->libdir.'/rsslib.php');

/**
 * Returns the path to the cached rss feed contents. Creates/updates the cache if necessary.
 * @param stdClass $context the context
 * @param array    $args    the arguments received in the url
 * @return string the full path to the cached RSS feed directory. Null if there is a problem.
 */
function sforum_rss_get_feed($context, $args) {
    global $CFG, $DB, $USER;

    $status = true;

    //are RSS feeds enabled?
    if (empty($CFG->sforum_enablerssfeeds)) {
        debugging('DISABLED (module configuration)');
        return null;
    }

    $sforumid  = clean_param($args[3], PARAM_INT);
    $cm = get_coursemodule_from_instance('sforum',
            $sforumid, 0, false, MUST_EXIST);
    $modcontext = context_module::instance($cm->id);

    //context id from db should match the submitted one
    if ($context->id != $modcontext->id ||
            !has_capability('mod/sforum:viewdiscussion', $modcontext)) {
        return null;
    }

    $sforum = $DB->get_record('sforum',
            array('id' => $sforumid), '*', MUST_EXIST);
    if (!rss_enabled_for_mod('sforum', $sforum)) {
        return null;
    }

    //the sql that will retreive the data for the feed and be hashed to get the cache filename
    list($sql, $params) = sforum_rss_get_sql($sforum, $cm);

    // Hash the sql to get the cache file name.
    $filename = rss_get_file_name($sforum, $sql, $params);
    $cachedfilepath = rss_get_file_full_name('mod_sforum', $filename);

    //Is the cache out of date?
    $cachedfilelastmodified = 0;
    if (file_exists($cachedfilepath)) {
        $cachedfilelastmodified = filemtime($cachedfilepath);
    }
    // Used to determine if we need to generate a new RSS feed.
    $dontrecheckcutoff = time() - 60; // Sixty seconds ago.

    // If it hasn't been generated we need to create it.
    // Otherwise, if it has been > 60 seconds since we last updated, check for new items.
    if (($cachedfilelastmodified == 0) || (($dontrecheckcutoff > $cachedfilelastmodified) &&
        sforum_rss_newstuff($sforum, $cm, $cachedfilelastmodified))) {
        // Need to regenerate the cached version.
        $result = sforum_rss_feed_contents($sforum, $sql, $params, $modcontext);
        $status = rss_save_file('mod_sforum', $filename, $result);
    }

    //return the path to the cached version
    return $cachedfilepath;
}

/**
 * Given a sforum object, deletes all cached RSS files associated with it.
 *
 * @param stdClass $sforum
 */
function sforum_rss_delete_file($sforum) {
    rss_delete_file('mod_sforum', $sforum);
}

///////////////////////////////////////////////////////
//Utility functions

/**
 * If there is new stuff in the sforum since $time this returns true
 * Otherwise it returns false.
 *
 * @param stdClass $sforum the sforum object
 * @param stdClass $cm    Course Module object
 * @param int      $time  check for items since this epoch timestamp
 * @return bool True for new items
 */
function sforum_rss_newstuff($sforum, $cm, $time) {
    global $DB;

    list($sql, $params) = sforum_rss_get_sql($sforum, $cm, $time);

    return $DB->record_exists_sql($sql, $params);
}

/**
 * Determines which type of SQL query is required,
 * one for posts or one for discussions, and returns the appropriate query
 *
 * @param stdClass $sforum the sforum object
 * @param stdClass $cm    Course Module object
 * @param int      $time  check for items since this epoch timestamp
 * @return string the SQL query to be used to get the Discussion/Post details from the sforum table of the database
 */
function sforum_rss_get_sql($sforum, $cm, $time=0) {
    if ($sforum->rsstype == 1) { // Discussion RSS
        return sforum_rss_feed_discussions_sql($sforum, $cm, $time);
    } else { // Post RSS
        return sforum_rss_feed_posts_sql($sforum, $cm, $time);
    }
}

/**
 * Generates the SQL query used to get the Discussion details from
 * the sforum table of the database
 *
 * @param stdClass $sforum     the sforum object
 * @param stdClass $cm        Course Module object
 * @param int      $newsince  check for items since this epoch timestamp
 * @return string the SQL query to be used to get the Discussion details from the sforum table of the database
 */
function sforum_rss_feed_discussions_sql($sforum, $cm, $newsince=0) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    $modcontext = null;

    $now = round(time(), -2);
    $params = array();

    $modcontext = context_module::instance($cm->id);

    if (!empty($CFG->sforum_enabletimedposts)) { /// Users must fulfill timed posts
        if (!has_capability('mod/sforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= :now1 AND (d.timeend = 0 OR d.timeend > :now2))";
            $params['now1'] = $now;
            $params['now2'] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = :userid";
                $params['userid'] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    // Do we only want new posts?
    if ($newsince) {
        $params['newsince'] = $newsince;
        $newsince = " AND p.modified > :newsince";
    } else {
        $newsince = '';
    }

    // Get group enforcing SQL.
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);
    list($groupselect, $groupparams) = sforum_rss_get_group_sql($cm,
            $groupmode, $currentgroup, $modcontext);

    // Add the groupparams to the params array.
    $params = array_merge($params, $groupparams);

    $sforumsort = "d.timemodified DESC";
    $postdata = "p.id AS postid, p.subject, p.created as postcreated, p.modified, p.discussion, p.userid, p.message as postmessage, p.messageformat AS postformat, p.messagetrust AS posttrust";
    $userpicturefields = user_picture::fields('u', null, 'userid');

    $sql = "SELECT $postdata, d.id as discussionid, d.name as discussionname, d.timemodified, d.usermodified, d.groupid,
                   d.timestart, d.timeend, $userpicturefields
              FROM {sforum_discussions} d
                   JOIN {sforum_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
             WHERE d.forum = {$sforum->id} AND p.parent = 0
                   $timelimit $groupselect $newsince
          ORDER BY $sforumsort";
    return array($sql, $params);
}

/**
 * Generates the SQL query used to get the Post details
 * from the sforum table of the database
 *
 * @param stdClass $sforum     the sforum object
 * @param stdClass $cm        Course Module object
 * @param int      $newsince  check for items since this epoch timestamp
 * @return string the SQL query to be used to get the Post details from the sforum table of the database
 */
function sforum_rss_feed_posts_sql($sforum, $cm, $newsince=0) {
    $modcontext = context_module::instance($cm->id);

    // Get group enforcement SQL.
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);
    $params = array();

    list($groupselect, $groupparams) = sforum_rss_get_group_sql($cm,
            $groupmode, $currentgroup, $modcontext);

    // Add the groupparams to the params array.
    $params = array_merge($params, $groupparams);

    // Do we only want new posts?
    if ($newsince) {
        $params['newsince'] = $newsince;
        $newsince = " AND p.modified > :newsince";
    } else {
        $newsince = '';
    }

    $usernamefields = get_all_user_name_fields(true, 'u');
    $sql = "SELECT p.id AS postid,
                 d.id AS discussionid,
                 d.name AS discussionname,
                 d.groupid,
                 d.timestart,
                 d.timeend,
                 u.id AS userid,
                 $usernamefields,
                 p.subject AS postsubject,
                 p.message AS postmessage,
                 p.created AS postcreated,
                 p.messageformat AS postformat,
                 p.messagetrust AS posttrust,
                 p.parent as postparent
            FROM {sforum_discussions} d,
               {sforum_posts} p,
               {user} u
            WHERE d.forum = {$sforum->id} AND
                p.discussion = d.id AND
                u.id = p.userid $newsince
                $groupselect
            ORDER BY p.created desc";

    return array($sql, $params);
}

/**
 * Retrieve the correct SQL snippet for group-only sforums
 *
 * @param stdClass $cm           Course Module object
 * @param int      $groupmode    the mode in which the sforum's groups are operating
 * @param bool     $currentgroup true if the user is from the a group enabled on the sforum
 * @param stdClass $modcontext   The context instance of the sforum module
 * @return string SQL Query for group details of the sforum
 */
function sforum_rss_get_group_sql($cm, $groupmode, $currentgroup, $modcontext=null) {
    $groupselect = '';
    $params = array();

    if ($groupmode) {
        if ($groupmode == VISIBLEGROUPS or
            has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :groupid OR d.groupid = -1)";
                $params['groupid'] = $currentgroup;
            }
        } else {
            // Separate groups without access all.
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :groupid OR d.groupid = -1)";
                $params['groupid'] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    }

    return array($groupselect, $params);
}

/**
 * This function return the XML rss contents about the sforum
 * It returns false if something is wrong
 *
 * @param stdClass $sforum the sforum object
 * @param string $sql the SQL used to retrieve the contents from the database
 * @param array $params the SQL parameters used
 * @param object $context the context this sforum relates to
 * @return bool|string false if the contents is empty, otherwise the contents of the feed is returned
 *
 * @Todo MDL-31129 implement post attachment handling
 */

function sforum_rss_feed_contents($sforum, $sql, $params, $context) {
    global $CFG, $DB, $USER;

    $status = true;
    $recs = $DB->get_recordset_sql($sql, $params, 0, $sforum->rssarticles);

    //set a flag. Are we displaying discussions or posts?
    $isdiscussion = true;
    if (!empty($sforum->rsstype) && $sforum->rsstype!=1) {
        $isdiscussion = false;
    }

    if (!$cm = get_coursemodule_from_instance('sforum',
            $sforum->id, $sforum->course)) {
        print_error('invalidcoursemodule');
    }

    $formatoptions = new stdClass();
    $items = array();
    foreach ($recs as $rec) {
            $item = new stdClass();

            $discussion = new stdClass();
            $discussion->id = $rec->discussionid;
            $discussion->groupid = $rec->groupid;
            $discussion->timestart = $rec->timestart;
            $discussion->timeend = $rec->timeend;

            $post = null;
            if (!$isdiscussion) {
                $post = new stdClass();
                $post->id = $rec->postid;
                $post->parent = $rec->postparent;
                $post->userid = $rec->userid;
            }

            if ($isdiscussion && !sforum_user_can_see_discussion($sforum,
                    $discussion, $context)) {
                // This is a discussion which the user has no permission to view
                $item->title = get_string('sforumsubjecthidden', 'sforum');
                $message = get_string('sforumbodyhidden', 'sforum');
                $item->author = get_string('sforumauthorhidden', 'sforum');
            } else if (!$isdiscussion &&
                       !sforum_user_can_see_post($sforum,
                               $discussion, $post, $USER, $cm)) {
                // This is a post which the user has no permission to view
                $item->title = get_string('sforumsubjecthidden', 'sforum');
                $message = get_string('sforumbodyhidden', 'sforum');
                $item->author = get_string('sforumauthorhidden', 'sforum');
            } else {
                // The user must have permission to view
                if ($isdiscussion && !empty($rec->discussionname)) {
                    $item->title = format_string($rec->discussionname);
                } else if (!empty($rec->postsubject)) {
                    $item->title = format_string($rec->postsubject);
                } else {
                    //we should have an item title by now but if we dont somehow then substitute something somewhat meaningful
                    $item->title = format_string($sforum->name.' '.
                                userdate($rec->postcreated,get_string('strftimedatetimeshort', 'langconfig')));
                }
                $item->author = fullname($rec);
                $message = file_rewrite_pluginfile_urls($rec->postmessage, 'pluginfile.php', $context->id,
                        'mod_sforum', 'post', $rec->postid);
                $formatoptions->trusted = $rec->posttrust;
            }

            if ($isdiscussion) {
                $item->link = $CFG->wwwroot."/mod/sforum/discuss.php?d=".$rec->discussionid;
            } else {
                $item->link = $CFG->wwwroot."/mod/sforum/discuss.php?d=".$rec->discussionid."&parent=".$rec->postid;
            }

            $formatoptions->trusted = $rec->posttrust;
            $item->description = format_text($message, $rec->postformat, $formatoptions, $sforum->course);

            //TODO: MDL-31129 implement post attachment handling
            /*if (!$isdiscussion) {
                $post_file_area_name = str_replace('//', '/', "$sforum->course/$CFG->moddata/sforum/$sforum->id/$rec->postid");
                $post_files = get_directory_list("$CFG->dataroot/$post_file_area_name");

                if (!empty($post_files)) {
                    $item->attachments = array();
                }
            }*/
            $item->pubdate = $rec->postcreated;

            $items[] = $item;
        }
    $recs->close();

    // Create the RSS header.
    $header = rss_standard_header(strip_tags(format_string($sforum->name,true)),
            $CFG->wwwroot."/mod/sforum/view.php?f=".
            $sforum->id,
            format_string($sforum->intro,true)); // TODO: fix format
    // Now all the RSS items, if there are any.
    $articles = '';
    if (!empty($items)) {
        $articles = rss_add_items($items);
    }
    // Create the RSS footer.
    $footer = rss_standard_footer();

    return $header . $articles . $footer;
}

