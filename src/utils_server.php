<?php

function println($str)
{
    return print($str.PHP_EOL);
}

function get_env()
{
    return array_merge($_SERVER, getenv());
}

/*
    (hier nicht vorhanden) preview: fs server
    local: local development
    dev: deployment server/ dev
    prod: deployment server/ prod
    live: live server, prod
*/
function get_env_name($env)
{
    // ausdrücklich gesetzt?
    if (isset($env['HKW_ENV']) && $env['HKW_ENV']) {
        return $env['HKW_ENV'];
    }

    // cli script?
    if (PHP_SAPI == 'cli') {
        // anderenfalls muss HKW_ENV gesetzt sein
        return "live";
    }
    # print_r($env);

    $host = $env['SERVER_NAME'];
    if ($host=='localhost') {
        return 'local';
    }
    if ($host == 'dev.hkw.online') {
        return 'dev';
    }
    if ($host == 'prod.hkw.online') {
        return 'prod';
    }
    if ($host == 'hkw.20sec.net') {
        return 'test';
    }
    return 'live';
}

function get_config($env, $appbase)
{
    // $conf = parse_ini_file($appbase."/emil.ini");
    $conf = [
        'envname' => get_env_name($env)
    ];
    #print "ENV-NAME: ".$conf['envname'];

    $solr_defaults = [
        'local' => 'host.docker.internal:8982/hkw',
        'dev' => 'http://10.60.10.76:8080/hkw_stage',
        'prod' => 'http://10.60.10.76:8080/hkw_preview',
        'test' => 'http://localhost:8982/hkw',
        'live' => '' # TODO slaves array
    ];
    $imgproxy_defaults = [
        'local' => 'http://dev.hkw.online',
        'dev' => '',
        'prod' => '',
        'test' => '',
        'live' => ''
    ];

    $solr = $env['SOLR']??null;
    if (!$solr) {
        $solr = $solr_defaults[$conf['envname']];
    }
    $imgproxy_prefix = $env['IMGPROXY_PREFIX']??null;
    if (!$imgproxy_prefix) {
        $imgproxy_prefix = $imgproxy_defaults[$conf['envname']];
    }
    
    $conf['base'] = '/templates';
    #$conf['base'] = $appbase . '/templates';
    $conf['etc'] = $appbase . '/etc';
    $conf['src'] = $appbase . '/src';
    $conf['tmp'] = sys_get_temp_dir(); # $appbase . '/tmp';
    $conf['appbase'] = $appbase;
    // $conf['api_keys'] = explode(",", $env['DRUCKT_API_KEYS']);
    $conf['debug_allow'] = parse_switches("rdata pdata settings pipe");
    $conf['solr'] = 'localhost:8982/hkw';
    $conf['solr'] = $solr;
    $conf['imgproxy_prefix'] = $imgproxy_prefix;

    image_config(['prefix'=>$conf['imgproxy_prefix']]);

    #$conf['solr'] = 'host.docker.internal:8982/testmanaged';
//    $conf['transport'] = $env['EMIL_MAIL_TRANSPORT'];
//    $conf['jwt_secret'] = $env['EMIL_JWT_SECRET'];
    return $conf;
}

/*
    files related
*/

/*
    creates a auto-delete tempfile
    returns the name
    substitude for tempnam($dir, $prefix)
*/
function tempfilename($dir="", $prefix="")
{
    # return stream_get_meta_data(tmpfile())['uri'];
    $file = tempnam($dir, $prefix);
    //register_shutdown_function(fn () => @unlink($file));
    register_shutdown_function(function () use ($file) {
        @unlink($file);
    });
    return $file;
}

function normalize_files_array($files = [])
{
    $normalized_array = [];

    foreach ($files as $index => $file) {
        if (!is_array($file['name'])) {
            $normalized_array[$index][] = $file;
            continue;
        }

        foreach ($file['name'] as $idx => $name) {
            $normalized_array[$index][$idx] = [
                'name' => $name,
                'type' => $file['type'][$idx],
                'tmp_name' => $file['tmp_name'][$idx],
                'error' => $file['error'][$idx],
                'size' => $file['size'][$idx]
            ];
        }
    }

    return $normalized_array;
}

function stream_to_file($name)
{
    $tmpfname = tempnam(sys_get_temp_dir(), 'emil-');
    file_put_contents($tmpfname, file_get_contents('php://input'));
    return [
        'name' => $name,
        'type' => 'stream',
        'tmp_name' => $tmpfname,
        'error' => 0,
        'size' => filesize($tmpfname)
    ];
}

function xsend_file($base, $file)
{
    $file = basename($file);
    if (preg_match('/css$/', $file)) {
        header('Content-Type: text/css');
    } elseif (preg_match('/js$/', $file)) {
        header('Content-Type: text/javascript');
    } elseif (preg_match('/svg$/', $file)) {
        header('Content-Type: image/svg+xml');
    } elseif (preg_match('/html$/', $file)) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Content-Type: text/html');
    }
    dbg('sending', $base . '/ui/' . $file);
    readfile($base . '/ui/' . $file);
}

