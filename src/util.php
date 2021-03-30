<?php

function send_file($base, $file) {
    $name = basename($file);
    $full = $base . '/' . $file;

    if (preg_match('/\.css$/', $name)) {
        header('Content-Type: text/css');
        $scss = $full . '.scss';
        if (file_exists($scss)) {
            // die(" sassc $scss $full");
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

function dbg($txt, ...$vars) {
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
