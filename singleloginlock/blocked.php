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
require_once(__DIR__ . '/../../config.php');

if (isloggedin() && !isguestuser()) {
    require_login();
}

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
