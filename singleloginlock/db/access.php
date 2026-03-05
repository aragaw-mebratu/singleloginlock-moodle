<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/singleloginlock:enforce' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],
    'local/singleloginlock:takeover' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
];
