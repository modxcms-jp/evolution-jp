<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('edit_template')) {
	$e->setError(3);
	$e->dumpError();
}
?>
<?php

$id=$_GET['id'];

// duplicate TV
$sql = "INSERT INTO $dbase.`".$table_prefix."site_tmplvars` (type, name, caption, description, default_text, elements, rank, display, display_params, category)
		SELECT type, CONCAT('Duplicate of ',name) AS 'name', CONCAT('Duplicate of ',caption) AS 'caption', description, default_text, elements, rank, display, display_params, category
		FROM $dbase.`".$table_prefix."site_tmplvars` WHERE id=$id;";
$rs = mysql_query($sql);

if($rs) $newid = mysql_insert_id(); // get new id
else {
	echo "A database error occured while trying to duplicate TV: <br /><br />".mysql_error();
	exit;
}


// duplicate TV Template Access Permissions
$sql = "INSERT INTO $dbase.`".$table_prefix."site_tmplvar_templates` (tmplvarid, templateid)
		SELECT $newid, templateid
		FROM $dbase.`".$table_prefix."site_tmplvar_templates` WHERE tmplvarid=$id;";
$rs = mysql_query($sql);

if (!$rs) {
	echo "A database error occured while trying to duplicate TV template access: <br /><br />".mysql_error();
	exit;
}


// duplicate TV Access Permissions
$sql = "INSERT INTO $dbase.`".$table_prefix."site_tmplvar_access` (tmplvarid, documentgroup)
		SELECT $newid, documentgroup
		FROM $dbase.`".$table_prefix."site_tmplvar_access` WHERE tmplvarid=$id;";
$rs = mysql_query($sql);

if (!$rs) {
	echo "A database error occured while trying to duplicate TV Acess Permissions: <br /><br />".mysql_error();
	exit;
}

// finish duplicating - redirect to new variable
$header="Location: index.php?r=2&a=301&id=$newid";
header($header);
?>