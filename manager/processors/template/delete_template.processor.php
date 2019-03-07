<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('delete_template')) {
	$e->setError(3);
	$e->dumpError();
}
$id= (int)$_GET['id'];
$tbl_site_content           = $modx->getFullTableName('site_content');
$tbl_site_templates         = $modx->getFullTableName('site_templates');
$tbl_site_tmplvar_templates = $modx->getFullTableName('site_tmplvar_templates');

// delete the template, but first check it doesn't have any documents using it
$rs = $modx->db->select('id, pagetitle',$tbl_site_content,"template='{$id}' and deleted=0");
$limit = $modx->db->getRecordCount($rs);
if($limit>0) {
	echo "This template is in use. Please set the documents using the template to another template. Documents using this template:<br />";
	for ($i=0;$i<$limit;$i++) {
		$row = $modx->db->getRow($rs);
		echo $row['id']." - ".$row['pagetitle']."<br />\n";
	}
	exit;
}

if($id==$default_template) {
	echo "This template is set as the default template. Please choose a different default template in the MODx configuration before deleting this template.<br />";
	exit;
}

// invoke OnBeforeTempFormDelete event
$tmp = array('id' => $id);
$modx->invokeEvent('OnBeforeTempFormDelete',$tmp);
						
//ok, delete the document.
$rs = $modx->db->delete($tbl_site_templates,"id='{$id}'");
if(!$rs)
{
	echo "Something went wrong while trying to delete the template...";
	exit;
}

$rs = $modx->db->delete($tbl_site_tmplvar_templates,"templateid='{$id}'");

// invoke OnTempFormDelete event
$tmp = array('id' => $id);
$modx->invokeEvent('OnTempFormDelete',$tmp);

// empty cache
$modx->clearCache();

header('Location: index.php?a=76');
