<?php

$base = SLOWFOOT_BASE;

$writeable = [
    'var',
    'var/download',
    'var/rendered-images',
    'var/template'
];

foreach ($writeable as $dir) {
    print "checking $dir ";
    $fdir = $base.'/'.$dir;
    if (!file_exists($fdir)) {
        print '... creating ';
        mkdir($fdir);
    }

    if (!is_dir($fdir)) {
        print "... not a directory. please make it a writeable directory\n";
        continue;
    }

    print "... ok\n";
}