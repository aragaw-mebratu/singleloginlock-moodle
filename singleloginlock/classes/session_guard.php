<?php
namespace local_singleloginlock;

defined('MOODLE_INTERNAL') || die();

class session_guard {
    /** @var string user preference key for the authoritative active session SID. */
    public const PREF_ACTIVE_SID = 'local_singleloginlock_activesid';
    /** @var string user preference key for unix timestamp heartbeat. */
    public const PREF_LAST_SEEN = 'local_singleloginlock_lastseen';
    /** @var int seconds before an active SID is considered stale. */
    public const ACTIVE_WINDOW_SECONDS = 300;
    /** @var string custom profile checkbox shortname for takeover override. */
    public const PROFILE_FIELD_ALLOWLOGIN = 'singleloginlock_allowlogin';
    /** @var array<int, bool> per-request cache for enforcement checks. */
    private static array $enforcedcache = [];
    /** @var array<int, bool> per-request cache for takeover override checks. */
    private static array $allowlogincache = [];
    /** @var int cached field id for the takeover checkbox profile field. */
    private static int $allowloginfieldid = -1;

    /**
     * Whether plugin runtime logic is enabled.
     *
     * @return bool
     */
    public static function is_plugin_enabled(): bool {
        $enabled = get_config('local_singleloginlock', 'enabled');
        // Default to enabled when setting not yet stored.
        if ($enabled === false || $enabled === null) {
            return true;
        }
        return !empty($enabled);
    }

    /**
     * Whether lock enforcement applies to a user.
     *
     * @param int $userid 0 means current user.
     * @return bool
     */
    public static function is_enforced_for_user(int $userid = 0): bool {
        global $USER, $DB;

        if ($userid <= 0 && !empty($USER->id)) {
            $userid = (int)$USER->id;
        }
        if ($userid <= 0) {
            return false;
        }

        if (array_key_exists($userid, self::$enforcedcache)) {
            return self::$enforcedcache[$userid];
        }

        if (is_siteadmin($userid)) {
            self::$enforcedcache[$userid] = false;
            return false;
        }

        $context = \context_system::instance();
        if (has_capability('local/singleloginlock:enforce', $context, $userid, false)) {
            self::$enforcedcache[$userid] = true;
            return true;
        }

        // Fallback: enforce for users assigned any role derived from the Student archetype.
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid
                   AND (r.archetype = :studentarchetype OR r.shortname = :studentshortname)";
        $params = [
            'userid' => $userid,
            'studentarchetype' => 'student',
            'studentshortname' => 'student',
        ];
        $isenforced = $DB->record_exists_sql($sql, $params);
        self::$enforcedcache[$userid] = $isenforced;
        return $isenforced;
    }

    /**
     * Resolve userid from the login event payload.
     *
     * @param \core\event\user_loggedin $event
     * @return int
     */
    public static function event_userid(\core\event\user_loggedin $event): int {
        $userid = (int)$event->userid;
        if ($userid <= 0) {
            $userid = (int)$event->objectid;
        }
        if ($userid <= 0) {
            $userid = (int)$event->relateduserid;
        }
        return $userid;
    }

    /**
     * Determine whether another session should be considered actively logged in.
     *
     * @param int $userid
     * @param string $currentsid
     * @param int|null $now
     * @return bool
     */
    public static function has_other_active_session(int $userid, string $currentsid, ?int $now = null): bool {
        if ($userid <= 0 || $currentsid === '') {
            return false;
        }
        if (!self::is_enforced_for_user($userid)) {
            return false;
        }

        $now = $now ?? time();
        $activesid = (string)get_user_preferences(self::PREF_ACTIVE_SID, '', $userid);
        if ($activesid === '' || $activesid === $currentsid) {
            return false;
        }

        $lastseen = (int)get_user_preferences(self::PREF_LAST_SEEN, 0, $userid);
        if ($lastseen <= 0) {
            return false;
        }

        if (($now - $lastseen) > self::ACTIVE_WINDOW_SECONDS) {
            return false;
        }

        return \core\session\manager::session_exists($activesid);
    }

