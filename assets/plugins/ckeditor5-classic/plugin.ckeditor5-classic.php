<?php
!defined('MODX_BASE_PATH') && die('What are you doing? Get out of here!');

$event = &$modx->event;

if ($event->name === 'OnRichTextEditorRegister') {
    $event->output('CKEditorClassic');
    return;
}

if ($event->name === 'OnRichTextEditorInit' && $editor === 'CKEditorClassic') {
    $pluginUrl = MODX_SITE_URL . 'assets/plugins/ckeditor5-classic/editor/';
    $html = [
        "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$pluginUrl}style.css\" />",
        '<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>',
        "<script src=\"{$pluginUrl}config.js\"></script>",
        "<script src=\"{$pluginUrl}init.js\"></script>",
    ];

    $event->output(implode("\n", $html));
}
