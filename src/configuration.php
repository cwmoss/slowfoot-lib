<?php

namespace slowfoot;

require_once 'lolql.php';
require_once 'store.php';
require_once 'store_memory.php';
require_once 'store_sqlite.php';
require_once 'JsConverter.php';
require_once 'template.php';
require_once 'hook.php';


use slowfoot\store;
use slowfoot\store\memory;
use slowfoot\store\sqlite;
use slowfoot\loader;

use Ovidigital\JsObjectToJson\JsConverter;
use slowfoot\template;

use function lolql\parse;
use function lolql\query as lquery;

/*

https://github.com/paquettg/php-html-parser
https://github.com/Masterminds/html5-php

if (! function_exists(__NAMESPACE__ . '\greetings'))

'site_name' => 'mumok Demo',
    'site_description' => 'look at beautiful works of art',
    'site_url' => '',
    // TODO: solve genenv vs ENV problem
    'path_prefix' => getenv('PATH_PREFIX') ?: $_ENV['PATH_PREFIX'] ?: '',
    'title_template' => '',
*/

class configuration {

    public string $base;
    public string $src;
    public string $dist;

    public function __construct(
        public string $site_name = "",
        public string $site_url = "",
        public string $site_description = "",
        public string $path_prefix = "",
        public string $title_template = "",
        public array $sources = [],
        public array $templates = [],
        public string|array $store = 'sqlite',
        public array $hooks = [],
        public array $assets = [],
        public array $plugins = [],
        public array $preview = [],
        public string|array $build = ['dist' => 'dist'],
    ) {
    }
    static function load($dir): self {
        $conf = include $dir . '/configuration.php';
        $conf->base = '/' . get_absolute_path($dir);
        $conf->src = $conf->base . '/src';
        $conf->dist = $conf->base . '/dist';
        $conf->init();
        return $conf;
    }

    public function init() {
        foreach ($this->templates as $name => $t) {
            $this->templates[$name] = $this->normalize_template_config($name, $t);
        }
        $this->assets = $this->normalize_assets_config($this->assets);
        $this->store = $this->normalize_store_config();
        // $this->plugins = $this->normalize_plugins_config();
        $this->build = $this->normalize_build_config($this->build);
    }

    public function get_loader() {
        return new loader($this);
    }
    public function get_store(): store {
        if (strpos($this->store['adapter'], 'sqlite') === 0) {
            $db = new sqlite($this->store);
        } else {
            $db = new memory();
        }
        return new store($db, $this->templates);
    }
    public function get_template_engine(): template {
        return new template($this);
    }

    // TODO:
    //  require in global context?
    //  do wee need a plugin init /w pconf?
    //  plugin via composer?
    //  raise error?
    function normalize_plugins_config($conf) {
        $plugins = $conf['plugins'] ?? [];
        $norm = [];
        foreach ($plugins as $k => $pconf) {
            $name = is_string($pconf) ? $pconf : (!is_numeric($k) ? $k : null);
            if (!$name) {
                continue;
            }
            $pfile = $name . '.php';
            if (file_exists($conf['src'] . '/plugins/' . $pfile)) {
                $fullname = $conf['src'] . '/plugins/' . $pfile;
            } else {
                if (file_exists(__DIR__ . '/plugins/' . $pfile)) {
                    $fullname = __DIR__ . '/plugins/' . $pfile;
                } else {
                    continue;
                }
            }
            $norm[$name] = [
                'filename' => $pfile,
                'fullpath' => $fullname,
                'conf' => is_array($pconf) ? $pconf : []
            ];
            require_once($fullname);
        }
        return $norm;
    }

    function normalize_template_config($name, $config) {
        if (!is_array($config) || is_assoc($config)) {
            $config = [$config];
        }
        $tpl = [];
        foreach ($config as $t) {
            if (!is_array($t)) {
                $t = ['path' => $t];
            }
            if (is_string($t['path'])) {
                $t['path'] = make_path_fn($t['path']);
            }
            $subname = $t['name'] ?? '_';
            $tpl[$subname] = array_merge(['type' => $name, 'template' => $name, 'name' => $subname], $t);
        }
        return $tpl;
    }

    function normalize_store_config() {
        $store = ['adapter' => $this->store];
        $store['base'] = $this->base . '/var';
        return $store;
    }

    function normalize_assets_config(array $assets): array {
        $default = [
            'base' => $this->base,
            'path' => '/images',
            'src' => '',
            'dest' => 'var/rendered-images',
            'profiles' => [],
            'map' => function ($img) {
                return hook::invoke_filter('assets_map', $img);
            }
        ];
        $assets = array_merge($default, $assets);
        return $assets;
    }
    function normalize_build_config(string|array $build): array {
        if (is_string($build)) {
            $build = ['dist' => $build];
        }
        #if($build['dist'][0]!='/'){
        $build['dist'] = $this->base . '/' . $build['dist'];
        #}
        return $build;
    }
}

function make_path_fn($pattern) {
    $replacements = [];
    if (preg_match_all('!:([^/:]+)!', $pattern, $mat, PREG_SET_ORDER)) {
        $replacements = $mat;
    }
    $replacements = array_map(fn ($r) => [$r[0], explode('.', $r[1])], $replacements);
    // print_r($replacements);
    // exit;
    return function ($item) use ($pattern, $replacements) {
        $path = $pattern;
        // $item[$r[1]]
        $replacements = array_map(fn ($r) => [$r[0], url_safe(resolve_dot_value($r[1], $item))], $replacements);
        $path = str_replace(
            array_column($replacements, 0),
            array_column($replacements, 1),
            $path
        );
        return $path;
    };
}
function resolve_dot_value($keys, $data) {
    if (!$data) {
        return null;
    }
    $current = array_shift($keys);

    // nested?
    if ($keys) {
        return resolve_dot_value($keys, $data[$current]);
    }

    if (!is_assoc($data)) {
        return array_column($data, $current);
    } else {
        return $data[$current];
    }
}
function url_safe($path) {
    // TODO
    // https://gist.github.com/jaywilliams/119517
    $path = str_replace([' '], ['-'], $path);
    $path = strtolower($path);
    return $path;
}
