<?php
define('SLOWFOOT_PREVIEW', true);
define('PATH_PREFIX', $_SERVER['SCRIPT_NAME']);

require __DIR__ . '/boot.php';

//phpinfo();
list($id, $type) = explode('/', trim($_SERVER['PATH_INFO'], '/'));

if ($id == 'css' || $id == 'js') {
    send_file($src, $_SERVER['PATH_INFO']);
    exit;
}
dbg('preview', $id, $type);

$obj = load_preview_object($id, $type, $config);

$template = $templates[$obj['_type']]['_']['template'];
dbg('template', $template, $obj);
$content = template($template, ['page' => $obj], $template_helper, $src);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: text/html');

print $content;
