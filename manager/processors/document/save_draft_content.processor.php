<?php
// 128
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

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

$fields = $modx->doc->fixTvNest('ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes',$_POST);
$fields = $modx->doc->fixPubStatus($fields);

if($_POST['stay']==='save_standby')
	$rs = $modx->revision->save($docid,$fields,'standby');
else
	$rs = $modx->revision->save($docid,$fields,'draft');

switch($_POST['stay'])
{
	case 'new'          : $header = "Location: index.php?a=131&id={$docid}&r=1";   break;
	case 'stay'         : $header = "Location: index.php?a=131&id={$docid}&stay=stay";break;
	case 'save_draft'   :
	case 'save_standby' : $header = "Location: index.php?a=133&id={$docid}&r=1";   break;
	default:              $header = "Location: index.php?a=3&id={$docid}&r=1";
}
header($header);
