<?php
global $modx;
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

// AJAX断片応答(X-Evo-Pane)では<html>・メニュー・ツリーを出力しない。
// 出力すると#mainPane内に入れ子で挿入され、画面崩れとローディング残りの原因になる。
// processorやerrorHandler::dumpError()など、自前でheader/footerを読み込む箇所もここで吸収する
if (evoIsPaneResponse()) {
    return;
}

if ($modx->config('remember_last_tab') != 2) {
    setcookie(
        'webfxtab_childPane',
        get_strvar('tab', '1'), [
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
    <?= manager_theme_stylesheets() ?>
    <link rel="stylesheet" type="text/css" href="<?= manager_asset('media/style/common/shell.css') ?>" />
    <link rel="stylesheet" type="text/css" href="<?= manager_asset('media/style/common/selection-dock.css') ?>" />
    <style>
        :root {
            --shell-menu-height: <?= (int)$modx->config('manager_menu_height', 58) ?>px;
            --shell-tree-width: <?= (int)$modx->config('manager_tree_width', 260) ?>px;
        }
    </style>
    <!-- OnManagerMainFrameHeaderHTMLBlock -->
    <?php if (is_array($evtOut)) {
        echo implode("\n", $evtOut);
    } ?>
    <script src="<?= manager_asset('media/script/evo-tooltip.js') ?>" type="text/javascript"></script>
    <script src="<?= manager_asset('media/script/evo-dialog.js') ?>" type="text/javascript"></script>
    <script type="text/javascript" src="<?= manager_asset('media/script/tabpane.js') ?>"></script>
    <!-- CSRFトークン自動付与。shell.jsがform.submitを退避するため先に読み込む -->
    <script type="text/javascript" src="<?= manager_asset('media/script/csrf.js') ?>"></script>
    <!-- jQuery 置き換え用の共通部品（Evo.ready / Evo.fetch / Evo.post） -->
    <script type="text/javascript" src="<?= manager_asset('media/script/evo.js') ?>"></script>
    <script type="text/javascript" src="<?= manager_asset('media/script/shell.js') ?>"></script>
    <script type="text/javascript" src="<?= manager_asset('media/browser/evo-file-picker.js') ?>"></script>
    <script type="text/javascript">
        // 旧フレーム参照(top.main / parent.main)互換: シェルではmainは自ウィンドウ
        window.main = window;

        if (window.EvoShell) {
            EvoShell.unsavedMessage = <?= js_literal(lang('warning_not_saved')) ?>;
            EvoShell.closeLabel = <?= js_literal(lang('close')) ?>;
        }
        EvoDialog.cancelLabel = <?= js_literal(lang('cancel')) ?>;

        var treeopen = <?= $modx->config('tree_pane_open_default', 1) ?>;

        var documentDirty = false;
        var dontShowWorker = false;
        var baseurl = '<?= MODX_BASE_URL ?>';

        Evo.ready(function () {
            // メニュー/ツリー部品のscriptが定義済みになった後に初期化する
            if (treeopen === 0 && window.mainMenu) mainMenu.hideTreeFrame();
            // tree.ca は既定(開く)へここで戻さない。Evo.ready は画面のスクリプトより後に動き、
            // 移動などの画面が設定した値を上書きしてしまう。戻すのは shell.js の applyFragment()
            // (画面のスクリプトより前) と frames/tree.php (ツリーの読み込み時) が行う
        });

        // 編集系アクションで入力変更時にdocumentDirtyを立てる。
        // AJAX遷移後の断片にも適用できるよう関数化し、shell.jsからも呼ばれる
        // 同じ関数を渡すため、再バインドしても二重には登録されない
        function evoMarkDirty() {
            documentDirty = true;
        }

        window.evoBindDirtyTracking = function (action) {
            switch (parseInt(action, 10)) {
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
                    var mainPane = document.getElementById('mainPane');
                    if (mainPane) {
                        mainPane.querySelectorAll('input,textarea,select').forEach(function (el) {
                            if (!el.matches('#field_template,#which_editor,#stay')) {
                                el.addEventListener('change', evoMarkDirty);
                            }
                        });
                    }
                    gotosave = false;
                    break;
            }
        };

        Evo.ready(function() {
            evoBindDirtyTracking(<?= (int)manager()->action ?>);
        });

        Evo.ready(function() {
            var preLoader = document.getElementById('preLoader');
            if (preLoader) preLoader.style.display = 'none';
        });

        window.addEventListener('beforeunload', function(e) {
            if (documentDirty) {
                // 戻り値だけでは確認が出ないため、preventDefault と returnValue も使う
                var message = window.EvoShell ? window.EvoShell.unsavedMessage : <?= js_literal(lang('warning_not_saved')) ?>;
                e.preventDefault();
                e.returnValue = message;
                return message;
            }
            var actions = document.getElementById('actions');
            if (actions) actions.style.display = 'none';
            document.querySelectorAll('input,textarea,select').forEach(function (el) {
                el.classList.add('readonly');
            });
            var preLoader = document.getElementById('preLoader');
            if (preLoader) preLoader.style.display = '';
            if (!dontShowWorker && top.mainMenu) top.mainMenu.work();
        });
    </script>
</head>

<?php
// クロームレスモード: QuickManager等がiframeへ埋め込む際は、
// 旧mainフレーム相当のコンテンツのみを返し、メニュー/ツリー/SPA機能を出さない
$evoShellChromeless = (bool)anyv('quickmanager') || getv('chromeless') === '1';
?>
<body id="<?= preg_replace('@[^a-zA-Z0-9_-]@', '', get_strvar('f', 'mainpane')) ?: 'mainpane' ?>" class="<?= $evoShellChromeless ? '' : 'evo-shell' ?><?= globalv('modx_textdir') === 'rtl' ? ' rtl' : '' ?>">
    <script type="text/javascript">
        // 旧ondragstart="return false"(body属性)相当。画像・リンク・選択テキストの
        // ネイティブドラッグを防ぐ。ただしbody属性方式はD&Dソート(dragdrop-sort.js)の
        // dragstartまでバブリングで潰すため、draggable明示要素は除外して許可する
        document.body.addEventListener('dragstart', function (e) {
            var el = e.target && e.target.nodeType === 1 ? e.target : e.target.parentElement;
            if (el && el.closest && el.closest('[draggable="true"]')) {
                return;
            }
            e.preventDefault();
        });
    </script>
    <div id="preLoader">
        <div class="preLoaderText"><?= style('ajax_loader') ?></div>
    </div>
<?php
if (!$evoShellChromeless) {
    // シェル: 旧framesetのメニュー/ツリーを同一ページの部品として描画する
    $tmp = ['action' => manager()->action];
    evo()->invokeEvent('OnManagerPreFrameLoader', $tmp);

    // 部品を独立スコープで実行し、部品側のローカル変数($tpl,$ph等)が
    // 後続のアクションファイル(同一スコープで実行される)を汚染しないようにする
    $evoShellIncludePartial = static function (string $evoShellPartialPath): void {
        // 部品が読むグローバル変数だけを明示する。設定値は config() で読む
        global $modx, $_lang, $modx_version, $modx_full_appname, $modx_textdir, $modx_manager_charset;
        include_once $evoShellPartialPath;
    };
    $evoShellIncludePartial(MODX_MANAGER_PATH . 'frames/menu.php');
    $evoShellIncludePartial(MODX_MANAGER_PATH . 'frames/tree.php');
    unset($evoShellIncludePartial);

    $tmp = ['action' => manager()->action];
    evo()->invokeEvent('OnManagerFrameLoader', $tmp);
}
// footer.inc.phpが</main>を出力してよいかの目印(header.inc.phpを介さず
// 独自に<html>を出力するアクションが将来追加された場合、footerだけが呼ばれるため)
if (!defined('EVO_SHELL_MAIN_OPENED')) {
    define('EVO_SHELL_MAIN_OPENED', true);
}
?>
    <main id="mainPane">
