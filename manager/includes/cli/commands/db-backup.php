<?php

$maxGenerations = 10;
$driver = 'mysqldump';
foreach ($args as $arg) {
    if (strpos($arg, '--max=') === 0) {
        $maxGenerations = max(1, (int)substr($arg, strlen('--max=')));
        continue;
    }
    if (strpos($arg, '--driver=') === 0) {
        $driver = substr($arg, strlen('--driver='));
    }
}

if ($driver !== 'mysqldump' && $driver !== 'php') {
    cli_usage('Usage: php evo db:backup [--max=N] [--driver=mysqldump|php]');
}

$snapshotPath = resolveSnapshotPath();
ensureSnapshotPath($snapshotPath);

$version = evo()->config('settings_version') ?: 'unknown';
$filename = sprintf('%s-%s.sql', date('Y-m-d-His'), $version);
$outputPath = $snapshotPath . $filename;

cli_export_database($driver, $outputPath, [], true);
cli_out("Backup written: {$outputPath}");

$files = glob($snapshotPath . '*.sql');
if ($files === false) {
    $files = [];
}

rsort($files);
$total = count($files);
$limit = 0;
while ($total > $maxGenerations && $limit < 100) {
    $oldFile = array_pop($files);
    if (!is_string($oldFile)) {
        break;
    }
    if (is_file($oldFile) && unlink($oldFile)) {
        cli_out('Removed old backup: ' . basename($oldFile));
    }
    $total = count($files);
    $limit++;
}

cli_out("Backups in {$snapshotPath}: {$total}/{$maxGenerations}");

function resolveSnapshotPath()
{
    $configPath = evo()->config('snapshot_path') ?: '';
    if ($configPath !== '' && strpos($configPath, MODX_BASE_PATH) === 0) {
        return rtrim($configPath, '/') . '/';
    }
    if (is_dir(MODX_BASE_PATH . 'temp/backup/')) {
        return MODX_BASE_PATH . 'temp/backup/';
    }
    if (is_dir(MODX_BASE_PATH . 'assets/backup/')) {
        return MODX_BASE_PATH . 'assets/backup/';
    }
    return MODX_BASE_PATH . 'temp/backup/';
}

function ensureSnapshotPath($snapshotPath)
{
    $dir = rtrim($snapshotPath, '/');
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            cli_usage("Error: cannot create directory {$snapshotPath}");
        }
    }
    if (!is_writable($dir)) {
        cli_usage("Error: directory not writable: {$snapshotPath}");
    }
    if (!is_file($snapshotPath . '.htaccess')) {
        $ok = file_put_contents($snapshotPath . '.htaccess', "order deny,allow\ndeny from all\n");
        if ($ok === false) {
            cli_usage("Error: failed to create {$snapshotPath}.htaccess");
        }
    }
}
