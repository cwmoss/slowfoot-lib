<?php
/*

php -S localhost:3039 -t ui/ api/index.php 


*** php -S 127.0.0.1:3039 -t api/ api/index.php 

https://epilys.github.io/bibliothecula/notekeeping.html

https://24ways.org/2018/fast-autocomplete-search-for-your-website/

https://docs.datasette.io/en/stable/full_text_search.html

*/

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/utils_server.php';

#print "hallo";

$dbf = __DIR__.'/../dataset-mumok.ndjson.db';
$dbf = __DIR__.'/../hkw.db';

ini_set("precision", 16);
define('START_TIME', microtime(true));

error_reporting(E_ALL & ~E_NOTICE);

$router = new \Bramus\Router\Router();

if (isset($BASE_URL)) {
 //   $router->setBasePath($BASE_URL);
}
$router->mount('/__api', function () use ($router, $dbf) {

    $db = \ParagonIE\EasyDB\Factory::fromArray([
        "sqlite:$dbf"
    ]);

    dbg('server', $_SERVER);
    send_cors();
    $router->get('/index', function () use ($router, $db) {

        #print "hallo";

        //$rows = $db->run('SELECT * FROM docs LIMIT 20');
        $rows = $db->run('SELECT _type, count(*) AS total FROM docs GROUP BY _type');
        resp($rows);
    });

    $router->get('/type/([-\w.]+)(/\d+)?', function ($type, $page=1) use ($router, $db) {

        #print "hallo";

        //$rows = $db->run('SELECT * FROM docs LIMIT 20');
        $rows = $db->q('SELECT _id, body FROM docs WHERE _type = ? LIMIT 20', $type);
        resp(['rows'=>$rows]);
    });

    $router->get('/id', function () use ($router, $db) {
        $id = $_GET['id'];
        $row = $db->row('SELECT _id, _type, body FROM docs WHERE _id = ? ', $id);
        resp($row);
    });

    $router->get('/fts', function () use ($router, $db) {
        $q = $_GET['q'];
        $rows = $db->q("SELECT _id, snippet(docs_fts,1, '<b>', '</b>', '[...]', 30) body FROM docs_fts WHERE docs_fts = ? ", $q);
        resp($rows);
    });
});

$router->set404(function () {
    // dbg('-- 404');
    e404();
});

$router->run();
