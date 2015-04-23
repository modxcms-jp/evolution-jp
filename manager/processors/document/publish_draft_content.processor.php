<?php
// 128 / 129
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

$modx->loadExtension('REVISION');
$modx->loadExtension('DocAPI');

$docid = $_POST['id'];

$fields = $modx->doc->fix_tv_nest('ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes',$_POST);
$fields = $modx->doc->convertPubStatus($fields);

if(time() < $fields['pub_date'])
{
	$modx->revision->save($docid,$fields,'standby');
	
	$f = array('pub_date' => $fields['pub_date']);
	$modx->db->update($f,'[+prefix+]site_revision',"elmid='{$docid}'");
	$modx->setCacheRefreshTime($fields['pub_date']);
}
else
{
	$fields = $modx->db->escape($fields);
	$rs = $modx->doc->update($fields, $docid);
	$modx->revision->delete($docid, 'draft');
}

$header = "Location: index.php?a=3&id={$docid}&r=1";
header($header);
exit;
