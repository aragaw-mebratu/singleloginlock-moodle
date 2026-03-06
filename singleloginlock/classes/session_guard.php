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
    /** @var string config key storing last saved enabled state. */
    private const CONFIG_ENABLED_LASTSTATE = 'enabled_laststate';
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
     * @return bool true when current session remains valid.
     */
    public static function heartbeat_current_session(): bool {
        global $USER;

        if (!isloggedin() || isguestuser() || empty($USER->id)) {
            return false;
        }

        $userid = (int)$USER->id;
        if (!self::enforce_current_user_single_session($userid)) {
            return false;
        }

        if (!self::is_enforced_for_user($userid)) {
            return true;
        }

        $sid = (string)session_id();
        if ($sid === '') {
            return true;
        }

        $activesid = (string)get_user_preferences(self::PREF_ACTIVE_SID, '', $userid);
        if ($activesid === '') {
            self::set_active_session($userid, $sid);
            return true;
        }

        if ($activesid === $sid) {
            set_user_preference(self::PREF_LAST_SEEN, (string)time(), $userid);
        }

        return true;
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
            self::invoke_user_session_killer($managerclass, 'destroy_user_sessions', $userid, $keepsid);
            return;
        }

        // Moodle 4.x API.
        if (is_callable([$managerclass, 'kill_user_sessions'])) {
            self::invoke_user_session_killer($managerclass, 'kill_user_sessions', $userid, $keepsid);
        }
    }

    /**
     * Run selective cleanup after enabled setting changes from disabled to enabled.
     *
     * @return void
     */
    public static function handle_enabled_setting_update(): void {
        $enabled = self::is_plugin_enabled();
        $previous = ((string)get_config('local_singleloginlock', self::CONFIG_ENABLED_LASTSTATE) === '1');

        if ($enabled && !$previous) {
            self::cleanup_users_with_multiple_active_sessions();
        }

        set_config(self::CONFIG_ENABLED_LASTSTATE, $enabled ? '1' : '0', 'local_singleloginlock');
    }

    /**
     * Logout only enforced users currently holding multiple active sessions.
     *
     * @return int number of users cleaned.
     */
    public static function cleanup_users_with_multiple_active_sessions(): int {
        global $DB, $CFG;

        $sessiontimeout = isset($CFG->sessiontimeout) ? (int)$CFG->sessiontimeout : 0;
        if ($sessiontimeout <= 0) {
            $sessiontimeout = 7200;
        }

        $cutoff = time() - $sessiontimeout;
        $sql = "SELECT s.userid
                  FROM {sessions} s
                 WHERE s.userid > 0
                   AND s.timemodified >= :cutoff
              GROUP BY s.userid
                HAVING COUNT(1) > 1";
        $recordset = $DB->get_recordset_sql($sql, ['cutoff' => $cutoff]);

        $cleanedusers = 0;
        foreach ($recordset as $record) {
            $userid = (int)$record->userid;
            if ($userid <= 0 || !self::is_enforced_for_user($userid)) {
                continue;
            }

            self::destroy_all_user_sessions($userid);
            self::clear_user_state($userid);
            $cleanedusers++;
        }
        $recordset->close();

        return $cleanedusers;
    }

    /**
     * Enforce immediate logout when a user currently has multiple active sessions.
     *
     * @param int $userid 0 means current user.
     * @return bool true when current session is allowed to continue.
     */
    public static function enforce_current_user_single_session(int $userid = 0): bool {
        global $USER;

        if ($userid <= 0 && !empty($USER->id)) {
            $userid = (int)$USER->id;
        }
        if ($userid <= 0) {
            return true;
        }

        if (!self::is_enforced_for_user($userid)) {
            self::clear_user_state($userid);
            return true;
        }

        if (!self::user_has_multiple_active_sessions($userid)) {
            return true;
        }

        self::destroy_all_user_sessions($userid);
        self::clear_user_state($userid);
        \core\session\manager::terminate_current();
        return false;
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

    /**
     * Destroy all sessions for a user with Moodle 4/5 API compatibility.
     *
     * @param int $userid
     * @return void
     */
    private static function destroy_all_user_sessions(int $userid): void {
        if ($userid <= 0) {
            return;
        }

        $managerclass = \core\session\manager::class;
        if (is_callable([$managerclass, 'destroy_user_sessions'])) {
            self::invoke_user_session_killer($managerclass, 'destroy_user_sessions', $userid);
            return;
        }

        // Moodle 4.x API.
        if (is_callable([$managerclass, 'kill_user_sessions'])) {
            self::invoke_user_session_killer($managerclass, 'kill_user_sessions', $userid);
        }
    }

    /**
     * Whether user currently has multiple active sessions in Moodle session store.
     *
     * @param int $userid
     * @return bool
     */
    private static function user_has_multiple_active_sessions(int $userid): bool {
        global $DB, $CFG;

        if ($userid <= 0) {
            return false;
        }

        $sessiontimeout = isset($CFG->sessiontimeout) ? (int)$CFG->sessiontimeout : 0;
        if ($sessiontimeout <= 0) {
            $sessiontimeout = 7200;
        }

        $cutoff = time() - $sessiontimeout;
        $count = (int)$DB->count_records_select(
            'sessions',
            'userid = :userid AND timemodified >= :cutoff',
            ['userid' => $userid, 'cutoff' => $cutoff]
        );
        if ($count > 1) {
            return true;
        }

        // Fallback for non-DB session handlers: detect active SID conflict directly.
        $currentsid = (string)session_id();
        $activesid = (string)get_user_preferences(self::PREF_ACTIVE_SID, '', $userid);
        if ($currentsid === '' || $activesid === '' || $currentsid === $activesid) {
            return false;
        }

        return \core\session\manager::session_exists($activesid);
    }

    /**
     * Call session-kill APIs that differ in argument count between Moodle versions.
     *
     * @param string $managerclass
     * @param string $method
     * @param int $userid
     * @param string|null $keepsid
     * @return void
     */
    private static function invoke_user_session_killer(
        string $managerclass,
        string $method,
        int $userid,
        ?string $keepsid = null
    ): void {
        try {
            $reflection = new \ReflectionMethod($managerclass, $method);
            if ($reflection->getNumberOfParameters() >= 2) {
                $managerclass::$method($userid, $keepsid ?? '');
                return;
            }
        } catch (\ReflectionException $e) {
            // Fall through to best-effort direct call.
        }

        $managerclass::$method($userid);
    }
}
