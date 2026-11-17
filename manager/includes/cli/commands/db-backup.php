<?php

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';

$backup = new EvoBackupManager();
$store = $backup->store();
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

// 管理画面の復元で戻せるよう、プレフィックスのテーブルだけを、ルーチンとトリガーなしで書き出す
$writer = function ($path, array $tables) use ($driver) {
    if (!$tables) {
        cli_usage('Error: no tables found with the configured prefix.');
    }
    return cli_export_database_file($driver, $path, $tables, ['routines' => false]);
};
$filename = $store->autoFilename();
$outputPath = $store->dir() . $filename;
if ($backup->createSnapshot($filename, ['writer' => $writer, 'keep' => $maxGenerations]) === false) {
    cli_usage("Error: failed to write backup {$outputPath}");
}
cli_out("Backup written: {$outputPath}");

foreach ($backup->pruned() as $removed) {
    cli_out('Removed old backup: ' . $removed);
}

$counts = $store->counts();
cli_out('Backups in ' . $store->dir() . ": auto-named {$counts['auto']}/{$maxGenerations}, other {$counts['other']}");
