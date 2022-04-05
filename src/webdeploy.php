<?php
/*

evtl alternativ via event source
https://developer.mozilla.org/en-US/docs/Web/API/EventSource/EventSource

https://www.py4u.net/discuss/212391
https://stackoverflow.com/questions/56415703/live-execute-git-command-on-php

*/
#require_once __DIR__.'/../vendor/autoload.php';
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;

define('SLOWFOOT_WEBDEPLOY', true);
$orig = $_SERVER['HTTP_ORIGIN'];

// TODO check list;

header('Access-Control-Allow-Origin: '.$orig);
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Max-Age: 1000');
if (array_key_exists('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', $_SERVER)) {
    header('Access-Control-Allow-Headers: '
         . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
} else {
    //   header('Access-Control-Allow-Headers: *');
}

header('Access-Control-Allow-Credentials: true');
#  header('Access-Control-Allow-Headers: Authorization');
header('Access-Control-Expose-Headers: Authorization');

if ("OPTIONS" == $_SERVER['REQUEST_METHOD']) {
    exit(0);
}

$ok = check_referer($_SERVER);
if (!$ok) {
    print "\nfailed\n";
    # print_r($_SERVER);
    exit;
}

$ok = check_token(getenv('SLFT_BUILD_KEY'));
if (!$ok) {
    print "\nauth failed\n";
    # print_r($_SERVER);
    exit;
}

/*
    some provider might not have a php cli
    with the right version but a http php SAPI
    so web-deploy/http.php could be used
*/
if ($NOCLI) {
    print "http build\n";
    $FETCH = true;
    
    require __DIR__ . '/boot.php';
    include 'build.php';
    exit;
}

#header('X-Accel-Buffering: no');
#header("Content-Type: text/plain; charset=utf-8");
//header("Content-Type: application/json");
// print $cmd;
$cmd = SLOWFOOT_BASE.'/slowfoot build';
$converter = new AnsiToHtmlConverter();
#print $converter->convert("hier \033[1mfett\033[0m text");
$converter=null;
$result = liveExecuteCommand($cmd, true, $converter);

if ($result['exit_status'] === 0) {
    // do something if command execution succeeds
    print "ok\n\n";
#`cd $dir; rsync -avz dist/ ../htdocs/`;
} else {
    // do something on failure
    print "failed\n\n";
}

printf('<a href="%s" target="_slft_preview">Look here</a>', '//'. $_SERVER['HTTP_HOST'].'/'.getenv("SLFT_PATH_PREFIX"));

function check_referer($headers)
{
    // local (dev) installation?
    if ($headers['HTTP_HOST']=='localhost') {
        return true;
    }

    if (!isset($headers['SLFT_WEBDEPLOY_ALLOWED_HOSTS'])) {
        return true;
    }
    
    // $allowed = ['localhost', 'sf-photog.sanity.studio', 'kurparkverlag-gs-studio.netlify.app', 'kurparkverlag.sanity.studio'];
    $allowed = explode(" ", $headers['SLFT_WEBDEPLOY_ALLOWED_HOSTS']);

    # sometimes referer doesn't include the full url (/dashboard)
    # if(!preg_match("!/dashboard$!", $headers['HTTP_REFERER'])) return false;

    $remote = parse_url($headers['HTTP_ORIGIN'], PHP_URL_HOST);

    return in_array($remote, $allowed);
}

function check_token($token)
{
    $hdrs = getallheaders();
    $hdrs = array_change_key_case($hdrs);
    return $hdrs['x-slft-deploy']==$token;
}

function liveExecuteCommand($cmd, $err=false, $converter=null)
{
    $lbr = "\n";
    $lbr = "";
    while (@ ob_end_flush()); // end all output buffers if any

    if ($err) {
        $cmd.=" 2>&1";
    }
    // $proc = popen("$cmd 2>&1 ; echo Exit status : $?", 'r');
    $proc = popen("$cmd ; echo Exit status : $?", 'r');
    $live_output     = "";
    $complete_output = "";

    while (!feof($proc)) {
        $live_output     = fread($proc, 4096);
        if ($converter) {
            $live_output = $converter->convert($live_output);
        }
        $complete_output = $complete_output . $live_output;
        echo "$live_output".$lbr."<br>";
        // echo($converter->convert($live_output.$lbr)."<br>");
        // echo json_encode(['txt'=>$live_output]);
        @ flush();
    }

    pclose($proc);

    // get exit status
    preg_match('/[0-9]+$/', $complete_output, $matches);

    // return exit status and intended output
    return array(
                    'exit_status'  => intval($matches[0]),
                    'output'       => str_replace("Exit status : " . $matches[0], '', $complete_output)
                 );
}
#define('PATH_PREFIX', $_SERVER['SCRIPT_NAME']);

#include 'build.php';
