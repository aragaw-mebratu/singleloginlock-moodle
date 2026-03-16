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
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $parent = $ADMIN->locate('modules') ? 'modules' : 'localplugins';
    $categoryname = 'local_singleloginlock_admin';

    if (!$ADMIN->locate($categoryname)) {
        $ADMIN->add($parent, new admin_category(
            $categoryname,
            get_string('pluginname', 'local_singleloginlock')
        ));
    }

    $settings = new admin_settingpage(
        'local_singleloginlock',
        get_string('pluginname', 'local_singleloginlock')
    );

    $enabledsetting = new admin_setting_configcheckbox(
        'local_singleloginlock/enabled',
        get_string('settings:enabled', 'local_singleloginlock'),
        get_string('settings:enabled_desc', 'local_singleloginlock'),
        1
    );
    $enabledsetting->set_updatedcallback('local_singleloginlock_enabled_updated');
    $settings->add($enabledsetting);

    $roleoptions = [];
    $roles = get_all_roles();
    foreach ($roles as $role) {
        $roleoptions[$role->id] = role_get_name($role, \context_system::instance(), ROLENAME_BOTH);
    }
    $enforcedroles = new admin_setting_configmultiselect(
        'local_singleloginlock/enforcedroles',
        get_string('settings:enforcedroles', 'local_singleloginlock'),
        get_string('settings:enforcedroles_desc', 'local_singleloginlock'),
        [],
        $roleoptions
    );
    $settings->add($enforcedroles);
    $ADMIN->add($categoryname, $settings);

    if (!$ADMIN->locate('local_singleloginlock_manage')) {
        $ADMIN->add($categoryname, new admin_externalpage(
            'local_singleloginlock_manage',
            get_string('managepage', 'local_singleloginlock'),
            new moodle_url('/local/singleloginlock/manage.php'),
            'moodle/site:config'
        ));
    }
}
