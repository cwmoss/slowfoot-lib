<?php
require_once 'lolql.php';
require_once 'store.php';
require_once 'store_memory.php';
require_once 'store_sqlite.php';
require_once 'JsConverter.php';
require_once 'template.php';
require_once 'hook.php';


use slowfoot\store;
use slowfoot\store_memory;
use slowfoot\store_sqlite;
use Ovidigital\JsObjectToJson\JsConverter;
use function lolql\parse;
use function lolql\query as lquery;

/*

https://github.com/paquettg/php-html-parser
https://github.com/Masterminds/html5-php

if (! function_exists(__NAMESPACE__ . '\greetings'))

*/

function load_config($dir)
{
    $conf = include $dir . '/config.php';
    $tpls = [];
    foreach ($conf['templates'] as $name => $t) {
        $tpls[$name] = normalize_template_config($name, $t);
    }
    $conf['templates'] = $tpls;
    #$conf['base'] = $dir;
    $conf['base'] = '/'.get_absolute_path($dir);
    $conf['src'] = $conf['base'].'/src';
    $conf['dist'] = $conf['base'].'/dist';
    $conf['assets'] = normalize_assets_config($conf);
    $conf['store'] = normalize_store_config($conf);
    $conf['plugins'] = normalize_plugins_config($conf);
    $conf['build'] = normalize_build_config($conf);
    return $conf;
}

// TODO:
//  require in global context?
//  do wee need a plugin init /w pconf?
//  plugin via composer?
//  raise error?
function normalize_plugins_config($conf)
{
    $plugins = $conf['plugins']??[];
    $norm = [];
    foreach ($plugins as $k => $pconf) {
        $name = is_string($pconf)?$pconf:(!is_numeric($k)?$k:null);
        if (!$name) {
            continue;
        }
        $pfile = $name.'.php';
        if (file_exists($conf['src'].'/plugins/'.$pfile)) {
            $fullname = $conf['src'].'/plugins/'.$pfile;
        } else {
            if (file_exists(__DIR__.'/plugins/'.$pfile)) {
                $fullname = __DIR__.'/plugins/'.$pfile;
            } else {
                continue;
            }
        }
        $norm[$name] = [
            'filename' => $pfile,
            'fullpath' => $fullname,
            'conf' => is_array($pconf)?$pconf:[]
        ];
        require_once($fullname);
    }
    return $norm;
}

function normalize_template_config($name, $config)
{
    if (!is_array($config) || is_assoc($config)) {
        $config = [$config];
    }
    $tpl = [];
    foreach ($config as $t) {
        if (!is_array($t)) {
            $t = ['path' => $t];
        }
        if (is_string($t['path'])) {
            $t['path'] = make_path_fn($t['path']);
        }
        $subname = $t['name'] ?? '_';
        $tpl[$subname] = array_merge(['type' => $name, 'template' => $name, 'name' => $subname], $t);
    }
    return $tpl;
}

function normalize_store_config($conf)
{
    $def = ['adapter'=>'memory'];
    $store = $conf['store']??null;
    if (!$store) {
        return $def;
    }
    if (is_string($store)) {
        $store=['adapter'=>$store];
    }
    $store['base'] = $conf['base'].'/var';
    return $store;
}
function normalize_assets_config($conf)
{
    $assets = $conf['assets'] ?: [];
    $default = [
        'base' => $conf['base'],
        'path' => '/images',
        'src' => '',
        'dest' => 'var/rendered-images',
        'profiles' => [],
        'map' => function ($img) {
            return hook::invoke_filter('assets_map', $img);
        }
    ];
    $assets = array_merge($default, $assets);
    return $assets;
}
function normalize_build_config($conf){
    $build = $conf['build']??['dist'=>'dist'];
    if(is_string($conf['build'])){
        $build = ['dist'=>$conf['build']];
    }
    #if($build['dist'][0]!='/'){
        $build['dist'] = $conf['base'].'/'.$build['dist'];
    #}
    return $build;
}
function make_path_fn($pattern)
{
    $replacements = [];
    if (preg_match_all('!:([^/:]+)!', $pattern, $mat, PREG_SET_ORDER)) {
        $replacements = $mat;
    }
    $replacements = array_map(fn ($r) => [$r[0], explode('.', $r[1])], $replacements);
    // print_r($replacements);
    // exit;
    return function ($item) use ($pattern, $replacements) {
        $path = $pattern;
        // $item[$r[1]]
        $replacements = array_map(fn ($r) => [$r[0], url_safe(resolve_dot_value($r[1], $item))], $replacements);
        $path = str_replace(
            array_column($replacements, 0),
            array_column($replacements, 1),
            $path
        );
        return $path;
    };
}
function resolve_dot_value($keys, $data)
{
    if (!$data) {
        return null;
    }
    $current = array_shift($keys);

    // nested?
    if ($keys) {
        return resolve_dot_value($keys, $data[$current]);
    }

    if (!is_assoc($data)) {
        return array_column($data, $current);
    } else {
        return $data[$current];
    }
}
function url_safe($path)
{
    // TODO
    // https://gist.github.com/jaywilliams/119517
    $path = str_replace([' '], ['-'], $path);
    $path = strtolower($path);
    return $path;
}

