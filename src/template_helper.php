<?php
use function lolql\query as lquery;

function add_template_helper($name, $fun){

}

function load_template_helper($ds, $src, $config)
{
    if (file_exists($src.'/template_helper.php')) {
        $custom = require_once($src.'/template_helper.php');
    } else {
        $custom = [];
    }
    foreach(hook::invoke('bind_template_helper', [], $ds, $src, $config) as $hlp){
        $custom[$hlp[0]] = $hlp[1]; 
    }
    #var_dump($custom);
    $default = [
        'path' => function ($oid, $name = null) use ($ds) {
            //print "-- $oid";
            return $ds->get_path($oid, $name);
        },
        'get' => function ($oid) use ($ds) {
            return $ds->get($oid);
        },
        'ref' => function ($oid) use ($ds) {
            return $ds->ref($oid);
        },
        'q' => function ($query_string, $params=[]) use ($ds) {
            return $ds->query_sql($query_string, $params);
        },
        'query' => function ($q, $params=[]) use ($ds) {
            return $ds->query($q, $params);
        //lquery($ds->data, $q);
        },
        'image' => function ($asset, $profile) use ($config) {
            return \slowfoot\image($asset, $profile, $config['assets']);
        },
        'image_tag' => function ($asset, $profile, $tag=[]) use ($config) {
            return \slowfoot\image_tag($asset, $profile, $tag, $config['assets']);
        },
        'image_url' => function ($asset, $profile) use ($config) {
            return \slowfoot\image_url($asset, $profile, $config['assets']);
        },
        'asset_from_file' => function ($path) use ($config) {
            //var_dump($config);
            return \slowfoot\asset_from_file($path, $config['assets']);
        },
        'markdown' => markdown_helper($config, $ds)
    ];
    return array_merge($default, $custom);
}

function h($str)
{
    return htmlspecialchars($str);
}


function text_for($pattern, $vars=[])
{
    $repl = [];
    foreach ($vars as $k=>$v) {
        $repl['{'.strtolower($k).'}']=$v;
    }
    $txt = $pattern;
    $txt = str_replace(array_keys($repl), $repl, $txt);
    return $txt;
}
