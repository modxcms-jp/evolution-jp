<?php
/**
 * MODX language File
 *
 * @author MEGU, yamamoto, TxO
 * @package MODX
 * @subpackage installer_translations
 * @version 1.0.7J-r1
 *
 * Filename:       /install/lang/japanese-utf8/japanese-utf8.inc.php
 * Language:       Japanese
 * Encoding:       utf-8
 */
$_lang["agree_to_terms"] = 'ライセンスの条件を確認してからインストールしてください';
$_lang["alert_database_test_connection"] = '先にデータベースの接続テスト（または作成）をしてください';
$_lang["alert_database_test_connection_failed"] = 'データベースに接続できません。';
$_lang["alert_enter_adminemail"] = '管理者のメールアドレスを入力してください';
$_lang["alert_enter_adminpassword"] = '管理者のパスワードを入力してください';
$_lang["all"] = 'すべて選択';
$_lang["and_try_again"] = 'これらのエラーを修正し、右下の「再チェック」ボタンをクリックしてください。';
$_lang["and_try_again_plural"] = 'これらのエラーを修正し、右下の「再チェック」ボタンをクリックしてください。'; //Plural form
$_lang["begin"] = '開始';
$_lang["btnback_value"] = '戻る';
$_lang["btnclose_value"] = 'インストール終了';
$_lang["btnnext_value"] = '進む';
$_lang["cant_write_config_file"] = '設定ファイルを生成できませんでした。以下をコピーしてconfig.inc.phpに反映してください ';
$_lang["cant_write_config_file_note"] = '反映したら、サイト名/manager/ から管理画面にログインできます。';
$_lang["checking_if_cache_exist"] = '<span class="mono">/temp/cache</span>ディレクトリの存在チェック(なければ転送に失敗しています): ';
$_lang["checking_if_cache_file_writable"] = '<span class="mono">/temp/cache/siteCache.idx.php</span>の書き込み属性: ';
$_lang["checking_if_cache_file2_writable"] = '<span class="mono">/temp/cache/basicConfig.php</span>の書き込み属性: ';
$_lang["checking_if_cache_writable"] = '<span class="mono">/temp/cache</span>ディレクトリの書き込み属性: ';
$_lang["checking_if_config_exist_and_writable"] = '<span class="mono">/manager/includes/config.inc.php</span>の存在と書き込み属性: ';
$_lang["checking_if_export_exists"] = '<span class="mono">/temp/export</span>ディレクトリの存在(なければ転送に失敗しています): ';
$_lang["checking_if_export_writable"] = '<span class="mono">/temp/export</span>ディレクトリの書き込み属性: ';
$_lang["checking_if_images_exist"] = '<span class="mono">/content/images</span>,<span class="mono">/content/files</span>,<span class="mono">/content/media</span>ディレクトリの存在(なければ転送に失敗しています): ';
$_lang["checking_if_images_writable"] = '<span class="mono">/content/images</span>,<span class="mono">/content/files</span>,<span class="mono">/content/media</span>ディレクトリの書き込み属性: ';
$_lang["checking_sql_version"] = 'SQLのバージョン: ';
$_lang["upgrade_old_db_title"] = 'この版からはアップデートできません';
$_lang["upgrade_old_db_not_supported"] = 'このデータベースは 1.0.0J より古い版のもので、この版から直接アップデートすることはできません。データベースはまだ変更していません。サイトのファイルを元に戻してください。';
$_lang["checking_sessions"] = 'セッションの動作: ';
$_lang["chunks"] = 'チャンク';
$_lang["config_permissions_note"] = '<span class="mono">/manager/includes/</span>に<span class="mono">config.inc.php</span>という空のファイルを置き（同じ場所のconfig.inc.php.blankの名前を変えてもかまいません）、パーミッションを606などにしてください。';
$_lang["config_permissions_upg_note"] = '<span class="mono">/manager/includes/config.inc.php</span>を書き込めるパーミッション(606など)にしてください。';
$_lang["connection_screen_collation"] = 'コレーション(照合順序):';
$_lang["connection_screen_database_connection_information"] = '利用するデータベースとTableプレフィックスの設定';
$_lang["connection_screen_database_connection_note"] = 'データベース名を入力してください。Tableプレフィックスを変更すると、ひとつのデータベースで複数のMODXを運用できます。';
$_lang["connection_screen_database_host"] = 'データベースホスト名:';
$_lang["connection_screen_database_info"] = 'データベース設定';
$_lang["connection_screen_database_login"] = 'データベース接続ログイン名:';
$_lang["connection_screen_database_name"] = 'データベース名:';
$_lang["connection_screen_database_pass"] = 'データベース接続パスワード:';
$_lang["connection_screen_database_test_connection"] = 'ここをクリックしてデータベースをテストしてください（権限があれば、データベースを作成します）。';
$_lang["connection_screen_default_admin_email"] = 'メールアドレス:';
$_lang["connection_screen_default_admin_login"] = 'ログイン名(半角英数字):';
$_lang["connection_screen_default_admin_note"] = 'デフォルトの管理アカウントを作成します。';
$_lang["connection_screen_default_admin_password"] = 'パスワード:';
$_lang["connection_screen_default_admin_password_confirm"] = 'パスワード(確認入力):';
$_lang["connection_screen_default_admin_user"] = 'デフォルトの管理アカウント作成';
$_lang["connection_screen_defaults"] = '管理アカウントの初期設定';
$_lang["connection_screen_server_connection_information"] = 'データベースホストへの接続';
$_lang["connection_screen_server_connection_note"] = 'ポート番号を指定する場合は「ホスト名:ポート番号」としてください(例：localhost:3306)。';
$_lang["connection_screen_server_test_connection"] = 'ここをクリックして接続をテストしてください。';
$_lang["connection_screen_table_prefix"] = 'Tableプレフィックス:';
$_lang["database_alerts"] = 'データベースの警告';
$_lang["during_execution_of_sql"] = ' — 実行したSQL: ';
$_lang["encoding"] = 'utf-8'; //charset encoding for html header
$_lang["error"] = 'エラー';
$_lang["errors"] = 'エラー';
$_lang["failed"] = '確認してください';
$_lang["help"] = 'ヘルプ';
$_lang["help_link_new"] = 'https://modx.jp/docs/install.html';
$_lang["help_link_upd"] = 'https://modx.jp/docs/update.html';
$_lang["help_title"] = 'インストールで困ったらこのページを見てください';
$_lang["iagree_box"] = '<b><a href="license.txt" target="_blank">このライセンス(GPL2)</a>の条件に同意します。</b></p><p><a href="http://www.opensource.jp/gpl/gpl.ja.html" target="_blank">GPL2の日本語訳</a>には法的な効力がありません。正式な条件は英語の原文で確認してください。';
$_lang["install"] = 'インストール';
$_lang["upgrade"] = 'アップデート';
$_lang["upgrade_in_progress"] = 'アップデートを実行しています…';
$_lang["install_overwrite"] = 'インストール - ';
$_lang["install_results"] = 'インストールを実行しました。';
$_lang["install_results_failed"] = 'インストールを完了できませんでした。';
$_lang["install_aborted"] = 'インストールを中断しました。';
$_lang["install_session_missing"] = 'インストールに必要な情報（データベースの接続先など）が残っていないため、中断しました。途中でセッションが切れたときなどに起こります。最初からやり直してください。';
$_lang["install_start_over"] = '最初からやり直す';
$_lang["installation_finished_with_errors"] = '<span style="color:#c00;">途中でエラーがあったため、インストールが正しく終わっていない可能性があります。上のメッセージを確認してください。</span>';
$_lang["installation_error_occured"] = '<span style="font-weight:bold;color:red;">インストール中に以下のエラーが発生しました。</span>';
$_lang["installation_install_new_note"] = 'MODXを新規インストールします。';
$_lang["htaccess_not_placed"] = 'フレンドリーURL用の.htaccessを置けませんでした。このままでは、トップページ以外の文書へのリンクが開けません（404）。サイトのルートに、配布物のsample.htaccessを.htaccessとして置いてください（サブディレクトリに設置したときは、RewriteBaseを設置先に合わせてください）。';
$_lang["htaccess_no_rewrite"] = 'サイトのルートにある.htaccessに、フレンドリーURL用の書き換えの規則がなく、足すこともできませんでした（書き込めない、または配布物のsample.htaccessがありません）。このままでは、トップページ以外の文書へのリンクが開けません（404）。配布物のsample.htaccessの「# BEGIN MODX」から「# END MODX」までを、既存の.htaccessに足してください（サブディレクトリに設置したときは、RewriteBaseを設置先に合わせてください）。';
$_lang["htaccess_rules_added"] = 'サイトのルートにある.htaccessに、フレンドリーURL用の書き換えの規則が無かったため、末尾に足しました（「# BEGIN MODX」から「# END MODX」まで）。それより前の行は変えていません。';
$_lang["installation_new_installation"] = '新規インストール';
$_lang["installation_note"] = '<strong>はじめに:</strong>管理画面にログインできたら、まずは管理画面右上のヘルプをご確認ください。';
$_lang["installation_successful"] = '<span style="color:#080;font-size:22px;">インストールが完了しました。</span>';
$_lang["installation_upgrade_existing"] = 'アップデート';
$_lang["installation_upgrade_existing_note"] = 'データベースをアップデートします。始める前に<a href="https://modx.jp/docs/update.html" target="_blank">手順と注意点</a>を確認してください。<br /><b style="color:red;">【注意】</b>まだなら、管理画面の「バックアップ・リストア」でデータベースのスナップショットを取っておいてください。';
$_lang["installed"] = 'インストールしました';
$_lang["installing_demo_site"] = 'サンプルサイトのインストール: ';
$_lang["install_in_progress"] = 'インストールを実行しています…';
$_lang["language_code"] = 'ja';
$_lang["loading"] = '処理中...';
$_lang["modules"] = 'モジュール';
$_lang["modx_footer1"] = '&copy; 2005-[+year+] the <a href="http://modx.com/" target="_blank">MODX</a> Content Management Framework (CMF) project. All rights reserved. MODX is licensed under the GNU GPL.';
$_lang["modx_footer2"] = 'MODX is free software.  We encourage you to be creative and make use of MODX in any way you see fit. Just make sure that if you do make changes and decide to redistribute your modified MODX, that you keep the source code free!';
$_lang["modx_install"] = 'MODX &raquo; インストール';
$_lang["sql_version_is"] = '';
$_lang["no"] = 'いいえ';
$_lang["none"] = 'すべての選択を解除';
$_lang["not_found"] = '見つかりません';
$_lang["ok"] = '問題なし';
$_lang["optional_items"] = 'インストールオプションの選択';
$_lang["optional_items_update"] = 'アップデートオプションの選択';
$_lang["optional_items_new_note"] = '<b>オプションを選択してください</b><br />初めてMODXを試す場合は、すべてチェックを入れましょう。';
$_lang["optional_items_upd_note"] = '<b>オプションを選択してください</b><br /><b>【アップデート時の注意】</b> プラグインに関しては、インストール時に既存プラグインを無効にします。';
$_lang["please_correct_error"] = 'があります。';
$_lang["please_correct_errors"] = 'があります。';
$_lang["plugins"] = 'プラグイン';
$_lang["plugin_disabled_in_site"] = 'このサイトでは無効にしてあります';
$_lang["preinstall_validation"] = 'インストール前の状態確認';
$_lang["preupdate_validation"] = 'アップデート前の状態確認';
$_lang["remove_install_folder_auto"] = 'インストールディレクトリを自動で削除する<br />※サーバーの設定によっては削除できないことがあります。<br />削除できなかったときはログイン時に警告が出るので、手作業で削除してください。';
$_lang["remove_install_folder_manual"] = '管理画面にログインする前に、&quot;<b>install</b>&quot; フォルダーを必ず削除してください。';
$_lang["retry"] = '再チェック';
$_lang["sample_web_site"] = 'サンプルサイト';
$_lang["sample_web_site_note"] = '<span style="font-style:normal;">簡易なテンプレート・新着情報一覧・ナビゲーション・パン屑リスト・問い合わせフォームの実装サンプルが含まれています。</span>';
$_lang["session_problem"] = 'サーバーのセッション設定に問題があります。';
$_lang["session_problem_try_again"] = '問題を修正できたら再試行';
$_lang["setup_cannot_continue"] = '問題があるため、セットアップを続けられません。';
$_lang["setup_couldnt_install"] = '選択されたテーブルをインストール/変更できませんでした。';
$_lang["setup_database"] = 'セットアップ結果<br />';
$_lang["setup_database_creating_tables"] = '必要なテーブルの作成: ';
$_lang["snippets"] = 'スニペット';
$_lang["some_tables_not_updated"] = 'いくつかのテーブルはアップデートされませんでした。';
$_lang["migration_failed"] = 'データベースの移行処理「%s」が失敗したため、残りの手順に進まずにアップデートを止めました。下に示した原因を取り除いてから、もう一度アップデートしてください。';
$_lang["migration_runner_failed"] = 'データベースの移行処理を実行できなかったため、残りの手順に進まずにアップデートを止めました。下に示した原因を取り除いてから、もう一度アップデートしてください。';
$_lang["install_failed_tables_removed"] = '途中まで作ったテーブルは削除しました。原因を取り除いてから、同じ設定でもう一度インストールできます。';
$_lang["install_failed_tables_left"] = '途中まで作った次のテーブルを削除できませんでした。phpMyAdmin などで削除してから、もう一度インストールしてください。';
$_lang["status_checking_database"] = 'データベースとTableプレフィックスのチェック: ';
$_lang["status_connecting"] = ' データベースホストへの接続: ';
$_lang["status_failed"] = '接続できません';
$_lang["status_failed_could_not_create_database"] = 'データベースが存在しないか、作成できません';
$_lang["status_failed_table_prefix_already_in_use"] = 'このTableプレフィックスはすでに使われています。既存のTableを削除するか、別のプレフィックスを指定してください。';
$_lang["status_passed"] = '問題ありません';
$_lang["status_passed_database_created"] = 'データベースを作成しました。';
$_lang["status_passed_server"] = '接続できます';
$_lang["summary_setup_check"] = '<strong>インストール前の最終チェックです。</strong>';
$_lang["summary_update_check"] = '<strong>アップデート前の最終チェックです。</strong>';
$_lang["templates"] = 'テンプレート';
$_lang["to_log_into_content_manager"] = 'おつかれさまでした。「インストール終了」をクリックすると、管理画面のログインページ(manager/index.php)を開きます。';
$_lang["to_log_into_content_manager_keep_login"] = 'おつかれさまでした。「インストール終了」をクリックすると、ログインしたまま管理画面(manager/index.php)に戻ります。';
$_lang["toggle"] = '選択状態を反転';
$_lang['tvs'] = 'テンプレート変数';
$_lang["unable_install_chunk"] = 'チャンクをインストールできません';
$_lang["unable_install_module"] = 'モジュールをインストールできません';
$_lang["unable_install_plugin"] = 'プラグインをインストールできません';
$_lang["unable_install_snippet"] = 'スニペットをインストールできません';
$_lang["unable_install_template"] = 'テンプレートをインストールできません';
$_lang["upgrade_note"] = '<strong>注意:</strong>管理画面にログインしたら、リソースや設定を日本語を含めて編集・保存し、文字化けしないか確かめてください。「システムログ」にエラーが出ていないかも確認してください。';
$_lang["upgrade_note_keep_login"] = '<strong>注意:</strong>管理画面で、リソースや設定を日本語を含めて編集・保存し、文字化けしないか確かめてください。「システムログ」にエラーが出ていないかも確認してください。';
$_lang["upgraded"] = 'アップデートしました';
$_lang["visit_forum"] = '';
$_lang["warning"] = '注意 ';
$_lang["welcome_message_text"] = 'MODXのインストールは簡単。インストーラーの説明に従って進めてください。';
$_lang["welcome_message_welcome"] = 'MODXのインストールを開始します。';
$_lang["writing_config_file"] = 'config.inc.phpへの書き込み(設定情報): ';
$_lang["yes"] = 'はい';

