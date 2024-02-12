<?php

namespace slowfoot\loader;

use Exception;
use slowfoot\configuration;
use OviDigital\JsObjectToJson\JsConverter;

class csv extends file {

    public function __construct(
        public string $file,
        public string $separator = ',',
        public string $enclosure = '"',
        public bool $json = false,
        public bool $jsol = false
    ) {
        if ($jsol && !class_exists(JsConverter::class)) {
            throw new Exception("JsConverter class is missing. please install with: composer require ovidigital/js-object-to-json");
        }
    }
    public function __invoke(configuration $config) {
        $file = $config->base . '/' . $this->file;
        $header = null;
        foreach (file($file) as $row) {
            if (is_null($header)) {
                $header = str_getcsv($row, $this->separator, $this->enclosure);
                print_r($header);
                continue;
            }
            $data = str_getcsv($row, $this->separator, $this->enclosure);

            if ($this->json) {
                $data = array_map(fn ($val) => json_decode($val, true), $data);
            }
            if ($this->jsol) {
                $data = array_map(function ($val) {
                    if ($val[0] == '[' || $val[0] == '{') {
                        return json_decode(JsConverter::convertToJson($val), true);
                    } else {
                        return $val;
                    }
                }, $data);
            }
            //print_r($data);
            //return [];
            yield array_combine($header, $data);
        }
    }
}
