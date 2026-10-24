<?php

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';

$backup = new EvoBackupManager();
$maxGenerations = EvoBackupManager::KEEP;
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

if (!$backup->ensureDir()) {
    cli_usage('Error: cannot create or write directory ' . $backup->dir());
}

global $modx;
require_once MODX_CORE_PATH . 'mysql_dumper.class.inc.php';
$dumper = new Mysqldumper();
$tables = $dumper->getTableNames();
if (!$tables) {
    cli_usage('Error: no tables found with the configured prefix.');
}

$filename = $backup->autoFilename();
$outputPath = $backup->dir() . $filename;

// 管理画面の復元で戻せるよう、プレフィックスのテーブルだけを、ルーチンとトリガーなしで書き出す
if (!cli_export_database_file($driver, $outputPath, $tables, ['routines' => false])) {
    cli_usage("Error: failed to write backup {$outputPath}");
}
cli_out("Backup written: {$outputPath}");

foreach ($backup->prune($maxGenerations) as $removed) {
    cli_out('Removed old backup: ' . $removed);
}

cli_out('Backups in ' . $backup->dir() . ': ' . count($backup->files()) . ' (auto-named kept: max ' . $maxGenerations . ')');
