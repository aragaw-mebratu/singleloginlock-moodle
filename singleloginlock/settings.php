<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_singleloginlock', get_string('pluginname', 'local_singleloginlock'));

    $enabledsetting = new admin_setting_configcheckbox(
        'local_singleloginlock/enabled',
        get_string('settings:enabled', 'local_singleloginlock'),
        get_string('settings:enabled_desc', 'local_singleloginlock'),
        1
    );
    $enabledsetting->set_updatedcallback('local_singleloginlock_enabled_updated');
    $settings->add($enabledsetting);

    $ADMIN->add('localplugins', $settings);
}
