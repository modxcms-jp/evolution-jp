<?php

if (!sessionv('database_server')) {
    exit('go to first step');
}

global $tplChunks;
global $tplTemplates;
global $tplSnippets;
global $tplPlugins;
global $tplModules;
global $tplTVs;
global $errors;

@ set_time_limit(120); // used @ to prevent warning when using safe mode?

require_once(MODX_BASE_PATH . 'manager/includes/default.config.php');

extract($_lang, EXTR_PREFIX_ALL, 'lang');

echo "<p>" . lang('setup_database') . "</p>\n";
$database_type = function_exists('mysqli_connect') ? 'mysqli' : 'mysql';

// open db connection
$callBackFnc = include(MODX_SETUP_PATH . 'setup.info.php');
include_once(MODX_SETUP_PATH . 'sqlParser.class.php');
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
    if (db()->tableExists('[+prefix+]site_revision') && !db()->fieldExists('elmid', '[+prefix+]site_revision')) {
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

$sqlParser->intoDB('create_tables.sql');

if (!sessionv('is_upgradeable')) {
    $sqlParser->intoDB('default_settings.sql');
    if (is_file(MODX_SETUP_PATH . 'sql/default_settings_custom.sql')) {
        $sqlParser->intoDB('default_settings_custom.sql');
    }
}

include(MODX_SETUP_PATH . 'sql/fix_settings.php');

if (sessionv('is_upgradeable')) {
    convert2utf8mb4();
}

// display database results
if ($sqlParser->installFailed == true) {
    $errors += 1;
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
$configString = file_get_contents(MODX_SETUP_PATH . 'tpl/config.inc.tpl');
$ph['database_type'] = $database_type;
$ph['database_server'] = sessionv('database_server');
$ph['database_user'] = db()->escape(sessionv('database_user'));
$ph['database_password'] = db()->escape(sessionv('database_password'));
$ph['database_connection_charset'] = sessionv('database_charset');
$ph['database_connection_method'] = sessionv('database_connection_method');
$ph['dbase'] = trim(sessionv('dbase'), '`');
$ph['table_prefix'] = sessionv('table_prefix');
$ph['lastInstallTime'] = time();
$ph['https_port'] = '443';

$configString = evo()->parseText($configString, $ph);
$config_path = MODX_BASE_PATH . 'manager/includes/config.inc.php';
$config_saved = @ file_put_contents($config_path, $configString);
// try to chmod the config file go-rwx (for suexeced php)
@chmod($config_path, 0404);

echo "<p>" . lang('writing_config_file');
if ($config_saved === false) {
    printf('<span class="notok">%s</span></p>', lang('failed'));
    $errors += 1;
    echo sprintf(
        '<p>%s<br /><span class="mono">manager/includes/config.inc.php</span></p>'
        , lang('cant_write_config_file')
    );
    echo '<textarea style="width:100%; height:200px;font-size:inherit;font-family:\'Courier New\',\'Courier\', monospace;">';
    echo htmlspecialchars($configString);
    echo '</textarea>';
    echo "<p>" . lang('cant_write_config_file_note') . "</p>";
} else {
    printf('<span class="ok">%s</span></p>', lang('ok'));
}

if (sessionv('is_upgradeable') == 0) {
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
        'setting_value', '[+prefix+]system_settings', "setting_name='site_id'"
    );
    if ($site_id) {
        if (!$site_id || $site_id = 'MzGeQ2faT4Dw06+U49x3') {
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

include_once('processors/prc_insTemplates.inc.php'); // Install Templates
include_once('processors/prc_insTVs.inc.php');       // Install Template Variables
include_once('processors/prc_insChunks.inc.php');    // Install Chunks
include_once('processors/prc_insModules.inc.php');   // Install Modules
include_once('processors/prc_insPlugins.inc.php');   // Install Plugins
include_once('processors/prc_insSnippets.inc.php');  // Install Snippets

// install data
if (sessionv('is_upgradeable') == 0 && sessionv('installdata') == 1) {
    echo "<p>" . lang('installing_demo_site');
    $sqlParser->intoDB('sample_data.sql');
    if ($sqlParser->installFailed == true) {
        $errors += 1;
        printf('<span class="notok"><b>%s</b></span></p>', lang('database_alerts'));
        echo "<p>" . lang('setup_couldnt_install') . "</p>";
        echo "<p>" . lang('installation_error_occured') . "<br /><br />";
        foreach ($sqlParser->mysqlErrors as $info) {
            printf(
                '<em>%s</em>%s<span class="mono">%s</span>.<hr />'
                , $info['error']
                , lang('during_execution_of_sql')
                , strip_tags($info['sql'])
            );
        }
        echo '</p>';
        echo '<p>' . lang('some_tables_not_updated') . '</p>';
        return;
    }
    printf('<span class="ok">%s</span></p>', lang('ok'));
}

// call back function
if ($callBackFnc != '') $callBackFnc ($sqlParser);

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

function deleteCacheDirectory($cachePath) {
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
