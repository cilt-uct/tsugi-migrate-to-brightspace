<?php
require_once('../config.php');
include 'tool-config.php';

require_once "dao/MigrateDAO.php";

use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \Migration\DAO\MigrateDAO;

$LAUNCH = LTIX::requireData();

$is_admin = $LAUNCH->ltiRawParameter('custom_admin', false);
// custom_admin=true

$site_id = $LAUNCH->ltiRawParameter('context_id','none');
$course_providers  = $LAUNCH->ltiRawParameter('lis_course_section_sourcedid','none');
$context_id = $LAUNCH->ltiRawParameter('context_id','none');
$provider = "none";

if ($course_providers != $context_id) {
    // So we might have some providers to show
    $list = explode('+', $course_providers);
        
    if (count($list) == 1) {
        $provider = $list[0];
    } else {
        $provider = $list;
    }
}

$menu = false; // We are not using a menu
if ( $USER->instructor ) {
    $migrationDAO = new MigrateDAO($PDOX, $CFG->dbprefix);
    $current_migration = $migrationDAO->getMigration($LINK->id, $USER->id, $site_id, $provider, $is_admin);

    if (($is_admin == 'true') || ($current_migration['is_admin'] === 1)) {
        header( 'Location: '.addSession('admin-home.php') ) ;
    } else {
        header( 'Location: '.addSession('instructor-home.php') ) ;
    }
} else {
    header( 'Location: '.addSession('student-home.php') ) ;
}
