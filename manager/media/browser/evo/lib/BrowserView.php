<?php

/**
 * ファイルブラウザの画面(HTML と、JS へ渡す設定)。画面の本体(markup())は共通で、置かれる場所ごとの外側だけを分ける。
 * 範囲の違いは JS の設定の値(apiParams など)と入口の JS(entry)だけで表し、管理モードにだけあるダイアログは外側で足す。
 */
class BrowserView
{
    /**
     * 入口の JS(js/<entry>.js)と、開いたときの既定の状態(view: grid|list、tree: open|closed)。
     * 利用者が切り替えた状態は js/prefs.js が入口ごとに残し、そちらが優先する
     */
    const ENTRIES = [
        // ピッカー: 画像などを見て選ぶので、サムネイルとツリーを出す
        'picker' => ['view' => 'grid', 'tree' => 'open'],
        // ファイル管理(a=31): 名前・大きさ・日時で見比べるので 1 行ずつ。広く使えるようツリーは畳む
        'manage' => ['view' => 'list', 'tree' => 'closed'],
    ];

    /** JS(js/i18n.js の t())へ渡すキー。JS で使うキーはここだけに列挙する */
    const JS_LANG_KEYS = [
        'fb_loading', 'preview', 'fb_rename', 'delete',
        'fb_selected_count', 'fb_no_match', 'fb_empty_folder',
        'fb_col_name', 'fb_col_mtime', 'fb_col_type', 'fb_col_size', 'fb_type_folder', 'fb_type_file', 'fb_type_file_noext', 'fb_load_failed', 'fb_no_subfolders',
        'fb_network_error',
        'fb_move_partial_failed', 'fb_move_failed', 'fb_rename_failed', 'fb_rename_prompt',
        'fb_confirm_delete_file', 'fb_confirm_delete_folder', 'fb_confirm_delete_files', 'fb_delete_failed', 'fb_delete_partial_failed',
        'fb_mkdir_prompt', 'fb_mkdir_failed',
        'open', 'move', 'fb_pick_this_file', 'fb_new_folder', 'fb_upload', 'fb_context_menu',
        'fb_copy', 'fb_cut', 'fb_paste', 'fb_copy_partial_failed', 'fb_copy_failed',
        'fb_download', 'fb_download_preparing', 'fb_download_failed',
        'fb_edit', 'fb_edit_overwrite_confirm', 'fb_edit_saving', 'fb_edit_save_failed', 'fb_edit_load_failed', 'fb_edit_encode_failed',
        'fb_err_too_large', 'fb_confirm_overwrite',
        // 管理モードだけが使う(js/manage.js・js/textEditor.js)
        'fb_text_loading', 'fb_err_text_link', 'fb_text_readonly_write', 'fb_text_readonly_encoding',
        'fb_text_saving', 'fb_text_saved', 'fb_text_save_failed', 'fb_text_discard_confirm',
        'fb_new_file', 'fb_new_file_prompt', 'fb_new_file_failed',
        'fb_unzip', 'fb_confirm_unzip', 'fb_unzip_failed', 'fb_unzip_done', 'fb_unzip_skipped', 'fb_unzip_entry_failed',
    ];

