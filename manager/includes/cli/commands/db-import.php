<?php

$inputPath = $args[0] ?? '';
$inputPath = trim($inputPath);

if ($inputPath === '') {
    cli_usage('Usage: php evo db:import path/to/file.sql');
}

if (!is_file($inputPath)) {
    cli_usage("File not found: {$inputPath}");
}

$source = file_get_contents($inputPath);
if ($source === false || $source === '') {
    cli_usage('Error: failed to read SQL file.');
}

if (!defined('EVO_CLI_IMPORT')) {
    cli_usage('Refusing to import without explicit confirmation. Set EVO_CLI_IMPORT=1.');
}

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';

global $modx;
require_once MODX_CORE_PATH . 'mysql_dumper.class.inc.php';

$backup = new EvoBackupManager();

// 復元前のスナップショットも mysqldump で取る（PHP のダンパーの遅さとメモリを CLI に持ち込まない）
$preRestore = function ($fullPath) {
    return cli_export_database_file(
        'mysqldump',
        $fullPath,
        (new Mysqldumper())->getTableNames(),
        ['routines' => false]
    );
};
$result = $backup->restore($source, $preRestore);

if ($result['pre_restore_failed']) {
    cli_usage('Error: failed to save the pre-restore snapshot; nothing was imported.');
}

cli_out("Pre-restore snapshot: {$result['pre_restore']}");
if (!$result['ok']) {
    cli_err("Error: statement {$result['failed_at']} failed: {$result['error']}");
    cli_out("Executed: {$result['executed']}, Skipped: {$result['skipped']}");
    cli_usage("Restore the previous state from {$result['pre_restore']} in " . $backup->dir());
}

cli_out("Import completed. Executed: {$result['executed']}, Skipped: {$result['skipped']}");
if ($result['error'] !== '') {
    cli_err('Warning: ' . $result['error']);
}
