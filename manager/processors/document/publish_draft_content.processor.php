<?php
// 128 / 129
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

$modx->loadExtension('REVISION');
$rs = $modx->revision->publishDraft($_POST);
if(!$rs) exit('false');

$header = sprintf('Location: index.php?a=3&id=%s&r=1', $_POST['id']);
header($header);
exit;
