<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$type = EvoElementTypes::typeByAction('delete', manager()->action);
$def = EvoElementTypes::get($type);
if (!$def) {
    exit;
}

if (!evo()->hasPermission($def['permissions']['delete'])) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

if (!manager()->deleteElement($type, $id)) {
    exit("Something went wrong while trying to delete the {$def['label']}...");
}

evo()->clearCache();
header('Location: ' . EvoElementTypes::deletedUrl($type));
