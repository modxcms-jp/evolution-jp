<?php

/**
 * グローバル設定の画面の定義。EvoSettingsForm::definition() だけが読む。
 * 形は assets/docs/global-settings.md の「設定画面への表示」を参照。
 * 文言は読んだときの言語の lang() で引く（言語ファイルの文言は信頼する HTML として出る）。
 */
return [
    [
        'id' => 'settingsTabSite',
        'event' => 'OnSiteSettingsRender',
        'title' => lang('settings_site'),
        'sections' => [
            [
                'title' => lang('settings_section_basics'),
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
                        'key' => 'track_visitors',
                        'type' => 'yesno',
                        'label' => lang('track_visitors_title'),
                        'desc' => lang('track_visitors_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_url'),
                'rows' => [
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
                        'key' => 'server_protocol',
                        'type' => 'radio',
                        'label' => lang('server_protocol_title'),
                        'options' => ['http' => lang('server_protocol_http'), 'https' => lang('server_protocol_https')],
                        'desc' => lang('server_protocol_message'),
                    ],
                    [
                        'key' => 'xhtml_urls',
                        'type' => 'yesno',
                        'label' => lang('xhtml_urls_title'),
                        'default' => '0',
                        'desc' => lang('xhtml_urls_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_special_pages'),
                'rows' => [
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
                ],
            ],
            [
                'title' => lang('settings_section_charset_time'),
                'rows' => [
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
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabResources',
        'title' => lang('settings_resources'),
        'sections' => [
            [
                'title' => lang('settings_section_new_resources'),
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
                        'key' => 'publish_default',
                        'type' => 'yesno',
                        'label' => lang('defaultpublish_title'),
                        'desc' => lang('defaultpublish_message'),
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
                    [
                        'key' => 'auto_menuindex',
                        'type' => 'yesno',
                        'label' => lang('defaultmenuindex_title'),
                        'desc' => lang('defaultmenuindex_message'),
                    ],
                    [
                        'key' => 'auto_pub_date',
                        'type' => 'enabled',
                        'label' => lang('auto_pub_date_title'),
                        'default' => '0',
                        'desc' => lang('auto_pub_date_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_creating_editing'),
                'rows' => [
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
                        'key' => 'custom_contenttype',
                        'type' => 'content_types',
                        'label' => lang('custom_contenttype_title'),
                        'desc' => lang('custom_contenttype_message'),
                    ],
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabCache',
        'title' => lang('settings_cache'),
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
                        'label' => lang('setting_legacy_cache'),
                        'desc' => lang('setting_legacy_cache_desc'),
                    ],
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabFurls',
        'event' => 'OnFriendlyURLSettingsRender',
        // このタブのプラグインの欄のセルだけ余白がある（今の画面のまま）
        'eventPadded' => true,
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
                            '2' => lang('automatic_alias_opt_numbering'),
                            '0' => lang('disabled'),
                        ],
                        'desc' => lang('automatic_alias_message'),
                        'showIf' => ['friendly_urls', '1'],
                    ],
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabSecurity',
        'event' => 'OnUserSettingsRender',
        'title' => lang('settings_users'),
        'sections' => [
            [
                'title' => lang('settings_section_users_groups'),
                'rows' => [
                    [
                        'key' => 'default_role',
                        'type' => 'select',
                        'label' => lang('default_role_title'),
                        'options' => static function () {
                            return EvoSettingsForm::roles();
                        },
                        'br' => false,
                        'desc' => '<div>' . lang('default_role_message') . '</div>',
                    ],
                    [
                        'key' => 'allow_mgr2web',
                        'type' => 'yesno',
                        'label' => lang('allow_mgr2web_title'),
                        'desc' => lang('allow_mgr2web_message'),
                    ],
                    [
                        'key' => 'use_udperms',
                        'type' => 'yesno',
                        'label' => lang('udperms_title'),
                        'desc' => lang('udperms_message'),
                    ],
                    [
                        'key' => 'udperms_allowroot',
                        'type' => 'yesno',
                        'label' => lang('udperms_allowroot_title'),
                        'desc' => lang('udperms_allowroot_message'),
                        'showIf' => ['use_udperms', '1'],
                    ],
                    [
                        'key' => 'tree_show_protected',
                        'type' => 'yesno',
                        'label' => lang('tree_show_protected'),
                        'desc' => lang('tree_show_protected_message'),
                        'showIf' => ['use_udperms', '1'],
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_login'),
                'rows' => [
                    [
                        'key' => 'failed_login_attempts',
                        'type' => 'text',
                        'size' => 3,
                        'label' => lang('failed_login_title'),
                        'desc' => lang('failed_login_message'),
                    ],
                    [
                        'key' => 'blocked_minutes',
                        'type' => 'text',
                        'size' => 7,
                        'label' => lang('blocked_minutes_title'),
                        'desc' => lang('blocked_minutes_message'),
                    ],
                    [
                        'key' => 'use_captcha',
                        'type' => 'yesno',
                        'label' => lang('captcha_title'),
                        // GD が無いと、どちらのラジオも選べず「いいえ」になる
                        'disabled' => !extension_loaded('gd'),
                        'value' => static function (array $values) {
                            return extension_loaded('gd') ? (string)($values['use_captcha'] ?? '') : '0';
                        },
                        'desc' => lang('captcha_message'),
                    ],
                    [
                        'key' => 'captcha_words',
                        'type' => 'lang_text',
                        'field' => 'text',
                        'label' => lang('captcha_words_title'),
                        'desc' => lang('captcha_words_message'),
                        'langKey' => 'captcha_words_default',
                        'reload' => 'reload_captcha_words',
                        'id' => 'captcha_words_input',
                        'attr' => ['style' => 'width:400px'],
                        'hiddenBr' => true,
                        'showIf' => ['use_captcha', '1'],
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_errors'),
                'rows' => [
                    [
                        'key' => 'error_reporting',
                        'type' => 'radio',
                        'label' => lang('a17_error_reporting_title'),
                        'options' => [
                            '0' => lang('a17_error_reporting_opt0'),
                            '1' => lang('a17_error_reporting_opt1'),
                            '2' => lang('a17_error_reporting_opt2'),
                            '99' => lang('a17_error_reporting_opt99'),
                        ],
                        'default' => '1',
                        'desc' => lang('a17_error_reporting_msg'),
                    ],
                    [
                        'key' => 'warning_visibility',
                        'type' => 'radio',
                        'label' => lang('warning_visibility'),
                        'options' => [
                            '0' => lang('administrators'),
                            '2' => lang('a17_warning_opt2'),
                            '1' => lang('everybody'),
                        ],
                        'desc' => lang('warning_visibility_message'),
                    ],
                    [
                        'key' => 'send_errormail',
                        'type' => 'radio',
                        'label' => lang('send_errormail_title'),
                        'options' => [
                            '0' => lang('send_errormail_none'),
                            '3' => 'error',
                            '2' => 'error + warning',
                            '1' => 'error + warning + information',
                        ],
                        'default' => '0',
                        // 送信者は DB の値なので hsc() する
                        'desc' => static function (array $values) {
                            return evo()->parseText(
                                lang('send_errormail_message'),
                                ['emailsender' => hsc($values['emailsender'] ?? '')]
                            );
                        },
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_template_variables'),
                'rows' => [
                    [
                        'key' => 'enable_bindings',
                        'type' => 'yesno',
                        'label' => lang('enable_bindings_title'),
                        'desc' => lang('enable_bindings_message'),
                    ],
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabMail',
        'title' => lang('settings_mail'),
        'sections' => [
            [
                'title' => lang('settings_section_mail_sending'),
                'rows' => [
                    [
                        'key' => 'emailsender',
                        'type' => 'text',
                        'label' => lang('emailsender_title'),
                        'desc' => lang('emailsender_message'),
                    ],
                    [
                        'key' => 'email_method',
                        'type' => 'radio',
                        'label' => lang('email_method_title'),
                        'options' => ['mail' => lang('email_method_mail'), 'smtp' => lang('email_method_smtp')],
                        'default' => 'mail',
                        'inline' => true,
                        'desc' => '<br/>',
                    ],
                    [
                        'key' => 'smtp_auth',
                        'type' => 'yesno',
                        'label' => lang('smtp_auth_title'),
                        'default' => '0',
                        'inline' => true,
                        'desc' => '<br/>',
                        'showIf' => ['email_method', 'smtp'],
                    ],
                    [
                        'key' => 'smtp_host',
                        'type' => 'text',
                        'label' => lang('smtp_host_title'),
                        'default' => 'smtp.example.com',
                        'attr' => ['style' => 'width: 250px;'],
                        'br' => false,
                        'showIf' => ['email_method', 'smtp'],
                    ],
                    [
                        'key' => 'smtp_port',
                        'type' => 'text',
                        'label' => lang('smtp_port_title'),
                        'default' => '25',
                        'attr' => ['style' => 'width: 250px;'],
                        'br' => false,
                        'showIf' => ['email_method', 'smtp'],
                    ],
                    [
                        'key' => 'smtp_username',
                        'type' => 'text',
                        'label' => lang('smtp_username_title'),
                        // 既定は送信者のメールアドレス
                        'default' => static function (array $values) {
                            return (string)($values['emailsender'] ?? '');
                        },
                        'attr' => ['style' => 'width: 250px;'],
                        'br' => false,
                        'showIf' => ['email_method', 'smtp'],
                    ],
                    [
                        'key' => 'smtppw',
                        'type' => 'password',
                        'label' => lang('smtp_password_title'),
                        'showIf' => ['email_method', 'smtp'],
                    ],
                    [
                        'key' => 'smtp_secure',
                        'type' => 'radio',
                        'label' => lang('smtp_secure_title'),
                        'options' => ['' => lang('none'), 'ssl' => 'ssl', 'tls' => 'tls'],
                        'default' => '',
                        'inline' => true,
                        'showIf' => ['email_method', 'smtp'],
                    ],
                    [
                        'key' => 'modxmailer_log',
                        'type' => 'yesno',
                        'label' => lang('modxmailer_log_title'),
                        'default' => '0',
                        'desc' => lang('modxmailer_log_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_mail_texts'),
                'rows' => [
                    [
                        'key' => 'emailsubject',
                        'type' => 'lang_text',
                        'field' => 'text',
                        'label' => lang('emailsubject_title'),
                        'desc' => lang('emailsubject_message'),
                        'langKey' => 'emailsubject_default',
                        'reload' => 'reload_emailsubject',
                        'id' => 'emailsubject_field',
                        'hiddenBr' => true,
                    ],
                    [
                        'key' => 'signupemail_message',
                        'type' => 'lang_text',
                        'label' => lang('signupemail_title'),
                        'desc' => lang('signupemail_message'),
                        'langKey' => 'system_email_signup',
                        'reload' => 'reload_signupemail_message',
                        'id' => 'signupemail_message_textarea',
                        'attr' => ['style' => 'width:100%; height: 120px;'],
                        'hiddenBr' => true,
                    ],
                    [
                        'key' => 'websignupemail_message',
                        'type' => 'lang_text',
                        'label' => lang('websignupemail_title'),
                        'desc' => lang('websignupemail_message'),
                        'langKey' => 'system_email_websignup',
                        'reload' => 'reload_websignupemail_message',
                        'id' => 'websignupemail_message_textarea',
                        'attr' => ['style' => 'width:100%; height: 120px;'],
                        'hiddenBr' => true,
                    ],
                    [
                        'key' => 'webpwdreminder_message',
                        'type' => 'lang_text',
                        'label' => lang('webpwdreminder_title'),
                        'desc' => lang('webpwdreminder_message'),
                        'langKey' => 'system_email_webreminder',
                        'reload' => 'reload_system_email_webreminder_message',
                        'reloadId' => 'reload_system_email_webreminder_select',
                        'id' => 'system_email_webreminder_textarea',
                        'attr' => ['style' => 'width:100%; height: 120px;'],
                        'hiddenBr' => true,
                    ],
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabManager',
        'title' => lang('settings_ui'),
        'sections' => [
            [
                'title' => lang('settings_section_display'),
                'rows' => [
                    [
                        'key' => 'manager_theme',
                        'scope' => ['system', 'user', 'profile'],
                        'type' => 'select',
                        'label' => lang('manager_theme'),
                        'options' => static function () {
                            return EvoSettingsForm::themes();
                        },
                        'desc' => lang('manager_theme_message'),
                        // ユーザーのテーマを変えたら、再読み込みのために時刻を hidden に入れる
                        'userAttr' => ['onchange' => 'document.userform.theme_refresher.value = Date.parse(new Date())'],
                        'userDesc' => '<input type="hidden" name="theme_refresher" value="" />' . lang('manager_theme_message'),
                    ],
                    [
                        'key' => 'manager_language',
                        'scope' => ['system', 'user', 'profile'],
                        'type' => 'select',
                        'label' => lang('language_title'),
                        'options' => static function () {
                            return EvoSettingsForm::languages();
                        },
                        'desc' => lang('language_message'),
                    ],
                    [
                        'key' => 'manager_inline_style',
                        'scope' => ['system', 'user', 'profile'],
                        'userDefault' => true,
                        'type' => 'textarea',
                        'label' => lang('a17_manager_inline_style_title'),
                        'attr' => ['id' => 'manager_inline_style', 'style' => 'width:95%; height: 9em;'],
                        'desc' => '<br/>' . lang('a17_manager_inline_style_message'),
                        // ユーザーの値が無いときは、「システム設定に従う」の先のシステム設定の値を出す
                        'userValue' => static function (array $values, array $stored) {
                            return (string)($values['manager_inline_style'] ?? $stored['manager_inline_style'] ?? '');
                        },
                    ],
                    [
                        'key' => 'manager_login_startup',
                        'scope' => ['user', 'profile'],
                        'type' => 'text',
                        'size' => 50,
                        'label' => lang('mgr_login_start'),
                        'attr' => ['style' => 'width: 100px;'],
                        'desc' => lang('mgr_login_start_message'),
                    ],
                    [
                        'key' => 'datetime_format',
                        'type' => 'select',
                        'label' => lang('datetime_format'),
                        'options' => [
                            'dd-mm-YYYY' => 'dd-mm-YYYY',
                            'mm/dd/YYYY' => 'mm/dd/YYYY',
                            'YYYY/mm/dd' => 'YYYY/mm/dd',
                        ],
                        'desc' => lang('datetime_format_message'),
                    ],
                    [
                        'key' => 'datepicker_offset',
                        'type' => 'text',
                        'size' => 5,
                        'label' => lang('datepicker_offset'),
                        'desc' => lang('datepicker_offset_message'),
                    ],
                    [
                        'key' => 'remember_last_tab',
                        'type' => 'radio',
                        'label' => lang('remember_last_tab'),
                        'options' => [
                            '2' => lang('yes') . ' (Full)',
                            '1' => lang('yes') . ' (Stay mode)',
                            '0' => lang('no'),
                        ],
                        'desc' => lang('remember_last_tab_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_tree'),
                'rows' => [
                    [
                        'key' => 'tree_pane_open_default',
                        'type' => 'radio',
                        'label' => lang('tree_pane_open_default_title'),
                        'options' => ['1' => lang('open'), '0' => lang('close')],
                        'desc' => lang('tree_pane_open_default_message'),
                    ],
                    [
                        'key' => 'tree_page_click',
                        'type' => 'radio',
                        'label' => lang('tree_page_click'),
                        'options' => [
                            '27' => lang('edit_resource'),
                            '3' => lang('doc_data_title'),
                            'auto' => lang('tree_page_click_option_auto'),
                        ],
                        'desc' => lang('tree_page_click_message'),
                    ],
                    [
                        'key' => 'resource_tree_sortby_default',
                        'type' => 'select',
                        'label' => lang('setting_resource_tree_sortby_default'),
                        // 表示は [*値*]（今の画面のまま）
                        'options' => [
                            'isfolder' => '[*isfolder*]',
                            'pagetitle' => '[*pagetitle*]',
                            'id' => '[*id*]',
                            'menuindex' => '[*menuindex*]',
                            'createdon' => '[*createdon*]',
                            'editedon' => '[*editedon*]',
                        ],
                        'desc' => lang('setting_resource_tree_sortby_default_desc'),
                    ],
                    [
                        'key' => 'resource_tree_node_name',
                        'type' => 'select',
                        'label' => lang('setting_resource_tree_node_name'),
                        'options' => [
                            'pagetitle' => '[*pagetitle*]',
                            'menutitle' => '[*menutitle*]',
                            'alias' => '[*alias*]',
                            'createdon' => '[*createdon*]',
                            'editedon' => '[*editedon*]',
                            'publishedon' => '[*publishedon*]',
                        ],
                        'desc' => lang('setting_resource_tree_node_name_desc'),
                    ],
                    [
                        'key' => 'manager_treepane_trim_title',
                        'type' => 'yesno',
                        'label' => lang('manager_treepane_trim_title'),
                        'desc' => lang('manager_treepane_trim_title_message'),
                    ],
                    [
                        'key' => 'manager_docs_orderby',
                        'type' => 'text',
                        'label' => lang('manager_docs_orderby_title'),
                        'attr' => ['style' => 'width:500px;'],
                        'br' => false,
                        'desc' => '<p>' . lang('manager_docs_orderby_message') . '</p>',
                    ],
                    [
                        'key' => 'limit_by_container',
                        'type' => 'text',
                        'size' => 4,
                        'label' => lang('limit_by_container'),
                        'desc' => lang('limit_by_container_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_menu'),
                'rows' => [
                    [
                        'type' => 'topmenu',
                        'label' => lang('topmenu_items_title'),
                        'items' => [
                            'topmenu_site' => lang('site'),
                            'topmenu_element' => lang('elements'),
                            'topmenu_security' => lang('users'),
                            'topmenu_user' => lang('user'),
                            'topmenu_tools' => lang('tools'),
                            'topmenu_reports' => lang('reports'),
                        ],
                        'desc' => lang('topmenu_items_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_counts'),
                'rows' => [
                    [
                        'key' => 'top_howmany',
                        'type' => 'text',
                        'size' => 3,
                        'label' => lang('top_howmany_title'),
                        'desc' => lang('top_howmany_message'),
                    ],
                    [
                        'key' => 'number_of_logs',
                        'type' => 'text',
                        'size' => 3,
                        'label' => lang('nologentries_title'),
                        'desc' => lang('nologentries_message'),
                    ],
                    [
                        'key' => 'number_of_results',
                        'type' => 'text',
                        'size' => 5,
                        'label' => lang('noresults_title'),
                        'desc' => lang('noresults_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_other'),
                'rows' => [
                    [
                        'key' => 'automatic_optimize',
                        'type' => 'yesno',
                        'label' => lang('automatic_optimize_table_title'),
                        'desc' => lang('automatic_optimize_table_message'),
                    ],
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabEditor',
        'event' => 'OnInterfaceSettingsRender',
        // エディターの設定のプラグインの欄は、ユーザー編集画面とプロフィールにも出す
        'eventScope' => ['system', 'user', 'profile'],
        'title' => lang('settings_editor'),
        'sections' => [
            [
                'title' => null,
                'rows' => [
                    [
                        'key' => 'use_editor',
                        'type' => 'yesno',
                        'label' => lang('use_editor_title'),
                        'desc' => lang('use_editor_message'),
                    ],
                    [
                        'key' => 'which_editor',
                        'scope' => ['system', 'user', 'profile'],
                        'type' => 'select',
                        'label' => lang('which_editor_title'),
                        // エディターのプラグインが無ければ null（欄を出さず説明だけを出す）
                        'options' => static function () {
                            $editors = evo()->invokeEvent('OnRichTextEditorRegister');
                            if (!is_array($editors)) {
                                return null;
                            }
                            $options = ['none' => lang('none')];
                            foreach ($editors as $editor) {
                                $options[$editor] = $editor;
                            }
                            return $options;
                        },
                        'desc' => lang('which_editor_message'),
                        // エディターのプラグインが無いとき、ユーザー画面の選択肢（グローバル設定は欄ごと出さない）
                        'fallbackOptions' => ['none' => lang('none')],
                        'showIf' => ['use_editor', '1'],
                    ],
                    [
                        'key' => 'fe_editor_lang',
                        'type' => 'select',
                        'label' => lang('fe_editor_lang_title'),
                        'options' => static function () {
                            return EvoSettingsForm::languages();
                        },
                        'desc' => lang('fe_editor_lang_message'),
                        'showIf' => ['use_editor', '1'],
                    ],
                    [
                        'key' => 'editor_css_path',
                        'userAttr' => ['style' => 'width: 250px;'],
                        'scope' => ['system', 'user', 'profile'],
                        'type' => 'text',
                        'label' => lang('editor_css_path_title'),
                        'attr' => ['style' => 'width:400px;'],
                        'desc' => lang('editor_css_path_message'),
                        'showIf' => ['use_editor', '1'],
                    ],
                ],
            ],
        ],
    ],
    [
        'id' => 'settingsTabFiles',
        'event' => 'OnMiscSettingsRender',
        'title' => lang('settings_files'),
        'sections' => [
            [
                'title' => lang('settings_section_files_picker'),
                'rows' => [
                    [
                        'key' => 'use_browser',
                        'type' => 'yesno',
                        'label' => lang('rb_title'),
                        'desc' => lang('rb_message'),
                    ],
                    [
                        'key' => 'rb_base_dir',
                        'userAttr' => ['style' => 'width: 300px;'],
                        'scope' => ['system', 'user'],
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
                        'userAttr' => ['style' => 'width: 300px;'],
                        'scope' => ['system', 'user'],
                        'type' => 'text',
                        'label' => lang('rb_base_url_title'),
                        'before' => static function (array $values) {
                            return hsc($values['site_url'] ?? '');
                        },
                        'desc' => lang('rb_base_url_message'),
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
                        'key' => 'strip_image_paths',
                        'type' => 'yesno',
                        'label' => lang('settings_strip_image_paths_title'),
                        'desc' => lang('settings_strip_image_paths_message'),
                        'showIf' => ['use_browser', '1'],
                    ],
                    [
                        'key' => 'upload_files',
                        'scope' => ['system', 'user'],
                        'userDefault' => true,
                        'type' => 'text',
                        'label' => lang('uploadable_files_title'),
                        'desc' => lang('uploadable_files_message'),
                        'userDesc' => lang('uploadable_files_message') . lang('user_upload_message'),
                    ],
                    [
                        'key' => 'upload_images',
                        'scope' => ['system', 'user'],
                        'userDefault' => true,
                        'type' => 'text',
                        'label' => lang('uploadable_images_title'),
                        'desc' => lang('uploadable_images_message'),
                        'userDesc' => lang('uploadable_images_message') . lang('user_upload_message'),
                    ],
                    [
                        'key' => 'upload_media',
                        'scope' => ['system', 'user'],
                        'userDefault' => true,
                        'type' => 'text',
                        'label' => lang('uploadable_media_title'),
                        'desc' => lang('uploadable_media_message'),
                        'userDesc' => lang('uploadable_media_message') . lang('user_upload_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_file_manager'),
                'rows' => [
                    [
                        'key' => 'filemanager_path',
                        'userAttr' => ['style' => 'width: 300px;'],
                        'scope' => ['system', 'user'],
                        'type' => 'path',
                        'label' => lang('filemanager_path_title'),
                        'desc' => lang('filemanager_path_message'),
                        'defaultPath' => EvoSettingsForm::BASE_PATH_TOKEN,
                        'absHint' => true,
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_uploads'),
                'rows' => [
                    [
                        'key' => 'upload_maxsize',
                        'scope' => ['system', 'user'],
                        'type' => 'text',
                        'size' => 8,
                        'attr' => ['inputmode' => 'decimal'],
                        'after' => ' MB',
                        'label' => lang('upload_maxsize_title'),
                        // 保存はバイト、欄は MB の数。バイト → MB（小数 2 桁、末尾の 0 は落とす。読めない値はそのまま出す）
                        'toInput' => static function ($value) {
                            $bytes = size_to_bytes($value);
                            if ($bytes === null) {
                                return (string)$value;
                            }
                            // 2 桁で元のバイトに戻れるときだけ 2 桁、戻れないときは戻る桁数まで出す（保存し直してもずれないように）
                            for ($digits = 2; $digits <= 10; $digits++) {
                                $mb = rtrim(rtrim(number_format($bytes / 1048576, $digits, '.', ''), '0'), '.');
                                if (size_to_bytes($mb, 1048576) === $bytes) {
                                    break;
                                }
                            }
                            return $mb === '' ? '0' : $mb;
                        },
                        // 欄の値（MB の数。「32mb」のような単位付きも読む）→ バイトの文字列。空は空、読めない値は null
                        'fromInput' => static function ($value) {
                            if (!is_scalar($value)) {
                                return null;
                            }
                            if (trim((string)$value) === '') {
                                return '';
                            }
                            $bytes = size_to_bytes($value, 1048576);
                            return $bytes === null ? null : (string)$bytes;
                        },
                        // ユーザーの値、無ければシステム設定の値、無ければサーバーの上限
                        'userValue' => static function (array $values, array $stored) {
                            return (string)(($values['upload_maxsize'] ?? '') ?: ($stored['upload_maxsize'] ?? '') ?: (manager()->getUploadMaxsize() ?: ''));
                        },
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
                        'key' => 'convert_datauri_to_file',
                        'type' => 'yesno',
                        'label' => lang('setting_convert_datauri_to_file'),
                        'desc' => lang('setting_convert_datauri_to_file_desc'),
                    ],
                    [
                        'key' => 'image_limit_width',
                        'type' => 'text',
                        'label' => lang('a17_image_limit_width_title'),
                        'after' => 'px',
                        'desc' => lang('a17_image_limit_width_message'),
                    ],
                ],
            ],
            [
                'title' => lang('settings_section_html_export'),
                'rows' => [
                    [
                        'key' => 'export_dir',
                        'type' => 'path',
                        'label' => lang('export_dir_title'),
                        'desc' => lang('export_dir_message'),
                        'defaultPath' => EvoSettingsForm::BASE_PATH_TOKEN . 'temp/export/',
                    ],
                ],
            ],
        ],
    ],
];
