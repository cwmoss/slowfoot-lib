<?php

namespace slowfoot\loader;

use slowfoot\configuration;

class dataset extends file {

    public function __invoke(configuration $config) {
        $file = $config->base . '/' . $this->file;
        foreach (file($file) as $row) {
            yield json_decode($row, true);
        }
        return;
    }
}
