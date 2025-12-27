<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('new_module')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = getv('id');
if (!preg_match('/^[0-9]+\z/', $id)) {
    echo 'Value of $id is invalid.';
    exit;
}

// duplicate module
$tbl_site_modules = evo()->getFullTableName('site_modules');
$tpl = $_lang['duplicate_title_string'];
$sql = sprintf(
    "
        INSERT INTO %s (name, description, disabled, category, wrap, icon, enable_resource, resourcefile, createdon, editedon, guid, enable_sharedparams, properties, modulecode)
		SELECT REPLACE('%s','[+title+]',name) AS 'name', description, disabled, category, wrap, icon, enable_resource, resourcefile, createdon, editedon, '%s' as 'guid', enable_sharedparams, properties, modulecode
		FROM %s WHERE id=%s",
    $tbl_site_modules, $tpl, createGUID(), $tbl_site_modules, $id);
$rs = db()->query($sql);

if ($rs) {
    $newid = $modx->db->getInsertId();
} // get new id
else {
    echo "A database error occured while trying to duplicate module: <br /><br />" . db()->getLastError();
    exit;
}

// duplicate module dependencies
$tbl_site_module_depobj = evo()->getFullTableName('site_module_depobj');
$sql = "INSERT INTO " . $tbl_site_module_depobj . " (module, resource, type)
		SELECT  '" . $newid . "', resource, type
		FROM " . $tbl_site_module_depobj . " WHERE module=" . $id;
$rs = db()->query($sql);

if (!$rs) {
    echo "A database error occured while trying to duplicate module dependencies: <br /><br />" . db()->getLastError();
    exit;
}

// duplicate module user group access
$tbl_site_module_access = evo()->getFullTableName('site_module_access');
$sql = "INSERT INTO " . $tbl_site_module_access . " (module, usergroup)
		SELECT  '" . $newid . "', usergroup
		FROM " . $tbl_site_module_access . " WHERE module=" . $id;
$rs = db()->query($sql);

if (!$rs) {
    echo "A database error occured while trying to duplicate module user group access: <br /><br />" . db()->getLastError();
    exit;
}

// finish duplicating - redirect to new module
header("Location: index.php?r=2&a=108&id=" . $newid);


// create globally unique identifiers (guid)
function createGUID()
{
    mt_srand((float)microtime() * 1000000);
    $r = mt_rand();
    $u = uniqid(getmypid() . $r . (float)microtime() * 1000000, 1);
    return md5($u);
}
