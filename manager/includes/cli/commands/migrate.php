<?php
/**
 * Command: migrate
 * Description: 未適用のマイグレーションを順に適用する
 */

require_once MODX_CORE_PATH . 'migrator.class.inc.php';

// 古い版の DB は、アップデーター（install/）でそろえる（表を作るのは create_tables.sql だけ）。
// system_cache が無いとキャッシュを消せず、流したあとに止まるため、流す前に確かめる
if (!(new EvoMigrationHelper())->tableExists('system_cache')) {
    cli_usage(
        'This database predates the current table definitions. '
        . 'Upgrade it with the installer (install/) instead of php evo migrate.'
    );
}

$migrator = new EvoMigrator();
$result = $migrator->run();

foreach ($result['applied'] as $id) {
    cli_out("Applied {$id}");
}

// キャッシュを消す処理が失敗の出力を潰さないよう、失敗の文を先に出す
if ($result['error'] !== null) {
    $target = $result['failed'] !== null ? "Migration {$result['failed']} failed" : 'Migration failed';
    cli_err("{$target}: {$result['error']}");
}

// 適用の途中で止まった場合も、済んだ分は反映されているのでキャッシュを消す
if ($result['applied'] || $result['failed'] !== null) {
    evo()->clearCache();
}

if ($result['error'] !== null) {
    exit(1);
}

if (!$result['applied']) {
    cli_out('Nothing to migrate.');
}
