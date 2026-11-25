<?php

$inputPath = $args[0] ?? '';
$inputPath = trim($inputPath);

if ($inputPath === '') {
    cli_usage('Usage: php evo.php db:import path/to/file.sql');
}

if (!is_file($inputPath)) {
    cli_usage("File not found: {$inputPath}");
}

if (!is_readable($inputPath) || (int) filesize($inputPath) === 0) {
    cli_usage('Error: failed to read SQL file.');
}

if (!defined('EVO_CLI_IMPORT')) {
    cli_usage('Refusing to import without explicit confirmation. Set EVO_CLI_IMPORT=1.');
}

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';

$backup = new EvoBackupManager();
// 保存先が使えなければ、復元の前の控えを取れないので戻さない（別の場所には切り替えない）
if (!$backup->store()->ensureDir()) {
    if ($backup->store()->problem() !== EvoSnapshotStore::UNWRITABLE) {
        cli_usage('Error: the snapshot directory ' . $backup->store()->dir() . ' cannot be used (outside the site folder, or a folder that holds other files); nothing was imported. Fix snapshot_path in the global settings.');
    }
    cli_usage('Error: cannot create or write directory ' . $backup->store()->dir() . '; nothing was imported.');
}

// 復元前のスナップショットも mysqldump で取る（PHP のダンパーの遅さとメモリを CLI に持ち込まない）
// プレフィックスのテーブルが無い（空の DB への取り込み）ときは保存するものが無いので書かない
$preRestoreSkipped = false;
$preRestore = function ($fullPath) use (&$preRestoreSkipped) {
    $tables = EvoDbTables::names();
    if (!$tables) {
        $preRestoreSkipped = true;
        return true;
    }
    return cli_export_database_file('mysqldump', $fullPath, $tables, ['routines' => false]);
};
$result = $backup->restoreFile($inputPath, $preRestore);

if ($result['read_failed']) {
    cli_usage('Error: failed to read SQL file.');
}
if ($result['refused'] === 'delimiter') {
    cli_usage('Error: the dump contains DELIMITER (triggers or stored routines) and cannot be restored here; nothing was changed. Restore it with the mysql command.');
}
if ($result['refused'] === 'database') {
    $databases = array_slice($result['foreign_databases'], 0, 5);
    cli_usage(sprintf(
        'Error: the dump points to a database other than this site\'s with USE or similar, or contains DROP DATABASE or ALTER DATABASE (this site: %s, found: %s); nothing was changed. Restore a dump of this site\'s tables only.',
        "'" . db()->dbname . "'",
        implode(', ', $databases) . (count($result['foreign_databases']) > 5 ? ', ...' : '')
    ));
}
if ($result['refused'] === 'prefix') {
    $tables = array_slice($result['foreign_tables'], 0, 5);
    cli_usage(sprintf(
        'Error: the dump has tables without this site\'s prefix (dump prefix: %s, this site: %s, tables: %s); nothing was changed.',
        $result['dump_prefix'] !== null ? "'{$result['dump_prefix']}'" : '?',
        "'" . db()->table_prefix . "'",
        implode(', ', $tables) . (count($result['foreign_tables']) > 5 ? ', ...' : '')
    ));
}
if ($result['pre_restore_failed']) {
    cli_usage('Error: failed to save the pre-restore snapshot; nothing was imported.');
}

if ($preRestoreSkipped) {
    cli_out('Pre-restore snapshot: skipped (no tables with prefix ' . db()->table_prefix . ')');
} else {
    cli_out("Pre-restore snapshot: {$result['pre_restore']}");
}
if (!$result['ok']) {
    cli_err("Error: statement {$result['failed_at']} failed: {$result['error']}");
    cli_out("Executed: {$result['executed']}, Skipped: {$result['skipped']}");
    if ($preRestoreSkipped) {
        cli_usage('The database had no tables with the prefix before the import; there is no snapshot to restore from.');
    }
    cli_usage("Restore the previous state from {$result['pre_restore']} in " . $backup->store()->dir());
}

cli_out("Import completed. Executed: {$result['executed']}, Skipped: {$result['skipped']}");
if ($result['error'] !== '') {
    cli_err('Warning: ' . $result['error']);
}
