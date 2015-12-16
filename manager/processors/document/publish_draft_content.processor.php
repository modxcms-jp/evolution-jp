<?php
// 129
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

//print_r($_GET);print_r($_POST);exit;
if(isset($_POST['id']) && preg_match('@^[1-9][0-9]*$@',$_POST['id']))
	$docid = $_POST['id'];
elseif(isset($_GET['id']) && preg_match('@^[1-9][0-9]*$@',$_GET['id']))
	$docid = $_GET['id'];
else {
	$e->setError(2);
	$e->dumpError();
}

$modx->loadExtension('REVISION');
$modx->loadExtension('DocAPI');

if($_POST['publishoption']==='reserve' && !empty($_POST['pub_date']))
{
	$pub_date = $modx->toTimeStamp($_POST['pub_date']);
	if($_SERVER['REQUEST_TIME'] < $pub_date)
		setStandBy($docid, $pub_date);
	else publishDraft($docid);
}
else publishDraft($docid);

//$modx->clearCache();
$header = sprintf('Location: index.php?a=3&id=%s&r=1', $docid);
header($header);
exit;



function setStandBy($docid, $pub_date) {
    global $modx;
    
	$f['pub_date'] = $pub_date;
	$f['status']   = 'standby';
	$modx->db->update($f, '[+prefix+]site_revision', "elmid='{$docid}'");
	$modx->setCacheRefreshTime($pub_date);
	return 'set_standby';
}

function publishDraft($docid) {
    global $modx;
    
    $rs = $modx->db->select('*','[+prefix+]site_content',"id='{$docid}'");
    $documentObject = $modx->db->getRow($rs);
    $draft = $modx->revision->getDraft($docid);
    $draft['published'] = $documentObject['published'];
    $modx->doc->update($draft,$docid);
    $modx->db->delete('[+prefix+]site_revision', "( status='draft' OR status='standby' ) AND elmid='{$docid}'");

	$modx->clearCache();
	$tmp = array('docid'=>$docid,'type'=>'draftManual');
	$modx->invokeEvent('OnDocPublished',$tmp); // invoke OnDocPublished  event

    return 'publish_draft';
}
