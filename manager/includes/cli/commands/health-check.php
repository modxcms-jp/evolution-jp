<?php

$checks = [];
$hasError = false;

// PHP extensions
$checks[] = check('PHP gd extension', extension_loaded('gd'));
$checks[] = check('PHP mysqli extension', extension_loaded('mysqli'));
$checks[] = check('PHP mbstring extension', extension_loaded('mbstring'));
$checks[] = check('PHP json extension', extension_loaded('json'));

// Database connection
$dbOk = false;
try {
    $rs = db()->query('SELECT 1');
    $dbOk = ($rs !== false);
} catch (\Throwable $e) {
    // connection failed
}
$checks[] = check('Database connection', $dbOk);

// Database version
if ($dbOk) {
    $checks[] = info('Database version', db()->getVersion());
}

// PHP version
$checks[] = info('PHP version', PHP_VERSION);

// CMS version
$checks[] = info('CMS version', evo()->config('settings_version') ?: 'unknown');

// Cache directory writable
$checks[] = check('Cache directory writable', is_writable(MODX_CACHE_PATH));

// config.inc.php not world-writable
$configFile = MODX_CORE_PATH . 'config.inc.php';
$configWritable = is_writable($configFile);
$checks[] = check('config.inc.php read-only', !$configWritable, 'writable (should be read-only)');

// Install directory removed
$installDir = MODX_BASE_PATH . 'install/';
$checks[] = check('Install directory removed', !is_dir($installDir), 'exists (security risk)');

// action.php removed or safe
$actionPhp = MODX_BASE_PATH . 'action.php';
$actionOk = true;
if (is_file($actionPhp)) {
    $src = file_get_contents($actionPhp);
    if (strpos($src, 'if(strpos($path,MODX_MANAGER_PATH)!==0)') === false) {
        $actionOk = false;
    }
}
$checks[] = check('action.php safe', $actionOk, 'outdated version found');

// rb_base_dir exists
$rbBaseDir = evo()->config('rb_base_dir');
if ($rbBaseDir) {
    $rbBaseDir = str_replace('[(base_path)]', MODX_BASE_PATH, $rbBaseDir);
    $checks[] = check('rb_base_dir exists', is_dir($rbBaseDir), $rbBaseDir);
    if (is_dir($rbBaseDir)) {
        $checks[] = check('rb_base_dir/images writable', is_writable($rbBaseDir . 'images'));
    }
}

// filemanager_path exists
$fmPath = evo()->config('filemanager_path');
if ($fmPath) {
    $fmPath = str_replace('[(base_path)]', MODX_BASE_PATH, $fmPath);
    $checks[] = check('filemanager_path exists', is_dir($fmPath), $fmPath);
}

// Error page published
$errorPage = evo()->config('error_page');
if ($errorPage) {
    $pub = db()->getValue(db()->select('published', '[+prefix+]site_content', "id='" . (int)$errorPage . "'"));
    $checks[] = check('Error page (id=' . $errorPage . ') published', $pub == 1);
}

// Unauthorized page published
$unauthPage = evo()->config('unauthorized_page');
if ($unauthPage) {
    $pub = db()->getValue(db()->select('published', '[+prefix+]site_content', "id='" . (int)$unauthPage . "'"));
    $checks[] = check('Unauthorized page (id=' . $unauthPage . ') published', $pub == 1);
}

// Output results
foreach ($checks as $c) {
    if ($c['status'] === 'FAIL') {
        $hasError = true;
    }
    $label = str_pad($c['status'], 4);
    $line = "[{$label}] {$c['name']}";
    if (isset($c['detail'])) {
        $line .= " -- {$c['detail']}";
    }
    cli_out($line);
}

exit($hasError ? 1 : 0);

// --- helpers ---

function check($name, $ok, $failDetail = null)
{
    $result = ['name' => $name, 'status' => $ok ? 'OK' : 'FAIL'];
    if (!$ok && $failDetail !== null) {
        $result['detail'] = $failDetail;
    }
    return $result;
}

function info($name, $value)
{
    return ['name' => $name, 'status' => 'INFO', 'detail' => $value];
}
