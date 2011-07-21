<?php 
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('new_module')) {	
	$e->setError(3);
	$e->dumpError();	
}
?>
<?php

$id=$_GET['id'];

// create globally unique identifiers (guid)
function createGUID(){
	srand((double)microtime()*1000000);
	$r = rand() ;
	$u = uniqid(getmypid() . $r . (double)microtime()*1000000,1);
	$m = md5 ($u);
	return $m;
}

// duplicate module
$sql = "INSERT INTO ".$modx->getFullTableName("site_modules")." (name, description, disabled, category, wrap, icon, enable_resource, resourcefile, createdon, editedon, guid, enable_sharedparams, properties, modulecode) 
		SELECT CONCAT('Duplicate of ',name) AS 'name', description, disabled, category, wrap, icon, enable_resource, resourcefile, createdon, editedon, '".createGUID()."' as 'guid', enable_sharedparams, properties, modulecode 
		FROM ".$modx->getFullTableName("site_modules")." WHERE id=$id;";
$rs = mysql_query($sql);

if($rs) $newid = mysql_insert_id(); // get new id
else {
	echo "A database error occured while trying to duplicate module: <br /><br />".mysql_error();
	exit;
}


// duplicate module dependencies
$sql = "INSERT INTO ".$modx->getFullTableName("site_module_depobj")." (module, resource, type)
		SELECT  '$newid', resource, type  
		FROM ".$modx->getFullTableName("site_module_depobj")." WHERE module=$id;";
$rs = mysql_query($sql);

if(!$rs){
	echo "A database error occured while trying to duplicate module dependencies: <br /><br />".mysql_error();
	exit;
}

// duplicate module user group access
$sql = "INSERT INTO ".$modx->getFullTableName("site_module_access")." (module, usergroup)
		SELECT  '$newid', usergroup  
		FROM ".$modx->getFullTableName("site_module_access")." WHERE module=$id;";
$rs = mysql_query($sql);

if(!$rs){
	echo "A database error occured while trying to duplicate module user group access: <br /><br />".mysql_error();
	exit;
}

// finish duplicating - redirect to new module
$header="Location: index.php?r=2&a=108&id=$newid";
header($header);
?>