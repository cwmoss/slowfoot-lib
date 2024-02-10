<?php
//print_r($_SERVER);
define('PATH_PREFIX', dirname($_SERVER['SCRIPT_NAME']));

require __DIR__ . '/_boot.php';

$hr = true;
$hr_host = $_SERVER['HTTP_HOST'];
$hr_port = $_SERVER['SERVER_PORT'];

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

if ($hr) {
    require_once __DIR__ . '/hot-reload/HotReloader.php';
    $htrldr = new HotReloader\HotReloader("//{$hr_host}:{$hr_port}" . PATH_PREFIX . '/phrwatcher.php');
    $js = $htrldr->init();
    $content = str_replace('</html>', $js . '</html>', $content);
}
print $content;
