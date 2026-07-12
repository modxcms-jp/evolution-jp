<?php

/**
 * ファイルブラウザ エントリ。
 *
 * ?modal=1 のときは EvoShell モーダル用のHTML断片を、それ以外はテーマCSSを読み込んだ
 * スタンドアロン完全HTML(chromelessフォールバックのポップアップ用)を出力する。
 */

define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);

$self = 'manager/media/browser/evo/browser.php';
$base_path = str_replace($self, '', str_replace('\\', '/', __FILE__));
require_once $base_path . 'manager/includes/document.parser.class.inc.php';

$modx = new DocumentParser();
$modx->getSettings();

if (!isset($_SESSION['mgrValidated']) && !isset($_SESSION['webValidated'])) {
    http_response_code(403);
    die('<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.');
}
if ((int) $modx->config('use_browser') !== 1) {
    http_response_code(403);
    die('<b>PERMISSION DENIED</b><br /><br />You do not have permission to access this file!');
}

// Type=(大文字)は旧mcpuk互換。カスタム設定(imanager_url等)の移行漏れを救済する
$type = getv('type', getv('Type', 'images'));
if (!in_array($type, ['images', 'media', 'files'], true)) {
    $type = 'images';
}
$isModal = getv('modal') === '1';

// CSSとJSは別々に更新されるため、キャッシュバスターもファイルごとに計算する
$jsVersion = filemtime(__DIR__ . '/filebrowser.js');
$cssVersion = filemtime(__DIR__ . '/filebrowser.css');
$labels = [
    'images' => 'イメージブラウザ',
    'media' => 'メディアブラウザ',
    'files' => 'ファイルブラウザ',
];

$jsConfig = [
    // モーダル断片は/manager/index.php上で動くため、相対パスの基準が異なる
    'apiUrl' => $isModal ? 'media/browser/evo/api.php' : 'api.php',
    'type' => $type,
    'csrfToken' => function_exists('getCurrentCsrfToken') ? getCurrentCsrfToken() : '',
];

$markup = '';
$markup .= '<div class="evo-fb" id="evoFileBrowser" data-config="' . htmlspecialchars(json_encode($jsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '">';
$markup .= '  <div class="evo-fb-toolbar">';
$markup .= '    <div class="evo-fb-path" data-role="path"></div>';
$markup .= '    <div class="evo-fb-toolbar-actions">';
$markup .= '      <input type="search" class="evo-fb-search" data-role="search" placeholder="ファイル名で検索">';
$markup .= '      <select class="evo-fb-sort" data-role="sort">';
$markup .= '        <option value="name">名前順</option>';
$markup .= '        <option value="mtime">更新日順</option>';
$markup .= '        <option value="size">サイズ順</option>';
$markup .= '      </select>';
$markup .= '      <button type="button" class="evo-fb-btn evo-fb-icon-toggle" data-role="view-toggle" title="表示切替">☷</button>';
$markup .= '      <button type="button" class="evo-fb-btn" data-action="mkdir">新規フォルダ</button>';
$markup .= '      <button type="button" class="evo-fb-btn" data-action="upload">アップロード</button>';
$markup .= '      <input type="file" data-role="file-input" multiple hidden>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-selectionbar" data-role="selectionbar" hidden>';
$markup .= '    <span data-role="selection-count"></span>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="bulk-move">移動</button>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="bulk-delete">削除</button>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="clear-selection">選択解除</button>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-body">';
$markup .= '    <div class="evo-fb-tree" data-role="tree"></div>';
$markup .= '    <div class="evo-fb-main" data-role="dropzone">';
$markup .= '      <div class="evo-fb-grid" data-role="grid"><div class="evo-fb-empty" data-role="loading">読み込み中...</div></div>';
$markup .= '      <div class="evo-fb-dropoverlay" data-role="dropoverlay">ここにドロップしてアップロード</div>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-uploads" data-role="uploads"></div>';
$markup .= '  <div class="evo-fb-overlay" data-role="move-overlay" hidden>';
$markup .= '    <div class="evo-fb-overlay-panel">';
$markup .= '      <div class="evo-fb-overlay-header">移動先フォルダを選択</div>';
$markup .= '      <div class="evo-fb-path" data-role="move-path"></div>';
$markup .= '      <div class="evo-fb-grid evo-fb-grid-folders" data-role="move-grid"></div>';
$markup .= '      <div class="evo-fb-overlay-footer">';
$markup .= '        <button type="button" class="evo-fb-btn" data-role="move-cancel">キャンセル</button>';
$markup .= '        <button type="button" class="evo-fb-btn evo-fb-btn-primary" data-role="move-confirm">ここに移動</button>';
$markup .= '      </div>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-overlay" data-role="preview-overlay" hidden>';
$markup .= '    <div class="evo-fb-overlay-panel evo-fb-preview-panel">';
$markup .= '      <img class="evo-fb-preview-img" data-role="preview-img" alt="">';
$markup .= '      <div class="evo-fb-preview-meta" data-role="preview-meta"></div>';
$markup .= '      <div class="evo-fb-overlay-footer">';
$markup .= '        <button type="button" class="evo-fb-btn" data-role="preview-close">閉じる</button>';
$markup .= '        <button type="button" class="evo-fb-btn evo-fb-btn-primary" data-role="preview-pick">このファイルを選択</button>';
$markup .= '      </div>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '</div>';

if ($isModal) {
    header('Content-Type: text/html; charset=utf-8');
    // シェル(shell.js)がモーダルへ差し込む断片であることを示す
    header('X-Evo-Pane: 1');
    echo '<h1>' . htmlspecialchars($labels[$type]) . '</h1>';
    echo '<link rel="stylesheet" href="media/browser/evo/filebrowser.css?' . $cssVersion . '">';
    echo $markup;
    echo '<script type="module" src="media/browser/evo/filebrowser.js?' . $jsVersion . '"></script>';
    // shell.jsのexecuteScriptsは同一srcを再実行しないため、2回目以降の
    // モーダルオープンはこのインラインscriptが初期化を担う
    echo '<script>if (window.EvoFileBrowserInit) { window.EvoFileBrowserInit(); }</script>';
    exit;
}

$theme = $modx->config('manager_theme');
?>
<!DOCTYPE html>
<html lang="<?= globalv('modx_lang_attribute', 'ja') ?>">
<head>
    <meta charset="<?= $modx->config('modx_charset') ?>">
    <title><?= htmlspecialchars($labels[$type]) ?></title>
    <link rel="stylesheet" href="../../style/<?= htmlspecialchars($theme) ?>/style.css">
    <link rel="stylesheet" href="filebrowser.css?<?= $cssVersion ?>">
</head>
<body class="evo-fb-standalone">
<?= $markup ?>
<script type="module" src="filebrowser.js?<?= $jsVersion ?>"></script>
</body>
</html>
<?php
