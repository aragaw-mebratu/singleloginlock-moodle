<?php
/**
 * Single login lock plugin.
 *
 * @package    local_singleloginlock
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

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
    $existing = $DB->get_record('user_info_field', ['shortname' => $shortname], 'id, datatype', IGNORE_MISSING);
    if (!empty($existing)) {
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

    $visibleall = defined('PROFILE_VISIBLE_ALL') ? (int)PROFILE_VISIBLE_ALL : 2;

    $field = (object)[
        'datatype' => 'checkbox',
        'shortname' => $shortname,
        'name' => 'Allow single login takeover',
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'required' => 0,
        'locked' => 0,
        'visible' => $visibleall,
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

    return true;
}
