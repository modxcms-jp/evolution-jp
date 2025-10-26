<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('new_snippet')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = getv('id');
if (!preg_match('/^[0-9]+\z/', $id)) {
    echo 'Value of $id is invalid.';
    exit;
}

// duplicate Snippet
$tbl_site_snippets = evo()->getFullTableName('site_snippets');
$tpl = $_lang['duplicate_title_string'];
$sql = "INSERT INTO {$tbl_site_snippets} (name, description, snippet, properties, category, php_error_reporting)
                SELECT REPLACE('{$tpl}','[+title+]',name) AS 'name', description, snippet, properties, category, php_error_reporting
                FROM {$tbl_site_snippets} WHERE id={$id}";
$rs = db()->query($sql);

if ($rs) {
    $newid = $modx->db->getInsertId();
} // get new id
else {
    echo "A database error occured while trying to duplicate snippet: <br /><br />" . db()->getLastError();
    exit;
}

// finish duplicating - redirect to new snippet
header("Location: index.php?a=22&id={$newid}");
