<?php
// 128
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

$modx->manager->saveFormValues(4);

$modx->loadExtension('REVISION');
$modx->loadExtension('DocAPI');

$fields = $modx->doc->fixTvNest('ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes',$_POST);
$fields = $modx->doc->convertPubStatus($fields);
$docid = $_POST['id'];
$rs = $modx->revision->save($docid,$fields,'draft');

if($_POST['stay']==='2')
	$header = "Location: index.php?a=131&id={$docid}&r=1&stay=2";
elseif($rs==='new')
	$header = "Location: index.php?a=131&id={$docid}&r=1";
else
	$header = "Location: index.php?a=3&id={$docid}&r=1";

header($header);
exit;
