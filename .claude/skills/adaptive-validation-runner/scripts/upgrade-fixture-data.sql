-- 古い版の DB を再現するフィクスチャの最小のデータ（upgrade-fixture.js build が、そのタグの CREATE TABLE のあとに足す）。
-- 1.0.0J から今まである列だけを使う（どのタグの定義にも同じ INSERT が通る）。
-- プレースホルダー: {PREFIX} 表の接頭辞 / {SETTINGS_VERSION} そのタグの版 / {ADMIN_USER} {ADMIN_EMAIL} 管理者
-- 管理者のパスワードは入れない（load が、流し込む前の DB の値に置き換える）。
--
-- 重複行: キーを持たない古い表にだけ重複が入る。キーのあるタグでは INSERT IGNORE で 2 行目以降が捨てられる。
-- 入る件数（消すべき件数。Docker の mariadb:11 で確かめた）:
--   表                   入れる行                                                                      1.0.0J  1.0.5J  1.0.6J 以降
--   site_plugin_events   (pluginid=1, evtid=1) が 2 行（priority 5 → 2。小さい 2 を残す）                 1       1       0
--   member_groups        (user_group=1, member=1) が 3 行（id の小さい行を残す）                           2       0       0
--   web_groups           (webgroup=1, webuser=1) が 2 行                                                   1       0       0
--   user_settings        (user=1, tinymce_editor_theme) が 2 行（'simple' → 'advanced'。後の行が残る）      1       1       0
--   web_user_settings    (webuser=1, lang) が 2 行（'en' → 'ja'。後の行が残る）                            1       1       0
-- 1.0.6J 以降は主キー・UNIQUE が既にあるので、重複は入らない（1.0.6J の user_settings / web_user_settings は
-- 主キーが setting_name だけなので、同じ名前の行は 1 行しか入らない）。
--
-- NULL を入れる文（site_templates.content）は、NOT NULL のタグで通るよう INSERT IGNORE にする。
SET NAMES utf8mb4;
SET SESSION sql_mode = '';

-- 管理者とロール
INSERT INTO `{PREFIX}manager_users` (`id`, `username`, `password`) VALUES
    (1, '{ADMIN_USER}', 'x');

INSERT INTO `{PREFIX}user_attributes` (`internalKey`, `fullname`, `role`, `email`) VALUES
    (1, 'Administrator', 1, '{ADMIN_EMAIL}');

-- ロール 1: 管理者。empty_trash だけ 0（利用者が外した権限の再現。残ること）
INSERT INTO `{PREFIX}user_roles`
    (`id`, `name`, `description`, `frames`, `home`, `view_document`, `new_document`, `save_document`,
     `delete_document`, `empty_trash`, `action_ok`, `logout`, `help`, `messages`, `new_user`, `edit_user`,
     `logs`, `edit_parser`, `save_parser`, `edit_template`, `settings`, `credits`, `new_template`,
     `save_template`, `delete_template`, `edit_snippet`, `new_snippet`, `save_snippet`, `delete_snippet`,
     `empty_cache`, `edit_document`, `change_password`, `error_dialog`, `about`, `file_manager`,
     `save_user`, `delete_user`, `save_password`, `edit_role`, `save_role`, `delete_role`, `new_role`,
     `access_permissions`)
VALUES
    (1, 'Administrator', 'Site administrators have full access to all functions', 1, 1, 1, 1, 1,
     1, 0, 1, 1, 1, 1, 1, 1,
     1, 1, 1, 1, 1, 1, 1,
     1, 1, 1, 1, 1, 1,
     1, 1, 1, 1, 1, 1,
     1, 1, 1, 1, 1, 1, 1,
     1);

-- ロール 2: save_role=1 で一部の権限が 0
INSERT INTO `{PREFIX}user_roles`
    (`id`, `name`, `description`, `frames`, `home`, `view_document`, `new_document`, `save_document`,
     `delete_document`, `empty_trash`, `action_ok`, `logout`, `help`, `messages`, `save_role`)
VALUES
    (2, 'Editor', 'Limited to managing content', 1, 1, 1, 1, 1,
     0, 0, 1, 1, 1, 1, 1);

-- システム設定（settings_version 以外は、アップグレードの処理が読む古い値）
-- install_version は、main を入れたサイトにある行の再現（どのリリースのタグのインストーラも書かない。main の d98d88609 から）。
-- 値は便宜上そのタグの版にしている
INSERT INTO `{PREFIX}system_settings` (`setting_name`, `setting_value`) VALUES
    ('settings_version', '{SETTINGS_VERSION}'),
    ('install_version', '{SETTINGS_VERSION}'),
    ('manager_theme', 'MODxCarbon'),
    ('which_editor', 'TinyMCE7'),
    ('site_id', 'MzGeQ2faT4Dw06+U49x3'),
    ('site_url', 'http://localhost/'),
    ('emailsender', 'you@example.com'),
    ('custom_contenttype', 'text/css,text/html,text/javascript,text/plain,text/xml'),
    ('topmenu_site', 'home,preview,refresh_site,search,add_resource,add_weblink'),
    ('validate_referer', '1'),
    ('upload_maxsize', '1048576'),
    ('use_udperms', '0'),
    ('csrf_token', 'fixture-token');

