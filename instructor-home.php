<?php
require_once "../config.php";
include 'tool-config_dist.php';
include 'src/Template.php';

require_once "dao/MigrateDAO.php";

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use \Tsugi\Core\LTIX;
use \Migration\DAO\MigrateDAO;

// Retrieve the launch data if present
$LAUNCH = LTIX::requireData();

$site_id = $LAUNCH->ltiRawParameter('context_id','none');
$course_providers  = $LAUNCH->ltiRawParameter('lis_course_section_sourcedid','none');
$context_id = $LAUNCH->ltiRawParameter('context_id','none');
$context_title = $LAUNCH->ltiRawParameter('context_title','No Title');
$user_role = str_contains($LAUNCH->ltiRawParameter('roles', 'none'), 'Administrator') ? 1 : 0;

$provider = "none";
$title = $CONTEXT->title;
$clickup_option_id = $tool['clickup_option_Id'];
$site_title = strstr($title, ",", true);
$has_site_properties = false;
$has_clickup_task = false;
$site_properties = [];
$clickup_task = null;

if ($course_providers != $context_id) {
    // So we might have some providers to show
    $list = explode('+', $course_providers);

    if (count($list) == 1) {
        $provider = $list[0];
    } else {
        $provider = $list;
    }
}

$migrationDAO = new MigrateDAO($PDOX, $CFG->dbprefix, $tool);
$current_migration = $migrationDAO->getMigration($LINK->id, $USER->id, $site_id, $provider, false, $context_title);

// Get site properties and clickup tasks
function getMigrationPhase($clickup_option_id, $title, $migrationDAO, $site_id, $site_title, $has_site_properties, $has_clickup_task, $site_properties, $clickup_task) {
    $error_message = '';
    try {
        $site_properties = $migrationDAO->getSiteProperties($site_id);

        if ($site_properties && !isset($site_properties['error']) && in_array($site_properties['subject'], ["Migration_Phase2:Year_2", "Migration_Phase2:Year_3"])) {
            $clickup_task = $migrationDAO->getClickupTaskByName($site_title);
            $has_site_properties = true;
            $has_clickup_task = (bool) $clickup_task;
        }
    } catch (SoapFault $soapFault) {
        $error_message = 'Caught SOAP Fault: ' . $soapFault->getMessage();
    } catch (Exception $e) {
        $error_message = 'Caught Exception: ' . $e->getMessage();
    }

    $result = [
        "error" => $error_message,
        "has_site_properties" => $has_site_properties,
        "has_clickup_task" => $has_clickup_task,
        "site_properties" => $site_properties,
        "clickup_task" => $clickup_task
    ];

    return $result;
}

$siteMigrationPhase = getMigrationPhase($clickup_option_id, $title, $migrationDAO, $site_id, $site_title, $has_site_properties, $has_clickup_task, $site_properties, $clickup_task);
$site_properties = $siteMigrationPhase["site_properties"];
$clickup_task = $siteMigrationPhase["clickup_task"];

$menu = false; // We are not using a menu

$workflow = $current_migration['workflow'] ? json_decode($current_migration['workflow']) : [];

// $title = 'Pan-African Ensemble 2021';
// $provider = array('MUZ1366H,2021','MUZ2366H,2021','MUZ3366H,2021');

// $title = 'Med Gen 2 PTY5006S,2021';
// $provider = 'none';

// $title = 'EDN4507F,2022 Test';
// $provider = 'EDN4507F,2022';

function time_Ago($time) {

    // Calculate difference between current
    // time and given timestamp in seconds
    $diff     = time() - $time;

    // Time difference in seconds
    $sec     = $diff;

    // Convert time difference in minutes
    $min     = round($diff / 60 );

    // Convert time difference in hours
    $hrs     = round($diff / 3600);

    // Convert time difference in days
    $days     = round($diff / 86400 );

    // Convert time difference in weeks
    $weeks     = round($diff / 604800);

    // Convert time difference in months
    $mnths     = round($diff / 2600640 );

    // Convert time difference in years
    $yrs     = round($diff / 31207680 );

    // Check for seconds
    if($sec <= 60) {
        return "$sec seconds ago";
    }

    // Check for minutes
    else if($min <= 60) {
        if($min==1) {
            return "one minute ago";
        }
        else {
            return "$min minutes ago";
        }
    }

    // Check for hours
    else if($hrs <= 24) {
        if($hrs == 1) {
            return "an hour ago";
        }
        else {
            return "$hrs hours ago";
        }
    }

    // Check for days
    else if($days <= 7) {
        if($days == 1) {
            return "since yesterday";
        }
        else {
            return "since $days days ago";
        }
    }

    // Check for weeks
    else if($weeks <= 4.3) {
        if($weeks == 1) {
            return "since a week ago";
        }
        else {
            return "since $weeks weeks ago";
        }
    }

    // Check for months
    else if($mnths <= 12) {
        if($mnths == 1) {
            return "since a month ago";
        }
        else {
            return "since $mnths months ago";
        }
    }

    // Check for years
    else {
        if($yrs == 1) {
            return "since one year ago";
        }
        else {
            return "since $yrs years ago";
        }
    }
}

$modified_time = strtotime($current_migration['modified_at']);
$time_modified = time_Ago($modified_time);
/*$started_time = $current_migration['modified_at'];

$time_ago = strtotime($started_time);*/


