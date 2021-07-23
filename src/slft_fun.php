<?php
require_once 'lolql.php';
require_once 'store.php';
require_once 'JsConverter.php';

use slowfoot\store;
use Ovidigital\JsObjectToJson\JsConverter;
use function lolql\parse;
use function lolql\query as lquery;

function load_config($dir) {
    $conf = include $dir . '/config.php';
    $tpls = [];
    foreach ($conf['templates'] as $name => $t) {
        $tpls[$name] = normalize_template_config($name, $t);
    }
    $conf['templates'] = $tpls;
    $conf['base'] = $dir;
    $conf['assets'] = normalize_assets_config($conf);
    return $conf;
}

function normalize_template_config($name, $config) {
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
        $subname = $t['name'] ?: '_';
        $tpl[$subname] = array_merge(['type' => $name, 'template' => $name, 'name' => $subname], $t);
    }
    return $tpl;
}

function normalize_assets_config($conf) {
    $assets = $conf['assets'] ?: [];
    $default = ['base' => $conf['base'], 'src' => 'images', 'dest' => 'cache', 'profiles' => []];
    $assets = array_merge($default, $assets);
    return $assets;
}

function make_path_fn($pattern) {
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
function resolve_dot_value($keys, $data) {
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
function url_safe($path) {
    // TODO
    // https://gist.github.com/jaywilliams/119517
    $path = str_replace([' '], ['-'], $path);
    $path = strtolower($path);
    return $path;
}

function xload_data($sources, $hooks) {
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

function load_data($sources, $hooks, $config) {
    $db = new store($config['templates']);

    foreach ($sources as $name => $opts) {
        if (!is_array($opts)) {
            $opts = ['file' => $opts];
        }
        if (!$opts['loader']) {
            $opts['loader'] = $name;
        }
        if (!$opts['type']) {
            $opts['type'] = $name;
        }
        $opts['name'] = $name;
        $fun = 'load_' . $opts['loader'];
        dbg('loading from source', $fun, $opts);

        foreach ($fun($opts, $config) as $row) {
            $otype = $row['_type'];
            $row['_src'] = $name;
            if ($hooks['on_load']) {
                $row = $hooks['on_load']($row, $db);
            }
            if (!$row) {
                $db->rejected($otype);
            } else {
                if (!$row['_type']) {
                    $row['_type'] = $opts['type'];
                }
                if (!$row['_id']) {
                    $row['_id'] = $row['id'];
                }
                $db->add($row['_id'], $row);
            }
        }
    }
    return $db;
}

function load_dataset($opts, $config) {
    $file = $config['base'] . '/' . $opts['file'];
    foreach (file($file) as $row) {
        yield json_decode($row, true);
    }
    return;
}

function load_json($opts, $config) {
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

function load_csv($opts, $config) {
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
function query($ds, $filter) {
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

function build_sorter($key) {
    return function ($a, $b) use ($key) {
        return strnatcasecmp($a[$key], $b[$key]);
    };
}

function chunked_paginate($ds, $rule) {
    $limit = $rule['limit'] ?? 20;
    $all = lquery($ds->data, $rule);
    $total = count($all);
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

function query_page($ds, $rule, $page = 1) {
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

function evaluate($cond, $data) {
    foreach ($cond as $k => $v) {
        $ok = evaluate_single($k, $v, $data);
        if (!$ok) {
            return false;
        }
    }
    return true;
}
function evaluate_single($key, $value, $data) {
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

function array_find($haystack, $needle, $prop) {
    foreach ($haystack as $val) {
        if ($val[$prop] == $needle) {
            return true;
        }
    }
    return false;
}

function is_assoc(array $arr) {
    if ([] === $arr) {
        return false;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function slow_query($q, $vars = []) {
    $q = str_replace(array_map(function ($k) {
        return '$' . $k;
    }, array_keys($vars)), array_values($vars), $q);
    //print "-- Q: $q";
    return query_cmd($q);
}

function slow_query_cmd($q) {
    $dataset = 'dataset-mumok.ndjson';

    $cmd = sprintf("cat %s | groq -i ndjson -o json '%s'", $dataset, $q);
    $res = `$cmd`;
    return json_decode($res, true);
}

function template_name($tconfig, $type, $name) {
    return $tconfig[$type][$name]['template'];
}

function path_asset($asset, $cachebust = false) {
    return PATH_PREFIX . $asset . ($cachebust ? '?' . mktime() : '');
}

function path_page($page) {
    return PATH_PREFIX . $page;
}

function process_template($id, $path) {
    global $templates;
    layout('-');
    $data = query('*[_id=="$id"][0]', ['id' => $id]);
    process_template_data($data, $path);
}

function partial($base, $template, $data, $helper) {
    extract($data);
    extract($helper);
    ob_start();
    include $base . '/partials/' . $template . '.php';
    $content = ob_get_clean();
    return $content;
}

function remove_tags($content) {
    //dbg('remove...');
    $content = preg_replace('!<page-query>.*?</page-query>!ism', '', $content);
    return $content;
}

function template($_template, $data, $helper, $_base) {
    extract($data);
    extract($helper);
    ob_start();
    include $_base . '/templates/' . $_template . '.php';
    $content = ob_get_clean();
    $layout = layout();
    if ($layout) {
        ob_start();
        include $_base . '/layouts/' . $layout . '.php';
        $content = ob_get_clean();
    }
    return $content;
}

function page($_template, $data, $helper, $_base) {
    extract($data);
    extract($helper);
    ob_start();
    include $_base . '/pages/' . $_template . '.php';

    $content = ob_get_clean();
    $layout = layout();
    if ($layout) {
        ob_start();
        include $_base . '/layouts/' . $layout . '.php';
        $content = ob_get_clean();
    }
    return $content;
}

function check_pagination($_template, $_base) {
    $content = file_get_contents($_base . '/pages/' . $_template . '.php');
    $prule = preg_match('!<page-query>(.*?)</page-query>!ism', $content, $mat);
    if ($prule) {
        return parse($mat[1]);
    } else {
        return false;
    }
}

function page_paginated($_template, $data, $_base) {
    extract($data);
    ob_start();
    include $_base . '/pages/' . $_template . '.php';
    $content = ob_get_clean();
    $layout = layout();
    if ($layout) {
        ob_start();
        include $_base . '/layouts/' . $layout . '.php';
        $content = ob_get_clean();
    }
    return $content;
}

function paginate($how = null) {
    static $rules;
    if (!is_null($how)) {
        // reset
        if ($how == '-') {
            $rules = null;
        }
        $rules = $how;
    }
    return $rules;
}

function process_template_data($data, $path) {
    global $templates;
    $file_template = $templates[$data['_type']]['template'];
    extract($data);
    ob_start();
    include $file_template . '.php';
    $content = ob_get_clean();
    $layout = layout();
    if ($layout) {
        ob_start();
        include 'templates/__' . $layout . '.php';
        $content = ob_get_clean();
    }
    write($content, $path);
}

function layout($name = null) {
    static $layout = null;
    if (!is_null($name)) {
        // reset layout name
        if ($name == '-') {
            $layout = null;
        }
        $layout = $name;
    }
    return $layout;
}

function write($content, $path, $base) {
    $file = $base . '/' . $path . '/index.html';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, $content);
}

function slugify($gen, $text, $remove_stop_words = false) {
    if ($remove_stop_words) {
        $text = remove_stop_words($text);
    }

    $text = $gen->generate($text);
    $text = substr($text, 0, 60);
    return $text;
}
function remove_stop_words($text) {
    $stops = ['a', 'the', 'and', 'ein', 'eine', 'der', 'die', 'das', 'und'];
    return preg_replace('/\b(' . join('|', $stops) . ')\b/', '', $text);
}
