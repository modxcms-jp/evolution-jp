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
        const field = document.getElementById('tv[+tv_name+]');
        if (!field) {
            return;
        }
        field.value = "[+site_url+]" + url;
        field.dispatchEvent(new Event('change', {bubbles: true}));
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
        OpenServerBrowser("[+base_url+]manager/media/browser/evo/browser.php?type=images", w, h);
    }

    (function () {
        const field = document.getElementById('tv[+tv_name+]');
        const preview = document.getElementById('qm-tv-image-preview');
        if (!field || !preview) {
            return;
        }
        // 選んだ画像でプレビューを差し替える（値は属性に入るので、文字列をつながずに img.src へ入れる）
        field.addEventListener('change', function () {
            preview.replaceChildren();
            if (field.value === '') {
                return;
            }
            const img = document.createElement('img');
            img.id = 'img_preview';
            img.className = 'qm-tv-image-preview-drskip qm-tv-image-preview-skip';
            img.alt = '';
            img.src = field.value;
            preview.append(img);
        });
    })();
</script>