    /**
     * JS の設定。画面の data-config に JSON で入れる。apiUrl は呼び出し側で相対パスの基準が違う(モーダル断片は /manager/index.php 上で動く)。
     * apiParams は JS がすべての要求に付ける(ピッカーは ['type' => 種類]、管理モードは ['scope' => 'manage'])。maxUploadSize の null は本体では制限しない
     */
    public static function jsConfig(DocumentParser $modx, BrowserConfig $config, array $options)
    {
        if (!isset(self::ENTRIES[$options['entry']])) {
            throw new InvalidArgumentException('Unknown entry: ' . $options['entry']);
        }

        $messages = [];
        foreach (self::JS_LANG_KEYS as $key) {
            $messages[$key] = lang($key);
        }

        return [
            'entry' => $options['entry'],
            // 開いたときの既定の状態(ENTRIES)。JS は入口を見て分けず、この値を使う
            'defaults' => self::ENTRIES[$options['entry']],
            'apiUrl' => $options['apiUrl'],
            'apiParams' => $options['apiParams'],
            'csrfToken' => function_exists('getCurrentCsrfToken') ? getCurrentCsrfToken() : '',
            'initialFolder' => $options['initialFolder'] ?? '',
            'initialSelect' => $options['initialSelect'] ?? '',
            'rootLabel' => $options['rootLabel'],
            // zip を作れない環境では、2 件以上のダウンロードを出さない(1 件はそのまま送れる)
            'zip' => $config->canCreateZip(),
            // 画像の編集: 編集できる拡張子、長い辺の上限(px)、大きさの上限。API も同じ値で確かめる
            'editableExtensions' => BrowserConfig::EDITABLE_EXTENSIONS,
            'editMaxDimension' => BrowserConfig::EDIT_MAX_DIMENSION,
            'editMaxPixels' => BrowserConfig::EDIT_MAX_PIXELS,
            // 最初からエディターで開く拡張子。ほかのファイルも、サーバーが中身をテキストと認めれば開ける
            'textExtensions' => BrowserConfig::TEXT_EXTENSIONS,
            'maxUploadSize' => $options['maxUploadSize'],
            'lang' => $messages,
        ];
    }

