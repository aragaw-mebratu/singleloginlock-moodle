<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_singleloginlock', get_string('pluginname', 'local_singleloginlock'));

    $settings->add(new admin_setting_configcheckbox(
        'local_singleloginlock/enabled',
        get_string('settings:enabled', 'local_singleloginlock'),
        get_string('settings:enabled_desc', 'local_singleloginlock'),
        1
    ));

    $ADMIN->add('localplugins', $settings);
}
