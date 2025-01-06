<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('new_template')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = getv('id');
if (!preg_match('/^[0-9]+\z/', $id)) {
    echo 'Value of $id is invalid.';
    exit;
}

// duplicate template
$tpl = $_lang['duplicate_title_string'];
$tbl_site_templates = evo()->getFullTableName('site_templates');
$sql = "INSERT INTO {$tbl_site_templates} (templatename, description, content, category, parent)
		SELECT REPLACE('{$tpl}','[+title+]',templatename) AS 'templatename', description, content, category, parent
		FROM {$tbl_site_templates} WHERE id={$id}";
$rs = db()->query($sql);

if ($rs) {
    $newid = $modx->db->getInsertId(); // get new id
    // duplicate TV values
    $tbl_site_tmplvar_templates = evo()->getFullTableName('site_tmplvar_templates');
    $tvs = db()->select('*', $tbl_site_tmplvar_templates, 'templateid=' . $id);
    if (db()->count($tvs) > 0) {
        while ($row = db()->getRow($tvs)) {
            $row['templateid'] = $newid;
            db()->insert($row, $tbl_site_tmplvar_templates);
        }
    }
} else {
    echo "A database error occured while trying to duplicate variable: <br /><br />" . db()->getLastError();
    exit;
}

// finish duplicating - redirect to new template
header("Location: index.php?a=16&id=$newid");
