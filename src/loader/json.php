<?php

namespace slowfoot\loader;

use slowfoot\configuration;

class json extends file {

    public function __invoke(configuration $config) {
        $file = $config->base . '/' . $this->file;
        $rows = json_decode(file_get_contents($file), true);
        if (is_assoc($rows)) {
            $rows = [$rows];
        }
        foreach ($rows as $row) {
            yield $row;
        }
        return;
    }
}
