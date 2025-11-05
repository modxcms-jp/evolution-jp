<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_module')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = (int)getv('id');

// invoke OnBeforeModFormDelete event
$tmp = ["id" => $id];
evo()->invokeEvent("OnBeforeModFormDelete", $tmp);

//ok, delete the module.
$sql = "DELETE FROM " . evo()->getFullTableName("site_modules") . " WHERE id=" . $id . ";";
$rs = db()->query($sql);
if (!$rs) {
    echo "Something went wrong while trying to delete the module...";
    exit;
}

//ok, delete the module dependencies.
$sql = "DELETE FROM " . evo()->getFullTableName("site_module_depobj") . " WHERE module='" . $id . "';";
$rs = db()->query($sql);

//ok, delete the module user group access.
$sql = "DELETE FROM " . evo()->getFullTableName("site_module_access") . " WHERE module='" . $id . "';";
$rs = db()->query($sql);

// invoke OnModFormDelete event
$tmp = ["id" => $id];
evo()->invokeEvent("OnModFormDelete", $tmp);


// empty cache
$modx->clearCache(); // first empty the cache
// finished emptying cache - redirect

$header = "Location: index.php?a=106&r=2";
header($header);
