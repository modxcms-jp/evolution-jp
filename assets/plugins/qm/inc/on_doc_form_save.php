<?php
if((int)anyv('quickmanager') != 1) {
    return;
}

$id = event()->param('id');
include_once(MODX_CORE_PATH . 'secure_web_documents.inc.php');
secureWebDocument($id);

include_once(MODX_CORE_PATH . 'secure_mgr_documents.inc.php');
secureMgrDocument($id);

evo()->clearCache();

global $modx;
$modx->config['xhtml_urls'] = 0;
evo()->sendRedirect(
    evo()->makeUrl(postv('qmrefresh', $id),'','quickmanagerclose=1','full'),
    0,
    'REDIRECT_HEADER',
    'HTTP/1.1 301 Moved Permanently'
);
