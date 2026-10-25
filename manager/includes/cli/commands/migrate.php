<?php
/**
 * Command: migrate
 * Description: 未適用のマイグレーションを順に適用する
 */

require_once MODX_CORE_PATH . 'migrator.class.inc.php';

$migrator = new EvoMigrator();
$result = $migrator->run();

foreach ($result['applied'] as $id) {
    cli_out("Applied {$id}");
}

if ($result['error'] !== null) {
    $target = $result['failed'] !== null ? "Migration {$result['failed']} failed" : 'Migration failed';
    cli_usage("{$target}: {$result['error']}");
}

if (!$result['applied']) {
    cli_out('Nothing to migrate.');
}
