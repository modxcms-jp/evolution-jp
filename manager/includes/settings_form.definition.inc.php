<?php

/**
 * グローバル設定の画面の定義。EvoSettingsForm::definition() だけが読む。
 * 形は assets/docs/global-settings.md の「設定画面への表示」を参照。
 * 文言は読んだときの言語の lang() で引く（言語ファイルの文言は信頼する HTML として出る）。
 */
return [
    // まだ定義に移していないタブは 'legacy' に今のタブのファイル名だけを持つ（mutate_settings.dynamic.php が読む）
    [
        'id' => 'tabPage2',
        'title' => lang('settings_site'),
        'sections' => [
            [
                'title' => null,
                'rows' => [
                    [
                        'key' => 'site_status',
                        'type' => 'radio',
                        'label' => lang('sitestatus_title'),
                        'options' => ['1' => lang('online'), '0' => lang('offline')],
                        'desc' => lang('sitestatus_message'),
                    ],
                    [
                        'key' => 'site_name',
                        'type' => 'text',
                        'label' => lang('sitename_title'),
                        'desc' => lang('sitename_message'),
                    ],
                    [
                        'key' => 'site_slogan',
                        'type' => 'textarea',
                        'label' => lang('site_slogan_title'),
                        'attr' => ['id' => 'site_slogan', 'style' => 'display:block;width:300px;height:4em;'],
                        'desc' => lang('site_slogan_message'),
                    ],
                    [
                        'key' => 'fixed_site_url',
                        'type' => 'text',
                        'label' => lang('site_url_title'),
                        // 環境変数で固定・未設定・ホスト違いの警告と MODX_SITE_URL を差し込む（DB や環境の値は hsc() する）
                        'desc' => static function (array $values) {
                            $envSiteUrl = init::env_site_url();
                            $fixedSiteUrl = $envSiteUrl ?: init::parse_site_url($values['fixed_site_url'] ?? '');
                            $html = '';
                            if ($envSiteUrl) {
                                $html .= '<span style="color:#c00;">'
                                    . evo()->parseText(lang('site_url_env'), ['url' => hsc(env('SITE_URL'))])
                                    . '</span><br />';
                            }
                            if (!$fixedSiteUrl) {
                                $html .= '<span style="color:#c00;">' . lang('site_url_unset') . '</span><br />';
                            } elseif ($fixedSiteUrl['host'] !== init::request_host()) {
                                $html .= '<span style="color:#c00;">'
                                    . evo()->parseText(lang('site_url_differs'), ['host' => hsc(init::request_host())])
                                    . '</span><br />';
                            }
                            return $html . evo()->parseText(lang('site_url_message'), ['MODX_SITE_URL' => MODX_SITE_URL]);
                        },
                    ],
                    [
                        'key' => 'base_url',
                        'type' => 'text',
                        'label' => lang('base_url_title'),
                        'desc' => static function () {
                            return evo()->parseText(lang('base_url_message'), ['MODX_BASE_URL' => MODX_BASE_URL]);
                        },
                    ],
                    [
                        'key' => 'site_start',
                        'type' => 'text',
                        'size' => 10,
                        'label' => lang('sitestart_title'),
                        'desc' => lang('sitestart_message'),
                    ],
                    [
                        'key' => 'error_page',
                        'type' => 'text',
                        'size' => 10,
                        'label' => lang('errorpage_title'),
                        'desc' => lang('errorpage_message'),
                    ],
                    [
                        'key' => 'unauthorized_page',
                        'type' => 'text',
                        'size' => 10,
                        'label' => lang('unauthorizedpage_title'),
                        'desc' => lang('unauthorizedpage_message'),
                    ],
                    [
                        'key' => 'site_unavailable_page',
                        'type' => 'text',
                        'size' => 10,
                        'label' => lang('siteunavailable_page_title'),
                        'desc' => lang('siteunavailable_page_message'),
                    ],
                    [
                        'key' => 'site_unavailable_message',
                        'type' => 'lang_text',
                        'label' => lang('siteunavailable_title'),
                        'desc' => lang('siteunavailable_message'),
                        'langKey' => 'siteunavailable_message_default',
                        'reload' => 'reload_site_unavailable',
                        'id' => 'site_unavailable_message_textarea',
                        'attr' => ['style' => 'width:100%; height: 120px;display:block;'],
                    ],
                    [
                        'key' => 'custom_contenttype',
                        'type' => 'content_types',
                        'label' => lang('custom_contenttype_title'),
                        'desc' => lang('custom_contenttype_message'),
                    ],
                    [
                        'key' => 'xhtml_urls',
                        'type' => 'yesno',
                        'label' => lang('xhtml_urls_title'),
                        'default' => '0',
                        'desc' => lang('xhtml_urls_message'),
                    ],
                    [
                        'key' => 'modx_charset',
                        'type' => 'select',
                        'label' => lang('charset_title'),
                        'options' => ['UTF-8' => 'UTF-8'],
                        'attr' => ['style' => 'display:block;width:250px;'],
                        'br' => false,
                        'desc' => lang('charset_message'),
                    ],
                    [
                        'key' => 'server_offset_time',
                        'type' => 'select',
                        'label' => lang('serveroffset_title'),
                        // 値は秒、表示は時間（-24〜24）
                        'options' => static function () {
                            $options = [];
                            for ($hours = -24; $hours < 25; $hours++) {
                                $options[(string)($hours * 3600)] = (string)$hours;
                            }
                            return $options;
                        },
                        'desc' => static function (array $values) {
                            return sprintf(
                                lang('serveroffset_message'),
                                evo()->mb_strftime('%H:%M:%S', time()),
                                evo()->mb_strftime('%H:%M:%S', time() + (int)($values['server_offset_time'] ?? 0))
                            );
                        },
                    ],
                    [
                        'key' => 'server_protocol',
                        'type' => 'radio',
                        'label' => lang('server_protocol_title'),
                        'options' => ['http' => lang('server_protocol_http'), 'https' => lang('server_protocol_https')],
                        'desc' => lang('server_protocol_message'),
                    ],
                    [
                        'key' => 'track_visitors',
                        'type' => 'yesno',
                        'label' => lang('track_visitors_title'),
                        'desc' => lang('track_visitors_message'),
                    ],
                    ['type' => 'event', 'name' => 'OnSiteSettingsRender'],
                ],
            ],
        ],
    ],
    [
        'id' => 'tabPageDoc',
        'title' => 'リソース設定',
        'sections' => [
            [
                'title' => null,
                'rows' => [
                    [
                        'key' => 'default_template',
                        'type' => 'template',
                        'label' => lang('defaulttemplate_title'),
                        'desc' => lang('defaulttemplate_message'),
                    ],
                    [
                        'key' => 'auto_template_logic',
                        'type' => 'radio',
                        'label' => lang('defaulttemplate_logic_title'),
                        'options' => [
                            'system' => lang('defaulttemplate_logic_system_message'),
                            'parent' => lang('defaulttemplate_logic_parent_message'),
                            'sibling' => lang('defaulttemplate_logic_sibling_message'),
                        ],
                        'desc' => lang('defaulttemplate_logic_general_message'),
                    ],
                    [
                        'key' => 'auto_pub_date',
                        'type' => 'enabled',
                        'label' => '公開開始日時自動設定',
                        'default' => '0',
                        'desc' => '公開開始日時(pub_date)を設定しない場合、公開日時(publishedon)の値を取得し設定します。',
                    ],
                    [
                        'key' => 'publish_default',
                        'type' => 'yesno',
                        'label' => lang('defaultpublish_title'),
                        'desc' => lang('defaultpublish_message'),
                    ],
                    [
                        'key' => 'auto_menuindex',
                        'type' => 'yesno',
                        'label' => lang('defaultmenuindex_title'),
                        'desc' => lang('defaultmenuindex_message'),
                    ],
                    [
                        'key' => 'docid_incrmnt_method',
                        'type' => 'radio',
                        'label' => lang('docid_incrmnt_method_title'),
                        'options' => [
                            '0' => lang('docid_incrmnt_method_0'),
                            '1' => lang('docid_incrmnt_method_1'),
                            '2' => lang('docid_incrmnt_method_2'),
                        ],
                        'desc' => lang('docid_incrmnt_method_message'),
                    ],
                    [
                        'key' => 'enable_draft',
                        'type' => 'enabled',
                        'label' => lang('enable_draft_title'),
                        'desc' => lang('enable_draft_message'),
                    ],
                    [
                        'key' => 'cache_default',
                        'type' => 'yesno',
                        'label' => lang('defaultcache_title'),
                        'desc' => lang('defaultcache_message'),
                    ],
                    [
                        'key' => 'search_default',
                        'type' => 'yesno',
                        'label' => lang('defaultsearch_title'),
                        'desc' => lang('defaultsearch_message'),
                    ],
                    ['type' => 'event', 'name' => 'OnSiteSettingsRender'],
                ],
            ],
        ],
    ],
    [
        'id' => 'tabPageCache',
        'title' => 'キャッシュ設定',
        'sections' => [
            [
                'title' => null,
                'rows' => [
                    [
                        'key' => 'cache_type',
                        'type' => 'radio',
                        'label' => lang('setting_cache_type'),
                        'options' => [
                            '1' => lang('setting_cache_type_standard'),
                            '2' => lang('setting_cache_type_bypass'),
                            '0' => lang('setting_cache_type_disabled'),
                        ],
                        'desc' => lang('setting_cache_type_desc'),
                    ],
                    [
                        'key' => 'disable_cache_at_login',
                        'type' => 'radio',
                        'label' => lang('setting_disable_cache_at_login'),
                        // 値と表示が逆（0 が有効）
                        'options' => ['0' => lang('enabled'), '1' => lang('disabled')],
                        'desc' => lang('setting_disable_cache_at_login_desc'),
                    ],
                    [
                        'key' => 'individual_cache',
                        'type' => 'enabled',
                        'label' => lang('setting_individual_cache'),
                        'desc' => lang('setting_individual_cache_desc'),
                    ],
                    [
                        'key' => 'conditional_get',
                        'type' => 'enabled',
                        'label' => lang('setting_conditional_get'),
                        'desc' => lang('setting_conditional_get_desc'),
                    ],
                    [
                        'key' => 'legacy_cache',
                        'type' => 'enabled',
                        'label' => '旧式のキャッシュ機構',
                        'desc' => '古いスニペット・プラグインは<a href="https://www.google.co.jp/search?q=modx+aliasListing+ddocumentMap+ocumentListing" target="_blank">旧式のキャッシュ機構</a>が有効でないと動作しないことがあります。その場合はこの設定を有効にしてください。このキャッシュ機構はサイトの規模が大きくなると負荷が高くなるため注意が必要です。',
                    ],
                    ['type' => 'event', 'name' => 'OnSiteSettingsRender'],
                ],
            ],
        ],
    ],
    [
        'id' => 'tabPage3',
        'title' => lang('settings_furls'),
        'sections' => [
            [
                'title' => null,
                'rows' => [
                    [
                        'key' => 'friendly_urls',
                        'type' => 'yesno',
                        'label' => lang('friendlyurls_title'),
                        'desc' => lang('friendlyurls_message'),
                    ],
                    [
                        'key' => 'friendly_url_prefix',
                        'type' => 'text',
                        'size' => 50,
                        'label' => lang('friendlyurlsprefix_title'),
                        'desc' => lang('friendlyurlsprefix_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    [
                        'key' => 'friendly_url_suffix',
                        'type' => 'text',
                        'size' => 50,
                        'label' => lang('friendlyurlsuffix_title'),
                        'desc' => lang('friendlyurlsuffix_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    [
                        'key' => 'make_folders',
                        'type' => 'yesno',
                        'label' => lang('make_folders_title'),
                        'desc' => lang('make_folders_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    [
                        'key' => 'suffix_mode',
                        'type' => 'yesno',
                        'label' => lang('suffix_mode_title'),
                        'desc' => lang('suffix_mode_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    [
                        'key' => 'friendly_alias_urls',
                        'type' => 'yesno',
                        'label' => lang('friendly_alias_title'),
                        'desc' => lang('friendly_alias_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    [
                        'key' => 'use_alias_path',
                        'type' => 'yesno',
                        'label' => lang('use_alias_path_title'),
                        'desc' => lang('use_alias_path_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    [
                        'key' => 'allow_duplicate_alias',
                        'type' => 'yesno',
                        'label' => lang('duplicate_alias_title'),
                        'desc' => lang('duplicate_alias_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    [
                        'key' => 'automatic_alias',
                        'type' => 'radio',
                        'label' => lang('automatic_alias_title'),
                        'options' => [
                            '1' => 'pagetitle',
                            '2' => 'numbering in each folder',
                            '0' => lang('disabled'),
                        ],
                        'desc' => lang('automatic_alias_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                    // この行のセルだけ余白がある（今の画面のまま）
                    ['type' => 'event', 'name' => 'OnFriendlyURLSettingsRender', 'padded' => true],
                ],
            ],
        ],
    ],
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
