<?php
require_once "../config.php";
include "tool-config.php";
include 'src/Template.php';

require_once "dao/MigrateDAO.php";

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ini_set('memory_limit', '512M');

use \Tsugi\Core\LTIX;
use \Migration\DAO\MigrateDAO;

// Retrieve the launch data if present
$LAUNCH = LTIX::requireData();

if (!$USER->instructor) {
    header('Location: ' . addSession('student-home.php'));
}

function getFilteredArray($params, $data) {
    $filtredArray = [];
    foreach($params as $key => $value) {
        foreach($data as $index => $item) {
            if(array_key_exists($key, $item) && in_array($value, $params)) {
                if($item[$key] == $value ){
                    $filtredArray[$index] = $item;
                } else {
                    continue;
                }
            }
        }
    }
    return $filtredArray;
}

$debug = $tool['debug'] == true || $LAUNCH->ltiRawParameter('custom_debug', false) == true;

$migrationDAO = new MigrateDAO($PDOX, $CFG->dbprefix, $tool);
$current_site_id = $LAUNCH->ltiRawParameter('context_id','none');

$sites = $migrationDAO->getMigrationsPerLink($LINK->id);

$states = array('init' => 0,'starting' => 0,'exporting' => 0,'running' => 0,'importing' => 0,'updating' => 0,'completed' => 0,'error' => 0);

$admin_sites_list = $migrationDAO->getAdminSiteIDs();
$admin_site_stats_raw = $migrationDAO->getAdminSiteStatus();
$single_sites_all = $migrationDAO->getSingleSites();

$admin_sites = array();
foreach ($admin_sites_list as $v) {

    $start = getFilteredArray(array('link_id' => $v['link_id']), $admin_site_stats_raw);
    $end = array();
    foreach ($start as $e) {
        $end[$e['state']] = $e['n'];
    }

    $admin_sites[ $v['link_id'] ] = array('title' => $v['title'],
                                            'site_id' => $v['site_id'],
                                            'created' => $v['created_at'],
                                            'stats' => array_merge($states, $end));
}

$single_site_stats_raw = $migrationDAO->getSingleSiteStatus();
$single_site_stats = array();
foreach ($single_site_stats_raw as $v) {
    $single_site_stats[$v['state']] = $v['n'];
}

$menu = false; // We are not using a menu

$context = [
    'instructor' => $USER->instructor,
    'styles'     => [ addSession('static/css/app.min.css'), ],
    'scripts'    => [ ],
    'debug'      => $debug,
    'states'    => $states,
    'admin_sites' => $admin_sites,
    'single_sites_list' => $single_sites_all,
    'single_sites' => array_merge($states, $single_site_stats),
    'reload_url' => addSession( str_replace("\\","/",$CFG->getCurrentFileUrl('index.php')) ),
    'fetch_workflow' => addSession( str_replace("\\","/",$CFG->getCurrentFileUrl('actions/process.php')) ),
    'fetch_report'   => addSession( str_replace("\\","/",$CFG->getCurrentFileUrl('report.php')) ),
    'fetch_single_sites'   => addSession( str_replace("\\","/",$CFG->getCurrentFileUrl('single_sites.php')) ),
];

// Start of the output
$OUTPUT->header();

Template::view('templates/header.html', $context);

$OUTPUT->bodyStart();
$OUTPUT->topNav($menu);

echo "<!--<h2>Super Admin</h2>-->";

if ($debug) {
    echo '<pre>'; print_r($context); echo '</pre>';
}

Template::view('templates/superadmin-body.html', $context);

$OUTPUT->footerStart();

Template::view('templates/superadmin-footer.html', $context);

$OUTPUT->footerEnd();

?>