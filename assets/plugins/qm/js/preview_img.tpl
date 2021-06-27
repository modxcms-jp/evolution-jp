<div id="qm-tv-image-preview">
    <img
        src="[+tv_value+]"
        class="qm-tv-image-preview-drskip qm-tv-image-preview-skip"
        id="img_preview"
        alt=""
    />
</div>
<script type="text/javascript" charset="UTF-8">
    function SetUrl(url, width, height, alt) {
        [+jq+]('#tv[+tv_name+]').val("[+site_url+]" + url);
        [+jq+]('#tv[+tv_name+]').trigger("change");
    }

    function OpenServerBrowser(url, width, height) {
        let iLeft = (screen.width - width) / 2;
        let iTop = (screen.height - height) / 2;

        let sOptions = "toolbar=no,status=no,resizable=yes,dependent=yes";
        sOptions += ",width=" + width;
        sOptions += ",height=" + height;
        sOptions += ",left=" + iLeft;
        sOptions += ",top=" + iTop;

        let oWindow = window.open(url, "FCKBrowseWindow", sOptions);
    }
    function BrowseServer() {
        let w = screen.width * 0.7;
        let h = screen.height * 0.7;
        OpenServerBrowser("[+base_url+]manager/media/browser/mcpuk/browser.php?Type=images", w, h);
    }
    [+jq+](function() {
        let previewImage = "#tv[+tv_name+]";
        let siteUrl = "[+site_url+]";
        [+jq+](previewImage).change(function() {
            [+jq+]("#qm-tv-image-preview").empty();
            if ([+jq+](previewImage).val()!=="" ) {
                [+jq+]("#qm-tv-image-preview").append(
                    '<img id="img_preview" class="qm-tv-image-preview-drskip qm-tv-image-preview-skip" src="' + [+jq+](previewImage).val()  + '" alt="" />'
                );
            } else {
                [+jq+]("#qm-tv-image-preview").append("");
            }
        });
    });
</script>
