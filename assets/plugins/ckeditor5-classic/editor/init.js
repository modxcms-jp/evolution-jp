function ImageSelectorPlugin(editor) {
    const ButtonView = window.CKEDITOR?.ui?.button?.ButtonView
        || window.CKEDITOR5?.ui?.button?.ButtonView;

    if (!ButtonView) {
        console.error('CKEditor5 ButtonView is unavailable.');
        return;
    }

    editor.ui.componentFactory.add('imageSelector', locale => {
        const view = new ButtonView(locale);
        view.set({ label: '画像', withText: true });

        view.on('execute', () => {
            window.open(
                'media/browser/mcpuk/browser.php?Type=images',
                'modxImageBrowser',
                'width=900,height=600'
            );
        });

        return view;
    });
}

window.addEventListener('DOMContentLoaded', () => {
    const textarea = document.getElementById('ta');
    const area = document.createElement('div');
    const ClassicEditor = window.ClassicEditor || window.CKEDITOR?.ClassicEditor;

    if (!ClassicEditor) {
        console.error('CKEditor5 ClassicEditor is unavailable.');
        return;
    }

    area.id = 'ckeditor-area';
    textarea.parentNode.insertBefore(area, textarea.nextSibling);

    ClassicEditor.create(area, {
        initialData: textarea.value,
        ...window.CKEDITOR_MODX_CONFIG,
        extraPlugins: [ImageSelectorPlugin]
    })
        .then(editor => {
            window.CKEDITOR_MODX_INSTANCE = editor;
            editor.model.document.on('change:data', () => {
                textarea.value = editor.getData();
            });
        })
        .catch(console.error);
});

window.SetUrl = function(url) {
    const editor = window.CKEDITOR_MODX_INSTANCE;
    if (!editor) return;

    editor.model.change(writer => {
        const imageElement = writer.createElement('imageBlock', { src: url });
        editor.model.insertContent(imageElement);
    });
};
