<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
$hasPermission = 0;
if($modx->hasPermission('save_plugin') ||
   $modx->hasPermission('save_snippet') ||
   $modx->hasPermission('save_template') ||
   $modx->hasPermission('save_module')) {
    $hasPermission = 1;
}

if ($hasPermission) {
    $catId = (int)$_GET['catId'];
    $modx->manager->deleteCategory($catId);
}
header("Location: index.php?a=76");
