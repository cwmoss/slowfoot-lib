<?php
// require_once __DIR__ . '/../vendor/autoload.php';
if (!class_exists("\Composer\Autoload\ClassLoader")) {
    require_once 'vendor/autoload.php';
}

if (!defined('SLOWFOOT_BASE')) {
//    define('SLOWFOOT_BASE', __DIR__ . '/../../../../');
}
if (!defined('SLOWFOOT_PREVIEW')) {
    define('SLOWFOOT_PREVIEW', false);
}
$base = SLOWFOOT_BASE;
if (file_exists("$base/.env")) {
    //print "env: $base/.env";
    Dotenv\Dotenv::createImmutable("$base")->load();
}

$src = $base . '/src';
$dist = $base . '/dist/';

require_once 'util.php';
require_once 'slft_fun.php';

$config = load_config($base);
//print_r($_ENV);
//print_r($config); exit;

if (!defined('PATH_PREFIX')) {
    define('PATH_PREFIX', $config['path_prefix']);
}

if (!SLOWFOOT_PREVIEW) {
    require_once 'routing.php';
}

require_once 'template_helper.php';

//print_r($config);

//var_dump($hooks);
$ds = load_data($config['sources'], $config['hooks'], $config);

$templates = $config['templates'];
/*
$paths = array_reduce($templates, function ($res, $item) use ($ds) {
    return array_merge($res, array_map(function ($obj) use ($item) {
        //print_r($obj);
        return [$obj['_id'], $item['path']($obj)];
    }, query($ds, ['_type' => $item['type']])));
}, []);

$paths = array_combine(array_column($paths, 0), array_column($paths, 1));
*/
//print_r($paths);
//print_r($paths_rev);
//exit;
//print $requestpath;

//print_r($ds['_info']);

$template_helper = load_template_helper($ds, $src);

$pages = glob($src . '/pages/*.php');
$pages = array_map(function ($p) {
    return '/' . basename($p, '.php');
}, $pages);
