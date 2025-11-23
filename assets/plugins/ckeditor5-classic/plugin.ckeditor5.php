<?php
!defined('MODX_BASE_PATH') && die('What are you doing? Get out of here!');

include_once(__DIR__ . '/functions.php');

$cke = new CKEditor5();

// Handle event
$e = &$modx->event;
switch ($e->name) {
    case "OnRichTextEditorRegister": // register only for backend
        $e->output('CKEditor5');
        break;

    case "OnRichTextEditorInit":
        if ($editor !== 'CKEditor5') return;

        $html = $cke->get_ckeditor_script();
        $e->output($html);
        break;

    case "OnInterfaceSettingsRender":
        $html = $cke->get_ckeditor_settings();
        $e->output($html);
        break;
}
