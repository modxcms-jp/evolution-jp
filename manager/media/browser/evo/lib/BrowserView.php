<?php

/**
 * ファイルブラウザの画面(HTML と、JS へ渡す設定)。
 * 画面の本体(markup())は共通で、置かれる場所ごとの外側(モーダルの断片・単独のページ)だけを分ける。
 * 範囲(ピッカー・管理モード)の違いは、JS の設定の値(apiParams など)と、入口の JS(entry)だけで表す。
 * 管理モードにだけあるボタンやダイアログは、markup() の中で範囲を見て分けず、管理モード用の外側で足す。
 */
class BrowserView
{
    /** 入口の JS(js/<entry>.js)。ピッカーと管理モード */
    const ENTRIES = ['picker', 'manage'];

    /**
     * JS(js/i18n.js の t())へ渡すキー。JS で使うキーはここだけに列挙する
     */
    const JS_LANG_KEYS = [
        'fb_loading', 'preview', 'fb_rename', 'delete',
        'fb_selected_count', 'fb_no_match', 'fb_empty_folder', 'fb_load_failed', 'fb_no_subfolders',
        'fb_network_error',
        'fb_move_partial_failed', 'fb_move_failed', 'fb_rename_failed', 'fb_rename_prompt',
        'fb_confirm_delete_file', 'fb_confirm_delete_folder', 'fb_confirm_delete_files', 'fb_delete_failed', 'fb_delete_partial_failed',
        'fb_mkdir_prompt', 'fb_mkdir_failed',
        'open', 'move', 'fb_pick_this_file', 'fb_new_folder', 'fb_upload', 'fb_context_menu',
        'fb_copy', 'fb_cut', 'fb_paste', 'fb_copy_partial_failed', 'fb_copy_failed',
        'fb_download', 'fb_download_preparing', 'fb_download_failed',
        'fb_edit', 'fb_edit_overwrite_confirm', 'fb_edit_saving', 'fb_edit_save_failed', 'fb_edit_load_failed', 'fb_edit_encode_failed',
        'fb_err_too_large', 'fb_confirm_overwrite',
    ];

    /**
     * JS の設定。サーバーで作り、画面の data-config に JSON で入れる。
     *
     * $options:
     * - entry: 入口の JS。picker(js/picker.js)か manage(js/manage.js)
     * - apiUrl: API の URL(モーダル断片は /manager/index.php 上で動くため、相対パスの基準が呼び出し側で違う)
     * - apiParams: JS がすべての要求に付けるパラメーター(ピッカーは ['type' => 種類]、管理モードは ['scope' => 'manage'])
     * - initialFolder / initialSelect: 開くフォルダーと、選んで見せるファイル名
     * - rootLabel: ツリーとパンくずの一番上に出す名前
     * - maxUploadSize: アップロードの上限(バイト)。null は本体では制限しない
     *
     * @return array
     */
    public static function jsConfig(DocumentParser $modx, BrowserConfig $config, array $options)
    {
        if (!in_array($options['entry'], self::ENTRIES, true)) {
            throw new InvalidArgumentException('Unknown entry: ' . $options['entry']);
        }

        $messages = [];
        foreach (self::JS_LANG_KEYS as $key) {
            $messages[$key] = lang($key);
        }

        return [
            'entry' => $options['entry'],
            'apiUrl' => $options['apiUrl'],
            'apiParams' => $options['apiParams'],
            'csrfToken' => function_exists('getCurrentCsrfToken') ? getCurrentCsrfToken() : '',
            'initialFolder' => $options['initialFolder'] ?? '',
            'initialSelect' => $options['initialSelect'] ?? '',
            'rootLabel' => $options['rootLabel'],
            // zip を作れない環境では、2 件以上のダウンロードを出さない(1 件はそのまま送れる)
            'zip' => $config->canCreateZip(),
            // 画像の編集: 編集できる拡張子、書き出しの長い辺の上限(px)、保存できるファイルの大きさの上限(バイト)。API も同じ値で確かめる
            'editableExtensions' => BrowserConfig::EDITABLE_EXTENSIONS,
            'editMaxDimension' => BrowserConfig::EDIT_MAX_DIMENSION,
            'editMaxPixels' => BrowserConfig::EDIT_MAX_PIXELS,
            'maxUploadSize' => $options['maxUploadSize'],
            'lang' => $messages,
        ];
    }

