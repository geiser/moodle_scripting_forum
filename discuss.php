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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package   mod_scriptingforum
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another scriptingforum
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$url = new moodle_url('/mod/scriptingforum/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('scriptingforum_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$scriptingforum = $DB->get_record('scriptingforum',
        array('id' => $discussion->scriptingforum), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('scriptingforum', $scriptingforum->id,
        $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/scriptingforum/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/scriptingforum:viewdiscussion', $modcontext, NULL,
        true, 'noviewdiscussionspermission', 'scriptingforum');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->scriptingforum_enablerssfeeds) &&
        $scriptingforum->rsstype && $scriptingforum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true,
            array('context' => context_course::instance($course->id))) . ': ' .
            format_string($scriptingforum->name);
    rss_add_http_header($modcontext, 'mod_scriptingforum', $scriptingforum, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/scriptingforum/discuss.php?d='.$discussion->id;

    if (!$scriptingforumto = $DB->get_record('scriptingforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'scriptingforum', $return);
    }
    require_capability('mod/scriptingforum:movediscussions', $modcontext);

    if ($scriptingforum->type == 'single') {
        print_error('cannotmovefromsinglescriptingforum', 'scriptingforum', $return);
    }

    if (!$scriptingforumto = $DB->get_record('scriptingforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'scriptingforum', $return);
    }

    if ($scriptingforumto->type == 'single') {
        print_error('cannotmovetosinglescriptingforum', 'scriptingforum', $return);
    }

    // Get target scriptingforum cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $scriptingforums = $modinfo->get_instances_of('scriptingforum');
    if (!array_key_exists($scriptingforumto->id, $scriptingforums)) {
        print_error('cannotmovetonotfound', 'scriptingforum', $return);
    }
    $cmto = $scriptingforums[$scriptingforumto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'scriptingforum', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/scriptingforum:startdiscussion', $destinationctx);

    if (!scriptingforum_move_attachments($discussion,
            $scriptingforum->id, $scriptingforumto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this scriptingforum and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_scriptingforum\subscriptions::fetch_subscribed_users(
        $scriptingforum,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the scriptingforum being moved to.
    \mod_scriptingforum\subscriptions::fill_subscription_cache($scriptingforumto->id);
    // And also for the discussion being moved.
    \mod_scriptingforum\subscriptions::fill_subscription_cache($scriptingforum->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_scriptingforum\subscriptions::is_subscribed($userid,
                $scriptingforumto, null, $cmto);
        $discussionsubscribed = \mod_scriptingforum\subscriptions::is_subscribed($userid,
                $scriptingforum, $discussion->id);
        $scriptingforumsubscribed = \mod_scriptingforum\subscriptions::is_subscribed($userid,
                $scriptingforum);

        if ($scriptingforumsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause
            // them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_scriptingforum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$scriptingforumsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive
            // the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('scriptingforum_discussions', 'scriptingforum',
            $scriptingforumto->id, array('id' => $discussion->id));
    $DB->set_field('scriptingforum_read', 'scriptingforumid',
            $scriptingforumto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and
    // replace them with the newly calculated ones.
    $DB->delete_records('scriptingforum_discussion_subs',
            array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->scriptingforum = $scriptingforumto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_scriptingforum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/scriptingforum:viewdiscussion', $destinationctx, $userid)) {
                \mod_scriptingforum\subscriptions::subscribe_user_to_discussion($userid,
                            $newdiscussion, $destinationctx);
            }
        } else {
                \mod_scriptingforum\subscriptions::unsubscribe_user_from_discussion($userid,
                        $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromscriptingforumid' => $scriptingforum->id,
            'toscriptingforumid' => $scriptingforumto->id,
        )
    );
    $event = \mod_scriptingforum\event\discussion_moved::create($params);
    $event->add_record_snapshot('scriptingforum_discussions', $discussion);
    $event->add_record_snapshot('scriptingforum', $scriptingforum);
    $event->add_record_snapshot('scriptingforum', $scriptingforumto);
    $event->trigger();

    // Delete the RSS files for the 2 scriptingforums to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/scriptingforum/rsslib.php');
    scriptingforum_rss_delete_file($scriptingforum);
    scriptingforum_rss_delete_file($scriptingforumto);

    redirect($return.'&move=-1&sesskey='.sesskey());
}
// Pin or unpin discussion if requested.
if ($pin !== -1 && confirm_sesskey()) {
    require_capability('mod/scriptingforum:pindiscussions', $modcontext);

    $params = array('context' => $modcontext, 'objectid' => $discussion->id,
            'other' => array('scriptingforumid' => $scriptingforum->id));

    switch ($pin) {
        case FORUM_DISCUSSION_PINNED:
            // Pin the discussion and trigger discussion pinned event.
            scriptingforum_discussion_pin($modcontext, $scriptingforum, $discussion);
            break;
        case FORUM_DISCUSSION_UNPINNED:
            // Unpin the discussion and trigger discussion unpinned event.
            scriptingforum_discussion_unpin($modcontext, $scriptingforum, $discussion);
            break;
        default:
            echo $OUTPUT->notification("Invalid value when attempting to pin/unpin discussion");
            break;
    }

    redirect(new moodle_url('/mod/scriptingforum/discuss.php', array('d' => $discussion->id)));
}

// Trigger discussion viewed event.
scriptingforum_discussion_view($modcontext, $scriptingforum, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('scriptingforum_displaymode', $mode);
}

$displaymode = get_user_preferences('scriptingforum_displaymode', $CFG->scriptingforum_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
        $displaymode = FORUM_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = scriptingforum_get_post_full($parent)) {
     print_error("notexists", 'scriptingforum',
                "$CFG->wwwroot/mod/scriptingforum/view.php?f=$scriptingforum->id");
}

if (!scriptingforum_user_can_see_post($scriptingforum, $discussion, $post, null, $cm)) {
     print_error('noviewdiscussionspermission', 'scriptingforum',
                "$CFG->wwwroot/mod/scriptingforum/view.php?id=$scriptingforum->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->scriptingforum_usermarksread &&
            scriptingforum_tp_can_track_scriptingforums($scriptingforum) &&
            scriptingforum_tp_is_tracked($scriptingforum)) {
        if ($mark == 'read') {
            scriptingforum_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            scriptingforum_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = scriptingforum_search_form($course);

$scriptingforumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($scriptingforumnode)) {
    $scriptingforumnode = $PAGE->navbar;
} else {
    $scriptingforumnode->make_active();
}
$node = $scriptingforumnode->add(format_string($discussion->name),
        new moodle_url('/mod/scriptingforum/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_scriptingforum');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($scriptingforum->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) &&
        has_capability('mod/scriptingforum:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_scriptingforum\subscriptions::is_subscribable($scriptingforum)) {
        echo html_writer::div(
                scriptingforum_get_discussion_subscription_icon($scriptingforum,
                $post->discussion, null, true),
            'discussionsubscription'
        );
        echo scriptingforum_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this scriptingforum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly
/// set $canreply for performance reasons

$canreply = scriptingforum_user_can_post($scriptingforum,
        $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $scriptingforum->type !== 'news') {
    if (isguestuser() or !isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link -
        // they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too,
        // they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// Output the links to neighbour discussions.
$neighbours = scriptingforum_get_discussion_neighbours($cm, $discussion, $scriptingforum);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'],
        $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix"><div class="controlscontainer">';

if (!empty($CFG->enableportfolios) &&
    has_capability('mod/scriptingforum:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('scriptingforum_portfolio_caller',
            array('discussionid' => $discussion->id), 'mod_scriptingforum');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM,
            get_string('exportdiscussion', 'mod_scriptingforum'));
    $buttonextraclass = '';
    if (empty($button)) {
        // no portfolio plugin available.
        $button = '&nbsp;';
        $buttonextraclass = ' noavailable';
    }
    echo html_writer::tag('div', $button,
            array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
} else {
    echo html_writer::tag('div', '&nbsp;',
            array('class'=>'discussioncontrol nullcontrol'));
}

// groups selector not needed here
echo '<div class="discussioncontrol displaymode">';
scriptingforum_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($scriptingforum->type != 'single' &&
        has_capability('mod/scriptingforum:movediscussions', $modcontext)) {
    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other scriptingforums. The discussion in a
    // single discussion scriptingforum can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['scriptingforum'])) {
        $scriptingforummenu = array();
        // Check scriptingforum types and eliminate simple discussions.
        $scriptingforumcheck = $DB->get_records('scriptingforum',
                array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['scriptingforum'] as $scriptingforumcm) {
           if (!$scriptingforumcm->uservisible ||
               !has_capability('mod/scriptingforum:startdiscussion',
                context_module::instance($scriptingforumcm->id))) {
                continue;
            }
            $section = $scriptingforumcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($scriptingforummenu[$section])) {
                $scriptingforummenu[$section] = array($sectionname => array());
            }
            $scriptingforumidcompare = $scriptingforumcm->instance != $scriptingforum->id;
            $scriptingforumtypecheck = $scriptingforumcheck[$scriptingforumcm->instance]->type !== 'single';
            if ($scriptingforumidcompare and $scriptingforumtypecheck) {
                $url = "/mod/scriptingforum/discuss.php?d=$discussion->id&move=$scriptingforumcm->instance&sesskey=".sesskey();
                $scriptingforummenu[$section][$sectionname][$url] = format_string($scriptingforumcm->name);
            }
        }
        if (!empty($scriptingforummenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($scriptingforummenu, '',
                    array('/mod/scriptingforum/discuss.php?d=' .
                    $discussion->id => get_string("movethisdiscussionto", "scriptingforum")),
                    'scriptingforummenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}

if (has_capability('mod/scriptingforum:pindiscussions', $modcontext)) {
    if ($discussion->pinned == FORUM_DISCUSSION_PINNED) {
        $pinlink = FORUM_DISCUSSION_UNPINNED;
        $pintext = get_string('discussionunpin', 'scriptingforum');
    } else {
        $pinlink = FORUM_DISCUSSION_PINNED;
        $pintext = get_string('discussionpin', 'scriptingforum');
    }
    $button = new single_button(new moodle_url('discuss.php',
            array('pin' => $pinlink, 'd' => $discussion->id)), $pintext, 'post');
    echo html_writer::tag('div', $OUTPUT->render($button),
            array('class' => 'discussioncontrol pindiscussion'));
}


echo "</div></div>";

if (!empty($scriptingforum->blockafter) && !empty($scriptingforum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $scriptingforum->blockafter;
    $a->blockperiod = get_string('secondstotime'.$scriptingforum->blockperiod);
    echo $OUTPUT->notification(get_string('thisscriptingforumisthrottled','scriptingforum',$a));
}

if ($scriptingforum->type == 'qanda' &&
    !has_capability('mod/scriptingforum:viewqandawithoutposting', $modcontext) &&
    !scriptingforum_user_has_posted($scriptingforum->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify', 'scriptingforum'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved',
         'scriptingforum', format_string($scriptingforum->name,true)), 'notifysuccess');
}

$canrate = has_capability('mod/scriptingforum:rate', $modcontext);
scriptingforum_print_discussion($course, $cm, $scriptingforum,
        $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_scriptingforum-subscriptiontoggle',
        'Y.M.mod_scriptingforum.subscriptiontoggle.init');

echo $OUTPUT->footer();
