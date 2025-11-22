window.CKEDITOR_MODX_CONFIG = {
    toolbar: {
        items: [
            'undo', 'redo',
            '|',
            'bold', 'italic', 'underline', 'strikethrough',
            'fontColor', 'fontBackgroundColor',
            '|',
            'heading', 'fontSize',
            '|',
            'link', 'specialCharacters', 'horizontalLine',
            'outdent', 'indent',
            'bulletedList', 'numberedList',
            '|',
            'alignment:left', 'alignment:center',
            'alignment:right', 'alignment:justify',
            '|',
            'imageSelector',
            'sourceEditing'
        ]
    },
    removePlugins: [
        'CKFinder', 'ImageUpload', 'ImageInsert',
        'MediaEmbed', 'Table', 'AutoImage', 'BlockQuote',
        'EasyImage', 'CloudServices'
    ],
    extraPlugins: []
};
