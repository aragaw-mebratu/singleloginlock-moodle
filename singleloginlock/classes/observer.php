<?php
namespace local_singleloginlock;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        if (!session_guard::is_plugin_enabled()) {
            return;
        }

        $userid = session_guard::event_userid($event);
        $currentsid = (string)session_id();
        if ($userid <= 0 || $currentsid === '') {
            return;
        }
        $allowtakeover = session_guard::user_allows_login_takeover($userid);

        if (session_guard::has_other_active_session($userid, $currentsid)) {
            if ($allowtakeover) {
                // User has explicit override enabled: allow this login and revoke older sessions.
                session_guard::destroy_other_user_sessions($userid, $currentsid);
                session_guard::set_active_session($userid, $currentsid);
                session_guard::reset_login_takeover_checkbox($userid);
                return;
            }

            // Keep the older active session and deny this new login.
            \core\session\manager::terminate_current();

            if (!(defined('CLI_SCRIPT') && CLI_SCRIPT) &&
                !(defined('AJAX_SCRIPT') && AJAX_SCRIPT) &&
                !(defined('WS_SERVER') && WS_SERVER)) {
                redirect(new \moodle_url('/local/singleloginlock/blocked.php'));
            }
            return;
        }

        session_guard::set_active_session($userid, $currentsid);
        if ($allowtakeover) {
            session_guard::reset_login_takeover_checkbox($userid);
        }
    }
}
