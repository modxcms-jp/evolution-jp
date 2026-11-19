<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$type = EvoElementTypes::typeByAction('duplicate', manager()->action);
$def = EvoElementTypes::get($type);
if (!$def) {
    exit;
}

if (!evo()->hasPermission($def['permissions']['duplicate'])) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

$newId = manager()->duplicateElement($type, $id);
if ($newId === 0) {
    alert()->setError(7);
    alert()->dumpError();
}
if (!$newId) {
    exit(
        "A database error occured while trying to duplicate {$def['label']}: <br /><br />"
        . hsc(db()->getLastError())
    );
}

header('Location: ' . sprintf($def['duplicated_url'], $newId));
