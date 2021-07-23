<?php

// some routing exceptions
// for development
//print_r($_SERVER);

if (PHP_SAPI == 'cli-server') {
    $docroot = $_SERVER['DOCUMENT_ROOT'];
    // $requestpath = dirname($_SERVER['SCRIPT_NAME']);
    $requestpath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
} else {
    $requestpath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $docroot = SLOWFOOT_BASE . '/src';
}

//print "REQ: $requestpath";

if (PATH_PREFIX) {
    $requestpath = str_replace(PATH_PREFIX, '', $requestpath);
}

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

//print "REQ: ~$requestpath~";

// /a/9990
if (strpos($requestpath, '/phrwatcher.php') === 0) {
    include __DIR__ . '/hot-reload/phrwatcher.php';
    exit;
}

if (preg_match("/\./", $requestpath)) {
    dbg('+++ dev route file', $requestpath);
    $assetpath = $config['assets']['src'];
    // TODO: single method with mapped directories
    if (preg_match("!^/$assetpath!", $requestpath)) {
        send_asset_file($base, $requestpath, $assetpath, $config['assets']['dest']);
    } else {
        send_file($src, $requestpath);
    }

    exit;
}
