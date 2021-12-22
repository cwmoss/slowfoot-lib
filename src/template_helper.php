<?php
use function lolql\query as lquery;

function load_template_helper($ds, $src, $config) {
    if(file_exists($src.'/template_helper.php')){
        $custom = require_once($src.'/template_helper.php');
    }else{
        $custom = [];
    }
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
        'q' => function ($query_string, $params=[]) use ($ds){
            return $ds->query_sql($query_string, $params);
        },
        'query' => function ($q) use ($ds) {
            return lquery($ds->data, $q);
        },
        'xxpartial' => function ($template, $data) use ($src) {
            //dbg('+++ partial src', $src);
            return \slowfoot\template\partial($src, $template, $data, []);
        },
        'image' => function ($asset, $profile) use ($config) {
            return \slowfoot\image($asset, $profile, $config['assets']);
        },
        'asset_from_file' => function ($path) use ($config) {
            //var_dump($config);
            return \slowfoot\asset_from_file($path, $config['assets']);
        }
    ];
    return array_merge($default, $custom);
}
