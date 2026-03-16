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
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Inject active-session heartbeat for logged-in users.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_singleloginlock_extend_navigation(global_navigation $navigation): void {
    global $PAGE;

    if (
        (defined('CLI_SCRIPT') && CLI_SCRIPT) ||
        (defined('AJAX_SCRIPT') && AJAX_SCRIPT) ||
        (defined('WS_SERVER') && WS_SERVER) ||
        during_initial_install()
    ) {
        return;
    }

    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!\local_singleloginlock\session_guard::is_plugin_enabled()) {
        return;
    }
    if (!\local_singleloginlock\session_guard::enforce_current_user_single_session()) {
        redirect(new moodle_url('/login/index.php', ['sessionexpired' => 1]));
        return;
    }
    if (!\local_singleloginlock\session_guard::is_enforced_for_user()) {
        return;
    }

    $config = [
        'pingUrl' => (new moodle_url('/local/singleloginlock/ping.php', ['sesskey' => sesskey()]))->out(false),
        'loginUrl' => (new moodle_url('/login/index.php', ['sessionexpired' => 1]))->out(false),
        'pollMs' => 120000,
    ];
    $jsonconfig = json_encode($config, JSON_UNESCAPED_SLASHES);

    $js = <<<JS
(() => {
    if (window.__singleLoginLockHeartbeatStarted) {
        return;
    }
    window.__singleLoginLockHeartbeatStarted = true;

    const cfg = {$jsonconfig};
    let redirecting = false;

    const kick = () => {
        if (redirecting) {
            return;
        }
        redirecting = true;
        window.location.replace(cfg.loginUrl);
    };

    const beat = async () => {
        try {
            const url = new URL(cfg.pingUrl, window.location.origin);
            url.searchParams.set('_', String(Date.now()));
            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            });

            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            if (!response.ok || !contentType.includes('application/json')) {
                kick();
                return;
            }

            const payload = await response.json();
            if (!payload || payload.loggedin !== true) {
                kick();
            }
        } catch (e) {
            // Ignore transient network issues.
        }
    };

    beat();
    window.setInterval(beat, cfg.pollMs);
})();
JS;

    $PAGE->requires->js_init_code($js);
}

/**
 * Setting updated callback for local_singleloginlock/enabled.
 *
 * @return void
 */
function local_singleloginlock_enabled_updated(): void {
    \local_singleloginlock\session_guard::handle_enabled_setting_update();
}

