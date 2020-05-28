<?php
// 128
if(!isset($modx) || !$modx->isLoggedin()) exit;

if(!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

if(isset($_POST['id']) && preg_match('@^[1-9][0-9]*$@',$_POST['id']))
	$docid = $_POST['id'];
else {
	$e->setError(2);
	$e->dumpError();
}

$modx->manager->saveFormValues(4);

$modx->loadExtension('REVISION');
$modx->loadExtension('DocAPI');

$fields = $modx->doc->fixTvNest($_POST);
$fields = $modx->doc->fixPubStatus($fields);

if(postv('stay')==='save_standby') {
    $rs = $modx->revision->save($docid, $fields, 'standby');
} else {
    $rs = $modx->revision->save($docid, $fields, 'draft');
}

$i = postv('stay');
if ($i === 'new') {
    $header = sprintf('Location: index.php?a=131&id=%s&r=1', $docid);
} elseif ($i === 'stay') {
    $header = sprintf('Location: index.php?a=131&id=%s&stay=stay', $docid);
} elseif ($i === 'save_draft' || $i === 'save_standby') {
    $header = sprintf('Location: index.php?a=133&id=%s&r=1', $docid);
} else {
    $header = sprintf('Location: index.php?a=3&id=%s&r=1', $docid);
}
header($header);
