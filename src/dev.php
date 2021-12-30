<?php
require __DIR__ . '/boot.php';
use function slowfoot\template\{page, template, remove_tags, preprocess};


$hr = false;
$debug = true;

// $obj_id = array_search($requestpath, $paths);
[$obj_id, $name] = $ds->get_by_path($requestpath);

if ($obj_id) {
    $obj = $ds->get($obj_id);

    // $template = $templates[$obj['_type']][$name]['template'];
    $template = template_name($config['templates'], $obj['_type'], $name);
    dbg('template', $template, $obj);
    $content = template(
        $template,
        [
            'page' => $obj,
            'path' => $ds->get_path($obj_id, $name),
            'template_config' => $config['templates'][$obj['_type']][$name], //TODO
            'path_name' => $name
        ],
        $template_helper,
        $src
    );
    debug_js("page", $obj);

} else {
    list($dummy, $pagename, $pagenr) = explode('/', $requestpath);
    $pagename = '/' . $pagename;
    dbg('page...', $pagename, $pagenr, $requestpath);
    $obj_id = array_search($pagename, $pages);
    #$pagination_query = check_pagination($pagename, $src);
    $pp = preprocess($pagename, $src);
    dbg('page query', $pp);
    if ($page_query = ($pp['page-query']??null)) {
        //var_dump($paginate);
        $qres = $ds->query($page_query['__content']);  // query_page($ds, $pagination_query, $pagenr);
        #var_dump($qres);
        //print_r($coll);
        $content = page($pagename, ['page' => $qres], $template_helper, $src);
        $content = remove_tags($content, ['page-query']);

        debug_js("page", $qres);

    } else {
        $content = page($requestpath, [], $template_helper, $src);

        debug_js("page", []);
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
if($debug){
    $inspector = include_to_buffer(__DIR__.'/assets/debug.php');
    $inspector_css = '<link rel="stylesheet" href="/inspector-json.css">';
    $content = str_replace('</head>', $inspector_css.'</head>', $content);
    $content = str_replace('</body>', $inspector.'</body>', $content);
}
print $content;
