<?php

namespace slowfoot\util;

use Closure;
use JsonSerializable;

/*
    reset all stores
        collect_data();

    reset store "meta"
        collect_data('meta');

    init store "meta"
        collect_data('meta', ['title' => 'blog // 2022', 'og:title' => 'blog year 2022'], true);

    add or update store "meta" title='blog // 2022'
        collect_data('meta', 'title', 'blog // 2022');

    add or update store "meta" title and og:title
        collect_data('meta', ['title' => 'blog // 2022', 'og:title' => 'blog year 2022']);

    get all data from store
        collect_data('meta', true);

*/

class html {
    static public function collect_data($storename = null, $data = null, $val = null) {
        static $store = [];
        if (is_null($storename)) {
            $store = [];
            return;
        }
        if (is_array($data) && $val === true) {
            $store[$storename] = $data;
            return;
        }

        if (is_null($data) && is_null($val)) {
            $store[$storename] = [];
            return;
        }

        if ($data === true) {
            return $store[$storename];
        }
        if (!isset($store[$storename])) {
            $store[$storename] = [];
        }
        if (!is_array($data)) {
            $data = [$data => $val];
        }
        $store[$storename] = array_merge($store[$storename], $data);
    }

    static public function meta_tags($data) {
        $image = $data['image'] ?? '';
        $site_name = $data['site_name'] ?? null;
        $color = $data['color'] ?? null;
        $pubdate = $data['pubdate'] ?? null;

        $meta = ['title' => $data['title'] ?? '', 'description' => $data['description'] ?? ''];

        $meta['og:site_name'] = $data['og:site_name'] ?? ($site_name ?: $meta['title']);
        $meta['og:url'] = $data['url'] ?? null;
        $meta['og:title'] = $data['og:title'] ?? $meta['title'];
        $meta['og:image'] = $data['og:image'] ?? $image;
        $meta['og:type'] = $data['og:type'] ?? 'website';
        $meta['og:locale'] = $data['og:locale'] ?? null;
        $meta['theme-color'] = $data['theme-color'] ?? $color;
        if (isset($data['twitter:site'])) {
            $meta['twitter:site'] = $data['twitter:site'];
            $meta['twitter:card'] = $data['twitter:card'] ?? 'summary_large_image';
            $meta['twitter:image'] = $data['twitter:image'] ?? $image;
        }
        $meta['article:published_time'] = $data['article:published_time'] ?? $pubdate;
        return self::html_meta_tags($meta);
    }

    static public function html_meta_tags($meta) {
        $tags = [];
        foreach ($meta as $key => $value) {
            if (empty($key) || empty($value)) {
                continue;
            }
            if ($key == 'title') {
                $tags[] = self::html_tag('title', null, null, $value);
                continue;
            }
            $value = (array) $value;
            if (preg_match('/^twitter/', $key)) {
                $attr = 'name';
            } elseif (preg_match('/:/', $key)) {
                $attr = 'property';
            } else {
                $attr = 'name';
            }
            foreach ($value as $v) {
                $v = html_entity_decode($v, null, 'UTF-8');
                $tags[] = self::html_tag('meta', [$attr => $key, 'content' => $v]);
            }
        }
        return join("\n", $tags);
    }

    static public function html_arributes($attrs, $prefix = null) {
        if (!$attrs) {
            return "";
        }

        $html = array_map(
            function ($val, $key) use ($prefix) {
                if ($prefix) {
                    $key = sprintf("%s-%s", $prefix, $key);
                }
                if (is_bool($val)) {
                    return ($val ? $key : '');
                } elseif (isset($val)) {
                    if ($val instanceof Closure) {
                        $val = $val();
                    } elseif ($val instanceof JsonSerializable) {
                        $val = json_encode(
                            $val->jsonSerialize(),
                            (\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)
                        );
                    } elseif (is_callable([$val, 'toArray'])) {
                        $val = $val->toArray();
                    } elseif (is_callable([$val, '__toString'])) {
                        $val = strval($val);
                    }

                    if (is_array($val)) {
                        $val = join(' ', array_filter($val, function ($var) {
                            return !empty($var) || is_numeric($var);
                        }));
                    }

                    $val = htmlspecialchars($val, \ENT_QUOTES);

                    if (is_string($val)) {
                        return sprintf('%s="%s"', $key, $val);
                    }
                }
            },
            $attrs,
            array_keys($attrs)
        );

        return join(" ", $html);
    }

    static public function html_tag($tag, $attrs = [], $data = [], $content = false) {
        $htmlattrs = trim(
            self::html_arributes($attrs) .
                self::html_arributes($data, 'data')
        );

        if ($content === false) {
            return sprintf(
                "<%s%s%s>",
                $tag,
                ($htmlattrs ? ' ' : ''),
                $htmlattrs
            );
        }
        return sprintf(
            "<%s%s%s>%s</%s>",
            $tag,
            ($htmlattrs ? ' ' : ''),
            $htmlattrs,
            htmlspecialchars($content),
            $tag
        );
    }
}
