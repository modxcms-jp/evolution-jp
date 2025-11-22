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
        'CKFinder', 'CKFinderUploadAdapter',
        'EasyImage', 'CloudServices',
        'CKBox', 'CKBoxUtils', 'CKBoxImageEdit', 'CKBoxToolbar', 'CKBoxList', 'CKBoxUploadAdapter',
        'RealTimeCollaborativeComments', 'RealTimeCollaborativeTrackChanges', 'RealTimeCollaborativeRevisionHistory',
        'PresenceList', 'Comments', 'TrackChanges', 'TrackChangesData', 'RevisionHistory',
        'Pagination', 'WProofreader', 'MathType',
        'ExportPdf', 'ExportWord',
        'SlashCommand', 'Template', 'DocumentOutline', 'FormatPainter', 'TableOfContents'
    ],
    extraPlugins: []
};
