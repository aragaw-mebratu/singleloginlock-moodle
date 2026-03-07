<?php
/**
 * Single login lock plugin.
 *
 * @package    local_singleloginlock
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

if (!\local_singleloginlock\session_guard::is_plugin_enabled()) {
    redirect(new moodle_url('/login/index.php'));
}

$context = context_system::instance();
$pageurl = new moodle_url('/local/singleloginlock/blocked.php');
$loginurl = new moodle_url('/login/index.php');

@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
@header('Pragma: no-cache');

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('pluginname', 'local_singleloginlock'));
$PAGE->set_heading(get_string('pluginname', 'local_singleloginlock'));

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('loginblocked', 'local_singleloginlock'), 'notifyproblem');
echo html_writer::div(
    $OUTPUT->single_button($loginurl, get_string('login'), 'get'),
    'singleloginlock-actions'
);
echo $OUTPUT->footer();
