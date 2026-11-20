<?php
echo '<!-- Begin ManagerManager output -->' . "\n";

// Create a mask to cover the page while the fields are being rearranged
echo '
    <div id="loadingmask">&nbsp;</div>
    <script src="' . hsc(site_asset(MODX_BASE_URL . 'assets/plugins/managermanager/loading-mask.js')) . '"></script>
';
echo '<!-- End ManagerManager output -->';
