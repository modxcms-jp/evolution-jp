<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('new_template')) {
	$e->setError(3);
	$e->dumpError();
}
$id=$_GET['id'];
if( !preg_match('/^[0-9]+\z/',$id) )
{
	echo 'Value of $id is invalid.';
	exit;
}

// duplicate template
$tpl = $_lang['duplicate_title_string'];
$tbl_site_templates = $modx->getFullTableName('site_templates');
$sql = "INSERT INTO {$tbl_site_templates} (templatename, description, content, category, parent)
		SELECT REPLACE('{$tpl}','[+title+]',templatename) AS 'templatename', description, content, category, parent
		FROM {$tbl_site_templates} WHERE id={$id}";
$rs = $modx->db->query($sql);

if($rs) {
	$newid = $modx->db->getInsertId(); // get new id
	// duplicate TV values
	$tbl_site_tmplvar_templates = $modx->getFullTableName('site_tmplvar_templates');
	$tvs = $modx->db->select('*', $tbl_site_tmplvar_templates, 'templateid='.$id);
	if ($modx->db->getRecordCount($tvs) > 0)
	{
		while ($row = $modx->db->getRow($tvs))
		{
			$row['templateid'] = $newid;
			$modx->db->insert($row, $tbl_site_tmplvar_templates);
		}
	}
} else {
	echo "A database error occured while trying to duplicate variable: <br /><br />".$modx->db->getLastError();
	exit;
}

// finish duplicating - redirect to new template
header("Location: index.php?a=16&id=$newid");
