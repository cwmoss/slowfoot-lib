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
use slowfoot\hook;
use Ovidigital\JsObjectToJson\JsConverter;
use function lolql\parse;
use function lolql\query as lquery;

/*

https://github.com/paquettg/php-html-parser
https://github.com/Masterminds/html5-php

if (! function_exists(__NAMESPACE__ . '\greetings'))

*/

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

function query_type($ds, $type) {
    return $ds->query_type($type);
}

function build_sorter($key) {
    return function ($a, $b) use ($key) {
        return strnatcasecmp($a[$key], $b[$key]);
    };
}
function pagination($info, $page) {
    return array_merge($info, [
        'page' => $page,
        'prev' => ($page - 1) ?: null,
        'next' => (($page + 1) <= $info['totalpages']) ? ($page + 1) : null
    ]);
}

function chunked_paginate($ds, $rule) {
    $limit = $rule['limit'] ?? 20;
    // $all = lquery($ds->data, $rule);
    list($all, $total) = $ds->query($rule, $limit);

    // $total = count($all);
    $totalpages = ceil($total / $limit);
    foreach (range(1, $totalpages) as $page) {
        $offset = ($page - 1) * $limit;
        $res = array_slice($all, $offset, $limit);
        $info = [
            'total' => $total, 'totalpages' => $totalpages, 'page' => $page,
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
    $info = [
        'total' => $total, 'totalpages' => $totalpages, 'page' => $page,
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

function template_context($type, $context, $data, $ds, $config) {
    $context['template_type'] = $type;
    return hook::invoke_filter('modify_template_context', $context, $data, $ds, $config);
}

function path_asset($asset, $cachebust = false) {
    return PATH_PREFIX . $asset . cachebuster($cachebust);
}

function cachebuster($cachebust = false) {
    if ($cachebust === false) {
        return "";
    }
    if ($cachebust === true) {
        return "?" . time();
    }
    return "?rev=" . $cachebust;
}

function path_page($page) {
    return PATH_PREFIX . $page;
}

function prefix_endpoint($ep) {
    return PATH_PREFIX . '/__fun' . $ep;
}

function write($content, $path, $pagenr, $base) {
    if (!$content) {
        return;
    }
    if ($pagenr && $pagenr != 1) {
        $path .= '/' . $pagenr;
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

function layout($name = null) {
    return \slowfoot\template\layout($name);
}

function get_absolute_path_from_base($path, $current, $base) {
    if (substr($path, 0, 2) == '~/') {
        $path = $base . ltrim($path, '~');
        dbg("+++ path ~ +++", $path);
        $remove_base = true;
    } else {
        $path = $current . '/' . $path;
        $remove_base = false;
    }

    $path = get_absolute_path($path);
    dbg("+++ path +++", $path);
    if ($remove_base) {
        $path = str_replace($base . '/', '', '/' . $path);
        dbg("+++ path +++ ++++ ", $path, $base);
    }
    return $path;
}
function get_absolute_path($path) {
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
