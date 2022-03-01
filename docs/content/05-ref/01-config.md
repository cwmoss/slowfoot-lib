---
chapter_title: Reference
title: Configuration Options
---

```php
<?php
return [
    'site_name' => 'slowfoot Documentation',
    'site_description' => 'Docs for slowfoot',
    'site_url' => '',
    'path_prefix' => getenv('PATH_PREFIX') ?: $_ENV['PATH_PREFIX'] ?: '',
    'title_template' => '',
    //'store' => 'sqlite',
    'sources' => [
       
       'markdown' => [
            'file' => 'content/**/*.md',
            'type' => 'chapter'
       ],
    ],
    'templates' => [
        'chapter' => '/:_file.name',
    ]
];
```

## site_name

Name

## path_prefix

prefix, if not root