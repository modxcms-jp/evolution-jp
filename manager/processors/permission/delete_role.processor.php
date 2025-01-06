<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_role')) {
    alert()->setError(3);
    alert()->dumpError();
}

$tbl_user_attributes = evo()->getFullTableName('user_attributes');
$tb_user_roles = evo()->getFullTableName('user_roles');

$id = getv('id');
if (empty($id)) {
    header("Location: index.php?a=86");
}

if (!preg_match('/^[0-9]+$/', $id)) {
    echo "Wrong data was inputted!";
    exit;
}

if ($id == 1) {
    echo "The role you are trying to delete is the admin role. This role cannot be deleted!";
    exit;
}

$rs = db()->select('count(id)', $tbl_user_attributes, "role={$id}");
if (!$rs) {
    echo "Something went wrong while trying to find users with this role...";
    exit;
}
if (db()->getValue($rs) > 0) {
    echo "There are users with this role. It can't be deleted.";
    exit;
}

// delete the attributes
$rs = db()->delete($tb_user_roles, "id={$id}");
if (!$rs) {
    echo "Something went wrong while trying to delete the role...";
    exit;
}

header("Location: index.php?a=86");
