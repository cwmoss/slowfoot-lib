<?php

namespace slowfoot\template;

use function lolql\parse;

function process_template($id, $path)
{
    global $templates;
    layout('-');
    $data = query('*[_id=="$id"][0]', ['id' => $id]);
    process_template_data($data, $path);
}

function partial($base, $template, $data=[], $helper=[], $non_existent="")
{
    extract($data);
    extract($helper);
    extract(load_late_template_helper($helper, $base, $data));
    $file = $base . '/partials/' . $template . '.php';
    if (is_file($file)) {
        ob_start();
        include($file);
        $content = ob_get_clean();
    } else {
        $content = sprintf($non_existent, $file);
    }
    return $content;
}

function template_get_context($name, $context, $props)
{
    $name = trim($name, '/');
    return array_merge($context, $props, ['name'=>$name]);
}

function template($_template, $data, $helper, $__context)
{
    #var_dump($__context);
    $_base = $__context['src'];
    extract($data);
    extract($helper);
    extract(load_late_template_helper($helper, $_base, $data));
    $_context = template_get_context($_template, $__context, ['is_template'=>true, 'is_page'=>false]);
    \collect_data();
    \collect_data('meta', $_context, true);
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

function page($_template, $data, $helper, $__context)
{
    $_base = $__context['src'];
    extract($data);
    extract($helper);
    extract(load_late_template_helper($helper, $_base, $data));
    $_context = template_get_context($_template, $__context, ['is_template'=>false, 'is_page'=>true]);
    \collect_data();
    \collect_data('meta', $_context, true);
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

function layout($name = null)
{
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
function check_pagination($_template, $_base)
{
    $content = file_get_contents($_base . '/pages/' . $_template . '.php');
    $prule = preg_match('!<page-query>(.*?)</page-query>!ism', $content, $mat);
    if ($prule) {
        return parse($mat[1]);
    } else {
        return false;
    }
}
function preprocess($_template, $_base)
{
    $content = file_get_contents($_base . '/pages/' . $_template . '.php');
    return parse_tags($content, ['page-query']);
}

function parse_tags($content, $tags)
{
    $res = [];
    foreach ($tags as $tag) {
        #print "hhh $tag\n$content";
        $x = preg_match("!<$tag([^>]*?)>(.*?)</$tag>!ism", $content, $mat);
        if ($x) {
            $xml = new \SimpleXMLElement($mat[0]);
            $res[$tag] = array_map(function ($attr) {
                return (string) $attr;
            }, \iterator_to_array($xml->attributes()));
            $res[$tag]['__content'] = trim($mat[2]);
            $res[$tag]['__tag'] = $tag;
        }
    }
    return $res;
}

function remove_tags($content, $tags)
{
    //dbg('remove...');
    foreach ($tags as $tag) {
        $content = preg_replace("!<$tag([^>]*?)>(.*?)</$tag>!ism", '', $content);
    }
    //$content = preg_replace('!<page-query>.*?</page-query>!ism', '', $content);
    return $content;
}

function page_paginated($_template, $data, $_base)
{
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

function paginate($how = null)
{
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

function process_template_data($data, $path)
{
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


function load_late_template_helper($helper, $base, $data)
{
    $additional_helper_for_partials = [
        // global $p('pagekey.subkey.etc') function
        'p' => function ($dot_path, $default = null) use ($data) {
            return dot_get($data, $dot_path, $default);
        },
        // local $dot('datakey.subkey.etc') function
        'dot' => function ($dot_path, $default = null) use ($data) {
            return dot_get($data, $dot_path, $default);
        }
    ];

    $helper = array_merge($helper, $additional_helper_for_partials);
    $markdown = $helper['markdown'];

    return array_merge($additional_helper_for_partials, [
        'partial' => function ($template, $data=[], $non_existent="") use ($helper, $base) {
            //dbg('+++ partial src', $src);
            $helper['dot'] = function ($dot_path, $default = null) use ($data) {
                return dot_get($data, $dot_path, $default);
            };
            
            return partial($base, $template, $data, $helper, $non_existent);
        },
        'markdown' => markdown_helper_obj($markdown, $data),
        
     ]);
}
