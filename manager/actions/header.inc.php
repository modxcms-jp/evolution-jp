<?php
global $modx;
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if ($modx->config('remember_last_tab') != 2) {
    setcookie(
        'webfxtab_childPane',
        getv('tab', 1), [
            'expires' => time() + 3600,
            'path' => MODX_BASE_URL,
            'domain' => '',
            'secure' => init::is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

// invoke OnManagerRegClientStartupHTMLBlock event
$evtOut = evo()->invokeEvent('OnManagerMainFrameHeaderHTMLBlock');
?>
<!DOCTYPE html>
<html lang="<?= globalv('modx_lang_attribute', 'en') ?>" dir="<?= globalv('modx_textdir', 'ltr') ?>">

<head>
    <title>MODX</title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?= $modx->config('modx_charset') ?>" />
    <?= csrfTokenMeta() ?>
    <link rel="stylesheet" type="text/css" href="media/style/<?= $modx->config('manager_theme') ?>/style.css?<?= globalv('modx_version') ?>" />
    <link rel="stylesheet" type="text/css" href="media/script/jquery/jquery.powertip.css" />
    <link rel="stylesheet" href="media/script/jquery/jquery.alerts.css" type="text/css" />
    <!-- OnManagerMainFrameHeaderHTMLBlock -->
    <?php if (is_array($evtOut)) {
        echo implode("\n", $evtOut);
    } ?>
    <?= $modx->config('manager_inline_style') ?>
    <?= sprintf(
        '<script src="%s" type="text/javascript"></script>',
        $modx->config('mgr_jquery_path', 'media/script/jquery/jquery.min.js')
    );
    ?>
    <script src="media/script/jquery/jquery.powertip.min.js" type="text/javascript"></script>
    <script src="media/script/jquery/jquery.alerts.js" type="text/javascript"></script>
    <script type="text/javascript" src="media/script/tabpane.js"></script>
    <script type="text/javascript">
        var treeopen = <?= $modx->config('tree_pane_open_default', 1) ?>;
        if (treeopen === 0 && top.mainMenu) top.mainMenu.hideTreeFrame();

        var documentDirty = false;
        var dontShowWorker = false;
        var baseurl = '<?= MODX_BASE_URL ?>';
        var $j = jQuery.noConflict();

        // set tree to default action.
        if (parent.tree) parent.tree.ca = "open";

        // call the updateMail function, updates mail notification in top navigation
        if (top.mainMenu && top.mainMenu.updateMail) top.mainMenu.updateMail(true);

        jQuery(function() {
            var action = <?= manager()->action ?>;
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
                            function() {
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
            jQuery('.tooltip').powerTip({
                'fadeInTime': '0',
                'placement': 'e'
            });
        });

        jQuery(function() {
            jQuery('#preLoader').hide();
            jQuery('input.DatePicker').attr('autocomplete', 'off');
        });

        jQuery(window).on('beforeunload', function() {
            if (documentDirty) return '<?= addslashes(lang('warning_not_saved')) ?>';
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

        // CSRF Token Auto-Injection
        (function() {
            'use strict';

            var debug = <?= config('debug', 0) ? 'true' : 'false' ?>;
            var tokenElement = document.querySelector('meta[name="csrf-token"]');

            if (!tokenElement) {
                console.error('CSRF token meta tag not found!');
                return;
            }

            var tokenValue = tokenElement.getAttribute('content');
            if (debug) console.log('CSRF Token loaded:', tokenValue ? 'Yes' : 'No');

            // 全てのPOSTフォームにトークンを追加
            function addTokenToAllForms() {
                var forms = document.querySelectorAll('form[method="post"], form[method="POST"]');
                if (debug) console.log('Found ' + forms.length + ' POST forms');

                forms.forEach(function(form) {
                    if (form.querySelector('input[name="csrf_token"]')) return;

                    if (debug) console.log('Adding CSRF token to form:', form.name || form.id || 'unnamed');

                    var hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'csrf_token';
                    hiddenField.value = tokenValue;
                    form.insertBefore(hiddenField, form.firstChild);
                });
            }

            // jQueryが読み込まれるまで待機
            function waitForJQuery(callback) {
                if (typeof jQuery !== 'undefined') {
                    callback(jQuery);
                } else {
                    setTimeout(function() { waitForJQuery(callback); }, 50);
                }
            }

            // ページロード時の処理
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addTokenToAllForms);
            } else {
                addTokenToAllForms();
            }

            // jQuery依存の処理
            waitForJQuery(function($) {
                $(document).ready(addTokenToAllForms);
                $.ajaxSetup({
                    beforeSend: function(xhr, settings) {
                        if (settings.type && settings.type.toUpperCase() === 'POST') {
                            xhr.setRequestHeader('X-CSRF-Token', tokenValue);
                        }
                    }
                });
            });

            // フォーム送信時の最終チェック
            document.addEventListener('submit', function(e) {
                var form = e.target;
                if (form.tagName === 'FORM' && form.method.toUpperCase() === 'POST') {
                    var existingToken = form.querySelector('input[name="csrf_token"]');
                    if (!existingToken) {
                        if (debug) console.log('Adding CSRF token to form on submit');
                        var hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = 'csrf_token';
                        hiddenField.value = tokenValue;
                        form.insertBefore(hiddenField, form.firstChild);
                    }
                }
            }, true);
        })();
    </script>
</head>

<body id="<?= getv('f', 'mainpane') ?>" ondragstart="return false" <?php if (globalv('modx_textdir') === 'rtl') { ?> class="rtl" <?php } ?>>
    <div id="preLoader">
        <div class="preLoaderText"><?= style('ajax_loader') ?></div>
    </div>
