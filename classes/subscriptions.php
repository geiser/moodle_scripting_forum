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
 * Forum subscription manager.
 *
 * @package    mod_scripting_forum
 * @copyright  2016 Geiser Chalco <geiser@usp.br>
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scripting_forum;

defined('MOODLE_INTERNAL') || die();

/**
 * Forum subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const FORUM_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for scripting_forums.
     *
     * The first level key is the user ID
     * The second level is the scripting_forum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $scripting_forumcache = array();

    /**
     * The list of scripting_forums which have been wholly retrieved for the scripting_forum subscription cache.
     *
     * This allows for prior caching of an entire scripting_forum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedscripting_forums = array();

    /**
     * The subscription cache for scripting_forum discussions.
     *
     * The first level key is the user ID
     * The second level is the scripting_forum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $scripting_forumdiscussioncache = array();

    /**
     * The list of scripting_forums which have been wholly retrieved for the scripting_forum discussion subscription cache.
     *
     * This allows for prior caching of an entire scripting_forum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedscripting_forums = array();

    /**
     * Whether a user is subscribed to this scripting_forum, or a discussion within
     * the scripting_forum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the scripting_forum preference.
     *
     * If it is not specified then only the scripting_forum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $scripting_forum The record of the scripting_forum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $scripting_forum, $discussionid = null, $cm = null) {
        // If scripting_forum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($scripting_forum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($scripting_forum->course)->instances['scripting_forum'][$scripting_forum->id];
            }
            if (has_capability('mod/scripting_forum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_scripting_forum($userid, $scripting_forum);
        }

        $subscriptions = self::fetch_discussion_subscription($scripting_forum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::FORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_scripting_forum($userid, $scripting_forum);
    }

    /**
     * Whether a user is subscribed to this scripting_forum.
     *
     * @param int $userid The user ID
     * @param \stdClass $scripting_forum The record of the scripting_forum to test
     * @return boolean
     */
    protected static function is_subscribed_to_scripting_forum($userid, $scripting_forum) {
        return self::fetch_subscription_cache($scripting_forum->id, $userid);
    }

    /**
     * Helper to determine whether a scripting_forum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $scripting_forum The record of the scripting_forum to test
     * @return bool
     */
    public static function is_forcesubscribed($scripting_forum) {
        return ($scripting_forum->forcesubscribe == FORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a scripting_forum has it's subscription mode set to disabled.
     *
     * @param \stdClass $scripting_forum The record of the scripting_forum to test
     * @return bool
     */
    public static function subscription_disabled($scripting_forum) {
        return ($scripting_forum->forcesubscribe == FORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified scripting_forum can be subscribed to.
     *
     * @param \stdClass $scripting_forum The record of the scripting_forum to test
     * @return bool
     */
    public static function is_subscribable($scripting_forum) {
        return (!\mod_scripting_forum\subscriptions::is_forcesubscribed($scripting_forum) &&
                !\mod_scripting_forum\subscriptions::subscription_disabled($scripting_forum));
    }

    /**
     * Set the scripting_forum subscription mode.
     *
     * By default when called without options, this is set to FORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $scripting_forum The record of the scripting_forum to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($scripting_forumid, $status = 1) {
        global $DB;
        return $DB->set_field("scripting_forum", "forcesubscribe", $status, array("id" => $scripting_forumid));
    }

    /**
     * Returns the current subscription mode for the scripting_forum.
     *
     * @param \stdClass $scripting_forum The record of the scripting_forum to set
     * @return int The scripting_forum subscription mode
     */
    public static function get_subscription_mode($scripting_forum) {
        return $scripting_forum->forcesubscribe;
    }

    /**
     * Returns an array of scripting_forums that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable scripting_forums
     */
    public static function get_unsubscribable_scripting_forums() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all scripting_forums from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a scripting_forum in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {scripting_forum} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {scripting_forum_subscriptions} fs ON (fs.scripting_forum = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'scripting_forum',
            'userid' => $USER->id,
            'forcesubscribe' => FORUM_FORCESUBSCRIBE,
        ));
        $scripting_forums = $DB->get_recordset_sql($sql, $params);

        $unsubscribablescripting_forums = array();
        foreach($scripting_forums as $scripting_forum) {
            if (empty($scripting_forum->visible)) {
                // The scripting_forum is hidden - check if the user can view the scripting_forum.
                $context = \context_module::instance($scripting_forum->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden scripting_forum to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribablescripting_forums[] = $scripting_forum;
        }
        $scripting_forums->close();

        return $unsubscribablescripting_forums;
    }

    /**
     * Get the list of potential subscribers to a scripting_forum.
     *
     * @param context_module $context the scripting_forum context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/scripting_forum:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the scripting_forum subscription data for the specified userid and scripting_forum.
     *
     * @param int $scripting_forumid The scripting_forum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($scripting_forumid, $userid) {
        if (isset(self::$scripting_forumcache[$userid]) && isset(self::$scripting_forumcache[$userid][$scripting_forumid])) {
            return self::$scripting_forumcache[$userid][$scripting_forumid];
        }
        self::fill_subscription_cache($scripting_forumid, $userid);

        if (!isset(self::$scripting_forumcache[$userid]) || !isset(self::$scripting_forumcache[$userid][$scripting_forumid])) {
            return false;
        }

        return self::$scripting_forumcache[$userid][$scripting_forumid];
    }

    /**
     * Fill the scripting_forum subscription data for the specified userid and scripting_forum.
     *
     * If the userid is not specified, then all subscription data for that scripting_forum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $scripting_forumid The scripting_forum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($scripting_forumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedscripting_forums[$scripting_forumid])) {
            // This scripting_forum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$scripting_forumcache[$userid])) {
                    self::$scripting_forumcache[$userid] = array();
                }

                if (!isset(self::$scripting_forumcache[$userid][$scripting_forumid])) {
                    if ($DB->record_exists('scripting_forum_subscriptions', array(
                        'userid' => $userid,
                        'scripting_forum' => $scripting_forumid,
                    ))) {
                        self::$scripting_forumcache[$userid][$scripting_forumid] = true;
                    } else {
                        self::$scripting_forumcache[$userid][$scripting_forumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('scripting_forum_subscriptions', array(
                    'scripting_forum' => $scripting_forumid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$scripting_forumcache[$data->userid])) {
                        self::$scripting_forumcache[$data->userid] = array();
                    }
                    self::$scripting_forumcache[$data->userid][$scripting_forumid] = true;
                }
                self::$fetchedscripting_forums[$scripting_forumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the scripting_forum subscription data for all scripting_forums that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$scripting_forumcache[$userid])) {
            self::$scripting_forumcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS scripting_forumid,
                    s.id AS subscriptionid
                FROM {scripting_forum} f
                LEFT JOIN {scripting_forum_subscriptions} s ON (s.scripting_forum = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => FORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$scripting_forumcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this scripting_forum.
     *
     * @param stdClass $scripting_forum The scripting_forum record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the scripting_forum context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($scripting_forum, $groupid = 0, $context = null, $fields = null,
            $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields ="u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackscripting_forums,
                      u.mnethostid";
        }

        // Retrieve the scripting_forum context if it wasn't specified.
        $context = scripting_forum_get_context($scripting_forum->id, $context);

        if (self::is_forcesubscribed($scripting_forum)) {
            $results = \mod_scripting_forum\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['scripting_forumid'] = $scripting_forum->id;

            if ($includediscussionsubscriptions) {
                $params['sscripting_forumid'] = $scripting_forum->id;
                $params['dsscripting_forumid'] = $scripting_forum->id;
                $params['unsubscribed'] = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {scripting_forum_subscriptions} s
                            WHERE
                                s.scripting_forum = :sscripting_forumid
                                UNION
                            SELECT userid FROM {scripting_forum_discussion_subs} ds
                            WHERE
                                ds.scripting_forum = :dsscripting_forumid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {scripting_forum_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.scripting_forum = :scripting_forumid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a scripting_forum.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('scripting_forum', $scripting_forum->id, $scripting_forum->course);
        $modinfo = get_fast_modinfo($scripting_forum->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and scripting_forum.
     *
     * This is returned as an array of discussions for that scripting_forum which contain the preference in a stdClass.
     *
     * @param int $scripting_forumid The scripting_forum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the scripting_forum.
     */
    public static function fetch_discussion_subscription($scripting_forumid, $userid = null) {
        self::fill_discussion_subscription_cache($scripting_forumid, $userid);

        if (!isset(self::$scripting_forumdiscussioncache[$userid]) || !isset(self::$scripting_forumdiscussioncache[$userid][$scripting_forumid])) {
            return array();
        }

        return self::$scripting_forumdiscussioncache[$userid][$scripting_forumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and scripting_forum.
     *
     * If the userid is not specified, then all discussion subscription data for that scripting_forum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $scripting_forumid The scripting_forum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($scripting_forumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedscripting_forums[$scripting_forumid])) {
            // This scripting_forum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$scripting_forumdiscussioncache[$userid])) {
                    self::$scripting_forumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$scripting_forumdiscussioncache[$userid][$scripting_forumid])) {
                    $subscriptions = $DB->get_recordset('scripting_forum_discussion_subs', array(
                        'userid' => $userid,
                        'scripting_forum' => $scripting_forumid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($scripting_forumid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('scripting_forum_discussion_subs', array(
                    'scripting_forum' => $scripting_forumid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($scripting_forumid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedscripting_forums[$scripting_forumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $scripting_forumid The ID of the scripting_forum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($scripting_forumid, $userid, $discussion, $preference) {
        if (!isset(self::$scripting_forumdiscussioncache[$userid])) {
            self::$scripting_forumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$scripting_forumdiscussioncache[$userid][$scripting_forumid])) {
            self::$scripting_forumdiscussioncache[$userid][$scripting_forumid] = array();
        }

        self::$scripting_forumdiscussioncache[$userid][$scripting_forumid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking scripting_forum discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$scripting_forumdiscussioncache = array();
        self::$discussionfetchedscripting_forums = array();
    }

    /**
     * Reset the scripting_forum cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking scripting_forum subscription states.
     */
    public static function reset_scripting_forum_cache() {
        self::$scripting_forumcache = array();
        self::$fetchedscripting_forums = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $scripting_forum The scripting_forum record for this scripting_forum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the scripting_forum_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $scripting_forum, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $scripting_forum)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->scripting_forum = $scripting_forum->id;

        $result = $DB->insert_record("scripting_forum_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('scripting_forum_discussion_subs', array('userid' => $userid, 'scripting_forum' => $scripting_forum->id));
            $DB->delete_records_select('scripting_forum_discussion_subs',
                    'userid = :userid AND scripting_forum = :scripting_forumid AND preference <> :preference', array(
                        'userid' => $userid,
                        'scripting_forumid' => $scripting_forum->id,
                        'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this scripting_forum.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$scripting_forumdiscussioncache[$userid]) && isset(self::$scripting_forumdiscussioncache[$userid][$scripting_forum->id])) {
                foreach (self::$scripting_forumdiscussioncache[$userid][$scripting_forum->id] as $discussionid => $preference) {
                    if ($preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$scripting_forumdiscussioncache[$userid][$scripting_forum->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this scripting_forum.
        self::$scripting_forumcache[$userid][$scripting_forum->id] = true;

        $context = scripting_forum_get_context($scripting_forum->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('scripting_forumid' => $scripting_forum->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('scripting_forum_discussion_subs', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $scripting_forum The scripting_forum record for this scripting_forum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $scripting_forum, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'scripting_forum' => $scripting_forum->id,
        );
        $DB->delete_records('scripting_forum_digests', $sqlparams);

        if ($scripting_forumsubscription = $DB->get_record('scripting_forum_subscriptions', $sqlparams)) {
            $DB->delete_records('scripting_forum_subscriptions', array('id' => $scripting_forumsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('scripting_forum_discussion_subs', $sqlparams);
                $DB->delete_records('scripting_forum_discussion_subs',
                        array('userid' => $userid, 'scripting_forum' => $scripting_forum->id, 'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$scripting_forumdiscussioncache[$userid]) && isset(self::$scripting_forumdiscussioncache[$userid][$scripting_forum->id])) {
                    self::$scripting_forumdiscussioncache[$userid][$scripting_forum->id] = array();
                }
            }

            // Reset the cache for this scripting_forum.
            self::$scripting_forumcache[$userid][$scripting_forum->id] = false;

            $context = scripting_forum_get_context($scripting_forum->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $scripting_forumsubscription->id,
                'relateduserid' => $userid,
                'other' => array('scripting_forumid' => $scripting_forum->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('scripting_forum_subscriptions', $scripting_forumsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('scripting_forum_discussion_subs', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('scripting_forum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a scripting_forum level subscription.
        if ($DB->record_exists('scripting_forum_subscriptions', array('userid' => $userid, 'scripting_forum' => $discussion->scripting_forum))) {
            if ($subscription && $subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the scripting_forum, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('scripting_forum_discussion_subs', array('id' => $subscription->id));
                unset(self::$scripting_forumdiscussioncache[$userid][$discussion->scripting_forum][$discussion->id]);
            } else {
                // The user is already subscribed to the scripting_forum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('scripting_forum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->scripting_forum = $discussion->scripting_forum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('scripting_forum_discussion_subs', $subscription);
                self::$scripting_forumdiscussioncache[$userid][$discussion->scripting_forum][$discussion->id] = $subscription->preference;
            }
        }

        $context = scripting_forum_get_context($discussion->scripting_forum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'scripting_forumid' => $discussion->scripting_forum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }
    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        $subscription = $DB->get_record('scripting_forum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a scripting_forum level subscription.
        if (!$DB->record_exists('scripting_forum_subscriptions', array('userid' => $userid, 'scripting_forum' => $discussion->scripting_forum))) {
            if ($subscription && $subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the scripting_forum, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('scripting_forum_discussion_subs', array('id' => $subscription->id));
                unset(self::$scripting_forumdiscussioncache[$userid][$discussion->scripting_forum][$discussion->id]);
            } else {
                // The user is not subscribed from the scripting_forum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('scripting_forum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->scripting_forum = $discussion->scripting_forum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('scripting_forum_discussion_subs', $subscription);
            }
            self::$scripting_forumdiscussioncache[$userid][$discussion->scripting_forum][$discussion->id] = $subscription->preference;
        }

        $context = scripting_forum_get_context($discussion->scripting_forum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'scripting_forumid' => $discussion->scripting_forum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}

