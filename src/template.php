<?php

namespace slowfoot\template;
use function lolql\parse;

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
    extract(load_late_template_helper($helper, $_base));
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
    extract(load_late_template_helper($helper, $_base));
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

/*
$x = new SimpleXMLElement('<element lang="sql"></element>');
iterator_to_array($x->attributes()))
*/
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



function load_late_template_helper($helper, $base){
    return [
       'partial' => function ($template, $data) use ($helper, $base) {
            //dbg('+++ partial src', $src);
            return partial($base, $template, $data, $helper);
        }
    ];
}