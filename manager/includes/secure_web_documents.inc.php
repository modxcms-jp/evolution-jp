<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

function secureWebDocument($docid = '')
{
    return manager()->setWebDocsAsPrivate($docid);
}
