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
 * @package   mod_sforum
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another sforum
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$url = new moodle_url('/mod/sforum/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('sforum_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$sforum = $DB->get_record('sforum',
        array('id' => $discussion->sforum), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('sforum', $sforum->id,
        $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/sforum/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/sforum:viewdiscussion', $modcontext, NULL,
        true, 'noviewdiscussionspermission', 'sforum');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->sforum_enablerssfeeds) &&
        $sforum->rsstype && $sforum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true,
            array('context' => context_course::instance($course->id))) . ': ' .
            format_string($sforum->name);
    rss_add_http_header($modcontext, 'mod_sforum', $sforum, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/sforum/discuss.php?d='.$discussion->id;

    if (!$sforumto = $DB->get_record('sforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'sforum', $return);
    }
    require_capability('mod/sforum:movediscussions', $modcontext);

    if ($sforum->type == 'single') {
        print_error('cannotmovefromsinglesforum', 'sforum', $return);
    }

    if (!$sforumto = $DB->get_record('sforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'sforum', $return);
    }

    if ($sforumto->type == 'single') {
        print_error('cannotmovetosinglesforum', 'sforum', $return);
    }

    // Get target sforum cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $sforums = $modinfo->get_instances_of('sforum');
    if (!array_key_exists($sforumto->id, $sforums)) {
        print_error('cannotmovetonotfound', 'sforum', $return);
    }
    $cmto = $sforums[$sforumto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'sforum', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/sforum:startdiscussion', $destinationctx);

    if (!sforum_move_attachments($discussion,
            $sforum->id, $sforumto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this sforum and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_sforum\subscriptions::fetch_subscribed_users(
        $sforum,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the sforum being moved to.
    \mod_sforum\subscriptions::fill_subscription_cache($sforumto->id);
    // And also for the discussion being moved.
    \mod_sforum\subscriptions::fill_subscription_cache($sforum->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_sforum\subscriptions::is_subscribed($userid,
                $sforumto, null, $cmto);
        $discussionsubscribed = \mod_sforum\subscriptions::is_subscribed($userid,
                $sforum, $discussion->id);
        $sforumsubscribed = \mod_sforum\subscriptions::is_subscribed($userid,
                $sforum);

        if ($sforumsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause
            // them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_sforum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$sforumsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive
            // the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('sforum_discussions', 'sforum',
            $sforumto->id, array('id' => $discussion->id));
    $DB->set_field('sforum_read', 'sforumid',
            $sforumto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and
    // replace them with the newly calculated ones.
    $DB->delete_records('sforum_discussion_subs',
            array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->sforum = $sforumto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_sforum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/sforum:viewdiscussion', $destinationctx, $userid)) {
                \mod_sforum\subscriptions::subscribe_user_to_discussion($userid,
                            $newdiscussion, $destinationctx);
            }
        } else {
                \mod_sforum\subscriptions::unsubscribe_user_from_discussion($userid,
                        $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromsforumid' => $sforum->id,
            'tosforumid' => $sforumto->id,
        )
    );
    $event = \mod_sforum\event\discussion_moved::create($params);
    $event->add_record_snapshot('sforum_discussions', $discussion);
    $event->add_record_snapshot('sforum', $sforum);
    $event->add_record_snapshot('sforum', $sforumto);
    $event->trigger();

    // Delete the RSS files for the 2 sforums to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/sforum/rsslib.php');
    sforum_rss_delete_file($sforum);
    sforum_rss_delete_file($sforumto);

    redirect($return.'&move=-1&sesskey='.sesskey());
}
// Pin or unpin discussion if requested.
if ($pin !== -1 && confirm_sesskey()) {
    require_capability('mod/sforum:pindiscussions', $modcontext);

    $params = array('context' => $modcontext, 'objectid' => $discussion->id,
            'other' => array('sforumid' => $sforum->id));

    switch ($pin) {
        case FORUM_DISCUSSION_PINNED:
            // Pin the discussion and trigger discussion pinned event.
            sforum_discussion_pin($modcontext, $sforum, $discussion);
            break;
        case FORUM_DISCUSSION_UNPINNED:
            // Unpin the discussion and trigger discussion unpinned event.
            sforum_discussion_unpin($modcontext, $sforum, $discussion);
            break;
        default:
            echo $OUTPUT->notification("Invalid value when attempting to pin/unpin discussion");
            break;
    }

    redirect(new moodle_url('/mod/sforum/discuss.php', array('d' => $discussion->id)));
}

// Trigger discussion viewed event.
sforum_discussion_view($modcontext, $sforum, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('sforum_displaymode', $mode);
}

$displaymode = get_user_preferences('sforum_displaymode', $CFG->sforum_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
        $displaymode = FORUM_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = sforum_get_post_full($parent)) {
     print_error("notexists", 'sforum',
                "$CFG->wwwroot/mod/sforum/view.php?f=$sforum->id");
}

if (!sforum_user_can_see_post($sforum, $discussion, $post, null, $cm)) {
     print_error('noviewdiscussionspermission', 'sforum',
                "$CFG->wwwroot/mod/sforum/view.php?id=$sforum->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->sforum_usermarksread &&
            sforum_tp_can_track_sforums($sforum) &&
            sforum_tp_is_tracked($sforum)) {
        if ($mark == 'read') {
            sforum_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            sforum_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = sforum_search_form($course);

$sforumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($sforumnode)) {
    $sforumnode = $PAGE->navbar;
} else {
    $sforumnode->make_active();
}
$node = $sforumnode->add(format_string($discussion->name),
        new moodle_url('/mod/sforum/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_sforum');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($sforum->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) &&
        has_capability('mod/sforum:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_sforum\subscriptions::is_subscribable($sforum)) {
        echo html_writer::div(
                sforum_get_discussion_subscription_icon($sforum,
                $post->discussion, null, true),
            'discussionsubscription'
        );
        echo sforum_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this sforum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly
/// set $canreply for performance reasons

$canreply = sforum_user_can_post($sforum,
        $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $sforum->type !== 'news') {
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
$neighbours = sforum_get_discussion_neighbours($cm, $discussion, $sforum);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'],
        $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix"><div class="controlscontainer">';

if (!empty($CFG->enableportfolios) &&
    has_capability('mod/sforum:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('sforum_portfolio_caller',
            array('discussionid' => $discussion->id), 'mod_sforum');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM,
            get_string('exportdiscussion', 'mod_sforum'));
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
sforum_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($sforum->type != 'single' &&
        has_capability('mod/sforum:movediscussions', $modcontext)) {
    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other sforums. The discussion in a
    // single discussion sforum can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['sforum'])) {
        $sforummenu = array();
        // Check sforum types and eliminate simple discussions.
        $sforumcheck = $DB->get_records('sforum',
                array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['sforum'] as $sforumcm) {
           if (!$sforumcm->uservisible ||
               !has_capability('mod/sforum:startdiscussion',
                context_module::instance($sforumcm->id))) {
                continue;
            }
            $section = $sforumcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($sforummenu[$section])) {
                $sforummenu[$section] = array($sectionname => array());
            }
            $sforumidcompare = $sforumcm->instance != $sforum->id;
            $sforumtypecheck = $sforumcheck[$sforumcm->instance]->type !== 'single';
            if ($sforumidcompare and $sforumtypecheck) {
                $url = "/mod/sforum/discuss.php?d=$discussion->id&move=$sforumcm->instance&sesskey=".sesskey();
                $sforummenu[$section][$sectionname][$url] = format_string($sforumcm->name);
            }
        }
        if (!empty($sforummenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($sforummenu, '',
                    array('/mod/sforum/discuss.php?d=' .
                    $discussion->id => get_string("movethisdiscussionto", "sforum")),
                    'sforummenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}

if (has_capability('mod/sforum:pindiscussions', $modcontext)) {
    if ($discussion->pinned == FORUM_DISCUSSION_PINNED) {
        $pinlink = FORUM_DISCUSSION_UNPINNED;
        $pintext = get_string('discussionunpin', 'sforum');
    } else {
        $pinlink = FORUM_DISCUSSION_PINNED;
        $pintext = get_string('discussionpin', 'sforum');
    }
    $button = new single_button(new moodle_url('discuss.php',
            array('pin' => $pinlink, 'd' => $discussion->id)), $pintext, 'post');
    echo html_writer::tag('div', $OUTPUT->render($button),
            array('class' => 'discussioncontrol pindiscussion'));
}


echo "</div></div>";

if (!empty($sforum->blockafter) && !empty($sforum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $sforum->blockafter;
    $a->blockperiod = get_string('secondstotime'.$sforum->blockperiod);
    echo $OUTPUT->notification(get_string('thissforumisthrottled','sforum',$a));
}

if ($sforum->type == 'qanda' &&
    !has_capability('mod/sforum:viewqandawithoutposting', $modcontext) &&
    !sforum_user_has_posted($sforum->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify', 'sforum'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved',
         'sforum', format_string($sforum->name,true)), 'notifysuccess');
}

$canrate = has_capability('mod/sforum:rate', $modcontext);
sforum_print_discussion($course, $cm, $sforum,
        $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_sforum-subscriptiontoggle',
        'Y.M.mod_sforum.subscriptiontoggle.init');

echo $OUTPUT->footer();
