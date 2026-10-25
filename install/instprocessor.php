<?php

if (!function_exists('install_log_path')) {
    function install_log_path()
    {
        static $path = null;

        if ($path) {
            return $path;
        }

        $path = sessionv('install_log_path');
        if ($path) {
            return $path;
        }

        $directory = rtrim(MODX_BASE_PATH, '/') . '/temp/logs/install/' . date('Y/m') . '/';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        install_ensure_logs_htaccess();

        $path = $directory . 'install-' . date('Y-m-d') . '.log';
        sessionv('*install_log_path', $path);

        return $path;
    }
}

if (!function_exists('install_ensure_logs_htaccess')) {
    function install_ensure_logs_htaccess()
    {
        $root = rtrim(MODX_BASE_PATH, '/') . '/temp/logs/';
        if (!is_dir($root) || !is_writable($root)) {
            return;
        }

        $htaccess = $root . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n", LOCK_EX);
        }
    }
}

if (!function_exists('install_log_max_bytes')) {
    function install_log_max_bytes()
    {
        return 104857600;
    }
}

if (!function_exists('install_rotate_log_if_needed')) {
    function install_rotate_log_if_needed($logPath)
    {
        if (!is_file($logPath) || @filesize($logPath) < install_log_max_bytes()) {
            return;
        }

        $dir = dirname($logPath);
        if (!is_writable($dir)) {
            return;
        }

        for ($i = 9; $i >= 1; $i--) {
            $source = "{$logPath}.{$i}";
            $target = $logPath . '.' . ($i + 1);
            if (is_file($source)) {
                @rename($source, $target);
            }
        }

        @rename($logPath, "{$logPath}.1");
    }
}

if (!function_exists('log_install_event')) {
    function log_install_event($message, array $context = [])
    {
        $logPath = install_log_path();
        install_rotate_log_if_needed($logPath);
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] {$message}";

        if ($context) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $entry .= $encoded !== false ? " {$encoded}" : ' ' . var_export($context, true);
        }

        $entry .= PHP_EOL;

        if (@file_put_contents($logPath, $entry, FILE_APPEND) === false) {
            error_log($entry);
        }
    }
}

if (!function_exists('install_error_level_name')) {
    function install_error_level_name($severity)
    {
        $map = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        return $map[$severity] ?? 'E_' . $severity;
    }
}

