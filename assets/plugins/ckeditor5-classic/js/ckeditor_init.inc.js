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
        GeneralHtmlSupport,
        ButtonView
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

                    // Create custom insertImage button for MCPUK browser
                    editor.ui.componentFactory.add('insertImage', locale => {
                        const button = new ButtonView(locale);

                        button.set({
                            label: editor.t('Insert image'),
                            icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M6.91 10.54c.26-.23.64-.21.88.03l3.36 3.14 2.23-2.06a.64.64 0 0 1 .87 0l2.52 2.97V4.5H3.2v10.12l3.71-4.08zm10.27-7.51c.6 0 1.09.47 1.09 1.05v11.84c0 .59-.49 1.06-1.09 1.06H2.79c-.6 0-1.09-.47-1.09-1.06V4.08c0-.58.49-1.05 1.1-1.05h14.38zm-5.22 5.56a1.96 1.96 0 1 1 3.4-1.96 1.96 1.96 0 0 1-3.4 1.96z"/></svg>',
                            tooltip: true
                        });

                        button.on('execute', () => {
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
                        });

                        return button;
                    });
                })
                .catch(error => {
                    console.error('CKEditor initialization error:', error);
                });
        }
    });
})();
</script>
