<?php
require __DIR__ . '/boot.php';
use function slowfoot\template\{page, template, remove_tags, preprocess};

dbg("start");
print memory_get_usage() . " paths ok\n";

//$template_helper = load_template_helper($ds, $src);

print memory_get_usage() . " helper ok \n";

//print_r(get($ds, 'p-20604'));
//exit;

if (!$dist) {
    die('NO DIST-PATH FOUND');
}

print_r($config);
print_r($ds->info);
#print_r($ds->db->paths_rev);
#exit;

print "clean up dist/\n\n";
`rm -rf $dist`;
#array_map('unlink', array_filter((array) globstar("$dist/**/*.*")));
// exit;



foreach ($templates as $type => $conf) {
    //$count = query('');
    //if($type=='article') continue;
    $bs = 100;
    $start = 0;

    foreach (query_type($ds, $type) as $row) {
        foreach ($conf as $templateconf) {
            //	process_template_data($row, path($row['_id']));
            $path = $ds->get_fpath($row['_id'], $templateconf['name']);
            if ($path == '/index') {
                $path = '/';
            }
            if($path=="/"){
                var_dump($row);
                exit;
            }
            $content = template(
                $templateconf['template'],
                [
                    'page' => $row,
                    'path' => $path,
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
    // $paginate = check_pagination($pagename, $src);
    $pp = preprocess($pagename, $src);
    $page_query = $pp['page-query']??null;
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
    }elseif($page_query){
        $qres = $ds->query($page_query['__content']);  // query_page($ds, $pagination_query, $pagenr);
        #var_dump($qres);
        //print_r($coll);
        $content = page($pagename, ['page' => $qres], $template_helper, $src);
        $content = remove_tags($content, ['page-query']);
        write($content, $pagepath, $dist);
    }else {
        $content = page($pagename, [], $template_helper, $src);
        write($content, $pagepath, $dist);
    }
}
#exit;

print memory_get_usage() . " pages ok\n";


`cp -R $src/css $src/js $dist/`;

print "copy assets\n";

`cp -R $base/cache $dist/images`;

print "finished\n";
