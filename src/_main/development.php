<?php
define('SLOWFOOT_START', microtime(true));

/*
TODO: vendor-dir vs project-dir
*/
$project_dir = $_SERVER['DOCUMENT_ROOT'] . '/../';
define('SLOWFOOT_BASE', $project_dir);

//require $project_dir . '/vendor/autoload.php';

require __DIR__ . '/../_boot.php';

use slowfoot\util\console;
use slowfoot\util\server;

use function slowfoot\template\page;
use function slowfoot\template\template;
use function slowfoot\template\remove_tags;
use function slowfoot\template\preprocess;

use Bramus\Router\Router;
use slowfoot\context;
use slowfoot\pagebuilder;

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

$router = new Router();

$hr = false;
$debug = true;

$router->mount('/__api', function () use ($router, $ds, $config, $src, $template_helper) {
    #dbg('server', $_SERVER);
    server::send_cors();
    $router->get('/index', function () use ($router, $ds) {

        #print "hallo";

        //$rows = $db->run('SELECT * FROM docs LIMIT 20');
        // $rows = $db->run('SELECT _type, count(*) AS total FROM docs GROUP BY _type');

        server::resp($ds->info());
    });

    $router->get('/type/([-\w.]+)(/\d+)?', function ($type, $page = 1) use ($router, $ds) {
        dbg("[api] type", $type);
        #print "hallo";
        if ($type == '__paths') {
            $rows = $ds->db->db->safeQuery('SELECT * FROM paths LIMIT ? OFFSET ?', [20, 0]);
        } else {
            $rows = $ds->query_type($type);
        }
        //$rows = $db->run('SELECT * FROM docs LIMIT 20');
        //$rows = $db->q('SELECT _id, body FROM docs WHERE _type = ? LIMIT 20', $type);

        server::resp(['rows' => $rows]);
    });

    $router->get('/id', function () use ($router, $ds) {
        $id = $_GET['id'];
        //$row = $db->row('SELECT _id, _type, body FROM docs WHERE _id = ? ', $id);
        $row = $ds->get($id);
        server::resp($row);
    });

    $router->get('/fts', function () use ($router, $ds) {
        $q = $_GET['q'];
        $rows = $ds->q("SELECT _id, snippet(docs_fts,1, '<b>', '</b>', '[...]', 30) body FROM docs_fts WHERE docs_fts = ? ", $q);
        server::resp($rows);
    });


    $router->get('/preview/(.*)', function ($id_type) use ($router, $ds, $config, $src, $template_helper) {
        list($id, $type) = explode('/', $id_type);
        dbg("[api/preview]", $id_type);

        $preview_obj = load_preview_object($id, $type, $config);

        #$template = $templates[$obj['_type']]['_']['template'];
        #$template = template_name($config['templates'], $obj['_type'], $name);
        #dbg('[api/preview] template', $preview_obj);
        $context = [
            'mode' => 'dev',
            'src' => $src,
            'path' => $id_type,
            'site_name' => $config['site_name'] ?? '',
            'site_description' => $config['site_description'] ?? '',
            'site_url' => $config['site_url'] ?? '',

        ];

        $content = template($preview_obj['template'], ['page' => $preview_obj['data']], $template_helper, template_context('template', $context, $preview_obj, $ds, $config));

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Content-Type: text/html');

        print $content;
    });
});

$router->mount('/__ui', function () use ($router, $ds) {
    $router->get('/', function () use ($router, $ds) {
        $uibase = __DIR__ . '/../../ui/build';
        #dbg("+++ ui index ++++", $uibase);
        server::send_file($uibase, 'index.html');
        exit;
    });

    $router->get('(.*)?', function ($file) use ($router, $ds) {
        $uibase = __DIR__ . '/../../ui/build';
        $uifile = $uibase . '/' . $file;
        dbg("__ui file00", $file, $uifile);

        if (file_exists($uifile)) {
            send_file($uibase, $file);
            exit;
        } else {
            send_file($uibase, 'index.html');
            exit;
        }
        dbg("__ui file", $file, $uifile);
        server::resp(['ok' => $file]);
    });
});

$router->get('/__sf/(.*)', function ($requestpath) use ($router, $ds) {
    $docbase = __DIR__ . '/../../resources';
    server::send_file($docbase, $requestpath);
    exit;
});

$router->post('/__fun/(.*)', function ($requestpath) use ($router, $ds) {
    $docbase = $_SERVER['DOCUMENT_ROOT'] . '/../endpoints';
    include($docbase . "/" . $requestpath);
    exit;
});

#dbg("++ image path", $config['assets']['path']);

$router->get($config->assets['path'] . '/' . '(.*\.\w{1,5})', function ($requestpath) use ($router, $ds, $config) {
    dbg('[dev] asssets', $requestpath);
    $docbase = $_SERVER['DOCUMENT_ROOT'] . '/../var/rendered-images';
    #dbg("++ image path base", $docbase, $requestpath);
    server::send_file($docbase, $requestpath);
    exit;
});

$router->get('(.*\.\w{1,5})', function ($requestpath) use ($router, $ds) {
    $docbase = $_SERVER['DOCUMENT_ROOT'];
    dbg('[dev] some.doc', $requestpath);
    server::send_file($docbase, $requestpath);
    exit;
});

$router->get('(.*)?', function ($requestpath) use ($router, $ds, $config, $pages, $src, $template_helper) {
    dbg('[dev] page/template', $requestpath);
    server::send_nocache();
    $requestpath = '/' . $requestpath;
    // startseite?
    if ($requestpath == '/' || $requestpath == '') {
        $requestpath = '/index';
    }
    $builder = new pagebuilder($config, $ds, $template_helper);
    #dbg("dev: req", $requestpath);
    [$obj_id, $name] = $ds->get_by_path($requestpath);

    $context = new context(
        mode: 'dev',
        src: $src,
        path: $requestpath,
        config: $config
    );

    if ($obj_id) {
        $content = $builder->make_template($obj_id, $name, $context);
    } else {
        list($dummy, $pagename, $pagenr) = explode('/', $requestpath) + [2 => 0];
        $pagename = '/' . $pagename;
        if ($pagename == '') {
            //    $pagename='/index';
        }

        dbg('page...', $pagename, $pagenr, $requestpath);
        $obj_id = array_search($pagename, $pages);
        $content = $builder->make_page($pagename, $pagenr, $requestpath, $context);
    }
    $debug = true;
    if ($debug) {
        $inspector = include_to_buffer(__DIR__ . '/../../resources/debug.php');
        $inspector_css = '<link rel="stylesheet" href="/__sf/inspector-json.css">';
        $content = str_replace('</head>', $inspector_css . '</head>', $content);
        $content = str_replace('</body>', $inspector . '</body>', $content);
    }
    print $content;
    exit;
});

$router->set404(function () {
    // dbg('-- 404');
    server::e404();
});

$router->run();
exit;


if ($hr) {
    require_once __DIR__ . '/hot-reload/HotReloader.php';
    $htrldr = new HotReloader\HotReloader('//localhost:1199/phrwatcher.php');
    $js = $htrldr->init();
    $content = str_replace('</html>', $js . '</html>', $content);
}
