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
</script>
