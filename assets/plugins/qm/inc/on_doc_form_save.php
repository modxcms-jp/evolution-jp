<?php
if ((int)anyv('quickmanager') != 1) {
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
exit(sprintf("<script>parent.location.href='%s';</script>", evo()->makeUrl(postv('qmrefresh', $id))));
