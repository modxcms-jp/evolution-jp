<?php
/**
 * ファイル管理(a=31)。ファイルブラウザ(manager/media/browser/evo/)を、管理モードで、メインの領域に出す。
 * 画面を出すだけで、ファイルを読み書きする操作はすべてファイルブラウザの API(api.php)が受ける。
 * 開くフォルダと、選んで見せるファイルは folder と select で指定する(ルートからの相対パス)。
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/BrowserConfig.php';
require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/PathGuard.php';
require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/BrowserScope.php';
require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/ManageScope.php';
require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/BrowserView.php';

$fb_config = new BrowserConfig($modx);
// 管理モードを使ってよいかの条件は、API と同じもの(ManageScope::authorize())を使う。画面を出すだけで、API の認可の代わりにはならない
$fb_scope = ManageScope::authorize($modx, $fb_config);
if (is_array($fb_scope)) {
    alert()->setError(3);
    alert()->dumpError();
}

$fb_folder = getv('folder', '');
$fb_select = getv('select', '');

echo BrowserView::managePane(
    BrowserView::jsConfig($modx, $fb_config, [
        'entry' => 'manage',
        'apiUrl' => 'media/browser/evo/api.php',
        'apiParams' => ['scope' => 'manage'],
        'initialFolder' => is_string($fb_folder) ? $fb_folder : '',
        'initialSelect' => is_string($fb_select) ? $fb_select : '',
        'rootLabel' => $fb_scope->rootLabel(),
        'maxUploadSize' => $fb_scope->maxUploadSize(),
    ]),
    lang('manage_files')
);
