<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!hasPermission('save_plugin') && !hasPermission('save_snippet') && !hasPermission('save_template') && !hasPermission('save_module')) {
    header('Location: index.php?a=76');
    return;
}

manager()->deleteCategory((int)getv('catId'));
header('Location: index.php?a=76');
