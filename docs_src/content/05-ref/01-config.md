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
    // prefix path of your destination
    // ex: https://acme.com/the-new-snake-oil-is-here
    //   => path_prefix = '/the-new-snake-oil-is-here
    'path_prefix' => '',
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