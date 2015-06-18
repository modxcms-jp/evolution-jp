<?php
// 132
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$_POST['id']) exit('postid');

$header = sprintf('Location: index.php?a=131&id=%s', $_POST['id']);

header($header);
exit;
