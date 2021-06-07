<?php
require __DIR__ . '/boot.php';

dbg('dataset info', $ds['_info']);

$hr = true;

// $obj_id = array_search($requestpath, $paths);
[$obj_id, $name] = path_rev($paths_rev, $requestpath);

if ($obj_id) {
    $obj = get($ds, $obj_id);

    // $template = $templates[$obj['_type']][$name]['template'];
    $template = template_name($config['templates'], $obj['_type'], $name);
    dbg('template', $template, $obj);
    $content = template(
        $template,
        [
            'page' => $obj,
            'path' => path($paths, $obj_id, $name),
            'template_config' => [], //TODO
            'path_name' => $name
        ],
        $template_helper,
        $src
    );
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

if ($hr) {
    require_once __DIR__ . '/hot-reload/HotReloader.php';
    $htrldr = new HotReloader\HotReloader('//localhost:1199/phrwatcher.php');
    $js = $htrldr->init();
    $content = str_replace('</html>', $js . '</html>', $content);
}
print $content;
