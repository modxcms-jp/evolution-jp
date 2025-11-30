<?php

include_once(__DIR__ . '/classes/docmanager.class.php');
include_once(__DIR__ . '/classes/dm_frontend.class.php');
include_once(__DIR__ . '/classes/dm_backend.class.php');

$dm = new DocManager();
$dmf = new DocManagerFrontend($dm);
$dmb = new DocManagerBackend($dm);

$dm->ph = $dm->getLang();
$dm->ph['theme'] = $dm->getTheme();
$dm->ph['style_tree_path'] = manager_style_image_path('tree');
$dm->ph['ajax.endpoint'] = MODX_SITE_URL . 'assets/modules/docmanager/tv.ajax.php';
$dm->ph['datepicker.offset'] = $modx->config['datepicker_offset'];
$dm->ph['datetime.format'] = $modx->config['datetime_format'];
$dm->ph['csrf_token'] = csrfTokenField();
$dm->ph['csrf_meta'] = csrfTokenMeta();

if (postv('tabAction')) {
    $dmb->handlePostback();
} else {
    $dmf->getViews();
    echo $dm->parseTemplate('main.tpl', $dm->ph);
}
