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
defined('MOODLE_INTERNAL') || die();

$string['admincategory'] = 'Single login lock';
$string['allowloginfielddesc'] = 'Profile checkbox shortname for override: singleloginlock_allowlogin';
$string['column_enable'] = 'Enable';
$string['column_name'] = 'Name';
$string['column_settings'] = 'Settings';
$string['column_uninstall'] = 'Uninstall';
$string['column_version'] = 'Version';
$string['disablelabel'] = 'Disable';
$string['enablelabel'] = 'Enable';
$string['loginblocked'] = 'Login blocked: this account is currently active on another device.';
$string['managepage'] = 'Manage';
$string['pluginname'] = 'Single login lock';
$string['privacy:metadata'] = 'The Single login lock plugin does not store personal data outside standard user preferences.';
$string['settings:enabled'] = 'Enable plugin';
$string['settings:enabled_desc'] = 'Enable or disable Single login lock behavior without uninstalling the plugin.';
$string['settings:enforcedroles'] = 'Enforced roles';
$string['settings:enforcedroles_desc'] = 'If selected, enforcement applies to users assigned any of these roles. Leave empty to use capability and Student role fallback.';
$string['settingslabel'] = 'Settings';
$string['settingspage'] = 'Settings';
$string['singleloginlock:enforce'] = 'Be subject to single-login lock enforcement';
$string['uninstalllabel'] = 'Uninstall';
