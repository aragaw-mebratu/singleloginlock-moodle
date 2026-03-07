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
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

if (isloggedin() && !isguestuser()) {
    require_login();
}

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
