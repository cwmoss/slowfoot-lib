<?php
$name = "otto";
if(!$title) $title="mr";

// require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/template.php';

?>

<h1>hello <?=$title?> <?=$name?></h1>

<p>now is <?=date("Y-m-d H:i:s")?></p>
