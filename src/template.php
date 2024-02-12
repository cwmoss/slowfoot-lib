<?php

namespace slowfoot;

use function lolql\parse;
use function collect_data;
use function dot_get;
use slowfoot\configuration;
use slowfoot\hook;
use slowfoot\util\html;


class template {

    public function __construct(public configuration $config) {
    }

    public function run(string $_template, array $data, array $helper, context $__context): string {
        #var_dump($__context);
        $_base = $__context->src;
        extract($data);
        extract($helper);

        $_context = $this->get_context(
            $_template,
            $__context,
            ['is_template' => true, 'is_page' => false],
            $data,
            $helper
        );
        extract($this->load_late_template_helper($helper, $_base, $data, $_context));

        html::collect_data();
        html::collect_data('meta', (array) $_context, true);
        self::layout('-');
        ob_start();
        include $_base . '/templates/' . $_template . '.php';
        $content = ob_get_clean();
        $layout = self::layout();
        if ($layout) {
            ob_start();
            include $_base . '/layouts/' . $layout . '.php';
            $content = ob_get_clean();
        }
        return $content;
    }

    public function run_page(string $_template, array $data, array $helper, context $__context): string {
        $_base = $__context->src;

        extract($data);
        extract($helper);

        $_context = $this->get_context(
            $_template,
            $__context,
            ['is_template' => false, 'is_page' => true],
            $data,
            $helper
        );
        extract($this->load_late_template_helper($helper, $_base, $data, $_context));

        html::collect_data();
        html::collect_data('meta', (array) $_context, true);
        self::layout('-');
        ob_start();
        include $_base . '/pages/' . $_template . '.php';

        $content = ob_get_clean();
        $layout = self::layout();
        if ($layout) {
            ob_start();
            include $_base . '/layouts/' . $layout . '.php';
            $content = ob_get_clean();
        }
        return $content;
    }

    static public function layout($name = null) {
        static $layout = null;
        if (!is_null($name)) {
            // reset layout name
            if ($name == '-') {
                $layout = null;
            } else {
                $layout = $name;
            }
        }
        return $layout;
    }

    public function partial($base, $template, context $_context, array $data = [], array $helper = [], $non_existent = "") {
        extract($data);
        extract($helper);
        extract($this->load_late_template_helper($helper, $base, $data, $_context));
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

    public function get_context(
        string $name,
        context $context,
        array $props,
        array $data,
        array $helper
    ): context {
        $context = $context->with(['name' => trim($name, '/')] + $props);
        return $context;
    }

    public function load_late_template_helper($helper, $base, $data, context $context) {
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

        foreach (hook::invoke('bind_late_template_helper', [], $helper, $base, $data) as $hlp) {
            $helper[$hlp[0]] = $hlp[1];
        }

        return array_merge($additional_helper_for_partials, [
            'partial' => function ($template, $data = [], $non_existent = "") use ($helper, $base, $context) {
                //dbg('+++ partial src', $src);
                $helper['dot'] = function ($dot_path, $default = null) use ($data) {
                    return dot_get($data, $dot_path, $default);
                };

                return $this->partial($base, $template, $context, $data, $helper,  $non_existent);
            },
        ]);
    }

    public function remove_tags($content, $tags) {
        //dbg('remove...');
        foreach ($tags as $tag) {
            $content = preg_replace("!<$tag([^>]*?)>(.*?)</$tag>!ism", '', $content);
        }
        //$content = preg_replace('!<page-query>.*?</page-query>!ism', '', $content);
        return $content;
    }

    public function preprocess($_template, $_base) {
        $content = file_get_contents($_base . '/pages/' . $_template . '.php');
        return $this->parse_tags($content, ['page-query']);
    }

    public function parse_tags($content, $tags) {
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
}
