<?php
return array(
    'simple' => json_encode(
        ['undo', 'redo', '|', 'bold', 'strikethrough', '|', 'alignment:left', 'alignment:center', 'alignment:right', '|', 'link', 'insertImage', '|', 'horizontalLine']
    ),
    'default' => json_encode([
        ['undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'strikethrough', '|', 'fontColor', 'fontBackgroundColor', '|', 'link', 'insertImage', 'insertTable', 'mediaEmbed', '|', 'sourceEditing'],
        ['alignment', '|', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'blockQuote', 'horizontalLine', '|', 'removeFormat']
    ]),
    'full' => json_encode([
        ['undo', 'redo', '|', 'heading', '|', 'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|', 'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript'],
        ['link', 'insertImage', 'insertTable', 'mediaEmbed', 'codeBlock', '|', 'alignment', '|', 'bulletedList', 'numberedList', 'todoList', '|', 'outdent', 'indent'],
        ['blockQuote', 'horizontalLine', 'pageBreak', '|', 'code', 'removeFormat', '|', 'findAndReplace', '|', 'sourceEditing']
    ])
);