function xload_data($sources, $hooks)
{
    $db = [];
    $loaded = $rejected = [];
    foreach (file($dataset) as $line) {
        $row = json_decode($line, true);
        $otype = $row['_type'];
        if ($hooks['on_load']) {
            $row = $hooks['on_load']($row, $db);
        }
        if (!$row) {
            $rejected[$otype]++;
        } else {
            $db[$row['_id']] = $row;
            $loaded[$row['_type']]++;
        }
    }
    $db['_info'] = ['loaded' => $loaded, 'rejected' => $rejected];
    return $db;
}

function get_store($config)
{
    if (strpos($config['store']['adapter'], 'sqlite') === 0) {
        $db = new store_sqlite($config['store']);
    } else {
        $db = new store_memory();
    }
    return new store($db, $config['templates']);
}
function load_data($sources, $hooks, $config)
{
    $db = get_store($config);
    $db_store = get_class($db->db);
    
    # TODO fetch or not
    if ($db->has_data_on_create()) {
        shell_info("store {$db_store} using old data", true);
        return $db;
    }
    
    shell_info("fetching data {$db_store}", true);

    foreach ($sources as $name => $opts) {
        if (!is_array($opts)) {
            $opts = ['file' => $opts];
        }
        $def = ['loader'=>$name, 'type'=>$name, 'name'=>$name];
        $opts = array_merge($def, $opts);
       
        $fun = 'load_' . $opts['loader'];
        
        shell_info("fetching $name");
    
        foreach ($fun($opts, $config, $db) as $row) {
            if (!isset($row['_type']) || !$row['_type']) {
                #print_r($row);
                $row['_type'] = $opts['type'];
            }
            $otype = $row['_type'];
            $row['_src'] = $name;
            if ($hooks['on_load']) {
                $row = $hooks['on_load']($row, $db);
            }
            if (!$row) {
                $db->rejected($otype);
            } else {
                if (!$row['_id']) {
                    $row['_id'] = $row['id'];
                }
                // $row['_id'] = str_replace('/', '-', $row['_id']);
                $db->add($row['_id'], $row);
            }
        }
        shell_info();
    }
    return $db;
}

function load_dataset($opts, $config, $db)
{
    $file = $config['base'] . '/' . $opts['file'];
    foreach (file($file) as $row) {
        yield json_decode($row, true);
    }
    return;
}

function load_json($opts, $config, $db)
{
    $file = $config['base'] . '/' . $opts['file'];
    $rows = json_decode(file_get_contents($file), true);
    if (is_assoc($rows)) {
        $rows = [$rows];
    }
    foreach ($rows as $row) {
        yield $row;
    }
    return;
}



function load_csv($opts, $config, $db)
{
    $file = $config['base'] . '/' . $opts['file'];
    $opts = array_merge(['sep' => ',', 'enc' => '"'], $opts);
    $header = null;
    foreach (file($file) as $row) {
        if (is_null($header)) {
            $header = str_getcsv($row, $opts['sep'], $opts['enc']);
            print_r($header);
            continue;
        }
        $data = str_getcsv($row, $opts['sep'], $opts['enc']);

        if ($opts['json']) {
            $data = array_map(fn ($val) => json_decode($val, true), $data);
        }
        if ($opts['jsol']) {
            $data = array_map(function ($val) {
                if ($val[0] == '[' || $val[0] == '{') {
                    return json_decode(JsConverter::convertToJson($val), true);
                } else {
                    return $val;
                }
            }, $data);
        }
        //print_r($data);
        //return [];
        yield array_combine($header, $data);
    }
}



function query_type($ds, $type)
{
    return $ds->query_type($type);
}

function queryxxx($ds, $filter)
{
    if (is_string($filter)) {
        $filter = ['_type' => $filter];
    }
    $rs = array_filter($ds->data, function ($row) use ($filter) {
        return evaluate($filter, $row);
    });

    if ($filter['_type'] == 'artist') {
        $skey = 'firstname';
    }

    $sfn = \lolql\build_order_fun('firstname, familyname');

    if ($sfn) {
        dbg('... sorting..');
        usort($rs, $sfn);
        // usort($rs, build_sorter($skey));
    }
    return $rs;
}

function build_sorter($key)
{
    return function ($a, $b) use ($key) {
        return strnatcasecmp($a[$key], $b[$key]);
    };
}
function pagination($info, $page)
{
    return array_merge($info, [
        'page' => $page,
        'prev' => ($page - 1) ?: null,
        'next' => (($page + 1) <= $info['totalpages']) ?($page + 1) : null
    ]);
}

