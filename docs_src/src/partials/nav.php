<?php
//$chapters = $query('chapter() order(_file.path)');
debug_js("current", $current);
$chapters = $get('chapter_index');

$current_section = $current['dir']?basename($current['dir']):basename($chapters[0]['_file']['dir']);

debug_js('chapters', $chapters);

?>
<nav>
    
<?foreach ($chapters['index'] as $sid => $section) {
    $open = $sid==$current_section?'open':'';
    $active = $sid==$current_section?'active':''; ?>

    <details <?=$open?> class="<?=$active?>">
    <summary><?=$section['title']?></summary>
    <?foreach ($section['c'] as $chapter) {
        $active = $chapter['_id'] == $current_id?'active':''; ?>
        <a href="<?=$path($chapter)?>" class="<?=$active?>"><?=$chapter['title']?></a>
    <?php
    } ?>    
    </details>
<?php
}?>


</nav>