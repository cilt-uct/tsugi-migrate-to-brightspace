<?php
require_once('../config.php');
include 'src/Template.php';

use \Tsugi\Core\LTIX;

// Retrieve the launch data if present
$LAUNCH = LTIX::requireData();

$menu = false; // We are not using a menu

// Start of the output
$OUTPUT->header();

$context = [
    'styles'     => [ addSession('static/css/app.min.css'), ],
];

Template::view('templates/header.html', $context);

$OUTPUT->bodyStart();

$OUTPUT->topNav($menu);

// style=" background-image: linear-gradient(to right top, #d16ba5, #c777b9, #ba83ca, #aa8fd8, #9a9ae1, #8aa7ec, #79b3f4, #69bff8, #52cffe, #41dfff, #46eefa, #5ffbf1);"
?>
<div class="bgnew"></div>
<?php
$OUTPUT->splashPage(
    "Hi !",
    __("This tool is not available to students.")
);

$OUTPUT->footerStart();

$OUTPUT->footerEnd();