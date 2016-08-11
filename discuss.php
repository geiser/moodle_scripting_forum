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
 * @package   mod_scripting_forum
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another scripting_forum
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$url = new moodle_url('/mod/scripting_forum/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('scripting_forum_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$scripting_forum = $DB->get_record('scripting_forum', array('id' => $discussion->scripting_forum), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('scripting_forum', $scripting_forum->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/scripting_forum/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/scripting_forum:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'scripting_forum');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->scripting_forum_enablerssfeeds) && $scripting_forum->rsstype && $scripting_forum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($scripting_forum->name);
    rss_add_http_header($modcontext, 'mod_scripting_forum', $scripting_forum, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/scripting_forum/discuss.php?d='.$discussion->id;

    if (!$scripting_forumto = $DB->get_record('scripting_forum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'scripting_forum', $return);
    }

    require_capability('mod/scripting_forum:movediscussions', $modcontext);

    if ($scripting_forum->type == 'single') {
        print_error('cannotmovefromsinglescripting_forum', 'scripting_forum', $return);
    }

    if (!$scripting_forumto = $DB->get_record('scripting_forum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'scripting_forum', $return);
    }

    if ($scripting_forumto->type == 'single') {
        print_error('cannotmovetosinglescripting_forum', 'scripting_forum', $return);
    }

    // Get target scripting_forum cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $scripting_forums = $modinfo->get_instances_of('scripting_forum');
    if (!array_key_exists($scripting_forumto->id, $scripting_forums)) {
        print_error('cannotmovetonotfound', 'scripting_forum', $return);
    }
    $cmto = $scripting_forums[$scripting_forumto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'scripting_forum', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/scripting_forum:startdiscussion', $destinationctx);

    if (!scripting_forum_move_attachments($discussion, $scripting_forum->id, $scripting_forumto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this scripting_forum and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_scripting_forum\subscriptions::fetch_subscribed_users(
        $scripting_forum,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the scripting_forum being moved to.
    \mod_scripting_forum\subscriptions::fill_subscription_cache($scripting_forumto->id);
    // And also for the discussion being moved.
    \mod_scripting_forum\subscriptions::fill_subscription_cache($scripting_forum->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_scripting_forum\subscriptions::is_subscribed($userid, $scripting_forumto, null, $cmto);
        $discussionsubscribed = \mod_scripting_forum\subscriptions::is_subscribed($userid, $scripting_forum, $discussion->id);
        $scripting_forumsubscribed = \mod_scripting_forum\subscriptions::is_subscribed($userid, $scripting_forum);

        if ($scripting_forumsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_scripting_forum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$scripting_forumsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('scripting_forum_discussions', 'scripting_forum', $scripting_forumto->id, array('id' => $discussion->id));
    $DB->set_field('scripting_forum_read', 'scripting_forumid', $scripting_forumto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('scripting_forum_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->scripting_forum = $scripting_forumto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_scripting_forum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/scripting_forum:viewdiscussion', $destinationctx, $userid)) {
                \mod_scripting_forum\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_scripting_forum\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromscripting_forumid' => $scripting_forum->id,
            'toscripting_forumid' => $scripting_forumto->id,
        )
    );
    $event = \mod_scripting_forum\event\discussion_moved::create($params);
    $event->add_record_snapshot('scripting_forum_discussions', $discussion);
    $event->add_record_snapshot('scripting_forum', $scripting_forum);
    $event->add_record_snapshot('scripting_forum', $scripting_forumto);
    $event->trigger();

    // Delete the RSS files for the 2 scripting_forums to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/scripting_forum/rsslib.php');
    scripting_forum_rss_delete_file($scripting_forum);
    scripting_forum_rss_delete_file($scripting_forumto);

    redirect($return.'&move=-1&sesskey='.sesskey());
}
// Pin or unpin discussion if requested.
if ($pin !== -1 && confirm_sesskey()) {
    require_capability('mod/scripting_forum:pindiscussions', $modcontext);

    $params = array('context' => $modcontext, 'objectid' => $discussion->id, 'other' => array('scripting_forumid' => $scripting_forum->id));

    switch ($pin) {
        case FORUM_DISCUSSION_PINNED:
            // Pin the discussion and trigger discussion pinned event.
            scripting_forum_discussion_pin($modcontext, $scripting_forum, $discussion);
            break;
        case FORUM_DISCUSSION_UNPINNED:
            // Unpin the discussion and trigger discussion unpinned event.
            scripting_forum_discussion_unpin($modcontext, $scripting_forum, $discussion);
            break;
        default:
            echo $OUTPUT->notification("Invalid value when attempting to pin/unpin discussion");
            break;
    }

    redirect(new moodle_url('/mod/scripting_forum/discuss.php', array('d' => $discussion->id)));
}

// Trigger discussion viewed event.
scripting_forum_discussion_view($modcontext, $scripting_forum, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('scripting_forum_displaymode', $mode);
}

$displaymode = get_user_preferences('scripting_forum_displaymode', $CFG->scripting_forum_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
        $displaymode = FORUM_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = scripting_forum_get_post_full($parent)) {
    print_error("notexists", 'scripting_forum', "$CFG->wwwroot/mod/scripting_forum/view.php?f=$scripting_forum->id");
}

if (!scripting_forum_user_can_see_post($scripting_forum, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'scripting_forum', "$CFG->wwwroot/mod/scripting_forum/view.php?id=$scripting_forum->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->scripting_forum_usermarksread && scripting_forum_tp_can_track_scripting_forums($scripting_forum) && scripting_forum_tp_is_tracked($scripting_forum)) {
        if ($mark == 'read') {
            scripting_forum_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            scripting_forum_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = scripting_forum_search_form($course);

$scripting_forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($scripting_forumnode)) {
    $scripting_forumnode = $PAGE->navbar;
} else {
    $scripting_forumnode->make_active();
}
$node = $scripting_forumnode->add(format_string($discussion->name), new moodle_url('/mod/scripting_forum/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_scripting_forum');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($scripting_forum->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/scripting_forum:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_scripting_forum\subscriptions::is_subscribable($scripting_forum)) {
        echo html_writer::div(
            scripting_forum_get_discussion_subscription_icon($scripting_forum, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo scripting_forum_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this scripting_forum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = scripting_forum_user_can_post($scripting_forum, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $scripting_forum->type !== 'news') {
    if (isguestuser() or !isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too, they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// Output the links to neighbour discussions.
$neighbours = scripting_forum_get_discussion_neighbours($cm, $discussion, $scripting_forum);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix"><div class="controlscontainer">';

if (!empty($CFG->enableportfolios) && has_capability('mod/scripting_forum:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('scripting_forum_portfolio_caller', array('discussionid' => $discussion->id), 'mod_scripting_forum');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_scripting_forum'));
    $buttonextraclass = '';
    if (empty($button)) {
        // no portfolio plugin available.
        $button = '&nbsp;';
        $buttonextraclass = ' noavailable';
    }
    echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
} else {
    echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
}

// groups selector not needed here
echo '<div class="discussioncontrol displaymode">';
scripting_forum_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($scripting_forum->type != 'single'
            && has_capability('mod/scripting_forum:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other scripting_forums. The discussion in a
    // single discussion scripting_forum can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['scripting_forum'])) {
        $scripting_forummenu = array();
        // Check scripting_forum types and eliminate simple discussions.
        $scripting_forumcheck = $DB->get_records('scripting_forum', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['scripting_forum'] as $scripting_forumcm) {
            if (!$scripting_forumcm->uservisible || !has_capability('mod/scripting_forum:startdiscussion',
                context_module::instance($scripting_forumcm->id))) {
                continue;
            }
            $section = $scripting_forumcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($scripting_forummenu[$section])) {
                $scripting_forummenu[$section] = array($sectionname => array());
            }
            $scripting_forumidcompare = $scripting_forumcm->instance != $scripting_forum->id;
            $scripting_forumtypecheck = $scripting_forumcheck[$scripting_forumcm->instance]->type !== 'single';
            if ($scripting_forumidcompare and $scripting_forumtypecheck) {
                $url = "/mod/scripting_forum/discuss.php?d=$discussion->id&move=$scripting_forumcm->instance&sesskey=".sesskey();
                $scripting_forummenu[$section][$sectionname][$url] = format_string($scripting_forumcm->name);
            }
        }
        if (!empty($scripting_forummenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($scripting_forummenu, '',
                    array('/mod/scripting_forum/discuss.php?d=' . $discussion->id => get_string("movethisdiscussionto", "scripting_forum")),
                    'scripting_forummenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}

if (has_capability('mod/scripting_forum:pindiscussions', $modcontext)) {
    if ($discussion->pinned == FORUM_DISCUSSION_PINNED) {
        $pinlink = FORUM_DISCUSSION_UNPINNED;
        $pintext = get_string('discussionunpin', 'scripting_forum');
    } else {
        $pinlink = FORUM_DISCUSSION_PINNED;
        $pintext = get_string('discussionpin', 'scripting_forum');
    }
    $button = new single_button(new moodle_url('discuss.php', array('pin' => $pinlink, 'd' => $discussion->id)), $pintext, 'post');
    echo html_writer::tag('div', $OUTPUT->render($button), array('class' => 'discussioncontrol pindiscussion'));
}


echo "</div></div>";

if (!empty($scripting_forum->blockafter) && !empty($scripting_forum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $scripting_forum->blockafter;
    $a->blockperiod = get_string('secondstotime'.$scripting_forum->blockperiod);
    echo $OUTPUT->notification(get_string('thisscripting_forumisthrottled','scripting_forum',$a));
}

if ($scripting_forum->type == 'qanda' && !has_capability('mod/scripting_forum:viewqandawithoutposting', $modcontext) &&
            !scripting_forum_user_has_posted($scripting_forum->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify', 'scripting_forum'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'scripting_forum', format_string($scripting_forum->name,true)), 'notifysuccess');
}

$canrate = has_capability('mod/scripting_forum:rate', $modcontext);
scripting_forum_print_discussion($course, $cm, $scripting_forum, $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_scripting_forum-subscriptiontoggle', 'Y.M.mod_scripting_forum.subscriptiontoggle.init');

echo $OUTPUT->footer();
