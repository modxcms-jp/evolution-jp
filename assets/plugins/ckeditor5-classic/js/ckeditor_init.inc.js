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
    window.ckeditorInstances = window.ckeditorInstances || {};

    elements.forEach(function(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            ClassicEditor
                .create(element, editorConfig)
                .then(editor => {
                    window.ckeditorInstances[elementId] = editor;

                    // Track changes for MODX
                    editor.model.document.on('change:data', () => {
                        if (typeof documentDirty !== 'undefined') {
                            documentDirty = true;
                        }
                    });

                    // Override the default insertImage command to use MCPUK browser
                    const insertImageCommand = editor.commands.get('insertImage');
                    if (insertImageCommand) {
                        // Store original execute method
                        const originalExecute = insertImageCommand.execute.bind(insertImageCommand);

                        // Override execute to open MCPUK browser instead
                        insertImageCommand.execute = function(options) {
                            // If URL is provided (from MCPUK callback), use original execute
                            if (options && options.source) {
                                originalExecute(options);
                            } else {
                                // Otherwise, open MCPUK browser
                                window.CKEditorModxBrowser.openBrowser(editor, 'image', function(url) {
                                    if (url) {
                                        originalExecute({ source: url });
                                    }
                                });
                            }
                        };
                    }

                    // Add uploadImage command override if exists
                    const uploadImageCommand = editor.commands.get('uploadImage');
                    if (uploadImageCommand) {
                        uploadImageCommand.on('execute', (evt) => {
                            evt.stop();
                            window.CKEditorModxBrowser.openBrowser(editor, 'image', function(url) {
                                if (url) {
                                    editor.model.change(writer => {
                                        const imageElement = writer.createElement('imageBlock', {
                                            src: url
                                        });
                                        editor.model.insertContent(imageElement, editor.model.document.selection);
                                    });
                                }
                            });
                        }, { priority: 'high' });
                    }

                    // Disable file dialog for image upload
                    editor.plugins.get('FileRepository').createUploadAdapter = () => {
                        return {
                            upload: () => {
                                return Promise.reject('Please use the image button to select images from the media browser');
                            },
                            abort: () => {}
                        };
                    };
                })
                .catch(error => {
                    console.error('CKEditor initialization error:', error);
                });
        }
    });
})();
</script>
