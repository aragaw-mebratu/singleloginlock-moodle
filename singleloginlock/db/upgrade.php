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
 * Ensure takeover profile checkbox field exists.
 *
 * @return void
 */
function local_singleloginlock_ensure_allowlogin_profile_field(): void {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/user/profile/lib.php');
    require_once($CFG->dirroot . '/user/profile/definelib.php');
    require_once($CFG->dirroot . '/user/profile/field/checkbox/define.class.php');

    $shortname = \local_singleloginlock\session_guard::PROFILE_FIELD_ALLOWLOGIN;
    $existing = $DB->get_record('user_info_field', ['shortname' => $shortname], 'id, datatype, locked, visible', IGNORE_MISSING);
    if (!empty($existing)) {
        $visiblenone = defined('PROFILE_VISIBLE_NONE') ? (int)PROFILE_VISIBLE_NONE : 0;
        $needsupdate = false;
        if ((int)$existing->locked !== 1) {
            $existing->locked = 1;
            $needsupdate = true;
        }
        if ((int)$existing->visible !== $visiblenone) {
            $existing->visible = $visiblenone;
            $needsupdate = true;
        }
        if ($needsupdate) {
            $DB->update_record('user_info_field', $existing);
        }
        set_config('allowlogin_field_ready', 1, 'local_singleloginlock');
        return;
    }

    $categoryid = (int)$DB->get_field_sql('SELECT id FROM {user_info_category} ORDER BY sortorder ASC');
    if ($categoryid <= 0) {
        $maxsortorder = (int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {user_info_category}');
        $category = (object)[
            'name' => 'Single login lock',
            'sortorder' => $maxsortorder + 1,
        ];
        $categoryid = (int)$DB->insert_record('user_info_category', $category);
    }

    $visiblenone = defined('PROFILE_VISIBLE_NONE') ? (int)PROFILE_VISIBLE_NONE : 0;

    $field = (object)[
        'datatype' => 'checkbox',
        'shortname' => $shortname,
        'name' => 'Allow single login takeover',
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'required' => 0,
        'locked' => 1,
        'visible' => $visiblenone,
        'forceunique' => 0,
        'signup' => 0,
        'categoryid' => $categoryid,
        'defaultdata' => 0,
        'defaultdataformat' => 0,
        'param1' => '',
        'param2' => '',
        'param3' => '',
        'param4' => '',
        'param5' => '',
    ];

    $definition = new \profile_define_checkbox();
    $definition->define_save($field);

    set_config('allowlogin_field_ready', 1, 'local_singleloginlock');
}

/**
 * Upgrade steps for local_singleloginlock.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_singleloginlock_upgrade(int $oldversion): bool {
    if ($oldversion < 2026030603) {
        local_singleloginlock_ensure_allowlogin_profile_field();
        upgrade_plugin_savepoint(true, 2026030603, 'local', 'singleloginlock');
    }

    if ($oldversion < 2026030604) {
        local_singleloginlock_ensure_allowlogin_profile_field();
        upgrade_plugin_savepoint(true, 2026030604, 'local', 'singleloginlock');
    }

    if ($oldversion < 2026031600) {
        local_singleloginlock_ensure_allowlogin_profile_field();
        upgrade_plugin_savepoint(true, 2026031600, 'local', 'singleloginlock');
    }

    return true;
}

