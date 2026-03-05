<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Inject a lightweight heartbeat that detects when this browser session was invalidated elsewhere.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_singlesession_extend_navigation(global_navigation $navigation): void {
    global $PAGE;

    if ((defined('CLI_SCRIPT') && CLI_SCRIPT) ||
        (defined('AJAX_SCRIPT') && AJAX_SCRIPT) ||
        (defined('WS_SERVER') && WS_SERVER) ||
        during_initial_install()) {
        return;
    }

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $config = [
        'pingUrl' => (new moodle_url('/local/singlesession/ping.php'))->out(false),
        'loginUrl' => (new moodle_url('/login/index.php', ['sessionexpired' => 1]))->out(false),
        'pollMs' => 1000,
    ];
    $jsonconfig = json_encode($config, JSON_UNESCAPED_SLASHES);

    $js = <<<JS
(() => {
    if (window.__localSingleSessionWatchStarted) {
        return;
    }
    window.__localSingleSessionWatchStarted = true;

    const cfg = {$jsonconfig};
    let redirecting = false;

    const kick = () => {
        if (redirecting) {
            return;
        }
        redirecting = true;
        window.location.replace(cfg.loginUrl);
    };

    const check = async () => {
        try {
            const response = await fetch(cfg.pingUrl + '?_=' + Date.now(), {
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
            // Ignore transient network failures.
        }
    };

    check();
    window.setInterval(check, cfg.pollMs);
})();
JS;

    $PAGE->requires->js_init_code($js);
}
