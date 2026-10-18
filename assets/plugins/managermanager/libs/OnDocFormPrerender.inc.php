<?php
if ($this->rule_exists($config_chunk)) {
    $modx->config['tvs_below_content'] = '1';
}
echo '<!-- Begin ManagerManager output -->' . "\n";

// Create a mask to cover the page while the fields are being rearranged
echo '
    <div id="loadingmask">&nbsp;</div>
    <script>
        (function () {
            // mm.js はまだ読まれていないので素の DOM で書く
            const mask = document.getElementById("loadingmask");
            if (!mask) {
                return;
            }
            Object.assign(mask.style, {
                width: "100%",
                height: document.body.offsetHeight + "px",
                position: "absolute",
                zIndex: "1000",
                backgroundColor: "#ffffff"
            });
        })();
    </script>
';
echo '<!-- End ManagerManager output -->';
