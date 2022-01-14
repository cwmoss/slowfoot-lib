<?php
require_once(__DIR__."/console.php");

function send_file($base, $file)
{
    $name = basename($file);
    $full = $base . '/' . $file;

    if (preg_match('/\.css$/', $name)) {
        header('Content-Type: text/css');
        $scss = $full . '.scss';
        if (file_exists($scss)) {
            // die(" sassc $scss $full");
            //print "sassc $scss $full";
            $ok = `sassc $scss $full`;
            //var_dump($ok);
        }
    } elseif (preg_match('/js$/', $name)) {
        header('Content-Type: text/javascript');
    } elseif (preg_match('/svg$/', $name)) {
        header('Content-Type: image/svg+xml');
    } elseif (preg_match('/html$/', $name)) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Content-Type: text/html');
    }
    // dbg('sending', $full);
    if (file_exists($full)) {
        readfile($full);
    } else {
        header('HTTP/1.1 404 Not Found');
    }
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
    $log[] = join(' ', array_map('json_encode', $vars));
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
        return;
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
        : ($nice['ms']?$nice['ms'].' ms':$nice['micro'].' μs');
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
