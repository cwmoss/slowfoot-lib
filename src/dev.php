<?php
require __DIR__ . '/boot.php';

$obj_id = array_search($requestpath, $paths);
if ($obj_id) {
    $obj = get($ds, $obj_id);

    $template = $templates[$obj['_type']]['template'];
    dbg('template', $template, $obj);
    $content = template($template, ['page' => $obj], $template_helper, $src);
} else {
    list($dummy, $pagename, $pagenr) = explode('/', $requestpath);
    $pagename = '/' . $pagename;
    dbg('page...', $pagename, $pagenr, $requestpath);
    $obj_id = array_search($pagename, $pages);
    $pagination_query = check_pagination($pagename, $src);
    dbg('page query', $pagination_query);
    if ($pagination_query) {
        //var_dump($paginate);
        $coll = query_page($ds, $pagination_query, $pagenr);
        //print_r($coll);
        $content = page($pagename, ['collection' => $coll], $template_helper, $src);
        $content = remove_tags($content);
    } else {
        $content = page($requestpath, [], $template_helper, $src);
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: text/html');

print $content;
