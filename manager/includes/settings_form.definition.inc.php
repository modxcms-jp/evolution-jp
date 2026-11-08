<?php

/**
 * グローバル設定の画面の定義。EvoSettingsForm::definition() だけが読む。
 * 形は assets/docs/global-settings.md の「設定画面への表示」を参照。
 * 文言は読んだときの言語の lang() で引く（言語ファイルの文言は信頼する HTML として出る）。
 */
return [
    // まだ定義に移していないタブは 'legacy' に今のタブのファイル名だけを持つ（mutate_settings.dynamic.php が読む）
    ['id' => 'tabPage2', 'title' => lang('settings_site'), 'legacy' => 'tab1_site_settings.inc.php'],
    ['id' => 'tabPageDoc', 'title' => 'リソース設定', 'legacy' => 'tab1_doc_settings.inc.php'],
    ['id' => 'tabPageCache', 'title' => 'キャッシュ設定', 'legacy' => 'tab2_cache_settings.inc.php'],
    ['id' => 'tabPage3', 'title' => lang('settings_furls'), 'legacy' => 'tab2_furl_settings.inc.php'],
    ['id' => 'tabPage4', 'title' => lang('settings_users'), 'legacy' => 'tab3_user_settings.inc.php'],
    ['id' => 'tabPage5', 'title' => lang('settings_ui'), 'legacy' => 'tab4_manager_settings.inc.php'],
    [
        'id' => 'tabPage7',
        'title' => lang('settings_misc'),
        'sections' => [
            [
                'title' => null,
                'rows' => [
                    [
                        'key' => 'filemanager_path',
                        'type' => 'path',
                        'label' => lang('filemanager_path_title'),
                        'desc' => lang('filemanager_path_message'),
                        'defaultPath' => EvoSettingsForm::BASE_PATH_TOKEN,
                        'absHint' => true,
                    ],
                    [
                        'key' => 'export_dir',
                        'type' => 'path',
                        'label' => lang('export_dir_title'),
                        'desc' => lang('export_dir_message'),
                        'defaultPath' => EvoSettingsForm::BASE_PATH_TOKEN . 'temp/export/',
                    ],
                    [
                        'key' => 'upload_files',
                        'type' => 'text',
                        'label' => lang('uploadable_files_title'),
                        'desc' => lang('uploadable_files_message'),
                    ],
                    [
                        'key' => 'upload_images',
                        'type' => 'text',
                        'label' => lang('uploadable_images_title'),
                        'desc' => lang('uploadable_images_message'),
                    ],
                    [
                        'key' => 'upload_media',
                        'type' => 'text',
                        'label' => lang('uploadable_media_title'),
                        'desc' => lang('uploadable_media_message'),
                    ],
                    [
                        'key' => 'upload_maxsize',
                        'type' => 'text',
                        'label' => lang('upload_maxsize_title'),
                        'desc' => static function () {
                            $limit = manager()->getUploadMaxsize();
                            return $limit > 0
                                ? sprintf(lang('upload_maxsize_message'), evo()->nicesize($limit))
                                : '';
                        },
                    ],
                    [
                        'key' => 'new_file_permissions',
                        'type' => 'text',
                        'size' => 4,
                        'label' => lang('new_file_permissions_title'),
                        'desc' => lang('new_file_permissions_message'),
                    ],
                    [
                        'key' => 'new_folder_permissions',
                        'type' => 'text',
                        'size' => 4,
                        'label' => lang('new_folder_permissions_title'),
                        'desc' => lang('new_folder_permissions_message'),
                    ],
                    [
                        'key' => 'clean_uploaded_filename',
                        'type' => 'yesno',
                        'label' => lang('clean_uploaded_filename'),
                        'desc' => lang('clean_uploaded_filename_message'),
                    ],
                    [
                        'key' => 'image_limit_width',
                        'type' => 'text',
                        'label' => lang('a17_image_limit_width_title'),
                        'after' => 'px',
                        'desc' => lang('a17_image_limit_width_message'),
                    ],
                    [
                        'key' => 'use_browser',
                        'type' => 'yesno',
                        'label' => lang('rb_title'),
                        'desc' => lang('rb_message'),
                    ],
                    [
                        'key' => 'strip_image_paths',
                        'type' => 'yesno',
                        'label' => lang('settings_strip_image_paths_title'),
                        'desc' => lang('settings_strip_image_paths_message'),
                        'showIf' => ['use_browser', '1'],
                    ],
                    [
                        'key' => 'rb_webuser',
                        'type' => 'yesno',
                        'label' => lang('rb_webuser_title'),
                        'desc' => lang('rb_webuser_message'),
                        'showIf' => ['use_browser', '1'],
                    ],
                    [
                        'key' => 'rb_base_dir',
                        'type' => 'path',
                        'label' => lang('rb_base_dir_title'),
                        'desc' => lang('rb_base_dir_message'),
                        'defaultPath' => static function () {
                            return EvoSettingsForm::BASE_PATH_TOKEN
                                . (is_dir(MODX_BASE_PATH . 'content') ? 'content/' : 'assets/');
                        },
                        'absHint' => true,
                        'showIf' => ['use_browser', '1'],
                    ],
                    [
                        'key' => 'rb_base_url',
                        'type' => 'text',
                        'label' => lang('rb_base_url_title'),
                        'before' => static function (array $values) {
                            return hsc($values['site_url'] ?? '');
                        },
                        'desc' => lang('rb_base_url_message'),
                        'showIf' => ['use_browser', '1'],
                    ],
                    ['type' => 'event', 'name' => 'OnMiscSettingsRender'],
                    [
                        'key' => 'convert_datauri_to_file',
                        'type' => 'yesno',
                        'label' => lang('setting_convert_datauri_to_file'),
                        'desc' => lang('setting_convert_datauri_to_file_desc'),
                    ],
                ],
            ],
        ],
    ],
];
