<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!manager()->canSaveAnyElement()) {
    header('Location: index.php?a=76');
    return;
}

manager()->deleteCategory(get_intvar('catId'));
header('Location: index.php?a=76');
