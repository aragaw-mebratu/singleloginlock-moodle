<?php
namespace local_singleloginlock;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {
    /**
     * Show an explanatory error on login page when a second login is blocked.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     * @return void
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE, $OUTPUT;

        if (!session_guard::is_plugin_enabled()) {
            return;
        }

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }

        if (isloggedin() && !isguestuser()) {
            if (!session_guard::enforce_current_user_single_session()) {
                redirect(new \moodle_url('/login/index.php', ['sessionexpired' => 1]));
            }
        }

        $showblocked = optional_param('singleloginlock', 0, PARAM_BOOL);
        if (!$showblocked || empty($PAGE->url)) {
            return;
        }

        $isloginpage = $PAGE->url->compare(new \moodle_url('/login/index.php'), URL_MATCH_BASE);
        if (!$isloginpage) {
            return;
        }

        $hook->add_html($OUTPUT->notification(
            get_string('loginblocked', 'local_singleloginlock'),
            'notifyproblem'
        ));
    }
}
