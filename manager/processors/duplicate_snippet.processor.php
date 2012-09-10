<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('new_snippet')) {
	$e->setError(3);
	$e->dumpError();
}
$id=$_GET['id'];

// duplicate Snippet
$tbl_site_snippets = $modx->getFullTableName('site_snippets');
$tpl = $_lang['duplicate_title_string'];
$sql = "INSERT INTO {$tbl_site_snippets} (name, description, snippet, properties, category)
		SELECT REPLACE('{$tpl}','[+title+]',name) AS 'name', description, snippet, properties, category
		FROM {$tbl_site_snippets} WHERE id={$id}";
$rs = $modx->db->query($sql);

if($rs) $newid = $modx->db->getInsertId(); // get new id
else {
	echo "A database error occured while trying to duplicate snippet: <br /><br />".$modx->db->getLastError();
	exit;
}

// finish duplicating - redirect to new snippet
header("Location: index.php?a=22&id={$newid}");