    /** 画面の本体(範囲によらず同じ)。用途ごとのダイアログなどは $extras で受けて末尾へ入れる。$classes は本体の要素に足す class */
    public static function markup(array $jsConfig, $extras = '', $classes = '')
    {
        $markup = '';
        $markup .= '<div class="evo-fb' . ($classes === '' ? '' : ' ' . hsc($classes)) . '" id="evoFileBrowser" data-config="' . htmlspecialchars(json_encode($jsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '">';
        $markup .= '  <div class="evo-fb-toolbar">';
        $markup .= '    <button type="button" class="evo-fb-btn evo-fb-icon-toggle" data-action="toggle-tree" aria-expanded="true" aria-controls="evoFbTree" title="' . hsc(lang('fb_toggle_tree')) . '" aria-label="' . hsc(lang('fb_toggle_tree')) . '"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M3 4h18v16H3zm7 2v12h9V6z"/></svg></button>';
        $markup .= '    <div class="evo-fb-path" data-role="path"></div>';
        $markup .= '    <div class="evo-fb-toolbar-actions">';
        $markup .= '      <input type="search" class="evo-fb-search" data-role="search" placeholder="' . hsc(lang('fb_search_placeholder')) . '">';
        $markup .= '      <select class="evo-fb-sort" data-role="sort">';
        $markup .= '        <option value="name">' . hsc(lang('fb_sort_name')) . '</option>';
        $markup .= '        <option value="mtime">' . hsc(lang('fb_sort_mtime')) . '</option>';
        $markup .= '        <option value="size">' . hsc(lang('fb_sort_size')) . '</option>';
        $markup .= '        <option value="type">' . hsc(lang('fb_sort_type')) . '</option>';
        $markup .= '      </select>';
        $markup .= '      <button type="button" class="evo-fb-btn evo-fb-icon-toggle" data-role="view-toggle" title="' . hsc(lang('fb_toggle_view')) . '" aria-label="' . hsc(lang('fb_toggle_view')) . '"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z"/></svg></button>';
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
        $markup .= '    <div class="evo-fb-tree" id="evoFbTree" data-role="tree"></div>';
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
        $markup .= '      <video class="evo-fb-preview-media" data-role="preview-video" controls preload="metadata" hidden></video>';
        $markup .= '      <audio class="evo-fb-preview-audio" data-role="preview-audio" controls preload="metadata" hidden></audio>';
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
        $markup .= $extras;
        $markup .= '</div>';

        return $markup;
    }

    /** 管理モードにだけあるダイアログ(テキストの表示・編集)。markup() の $extras へ渡す。見た目はテーマの CSS をそのまま使う */
    public static function manageExtras()
    {
        $html = '';
        $html .= '  <div class="evo-fb-overlay" data-role="text-overlay" hidden>';
        $html .= '    <div class="evo-fb-overlay-panel evo-fb-text-panel" role="dialog" aria-modal="true" aria-labelledby="evoFbTextTitle" data-role="text-panel">';
        $html .= '      <div class="evo-fb-overlay-header" id="evoFbTextTitle">' . hsc(lang('fb_text_title')) . '</div>';
        $html .= '      <div class="code-editor evo-fb-text-editor">';
        $html .= '        <div class="code-editor-header">';
        $html .= '          <span class="code-editor-title" data-role="text-name"></span>';
        $html .= '          <label class="code-editor-options">' . hsc(lang('fb_text_wrap')) . ' <input type="checkbox" class="inputBox" checked data-role="text-wrap"></label>';
        $html .= '        </div>';
        $html .= '        <textarea class="phptextarea evo-fb-text-area" dir="ltr" wrap="soft" spellcheck="false" aria-labelledby="evoFbTextTitle" data-role="text-area"></textarea>';
        $html .= '      </div>';
        $html .= '      <div class="evo-fb-edit-note" role="status" data-role="text-status"></div>';
        $html .= '      <div class="evo-fb-edit-error" role="alert" data-role="text-error" hidden></div>';
        $html .= '      <div class="evo-fb-overlay-footer">';
        $html .= '        <button type="button" class="evo-fb-btn" data-role="text-close">' . hsc(lang('close')) . '</button>';
        $html .= '        <button type="button" class="evo-fb-btn evo-fb-btn-primary" data-role="text-save" disabled>' . hsc(lang('save')) . '</button>';
        $html .= '      </div>';
        $html .= '    </div>';
        $html .= '  </div>';

        return $html;
    }

    /** ピッカーの画面の断片。EvoShell のモーダルが /manager/index.php の上へ差し込む */
    public static function modalFragment(array $jsConfig, $title)
    {
        header('Content-Type: text/html; charset=utf-8');
        // シェル(shell.js)がモーダルへ差し込む断片であることを示す
        header('X-Evo-Pane: 1');
        echo '<h1>' . hsc($title) . '</h1>';
        echo '<link rel="stylesheet" href="media/browser/evo/filebrowser.css?' . self::cssVersion() . '">';
        echo self::markup($jsConfig);
        echo self::entryScripts($jsConfig);
    }

    /** メインの領域(メニューの「ファイル管理」、a=31)に出す断片。/manager/index.php が、fetch でも直に開かれたときでも、そのまま出す */
    public static function managePane(array $jsConfig, $title)
    {
        $html = '<div class="evo-fb-pane">';
        $html .= '<h1>' . hsc($title) . '</h1>';
        $html .= '<link rel="stylesheet" href="media/browser/evo/filebrowser.css?' . self::cssVersion() . '">';
        $html .= self::markup($jsConfig, self::manageExtras(), 'evo-fb-page');
        $html .= self::entryScripts($jsConfig);
        $html .= '</div>';

        return $html;
    }

    /**
     * 入口の JS(モジュール)と、その入口を呼び直すインラインの script。shell.js の executeScripts は同一 src を再実行しないため、
     * 2 回目以降に断片を差し込んだときはインラインの script が初期化を担う(全体の読み込みでは、モジュールが自分で初期化する)。
     */
    private static function entryScripts(array $jsConfig)
    {
        $entry = js_literal($jsConfig['entry']);

        return '<script type="module" src="media/browser/evo/js/' . hsc($jsConfig['entry']) . '.js?' . self::jsVersion($jsConfig['entry']) . '"></script>'
            . '<script>(function (entries) { if (entries && entries[' . $entry . ']) { entries[' . $entry . '](); } })(window.EvoFileBrowserEntries);</script>';
    }

    /** テーマの CSS を読み込んだ単独の完全な HTML(chromeless フォールバックのポップアップや、単独の確認用) */
    public static function standalonePage(DocumentParser $modx, array $jsConfig, $title, $extras = '')
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
<?= self::markup($jsConfig, $extras) ?>
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
