<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\user_loggedin',
        'callback'  => '\\local_singleloginlock\\observer::user_loggedin',
    ],
];
