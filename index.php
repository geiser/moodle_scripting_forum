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
 * @package   mod_scriptingforum
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/scriptingforum/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all scriptingforums

$url = new moodle_url('/mod/scriptingforum/index.php', array('id'=>$id));
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
$event = \mod_scriptingforum\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strscriptingforums = get_string('scriptingforums', 'scriptingforum');
$strscriptingforum  = get_string('scriptingforum', 'scriptingforum');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'scriptingforum');
$strsubscribed   = get_string('subscribed', 'scriptingforum');
$strunreadposts  = get_string('unreadposts', 'scriptingforum');
$strtracking     = get_string('tracking', 'scriptingforum');
$strmarkallread  = get_string('markallread', 'scriptingforum');
$strtrackscriptingforum   = get_string('trackscriptingforum', 'scriptingforum');
$strnotrackscriptingforum = get_string('notrackscriptingforum', 'scriptingforum');
$strsubscribe    = get_string('subscribe', 'scriptingforum');
$strunsubscribe  = get_string('unsubscribe', 'scriptingforum');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = scriptingforum_search_form($course);

// Retrieve the list of scriptingforum digest options for later.
$digestoptions = scriptingforum_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/scriptingforum/maildigest.php',
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
$generaltable->head  = array ($strscriptingforum, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = scriptingforum_tp_can_track_scriptingforums()) {
    $untracked = scriptingforum_tp_get_untracked_scriptingforums($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_scriptingforum\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' .
            $OUTPUT->help_icon('emaildigesttype', 'mod_scriptingforum');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->scriptingforum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->scriptingforum_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the scriptingforums.  Most scriptingforums are course modules but
// some special ones are not.  These get placed in the general scriptingforums
// category with the scriptingforums in section 0.

$scriptingforums = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {scriptingforum} f
 LEFT JOIN {scriptingforum_digests} d ON d.forum = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalscriptingforums  = array();
$learningscriptingforums = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('scriptingforum') as $scriptingforumid=>$cm) {
    if (!$cm->uservisible or !isset($scriptingforums[$scriptingforumid])) {
        continue;
    }

    $scriptingforum = $scriptingforums[$scriptingforumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/scriptingforum:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($scriptingforum->type == 'news' or $scriptingforum->type == 'social') {
        $generalscriptingforums[$scriptingforum->id] = $scriptingforum;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalscriptingforums[$scriptingforum->id] = $scriptingforum;

    } else {
        $learningscriptingforums[$scriptingforum->id] = $scriptingforum;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/scriptingforum/index.php', array('id' => $id)),
                get_string('subscribeenrolledonly', 'scriptingforum'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('scriptingforum') as $scriptingforumid=>$cm) {
        $scriptingforum = $scriptingforums[$scriptingforumid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/scriptingforum:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/scriptingforum:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_scriptingforum\subscriptions::is_forcesubscribed($scriptingforum)) {
            $subscribed = \mod_scriptingforum\subscriptions::is_subscribed($USER->id,
                        $scriptingforum, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities',
                    $coursecontext, $USER->id);
            if (($canmanageactivities ||
                \mod_scriptingforum\subscriptions::is_subscribable($scriptingforum)) &&
                $subscribe && !$subscribed && $cansub) {
                \mod_scriptingforum\subscriptions::subscribe_user($USER->id,
                                $scriptingforum, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_scriptingforum\subscriptions::unsubscribe_user($USER->id,
                            $scriptingforum, $modcontext, true);
            }
        }
    }
    $returnto = scriptingforum_go_back_to(new moodle_url('/mod/scriptingforum/index.php',
            array('id' => $course->id)));
    $shortname = format_string($course->shortname,
            true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect(
                $returnto,
                get_string('nowallsubscribed', 'scriptingforum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        redirect(
                $returnto,
                get_string('nowallunsubscribed', 'scriptingforum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

/// First, let's process the general scriptingforums and build up a display

if ($generalscriptingforums) {
    foreach ($generalscriptingforums as $scriptingforum) {
        $cm      = $modinfo->instances['scriptingforum'][$scriptingforum->id];
        $context = context_module::instance($cm->id);

        $count = scriptingforum_count_discussions($scriptingforum, $cm, $course);

        if ($usetracking) {
            if ($scriptingforum->trackingtype == FORUM_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$scriptingforum->id])) {
                        $unreadlink  = '-';
                } else if ($unread = scriptingforum_tp_count_scriptingforum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$scriptingforum->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $scriptingforum->id.'&amp;mark=read&amp;sesskey=' . sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($scriptingforum->trackingtype == FORUM_TRACKING_FORCED) &&
                    ($CFG->scriptingforum_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($scriptingforum->trackingtype === FORUM_TRACKING_OFF ||
                            ($USER->trackscriptingforums == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/scriptingforum/settracking.php', array(
                            'id' => $scriptingforum->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$scriptingforum->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl,
                        $stryes, 'post', array('title'=>$strnotrackscriptingforum));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl,
                            $strno, 'post', array('title'=>$strtrackscriptingforum));
                    }
                }
            }
        }

        $scriptingforum->intro = shorten_text(format_module_intro('scriptingforum',
                $scriptingforum, $cm->id), $CFG->scriptingforum_shortpost);
        $scriptingforumname = format_string($scriptingforum->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $scriptingforumlink = "<a href=\"view.php?f=$scriptingforum->id\" $style>".
                format_string($scriptingforum->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$scriptingforum->id\" $style>".
                $count."</a>";

        $row = array ($scriptingforumlink, $scriptingforum->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = scriptingforum_get_subscribe_link($scriptingforum,
                    $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $scriptingforum->id);
            if ($scriptingforum->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $scriptingforum->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this scriptingforum has RSS activated, calculate it
        if ($show_rss) {
            if ($scriptingforum->rsstype and $scriptingforum->rssarticles) {
                //Calculate the tooltip text
                if ($scriptingforum->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'scriptingforum');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'scriptingforum');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid,
                        'mod_scriptingforum', $scriptingforum->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forums
$learningtable = new html_table();
$learningtable->head  = array ($strscriptingforum, $strdescription, $strdiscussions);
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
            $OUTPUT->help_icon('emaildigesttype', 'mod_scriptingforum');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->scriptingforum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->scriptingforum_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning scriptingforums

if ($course->id != SITEID) {    // Only real courses have learning scriptingforums
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningscriptingforums) {
        $currentsection = '';
            foreach ($learningscriptingforums as $scriptingforum) {
            $cm      = $modinfo->instances['scriptingforum'][$scriptingforum->id];
            $context = context_module::instance($cm->id);

            $count = scriptingforum_count_discussions($scriptingforum, $cm, $course);

            if ($usetracking) {
                if ($scriptingforum->trackingtype == FORUM_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$scriptingforum->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = scriptingforum_tp_count_scriptingforum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.
                                    $scriptingforum->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                $scriptingforum->id.'&amp;mark=read&sesskey=' .
                                sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') .
                                '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($scriptingforum->trackingtype == FORUM_TRACKING_FORCED) &&
                            ($CFG->scriptingforum_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($scriptingforum->trackingtype === FORUM_TRACKING_OFF ||
                                    ($USER->trackscriptingforums == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/scriptingforum/settracking.php',
                                    array('id'=>$scriptingforum->id));
                        if (!isset($untracked[$scriptingforum->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl,
                                    $stryes, 'post', array('title'=>$strnotrackscriptingforum));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl,
                                    $strno, 'post', array('title'=>$strtrackscriptingforum));
                        }
                    }
                }
            }

            $scriptingforum->intro = shorten_text(format_module_intro('scriptingforum',
                    $scriptingforum, $cm->id), $CFG->scriptingforum_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $scriptingforumname = format_string($scriptingforum->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $scriptingforumlink = "<a href=\"view.php?f=$scriptingforum->id\" $style>".
                    format_string($scriptingforum->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$scriptingforum->id\" $style>".$count."</a>";

            $row = array ($printsection, $scriptingforumlink,
                    $scriptingforum->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = scriptingforum_get_subscribe_link($scriptingforum,
                            $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $scriptingforum->id);
                if ($scriptingforum->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $scriptingforum->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this scriptingforum has RSS activated, calculate it
            if ($show_rss) {
                if ($scriptingforum->rsstype and $scriptingforum->rssarticles) {
                    //Calculate the tolltip text
                    if ($scriptingforum->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'scriptingforum');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'scriptingforum');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id,
                            'mod_scriptingforum', $scriptingforum->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strscriptingforums);
$PAGE->set_title("$course->shortname: $strscriptingforums");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/scriptingforum/index.php',
            array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'scriptingforum')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/scriptingforum/index.php',
            array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'scriptingforum')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalscriptingforums) {
    echo $OUTPUT->heading(get_string('generalscriptingforums', 'scriptingforum'), 2);
    echo html_writer::table($generaltable);
}

if ($learningscriptingforums) {
    echo $OUTPUT->heading(get_string('learningscriptingforums', 'scriptingforum'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();