function chunked_paginate($ds, $rule)
{
    $limit = $rule['limit'] ?? 20;
    // $all = lquery($ds->data, $rule);
    list($all, $total) = $ds->query($rule, $limit);

    // $total = count($all);
    $totalpages = ceil($total / $limit);
    foreach (range(1, $totalpages) as $page) {
        $offset = ($page - 1) * $limit;
        $res = array_slice($all, $offset, $limit);
        $info = ['total' => $total, 'totalpages' => $totalpages, 'page' => $page,
            'limit' => $limit, 'real' => count($res),
            'prev' => ($page - 1) ?: null,
            'next' => (($page + 1) <= $totalpages) ?: null
        ];
        yield ['items' => $res, 'info' => $info];
    }
}



function query_page($ds, $rule, $page = 1)
{
    $limit = $rule['limit'] ?? 20;
    $page = $page ?? 1;

    $all = lquery($ds->data, $rule);

    $total = count($all);
    $totalpages = ceil($total / $limit);
    $offset = ($page - 1) * $limit;
    dbg('paginate', $page, $offset);
    $res = array_slice($all, $offset, $limit);
    $info = ['total' => $total, 'totalpages' => $totalpages, 'page' => $page,
        'limit' => $limit, 'real' => count($res),
        'prev' => ($page - 1) ?: null,
        'next' => (($page + 1) <= $totalpages) ?: null
    ];
    return ['items' => $res, 'info' => $info];
}

function evaluate($cond, $data)
{
    foreach ($cond as $k => $v) {
        $ok = evaluate_single($k, $v, $data);
        if (!$ok) {
            return false;
        }
    }
    return true;
}
function evaluate_single($key, $value, $data)
{
    $nested = explode('.', $key);
    $current = array_shift($nested);

    if ($nested) {
        return evaluate_single(join('.', $nested), $value, $data[$current]);
    }

    if (!$data) {
        return false;
    }

    if (!is_assoc($data)) {
        return array_find($data, $value, $current);
    } else {
        return $data[$current] == $value;
    }
}

function array_find($haystack, $needle, $prop)
{
    foreach ($haystack as $val) {
        if ($val[$prop] == $needle) {
            return true;
        }
    }
    return false;
}



function slow_query($q, $vars = [])
{
    $q = str_replace(array_map(function ($k) {
        return '$' . $k;
    }, array_keys($vars)), array_values($vars), $q);
    //print "-- Q: $q";
    return query_cmd($q);
}

function slow_query_cmd($q)
{
    $dataset = 'dataset-mumok.ndjson';

    $cmd = sprintf("cat %s | groq -i ndjson -o json '%s'", $dataset, $q);
    $res = `$cmd`;
    return json_decode($res, true);
}

function template_name($tconfig, $type, $name)
{
    return $tconfig[$type][$name]['template'];
}

function path_asset($asset, $cachebust = false)
{
    return PATH_PREFIX . $asset . cachebuster($cachebust);
}

function cachebuster($cachebust=false)
{
    if ($cachebust===false) {
        return "";
    }
    if ($cachebust===true) {
        return "?".time();
    }
    return "?rev=".$cachebust;
}

function path_page($page)
{
    return PATH_PREFIX . $page;
}

function prefix_endpoint($ep)
{
    return PATH_PREFIX .'/__fun'.$ep;
}

function write($content, $path, $pagenr, $base)
{
    if (!$content) {
        return;
    }
    if ($pagenr && $pagenr != 1) {
        $path .= '/'.$pagenr;
    }
    if ($path != '/404') {
        $path .= '/index';
    }
    $path .= '.html';

    $file = $base . '/' . $path;
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, $content);
}

function slugify($gen, $text, $remove_stop_words = false)
{
    if ($remove_stop_words) {
        $text = remove_stop_words($text);
    }

    $text = $gen->generate($text);
    $text = substr($text, 0, 60);
    return $text;
}
function remove_stop_words($text)
{
    $stops = ['a', 'the', 'and', 'ein', 'eine', 'der', 'die', 'das', 'und'];
    return preg_replace('/\b(' . join('|', $stops) . ')\b/', '', $text);
}

function layout($name = null)
{
    return \slowfoot\template\layout($name);
}

function get_absolute_path_from_base($path, $current, $base)
{
    if (substr($path, 0, 2)=='~/') {
        $path = $base.ltrim($path, '~');
        dbg("+++ path ~ +++", $path);
        $remove_base = true;
    } else {
        $path = $current.'/'.$path;
        $remove_base = false;
    }
    
    $path = get_absolute_path($path);
    dbg("+++ path +++", $path);
    if ($remove_base) {
        $path = str_replace($base.'/', '', '/'.$path);
        dbg("+++ path +++ ++++ ", $path, $base);
    }
    return $path;
}
function get_absolute_path($path)
{
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
        if ('.' == $part) {
            continue;
        }
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
}