if (!function_exists('register_install_error_handlers')) {
    function register_install_error_handlers()
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;

        set_error_handler(function ($severity, $message, $file = '', $line = 0) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            log_install_event('PHP error triggered during installation', [
                'severity' => install_error_level_name($severity),
                'message' => $message,
                'file' => $file,
                'line' => $line
            ]);

            return false;
        });

        set_exception_handler(function ($exception) {
            log_install_event('Uncaught exception during installation', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode()
            ]);
        });

        register_shutdown_function(function () {
            $error = error_get_last();

            if (!$error) {
                return;
            }

            $fatalTypes = [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
                E_USER_ERROR
            ];

            if (!in_array($error['type'], $fatalTypes, true)) {
                return;
            }

            log_install_event('Fatal error during installation', [
                'severity' => install_error_level_name($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        });
    }
}

register_install_error_handlers();

$logPath = install_log_path();
log_install_event('Installation processor invoked', ['log_path' => $logPath]);

// インストールに必要な値がセッションに無いときは、何もせずに中断する（結果画面は install.php が出し分ける）
if (!sessionv('database_server')) {
    log_install_event('Installation aborted: missing database_server in session');
    $install_aborted = true;
    printf('<p>%s</p>', lang('install_session_missing'));
    return;
}

if (!validateSessionValues()) {
    log_install_event('Installation aborted: session values validation failed');
    $install_aborted = true;
    printf('<p>%s</p>', lang('install_session_missing'));
    return;
}

log_install_event('Session validation succeeded', [
    'is_upgradeable' => (int) sessionv('is_upgradeable', 0),
    'installdata' => (int) sessionv('installdata', 0),
    'table_prefix' => sessionv('table_prefix')
]);

require_once MODX_BASE_PATH . 'manager/includes/default.config.php';

echo "<p>" . lang('setup_database') . "</p>\n";
log_install_event('Database setup started');

// open db connection
include MODX_SETUP_PATH . 'setup.info.php';

include_once MODX_SETUP_PATH . 'sqlParser.class.php';
$sqlParser = new SqlParser();

$sqlParser->prefix = sessionv('table_prefix');
$sqlParser->adminname = sessionv('adminname');
$sqlParser->adminpass = sessionv('adminpass');
$sqlParser->adminemail = sessionv('adminemail');
$sqlParser->connection_charset = sessionv('database_charset');
$sqlParser->connection_collation = sessionv('database_collation');
$sqlParser->managerlanguage = sessionv('managerlanguage');

// install/update database

require_once MODX_CORE_PATH . 'migrator.class.inc.php';

if (sessionv('is_upgradeable')) {
    log_install_event('Upgrade path selected');

    // マイグレーションは create_tables.sql より前に流す。古い形の表を baseline が直してから、
    // create_tables.sql が今の形で足りない表を作る（列を縮める処理も、utf8mb4 への変換より前に済ませる）。
    // 大きなサイトでは表の作り直しに数分かかるので、要求が切られても、時間切れにならずに最後まで進める
    ignore_user_abort(true);
    set_time_limit(0);

    log_install_event('Running migrations');
    $migration = (new EvoMigrator())->run();
    if ($migration['applied']) {
        log_install_event('Migrations applied', ['migrations' => $migration['applied']]);
    }
    if ($migration['error'] !== null) {
        $errors += 1;
        log_install_event('Migration failed', [
            'migration' => $migration['failed'],
            'error' => $migration['error']
        ]);
        printf('<p><span class="notok"><b>%s</b></span></p>', lang('database_alerts'));
        printf('<p>%s</p>', hsc(sprintf(lang('migration_failed'), (string)$migration['failed'])));
        printf('<p class="mono">%s</p>', nl2br(hsc($migration['error'])));
        printf('<p>%s</p>', lang('some_tables_not_updated'));
        return;
    }
}

echo "<p>" . lang('setup_database_creating_tables');
log_install_event('Creating database tables');

// 新規インストールが失敗したときに、この実行で作ったテーブルだけを片付けられるよう、前からあるテーブルを控える
$tablesBeforeInstall = sessionv('is_upgradeable') ? [] : prefixedTables(sessionv('table_prefix'));

$sqlParser->intoDB('create_tables.sql');

if (!sessionv('is_upgradeable')) {
    log_install_event('Installing default settings');
    $sqlParser->intoDB('default_settings.sql');
    if (is_file(MODX_SETUP_PATH . 'sql/default_settings_custom.sql')) {
        log_install_event('Installing custom default settings');
        $sqlParser->intoDB('default_settings_custom.sql');
    }
}

// 新規インストールで失敗したときは、イベント名の同期と適用済みの記録に進まない（欠けたテーブルへの書き込みで、エラーが重なるため）
if (sessionv('is_upgradeable') || !$sqlParser->installFailed) {
    $sqlParser->intoDB('system_eventnames.sql');
    log_install_event('Synchronized system event names');
}

// 新規インストールは最終形をそのまま作ったので、マイグレーションは流さず、適用済みとだけ記録する
if (!sessionv('is_upgradeable') && !$sqlParser->installFailed) {
    try {
        (new EvoMigrator())->markAllApplied();
        log_install_event('Recorded migrations as applied for fresh installation');
    } catch (Throwable $e) {
        $sqlParser->installFailed = true;
        $sqlParser->mysqlErrors[] = ['error' => $e->getMessage(), 'sql' => 'migrations'];
    }
}

if (sessionv('is_upgradeable') && sessionv('convert_to_utf8mb4', 1)) {
    log_install_event('Converting database to utf8mb4');
    convert2utf8mb4();
}

// display database results
if ($sqlParser->installFailed == true) {
    $errors += 1;
    log_install_event('Database installation failed', ['errors' => $sqlParser->mysqlErrors]);
    printf('<span class="notok"><b>%s</b></span></p>', lang('database_alerts'));
    printf('<p>%s</p>', lang('setup_couldnt_install'));
    printf('<p>%s<br /><br />', lang('installation_error_occured'));
    foreach ($sqlParser->mysqlErrors as $err) {
        printf('<em>%s</em>%s<span class="mono">%s</span>.<hr />', $err['error'], lang('during_execution_of_sql'), strip_tags($err['sql']));
    }
    echo '</p>';
    if (sessionv('is_upgradeable')) {
        echo "<p>" . lang('some_tables_not_updated') . "</p>";
        return;
    }
    // 作ったテーブルが残ると、やり直しの接続テストで接頭辞の重複になるので片付ける（前からあったテーブルには触れない）
    // どちらかの一覧を取得できなかったときは、前からあったテーブルと見分けられないので何も消さない
    $tablesAfterInstall = prefixedTables(sessionv('table_prefix'));
    if ($tablesBeforeInstall === null || $tablesAfterInstall === null) {
        log_install_event('Skipped removing tables: could not list tables');
        echo "<p>" . lang('some_tables_not_updated') . "</p>";
        return;
    }
    $leftTables = [];
    foreach (array_diff($tablesAfterInstall, $tablesBeforeInstall) as $table) {
        if (!db()->query(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', $table)), false)) {
            $leftTables[] = $table;
        }
    }
    log_install_event('Removed tables created by the failed installation', ['left' => $leftTables]);
    if ($leftTables) {
        printf('<p>%s<br /><span class="mono">%s</span></p>', lang('install_failed_tables_left'), hsc(implode(', ', $leftTables)));
    } else {
        printf('<p>%s</p>', lang('install_failed_tables_removed'));
    }
    return;
}

printf('<span class="ok">%s</span></p>', lang('ok'));
log_install_event('Database installation completed successfully');
$configString = file_get_contents(MODX_SETUP_PATH . 'tpl/config.inc.tpl');
$ph['database_type'] = 'mysqli';
$ph['database_server'] = sessionv('database_server');
$ph['database_user'] = sessionv('database_user');
$ph['database_password'] = sessionv('database_password');
$ph['database_connection_charset'] = sessionv('database_charset');
$ph['database_connection_method'] = sessionv('database_connection_method');
$ph['dbase'] = trim(sessionv('dbase'), '`');
$ph['table_prefix'] = sessionv('table_prefix');
$ph['lastInstallTime'] = time();
$ph['https_port'] = '443';

$configString = buildConfigFile($configString, $ph);
$config_path = MODX_BASE_PATH . 'manager/includes/config.inc.php';
$config_saved = @file_put_contents($config_path, $configString);
// try to chmod the config file go-rwx (for suexeced php)
@chmod($config_path, 0404);

echo "<p>" . lang('writing_config_file');
log_install_event('Writing configuration file', ['path' => $config_path]);
if ($config_saved === false) {
    printf('<span class="notok">%s</span></p>', lang('failed'));
    $errors += 1;
    log_install_event('Failed to write configuration file', ['path' => $config_path]);
    echo sprintf(
        '<p>%s<br /><span class="mono">manager/includes/config.inc.php</span></p>',
        lang('cant_write_config_file')
    );
    echo '<textarea style="width:100%; height:200px;font-size:inherit;font-family:\'Courier New\',\'Courier\', monospace;">';
    echo htmlspecialchars($configString);
    echo '</textarea>';
    echo "<p>" . lang('cant_write_config_file_note') . "</p>";
} else {
    printf('<span class="ok">%s</span></p>', lang('ok'));
    log_install_event('Configuration file written successfully', ['path' => $config_path]);
}

if (sessionv('is_upgradeable') == 0) {
    log_install_event('Creating site_id for fresh installation');
    db()->replace(
        ['setting_name' => 'site_id', 'setting_value' => uniqid('')],
        '[+prefix+]system_settings'
    );

    // 新規インストールした版。settings_version が '0' のまま(設定未保存)でも、
    // この版のコードなら公開側を止めない(version_mismatch_guard.php)
    include MODX_BASE_PATH . 'manager/includes/version.inc.php';
    log_install_event('Recording install_version for fresh installation', ['version' => $modx_version]);
    db()->replace(
        ['setting_name' => 'install_version', 'setting_value' => db()->escape($modx_version)],
        '[+prefix+]system_settings'
    );

    // サイトのURL：インストールしたときのURL（.env で決めている場合は書かない）
    $install_site_url = init::request_host() !== '' ? init::normalize_site_url(MODX_SITE_URL, MODX_BASE_URL) : '';
    if ($install_site_url !== '' && !init::env_site_url()) {
        log_install_event('Setting fixed_site_url for fresh installation', ['url' => $install_site_url]);
        db()->replace(
            ['setting_name' => 'fixed_site_url', 'setting_value' => db()->escape($install_site_url)],
            '[+prefix+]system_settings'
        );
    }
}

include 'processors/prc_insTemplates.inc.php'; // Install Templates
include 'processors/prc_insTVs.inc.php';       // Install Template Variables
include 'processors/prc_insChunks.inc.php';    // Install Chunks
include 'processors/prc_insModules.inc.php';   // Install Modules
include 'processors/prc_insPlugins.inc.php';   // Install Plugins
include 'processors/prc_insSnippets.inc.php';  // Install Snippets
log_install_event('Core components installed');

// install data
if (sessionv('is_upgradeable') == 0 && sessionv('installdata') == 1) {
    echo "<p>" . lang('installing_demo_site');
    log_install_event('Installing demo site data');
    $sqlParser->intoDB('sample_data.sql');
    if ($sqlParser->installFailed == true) {
        $errors += 1;
        log_install_event('Demo site installation failed', ['errors' => $sqlParser->mysqlErrors]);
        printf('<span class="notok"><b>%s</b></span></p>', lang('database_alerts'));
        echo "<p>" . lang('setup_couldnt_install') . "</p>";
        echo "<p>" . lang('installation_error_occured') . "<br /><br />";
        foreach ($sqlParser->mysqlErrors as $info) {
            printf(
                '<em>%s</em>%s<span class="mono">%s</span>.<hr />',
                $info['error'],
                lang('during_execution_of_sql'),
                strip_tags($info['sql'])
            );
        }
        echo '</p>';
        echo '<p>' . lang('some_tables_not_updated') . '</p>';
        log_install_event('Installation halted after demo data failure');
        return;
    }
    printf('<span class="ok">%s</span></p>', lang('ok'));
    log_install_event('Demo site installation completed successfully');
}

// アップグレードを通した版。公開側と管理画面の入口の判定がまだ読んでいる（判定を未適用のマイグレーションにそろえるときに消す）
if (sessionv('is_upgradeable') && $errors == 0) {
    include MODX_BASE_PATH . 'manager/includes/version.inc.php';

    log_install_event('Recording install_version for upgrade', ['version' => $modx_version]);
    db()->replace(
        ['setting_name' => 'install_version', 'setting_value' => db()->escape($modx_version)],
        '[+prefix+]system_settings'
    );
}

clean_up($sqlParser->prefix);
log_install_event('Clean up completed');

// Setup the MODX API -- needed for the cache processor
// initiate a new document parser

$files = glob(MODX_CACHE_PATH . "*.idx.php");
foreach ($files as $file) {
    @unlink($file);
}

// try to chmod the cache go-rwx (for suexeced php)
@chmod(MODX_CACHE_PATH . "siteCache.idx.php", 0644);
@chmod(MODX_CACHE_PATH . "basicConfig.php", 0644);

evo()->clearCache(); // always empty cache after install
log_install_event('Cache cleared');

// remove any locks on the manager functions so initial manager login is not blocked
db()->truncate('[+prefix+]active_users');

// andrazk 20070416 - release manager access
if (is_file(MODX_CACHE_PATH . "installProc.inc.php")) {
    @chmod(MODX_CACHE_PATH . "installProc.inc.php", 0755);
    unlink(MODX_CACHE_PATH . "installProc.inc.php");
}

// assets/cacheディレクトリが存在する場合は、サブディレクトリも含めて全て削除
if (is_dir(MODX_BASE_PATH . 'assets/cache')) {
    deleteCacheDirectory(MODX_BASE_PATH . 'assets/cache');
}

log_install_event('Installation processor finished');

// 途中でエラーがあったときは、成功の案内を出さない（見出しは install.php が出し分ける）
if ($errors > 0) {
    log_install_event('Installation finished with errors', ['errors' => $errors]);
    printf('<p><b>%s</b></p>', lang('installation_finished_with_errors'));
    return;
}

// setup completed!
echo "<p><b>" . lang('installation_successful') . "</b></p>";
// アップデートで管理画面にログイン中なら、ログインを保つ（install.php は $keep_login のときセッションを破棄しない）
$keep_login = sessionv('is_upgradeable') && isManagerAuthenticated();
echo "<p>" . lang($keep_login ? 'to_log_into_content_manager_keep_login' : 'to_log_into_content_manager') . "</p>";
echo '<p><img src="img/ico_info.png" align="left" style="margin-right:10px;" />';

if (sessionv('is_upgradeable') == 0) {
    echo lang('installation_note');
} else {
    echo lang($keep_login ? 'upgrade_note_keep_login' : 'upgrade_note');
}

echo '</p>';

if ($keep_login) {
    keepManagerLoginAfterUpgrade();
} else {
    $_SESSION = [];
}

function deleteCacheDirectory($cachePath)
{
    if (!is_dir($cachePath)) {
        return;
    }

    $dir = new RecursiveDirectoryIterator($cachePath, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($cachePath);
}

function ok($name, $msg)
{
    return sprintf('<p>&nbsp;&nbsp;%s: <span class="ok">%s</span></p>', $name, $msg) . "\n";
}

function ng($name, $msg)
{
    return sprintf('<p>&nbsp;&nbsp;%s: <span class="notok">%s</span></p>', $name, $msg) . "\n";
}

function showError()
{
    printf('<p>%s</p>', db()->getLastError());
}