$_lang['checking_if_backup_exists'] = '<span class="mono">/temp/backup</span>ディレクトリの存在(なければ転送に失敗しています): ';
$_lang['checking_if_backup_writable'] = '<span class="mono">/temp/backup</span>ディレクトリの書き込み属性: ';
$_lang['no_update_options'] = 'アップデートするオプションはありません。config.inc.phpの「$lastInstallTime」だけを更新します。';
$_lang['no_update_options_with_utf8mb4'] = 'アップデートするオプションはありません。config.inc.phpの「$lastInstallTime」を更新し、既存のテーブルをutf8mb4に変換します。';
$_lang['utf8mb4_conversion_title'] = 'データベースの文字セット変換';
$_lang['utf8mb4_conversion_enable'] = 'アップデート時に既存のutf8テーブルをutf8mb4へ変換する';
$_lang['utf8mb4_conversion_skip'] = '変換せず、utf8のままにする';
$_lang['utf8mb4_conversion_tables_intro'] = '変換するテーブル:';
$_lang['utf8mb4_conversion_tables_none'] = '変換するテーブルはありません。';

$_lang['checking_if_content_exists'] = '<span class="mono">/content</span>ディレクトリの存在(なければ転送に失敗しています): ';
$_lang['checking_if_content_writable'] = '<span class="mono">/content</span>ディレクトリの書き込み属性: ';

