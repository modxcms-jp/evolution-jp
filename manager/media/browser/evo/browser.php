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
require_once __DIR__ . '/lib/BrowserConfig.php';

$modx = new DocumentParser();
$modx->getSettings();

// 管理セッションは常に許可。ウェブユーザーのセッションは、システム設定
// 「ウェブユーザーのファイルブラウザ利用」(rb_webuser) が有効なときだけ許可する（既定は無効）。
if (!sessionv('mgrValidated')) {
    if (!sessionv('webValidated') || (int) $modx->config('rb_webuser') !== 1) {
        http_response_code(403);
        die('<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.');
    }
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
    'images' => lang('fb_title_images'),
    'media' => lang('fb_title_media'),
    'files' => lang('fb_title_files'),
];

// JS(js/i18n.js の t())へ渡すキー。JS で使うキーはここだけに列挙する
$jsLangKeys = [
    'fb_loading', 'preview', 'fb_rename', 'delete',
    'fb_selected_count', 'fb_no_match', 'fb_empty_folder', 'fb_load_failed', 'fb_no_subfolders',
    'fb_upload_failed', 'fb_network_error',
    'fb_move_partial_failed', 'fb_move_failed', 'fb_rename_failed', 'fb_rename_prompt',
    'fb_confirm_delete_file', 'fb_confirm_delete_folder', 'fb_confirm_delete_files', 'fb_delete_failed', 'fb_delete_partial_failed',
    'fb_mkdir_prompt', 'fb_mkdir_failed',
    'open', 'move', 'fb_pick_this_file', 'fb_new_folder', 'fb_upload', 'fb_context_menu',
    'fb_copy', 'fb_cut', 'fb_paste', 'fb_copy_partial_failed', 'fb_copy_failed',
    'fb_download', 'fb_download_preparing', 'fb_download_failed',
    'fb_edit', 'fb_edit_overwrite_confirm', 'fb_edit_saving', 'fb_edit_save_failed', 'fb_edit_load_failed', 'fb_edit_encode_failed',
    'fb_err_too_large',
];
$jsMessages = [];
foreach ($jsLangKeys as $jsLangKey) {
    $jsMessages[$jsLangKey] = lang($jsLangKey);
}

$browserConfig = new BrowserConfig($modx);
$jsConfig = [
    // モーダル断片は/manager/index.php上で動くため、相対パスの基準が異なる
    'apiUrl' => $isModal ? 'media/browser/evo/api.php' : 'api.php',
    'type' => $type,
    'csrfToken' => function_exists('getCurrentCsrfToken') ? getCurrentCsrfToken() : '',
    'initialFolder' => getv('folder', ''),
    'initialSelect' => getv('select', ''),
    // zip を作れない環境では、2 件以上のダウンロードを出さない(1 件はそのまま送れる)
    'zip' => $browserConfig->canCreateZip(),
    // 画像の編集: 編集できる拡張子、書き出しの長い辺の上限(px)、保存できるファイルの大きさの上限(バイト)。API も同じ値で確かめる
    'editableExtensions' => BrowserConfig::EDITABLE_EXTENSIONS,
    'editMaxDimension' => BrowserConfig::EDIT_MAX_DIMENSION,
    'editMaxPixels' => BrowserConfig::EDIT_MAX_PIXELS,
    'maxUploadSize' => $browserConfig->maxUploadSize(),
    'lang' => $jsMessages,
];

