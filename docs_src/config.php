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
       'chapter_index' => null
    ],
    'templates' => [
        'chapter' => '/:_file.name',
    ],
    'plugins' => [
        'markdown'
    ]
];

function load_chapter_index($opts, $conf, $db)
{
    $chapters = $db->query('chapter() order(_file.path)');
    //$current_section = $current['dir']?basename($current['dir']):basename($chapters[0]['_file']['dir']);

    $chapters = array_reduce($chapters, function ($res, $chapter) {
        $sid = basename($chapter['_file']['dir']);
        if (!isset($res[$sid])) {
            $res[$sid] = [
                'sid' => $sid,
                'title' => $chapter['chapter_title']??$sid,
                'active' => $sid==$current_section,
                'c'=>[$chapter]];
        } else {
            $res[$sid]['c'][] = $chapter;
        }
        return $res;
    }, []);
    yield ['_id'=>'chapter_index', 'index'=>$chapters];
    return;
}
