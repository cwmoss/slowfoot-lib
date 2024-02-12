<?php

use slowfoot\util\console;

function println($str) {
    return print($str . PHP_EOL);
}

function get_env() {
    return array_merge($_SERVER, getenv());
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

function query_type($ds, $type) {
    return $ds->query_type($type);
}

function build_sorter($key) {
    return function ($a, $b) use ($key) {
        return strnatcasecmp($a[$key], $b[$key]);
    };
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
    return \slowfoot\template::layout($name);
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

function send_file($base, $file) {
    $name = basename($file);
    $full = $base . '/' . $file;

    if (!file_exists($full)) {
        header('HTTP/1.1 404 Not Found');
        return;
    }

    $ext = pathinfo($name, PATHINFO_EXTENSION);

    $types = [
        'css' => 'text/css',
        'js'   => 'text/javascript',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'html' => 'text/html',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf'
    ];

    $type = $types[$ext];

    if ($ext == 'css') {
        $scss = $full . '.scss';
        if (file_exists($scss)) {
            // die(" sassc $scss $full");
            //print "sassc $scss $full";
            $resp = shell_command('sassc {in} {out} 2>&1', ['in' => $scss, 'out' => $full]);
            // $ok = `sassc $scss $full`;
            if ($resp[1] !== 0) {
                dbg("[sassc] error", $resp);
            }
            //var_dump($ok);
        }
    }
    header('Content-Type: ' . $type);

    if ($ext == 'html') {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Content-Type: text/html');
    }

    readfile($full);
}

function send_asset_file($base, $file, $orig, $cache) {
    $full = $base . '/' . $file;
    $full = str_replace($orig, $cache, $full);
    dbg('+++ asset route', $full);
    //print "$full";
    //exit;
    header('Content-Type: image/jpg');
    if (file_exists($full)) {
        readfile($full);
    } else {
        header('HTTP/1.1 404 Not Found');
    }
}

function dbg($txt, ...$vars) {
    // im servermodus wird der zeitstempel automatisch gesetzt
    //	$log = [date('Y-m-d H:i:s')];
    $log = [];
    if (!is_string($txt)) {
        array_unshift($vars, $txt);
    } else {
        $log[] = $txt;
    }
    $log[] = join(' ~ ', array_map(fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $vars));
    error_log(join(' ', $log));
}

function markdown($text) {
    $parser = new Parsedown();
    //$parser->setUrlsLinked(false);
    return $parser->text($text);
}

function fetch($url, $data) {
    if (is_array($data)) {
        $data = json_encode($data);
    }
    $options = [
        'http' => [
            'method' => 'POST',
            'content' => $data,
            'header' => "Content-Type: application/json\r\n" .
                "Accept: application/json\r\n"
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);
    return $response;
}

function globstar($pattern, $flags = 0) {
    if (stripos($pattern, '**') === false) {
        $files = glob($pattern, $flags);
    } else {
        $position = stripos($pattern, '**');
        $rootPattern = substr($pattern, 0, $position - 1);
        $restPattern = substr($pattern, $position + 2);
        $patterns = array($rootPattern . $restPattern);
        $rootPattern .= '/*';
        while ($dirs = glob($rootPattern, GLOB_ONLYDIR)) {
            $rootPattern .= '/*';
            foreach ($dirs as $dir) {
                $patterns[] = $dir . $restPattern;
            }
        }
        $files = array();
        foreach ($patterns as $pat) {
            $files = array_merge($files, globstar($pat, $flags));
        }
    }
    $files = array_unique($files);
    sort($files);
    return $files;
}

function shell_info($start = null, $single = false) {
    static $stime;
    static $console;

    // last resort to non-cli stuff
    if (PHP_SAPI != 'cli') {
        if (!(defined('SLOWFOOT_WEBDEPLOY') && SLOWFOOT_WEBDEPLOY)) {
            return;
        }
    }

    if (!$console) {
        $console = console::console();
    }

    if ($start) {
        if (!$single) {
            $stime = microtime(true);
            print $console('bold', $start);
            print ' ... ';
        } else {
            print $console('green', $start);
            print PHP_EOL;
        }
    } else {
        $elapsed = microtime(true) - $stime;
        $stime = null;
        print $console('reverse', nice_elapsed_time($elapsed)['print']);
        print PHP_EOL;
    }
}

function nice_elapsed_time($elapsed) {
    $nice = [
        'time' => $elapsed,
        's' => (int) $elapsed,
        'ms' => (int)($elapsed * 1000),
        'micro' => (int)($elapsed * 1000 * 1000),
    ];
    $nice['print'] = $nice['s'] ?
        $nice['s'] . ' s'
        : ($nice['ms'] ? $nice['ms'] . ' ms' : $nice['micro'] . ' μs');
    return $nice;
}

// output zur browser console
function console_log(...$data) {
    //func_get_args()
    $out = ["<script>", "console.info('%cPHP console', 'font-weight:bold;color:green;');"];
    foreach ($data as $d) {
        $out[] = 'console.log(' . json_encode($d) . ');';
    }
    $out[] = "</script>";
    print(join("", $out));
}
function debug_js($k = null, $v = null) {
    static $vars = [];
    if (is_null($k) && is_null($v)) {
        return json_encode($vars, JSON_PRETTY_PRINT);
    }
    $vars[$k] = $v;
}

function include_to_buffer($incl) {
    ob_start();
    include $incl;
    return ob_get_clean();
}

function dot_get($data, $path, $default = null) {
    $val = $data;
    $path = explode(".", $path);
    #print_r($path);
    foreach ($path as $key) {
        if (!isset($val[$key])) {
            return $default;
        }
        $val = $val[$key];
    }
    return $val;
}

/*
    parts:
    m main
    i imports
    c css
*/
function assets_from_manifest($manifest_base, $prefix = "", $entry = "", $parts = 'm') {
    #print $manifest_base;
    #return "hoho";

    $content = file_get_contents($manifest_base . '/manifest.json');
    $man = json_decode($content, true);
    $entry = $entry ? $man[$entry] : $man[key($man)];

    $main = '<script type="module" crossorigin src="%s"></script>';
    $import = '<link rel="modulepreload" href="%s">';
    $css = '<link rel="stylesheet" href="%s">';
    $tags = [
        sprintf($main, man_asset_url($entry, $prefix))
    ];

    if (strpos($parts, 'i') !== false) {
        $tags = array_merge($tags, array_map(function ($url) use ($import) {
            return sprintf($import, $url);
        }, man_imports_urls($entry, $man, $prefix)));
    }
    if (strpos($parts, 'c') !== false) {
        $tags = array_merge($tags, array_map(function ($url) use ($css) {
            return sprintf($css, $url);
        }, man_css_urls($entry, $prefix)));
    }

    return join("\n", $tags);
}

function man_asset_url($entry, $prefix = "") {
    return isset($entry['file'])
        ? $prefix . '/' . $entry['file']
        : '';
}

function man_imports_urls($entry, $manifest, $prefix = "") {
    $urls = [];
    if (!empty($entry['imports'])) {
        foreach ($entry['imports'] as $imports) {
            $urls[] = $prefix . '/' . $manifest[$imports]['file'];
        }
    }
    return $urls;
}

function man_css_urls($entry, $prefix = "") {
    $urls = [];
    if (!empty($entry['css'])) {
        foreach ($entry['css'] as $file) {
            $urls[] = $prefix . '/' . $file;
        }
    }
    return $urls;
}

if (!function_exists('is_assoc')) {
    function is_assoc(array $arr) {
        if ([] === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
