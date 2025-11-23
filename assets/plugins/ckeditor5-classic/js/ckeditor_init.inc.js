<script src="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.umd.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/43.3.1/translations/ja.umd.js"></script>
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css" />
<style>
/* CKEditor container */
.ck-editor__editable {
    min-height: [+height+]px;
}
.ck.ck-editor__main > .ck-editor__editable {
    background-color: #fff;
}

/* Source editing (HTML) view */
.ck-source-editing-area {
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace !important;
    font-size: 13px !important;
    line-height: 1.6 !important;
    white-space: pre-wrap !important;
    background-color: #f7f7f9 !important;
    color: #1f2933 !important;
    padding: 12px !important;
    box-sizing: border-box !important;
}

.ck-source-editing-area:focus {
    outline: 2px solid #2680eb;
    outline-offset: -2px;
}

/* Reset MODX styles within CKEditor content area */
.ck-content,
.ck-content * {
    all: revert;
}

/* Re-apply CKEditor5 content styles */
.ck-content {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    font-size: 14px !important;
    line-height: 1.6 !important;
    color: #333 !important;
    background-color: #fff !important;
    padding: 10px !important;
}

.ck-content p {
    margin: 0 0 1em 0 !important;
}

.ck-content h1,
.ck-content h2,
.ck-content h3,
.ck-content h4,
.ck-content h5,
.ck-content h6 {
    margin: 1em 0 0.5em 0 !important;
    font-weight: bold !important;
    line-height: 1.2 !important;
}

.ck-content h1 { font-size: 2em !important; }
.ck-content h2 { font-size: 1.75em !important; }
.ck-content h3 { font-size: 1.5em !important; }
.ck-content h4 { font-size: 1.25em !important; }
.ck-content h5 { font-size: 1.1em !important; }
.ck-content h6 { font-size: 1em !important; }

.ck-content ul,
.ck-content ol {
    margin: 1em 0 !important;
    padding-left: 40px !important;
}

.ck-content li {
    margin: 0.5em 0 !important;
}

.ck-content a {
    color: #0066cc !important;
    text-decoration: underline !important;
}

.ck-content a:hover {
    color: #003399 !important;
}

.ck-content img {
    max-width: 100% !important;
    height: auto !important;
}

.ck-content table {
    border-collapse: collapse !important;
    margin: 1em 0 !important;
    width: 100% !important;
}

.ck-content table th,
.ck-content table td {
    border: 1px solid #ddd !important;
    padding: 8px !important;
    text-align: left !important;
}

.ck-content table th {
    background-color: #f5f5f5 !important;
    font-weight: bold !important;
}

.ck-content blockquote {
    margin: 1em 20px !important;
    padding: 10px 20px !important;
    border-left: 4px solid #ccc !important;
    background-color: #f9f9f9 !important;
    font-style: italic !important;
}

.ck-content code {
    font-family: "Courier New", Courier, monospace !important;
    background-color: #f4f4f4 !important;
    padding: 2px 4px !important;
    border-radius: 3px !important;
}

.ck-content pre {
    background-color: #f4f4f4 !important;
    border: 1px solid #ddd !important;
    border-radius: 3px !important;
    padding: 10px !important;
    overflow: auto !important;
}

.ck-content pre code {
    background-color: transparent !important;
    padding: 0 !important;
}

.ck-content hr {
    border: 0 !important;
    border-top: 1px solid #ccc !important;
    margin: 1em 0 !important;
}
</style>
<script type="text/javascript">
(function() {
    const {
        ClassicEditor,
        Plugin,
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
        ImageInsert,
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
        GeneralHtmlSupport,
        ButtonView
    } = CKEDITOR;

    class ModxImageButton extends Plugin {
        init() {
            const editor = this.editor;
            const icon = CKEDITOR.icons && CKEDITOR.icons.image ? CKEDITOR.icons.image : null;

            editor.ui.componentFactory.add('modxImage', locale => {
                const view = new ButtonView(locale);

                view.set({
                    label: editor.t('Insert image'),
                    icon,
                    tooltip: true
                });

                view.on('execute', () => {
                    window.CKEditorModxBrowser.openBrowser(editor, 'image', url => {
                        if (url) {
                            editor.execute('insertImage', { source: url });
                            editor.editing.view.focus();
                        }
                    });
                });

                return view;
            });
        }
    }

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
            ImageInsert,
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
            GeneralHtmlSupport,
            ModxImageButton
        ],
        toolbar: {
            items: toolbarItems,
            shouldNotGroupWhenFull: true
        },
        language: '[+language+]',
        image: {
            insert: {
                type: 'inline'
            },
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

                    // Create a dummy upload adapter to prevent errors
                    // Silently rejects clipboard/drag-drop uploads without showing alerts
                    // Users should use the image button to select images via MCPUK browser
                    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
                        return {
                            upload: () => {
                                return Promise.reject();
                            },
                            abort: () => {}
                        };
                    };

                    // Override insertImage command to use MCPUK browser
                    const insertImageCommand = editor.commands.get('insertImage');
                    if (insertImageCommand) {
                        const originalExecute = insertImageCommand.execute.bind(insertImageCommand);

                        insertImageCommand.execute = function(options) {
                            // If options has source, it's being called from our callback
                            if (options && (options.source || options.sources)) {
                                originalExecute(options);
                            } else {
                                // Open MCPUK browser
                                window.CKEditorModxBrowser.openBrowser(editor, 'image', function(url) {
                                    if (url) {
                                        originalExecute({ source: url });
                                    }
                                });
                            }
                        };
                    }
                })
                .catch(error => {
                    console.error('CKEditor initialization error:', error);
                });
        }
    });
})();
</script>
