<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode(['loggedin' => false]);
    exit;
}

echo json_encode(['loggedin' => true]);
