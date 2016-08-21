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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_sforum_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('sforumname',
                'sforum'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('sforumintro', 'sforum'));

        $sforumtypes = sforum_get_sforum_types();
        core_collator::asort($sforumtypes, core_collator::SORT_STRING);
        $mform->addElement('select', 'type',
                get_string('sforumtype', 'sforum'), $sforumtypes);
        $mform->addHelpButton('type', 'sforumtype', 'sforum');
        $mform->setDefault('type', 'general');
        
        if ($this->_features->groupings) {
            // CL Role selector - used to select CL roles in activity
            $options = array();
            if ($groupings = $DB->get_records('groupings', array('courseid'=>$COURSE->id))) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = format_string($grouping->name);
                }
            }
            core_collator::asort($options); 
            $options = array(null => get_string('none')) + $options;
            $mform->addElement('select', 'clroles', get_string('clroles', 'sforum'), $options);
            $mform->addHelpButton('clroles', 'clroles', 'sforum');
        }
        $mform->addRule('clroles', null, 'required', null, 'client');

        $mform->addElement('textarea', 'steps', get_string('steps', 'sforum'),
                'rows="10" cols="80"');
        $mform->addHelpButton('steps', 'steps', 'sforum');
        $mform->addRule('steps', null, 'required', null, 'client');

        // Attachments and word count.
        $mform->addElement('header', 'attachmentswordcounthdr',
                get_string('attachmentswordcount', 'sforum'));

        $choices = get_max_upload_sizes($CFG->maxbytes,
                $COURSE->maxbytes, 0, $CFG->forum_maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes',
                get_string('maxattachmentsize', 'sforum'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'sforum');
        $mform->setDefault('maxbytes', $CFG->forum_maxbytes);

        $choices = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100
        );
        $mform->addElement('select', 'maxattachments',
                get_string('maxattachments', 'sforum'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'sforum');
        $mform->setDefault('maxattachments', $CFG->forum_maxattachments);

        $mform->addElement('selectyesno', 'displaywordcount',
                get_string('displaywordcount', 'sforum'));
        $mform->addHelpButton('displaywordcount', 'displaywordcount', 'sforum');
        $mform->setDefault('displaywordcount', 0);

        // Subscription and tracking.
        $mform->addElement('header', 'subscriptionandtrackinghdr',
                get_string('subscriptionandtracking', 'sforum'));

        $options = array();
        $options[FORUM_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'sforum');
        $options[FORUM_FORCESUBSCRIBE] = get_string('subscriptionforced', 'sforum');
        $options[FORUM_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'sforum');
        $options[FORUM_DISALLOWSUBSCRIBE] = get_string('subscriptiondisabled','sforum');
        $mform->addElement('select', 'forcesubscribe',
                get_string('subscriptionmode', 'sforum'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'sforum');

        $options = array();
        $options[FORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'sforum');
        $options[FORUM_TRACKING_OFF] = get_string('trackingoff', 'sforum');
        if ($CFG->forum_allowforcedreadtracking) {
            $options[FORUM_TRACKING_FORCED] = get_string('trackingon', 'sforum');
        }
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'sforum'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'sforum');
        $default = $CFG->forum_trackingtype;
        if ((!$CFG->forum_allowforcedreadtracking) && ($default == FORUM_TRACKING_FORCED)) {
            $default = FORUM_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        if ($CFG->enablerssfeeds && isset($CFG->forum_enablerssfeeds) &&
                $CFG->forum_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'sforum');
            $choices[2] = get_string('posts', 'sforum');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'sforum');
            if (isset($CFG->forum_rsstype)) {
                $mform->setDefault('rsstype', $CFG->forum_rsstype);
            }

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'sforum');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', '0');
            if (isset($CFG->forum_rssarticles)) {
                $mform->setDefault('rssarticles', $CFG->forum_rssarticles);
            }
        }

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'sforum'));
        $options = array();
        $options[0] = get_string('blockperioddisabled','sforum');
        $options[60*60*24]   = '1 '.get_string('day');
        $options[60*60*24*2] = '2 '.get_string('days');
        $options[60*60*24*3] = '3 '.get_string('days');
        $options[60*60*24*4] = '4 '.get_string('days');
        $options[60*60*24*5] = '5 '.get_string('days');
        $options[60*60*24*6] = '6 '.get_string('days');
        $options[60*60*24*7] = '1 '.get_string('week');
        $mform->addElement('select', 'blockperiod',
                get_string('blockperiod', 'sforum'), $options);
        $mform->addHelpButton('blockperiod', 'blockperiod', 'sforum');

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'sforum'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('blockafter', 'blockafter', 'sforum');
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);

        $mform->addElement('text', 'warnafter', get_string('warnafter', 'sforum'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('warnafter', 'warnafter', 'sforum');
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

        $coursecontext = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_sforum');

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();

    }

    /**
     * Hack in adds all the standard elements to a form to edit the settings for an activity module.
     */
    function standard_coursemodule_elements(){
        global $COURSE, $CFG, $DB;
        $mform =& $this->_form;

        $this->_outcomesused = false;
        if ($this->_features->outcomes) {
            if ($outcomes = grade_outcome::fetch_all_available($COURSE->id)) {
                $this->_outcomesused = true;
                $mform->addElement('header', 'modoutcomes', get_string('outcomes', 'grades'));
                foreach($outcomes as $outcome) {
                    $mform->addElement('advcheckbox', 'outcome_'.$outcome->id, $outcome->get_name());
                }
            }
        }

        if ($this->_features->rating) {
            require_once($CFG->dirroot.'/rating/lib.php');
            $rm = new rating_manager();

            $mform->addElement('header', 'modstandardratings', get_string('ratings', 'rating'));

            $permission=CAP_ALLOW;
            $rolenamestring = null;
            $isupdate = false;
            if (!empty($this->_cm)) {
                $isupdate = true;
                $context = context_module::instance($this->_cm->id);

                $rolenames = get_role_names_with_caps_in_context($context, array('moodle/rating:rate', 'mod/'.$this->_cm->modname.':rate'));
                $rolenamestring = implode(', ', $rolenames);
            } else {
                $rolenamestring = get_string('capabilitychecknotavailable','rating');
            }
            $mform->addElement('static', 'rolewarning', get_string('rolewarning','rating'), $rolenamestring);
            $mform->addHelpButton('rolewarning', 'rolewarning', 'rating');

            $mform->addElement('select', 'assessed', get_string('aggregatetype', 'rating') , $rm->get_aggregate_types());
            $mform->setDefault('assessed', 0);
            $mform->addHelpButton('assessed', 'aggregatetype', 'rating');

            $gradeoptions = array('isupdate' => $isupdate,
                                  'currentgrade' => false,
                                  'hasgrades' => false,
                                  'canrescale' => $this->_features->canrescale,
                                  'useratings' => $this->_features->rating);
            if ($isupdate) {
                $gradeitem = grade_item::fetch(array('itemtype' => 'mod',
                                                     'itemmodule' => $this->_cm->modname,
                                                     'iteminstance' => $this->_cm->instance,
                                                     'itemnumber' => 0,
                                                     'courseid' => $COURSE->id));
                if ($gradeitem) {
                    $gradeoptions['currentgrade'] = $gradeitem->grademax;
                    $gradeoptions['currentgradetype'] = $gradeitem->gradetype;
                    $gradeoptions['currentscaleid'] = $gradeitem->scaleid;
                    $gradeoptions['hasgrades'] = $gradeitem->has_grades();
                }
            }
            $mform->addElement('modgrade', 'scale', get_string('scale'), $gradeoptions);
            $mform->disabledIf('scale', 'assessed', 'eq', 0);
            $mform->addHelpButton('scale', 'modgrade', 'grades');
            $mform->setDefault('scale', $CFG->gradepointdefault);

            $mform->addElement('checkbox', 'ratingtime', get_string('ratingtime', 'rating'));
            $mform->disabledIf('ratingtime', 'assessed', 'eq', 0);

            $mform->addElement('date_time_selector', 'assesstimestart', get_string('from'));
            $mform->disabledIf('assesstimestart', 'assessed', 'eq', 0);
            $mform->disabledIf('assesstimestart', 'ratingtime');

            $mform->addElement('date_time_selector', 'assesstimefinish', get_string('to'));
            $mform->disabledIf('assesstimefinish', 'assessed', 'eq', 0);
            $mform->disabledIf('assesstimefinish', 'ratingtime');
        }

        //doing this here means splitting up the grade related settings on the lesson settings page
        //$this->standard_grading_coursemodule_elements();

        $mform->addElement('header', 'modstandardelshdr', get_string('modstandardels', 'form'));

        $mform->addElement('modvisible', 'visible', get_string('visible'));
        if (!empty($this->_cm)) {
            $context = context_module::instance($this->_cm->id);
            if (!has_capability('moodle/course:activityvisibility', $context)) {
                $mform->hardFreeze('visible');
            }
        }

        if ($this->_features->idnumber) {
            $mform->addElement('text', 'cmidnumber', get_string('idnumbermod'));
            $mform->setType('cmidnumber', PARAM_RAW);
            $mform->addHelpButton('cmidnumber', 'idnumbermod');
        }

        if ($this->_features->groups) {
            // hack to change group mode 
            $options = array(SEPARATEGROUPS => get_string('groupsseparate'));
            $mform->addElement('select', 'groupmode', get_string('groupmode', 'group'), $options, SEPARATEGROUPS);
            $mform->addHelpButton('groupmode', 'groupmode', 'group');
        }

        if ($this->_features->groupings) {
            // Groupings selector - used to select grouping for groups in activity.
            $options = array();
            if ($groupings = $DB->get_records('groupings', array('courseid'=>$COURSE->id))) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = format_string($grouping->name);
                }
            }
            core_collator::asort($options);
            $options = array(null => get_string('none')) + $options;
            $mform->addElement('select', 'groupingid', get_string('grouping', 'group'), $options);
            $mform->addHelpButton('groupingid', 'grouping', 'group');
            $mform->addRule('groupingid', null, 'required', null, 'client');
        }
        
        if (!empty($CFG->enableavailability)) {
            // Add special button to end of previous section if groups/groupings
            // are enabled.
            if ($this->_features->groups || $this->_features->groupings) {
                $mform->addElement('static', 'restrictgroupbutton', '',
                        html_writer::tag('button', get_string('restrictbygroup', 'availability'),
                        array('id' => 'restrictbygroup', 'disabled' => 'disabled')));
            }

            // Availability field. This is just a textarea; the user interface
            // interaction is all implemented in JavaScript.
            $mform->addElement('header', 'availabilityconditionsheader',
                    get_string('restrictaccess', 'availability'));
            // Note: This field cannot be named 'availability' because that
            // conflicts with fields in existing modules (such as assign).
            // So it uses a long name that will not conflict.
            $mform->addElement('textarea', 'availabilityconditionsjson',
                    get_string('accessrestrictions', 'availability'));
            // The _cm variable may not be a proper cm_info, so get one from modinfo.
            if ($this->_cm) {
                $modinfo = get_fast_modinfo($COURSE);
                $cm = $modinfo->get_cm($this->_cm->id);
            } else {
                $cm = null;
            }
            \core_availability\frontend::include_all_javascript($COURSE, $cm);
        }

        // Conditional activities: completion tracking section
        if(!isset($completion)) {
            $completion = new completion_info($COURSE);
        }
        if ($completion->is_enabled()) {
            $mform->addElement('header', 'activitycompletionheader', get_string('activitycompletion', 'completion'));

            // Unlock button for if people have completed it (will
            // be removed in definition_after_data if they haven't)
            $mform->addElement('submit', 'unlockcompletion', get_string('unlockcompletion', 'completion'));
            $mform->registerNoSubmitButton('unlockcompletion');
            $mform->addElement('hidden', 'completionunlocked', 0);
            $mform->setType('completionunlocked', PARAM_INT);

            $trackingdefault = COMPLETION_TRACKING_NONE;
            // If system and activity default is on, set it.
            if ($CFG->completiondefault && $this->_features->defaultcompletion) {
                $trackingdefault = COMPLETION_TRACKING_MANUAL;
            }

            $mform->addElement('select', 'completion', get_string('completion', 'completion'),
                array(COMPLETION_TRACKING_NONE=>get_string('completion_none', 'completion'),
                COMPLETION_TRACKING_MANUAL=>get_string('completion_manual', 'completion')));
            $mform->setDefault('completion', $trackingdefault);
            $mform->addHelpButton('completion', 'completion', 'completion');

            // Automatic completion once you view it
            $gotcompletionoptions = false;
            if (plugin_supports('mod', $this->_modname, FEATURE_COMPLETION_TRACKS_VIEWS, false)) {
                $mform->addElement('checkbox', 'completionview', get_string('completionview', 'completion'),
                    get_string('completionview_desc', 'completion'));
                $mform->disabledIf('completionview', 'completion', 'ne', COMPLETION_TRACKING_AUTOMATIC);
                $gotcompletionoptions = true;
            }

            // Automatic completion once it's graded
            if (plugin_supports('mod', $this->_modname, FEATURE_GRADE_HAS_GRADE, false)) {
                $mform->addElement('checkbox', 'completionusegrade', get_string('completionusegrade', 'completion'),
                    get_string('completionusegrade_desc', 'completion'));
                $mform->disabledIf('completionusegrade', 'completion', 'ne', COMPLETION_TRACKING_AUTOMATIC);
                $mform->addHelpButton('completionusegrade', 'completionusegrade', 'completion');
                $gotcompletionoptions = true;

                // If using the rating system, there is no grade unless ratings are enabled.
                if ($this->_features->rating) {
                    $mform->disabledIf('completionusegrade', 'assessed', 'eq', 0);
                }
            }

            // Automatic completion according to module-specific rules
            $this->_customcompletionelements = $this->add_completion_rules();
            foreach ($this->_customcompletionelements as $element) {
                $mform->disabledIf($element, 'completion', 'ne', COMPLETION_TRACKING_AUTOMATIC);
            }

            $gotcompletionoptions = $gotcompletionoptions ||
                count($this->_customcompletionelements)>0;

            // Automatic option only appears if possible
            if ($gotcompletionoptions) {
                $mform->getElement('completion')->addOption(
                    get_string('completion_automatic', 'completion'),
                    COMPLETION_TRACKING_AUTOMATIC);
            }

            // Completion expected at particular date? (For progress tracking)
            $mform->addElement('date_selector', 'completionexpected', get_string('completionexpected', 'completion'), array('optional'=>true));
            $mform->addHelpButton('completionexpected', 'completionexpected', 'completion');
            $mform->disabledIf('completionexpected', 'completion', 'eq', COMPLETION_TRACKING_NONE);
        }

        // Populate module tags.
        if (core_tag_tag::is_enabled('core', 'course_modules')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
            $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'course_modules', 'component' => 'core'));
            if ($this->_cm) {
                $tags = core_tag_tag::get_item_tags_array('core', 'course_modules', $this->_cm->id);
                $mform->setDefault('tags', $tags);
            }
        }

        $this->standard_hidden_coursemodule_elements();

        $this->plugin_extend_coursemodule_standard_elements();
    }

    function set_data($default_values) {
        global $DB;
        parent::set_data($default_values);

        // set steps
        if ($default_values->id) {
            $steps = $DB->get_records('sforum_steps',
                        array('deleted'=>0, 'forum'=>$default_values->id));
            $strsteps = '';
            foreach ($steps as $step) {
                unset($step->id);
                unset($step->forum);
                unset($step->deleted);
                // true for optional
                if ((int)$step->optional == 0) unset($step->optional);
                else $step->optional = true;
                // label for dependon
                if (empty($step->dependon)) unset($step->dependon); else {
                    $step->dependon = $DB->get_field('sforum_steps',
                                'label', array('id'=>$step->dependon));
                }
                //label for cl role
                $step->clrole = $DB->get_field('groups', 'name', array('id'=>$step->groupid));
                unset($step->groupid);
                //array list for nextsteps
                if (empty($step->nextsteps)) unset($step->nextsteps); else {
                    $nextsteps = array();
                    foreach (explode(',', $step->nextsteps) as $nextstep) {
                        $nextsteps[] = $DB->get_field('sforum_steps', 'label', array('id'=>$nextstep));
                    }
                    $step->nextsteps = $nextsteps;
                }
                
                $strsteps .= json_encode($step). "\n";
            }
            $this->_form->setDefault('steps', $strsteps);
        }

        // set CL roles
        if ($default_values->id) {
            $grouping = $DB->get_field('sforum_clroles',
                        'grouping', array('forum'=>$default_values->id));
            $this->_form->setDefault('clroles', $grouping);
        }
    }

    function definition_after_data() {
        parent::definition_after_data();
        $mform     =& $this->_form;
        $type      =& $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        //we don't want to have these appear as possible selections in the form but
        //we want the form to display them if they are set.
        if ($typevalue[0]=='news') {
            $type->addOption(get_string('namenews', 'sforum'), 'news');
            $mform->addHelpButton('type', 'namenews', 'sforum');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
        if ($typevalue[0]=='social') {
            $type->addOption(get_string('namesocial', 'sforum'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completiondiscussionsenabled']=
            !empty($default_values['completiondiscussions']) ? 1 : 0;
        if (empty($default_values['completiondiscussions'])) {
            $default_values['completiondiscussions']=1;
        }
        $default_values['completionrepliesenabled']=
            !empty($default_values['completionreplies']) ? 1 : 0;
        if (empty($default_values['completionreplies'])) {
            $default_values['completionreplies']=1;
        }
        $default_values['completionpostsenabled']=
            !empty($default_values['completionposts']) ? 1 : 0;
        if (empty($default_values['completionposts'])) {
            $default_values['completionposts']=1;
        }
    }

    function add_completion_rules() {
        $mform =& $this->_form;

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionpostsenabled',
                '', get_string('completionposts','sforum'));
        $group[] =& $mform->createElement('text', 'completionposts', '', array('size'=>3));
        $mform->setType('completionposts',PARAM_INT);
        $mform->addGroup($group, 'completionpostsgroup',
                get_string('completionpostsgroup','sforum'), array(' '), false);
        $mform->disabledIf('completionposts','completionpostsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completiondiscussionsenabled', '',
                get_string('completiondiscussions','sforum'));
        $group[] =& $mform->createElement('text', 'completiondiscussions', '', array('size'=>3));
        $mform->setType('completiondiscussions',PARAM_INT);
        $mform->addGroup($group, 'completiondiscussionsgroup',
                get_string('completiondiscussionsgroup','sforum'), array(' '), false);
        $mform->disabledIf('completiondiscussions','completiondiscussionsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionrepliesenabled', '',
                get_string('completionreplies','sforum'));
        $group[] =& $mform->createElement('text', 'completionreplies', '', array('size'=>3));
        $mform->setType('completionreplies',PARAM_INT);
        $mform->addGroup($group, 'completionrepliesgroup',
                get_string('completionrepliesgroup','sforum'), array(' '), false);
        $mform->disabledIf('completionreplies','completionrepliesenabled','notchecked');

        return array('completiondiscussionsgroup','completionrepliesgroup','completionpostsgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completiondiscussionsenabled']) && $data['completiondiscussions']!=0) ||
            (!empty($data['completionrepliesenabled']) && $data['completionreplies']!=0) ||
            (!empty($data['completionpostsenabled']) && $data['completionposts']!=0);
    }

    function get_data() {
        $data = parent::get_data();
            
        if (!$data) {
            return false;
        }
        // Turn off completion settings if the checkboxes aren't ticked
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiondiscussionsenabled) || !$autocompletion) {
                $data->completiondiscussions = 0;
            }
            if (empty($data->completionrepliesenabled) || !$autocompletion) {
                $data->completionreplies = 0;
            }
            if (empty($data->completionpostsenabled) || !$autocompletion) {
                $data->completionposts = 0;
            }
        }
        return $data;
    }
}

