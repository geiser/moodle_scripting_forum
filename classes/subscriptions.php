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
 * @package    mod_sforum
 * @copyright  2016 Geiser Chalco <geiser@usp.br>
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_sforum;

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
     * The subscription cache for sforums.
     *
     * The first level key is the user ID
     * The second level is the sforum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $sforumcache = array();

    /**
     * The list of sforums which have been wholly retrieved for the sforum subscription cache.
     *
     * This allows for prior caching of an entire sforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedsforums = array();

    /**
     * The subscription cache for sforum discussions.
     *
     * The first level key is the user ID
     * The second level is the sforum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $sforumdiscussioncache = array();

    /**
     * The list of sforums which have been wholly retrieved for the sforum discussion subscription cache.
     *
     * This allows for prior caching of an entire sforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedsforums = array();

    /**
     * Whether a user is subscribed to this sforum, or a discussion within
     * the sforum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the sforum preference.
     *
     * If it is not specified then only the sforum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $sforum The record of the sforum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $sforum, $discussionid = null, $cm = null) {
        // If sforum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($sforum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($sforum->course)->instances['sforum'][$sforum->id];
            }
            if (has_capability('mod/sforum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_sforum($userid, $sforum);
        }

        $subscriptions = self::fetch_discussion_subscription($sforum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::FORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_sforum($userid, $sforum);
    }

    /**
     * Whether a user is subscribed to this sforum.
     *
     * @param int $userid The user ID
     * @param \stdClass $sforum The record of the sforum to test
     * @return boolean
     */
    protected static function is_subscribed_to_sforum($userid, $sforum) {
        return self::fetch_subscription_cache($sforum->id, $userid);
    }

    /**
     * Helper to determine whether a sforum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $sforum The record of the sforum to test
     * @return bool
     */
    public static function is_forcesubscribed($sforum) {
        return ($sforum->forcesubscribe == FORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a sforum has it's subscription mode set to disabled.
     *
     * @param \stdClass $sforum The record of the sforum to test
     * @return bool
     */
    public static function subscription_disabled($sforum) {
        return ($sforum->forcesubscribe == FORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified sforum can be subscribed to.
     *
     * @param \stdClass $sforum The record of the sforum to test
     * @return bool
     */
    public static function is_subscribable($sforum) {
        return (!\mod_sforum\subscriptions::is_forcesubscribed($sforum) &&
                !\mod_sforum\subscriptions::subscription_disabled($sforum));
    }

    /**
     * Set the sforum subscription mode.
     *
     * By default when called without options, this is set to FORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $sforum The record of the sforum to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($sforumid, $status = 1) {
        global $DB;
        return $DB->set_field("sforum", "forcesubscribe", $status, array("id" => $sforumid));
    }

    /**
     * Returns the current subscription mode for the sforum.
     *
     * @param \stdClass $sforum The record of the sforum to set
     * @return int The sforum subscription mode
     */
    public static function get_subscription_mode($sforum) {
        return $sforum->forcesubscribe;
    }

    /**
     * Returns an array of sforums that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable sforums
     */
    public static function get_unsubscribable_sforums() {
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

        // Get all sforums from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a sforum in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {sforum} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {sforum_subscriptions} fs ON (fs.sforum = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'sforum',
            'userid' => $USER->id,
            'forcesubscribe' => FORUM_FORCESUBSCRIBE,
        ));
        $sforums = $DB->get_recordset_sql($sql, $params);

        $unsubscribablesforums = array();
        foreach($sforums as $sforum) {
            if (empty($sforum->visible)) {
                // The sforum is hidden - check if the user can view the sforum.
                $context = \context_module::instance($sforum->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden sforum to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribablesforums[] = $sforum;
        }
        $sforums->close();

        return $unsubscribablesforums;
    }

    /**
     * Get the list of potential subscribers to a sforum.
     *
     * @param context_module $context the sforum context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/sforum:allowforcesubscribe', $groupid, true);
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
     * Fetch the sforum subscription data for the specified userid and sforum.
     *
     * @param int $sforumid The sforum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($sforumid, $userid) {
        if (isset(self::$sforumcache[$userid]) && isset(self::$sforumcache[$userid][$sforumid])) {
            return self::$sforumcache[$userid][$sforumid];
        }
        self::fill_subscription_cache($sforumid, $userid);

        if (!isset(self::$sforumcache[$userid]) || !isset(self::$sforumcache[$userid][$sforumid])) {
            return false;
        }

        return self::$sforumcache[$userid][$sforumid];
    }

    /**
     * Fill the sforum subscription data for the specified userid and sforum.
     *
     * If the userid is not specified, then all subscription data for that sforum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $sforumid The sforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($sforumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedsforums[$sforumid])) {
            // This sforum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$sforumcache[$userid])) {
                    self::$sforumcache[$userid] = array();
                }

                if (!isset(self::$sforumcache[$userid][$sforumid])) {
                    if ($DB->record_exists('sforum_subscriptions', array(
                        'userid' => $userid,
                        'sforum' => $sforumid,
                    ))) {
                        self::$sforumcache[$userid][$sforumid] = true;
                    } else {
                        self::$sforumcache[$userid][$sforumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('sforum_subscriptions', array(
                    'sforum' => $sforumid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$sforumcache[$data->userid])) {
                        self::$sforumcache[$data->userid] = array();
                    }
                    self::$sforumcache[$data->userid][$sforumid] = true;
                }
                self::$fetchedsforums[$sforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the sforum subscription data for all sforums that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$sforumcache[$userid])) {
            self::$sforumcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS sforumid,
                    s.id AS subscriptionid
                FROM {sforum} f
                LEFT JOIN {sforum_subscriptions} s ON (s.sforum = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => FORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$sforumcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this sforum.
     *
     * @param stdClass $sforum The sforum record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the sforum context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($sforum, $groupid = 0, $context = null, $fields = null,
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
                      u.tracksforums,
                      u.mnethostid";
        }

        // Retrieve the sforum context if it wasn't specified.
        $context = sforum_get_context($sforum->id, $context);

        if (self::is_forcesubscribed($sforum)) {
            $results = \mod_sforum\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['sforumid'] = $sforum->id;

            if ($includediscussionsubscriptions) {
                $params['ssforumid'] = $sforum->id;
                $params['dssforumid'] = $sforum->id;
                $params['unsubscribed'] = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {sforum_subscriptions} s
                            WHERE
                                s.sforum = :ssforumid
                                UNION
                            SELECT userid FROM {sforum_discussion_subs} ds
                            WHERE
                                ds.sforum = :dssforumid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {sforum_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.sforum = :sforumid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a sforum.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('sforum', $sforum->id, $sforum->course);
        $modinfo = get_fast_modinfo($sforum->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and sforum.
     *
     * This is returned as an array of discussions for that sforum which contain the preference in a stdClass.
     *
     * @param int $sforumid The sforum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the sforum.
     */
    public static function fetch_discussion_subscription($sforumid, $userid = null) {
        self::fill_discussion_subscription_cache($sforumid, $userid);

        if (!isset(self::$sforumdiscussioncache[$userid]) || !isset(self::$sforumdiscussioncache[$userid][$sforumid])) {
            return array();
        }

        return self::$sforumdiscussioncache[$userid][$sforumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and sforum.
     *
     * If the userid is not specified, then all discussion subscription data for that sforum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $sforumid The sforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($sforumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedsforums[$sforumid])) {
            // This sforum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$sforumdiscussioncache[$userid])) {
                    self::$sforumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$sforumdiscussioncache[$userid][$sforumid])) {
                    $subscriptions = $DB->get_recordset('sforum_discussion_subs', array(
                        'userid' => $userid,
                        'sforum' => $sforumid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($sforumid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('sforum_discussion_subs', array(
                    'sforum' => $sforumid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($sforumid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedsforums[$sforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $sforumid The ID of the sforum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($sforumid, $userid, $discussion, $preference) {
        if (!isset(self::$sforumdiscussioncache[$userid])) {
            self::$sforumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$sforumdiscussioncache[$userid][$sforumid])) {
            self::$sforumdiscussioncache[$userid][$sforumid] = array();
        }

        self::$sforumdiscussioncache[$userid][$sforumid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking sforum discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$sforumdiscussioncache = array();
        self::$discussionfetchedsforums = array();
    }

    /**
     * Reset the sforum cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking sforum subscription states.
     */
    public static function reset_sforum_cache() {
        self::$sforumcache = array();
        self::$fetchedsforums = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $sforum The sforum record for this sforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the sforum_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $sforum, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $sforum)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->sforum = $sforum->id;

        $result = $DB->insert_record("sforum_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('sforum_discussion_subs', array('userid' => $userid, 'sforum' => $sforum->id));
            $DB->delete_records_select('sforum_discussion_subs',
                    'userid = :userid AND sforum = :sforumid AND preference <> :preference', array(
                        'userid' => $userid,
                        'sforumid' => $sforum->id,
                        'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this sforum.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$sforumdiscussioncache[$userid]) && isset(self::$sforumdiscussioncache[$userid][$sforum->id])) {
                foreach (self::$sforumdiscussioncache[$userid][$sforum->id] as $discussionid => $preference) {
                    if ($preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$sforumdiscussioncache[$userid][$sforum->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this sforum.
        self::$sforumcache[$userid][$sforum->id] = true;

        $context = sforum_get_context($sforum->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('sforumid' => $sforum->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('sforum_discussion_subs', $subscription);
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
     * @param \stdClass $sforum The sforum record for this sforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $sforum, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'sforum' => $sforum->id,
        );
        $DB->delete_records('sforum_digests', $sqlparams);

        if ($sforumsubscription = $DB->get_record('sforum_subscriptions', $sqlparams)) {
            $DB->delete_records('sforum_subscriptions', array('id' => $sforumsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('sforum_discussion_subs', $sqlparams);
                $DB->delete_records('sforum_discussion_subs',
                        array('userid' => $userid, 'sforum' => $sforum->id, 'preference' => self::FORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$sforumdiscussioncache[$userid]) && isset(self::$sforumdiscussioncache[$userid][$sforum->id])) {
                    self::$sforumdiscussioncache[$userid][$sforum->id] = array();
                }
            }

            // Reset the cache for this sforum.
            self::$sforumcache[$userid][$sforum->id] = false;

            $context = sforum_get_context($sforum->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $sforumsubscription->id,
                'relateduserid' => $userid,
                'other' => array('sforumid' => $sforum->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('sforum_subscriptions', $sforumsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('sforum_discussion_subs', $subscription);
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
        $subscription = $DB->get_record('sforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a sforum level subscription.
        if ($DB->record_exists('sforum_subscriptions', array('userid' => $userid, 'sforum' => $discussion->sforum))) {
            if ($subscription && $subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the sforum, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('sforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$sforumdiscussioncache[$userid][$discussion->sforum][$discussion->id]);
            } else {
                // The user is already subscribed to the sforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('sforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->sforum = $discussion->sforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('sforum_discussion_subs', $subscription);
                self::$sforumdiscussioncache[$userid][$discussion->sforum][$discussion->id] = $subscription->preference;
            }
        }

        $context = sforum_get_context($discussion->sforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'sforumid' => $discussion->sforum,
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
        $subscription = $DB->get_record('sforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a sforum level subscription.
        if (!$DB->record_exists('sforum_subscriptions', array('userid' => $userid, 'sforum' => $discussion->sforum))) {
            if ($subscription && $subscription->preference != self::FORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the sforum, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('sforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$sforumdiscussioncache[$userid][$discussion->sforum][$discussion->id]);
            } else {
                // The user is not subscribed from the sforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('sforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->sforum = $discussion->sforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::FORUM_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('sforum_discussion_subs', $subscription);
            }
            self::$sforumdiscussioncache[$userid][$discussion->sforum][$discussion->id] = $subscription->preference;
        }

        $context = sforum_get_context($discussion->sforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'sforumid' => $discussion->sforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}

