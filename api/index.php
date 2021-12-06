<?php
/*

php -S localhost:3039 -t ui/ api/index.php 


*** php -S localhost:3039 -t api/ api/index.php 

*/

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/utils_server.php';

#print "hallo";

$dbf = __DIR__.'/../dataset-mumok.ndjson.db';



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

});

$router->set404(function () {
    // dbg('-- 404');
    e404();
});

$router->run();
