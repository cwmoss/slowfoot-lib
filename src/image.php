<?php

namespace slowfoot;

/*

    size
    500x400
    500x
    x400
    "" (empty) => only copy, don't resize

    mode
    - fit, fit image to max constraints => whole image visible
    - fill, crop if aspect-ratio needs to, use fp (focalpoint if available)

    cache: rendered-images
*/
function image($img, $opts = [], $gopts = [])
{
    static $resizer;
    if (!$resizer) {
        $resizer = get_resizer($gopts['base']);
    }

    $profile = get_profile($opts, $gopts['profiles']);

    // local images, die direkt genutzt werden
    if (\is_string($img)) {
        $img = asset_from_file($img, $gopts);
        dbg("[image] asset from file", $img);
        if (!$img) {
            return;
        }
    }

    if ($img['_type']!='slft.asset') {
        if (!isset($gopts['map'])) {
            return;
        }
        $img = $gopts['map']($img);
    }
    
    dbg("[image] start", $img['url'], $profile);

    if (is_remote($img['url'])) {
        $img['remote_src'] = true;

        if ($gopts['download']) {
            $dl_name = $img['_id'];
            $download_file = $gopts['base'].'/var/download/'.$dl_name;

            dbg("[image] download to ", $download_file);

            $resd = download_remote_image($img['url'], $download_file);
            if ($resd[0]) {
                $img['download_file'] = $download_file; #'/download/'.$dl_name;
            } else {
                return "";
            }
        } else {
            if (isset($gopts['resize_cdn'])) {
                $cdn_url = $gopts['resize_cdn']($img, $profile);
                $img['resize_url'] = $cdn_url;
                dbg("[image] resize_cdn", $cdn_url);

                return $img;
            }
        }
    }

    $name = get_name($img['url'], $profile);
    $dest = $gopts['base'] . '/' . $gopts['dest'] . '/' . $name;

    $src = $img['download_file']?:$img['_id'];

    dbg("[image] resizer", $src, $dest);
    $res = resize($resizer, $src, $dest, $profile, $gopts['base']);
    if ($profile['4c']) {
        set_tags($dest, $profile);
    }
    dbg('[image] finished', $res);
    $img['resize_url'] = $name;
    $img['resize'] = $res;
    return $img;
    // return $opts['image_prefix'] . '/' . $name;
}

// <img> tag
function image_tag($img, $opts = [], $tagopts=[], $gopts = [])
{
    $resize = image($img, $opts, $gopts);
    if (!$resize) {
        return "";
    }

    if (is_remote($resize['resize_url'])) {
        return html_cdn($resize, $gopts, $tagopts);
    } else {
        return html($resize, $gopts, $tagopts);
    }
}

function image_url($img, $opts = [], $gopts = [])
{
    $resize = image($img, $opts, $gopts);
    if (!$resize) {
        return "";
    }
    
    return prefixed_url($resize['resize_url'], $gopts);
}

function prefixed_url($url, $opts=[])
{
    $url = PATH_PREFIX. $opts['path'] . '/' .$url;
    return $url;
}

/*
    set most important iptc/exif data
    Caption/description, Creator, Copyright Notice, Credit Line (= the 4Cs)
    https://www.iptc.org/std/photometadata/documentation/userguide/#_rights_information
    https://iptc.org/standards/photo-metadata/social-media-sites-photo-metadata-test-results-2019/
    https://de.wikipedia.org/wiki/IPTC-IIM-Standard
*/
function set_tags($dest, $profile)
{
    $tags = [
        'caption' => '2#120',
        'creator' => '2#80',
        'copyright' => '2#116',
        'credit' => '2#110',
    ];
    $caption = $profile['4c']['caption']??$profile['caption']??$profile['alt'];
    $creator = $profile['4c']['creator']??$profile['author'];
    $copyright = $profile['4c']['copyright']??"© All Rights Reserved.";
    $credit = $profile['4c']['credit']??"© ".$creator;

    $utf8seq = chr(0x1b) . chr(0x25) . chr(0x47);
    $length = strlen($utf8seq);
    $data = chr(0x1C) . chr(1) . chr('090') . chr($length >> 8) . chr($length & 0xFF) . $utf8seq;
    foreach ($tags as $tname=>$tcode) {
        if ($$tname) {
            $tag = substr($tcode, 2);
            $data .= iptc_make_tag(2, $tag, $$tname);
        }
    }
    $content = iptcembed($data, $dest);
    if ($content!==false) {
        $fp = fopen($dest, "wb");
        fwrite($fp, $content);
        fclose($fp);
    }
}


function iptc_make_tag($rec, $data, $value)
{
    $length = strlen($value);
    $retval = chr(0x1C) . chr($rec) . chr($data);

    if ($length < 0x8000) {
        $retval .= chr($length >> 8) .  chr($length & 0xFF);
    } else {
        $retval .= chr(0x80) .
                   chr(0x04) .
                   chr(($length >> 24) & 0xFF) .
                   chr(($length >> 16) & 0xFF) .
                   chr(($length >> 8) & 0xFF) .
                   chr($length & 0xFF);
    }

    return $retval . $value;
}

function get_resizer($base)
{
    // The internal adapter
    $adapter = new \League\Flysystem\InMemory\InMemoryFilesystemAdapter();

    // The FilesystemOperator
    $cache = new \League\Flysystem\Filesystem($adapter);
    dbg("+++ glide src path", $base);
    $server = \League\Glide\ServerFactory::create([
        'source' => $base,
        'cache' => $cache,
        //'source_path_prefix' => '/'
    ]);
    return [$server, $cache];
}

