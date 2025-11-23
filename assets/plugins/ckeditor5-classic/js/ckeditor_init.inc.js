<script src="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.umd.js"></script>
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css" />
<style>
.ck-editor__editable {
    min-height: [+height+]px;
}
.ck.ck-editor__main > .ck-editor__editable {
    background-color: #fff;
}
</style>
<script type="text/javascript">
(function() {
    const {
        ClassicEditor,
        Essentials,
        Bold,
        Italic,
        Underline,
        Strikethrough,
        Subscript,
        Superscript,
        Font,
        Paragraph,
        Heading,
        Link,
        List,
        TodoList,
        Image,
        ImageCaption,
        ImageStyle,
        ImageToolbar,
        ImageUpload,
        ImageResize,
        LinkImage,
        Table,
        TableToolbar,
        TableProperties,
        TableCellProperties,
        MediaEmbed,
        BlockQuote,
        CodeBlock,
        Code,
        Indent,
        IndentBlock,
        Alignment,
        HorizontalLine,
        PageBreak,
        RemoveFormat,
        FindAndReplace,
        SourceEditing,
        GeneralHtmlSupport
    } = CKEDITOR;

    const toolbarItems = [+toolbar_config+];

    const editorConfig = {
        plugins: [
            Essentials,
            Bold,
            Italic,
            Underline,
            Strikethrough,
            Subscript,
            Superscript,
            Font,
            Paragraph,
            Heading,
            Link,
            List,
            TodoList,
            Image,
            ImageCaption,
            ImageStyle,
            ImageToolbar,
            ImageUpload,
            ImageResize,
            LinkImage,
            Table,
            TableToolbar,
            TableProperties,
            TableCellProperties,
            MediaEmbed,
            BlockQuote,
            CodeBlock,
            Code,
            Indent,
            IndentBlock,
            Alignment,
            HorizontalLine,
            PageBreak,
            RemoveFormat,
            FindAndReplace,
            SourceEditing,
            GeneralHtmlSupport
        ],
        toolbar: {
            items: toolbarItems,
            shouldNotGroupWhenFull: true
        },
        language: '[+language+]',
        image: {
            toolbar: [
                'imageTextAlternative',
                'toggleImageCaption',
                'imageStyle:inline',
                'imageStyle:block',
                'imageStyle:side',
                'linkImage'
            ]
        },
        table: {
            contentToolbar: [
                'tableColumn',
                'tableRow',
                'mergeTableCells',
                'tableCellProperties',
                'tableProperties'
            ]
        },
        heading: {
            options: [
                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' },
                { model: 'heading5', view: 'h5', title: 'Heading 5', class: 'ck-heading_heading5' },
                { model: 'heading6', view: 'h6', title: 'Heading 6', class: 'ck-heading_heading6' }
            ]
        },
        htmlSupport: {
            allow: [
                {
                    name: /.*/,
                    attributes: true,
                    classes: true,
                    styles: true
                }
            ]
        }
    };

    // Initialize CKEditor on specified elements
    const elements = '[+elmList+]'.split(',');
    const editors = {};

    elements.forEach(function(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            ClassicEditor
                .create(element, editorConfig)
                .then(editor => {
                    editors[elementId] = editor;

                    // Set up file browser integration
                    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
                        return new ModxUploadAdapter(loader);
                    };

                    // Track changes for MODX
                    editor.model.document.on('change:data', () => {
                        if (typeof documentDirty !== 'undefined') {
                            documentDirty = true;
                        }
                    });

                    // Expose editor instance globally
                    window.ckeditorInstances = window.ckeditorInstances || {};
                    window.ckeditorInstances[elementId] = editor;
                })
                .catch(error => {
                    console.error('CKEditor initialization error:', error);
                });
        }
    });

    // Custom Upload Adapter for MODX file browser
    class ModxUploadAdapter {
        constructor(loader) {
            this.loader = loader;
        }

        upload() {
            return this.loader.file
                .then(file => new Promise((resolve, reject) => {
                    // This will be handled by the file browser callback
                    reject('Upload should be handled via file browser');
                }));
        }

        abort() {
            // Reject the promise returned from the upload() method.
        }
    }
})();
</script>
