<?php
require_once(__DIR__."/console.php");

function send_file($base, $file)
{
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

    if ($ext=='css') {
        $scss = $full . '.scss';
        if (file_exists($scss)) {
            // die(" sassc $scss $full");
            //print "sassc $scss $full";
            $resp = shell_command('sassc {in} {out} 2>&1', ['in'=>$scss, 'out'=>$full]);
            // $ok = `sassc $scss $full`;
            if ($resp[1]!==0) {
                dbg("[sassc] error", $resp);
            }
            //var_dump($ok);
        }
    } 
    header('Content-Type: '.$type);

    if ($ext=='html'){
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Content-Type: text/html');
    }

    readfile($full);
}

function send_asset_file($base, $file, $orig, $cache)
{
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

function dbg($txt, ...$vars)
{
    // im servermodus wird der zeitstempel automatisch gesetzt
    //	$log = [date('Y-m-d H:i:s')];
    $log = [];
    if (!is_string($txt)) {
        array_unshift($vars, $txt);
    } else {
        $log[] = $txt;
    }
    $log[] = join(' ~ ', array_map(fn ($v) =>json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $vars));
    error_log(join(' ', $log));
}

function markdown($text)
{
    $parser = new Parsedown();
    //$parser->setUrlsLinked(false);
    return $parser->text($text);
}

function fetch($url, $data)
{
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

function globstar($pattern, $flags = 0)
{
    if (stripos($pattern, '**') === false) {
        $files = glob($pattern, $flags);
    } else {
        $position = stripos($pattern, '**');
        $rootPattern = substr($pattern, 0, $position - 1);
        $restPattern = substr($pattern, $position + 2);
        $patterns = array($rootPattern.$restPattern);
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

function shell_info($start=null, $single=false)
{
    static $stime;
    static $console;

    // last resort to non-cli stuff
    if (PHP_SAPI != 'cli') {
        if(!(defined('SLOWFOOT_WEBDEPLOY') && SLOWFOOT_WEBDEPLOY)){
            return;
        }
    }

    if (!$console) {
        $console = console();
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

function nice_elapsed_time($elapsed)
{
    $nice = [
        'time' => $elapsed,
        's' => (int) $elapsed,
        'ms' => (int)($elapsed * 1000),
        'micro' => (int)($elapsed * 1000 * 1000),
    ];
    $nice['print'] = $nice['s']?
        $nice['s']. ' s'
        : ($nice['ms']?$nice['ms'].' ms':$nice['micro'].' ??s');
    return $nice;
}

// output zur browser console
function console_log(...$data)
{
    //func_get_args()
    $out = ["<script>", "console.info('%cPHP console', 'font-weight:bold;color:green;');"];
    foreach ($data as $d) {
        $out[] = 'console.log('.json_encode($d).');';
    }
    $out[] = "</script>";
    print(join("", $out));
}
function debug_js($k=null, $v=null)
{
    static $vars=[];
    if (is_null($k) && is_null($v)) {
        return json_encode($vars, JSON_PRETTY_PRINT);
    }
    $vars[$k] = $v;
}

function include_to_buffer($incl)
{
    ob_start();
    include $incl;
    return ob_get_clean();
}

function dot_get($data, $path, $default=null)
{
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
function assets_from_manifest($manifest_base, $prefix="", $entry="", $parts='m')
{
    #print $manifest_base;
    #return "hoho";

    $content = file_get_contents($manifest_base . '/manifest.json');
    $man = json_decode($content, true);
    $entry = $entry?$man[$entry]:$man[key($man)];

    $main = '<script type="module" crossorigin src="%s"></script>';
    $import = '<link rel="modulepreload" href="%s">';
    $css = '<link rel="stylesheet" href="%s">';
    $tags = [
        sprintf($main, man_asset_url($entry, $prefix))
    ];

    if (strpos($parts, 'i')!==false) {
        $tags = array_merge($tags, array_map(function ($url) use ($import) {
            return sprintf($import, $url);
        }, man_imports_urls($entry, $man, $prefix)));
    }
    if (strpos($parts, 'c')!==false) {
        $tags = array_merge($tags, array_map(function ($url) use ($css) {
            return sprintf($css, $url);
        }, man_css_urls($entry, $prefix)));
    }
  
    return join("\n", $tags);
}

function man_asset_url($entry, $prefix="")
{
    return isset($entry['file'])
        ? $prefix.'/'.$entry['file']
        : '';
}

function man_imports_urls($entry, $manifest, $prefix="")
{
    $urls = [];
    if (!empty($entry['imports'])) {
        foreach ($entry['imports'] as $imports) {
            $urls[] = $prefix.'/'.$manifest[$imports]['file'];
        }
    }
    return $urls;
}

function man_css_urls($entry, $prefix="")
{
    $urls = [];
    if (!empty($entry['css'])) {
        foreach ($entry['css'] as $file) {
            $urls[] = $prefix.'/'.$file;
        }
    }
    return $urls;
}
