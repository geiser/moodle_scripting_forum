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
 * @package    mod_scriptingforum
 * @copyright  2016 Geiser Chalco <geiser@usp.br>
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scriptingforum;

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
     * The subscription cache for scriptingforums.
     *
     * The first level key is the user ID
     * The second level is the scriptingforum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $scriptingforumcache = array();

    /**
     * The list of scriptingforums which have been wholly retrieved for the scriptingforum subscription cache.
     *
     * This allows for prior caching of an entire scriptingforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedscriptingforums = array();

    /**
     * The subscription cache for scriptingforum discussions.
     *
     * The first level key is the user ID
     * The second level is the scriptingforum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $scriptingforumdiscussioncache = array();

    /**
     * The list of scriptingforums which have been wholly retrieved for the scriptingforum discussion subscription cache.
     *
     * This allows for prior caching of an entire scriptingforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedscriptingforums = array();

    /**
     * Whether a user is subscribed to this scriptingforum, or a discussion within
     * the scriptingforum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the scriptingforum preference.
     *
     * If it is not specified then only the scriptingforum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $scriptingforum The record of the scriptingforum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $scriptingforum, $discussionid = null, $cm = null) {
        // If scriptingforum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($scriptingforum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($scriptingforum->course)->instances['scriptingforum'][$scriptingforum->id];
            }
            if (has_capability('mod/scriptingforum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_scriptingforum($userid, $scriptingforum);
        }

        $subscriptions = self::fetch_discussion_subscription($scriptingforum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::FORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_scriptingforum($userid, $scriptingforum);
    }

    /**
     * Whether a user is subscribed to this scriptingforum.
     *
     * @param int $userid The user ID
     * @param \stdClass $scriptingforum The record of the scriptingforum to test
     * @return boolean
     */
    protected static function is_subscribed_to_scriptingforum($userid, $scriptingforum) {
        return self::fetch_subscription_cache($scriptingforum->id, $userid);
    }

    /**
     * Helper to determine whether a scriptingforum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $scriptingforum The record of the scriptingforum to test
     * @return bool
     */
    public static function is_forcesubscribed($scriptingforum) {
        return ($scriptingforum->forcesubscribe == FORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a scriptingforum has it's subscription mode set to disabled.
     *
     * @param \stdClass $scriptingforum The record of the scriptingforum to test
     * @return bool
     */
    public static function subscription_disabled($scriptingforum) {
        return ($scriptingforum->forcesubscribe == FORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified scriptingforum can be subscribed to.
     *
     * @param \stdClass $scriptingforum The record of the scriptingforum to test
     * @return bool
     */
    public static function is_subscribable($scriptingforum) {
        return (!\mod_scriptingforum\subscriptions::is_forcesubscribed($scriptingforum) &&
                !\mod_scriptingforum\subscriptions::subscription_disabled($scriptingforum));
    }

    /**
     * Set the scriptingforum subscription mode.
     *
     * By default when called without options, this is set to FORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $scriptingforum The record of the scriptingforum to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($scriptingforumid, $status = 1) {
        global $DB;
        return $DB->set_field("scriptingforum", "forcesubscribe", $status, array("id" => $scriptingforumid));
    }

    /**
     * Returns the current subscription mode for the scriptingforum.
     *
     * @param \stdClass $scriptingforum The record of the scriptingforum to set
     * @return int The scriptingforum subscription mode
     */
    public static function get_subscription_mode($scriptingforum) {
        return $scriptingforum->forcesubscribe;
    }

    /**
     * Returns an array of scriptingforums that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable scriptingforums
     */
    public static function get_unsubscribable_scriptingforums() {
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

        // Get all scriptingforums from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a scriptingforum in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {scriptingforum} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {scriptingforum_subscriptions} fs ON (fs.scriptingforum = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'scriptingforum',
            'userid' => $USER->id,
            'forcesubscribe' => FORUM_FORCESUBSCRIBE,
        ));
        $scriptingforums = $DB->get_recordset_sql($sql, $params);

        $unsubscribablescriptingforums = array();
        foreach($scriptingforums as $scriptingforum) {
            if (empty($scriptingforum->visible)) {
                // The scriptingforum is hidden - check if the user can view the scriptingforum.
                $context = \context_module::instance($scriptingforum->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden scriptingforum to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribablescriptingforums[] = $scriptingforum;
        }
        $scriptingforums->close();

        return $unsubscribablescriptingforums;
    }

    /**
     * Get the list of potential subscribers to a scriptingforum.
     *
     * @param context_module $context the scriptingforum context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/scriptingforum:allowforcesubscribe', $groupid, true);
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
     * Fetch the scriptingforum subscription data for the specified userid and scriptingforum.
     *
     * @param int $scriptingforumid The scriptingforum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($scriptingforumid, $userid) {
        if (isset(self::$scriptingforumcache[$userid]) && isset(self::$scriptingforumcache[$userid][$scriptingforumid])) {
            return self::$scriptingforumcache[$userid][$scriptingforumid];
        }
        self::fill_subscription_cache($scriptingforumid, $userid);

        if (!isset(self::$scriptingforumcache[$userid]) || !isset(self::$scriptingforumcache[$userid][$scriptingforumid])) {
            return false;
        }

        return self::$scriptingforumcache[$userid][$scriptingforumid];
    }

    /**
     * Fill the scriptingforum subscription data for the specified userid and scriptingforum.
     *
     * If the userid is not specified, then all subscription data for that scriptingforum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $scriptingforumid The scriptingforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($scriptingforumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedscriptingforums[$scriptingforumid])) {
            // This scriptingforum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$scriptingforumcache[$userid])) {
                    self::$scriptingforumcache[$userid] = array();
                }

                if (!isset(self::$scriptingforumcache[$userid][$scriptingforumid])) {
                    if ($DB->record_exists('scriptingforum_subscriptions', array(
                        'userid' => $userid,
                        'scriptingforum' => $scriptingforumid,
                    ))) {
                        self::$scriptingforumcache[$userid][$scriptingforumid] = true;
                    } else {
                        self::$scriptingforumcache[$userid][$scriptingforumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('scriptingforum_subscriptions', array(
                    'scriptingforum' => $scriptingforumid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$scriptingforumcache[$data->userid])) {
                        self::$scriptingforumcache[$data->userid] = array();
                    }
                    self::$scriptingforumcache[$data->userid][$scriptingforumid] = true;
                }
                self::$fetchedscriptingforums[$scriptingforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the scriptingforum subscription data for all scriptingforums that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$scriptingforumcache[$userid])) {
            self::$scriptingforumcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS scriptingforumid,
                    s.id AS subscriptionid
                FROM {scriptingforum} f
                LEFT JOIN {scriptingforum_subscriptions} s ON (s.scriptingforum = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => FORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$scriptingforumcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this scriptingforum.
     *
     * @param stdClass $scriptingforum The scriptingforum record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the scriptingforum context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($scriptingforum, $groupid = 0, $context = null, $fields = null,
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
                      u.trackscriptingforums,
                      u.mnethostid";
        }

        // Retrieve the scriptingforum context if it wasn't specified.
        $context = scriptingforum_get_context($scriptingforum->id, $context);

        if (self::is_forcesubscribed($scriptingforum)) {
            $results = \mod_scriptingforum\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['scriptingforumid'] = $scriptingforum->id;

            if ($includediscussionsubscriptions) {
                $params['sscriptingforumid'] = $scriptingforum->id;
                $params['dsscriptingforumid'] = $scriptingforum->id;
                $params['unsubscribed'] = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {scriptingforum_subscriptions} s
                            WHERE
                                s.scriptingforum = :sscriptingforumid
                                UNION
                            SELECT userid FROM {scriptingforum_discussion_subs} ds
                            WHERE
                                ds.scriptingforum = :dsscriptingforumid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {scriptingforum_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.scriptingforum = :scriptingforumid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a scriptingforum.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('scriptingforum', $scriptingforum->id, $scriptingforum->course);
        $modinfo = get_fast_modinfo($scriptingforum->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and scriptingforum.
     *
     * This is returned as an array of discussions for that scriptingforum which contain the preference in a stdClass.
     *
     * @param int $scriptingforumid The scriptingforum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the scriptingforum.
     */
    public static function fetch_discussion_subscription($scriptingforumid, $userid = null) {
        self::fill_discussion_subscription_cache($scriptingforumid, $userid);

        if (!isset(self::$scriptingforumdiscussioncache[$userid]) || !isset(self::$scriptingforumdiscussioncache[$userid][$scriptingforumid])) {
            return array();
        }

        return self::$scriptingforumdiscussioncache[$userid][$scriptingforumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and scriptingforum.
     *
     * If the userid is not specified, then all discussion subscription data for that scriptingforum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $scriptingforumid The scriptingforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($scriptingforumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedscriptingforums[$scriptingforumid])) {
            // This scriptingforum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$scriptingforumdiscussioncache[$userid])) {
                    self::$scriptingforumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$scriptingforumdiscussioncache[$userid][$scriptingforumid])) {
                    $subscriptions = $DB->get_recordset('scriptingforum_discussion_subs', array(
                        'userid' => $userid,
                        'scriptingforum' => $scriptingforumid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($scriptingforumid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('scriptingforum_discussion_subs', array(
                    'scriptingforum' => $scriptingforumid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($scriptingforumid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedscriptingforums[$scriptingforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $scriptingforumid The ID of the scriptingforum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($scriptingforumid, $userid, $discussion, $preference) {
        if (!isset(self::$scriptingforumdiscussioncache[$userid])) {
            self::$scriptingforumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$scriptingforumdiscussioncache[$userid][$scriptingforumid])) {
            self::$scriptingforumdiscussioncache[$userid][$scriptingforumid] = array();
        }

        self::$scriptingforumdiscussioncache[$userid][$scriptingforumid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking scriptingforum discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$scriptingforumdiscussioncache = array();
        self::$discussionfetchedscriptingforums = array();
    }

    /**
     * Reset the scriptingforum cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking scriptingforum subscription states.
     */
    public static function reset_scriptingforum_cache() {
        self::$scriptingforumcache = array();
        self::$fetchedscriptingforums = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $scriptingforum The scriptingforum record for this scriptingforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the scriptingforum_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $scriptingforum, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $scriptingforum)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->scriptingforum = $scriptingforum->id;

        $result = $DB->insert_record("scriptingforum_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('scriptingforum_discussion_subs', array('userid' => $userid, 'scriptingforum' => $scriptingforum->id));
            $DB->delete_records_select('scriptingforum_discussion_subs',
                    'userid = :userid AND scriptingforum = :scriptingforumid AND preference <> :preference', array(
                        'userid' => $userid,
                        'scriptingforumid' => $scriptingforum->id,
                        'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this scriptingforum.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$scriptingforumdiscussioncache[$userid]) && isset(self::$scriptingforumdiscussioncache[$userid][$scriptingforum->id])) {
                foreach (self::$scriptingforumdiscussioncache[$userid][$scriptingforum->id] as $discussionid => $preference) {
                    if ($preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$scriptingforumdiscussioncache[$userid][$scriptingforum->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this scriptingforum.
        self::$scriptingforumcache[$userid][$scriptingforum->id] = true;

        $context = scriptingforum_get_context($scriptingforum->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('scriptingforumid' => $scriptingforum->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('scriptingforum_discussion_subs', $subscription);
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
     * @param \stdClass $scriptingforum The scriptingforum record for this scriptingforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $scriptingforum, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'scriptingforum' => $scriptingforum->id,
        );
        $DB->delete_records('scriptingforum_digests', $sqlparams);

        if ($scriptingforumsubscription = $DB->get_record('scriptingforum_subscriptions', $sqlparams)) {
            $DB->delete_records('scriptingforum_subscriptions', array('id' => $scriptingforumsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('scriptingforum_discussion_subs', $sqlparams);
                $DB->delete_records('scriptingforum_discussion_subs',
                        array('userid' => $userid, 'scriptingforum' => $scriptingforum->id, 'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$scriptingforumdiscussioncache[$userid]) && isset(self::$scriptingforumdiscussioncache[$userid][$scriptingforum->id])) {
                    self::$scriptingforumdiscussioncache[$userid][$scriptingforum->id] = array();
                }
            }

            // Reset the cache for this scriptingforum.
            self::$scriptingforumcache[$userid][$scriptingforum->id] = false;

            $context = scriptingforum_get_context($scriptingforum->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $scriptingforumsubscription->id,
                'relateduserid' => $userid,
                'other' => array('scriptingforumid' => $scriptingforum->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('scriptingforum_subscriptions', $scriptingforumsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('scriptingforum_discussion_subs', $subscription);
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
        $subscription = $DB->get_record('scriptingforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a scriptingforum level subscription.
        if ($DB->record_exists('scriptingforum_subscriptions', array('userid' => $userid, 'scriptingforum' => $discussion->scriptingforum))) {
            if ($subscription && $subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the scriptingforum, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('scriptingforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$scriptingforumdiscussioncache[$userid][$discussion->scriptingforum][$discussion->id]);
            } else {
                // The user is already subscribed to the scriptingforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('scriptingforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->scriptingforum = $discussion->scriptingforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('scriptingforum_discussion_subs', $subscription);
                self::$scriptingforumdiscussioncache[$userid][$discussion->scriptingforum][$discussion->id] = $subscription->preference;
            }
        }

        $context = scriptingforum_get_context($discussion->scriptingforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'scriptingforumid' => $discussion->scriptingforum,
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
        $subscription = $DB->get_record('scriptingforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a scriptingforum level subscription.
        if (!$DB->record_exists('scriptingforum_subscriptions', array('userid' => $userid, 'scriptingforum' => $discussion->scriptingforum))) {
            if ($subscription && $subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the scriptingforum, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('scriptingforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$scriptingforumdiscussioncache[$userid][$discussion->scriptingforum][$discussion->id]);
            } else {
                // The user is not subscribed from the scriptingforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('scriptingforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->scriptingforum = $discussion->scriptingforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('scriptingforum_discussion_subs', $subscription);
            }
            self::$scriptingforumdiscussioncache[$userid][$discussion->scriptingforum][$discussion->id] = $subscription->preference;
        }

        $context = scriptingforum_get_context($discussion->scriptingforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'scriptingforumid' => $discussion->scriptingforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}

