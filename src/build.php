<?php
require __DIR__ . '/boot.php';
dbg("start");
print memory_get_usage() . " paths ok\n";

//$template_helper = load_template_helper($ds, $src);

print memory_get_usage() . " helper ok \n";

//print_r(get($ds, 'p-20604'));
//exit;

if (!$dist) {
    die('NO DIST-PATH FOUND');
}

print_r($ds->info);
//exit;

print "clean up dist/\n\n";
`rm -rf $dist`;

// exit;

foreach ($templates as $type => $conf) {
    //$count = query('');
    //if($type=='article') continue;
    $bs = 100;
    $start = 0;

    foreach (query($ds, $type) as $row) {
        foreach ($conf as $templateconf) {
            //	process_template_data($row, path($row['_id']));
            $path = $ds->get_fpath($row['_id'], $templateconf['name']);
            $content = template(
                $templateconf['template'],
                [
                    'page' => $row,
                    'path' => $ds->get_path($row['_id'], $templateconf['name']),
                    'template_config' => $templateconf,
                    'path_name' => $templateconf['name']
                ],
                $template_helper,
                $src
            );
            write($content, $path, $dist);
        }
    }
}

print memory_get_usage() . " templates ok\n";

foreach ($pages as $pagename) {
    dbg('page... ', $pagename);
    $paginate = check_pagination($pagename, $src);
    $pagepath = $pagename;
    if ($pagepath == '/index') {
        $pagepath = '/';
    }
    if ($paginate) {
        $pagenr = 1;
        $path = $pagepath;
        foreach (chunked_paginate($ds, $paginate) as $coll) {
            dbg('page', $pagenr);
            $content = page($pagename, ['collection' => $coll], $template_helper, $src);
            $content = remove_tags($content);
            write($content, $pagepath, $dist);
            $pagenr++;
            $pagepath = $path . '/' . $pagenr;
        }
    } else {
        $content = page($pagename, [], $template_helper, $src);
        write($content, $pagepath, $dist);
    }
}

print memory_get_usage() . " pages ok\n";

`cp -R $src/css $src/js $dist/`;

print "copy assets\n";

`cp -R $base/cache $dist/images`;

print "finished\n";
