<?php
if($this->rule_exists($config_chunk)) {
    $modx->config['tvs_below_content'] = '1';
}
// Load the jquery library
echo '<!-- Begin ManagerManager output -->' . "\n";

// Create a mask to cover the page while the fields are being rearranged
echo '
    <div id="loadingmask">&nbsp;</div>
    <script type="text/javascript">
        $j("#loadingmask").css( {width: "100%", height: $j("body").height(), position: "absolute", zIndex: "1000", backgroundColor: "#ffffff"} );
    </script>
';
echo '<!-- End ManagerManager output -->';