function xsend_asset_file($base, $file, $ext="")
{
    $types = [
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf'
    ];
    $f = $base.'/'.$file;
    $ext = pathinfo($f, PATHINFO_EXTENSION);
    $file = basename($file);
    header('Content-Type: '.$types[$ext]);
    readfile($f);
}

function get_trace_from_exception($e)
{
    $class = get_class($e);
    $pclass = get_parent_class($e);
    $m = $e->getMessage();
    $trace = "";
    $fm = sprintf(
        "%s:\n   %s line: %s code: %s\n   via %s%s\n",
        $m,
        $e->getFile(),
        $e->getLine(),
        $e->getCode(),
        $class,
        $pclass ? ', ' . $pclass : ''
    );
    $trace .= $fm . $e->getTraceAsString();
    return $trace;
}


function text_for($muster, $vars=array())
{
    $repl = array();
    foreach ($vars as $k=>$v) {
        $repl['{'.strtolower($k).'}']=$v;
    }
    $txt = $muster;
    $txt = str_replace(array_keys($repl), $repl, $txt);
    return $txt;
}



function shell_command($cmd, $parms, $opts=[])
{
    $parms = array_map('escapeshellarg', $parms);
    $cmd = text_for($cmd, $parms);
    # $ok = shell_exec($cmd);

    exec($cmd, $output, $ok);
    if ($ok!==0) {
        #e500("shell command failed: $cmd");
    }
    return [$output, $ok];
}


/*
    http related
*/
function resp($data)
{
    $elapsed = microtime(true) - START_TIME;
    if (!isset($data['res'])) {
        $data = ['res'=>$data];
    }
    if (isset($data['__meta'])) {
        $data['__meta']['time'] = $elapsed;
    } else {
        $data['__meta']=['time'=>$elapsed];
    }
    $data['__meta']['time_ms'] = (int)($elapsed * 1000);
    $data['__meta']['time_microsec'] = (int)($elapsed * 1000 * 1000);
    $data['__meta']['time_print'] = $data['__meta']['time_ms']?
        $data['__meta']['time_ms'].' ms':$data['__meta']['time_microsec'].' μs';
    header('Content-Type: application/json'); //; charset=utf-8
    print json_encode($data);

    dbg('+++ finished');
}

function e404($msg = 'not found')
{
    header('HTTP/1.1 404 Not Found');
    resp(['fail' => $msg]);
}

function e401($msg = 'unauthorized api request')
{
    dbg('+++ 401 +++ ');
    header('HTTP/1.1 401 Unauthorized');
    resp(['fail' => $msg]);
    exit;
}

function e500($msg = 'fatal error')
{
    dbg('+++ 500 +++ ');
    header('HTTP/1.1 500 Bad Request');
    resp(['fail' => $msg]);
    exit;
}

function get_json_and_raw_req()
{
    $raw = get_raw_req();
    $post = json_decode($raw, true);
    return [$post, $raw];
}

function get_json_req()
{
    return json_decode(get_raw_req(), true);
}

function get_raw_req()
{
    dbg('++++ raw input read ++++');
    return file_get_contents('php://input');
}

function get_req_headers($router)
{
    dbg('+++ router get headers');
    return array_change_key_case($router->getRequestHeaders());
}

function url_to_pdo_dsn($url)
{
    $parts = parse_url($url);

    return [
        $parts['scheme'] . ':host=' . $parts['host'] . ';dbname=' . trim($parts['path'], '/'),
        $parts['user'],
        $parts['pass']
    ];
}


function send_cors()
{
    $orig = @$_SERVER['HTTP_ORIGIN'];
    header('Access-Control-Allow-Origin: ' . $orig);
    header('Access-Control-Allow-Methods: POST, GET, HEAD, PATCH, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 1000');
    if (array_key_exists('HTTP_ACCESS_CONTROL_REQUEST_HEADERS', $_SERVER)) {
        header(
            'Access-Control-Allow-Headers: '
                  . 'Authorization, Origin, X-Requested-With, X-Request-ID, X-HTTP-Method-Override, Content-Type, Upload-Length, Upload-Offset, Tus-Resumable, Upload-Metadata'
              //   . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']
        );
    } else {
        //   header('Access-Control-Allow-Headers: *');
    }

    header('Access-Control-Allow-Credentials: true');
    //  header('Access-Control-Allow-Headers: Authorization');
    header('Access-Control-Expose-Headers: Upload-Key, Upload-Checksum, Upload-Length, Upload-Offset, Upload-Metadata, Tus-Version, Tus-Resumable, Tus-Extension, Location');
}

function send_nocache()
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Content-Type: text/html');
}
