<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('new_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = getv('id');
if (!preg_match('/^[0-9]+\z/', $id)) {
    echo 'Value of $id is invalid.';
    exit;
}

// duplicate Plugin
$tbl_site_plugins = evo()->getFullTableName('site_plugins');
$tpl = $_lang['duplicate_title_string'];
$sql = "INSERT INTO {$tbl_site_plugins} (name, description, disabled, moduleguid, plugincode, properties, category, php_error_reporting)
                SELECT REPLACE('{$tpl}','[+title+]',name) AS 'name', description, disabled, moduleguid, plugincode, properties, category, php_error_reporting
                FROM {$tbl_site_plugins} WHERE id={$id}";
$rs = db()->query($sql);

if ($rs) {
    $newid = $modx->db->getInsertId();
} // get new id
else {
    echo "A database error occured while trying to duplicate plugin: <br /><br />" . db()->getLastError();
    exit;
}

// duplicate Plugin Event Listeners
$tbl_site_plugin_events = evo()->getFullTableName('site_plugin_events');
$sql = "INSERT INTO {$tbl_site_plugin_events} (pluginid,evtid,priority)
		SELECT $newid, evtid, priority
		FROM {$tbl_site_plugin_events} WHERE pluginid={$id}";
$rs = db()->query($sql);

if (!$rs) {
    echo "A database error occured while trying to duplicate plugin events: <br /><br />" . db()->getLastError();
    exit;
}

// finish duplicating - redirect to new plugin
header("Location: index.php?a=102&id={$newid}");