$markup = '';
$markup .= '<div class="evo-fb" id="evoFileBrowser" data-config="' . htmlspecialchars(json_encode($jsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '">';
$markup .= '  <div class="evo-fb-toolbar">';
$markup .= '    <div class="evo-fb-path" data-role="path"></div>';
$markup .= '    <div class="evo-fb-toolbar-actions">';
$markup .= '      <input type="search" class="evo-fb-search" data-role="search" placeholder="' . hsc(lang('fb_search_placeholder')) . '">';
$markup .= '      <select class="evo-fb-sort" data-role="sort">';
$markup .= '        <option value="name">' . hsc(lang('fb_sort_name')) . '</option>';
$markup .= '        <option value="mtime">' . hsc(lang('fb_sort_mtime')) . '</option>';
$markup .= '        <option value="size">' . hsc(lang('fb_sort_size')) . '</option>';
$markup .= '      </select>';
$markup .= '      <button type="button" class="evo-fb-btn evo-fb-icon-toggle" data-role="view-toggle" title="' . hsc(lang('fb_toggle_view')) . '">☷</button>';
$markup .= '      <button type="button" class="evo-fb-btn" data-action="mkdir">' . hsc(lang('fb_new_folder')) . '</button>';
$markup .= '      <button type="button" class="evo-fb-btn" data-action="upload">' . hsc(lang('fb_upload')) . '</button>';
$markup .= '      <button type="button" class="evo-fb-btn" data-action="paste" hidden>' . hsc(lang('fb_paste')) . '</button>';
$markup .= '      <input type="file" data-role="file-input" multiple hidden>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-selectionbar" data-role="selectionbar" hidden>';
$markup .= '    <span data-role="selection-count"></span>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="bulk-copy">' . hsc(lang('fb_copy')) . '</button>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="bulk-cut">' . hsc(lang('fb_cut')) . '</button>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="bulk-download">' . hsc(lang('fb_download')) . '</button>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="bulk-move">' . hsc(lang('move')) . '</button>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="bulk-delete">' . hsc(lang('delete')) . '</button>';
$markup .= '    <button type="button" class="evo-fb-btn" data-action="clear-selection">' . hsc(lang('fb_clear_selection')) . '</button>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-body">';
$markup .= '    <div class="evo-fb-tree" data-role="tree"></div>';
$markup .= '    <div class="evo-fb-main" data-role="dropzone" tabindex="-1">';
$markup .= '      <div class="evo-fb-grid" data-role="grid"><div class="evo-fb-empty" data-role="loading">' . hsc(lang('fb_loading')) . '</div></div>';
$markup .= '      <div class="evo-fb-dropoverlay" data-role="dropoverlay">' . hsc(lang('fb_drop_to_upload')) . '</div>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-uploads" data-role="uploads"></div>';
$markup .= '  <div class="evo-fb-overlay" data-role="move-overlay" hidden>';
$markup .= '    <div class="evo-fb-overlay-panel">';
$markup .= '      <div class="evo-fb-overlay-header">' . hsc(lang('fb_move_dest_title')) . '</div>';
$markup .= '      <div class="evo-fb-path" data-role="move-path"></div>';
$markup .= '      <div class="evo-fb-grid evo-fb-grid-folders" data-role="move-grid"></div>';
$markup .= '      <div class="evo-fb-overlay-footer">';
$markup .= '        <button type="button" class="evo-fb-btn" data-role="move-cancel">' . hsc(lang('cancel')) . '</button>';
$markup .= '        <button type="button" class="evo-fb-btn evo-fb-btn-primary" data-role="move-confirm">' . hsc(lang('fb_move_here')) . '</button>';
$markup .= '      </div>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-overlay" data-role="preview-overlay" hidden>';
$markup .= '    <div class="evo-fb-overlay-panel evo-fb-preview-panel">';
$markup .= '      <img class="evo-fb-preview-img" data-role="preview-img" alt="">';
$markup .= '      <div class="evo-fb-preview-meta" data-role="preview-meta"></div>';
$markup .= '      <div class="evo-fb-overlay-footer">';
$markup .= '        <button type="button" class="evo-fb-btn" data-role="preview-close">' . hsc(lang('close')) . '</button>';
$markup .= '        <button type="button" class="evo-fb-btn" data-role="preview-edit" hidden>' . hsc(lang('fb_edit')) . '</button>';
$markup .= '        <button type="button" class="evo-fb-btn evo-fb-btn-primary" data-role="preview-pick">' . hsc(lang('fb_pick_this_file')) . '</button>';
$markup .= '      </div>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '  <div class="evo-fb-overlay" data-role="edit-overlay" hidden>';
$markup .= '    <div class="evo-fb-overlay-panel evo-fb-edit-panel" role="dialog" aria-modal="true" aria-labelledby="evoFbEditTitle" data-role="edit-panel">';
$markup .= '      <div class="evo-fb-overlay-header" id="evoFbEditTitle">' . hsc(lang('fb_edit_title')) . ' <span class="evo-fb-edit-name" data-role="edit-name"></span></div>';
$markup .= '      <div class="evo-fb-edit-body">';
$markup .= '      <div class="evo-fb-edit-stage" data-role="edit-stage"><div class="evo-fb-edit-wrap" data-role="edit-wrap"><canvas data-role="edit-canvas"></canvas><div class="evo-fb-edit-crop" data-role="edit-crop" hidden></div></div></div>';
$markup .= '      <div class="evo-fb-edit-controls">';
$markup .= '        <fieldset class="evo-fb-edit-group"><legend>' . hsc(lang('fb_edit_rotate')) . '</legend>';
$markup .= '          <button type="button" class="evo-fb-btn" data-role="edit-rotate-left">' . hsc(lang('fb_edit_rotate_left')) . '</button>';
$markup .= '          <button type="button" class="evo-fb-btn" data-role="edit-rotate-right">' . hsc(lang('fb_edit_rotate_right')) . '</button>';
$markup .= '        </fieldset>';
$markup .= '        <fieldset class="evo-fb-edit-group"><legend>' . hsc(lang('fb_edit_crop')) . '</legend>';
$markup .= '          <label>X <input type="number" min="0" step="1" data-role="edit-crop-x"></label>';
$markup .= '          <label>Y <input type="number" min="0" step="1" data-role="edit-crop-y"></label>';
$markup .= '          <label>' . hsc(lang('fb_edit_width')) . ' <input type="number" min="1" step="1" data-role="edit-crop-w"></label>';
$markup .= '          <label>' . hsc(lang('fb_edit_height')) . ' <input type="number" min="1" step="1" data-role="edit-crop-h"></label>';
$markup .= '          <button type="button" class="evo-fb-btn" data-role="edit-crop-clear">' . hsc(lang('fb_edit_crop_clear')) . '</button>';
$markup .= '          <div class="evo-fb-edit-note">' . hsc(lang('fb_edit_crop_hint')) . '</div>';
$markup .= '        </fieldset>';
$markup .= '        <fieldset class="evo-fb-edit-group"><legend>' . hsc(lang('fb_edit_resize')) . '</legend>';
$markup .= '          <label>' . hsc(lang('fb_edit_width')) . ' <input type="number" min="1" step="1" data-role="edit-out-w"></label>';
$markup .= '          <label>' . hsc(lang('fb_edit_height')) . ' <input type="number" min="1" step="1" data-role="edit-out-h"></label>';
$markup .= '          <label class="evo-fb-edit-check"><input type="checkbox" data-role="edit-keep-ratio" checked> ' . hsc(lang('fb_edit_keep_ratio')) . '</label>';
$markup .= '          <div class="evo-fb-edit-note">' . hsc(sprintf(lang('fb_edit_resize_hint'), BrowserConfig::EDIT_MAX_DIMENSION)) . '</div>';
$markup .= '        </fieldset>';
$markup .= '        <fieldset class="evo-fb-edit-group"><legend>' . hsc(lang('fb_edit_save_as')) . '</legend>';
$markup .= '          <label class="evo-fb-edit-check"><input type="radio" name="evoFbEditMode" value="copy" data-role="edit-mode" checked> ' . hsc(lang('fb_edit_save_copy')) . '</label>';
$markup .= '          <label class="evo-fb-edit-check"><input type="radio" name="evoFbEditMode" value="overwrite" data-role="edit-mode"> ' . hsc(lang('fb_edit_save_overwrite')) . '</label>';
$markup .= '        </fieldset>';
$markup .= '      </div>';
$markup .= '      <div class="evo-fb-edit-note">' . hsc(lang('fb_edit_exif_notice')) . '</div>';
$markup .= '      </div>';
$markup .= '      <div class="evo-fb-edit-note" role="status" data-role="edit-status"></div>';
$markup .= '      <div class="evo-fb-edit-error" role="alert" data-role="edit-error" hidden></div>';
$markup .= '      <div class="evo-fb-overlay-footer">';
$markup .= '        <button type="button" class="evo-fb-btn" data-role="edit-cancel">' . hsc(lang('cancel')) . '</button>';
$markup .= '        <button type="button" class="evo-fb-btn evo-fb-btn-primary" data-role="edit-save">' . hsc(lang('save')) . '</button>';
$markup .= '      </div>';
$markup .= '    </div>';
$markup .= '  </div>';
$markup .= '</div>';

if ($isModal) {
    header('Content-Type: text/html; charset=utf-8');
    // シェル(shell.js)がモーダルへ差し込む断片であることを示す
    header('X-Evo-Pane: 1');
    echo '<h1>' . hsc($labels[$type]) . '</h1>';
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
<html lang="<?= hsc(lang('lang_attribute', 'en')) ?>">
<head>
    <meta charset="<?= $modx->config('modx_charset') ?>">
    <title><?= hsc($labels[$type]) ?></title>
    <link rel="stylesheet" href="../../style/<?= htmlspecialchars($theme) ?>/style.css">
    <link rel="stylesheet" href="filebrowser.css?<?= $cssVersion ?>">
</head>
<body class="evo-fb-standalone">
<?= $markup ?>
<script type="module" src="filebrowser.js?<?= $jsVersion ?>"></script>
</body>
</html>
<?php