    /**
     * Mark the current SID as the authoritative active session for the user.
     *
     * @param int $userid
     * @param string $sid
     * @param int|null $now
     * @return void
     */
    public static function set_active_session(int $userid, string $sid, ?int $now = null): void {
        if ($userid <= 0 || $sid === '') {
            return;
        }
        if (!self::is_enforced_for_user($userid)) {
            self::clear_user_state($userid);
            return;
        }

        $now = $now ?? time();
        set_user_preference(self::PREF_ACTIVE_SID, $sid, $userid);
        set_user_preference(self::PREF_LAST_SEEN, (string)$now, $userid);
    }

    /**
     * Heartbeat from current request keeps active session fresh.
     *
     * @return void
     */
    public static function heartbeat_current_session(): void {
        global $USER;

        if (!isloggedin() || isguestuser() || empty($USER->id)) {
            return;
        }

        $userid = (int)$USER->id;
        if (!self::is_enforced_for_user($userid)) {
            self::clear_user_state($userid);
            return;
        }

        $sid = (string)session_id();
        if ($sid === '') {
            return;
        }

        $activesid = (string)get_user_preferences(self::PREF_ACTIVE_SID, '', $userid);
        if ($activesid === '') {
            self::set_active_session($userid, $sid);
            return;
        }

        if ($activesid === $sid) {
            set_user_preference(self::PREF_LAST_SEEN, (string)time(), $userid);
        }
    }

    /**
     * Whether the user is allowed to force login takeover via profile checkbox.
     *
     * Requires a custom profile checkbox field with shortname:
     * singleloginlock_allowlogin.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_allows_login_takeover(int $userid): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }
        if (array_key_exists($userid, self::$allowlogincache)) {
            return self::$allowlogincache[$userid];
        }

        $fieldid = self::get_allowlogin_fieldid();
        if (empty($fieldid)) {
            self::$allowlogincache[$userid] = false;
            return false;
        }

        $value = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
        $allowed = ((string)$value === '1');
        self::$allowlogincache[$userid] = $allowed;
        return $allowed;
    }

    /**
     * Automatically uncheck takeover checkbox after successful login.
     *
     * @param int $userid
     * @return void
     */
    public static function reset_login_takeover_checkbox(int $userid): void {
        global $DB;

        if ($userid <= 0) {
            return;
        }

        $fieldid = self::get_allowlogin_fieldid();
        if (empty($fieldid)) {
            self::$allowlogincache[$userid] = false;
            return;
        }

        $record = $DB->get_record(
            'user_info_data',
            ['userid' => $userid, 'fieldid' => $fieldid],
            'id, data',
            IGNORE_MISSING
        );
        if ($record && (string)$record->data !== '0') {
            $record->data = '0';
            $DB->update_record('user_info_data', $record);
        }

        self::$allowlogincache[$userid] = false;
    }

    /**
     * Destroy all sessions for a user except the current session, with Moodle 4/5 API compatibility.
     *
     * @param int $userid
     * @param string $keepsid
     * @return void
     */
    public static function destroy_other_user_sessions(int $userid, string $keepsid): void {
        if ($userid <= 0 || $keepsid === '') {
            return;
        }

        $managerclass = \core\session\manager::class;
        if (is_callable([$managerclass, 'destroy_user_sessions'])) {
            $managerclass::destroy_user_sessions($userid, $keepsid);
            return;
        }

        // Moodle 4.x API.
        if (is_callable([$managerclass, 'kill_user_sessions'])) {
            $managerclass::kill_user_sessions($userid, $keepsid);
        }
    }

    /**
     * Resolve and cache profile field id for takeover checkbox.
     *
     * @return int
     */
    private static function get_allowlogin_fieldid(): int {
        global $DB;

        if (self::$allowloginfieldid !== -1) {
            return self::$allowloginfieldid;
        }

        self::$allowloginfieldid = (int)$DB->get_field(
            'user_info_field',
            'id',
            ['shortname' => self::PROFILE_FIELD_ALLOWLOGIN, 'datatype' => 'checkbox']
        );
        return self::$allowloginfieldid;
    }

    /**
     * Remove persisted lock state for a user.
     *
     * @param int $userid
     * @return void
     */
    public static function clear_user_state(int $userid): void {
        if ($userid <= 0) {
            return;
        }
        unset_user_preference(self::PREF_ACTIVE_SID, $userid);
        unset_user_preference(self::PREF_LAST_SEEN, $userid);
    }
}
