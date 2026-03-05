<?php
namespace local_singlesession;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        $userid = (int)$event->userid;
        if ($userid <= 0) {
            $userid = (int)$event->objectid;
        }
        if ($userid <= 0) {
            $userid = (int)$event->relateduserid;
        }

        $currentsid = (string)session_id();
        if ($userid <= 0 || $currentsid === '') {
            return;
        }

        \core\session\manager::destroy_user_sessions($userid, $currentsid);
    }
}
