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
require_once($CFG->libdir . '/adminlib.php');

use local_singleloginlock\session_guard;

require_login();
admin_externalpage_setup('local_singleloginlock_manage');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'toggle' && confirm_sesskey()) {
    $enabled = session_guard::is_plugin_enabled();
    set_config('enabled', $enabled ? 0 : 1, 'local_singleloginlock');
    session_guard::handle_enabled_setting_update();
    redirect(new moodle_url('/local/singleloginlock/manage.php'));
}

$pluginmanager = \core_plugin_manager::instance();
$plugininfo = $pluginmanager->get_plugin_info('local_singleloginlock');
$version = '';
if (!empty($plugininfo)) {
    if (!empty($plugininfo->versiondb)) {
        $version = (string)$plugininfo->versiondb;
    } else if (!empty($plugininfo->versiondisk)) {
        $version = (string)$plugininfo->versiondisk;
    }
}

$enabled = session_guard::is_plugin_enabled();
$toggleurl = new moodle_url('/local/singleloginlock/manage.php', [
    'action' => 'toggle',
    'sesskey' => sesskey(),
]);
$toggleicon = new pix_icon(
    $enabled ? 't/hide' : 't/show',
    get_string($enabled ? 'disablelabel' : 'enablelabel', 'local_singleloginlock')
);
$togglecontrol = $OUTPUT->action_icon($toggleurl, $toggleicon);
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_singleloginlock']);
$uninstallurl = new moodle_url('/admin/plugins.php', [
    'action' => 'uninstall',
    'plugin' => 'local_singleloginlock',
    'sesskey' => sesskey(),
]);

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('column_name', 'local_singleloginlock'),
    get_string('column_version', 'local_singleloginlock'),
    get_string('column_enable', 'local_singleloginlock'),
    get_string('column_settings', 'local_singleloginlock'),
    get_string('column_uninstall', 'local_singleloginlock'),
];
$table->data[] = [
    format_string(get_string('pluginname', 'local_singleloginlock')),
    s($version),
    $togglecontrol,
    html_writer::link($settingsurl, get_string('settingslabel', 'local_singleloginlock')),
    html_writer::link($uninstallurl, get_string('uninstalllabel', 'local_singleloginlock')),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepage', 'local_singleloginlock'));
echo html_writer::table($table);
echo $OUTPUT->footer();

