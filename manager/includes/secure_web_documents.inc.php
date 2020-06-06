<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

function secureWebDocument($docid = '') {
    global $modx;
    return $modx->manager->setWebDocsAsPrivate($docid);
}
