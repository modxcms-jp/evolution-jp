<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

function secureMgrDocument($docid = '') {
    global $modx;
    return $modx->manager->setMgrDocsAsPrivate($docid);
}
