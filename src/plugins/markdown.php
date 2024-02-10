<?php

hook::add('bind_template_helper', function ($ds, $src, $config) {
    return ['markdown', markdown_helper($config, $ds)];
});

hook::add('bind_late_template_helper', function ($helper, $base, $data) {
    $md = $helper['markdown'];
    return ['markdown', markdown_helper_obj($md, $data)];
});

function load_markdown($opts, $config, $db) {
    $front = new Mni\FrontYAML\Parser;
    $filep = $config['base'] . '/' . $opts['file'];
    dbg("++ md glob:", $filep);
    $prefix = $config['base'] . '/';

    $files = globstar($filep);
    foreach ($files as $f) {
        dbg("++ md file:", $f);

        $fname = str_replace($prefix, '', $f);
        $path_parts = pathinfo($fname);

        $document = $front->parse(file_get_contents($f), false);
        $data = $document->getYAML() ?? [];
        $md = $document->getContent() ?? '';
        #$id = $data['_id']??($data['id']??$fname);
        $id = $path_parts['dirname'] . '/' . $path_parts['filename'];
        // TODO: anything goes
        // $id = str_replace('/', '-', $id);
        $row = array_merge($data, [
            'mdbody' => $md,
            '_id' => $id,
            '_file' => [
                'path' => $fname,
                'dir' => $path_parts['dirname'],
                'name' => $path_parts['filename'],
                'ext' => $path_parts['extension']
            ]
        ]);
        yield $row;
    }
    return;
}

function markdown_sf($md, $config = null, $ds = null) {
    $parser = new markdown_sfp();
    $parser->set_context(['conf' => $config, 'ds' => $ds]);
    //$parser->setUrlsLinked(false);
    return $parser->text($md);
}

function markdown_toc($content) {
    $Parsedown = new \ParsedownToC();
    $body = $Parsedown->body($content);
    $toc  = $Parsedown->contentsList();
    dbg("+++ toc", $toc);
    return $toc;
}

function markdown_parser($config, $ds) {
    $parser = new markdown_sfp();
    $parser->set_context(['conf' => $config, 'ds' => $ds]);
    return $parser;
}

function markdown_helper($config = null, $ds = null) {
    $parser = markdown_parser($config, $ds);

    return function ($text, $obj = null) use ($parser) {
        $parser->set_current_obj($obj);
        return $parser->text($text);
    };
}

function markdown_helper_obj($parser, $data = null) {
    #var_dump($data);
    return function ($text) use ($parser, $data) {
        return $parser($text, $data);
    };
}

/*
https://github.com/erusev/parsedown/wiki/Tutorial:-Create-Extensions#change-element-markup

https://github.com/Nessworthy/parsedown-extension-manager

*/
class markdown_sfp extends ParsedownToC {
    public $context;
    public $current_obj;

    public function set_context($ctx) {
        $this->context = $ctx;
    }

    public function set_current_obj($obj) {
        $this->current_obj = $obj;
    }

    public function xxx__construct() {
        //$this->InlineTypes['['][]= 'Shortcode';
        // $this->inlineMarkerList .= '^';
    }

    # TODO:
    #   - check protocol
    #   - check images
    protected function inlineLink($Excerpt) {
        $link = parent::inlineLink($Excerpt);
        if (is_array($link)) {
            $href = $link['element']['attributes']['href'];
            $href = $this->resolve_link($href);
            $link['element']['attributes']['href'] = $href;
        }
        dbg("++ final link", $link);
        return $link;
    }

    // wird auch für !image tags aufgerufen
    protected function resolve_link($href) {
        if ($href[0] == '/') {
            return $href;
        }
        #TODO: parse_url

        #$id = get_absolute_path(dirname($this->current_obj['page']['_id']).'/'.$href );
        $id = get_absolute_path_from_base(
            $href,
            dirname($this->current_obj['page']['_id']),
            $this->context['conf']['base']
        );

        if (pathinfo($href, PATHINFO_EXTENSION)) {
            return $id;
        }
        $src_conf = $this->context['conf'];
        dbg("+++ id-> link", $href, $id);
        return $this->context['ds']->get_path($id);
        dbg("current obj", $this->current_obj);
        #return '--resolvedd--'.$this->current_obj['title'].$href;
        return '--resolved--' . $this->current_obj['page']['_file']['path'] . $href;
    }

    protected function inlineImage($Excerpt) {
        dbg("+++ img excerpt", $Excerpt);
        $img = parent::inlineImage($Excerpt);
        if (is_array($img)) {
            dbg("+++ img", $img);
            $pipe = \slowfoot\image_url(
                $img['element']['attributes']['src'],
                ['size' => ""],
                $this->context['conf']['assets']
            );
            $img['element']['attributes']['src'] = $pipe;
            $img['element']['attributes']['data-slft'] = 'ok';
        }
        return $img;
    }

    protected function inlineShortcode($Excerpt) {
        dbg("+++ shortcode excerpt", $Excerpt);

        $el = [
            'extent' => strlen($Excerpt['text']),
            'element' => array(
                'name' => 'shortcodex',
                // 'text' => $matches[1],
                'attributes' => array(
                    'stupid' => 'shit',
                ),
                'handler' => 'shortcode'
                /*array(
                    'function' => 'shortcode',
                    'argument' => 'hoho',
                    'destination' => 'rawHtml',
                )*/
            )
        ];
        return $el;
    }

    public function shortcode($args = []) {
        dbg("handle shortcode", $args);
        return "<shortcodexx>";
    }
}
