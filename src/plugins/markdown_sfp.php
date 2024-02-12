<?php

namespace slowfoot\plugins;

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
            $this->context['conf']->base
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
