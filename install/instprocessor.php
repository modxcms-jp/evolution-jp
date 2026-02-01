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

        $directory = rtrim(MODX_BASE_PATH, '/') . '/temp/';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $path = $directory . 'install-' . date('Ymd-His') . '-' . substr(md5((string) microtime(true)), 0, 8) . '.log';
        sessionv('*install_log_path', $path);

        return $path;
    }
}

if (!function_exists('log_install_event')) {
    function log_install_event($message, array $context = [])
    {
        $logPath = install_log_path();
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

if (!sessionv('database_server')) {
    log_install_event('Installation aborted: missing database_server in session');
    exit('go to first step');
}

if (!validateSessionValues()) {
    log_install_event('Installation aborted: session values validation failed');
    exit('session values are not valid, go to first step');
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

if (sessionv('is_upgradeable')) {
    log_install_event('Upgrade path selected');
    if (db()->tableExists('[+prefix+]site_revision') && !db()->fieldExists('elmid', '[+prefix+]site_revision')) {
        log_install_event('Dropping legacy site_revision table');
        db()->query(
            str_replace(
                '[+prefix+]',
                sessionv('table_prefix'),
                'DROP TABLE IF EXISTS `[+prefix+]site_revision`'
            )
        );
    }
}

echo "<p>" . lang('setup_database_creating_tables');
log_install_event('Creating database tables');

$sqlParser->intoDB('create_tables.sql');

if (!sessionv('is_upgradeable')) {
    log_install_event('Installing default settings');
    $sqlParser->intoDB('default_settings.sql');
    if (is_file(MODX_SETUP_PATH . 'sql/default_settings_custom.sql')) {
        log_install_event('Installing custom default settings');
        $sqlParser->intoDB('default_settings_custom.sql');
    }
}

include MODX_SETUP_PATH . 'sql/fix_settings.php';
log_install_event('Applied settings fix script');

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
    echo "<p>" . lang('some_tables_not_updated') . "</p>";
    return;
}

printf('<span class="ok">%s</span></p>', lang('ok'));
log_install_event('Database installation completed successfully');
$configString = file_get_contents(MODX_BASE_PATH . 'manager/includes/templates/config.inc.tpl');
$ph['database_type'] = 'mysqli';
$ph['database_server'] = sessionv('database_server');
$ph['database_user'] = db()->escape(sessionv('database_user'));
$ph['database_password'] = db()->escape(sessionv('database_password'));
$ph['database_connection_charset'] = sessionv('database_charset');
$ph['database_connection_method'] = sessionv('database_connection_method');
$ph['dbase'] = trim(sessionv('dbase'), '`');
$ph['table_prefix'] = sessionv('table_prefix');
$ph['lastInstallTime'] = time();
$ph['https_port'] = '443';
$ph['filemanager_path'] = getSystemSettingValue('filemanager_path') ?: '';
$ph['rb_base_dir'] = getSystemSettingValue('rb_base_dir') ?: '';

$configString = evo()->parseText($configString, $ph);
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

db()->delete(
    '[+prefix+]system_settings',
    "setting_name IN ('filemanager_path','rb_base_dir')"
);
log_install_event('Removed path settings from system_settings', ['settings' => ['filemanager_path', 'rb_base_dir']]);

if (sessionv('is_upgradeable') == 0) {
    log_install_event('Creating site_id for fresh installation');
    $query = str_replace(
        '[+prefix+]',
        db()->table_prefix,
        sprintf(
            "REPLACE INTO [+prefix+]system_settings (setting_name,setting_value) VALUES('site_id','%s')",
            uniqid('')
        )
    );
    db()->query($query);
} else {
    $site_id = db()->getValue(
        'setting_value',
        '[+prefix+]system_settings',
        "setting_name='site_id'"
    );
    if ($site_id) {
        if (!$site_id || $site_id = 'MzGeQ2faT4Dw06+U49x3') {
            log_install_event('Regenerating site_id during upgrade');
            $query = str_replace(
                '[+prefix+]',
                db()->table_prefix,
                sprintf(
                    "REPLACE INTO [+prefix+]system_settings (setting_name,setting_value) VALUES('site_id','%s')",
                    uniqid('')
                )
            );
            db()->query($query);
        }
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

// setup completed!
echo "<p><b>" . lang('installation_successful') . "</b></p>";
echo "<p>" . lang('to_log_into_content_manager') . "</p>";
echo '<p><img src="img/ico_info.png" align="left" style="margin-right:10px;" />';

if (sessionv('is_upgradeable') == 0) {
    echo lang('installation_note');
} else {
    echo lang('upgrade_note');
}

echo '</p>';

$_SESSION = [];

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

function getSystemSettingValue($key)
{
    $configValue = getConfigSettingValue($key);
    if ($configValue !== null && $configValue !== '') {
        return $configValue;
    }

    return db()->getValue(
        'setting_value',
        '[+prefix+]system_settings',
        sprintf("setting_name='%s'", db()->escape($key))
    );
}

function getConfigSettingValue($key)
{
    static $configValues = null;

    if ($configValues === null) {
        $configValues = [
            'filemanager_path' => null,
            'rb_base_dir' => null,
        ];

        $configPath = MODX_BASE_PATH . 'manager/includes/config.inc.php';
        if (is_file($configPath)) {
            $filemanager_path = null;
            $rb_base_dir = null;
            include $configPath;
            if (isset($filemanager_path)) {
                $configValues['filemanager_path'] = $filemanager_path;
            }
            if (isset($rb_base_dir)) {
                $configValues['rb_base_dir'] = $rb_base_dir;
            }
        }
    }

    if (array_key_exists($key, $configValues)) {
        return $configValues[$key];
    }

    return null;
}
