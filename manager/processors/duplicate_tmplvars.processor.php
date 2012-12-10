<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('edit_template')) {
	$e->setError(3);
	$e->dumpError();
}
$id=$_GET['id'];

// duplicate TV
$tpl = $_lang['duplicate_title_string'];
$tbl_site_tmplvars = $modx->getFullTableName('site_tmplvars');
$sql = "INSERT INTO {$tbl_site_tmplvars} (type, name, caption, description, default_text, elements, rank, display, display_params, category)
		SELECT type, REPLACE('{$tpl}','[+title+]',name) AS 'name', caption, description, default_text, elements, rank, display, display_params, category
		FROM {$tbl_site_tmplvars} WHERE id={$id}";
$rs = $modx->db->query($sql);

if($rs) $newid = $modx->db->getInsertId(); // get new id
else {
	echo "A database error occured while trying to duplicate TV: <br /><br />".$modx->db->getLastError();
	exit;
}


// duplicate TV Template Access Permissions
$tbl_site_tmplvar_templates = $modx->getFullTableName('site_tmplvar_templates');
$sql = "INSERT INTO {$tbl_site_tmplvar_templates} (tmplvarid, templateid)
		SELECT $newid, templateid
		FROM {$tbl_site_tmplvar_templates} WHERE tmplvarid={$id}";
$rs = $modx->db->query($sql);

if (!$rs) {
	echo "A database error occured while trying to duplicate TV template access: <br /><br />".$modx->db->getLastError();
	exit;
}


// duplicate TV Access Permissions
$tbl_site_tmplvar_access = $modx->getFullTableName('site_tmplvar_access');
$sql = "INSERT INTO {$tbl_site_tmplvar_access} (tmplvarid, documentgroup)
		SELECT $newid, documentgroup
		FROM {$tbl_site_tmplvar_access} WHERE tmplvarid={$id}";
$rs = $modx->db->query($sql);

if (!$rs) {
	echo "A database error occured while trying to duplicate TV Acess Permissions: <br /><br />".$modx->db->getLastError();
	exit;
}

// finish duplicating - redirect to new variable
header("Location: index.php?a=301&id=$newid");
