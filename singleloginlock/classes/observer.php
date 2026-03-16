<?php
// This file is part of Moodle - http://moodle.org/
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
 * Single login lock plugin.
 *
 * @package    local_singleloginlock
 * @copyright  2026 Aragaw Mebratu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_singleloginlock;

/**
 * Event observers for local_singleloginlock.
 *
 * @package local_singleloginlock
 */
class observer {
    /**
     * Enforce single-session policy on user login.
     *
     * @param \core\event\user_loggedin $event
     * @return void
     */
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
            global $SESSION;
            $SESSION->singleloginlock_blocked = 1;

            if (
                !(defined('CLI_SCRIPT') && CLI_SCRIPT) &&
                !(defined('AJAX_SCRIPT') && AJAX_SCRIPT) &&
                !(defined('WS_SERVER') && WS_SERVER)
            ) {
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
