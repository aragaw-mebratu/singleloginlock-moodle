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
 * Single login lock plugin install steps.
 *
 * @package    local_singleloginlock
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install hook for local_singleloginlock.
 *
 * @return void
 */
function xmldb_local_singleloginlock_install(): void {
    global $CFG;

    require_once($CFG->dirroot . '/local/singleloginlock/db/upgrade.php');
    local_singleloginlock_ensure_allowlogin_profile_field();
}
