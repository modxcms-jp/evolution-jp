<?php 
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");

if(!$modx->hasPermission('new_plugin')) {	
	$e->setError(3);
	$e->dumpError();	
}

$id=$_GET['id'];

// duplicate Plugin
$sql = "INSERT INTO $dbase.`".$table_prefix."site_plugins` (name, description, disabled, moduleguid, plugincode, properties, category) 
		SELECT CONCAT('Duplicate of ',name) AS 'name', description, disabled, moduleguid, plugincode, properties, category 
		FROM $dbase.`".$table_prefix."site_plugins` WHERE id=$id;";
$rs = mysql_query($sql);

if($rs) $newid = mysql_insert_id(); // get new id
else {
	echo "A database error occured while trying to duplicate plugin: <br /><br />".mysql_error();
	exit;
}

// duplicate Plugin Event Listeners
$sql = "INSERT INTO $dbase.`".$table_prefix."site_plugin_events` (pluginid,evtid,priority)
		SELECT $newid, evtid, priority
		FROM $dbase.`".$table_prefix."site_plugin_events` WHERE pluginid=$id;";
$rs = mysql_query($sql);

if (!$rs) {
	echo "A database error occured while trying to duplicate plugin events: <br /><br />".mysql_error();
	exit;
}

// finish duplicating - redirect to new plugin
$header="Location: index.php?r=2&a=102&id=$newid";
header($header);
?>