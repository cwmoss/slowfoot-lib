<?php
$cmd = $argv[1];
$slft_lib_base = __DIR__;

if ($cmd == 'dev') {
    $command = "php -S localhost:1199 -t src/ {$slft_lib_base}/dev.php";
    print "*** slowfoot ***\n";
    print "* starting development server $command *\n";
    print "*****************\n";
    `$command`;
}
if ($cmd == 'build') {
    include 'build.php';
}
