<?php

layout("default");
?>
<article>
    <h1><?=$page['title']?></h1>
<?=$markdown($page['mdbody'])?>
</article>