    /**
     * 画面の本体(ツールバー・選択バー・ツリー・一覧・ダイアログの枠)。範囲によらず同じ
     *
     * @param array $jsConfig jsConfig() の戻り値
     * @return string
     */
    public static function markup(array $jsConfig)
    {
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
        $markup .= '  <div class="evo-fb-notice" role="status" data-role="notice" hidden>' . hsc(lang('fb_not_writable')) . '</div>';
        $markup .= '  <div class="evo-fb-body">';
        $markup .= '    <div class="evo-fb-tree" data-role="tree"></div>';
        $markup .= '    <div class="evo-fb-main" data-role="dropzone" tabindex="-1">';
        $markup .= '      <div class="evo-fb-grid" data-role="grid"><div class="evo-fb-empty" data-role="loading">' . hsc(lang('fb_loading')) . '</div></div>';
        $markup .= '      <div class="evo-fb-dropoverlay" data-role="dropoverlay">' . hsc(lang('fb_drop_to_upload')) . '</div>';
        $markup .= '    </div>';
        $markup .= '  </div>';
        $markup .= '  <div class="evo-fb-uploads" data-role="uploads"></div>';
        $markup .= '  <div class="evo-fb-overlay" data-role="move-overlay" hidden>';
        $markup .= '    <div class="evo-fb-overlay-panel" role="dialog" aria-modal="true" aria-labelledby="evoFbMoveTitle" data-role="move-panel">';
        $markup .= '      <div class="evo-fb-overlay-header" id="evoFbMoveTitle">' . hsc(lang('fb_move_dest_title')) . '</div>';
        $markup .= '      <div class="evo-fb-path" data-role="move-path"></div>';
        $markup .= '      <div class="evo-fb-grid evo-fb-grid-folders" data-role="move-grid"></div>';
        $markup .= '      <div class="evo-fb-overlay-footer">';
        $markup .= '        <button type="button" class="evo-fb-btn" data-role="move-cancel">' . hsc(lang('cancel')) . '</button>';
        $markup .= '        <button type="button" class="evo-fb-btn evo-fb-btn-primary" data-role="move-confirm">' . hsc(lang('fb_move_here')) . '</button>';
        $markup .= '      </div>';
        $markup .= '    </div>';
        $markup .= '  </div>';
        $markup .= '  <div class="evo-fb-overlay" data-role="preview-overlay" hidden>';
        $markup .= '    <div class="evo-fb-overlay-panel evo-fb-preview-panel" role="dialog" aria-modal="true" aria-label="' . hsc(lang('preview')) . '" data-role="preview-panel">';
        $markup .= '      <img class="evo-fb-preview-img" data-role="preview-img" alt="">';
        $markup .= '      <div class="evo-fb-preview-meta" data-role="preview-meta"></div>';
        $markup .= '      <div class="evo-fb-overlay-footer">';
        $markup .= '        <button type="button" class="evo-fb-btn" data-role="preview-close">' . hsc(lang('close')) . '</button>';
        $markup .= '        <button type="button" class="evo-fb-btn" data-role="preview-edit" hidden>' . hsc(lang('fb_edit')) . '</button>';
        $markup .= '        <span class="evo-fb-preview-actions" data-role="preview-actions"></span>';
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

        return $markup;
    }

    /**
     * ピッカーの画面の断片。EvoShell のモーダルが /manager/index.php の上へ差し込む
     *
     * @param array $jsConfig jsConfig() の戻り値(apiUrl は media/browser/evo/api.php)
     * @param string $title 見出し
     */
    public static function modalFragment(array $jsConfig, $title)
    {
        header('Content-Type: text/html; charset=utf-8');
        // シェル(shell.js)がモーダルへ差し込む断片であることを示す
        header('X-Evo-Pane: 1');
        echo '<h1>' . hsc($title) . '</h1>';
        echo '<link rel="stylesheet" href="media/browser/evo/filebrowser.css?' . self::cssVersion() . '">';
        echo self::markup($jsConfig);
        echo '<script type="module" src="media/browser/evo/js/' . hsc($jsConfig['entry']) . '.js?' . self::jsVersion($jsConfig['entry']) . '"></script>';
        // shell.jsのexecuteScriptsは同一srcを再実行しないため、2回目以降の
        // モーダルオープンはこのインラインscriptが初期化を担う。入口の登録表から、この画面の入口だけを呼ぶ
        $entry = js_literal($jsConfig['entry']);
        echo '<script>(function (entries) { if (entries && entries[' . $entry . ']) { entries[' . $entry . '](); } })(window.EvoFileBrowserEntries);</script>';
    }

    /**
     * テーマの CSS を読み込んだ単独の完全な HTML(chromeless フォールバックのポップアップや、単独の確認用)
     *
     * @param array $jsConfig jsConfig() の戻り値(apiUrl は api.php)
     * @param string $title 見出し
     */
    public static function standalonePage(DocumentParser $modx, array $jsConfig, $title)
    {
        $theme = $modx->config('manager_theme');
        ?>
<!DOCTYPE html>
<html lang="<?= hsc(lang('lang_attribute', 'en')) ?>">
<head>
    <meta charset="<?= $modx->config('modx_charset') ?>">
    <title><?= hsc($title) ?></title>
    <link rel="stylesheet" href="../../style/<?= hsc($theme) ?>/style.css">
    <link rel="stylesheet" href="filebrowser.css?<?= self::cssVersion() ?>">
</head>
<body class="evo-fb-standalone">
<?= self::markup($jsConfig) ?>
<script type="module" src="js/<?= hsc($jsConfig['entry']) ?>.js?<?= self::jsVersion($jsConfig['entry']) ?>"></script>
</body>
</html>
<?php
    }

    // CSSとJSは別々に更新されるため、キャッシュバスターもファイルごとに計算する
    private static function cssVersion()
    {
        return filemtime(__DIR__ . '/../filebrowser.css');
    }

    // 入口の JS(js/<entry>.js)か、それが読み込む filebrowser.js のどちらかが更新されたら変わる
    private static function jsVersion($entry)
    {
        return max(filemtime(__DIR__ . '/../filebrowser.js'), filemtime(__DIR__ . '/../js/' . $entry . '.js'));
    }
}
