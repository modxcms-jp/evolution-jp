<?php
global $modx;
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if ($modx->config('remember_last_tab') !== '2') {
    if (70300 <= PHP_VERSION_ID) {
        setcookie(
            'webfxtab_childPane'
            , getv('tab', 1)
            , array(
                'expires' => time() + 3600,
                'path' => MODX_BASE_URL,
                'secure' => init::is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    } else {
        setcookie(
            'webfxtab_childPane'
            , getv('tab', 1)
            , time() + 3600
            , MODX_BASE_URL . '; SameSite=Lax'
            , ''
            , init::is_ssl()
            , true
        );
    }
}

// invoke OnManagerRegClientStartupHTMLBlock event
$evtOut = evo()->invokeEvent('OnManagerMainFrameHeaderHTMLBlock');
?>
<!DOCTYPE html>
<html lang="<?php echo globalv('modx_lang_attribute','en'); ?>" dir="<?php echo globalv('modx_textdir', 'ltr'); ?>">
<head>
    <title>MODX</title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $modx->config('modx_charset'); ?>"/>
    <link rel="stylesheet" type="text/css"
        href="media/style/<?php echo $modx->config('manager_theme'); ?>/style.css?<?php echo globalv('modx_version'); ?>"
    />
    <link rel="stylesheet" type="text/css" href="media/script/jquery/jquery.powertip.css"/>
    <link rel="stylesheet" href="media/script/jquery/jquery.alerts.css" type="text/css"/>
    <!-- OnManagerMainFrameHeaderHTMLBlock -->
    <?php if (is_array($evtOut)) {
        echo implode("\n", $evtOut);
    } ?>
    <?php echo $modx->config('manager_inline_style'); ?>
    <?php echo sprintf(
            '<script src="%s" type="text/javascript"></script>'
            , $modx->config('mgr_jquery_path', 'media/script/jquery/jquery.min.js')
    );
    ?>
    <script src="media/script/jquery/jquery.powertip.min.js" type="text/javascript"></script>
    <script src="media/script/jquery/jquery.alerts.js" type="text/javascript"></script>
    <script src="media/script/mootools/mootools.js" type="text/javascript"></script>
    <script type="text/javascript" src="media/script/tabpane.js"></script>
    <script type="text/javascript">
        var treeopen = <?php echo $modx->config('tree_pane_open_default',1);?>;
        if (treeopen === 0 && top.mainMenu) top.mainMenu.hideTreeFrame();

        var documentDirty = false;
        var dontShowWorker = false;
        var baseurl = '<?php echo MODX_BASE_URL; ?>';
        var $j = jQuery.noConflict();

        // set tree to default action.
        if (parent.tree) parent.tree.ca = "open";

        // call the updateMail function, updates mail notification in top navigation
        if (top.mainMenu && top.mainMenu.updateMail) top.mainMenu.updateMail(true);

        jQuery(function () {
            var action = <?php echo $modx->manager->action;?>;
            switch (action) {
                case 27:
                case 17:
                case 4:
                case 87:
                case 88:
                case 11:
                case 12:
                case 28:
                case 38:
                case 35:
                case 16:
                case 19:
                case 22:
                case 23:
                case 77:
                case 78:
                case 107:
                case 108:
                case 113:
                case 100:
                case 101:
                case 102:
                case 300:
                case 301:
                    jQuery('input,textarea,select:not(#field_template,#which_editor,#stay)')
                        .change(
                            function () {
                                documentDirty = true;
                            }
                        );
                    gotosave = false;
                    break;
            }
            <?php if (anyv('r')) {
            echo sprintf("doRefresh(%s);\n", anyv('r'));
        }
            ?>
            jQuery('.tooltip').powerTip({'fadeInTime': '0', 'placement': 'e'});
        });

        jQuery(function () {
            jQuery('#preLoader').hide();
            jQuery('input.DatePicker').attr('autocomplete', 'off');
        });

        jQuery(window).on('beforeunload', function () {
            if (documentDirty) return '<?php echo addslashes(lang('warning_not_saved'));?>';
            jQuery('#actions').fadeOut(100);
            jQuery('input,textarea,select').addClass('readonly');
            jQuery('#preLoader').show();
            if (!dontShowWorker && top.mainMenu) top.mainMenu.work();
        });

        function doRefresh(r) {
            try {
                rr = r;
                top.mainMenu.reloadPane(rr);
            } catch (oException) {
                vv = window.setTimeout('doRefresh(' + r + ')', 200);
            }
        }
    </script>
</head>
<body
    id="<?php echo getv('f', 'mainpane'); ?>"
    ondragstart="return false"
    <?php if (globalv('modx_textdir') === 'rtl') {?>
    class="rtl"
    <?php }?>
>
<div id="preLoader">
    <div class="preLoaderText"><?php echo style('ajax_loader'); ?></div>
</div>
