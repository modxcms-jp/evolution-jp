<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('new_template')) {
	$e->setError(3);
	$e->dumpError();
}
?>
<?php

$id=$_GET['id'];

// duplicate template
$sql = "INSERT INTO $dbase.`".$table_prefix."site_templates` (templatename, description, content, category)
		SELECT CONCAT('Duplicate of ',templatename) AS 'templatename', description, content, category
		FROM $dbase.`".$table_prefix."site_templates` WHERE id=$id;";
$rs = mysql_query($sql);

if($rs) {
	$newid = mysql_insert_id(); // get new id
	// duplicate TV values
	$tvs = $modx->db->select('*', $modx->getFullTableName('site_tmplvar_templates'), 'templateid='.$id);
	if ($modx->db->getRecordCount($tvs) > 0) {
		while ($row = $modx->db->getRow($tvs)) {
			$row['templateid'] = $newid;
			$modx->db->insert($row, $modx->getFullTableName('site_tmplvar_templates'));
		}
	}
} else {
	echo "A database error occured while trying to duplicate variable: <br /><br />".mysql_error();
	exit;
}

// finish duplicating - redirect to new template
$header="Location: index.php?r=2&a=16&id=$newid";
header($header);
?>