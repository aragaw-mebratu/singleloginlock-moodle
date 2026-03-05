<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Inject active-session heartbeat for logged-in users.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_singleloginlock_extend_navigation(global_navigation $navigation): void {
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
    if (!\local_singleloginlock\session_guard::is_enforced_for_user()) {
        return;
    }

    $config = [
        'pingUrl' => (new moodle_url('/local/singleloginlock/ping.php', ['sesskey' => sesskey()]))->out(false),
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

    const beat = async () => {
        try {
            const url = new URL(cfg.pingUrl, window.location.origin);
            url.searchParams.set('_', String(Date.now()));
            await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            });
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