$_lang['checking_if_temp_exists'] = '<span class="mono">/temp</span>ディレクトリの存在(なければ転送に失敗しています): ';
$_lang['checking_if_temp_writable'] = '<span class="mono">/temp</span>ディレクトリの書き込み属性: ';

$_lang["welcome_message_upd_text"] = 'MODXのアップデートは簡単。インストーラーの説明に従って進めてください。';
$_lang["welcome_message_upd_welcome"] = 'MODXのアップデートを開始します。';
$_lang["install_config_title"] = 'インストーラーのアクセス制御';
$_lang["install_config_missing_message"] = '<span class="mono">install-config.php</span> がないため、インストールを続けられません。';
$_lang["install_config_ip_message"] = '現在の接続元IPアドレスからはインストールを実行できません。<span class="mono">install-config.php</span> の <span class="mono">allowed_ips</span> を更新してください。';
$_lang["install_config_auth_message"] = 'インストーラーにアクセスするには BASIC 認証が必要です。<span class="mono">install-config.php</span> で設定したユーザー名とパスワードを入力してください。';
$_lang["install_config_remote_hint"] = '<span class="mono">allowed_ips</span> に追加するIPアドレス';
$_lang["install_config_step1"] = '<span class="mono">%s</span> ディレクトリの <span class="mono">%s</span> を <span class="mono">%s</span> としてコピーしてください。';
$_lang["install_config_step2"] = '許可するIPアドレス（<span class="mono">allowed_ips</span>）、または BASIC 認証を設定してください。';
$_lang["install_config_step3"] = '設定ファイルを保存したら、このページを再読み込みしてください。';
$_lang["install_config_footer"] = '両方を空にするとアクセス制御は無効になります（非推奨）。';
$_lang["upgrade_auth_required_title"] = 'アップデートには管理者認証が必要です';
$_lang["upgrade_auth_required_message"] = '<p>アップデートを実行するには、Evolution CMS の管理画面にログインしている必要があります。</p>';
$_lang["upgrade_auth_login_link"] = '管理画面にログイン';
$_lang["upgrade_auth_after_login"] = 'ログイン後、このページに戻ってアップデートを続行してください。';
$_lang["upgrade_permission_required_title"] = 'アップデートを実行する権限がありません';
$_lang["upgrade_permission_required_message"] = 'アップデートを実行するには、システム設定（settings）の権限が必要です。権限のある管理者に依頼してください。';
