<?php
$cmd = $argv[1];
$slft_lib_base = __DIR__;

if ($cmd == 'dev') {
    // this wont work :)
    // `(sleep 1 ; open http://localhost:1199/ )&`;
    // this works!
    shell_exec('(sleep 1 ; open http://localhost:1199/ ) 2>/dev/null >/dev/null &');
    $command = "php -S localhost:1199 -t src/ {$slft_lib_base}/dev.php";
    print "\n\n";
    print "     ***********************************\n";
    print "     *                                 *\n";
    print "     *          slowfoot               *\n";
    print "     *                                 *\n";
    print "     *   starting development server   *\n";
    print "     *                                 *\n";
    print "     *   http://localhost:1199         *\n";
    print "     *                                 *\n";
    print "     *                                 *\n";
    print "     *   have fun!                     *\n";
    print "     *                                 *\n";
    print "     ***********************************\n\n\n";
    `$command`;
}
if ($cmd == 'build') {
    include 'build.php';
}