function get_provider_object($provider, $title) {

    if (preg_match("/Turnitin/i", $title)) {
        return [];
    }

    $test = $provider;
    if ($provider == 'none') {
        # see if we can get it from the title ???
        $test = [ strtoupper($title) ];
    }

    if (gettype($test) == "string") {
        $test = [ $test ];
    }
    $course_sites_list = array();
    $project_sites_list = array();
    $list = array();

    // To find full course codes
    foreach($test as $t) {
        preg_match('/^([A-Za-z]{3})\s?(\d)(\d{3})([A-Z]{0,})[\s|,]?(\d{4})?/', $t, $matches);
        if (count($matches) >= 1) {
            array_push($course_sites_list, [ 'full' => $matches[0] ?? '',
                                'dept' => $matches[1] ?? '',
                                'year' => $matches[2] ?? '',
                                'no' => $matches[3] ?? '',
                                'period' => $matches[4] ?? '',
                                'term' => $matches[5] ?? '',
                                'course' => ($matches[1] ?? '') . ($matches[2] ?? '') . ($matches[3] ?? '') . ($matches[4] ?? '')
                            ]);
        }
    }

    // to find program codes - We can use this later to determine Faculty if need be
    // foreach($test as $t) {
    //     preg_match('/([A-Za-z]{2})\s?(\d)(\d{2})([A-Z]{0,})[\s|,]?(\d{4})?/', $t, $matches);
    //     if (count($matches) >= 1) {
    //         array_push($project_sites_list, [ 'full' => $matches[0] ?? '',
    //                             'dept' => $matches[1] ?? '',
    //                             'year' => $matches[2] ?? '',
    //                             'no' => $matches[3] ?? '',
    //                             'term' => $matches[5] ?? ''
    //                         ]);
    //     }
    // }

    if (count($course_sites_list) >= 1) {
        $list = $course_sites_list;
    } else if (count($project_sites_list) >= 1) {
        $list = $project_sites_list;
    }

    return $list;
}

$provider_details = get_provider_object($provider, $title);

## For multiple providers we are not doing that for now
# single site == 1 (Working)
# no provider / project site <= 0 (Working)
# more than one provider > 1 (Coming soon)
/*if (count($provider_details) > 1) {
    header( 'Location: '.addSession('coming-soon.php') ) ;
    exit;
}*/

$context = [
    'instructor' => $USER->instructor,
    'user_role' => $user_role,
    'styles'     => [ addSession('static/css/app.min.css') ],
    'scripts'    => [ addSession('static/js/jquery.email.multiple.js'), addSession('static/js/jquery.validate.min.js'),  ],
    'title'      => $title,
    'site_id'    => $site_id,
    'imported_site_id' => $current_migration['imported_site_id'],
    'transfer_site_id' => $current_migration['transfer_site_id'],
    'target_site_id' => $current_migration['target_site_id'],
    'current_email' => $USER->email,
    'email'      => $current_migration['state'] == 'init' ? $USER->email : $current_migration['email'],
    'name'       => $current_migration['state'] == 'init' ? $USER->displayname : $current_migration['displayname'],
    'notifications' => $current_migration['notification'],

    // 'init','starting','exporting','running','importing','completed','error','admin'
    'state'      => $current_migration['state'],
    'workflow'   => $workflow,
    'years'      => range(date("Y"), date("Y")+1),
    'reset'      => addSession( str_replace("\\","/",$CFG->getCurrentFileUrl('actions/reset.php')) ),
    'submit'     => addSession( str_replace("\\","/",$CFG->getCurrentFileUrl('actions/process.php')) ),
    'fetch_workflow' => addSession( str_replace("\\","/",$CFG->getCurrentFileUrl('actions/process.php')) ),
    'fetch_report'   => $current_migration['report_url'],
    'report_url' =>  $current_migration['report_url'],
    'has_report' => strlen($current_migration['report_url'] ?? '') > 0,
    'provider'   => $provider,
    'provider_details'=> $provider_details,
    'started' => $current_migration['started_at'],
    'modified_at' => $current_migration['modified_at'],
    'last_modified' => $time_modified,
    'current_provider' => $current_migration['provider'],
    'current_dept'     => $current_migration['dept'],
    'current_term'     => $current_migration['term'],
    'target_title' => $current_migration['target_title'],
    'target_course' => $current_migration['target_course'],
    'target_term' => $current_migration['target_term'] == OTHER ? 'other' : $current_migration['target_term'],
    'target_dept' => $current_migration['target_dept'],
    'create_course_offering' => $current_migration['create_course_offering'],
    'site_size' => $migrationDAO->getSiteSize($site_id),
    'has_site_properties' => $siteMigrationPhase["has_site_properties"],
    'site_properties' => $site_properties,
    'has_clickup_task' => $siteMigrationPhase["has_clickup_task"],
    'clickup_task' => $clickup_task,
    'task_id' => ($clickup_task && isset($clickup_task['id'])) ? $clickup_task['id'] : null,
    'clickup_custom_fields' => ($clickup_task && isset($clickup_task['custom_fields'])) ? $clickup_task['custom_fields'] : null,
    'clickup_option_id' => $tool['clickup_option_Id'],
    'lesson_choice' => false, // for single conversions we hide lesson choice - for now
    'departments' => $departments,
    'all_departments' => $full_departments_list,
    'brightspace_url' => $tool['brightspace_url']
];

if (!$USER->instructor) {
    header('Location: ' . addSession('student-home.php'));
}

// Start of the output
$OUTPUT->header();

Template::view('templates/header.html', $context);

$OUTPUT->bodyStart();
$OUTPUT->topNav($menu);

if ($tool['debug']) {
    echo '<pre>'; print_r($context); echo '</pre>';
}

Template::view('templates/instructor-body.html', $context);

$OUTPUT->footerStart();

Template::view('templates/instructor-footer.html', $context);

$OUTPUT->footerEnd();

?>
