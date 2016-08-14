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
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/sforum/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all sforums

$url = new moodle_url('/mod/sforum/index.php', array('id'=>$id));
if ($subscribe !== null) {
    require_sesskey();
    $url->param('subscribe', $subscribe);
}
$PAGE->set_url($url);

if ($id) {
    if (! $course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);


unset($SESSION->fromdiscussion);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_sforum\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strsforums = get_string('sforums', 'sforum');
$strsforum  = get_string('sforum', 'sforum');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'sforum');
$strsubscribed   = get_string('subscribed', 'sforum');
$strunreadposts  = get_string('unreadposts', 'sforum');
$strtracking     = get_string('tracking', 'sforum');
$strmarkallread  = get_string('markallread', 'sforum');
$strtracksforum   = get_string('tracksforum', 'sforum');
$strnotracksforum = get_string('notracksforum', 'sforum');
$strsubscribe    = get_string('subscribe', 'sforum');
$strunsubscribe  = get_string('unsubscribe', 'sforum');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = sforum_search_form($course);

// Retrieve the list of sforum digest options for later.
$digestoptions = sforum_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/sforum/maildigest.php',
    array(
        'backtoindex' => 1,
    )),
    'maildigest',
    $digestoptions,
    null,
    '');
$digestoptions_selector->method = 'post';

// Start of the table for General Forums

