<?php

namespace slowfoot;

class context {

    public function __construct(
        public string $name = '',
        public string $mode = 'build',
        public string $template_type = 'page',
        public string $src = '',
        public string $path = '',
        public bool $is_template = false,
        public bool $is_page = true,
        public configuration $config
    ) {
    }

    /*
        clones and then updates the context
    */
    public function with(string|array $key, mixed $value = null): self {
        $context = clone ($this);
        if (is_string($key)) {
            $key = [$key => $value];
        }
        foreach ($key as $k => $v) {
            $context->$k = $v;
        }
        return $context;
    }
}
