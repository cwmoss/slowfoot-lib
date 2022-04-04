<?php

use Sanity\BlockContent;
use Sanity\Client as SanityClient;

hook::add('bind_template_helper', function ($ds, $src, $config) {
    return ['sanity_text', function ($text, $opts = []) use ($ds, $config) {
        //print_r($sl);
        return sanity_text($text, $ds, $config);
    }];
});
hook::add_filter('assets_map', function ($img) {
    // sanity images
    if ($img['_type'] == 'sanity.imageAsset') {
        return sanity_image_to_slft($img);
    }
    return $img;
});

function load_preview_object($id, $type = null, $config)
{
    // print_r($config);
    //print_r(apache_request_headers());
    //print_r($_COOKIE);
    $client = sanity_client($config['preview']['sanity']);

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

function load_sanity($opts, $config, $store)
{
    $client = sanity_client($opts);
    $query = $opts['query'] ?? '*[!(_type match "system.*") && !(_id in path("drafts.**"))]';
    #print "\n".$query."\n";
    $res = $client->fetch($query);
    // falls mehrteilige query => flach machen
    if ($res && !isset($res[0])) {
        return array_merge(...array_values($res));
    }
    
    return array_values($res);
}

function sanity_client($config)
{
    return new SanityClient($config);
}

/*
$sl could be
- a sanity#link object
- a sanity#nav_item
 */
function xsanity_link($sl, $opts = [], $ds)
{
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

function xsanity_link_url($link, $ds)
{
    var_dump($link);
    return $link['internal'] ? $ds->get_path($link['internal']['_ref']) : ($link['route'] ? path_page($link['route']) : $link['external']);
}

function sanity_text($block, $ds, $config)
{
    $conf = $config['sources']['sanity'];
    #var_dump($conf);
    $serializer = hook::invoke_filter('sanity.block_serializers', [], $ds, $config);
    #var_dump($serializer);

    $html = BlockContent::toHtml($block, [
        'projectId' => $conf['projectId'],
        'dataset' => $conf['dataset'],
        'serializers' => $serializer,
    ]);
    return nl2br($html);
}

function sanity_image_to_slft($img)
{
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

function sanity_resize($img, $opts)
{
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
