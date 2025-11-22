<?php
!defined('MODX_BASE_PATH') && die('What are you doing? Get out of here!');

$event = &$modx->event;

if ($event->name === 'OnRichTextEditorRegister') {
    $event->output('CKEditorClassic');
    return;
}

if ($event->name === 'OnRichTextEditorInit' && $editor === 'CKEditorClassic') {
    $html = [
        '<link rel="stylesheet" type="text/css" href="assets/plugins/ckeditor5-classic/editor/style.css" />',
        '<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>',
        '<script src="assets/plugins/ckeditor5-classic/editor/config.js"></script>',
        '<script src="assets/plugins/ckeditor5-classic/editor/init.js"></script>',
    ];

    $event->output(implode("\n", $html));
}
