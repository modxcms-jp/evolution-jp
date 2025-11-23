<script type="text/javascript">
// MODX File Browser integration for CKEditor5
window.CKEditorModxBrowser = {
    currentEditor: null,
    currentCallback: null,

    openBrowser: function(editor, type, callback) {
        this.currentEditor = editor;
        this.currentCallback = callback;

        var cmsURL = "[+cmsurl+]";

        // Map CKEditor types to MCPUK types
        var browserType = 'images';
        switch(type) {
            case 'image':
                browserType = 'images';
                break;
            case 'media':
            case 'video':
                browserType = 'media';
                break;
            case 'file':
                browserType = 'files';
                break;
        }

        if (cmsURL.indexOf("?") < 0) {
            cmsURL = cmsURL + "?type=" + browserType;
        } else {
            cmsURL = cmsURL + "&type=" + browserType;
        }

        // Add CKEditor specific parameter
        cmsURL = cmsURL + "&CKEditor=1";

        var width = screen.width * 0.7;
        var height = screen.height * 0.7;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;

        window.open(
            cmsURL,
            'MCPUKBrowser',
            'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
        );
    },

    setUrl: function(url) {
        if (this.currentCallback) {
            this.currentCallback(url);
            this.currentCallback = null;
        }
        this.currentEditor = null;
    }
};

// Function to be called by MCPUK browser
function SetUrl(url, width, height, alt) {
    window.CKEditorModxBrowser.setUrl(url);
}

// Initialize image command override for CKEditor instances
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.ckeditorInstances !== 'undefined') {
        Object.keys(window.ckeditorInstances).forEach(function(instanceId) {
            var editor = window.ckeditorInstances[instanceId];

            // Override image insert command
            editor.commands.get('insertImage').on('execute', function(evt, args) {
                evt.stop();
                window.CKEditorModxBrowser.openBrowser(editor, 'image', function(url) {
                    editor.model.change(writer => {
                        const imageElement = writer.createElement('imageBlock', {
                            src: url
                        });
                        editor.model.insertContent(imageElement, editor.model.document.selection);
                    });
                });
            }, { priority: 'high' });

            // Add custom button for media embed
            if (editor.commands.get('mediaEmbed')) {
                editor.commands.get('mediaEmbed').on('execute', function(evt, args) {
                    // Let default behavior handle media embed
                });
            }
        });
    }
});
</script>
