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
 * @copyright 2016 Geiser Chalco {@link https://github.com/geiser}
 * @copyright 1999 Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/scripting_forum/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all scripting_forums

$url = new moodle_url('/mod/scripting_forum/index.php', array('id'=>$id));
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
$event = \mod_scripting_forum\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strscripting_forums       = get_string('scripting_forums', 'scripting_forum');
$strscripting_forum        = get_string('scripting_forum', 'scripting_forum');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'scripting_forum');
$strsubscribed   = get_string('subscribed', 'scripting_forum');
$strunreadposts  = get_string('unreadposts', 'scripting_forum');
$strtracking     = get_string('tracking', 'scripting_forum');
$strmarkallread  = get_string('markallread', 'scripting_forum');
$strtrackscripting_forum   = get_string('trackscripting_forum', 'scripting_forum');
$strnotrackscripting_forum = get_string('notrackscripting_forum', 'scripting_forum');
$strsubscribe    = get_string('subscribe', 'scripting_forum');
$strunsubscribe  = get_string('unsubscribe', 'scripting_forum');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = scripting_forum_search_form($course);

// Retrieve the list of scripting_forum digest options for later.
$digestoptions = scripting_forum_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/scripting_forum/maildigest.php',
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
$generaltable->head  = array ($strscripting_forum, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = scripting_forum_tp_can_track_scripting_forums()) {
    $untracked = scripting_forum_tp_get_untracked_scripting_forums($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_scripting_forum\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_scripting_forum');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->scripting_forum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->scripting_forum_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the scripting_forums.  Most scripting_forums are course modules but
// some special ones are not.  These get placed in the general scripting_forums
// category with the scripting_forums in section 0.

$scripting_forums = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {scripting_forum} f
 LEFT JOIN {scripting_forum_digests} d ON d.scripting_forum = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalscripting_forums  = array();
$learningscripting_forums = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('scripting_forum') as $scripting_forumid=>$cm) {
    if (!$cm->uservisible or !isset($scripting_forums[$scripting_forumid])) {
        continue;
    }

    $scripting_forum = $scripting_forums[$scripting_forumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/scripting_forum:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($scripting_forum->type == 'news' or $scripting_forum->type == 'social') {
        $generalscripting_forums[$scripting_forum->id] = $scripting_forum;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalscripting_forums[$scripting_forum->id] = $scripting_forum;

    } else {
        $learningscripting_forums[$scripting_forum->id] = $scripting_forum;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/scripting_forum/index.php', array('id' => $id)),
                get_string('subscribeenrolledonly', 'scripting_forum'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('scripting_forum') as $scripting_forumid=>$cm) {
        $scripting_forum = $scripting_forums[$scripting_forumid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/scripting_forum:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/scripting_forum:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_scripting_forum\subscriptions::is_forcesubscribed($scripting_forum)) {
            $subscribed = \mod_scripting_forum\subscriptions::is_subscribed($USER->id, $scripting_forum, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_scripting_forum\subscriptions::is_subscribable($scripting_forum)) && $subscribe && !$subscribed && $cansub) {
                \mod_scripting_forum\subscriptions::subscribe_user($USER->id, $scripting_forum, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_scripting_forum\subscriptions::unsubscribe_user($USER->id, $scripting_forum, $modcontext, true);
            }
        }
    }
    $returnto = scripting_forum_go_back_to(new moodle_url('/mod/scripting_forum/index.php', array('id' => $course->id)));
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect(
                $returnto,
                get_string('nowallsubscribed', 'scripting_forum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        redirect(
                $returnto,
                get_string('nowallunsubscribed', 'scripting_forum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

/// First, let's process the general scripting_forums and build up a display

if ($generalscripting_forums) {
    foreach ($generalscripting_forums as $scripting_forum) {
        $cm      = $modinfo->instances['scripting_forum'][$scripting_forum->id];
        $context = context_module::instance($cm->id);

        $count = scripting_forum_count_discussions($scripting_forum, $cm, $course);

        if ($usetracking) {
            if ($scripting_forum->trackingtype == FORUM_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$scripting_forum->id])) {
                        $unreadlink  = '-';
                } else if ($unread = scripting_forum_tp_count_scripting_forum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$scripting_forum->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $scripting_forum->id.'&amp;mark=read&amp;sesskey=' . sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($scripting_forum->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->scripting_forum_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($scripting_forum->trackingtype === FORUM_TRACKING_OFF || ($USER->trackscripting_forums == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/scripting_forum/settracking.php', array(
                            'id' => $scripting_forum->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$scripting_forum->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackscripting_forum));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackscripting_forum));
                    }
                }
            }
        }

        $scripting_forum->intro = shorten_text(format_module_intro('scripting_forum', $scripting_forum, $cm->id), $CFG->scripting_forum_shortpost);
        $scripting_forumname = format_string($scripting_forum->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $scripting_forumlink = "<a href=\"view.php?f=$scripting_forum->id\" $style>".format_string($scripting_forum->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$scripting_forum->id\" $style>".$count."</a>";

        $row = array ($scripting_forumlink, $scripting_forum->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = scripting_forum_get_subscribe_link($scripting_forum, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $scripting_forum->id);
            if ($scripting_forum->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $scripting_forum->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this scripting_forum has RSS activated, calculate it
        if ($show_rss) {
            if ($scripting_forum->rsstype and $scripting_forum->rssarticles) {
                //Calculate the tooltip text
                if ($scripting_forum->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'scripting_forum');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'scripting_forum');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_scripting_forum', $scripting_forum->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forums
$learningtable = new html_table();
$learningtable->head  = array ($strscripting_forum, $strdescription, $strdiscussions);
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

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_scripting_forum');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->scripting_forum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->scripting_forum_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning scripting_forums

if ($course->id != SITEID) {    // Only real courses have learning scripting_forums
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningscripting_forums) {
        $currentsection = '';
            foreach ($learningscripting_forums as $scripting_forum) {
            $cm      = $modinfo->instances['scripting_forum'][$scripting_forum->id];
            $context = context_module::instance($cm->id);

            $count = scripting_forum_count_discussions($scripting_forum, $cm, $course);

            if ($usetracking) {
                if ($scripting_forum->trackingtype == FORUM_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$scripting_forum->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = scripting_forum_tp_count_scripting_forum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$scripting_forum->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $scripting_forum->id.'&amp;mark=read&sesskey=' . sesskey() . '"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($scripting_forum->trackingtype == FORUM_TRACKING_FORCED) && ($CFG->scripting_forum_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($scripting_forum->trackingtype === FORUM_TRACKING_OFF || ($USER->trackscripting_forums == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/scripting_forum/settracking.php', array('id'=>$scripting_forum->id));
                        if (!isset($untracked[$scripting_forum->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackscripting_forum));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackscripting_forum));
                        }
                    }
                }
            }

            $scripting_forum->intro = shorten_text(format_module_intro('scripting_forum', $scripting_forum, $cm->id), $CFG->scripting_forum_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $scripting_forumname = format_string($scripting_forum->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $scripting_forumlink = "<a href=\"view.php?f=$scripting_forum->id\" $style>".format_string($scripting_forum->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$scripting_forum->id\" $style>".$count."</a>";

            $row = array ($printsection, $scripting_forumlink, $scripting_forum->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = scripting_forum_get_subscribe_link($scripting_forum, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $scripting_forum->id);
                if ($scripting_forum->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $scripting_forum->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this scripting_forum has RSS activated, calculate it
            if ($show_rss) {
                if ($scripting_forum->rsstype and $scripting_forum->rssarticles) {
                    //Calculate the tolltip text
                    if ($scripting_forum->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'scripting_forum');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'scripting_forum');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_scripting_forum', $scripting_forum->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strscripting_forums);
$PAGE->set_title("$course->shortname: $strscripting_forums");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/scripting_forum/index.php', array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'scripting_forum')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/scripting_forum/index.php', array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'scripting_forum')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalscripting_forums) {
    echo $OUTPUT->heading(get_string('generalscripting_forums', 'scripting_forum'), 2);
    echo html_writer::table($generaltable);
}

if ($learningscripting_forums) {
    echo $OUTPUT->heading(get_string('learningscripting_forums', 'scripting_forum'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();

