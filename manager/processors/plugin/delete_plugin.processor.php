<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('delete_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = (int)getv('id');

// invoke OnBeforePluginFormDelete event
$tmp = ['id' => $id];
evo()->invokeEvent('OnBeforePluginFormDelete', $tmp);

// delete the plugin.
$rs = db()->delete('[+prefix+]site_plugins', "id='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the plugin...";
    exit;
}

// delete the plugin events.
$rs = db()->delete('[+prefix+]site_plugin_events', "pluginid='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the plugin events...";
    exit;
}

// invoke OnPluginFormDelete event
$tmp = ['id' => $id];
evo()->invokeEvent('OnPluginFormDelete', $tmp);
// empty cache
$modx->clearCache();
header('Location: index.php?a=76');
