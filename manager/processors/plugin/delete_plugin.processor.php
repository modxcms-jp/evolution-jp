<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('delete_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

if (!manager()->deletePlugin($id)) {
    echo "Something went wrong while trying to delete the plugin...";
    exit;
}

evo()->clearCache();
header('Location: index.php?a=76');
