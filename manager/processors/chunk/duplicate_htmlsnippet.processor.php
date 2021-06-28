<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('new_chunk')) {
    alert()->setError(3);
    alert()->dumpError();
}
if (!preg_match('@^[1-9][0-9]*$@', getv('id'))) {
    exit;
}

$sql = sprintf(
    "INSERT INTO %s (name, description, snippet, category, editor_type)
		SELECT REPLACE('%s','[+title+]',name) AS 'name', description, snippet, category, editor_type
		FROM %s WHERE id='%s'",
    evo()->getFullTableName('site_htmlsnippets'),
    $_lang['duplicate_title_string'],
    evo()->getFullTableName('site_htmlsnippets'),
    getv('id')
);
$rs = db()->query($sql);

if (!$rs) {
    echo "A database error occured while trying to duplicate variable: <br /><br />" . db()->getLastError();
    exit;
}

header("Location: index.php?a=78&id=" . db()->getInsertId());