$generaltable = new html_table();
$generaltable->head  = array ($strsforum, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = sforum_tp_can_track_sforums()) {
    $untracked = sforum_tp_get_untracked_sforums($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_sforum\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' .
            $OUTPUT->help_icon('emaildigesttype', 'mod_sforum');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->sforum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->sforum_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the sforums.  Most sforums are course modules but
// some special ones are not.  These get placed in the general sforums
// category with the sforums in section 0.

$sforums = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {sforum} f
 LEFT JOIN {sforum_digests} d ON d.forum = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalsforums  = array();
$learningsforums = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('sforum') as $sforumid=>$cm) {
    if (!$cm->uservisible or !isset($sforums[$sforumid])) {
        continue;
    }

    $sforum = $sforums[$sforumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/sforum:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($sforum->type == 'news' or $sforum->type == 'social') {
        $generalsforums[$sforum->id] = $sforum;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalsforums[$sforum->id] = $sforum;

    } else {
        $learningsforums[$sforum->id] = $sforum;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/sforum/index.php', array('id' => $id)),
                get_string('subscribeenrolledonly', 'sforum'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('sforum') as $sforumid=>$cm) {
        $sforum = $sforums[$sforumid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/sforum:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/sforum:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_sforum\subscriptions::is_forcesubscribed($sforum)) {
            $subscribed = \mod_sforum\subscriptions::is_subscribed($USER->id,
                        $sforum, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities',
                    $coursecontext, $USER->id);
            if (($canmanageactivities ||
                \mod_sforum\subscriptions::is_subscribable($sforum)) &&
                $subscribe && !$subscribed && $cansub) {
                \mod_sforum\subscriptions::subscribe_user($USER->id,
                                $sforum, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_sforum\subscriptions::unsubscribe_user($USER->id,
                            $sforum, $modcontext, true);
            }
        }
    }
    $returnto = sforum_go_back_to(new moodle_url('/mod/sforum/index.php',
            array('id' => $course->id)));
    $shortname = format_string($course->shortname,
            true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect(
                $returnto,
                get_string('nowallsubscribed', 'sforum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        redirect(
                $returnto,
                get_string('nowallunsubscribed', 'sforum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

/// First, let's process the general sforums and build up a display

if ($generalsforums) {
    foreach ($generalsforums as $sforum) {
        $cm      = $modinfo->instances['sforum'][$sforum->id];
        $context = context_module::instance($cm->id);

        $count = sforum_count_discussions($sforum, $cm, $course);

        if ($usetracking) {
            if ($sforum->trackingtype == FORUM_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$sforum->id])) {
                        $unreadlink  = '-';
                } else if ($unread = sforum_tp_count_sforum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$sforum->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $sforum->id.'&amp;mark=read&amp;sesskey=' . sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($sforum->trackingtype == FORUM_TRACKING_FORCED) &&
                    ($CFG->sforum_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($sforum->trackingtype === FORUM_TRACKING_OFF ||
                            ($USER->tracksforums == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/sforum/settracking.php', array(
                            'id' => $sforum->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$sforum->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl,
                        $stryes, 'post', array('title'=>$strnotracksforum));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl,
                            $strno, 'post', array('title'=>$strtracksforum));
                    }
                }
            }
        }

        $sforum->intro = shorten_text(format_module_intro('sforum',
                $sforum, $cm->id), $CFG->sforum_shortpost);
        $sforumname = format_string($sforum->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $sforumlink = "<a href=\"view.php?f=$sforum->id\" $style>".
                format_string($sforum->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$sforum->id\" $style>".
                $count."</a>";

        $row = array ($sforumlink, $sforum->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = sforum_get_subscribe_link($sforum,
                    $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $sforum->id);
            if ($sforum->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $sforum->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this sforum has RSS activated, calculate it
        if ($show_rss) {
            if ($sforum->rsstype and $sforum->rssarticles) {
                //Calculate the tooltip text
                if ($sforum->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'sforum');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'sforum');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid,
                        'mod_sforum', $sforum->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forums
$learningtable = new html_table();
$learningtable->head  = array ($strsforum, $strdescription, $strdiscussions);
$learningtable->align = array ('left', 'left', 'center');

if ($usetracking) {
    $learningtable->head[] = $strunreadposts;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $strtracking;
    $learningtable->align[] = 'center';
}

if ($can_subscribe) {
    $learningtable->head[] = $strsubscribed;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $stremaildigest . ' ' .
            $OUTPUT->help_icon('emaildigesttype', 'mod_sforum');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->sforum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->sforum_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning sforums

if ($course->id != SITEID) {    // Only real courses have learning sforums
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningsforums) {
        $currentsection = '';
            foreach ($learningsforums as $sforum) {
            $cm      = $modinfo->instances['sforum'][$sforum->id];
            $context = context_module::instance($cm->id);

            $count = sforum_count_discussions($sforum, $cm, $course);

            if ($usetracking) {
                if ($sforum->trackingtype == FORUM_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$sforum->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = sforum_tp_count_sforum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.
                                    $sforum->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                $sforum->id.'&amp;mark=read&sesskey=' .
                                sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') .
                                '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($sforum->trackingtype == FORUM_TRACKING_FORCED) &&
                            ($CFG->sforum_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($sforum->trackingtype === FORUM_TRACKING_OFF ||
                                    ($USER->tracksforums == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/sforum/settracking.php',
                                    array('id'=>$sforum->id));
                        if (!isset($untracked[$sforum->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl,
                                    $stryes, 'post', array('title'=>$strnotracksforum));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl,
                                    $strno, 'post', array('title'=>$strtracksforum));
                        }
                    }
                }
            }

            $sforum->intro = shorten_text(format_module_intro('sforum',
                    $sforum, $cm->id), $CFG->sforum_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $sforumname = format_string($sforum->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $sforumlink = "<a href=\"view.php?f=$sforum->id\" $style>".
                    format_string($sforum->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$sforum->id\" $style>".$count."</a>";

            $row = array ($printsection, $sforumlink,
                    $sforum->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = sforum_get_subscribe_link($sforum,
                            $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $sforum->id);
                if ($sforum->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $sforum->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this sforum has RSS activated, calculate it
            if ($show_rss) {
                if ($sforum->rsstype and $sforum->rssarticles) {
                    //Calculate the tolltip text
                    if ($sforum->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'sforum');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'sforum');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id,
                            'mod_sforum', $sforum->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strsforums);
$PAGE->set_title("$course->shortname: $strsforums");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/sforum/index.php',
            array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'sforum')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/sforum/index.php',
            array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'sforum')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalsforums) {
    echo $OUTPUT->heading(get_string('generalsforums', 'sforum'), 2);
    echo html_writer::table($generaltable);
}

if ($learningsforums) {
    echo $OUTPUT->heading(get_string('learningsforums', 'sforum'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();

