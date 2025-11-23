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
            const baseUrl = '[+base_url+]';
            const managerPrefix = 'manager/';
            let processedUrl = url;

            // Normalize URLs coming back from the manager (absolute or relative)
            if (url.startsWith(baseUrl + managerPrefix)) {
                processedUrl = baseUrl + url.substring((baseUrl + managerPrefix).length);
            } else if (url.startsWith('/' + managerPrefix)) {
                processedUrl = baseUrl + url.substring(managerPrefix.length + 1);
            } else if (url.startsWith(managerPrefix)) {
                processedUrl = baseUrl + url.substring(managerPrefix.length);
            } else if (url.startsWith('/')) {
                processedUrl = baseUrl + url.substring(1);
            } else if (!/^https?:\/\//i.test(url)) {
                // Handle relative paths like "content/images/..."
                processedUrl = baseUrl + url;
            }

            this.currentCallback(processedUrl);
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
