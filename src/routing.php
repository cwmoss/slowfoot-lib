<?php

// some routing exceptions
// for development

$docroot = $_SERVER['DOCUMENT_ROOT'];
$requestpath = $_SERVER['SCRIPT_NAME'];
// startseite?
if ($requestpath == '/') {
    $requestpath = '/index';
}
// paginierte startseite?
if (preg_match("!^/\d+$!", $requestpath)) {
    $requestpath = '/index' . $requestpath;
}

$orig = $_SERVER['HTTP_ORIGIN'];
// TODO check list;

header('Access-Control-Allow-Origin: ' . $orig);
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Max-Age: 1000');
if (array_key_exists('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', $_SERVER)) {
    header('Access-Control-Allow-Headers: '
           . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
} else {
    //   header('Access-Control-Allow-Headers: *');
}

// /a/9990
if ($requestpath == '/phrwatcher.php') {
    include 'phrwatcher.php';
    exit;
}

if (preg_match("/\./", $requestpath)) {
    send_file($src, $requestpath);
    exit;
}
