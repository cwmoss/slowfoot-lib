<?php
// require_once __DIR__ . '/../vendor/autoload.php';
#if (!class_exists("\Composer\Autoload\ClassLoader")) {
    //require_once __DIR__.'/../vendor/autoload.php';
//   require_once __DIR__.'/../vendor/autoload.php';
#}

if (!defined('SLOWFOOT_BASE')) {
    // via php cli webserver
#    print_r($_SERVER);
#    print_r($_SERVER);
    // different project path without vendor/ dir?
    // TODO: better ideas
    $internal = str_replace('vendor/cwmoss/slowfoot-lib/docs/src', '', $_SERVER['DOCUMENT_ROOT']);
    if ($internal == $_SERVER['DOCUMENT_ROOT']) {
        unset($internal);
    }
    define('SLOWFOOT_BASE', $_SERVER['DOCUMENT_ROOT'] . '/../');
} else {
}

$autoload = $internal??SLOWFOOT_BASE;

require_once $autoload.'/vendor/autoload.php';

if (!defined('SLOWFOOT_PREVIEW')) {
    define('SLOWFOOT_PREVIEW', false);
}
if (!defined('SLOWFOOT_WEBDEPLOY')) {
    define('SLOWFOOT_WEBDEPLOY', false);
}
$base = SLOWFOOT_BASE;
// set a different directory as project base
// independent from ./vendor dir
if (isset($PDIR) && $PDIR) {
    $base = $PDIR;
}
if (file_exists("$base/.env")) {
    //print "env: $base/.env";
    Dotenv\Dotenv::createImmutable("$base")->load();
}

$_ENV = array_merge(getenv(), $_ENV);

$src = $base . '/src';
$dist = $base . '/dist/';

#require_once 'util.php';

#require_once 'image.php';
#require_once 'slft_fun.php';

$config = load_config($base);
//print_r($_ENV);
//print_r($config); exit;

if (!defined('PATH_PREFIX')) {
    if (PHP_SAPI == 'cli-server') {
        define('PATH_PREFIX', "");
    } else {
        define('PATH_PREFIX', $config['path_prefix']);
    }
}

if (!(SLOWFOOT_PREVIEW || SLOWFOOT_WEBDEPLOY)) {
#    require_once 'routing.php';
}

require_once 'template_helper.php';

//print_r($config);

# TODO: im store inbauen
if (isset($FETCH) && $FETCH) {
    $dbfile = SLOWFOOT_BASE.'/var/slowfoot.db';
    `rm $dbfile`;
}
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

$template_helper = load_template_helper($ds, $src, $config);

$pages = glob($src . '/pages/*.php');
$pages = array_map(function ($p) {
    return '/' . basename($p, '.php');
}, $pages);

dbg('[dataset] info', $ds->info);
