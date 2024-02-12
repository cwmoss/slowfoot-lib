<?php
/*

composer install sanity/sanity-php

            'dataset' => 'production',
            'projectId' => 'pna8s3iv', #$_ENV['SANITY_ID'],
            'useCdn' => false,
            'token' => $_ENV['SANITY_TOKEN'],
            // 'query' => '*[_type=="custom-type-query"]'
*/

namespace slowfoot\plugins;

use Sanity\BlockContent;
use Sanity\Client as SanityClient;
use slowfoot\configuration;
use slowfoot\hook;
use function http_build_query;

class sanity {

    public function __construct(
        public string $project_id,
        public string $token = '',
        public string $dataset = 'production',
        public bool $use_cdn = false,
        public string $query = '*[!(_type match "system.*") && !(_id in path("drafts.**"))]'
    ) {
    }

    public function init() {
        hook::add('bind_template_helper', function ($ds, $src, $config) {
            return ['sanity_text', function ($text, $opts = []) use ($ds, $config) {
                //print_r($sl);
                return self::sanity_text($text, $opts, $ds, $config, $this);
            }];
        });
        hook::add_filter('assets_map', function ($img) {
            // sanity images
            if ($img['_type'] == 'sanity.imageAsset') {
                return self::sanity_image_to_slft($img);
            }
            return $img;
        });
    }

    static public function data_loader(configuration $config) {
        $me = $config->get_plugin(self::class);
        $client = $me->get_client();
        $query = $me->query;
        #print "\n".$query."\n";
        $res = $client->fetch($query);
        // falls mehrteilige query => flach machen
        if ($res && !isset($res[0])) {
            return array_merge(...array_values($res));
        }

        return array_values($res);
    }

    public function load_preview_object($id, $type = null, configuration $config) {
        // print_r($config);
        //print_r(apache_request_headers());
        //print_r($_COOKIE);
        $me = $config->get_plugin(self::class);
        $client = $me->get_client();
        //       $client = sanity_client($config['preview']['sanity']);

        $document = $client->getDocument($id);
        //print_r($document);
        return $document;
        return [
            '_id' => $id,
            '_type' => 'artist',
            'title' => 'hoho',
            'firstname' => 'HEiko',
            'familyname' => 'van Gogh',
        ];
    }

    public function get_client() {
        return new SanityClient([
            'projectId' => $this->project_id,
            'dataset' => $this->dataset,
            'useCdn' => $this->use_cdn,
            'token' => $this->token
        ]);
    }
    static public function sanity_text($block, $opts, $ds, $config, self $plugin) {
        if (!$block) return "";
        #var_dump($conf);
        $serializer = hook::invoke_filter('sanity.block_serializers', [], $opts, $ds, $config);
        #var_dump($serializer);

        $html = BlockContent::toHtml($block, [
            'projectId' => $plugin->project_id,
            'dataset' => $plugin->dataset,
            'serializers' => $serializer,
        ]);
        return nl2br($html);
    }

    static public function sanity_image_to_slft($img) {
        $img['w'] = $img['metadata']['dimensions']['width'];
        $img['h'] = $img['metadata']['dimensions']['height'];
        $img['mime'] = $img['mimeType'];
        /*hotspot: {
        height: 0.44,
        width: 0.65,
        x: 0.43,
        y: 0.26
        }*/
        if (isset($img['hotspot'])) {
            $img['fp'] = [$img['hotspot']['x'], $img['hotspot']['y']];
        }
        return $img;
    }

    static public function sanity_resize($img, $opts) {
        print_r($opts);
        $params = ['q' => 90];
        if ($opts['w']) {
            $params['w'] = $opts['w'];
        }
        if ($opts['h']) {
            $params['h'] = $opts['h'];
        }
        return $img['url'] . '?' . http_build_query($params);
    }
}






/*
$sl could be
- a sanity#link object
- a sanity#nav_item
 */
function xsanity_link($sl, $opts = [], $ds) {
    $link = $sl['link'];
    if (!$link) {
        $link = $sl;
    }

    #print_r($link);
    $url = sanity_link_url($link, $ds);

    $text = $opts['text'] ?: $sl['text'];
    if (!$text) {
        if ($link['internal']) {
            $internal = $ds->ref($link['internal']);
            $text = $internal['title'];
        } else {
            $text = $url;
        }
    }
    return sprintf('<a href="%s">%s</a>', $url, $text);
}

function xsanity_link_url($link, $ds) {
    var_dump($link);
    return $link['internal'] ? $ds->get_path($link['internal']['_ref']) : ($link['route'] ? path_page($link['route']) : $link['external']);
}
