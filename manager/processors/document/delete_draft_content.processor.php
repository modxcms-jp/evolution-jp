<?php
// 128 / 129
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

$modx->loadExtension('REVISION');

$docid = $_POST['id'];
$modx->revision->delete($docid, 'draft');
$modx->revision->delete($docid, 'standby');

$header = "Location: index.php?a=3&id={$docid}&r=1";
header($header);
