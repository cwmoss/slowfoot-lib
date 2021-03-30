<?php
require_once __DIR__ . '/../vendor/autoload.php';

define('PATH_PREFIX', '');
if (!defined('SLOWFOOT_BASE')) {
    define('SLOWFOOT_BASE', __DIR__ . '/../');
}
$base = SLOWFOOT_BASE;
$src = $base . '/src';
$dist = $base . '/dist/';

require_once 'util.php';
require_once 'routing.php';

require_once 'slft_fun.php';
require_once 'template_helper.php';

require_once $src . '/helper.php';

//$dataset = 'wp.json';
$dataset = 'dataset-mumok.ndjson';

$config = load_config($src);
//print_r($config);
[$templates, $hooks] = $config;

//var_dump($hooks);
$ds = load_data($dataset, $hooks);

$paths = array_reduce($templates, function ($res, $item) use ($ds) {
    return array_merge($res, array_map(function ($obj) use ($item) {
        //print_r($obj);
        return [$obj['_id'], $item['path']($obj)];
    }, query($ds, ['_type' => $item['type']])));
}, []);

$paths = array_combine(array_column($paths, 0), array_column($paths, 1));
//print_r($paths);
//print $requestpath;

//print_r($ds['_info']);

$template_helper = load_template_helper($ds, $paths, $src);

$pages = glob($src . '/pages/*.php');
$pages = array_map(function ($p) {
    return '/' . basename($p, '.php');
}, $pages);
