<?php
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
