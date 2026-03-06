<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'loggedin' => false]);
    exit;
}

if (!\local_singleloginlock\session_guard::is_plugin_enabled()) {
    echo json_encode(['ok' => true, 'loggedin' => true, 'enabled' => false]);
    exit;
}

require_sesskey();

$stillvalid = \local_singleloginlock\session_guard::heartbeat_current_session();
if (!$stillvalid || !isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'loggedin' => false]);
    exit;
}

echo json_encode(['ok' => true, 'loggedin' => true]);
