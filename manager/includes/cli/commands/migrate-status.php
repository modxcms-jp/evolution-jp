<?php
/**
 * Command: migrate:status
 * Description: マイグレーションの適用状況を表示する
 */

require_once MODX_CORE_PATH . 'migrator.class.inc.php';

$migrator = new EvoMigrator();
try {
    $applied = $migrator->applied();
    $all = $migrator->all();
} catch (Throwable $e) {
    cli_usage('Error: ' . $e->getMessage());
}

if (!(new EvoMigrationHelper())->tableExists('migrations')) {
    cli_out('Migrations table not found (every migration is pending).');
}

foreach ($all as $id) {
    if (isset($applied[$id])) {
        cli_out(sprintf('applied %s %s', $id, date('Y-m-d H:i:s', $applied[$id])));
        continue;
    }
    cli_out("pending {$id}");
}

// ファイルが無くなった適用済みの記録も知らせる
foreach (array_diff_key($applied, array_flip($all)) as $id => $time) {
    cli_out(sprintf('applied %s %s (file missing)', $id, date('Y-m-d H:i:s', $time)));
}

$pending = count($all) - count(array_intersect($all, array_keys($applied)));
cli_out(sprintf('%d applied, %d pending', count($applied), $pending));
