<?php

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';

$store = new EvoSnapshotStore();
$maxGenerations = EvoSnapshotStore::KEEP;
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
    cli_usage('Usage: php evo.php db:backup [--max=N] [--driver=mysqldump|php]');
}

if (!$store->ensureDir()) {
    if ($store->problem() !== EvoSnapshotStore::UNWRITABLE) {
        cli_usage('Error: the snapshot directory ' . $store->dir() . ' cannot be used (outside the site folder, or a folder that holds other files). Fix snapshot_path in the global settings.');
    }
    cli_usage('Error: cannot create or write directory ' . $store->dir());
}

$tables = EvoDbTables::names();
if (!$tables) {
    cli_usage('Error: no tables found with the configured prefix.');
}

$filename = $store->autoFilename();
$outputPath = $store->dir() . $filename;

// 管理画面の復元で戻せるよう、プレフィックスのテーブルだけを、ルーチンとトリガーなしで書き出す
if (!cli_export_database_file($driver, $outputPath, $tables, ['routines' => false])) {
    cli_usage("Error: failed to write backup {$outputPath}");
}
cli_out("Backup written: {$outputPath}");

foreach ($store->prune($maxGenerations) as $removed) {
    cli_out('Removed old backup: ' . $removed);
}

$counts = $store->counts();
cli_out('Backups in ' . $store->dir() . ": auto-named {$counts['auto']}/{$maxGenerations}, other {$counts['other']}");