-- 基本の設定。アップグレードのあと、設定を保存しなくても公開側と管理画面が開くために要る
-- （install/sql/default_settings.sql の設定のうち、1.0.0J から今まである名前だけ。manager_language と fe_editor_lang は
-- フィクスチャのため japanese-utf8 にする。smoke.js のメールの日本語の検査に合わせる）
INSERT INTO `{PREFIX}system_settings` (`setting_name`, `setting_value`) VALUES
    ('server_offset_time', '0'),
    ('manager_language', 'japanese-utf8'),
    ('modx_charset', 'UTF-8'),
    ('site_name', 'Fixture Site'),
    ('site_start', '1'),
    ('error_page', '1'),
    ('unauthorized_page', '1'),
    ('site_status', '1'),
    ('old_template', ''),
    ('cache_type', '1'),
    ('udperms_allowroot', '0'),
    ('failed_login_attempts', '5'),
    ('blocked_minutes', '10'),
    ('use_captcha', '0'),
    ('use_editor', '1'),
    ('use_browser', '1'),
    ('fe_editor_lang', 'japanese-utf8'),
    ('session.cookie.lifetime', '604800');

INSERT IGNORE INTO `{PREFIX}user_settings` (`user`, `setting_name`, `setting_value`) VALUES
    (1, 'tinymce_editor_theme', 'simple'),
    (1, 'tinymce_editor_theme', 'advanced'),
    (1, 'csrf_token', 'fixture-token');

INSERT IGNORE INTO `{PREFIX}web_user_settings` (`webuser`, `setting_name`, `setting_value`) VALUES
    (1, 'lang', 'en'),
    (1, 'lang', 'ja');

-- 文書: type='' の文書（type の推定）と、alias が文字列 NULL の文書
INSERT INTO `{PREFIX}site_content` (`type`, `contentType`, `pagetitle`, `alias`, `published`, `parent`, `content`, `template`) VALUES
    ('document', 'text/html', 'Home', 'index', 1, 0, '<p>Home</p>', 1),
    ('', '', 'External', 'external', 1, 0, 'http://example.com/', 1),
    ('', '', 'Feed', 'feed.xml', 1, 0, '<rss/>', 1),
    ('', '', 'Script', 'site.js', 1, 0, 'var a = 1;', 1),
    ('', '', 'Style', 'site.css', 1, 0, 'body {}', 1),
    ('', '', 'Plain', 'plain', 1, 0, 'text', 1),
    ('document', 'text/html', 'Null alias', 'NULL', 1, 0, '<p>NULL</p>', 1);

-- テンプレート: content が NULL
INSERT IGNORE INTO `{PREFIX}site_templates` (`id`, `templatename`, `content`) VALUES
    (1, 'Default', '<html>[*content*]</html>'),
    (2, 'Empty', NULL);

-- スニペット: プロパティに &NULL=undefined;; を含む
INSERT INTO `{PREFIX}site_snippets` (`id`, `name`, `snippet`, `properties`) VALUES
    (1, 'FixtureSnippet', 'return "";', '&a=A;text;1 &NULL=undefined;;');

-- プラグイン: 有効な Inherit Parent Template（auto_template_logic の行は入れない）と Bottom Button Bar
INSERT INTO `{PREFIX}site_plugins` (`id`, `name`, `plugincode`, `properties`, `disabled`) VALUES
    (1, 'Inherit Parent Template', 'return;', '&inheritTemplate=Inherit Template;list;From Parent,From First Sibling;From First Sibling', 0),
    (2, 'Bottom Button Bar', 'return;', '', 0),
    (3, 'FixturePlugin', 'return;', '', 0);

INSERT IGNORE INTO `{PREFIX}site_plugin_events` (`pluginid`, `evtid`, `priority`) VALUES
    (1, 1, 5),
    (1, 1, 2),
    (2, 2, 0),
    (3, 3, 0);

INSERT IGNORE INTO `{PREFIX}member_groups` (`user_group`, `member`) VALUES
    (1, 1),
    (1, 1),
    (1, 1);

INSERT IGNORE INTO `{PREFIX}web_groups` (`webgroup`, `webuser`) VALUES
    (1, 1),
    (1, 1);
