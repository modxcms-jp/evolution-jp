<?php
!defined('MODX_BASE_PATH') && die('What are you doing? Get out of here!');

include_once(__DIR__ . '/functions.php');

$mce = new TinyMCE();

// Handle event
$e = &$modx->event;
switch ($e->name) {
    case "OnRichTextEditorRegister": // register only for backend
        $e->output('TinyMCE');
        break;

    case "OnRichTextEditorInit":
        if ($editor !== 'TinyMCE') return;

        $html = $mce->get_mce_script();
        $e->output($html);
        break;

    case "OnInterfaceSettingsRender":
        $html = $mce->get_mce_settings();
        $e->output($html);
        break;
}
