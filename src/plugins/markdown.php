<?php

namespace slowfoot\plugins;

use slowfoot\hook;
use slowfoot\configuration;

use Mni\FrontYAML\Parser;
use ParsedownToC;

class markdown {

    public function __construct(
        public string $file,

    ) {
    }

    public function init() {

        hook::add('bind_template_helper', function ($ds, $src, $config) {
            return ['markdown', $this->markdown_helper($config, $ds)];
        });

        hook::add('bind_late_template_helper', function ($helper, $base, $data) {
            $md = $helper['markdown'];
            return ['markdown', $this->markdown_helper_obj($md, $data)];
        });
    }

    static public function data_loader(configuration $config) {
        $me = $config->get_plugin(self::class);
        $front = new Parser;
        $filep = $config['base'] . '/' . $me->file;
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

    public function markdown_sf($md, $config = null, $ds = null) {
        $parser = new markdown_sfp();
        $parser->set_context(['conf' => $config, 'ds' => $ds]);
        //$parser->setUrlsLinked(false);
        return $parser->text($md);
    }

    public function markdown_toc($content) {
        $Parsedown = new ParsedownToC();
        $body = $Parsedown->body($content);
        $toc  = $Parsedown->contentsList();
        dbg("+++ toc", $toc);
        return $toc;
    }

    public function markdown_parser($config, $ds) {
        $parser = new markdown_sfp();
        $parser->set_context(['conf' => $config, 'ds' => $ds]);
        return $parser;
    }

    public function markdown_helper($config = null, $ds = null) {
        $parser = $this->markdown_parser($config, $ds);

        return function ($text, $obj = null) use ($parser) {
            $parser->set_current_obj($obj);
            return $parser->text($text);
        };
    }

    public function markdown_helper_obj($parser, $data = null) {
        #var_dump($data);
        return function ($text) use ($parser, $data) {
            return $parser($text, $data);
        };
    }
}
