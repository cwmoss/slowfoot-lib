<?php
$cmd = $argv[1];
$slft_base = SLOWFOOT_BASE;

if ($cmd == 'dev') {
    $command = "php -S localhost:1199 -t src/ {$slft_base}/lib/dev.php";
    print "starting development server $command\n";
    `$command`;
}
if ($cmd == 'build') {
    include 'build.php';
}
