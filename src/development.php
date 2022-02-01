<?php
require __DIR__ . '/boot.php';
#require_once __DIR__.'/../vendor/autoload.php';
#dbg("++start");
require_once __DIR__.'/utils_server.php';
#dbg("++start");
use function slowfoot\template\page;
use function slowfoot\template\template;
use function slowfoot\template\remove_tags;
use function slowfoot\template\preprocess;

#dbg("++start");
#dbg("SERVER", $_SERVER);
ini_set("precision", 16);
define('START_TIME', microtime(true));

error_reporting(E_ALL & ~E_NOTICE);

if (PHP_SAPI == 'cli-server') {
    if (strpos($_SERVER['REQUEST_URI'], '.') !== false) {
        #dbg('+++ env hack!');
        $_SERVER['SCRIPT_NAME'] = '/' . basename($_SERVER['SCRIPT_FILENAME']);
    }
}

$router = new \Bramus\Router\Router();

$hr = false;
$debug = true;

$router->mount('/__api', function () use ($router, $ds) {
    #dbg('server', $_SERVER);
    send_cors();
    $router->get('/index', function () use ($router, $ds) {

        #print "hallo";

        //$rows = $db->run('SELECT * FROM docs LIMIT 20');
        // $rows = $db->run('SELECT _type, count(*) AS total FROM docs GROUP BY _type');

        resp($ds->info());
    });

    $router->get('/type/([-\w.]+)(/\d+)?', function ($type, $page=1) use ($router, $ds) {

        #print "hallo";

        //$rows = $db->run('SELECT * FROM docs LIMIT 20');
        //$rows = $db->q('SELECT _id, body FROM docs WHERE _type = ? LIMIT 20', $type);
        $rows = $ds->query_type($type);
        resp(['rows'=>$rows]);
    });

    $router->get('/id/([-\w.]+)?', function ($id) use ($router, $ds) {

        //$row = $db->row('SELECT _id, _type, body FROM docs WHERE _id = ? ', $id);
        $row = $ds->get($id);
        resp($row);
    });

    $router->get('/fts', function () use ($router, $ds) {
        $q = $_GET['q'];
        $rows = $ds->q("SELECT _id, snippet(docs_fts,1, '<b>', '</b>', '[...]', 30) body FROM docs_fts WHERE docs_fts = ? ", $q);
        resp($rows);
    });
});

$router->mount('/__ui', function () use ($router, $ds) {
    $router->get('/', function () use ($router, $ds) {
        $uibase = __DIR__.'/../ui/build';
        #dbg("+++ ui index ++++", $uibase);
        send_file($uibase, 'index.html');
        exit;
    });

    $router->get('(.*)?', function ($file) use ($router, $ds) {
        $uibase = __DIR__.'/../ui/build';
        $uifile = $uibase.'/'.$file;
        dbg("__ui file00", $file, $uifile);

        if (file_exists($uifile)) {
            send_file($uibase, $file);
            exit;
        } else {
            send_file($uibase, 'index.html');
            exit;
        }
        dbg("__ui file", $file, $uifile);
        resp(['ok'=>$file]);
    });
});

$router->get('/__sf/(.*)', function ($requestpath) use ($router, $ds) {
    $docbase = __DIR__.'/assets';
    send_file($docbase, $requestpath);
    exit;
});

$router->post('/__fun/(.*)', function ($requestpath) use ($router, $ds) {
    $docbase = $_SERVER['DOCUMENT_ROOT'].'/../endpoints';
    include($docbase."/".$requestpath);
    exit;
});

#dbg("++ image path", $config['assets']['path']);

$router->get($config['assets']['path'].'/'.'(.*\.\w{1,5})', function ($requestpath) use ($router, $ds, $config) {
    $docbase = $_SERVER['DOCUMENT_ROOT'].'/../cache';
    #dbg("++ image path base", $docbase, $requestpath);
    send_file($docbase, $requestpath);
    exit;
});

$router->get('(.*\.\w{1,5})', function ($requestpath) use ($router, $ds) {
    $docbase = $_SERVER['DOCUMENT_ROOT'];
    send_file($docbase, $requestpath);
    exit;
});

$router->get('(.*)?', function ($requestpath) use ($router, $ds, $config, $pages, $src, $template_helper) {
    send_nocache();
    $requestpath = '/'.$requestpath;
    // startseite?
    if ($requestpath == '/' || $requestpath == '') {
        $requestpath = '/index';
    }
    #dbg("dev: req", $requestpath);
    [$obj_id, $name] = $ds->get_by_path($requestpath);

    if ($obj_id) {
        $obj = $ds->get($obj_id);

        // $template = $templates[$obj['_type']][$name]['template'];
        $template = template_name($config['templates'], $obj['_type'], $name);
        #dbg('template', $template, $obj);
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
        #dbg('page...', $pagename, $pagenr, $requestpath);
        $obj_id = array_search($pagename, $pages);
        #$pagination_query = check_pagination($pagename, $src);
        $pp = preprocess($pagename, $src);
        #dbg('page query', $pp);
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
    $debug = true;
    if ($debug) {
        $inspector = include_to_buffer(__DIR__.'/assets/debug.php');
        $inspector_css = '<link rel="stylesheet" href="/__sf/inspector-json.css">';
        $content = str_replace('</head>', $inspector_css.'</head>', $content);
        $content = str_replace('</body>', $inspector.'</body>', $content);
    }
    print $content;
    exit;
});

$router->set404(function () {
    // dbg('-- 404');
    e404();
});

$router->run();
exit;


if ($hr) {
    require_once __DIR__ . '/hot-reload/HotReloader.php';
    $htrldr = new HotReloader\HotReloader('//localhost:1199/phrwatcher.php');
    $js = $htrldr->init();
    $content = str_replace('</html>', $js . '</html>', $content);
}
