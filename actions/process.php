<?php
require_once "../../config.php";
include "../tool-config_dist.php";
require_once("../dao/MigrateDAO.php");

use \Tsugi\Core\LTIX;
use \Migration\DAO\MigrateDAO;

// Retrieve the launch data if present
$LAUNCH = LTIX::requireData();

$site_id = $LAUNCH->ltiRawParameter('context_id','none');

$migrationDAO = new MigrateDAO($PDOX, $CFG->dbprefix, $tool);

$result = ['success' => 0, 'msg' => 'requires POST'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // $result['msg'] = 'POST is mallformed';
    $result['msg'] = $_POST;
    if (isset($_POST['type'])) {

        if (!isset($_POST['is_test'])) {
            $_POST['is_test'] = 0;
        }

        switch($_POST['type']) {
            case 'init':
                $dept = isset($_POST['dept']) ? $_POST['dept'] : '';
                $term = isset($_POST['term']) ? $_POST['term'] : date("Y");
                $provider = isset($_POST['provider']) ? $_POST['provider'] : '';
                $course = isset($_POST['course']) ? $_POST['course'] : (isset($_POST['provider']) ? $_POST['provider']:'');
                $create_course = isset($_POST['create_course']) ? ($_POST['create_course'] == '1'? 1 : 0) : 0;
                $title = isset($_POST['title']) ? $_POST['title'] : $CONTEXT->title;
                $target_course = isset($_POST['course']) ? $_POST['course'] : '';
                $enrol_users = isset($_POST['enrol']) ? $_POST['enrol'] : 1; # Enrol site owners and support staff in the converted site
                $lesson_type = isset($_POST['lesson_type']) ? $_POST['lesson_type'] : 'default';
                $task_id = isset($_POST['clickup_task_id']) ? $_POST['clickup_task_id'] : '';

                $result['success'] = $migrationDAO->startMigration($LINK->id, $USER->id, $site_id,
                                            $_POST['notification'], $dept, $term, $provider, $_POST['is_test'], $enrol_users, $lesson_type,
                                            $title, $target_course, $term, $dept, $create_course, $task_id) ? 1 : 0;
                break;
            case 'updating':
            case 'starting':
            case 'exporting':
            case 'running':
            case 'importing':
            case 'completed':
                $result['success'] = $migrationDAO->updateMigration($LINK->id, $USER->id, $_POST['notification'], $_POST['term']) ? 1 : 0;
            case 'error':
                break;
            case 'add_sites':
                $term = isset($_POST['term']) ? $_POST['term'] : date("Y");

                $result['success'] = $migrationDAO->addSitesMigration($LINK->id, $USER->id,
                                                                        $_POST['sites'], $term,
                                                                        $_POST['is_test'], $_POST['enrol'], $_POST['lesson_type']) ? 1 : 0;
                break;
            case 'delete':
                $result['success'] = $migrationDAO->removeSite($LINK->id, $USER->id, $_POST['site']) ? 1 : 0;
                break;
        }
        $result['msg'] = $result['success'] ? 'Updated' : 'Error Updating';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['type'])) {
        switch($_GET['type']) {
            case 'workflow':
                $result = $migrationDAO->getWorkflowAndReport($LINK->id, $_GET['site']);

                $result = [
                        'success' => $result ? 1 : 0,
                        'workflow' => $result ? json_decode($result['workflow']) : [],
                        'report_url' => $result['report_url'],
                        'state' => $result['state'],
                        'transfer_site_id' => $result['transfer_site_id'] ? $result['transfer_site_id'] : '',
                        'amathuba_site' => $result['imported_site_id'] ? $result['imported_site_id'] : ''
                    ];
                break;
        }
    }
}

echo json_encode($result);
exit;