function asset_from_file($path, $gopts)
{
    //var_dump($gopts);
    $fname = $gopts['base'] . '/' . $gopts['src'] . '/' . $path;
    $info = \getimagesize($fname);
    if (!$info) {
        return null;
    }
    return [
        '_type' => 'slft.asset',
        '_id' => $path,
        '_src' => $gopts['src'],
        'url' => $fname,
        'path' => $fname,
        'w' => $info[0],
        'h' => $info[1],
        'mime' => $info['mime']
    ];
}

function html($res, $opts, $tagopts)
{
    //print_r($opts);
    return sprintf(
        '<img src="%s" width="%s" xheight="%s" alt="%s" loading="lazy" class="%s">',
        prefixed_url($res['resize_url'], $opts),
        $res['resize'][0],
        $res['resize'][1],
        \htmlspecialchars($tagopts['alt']??''),
        \htmlspecialchars($tagopts['class']??'')
    );
}

function html_cdn($res, $opts, $tagopts)
{
    return sprintf(
        '<img src="%s" alt="%s" class="%s">',
        $res['resize_url'],
        \htmlspecialchars($tagopts['alt']??''),
        \htmlspecialchars($tagopts['class']??'')
    );
}
function resize($resizer, $src, $dest, $profile, $base)
{
    #dbg('++ resize', $src, $dest, $profile);
    // copy-only
    if (isset($profile['size'])&& !$profile['size']) {
        `cp $src $dest`;
        return \getimagesize($dest);
    }

    if (\file_exists($dest)) {
        return \getimagesize($dest);
    }

    if (!\file_exists($src)) {
        dbg("+++ not exists", $src);
        return [];
    }
    
    // TODO: this should not be needed
    $src = \str_replace($base, '', $src);

    if (!$profile['w'] || !$profile['h']) {
        $new = resize_one_side($resizer[0], $src, $dest, $profile['w'], $profile['h']);
    //var_dump($new);
    } else {
        $mode = $profile['mode'];
        if (!$mode) {
            $mode = 'fit';
        }
        if ($mode == 'fill' && $profile['fp']) {
            $new = resize_fp($resizer[0], $src, $dest, $profile['w'], $profile['h'], $profile['fp']);
        } else {
            $new = resize_two_sides($resizer[0], $src, $dest, $profile['w'], $profile['h'], $mode);
        }
    }
    //var_dump($server);
    if ($new) {
        \file_put_contents($dest, $resizer[1]->read($new));
        return \getimagesize($dest);
    }
    return [];
}

function resize_one_side($resizer, $src, $dest, $w, $h)
{
    $p = $w ? ['w' => $w] : ['h' => $h];
    #dbg('+++ server', $src, $p);
    $p['q'] = 72;
    $p['sharp'] = 5;
    return $resizer->makeImage($src, $p);
}

function resize_two_sides($resizer, $src, $dest, $w, $h, $mode)
{
    $mode = $mode == 'fit' ? 'contain' : 'crop';
    $p = ['w' => $w, 'h' => $h, 'fit' => $mode];
    $p['q'] = 72;
    $p['sharp'] = 5;
    #dbg('+++ server', $src, $p);
    return $resizer->makeImage($src, $p);
}
/*
    crop with focal point
*/
function resize_fp($resizer, $src, $dest, $w, $h, $fp)
{
    $p = ['w' => $w, 'h' => $h, 'fit' => 'crop-' . round($fp[0] * 100) . '-' . round($fp[1] * 100)];
    $p['q'] = 72;
    $p['sharp'] = 5;
    #dbg('+++ server', $src, $p);
    return $resizer->makeImage($src, $p);
}

function get_profile($opts = [], $profiles = [])
{
    if (is_string($opts)) {
        $profilename = $opts;
        $opts = [];
    } else {
        $profilename = $opts['profile'];
    }
    $def = ($profilename && $profiles[$profilename]) ? $profiles[$profilename] : [];
    $profile = array_merge($def, $opts);
    list($profile['w'], $profile['h']) = explode('x', $profile['size']);
    return $profile;
}

function get_name($url, $profile)
{
    $significant = 'size mode fp 4c';
    $profile = array_intersect_key($profile, array_flip(explode(' ', $significant)));
    ksort($profile);
    $info = \pathinfo($url);
    $hash = \md5($info['filename'] . '?'. http_build_query($profile));
    if (!$profile['size']) {
        $profile['size'] = 'ypoc';
    }
    return $info['filename'] . '--' . $hash . '-' . $profile['size'] . '.' . $info['extension'];
}

function download_remote_image($url, $file_name)
{
    if (file_exists($file_name) || file_put_contents($file_name, file_get_contents($url))) {
        $info = \getimagesize($file_name);
        if (!\in_array($info['mime'], ['image/jpeg', 'image/png'])) {
            unlink($file_name);
            return [false, "Unsupported file type"];
        } else {
            return [true, $info];
        }
    }
    return [false, "Download failed"];
}

// TODO: make it more bulletproof
function is_remote($url)
{
    return (preg_match("!^https?://!", $url));
}
/*

1 Calculate the final image aspect ratio:
k=Wr/Hr,
where Wr and Hr - the width and height of the image of the future
2 Determine the maximum rectangle that will fit into the original image:
if Wr >= Hr
then Wm = Wi, Hm = Wi/k
else Hm = Hi, Wm = Hm*k,
where Wi, Hi - the size of the original, and Wm, Hm - the maximum size of the rectangle.
3 We calculate new coordinates for the focal point:
fx2 = fx*Wm/Wi,
fy2 = fy*Hm/Hi,
fx, fx - the coordinates of the focus on the original image
4 We do the actual cropping by shifting the rectangle by the difference between the old and new coordinates of the focal point:
crop(Wm, Hm, (fx-fx2), (fy-fy2))
5 Reduce the result to the desired size:
resize(Wr, Hr)
*/
