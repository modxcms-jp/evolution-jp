<?php
/**
 * Command: migrate
 * Description: 未適用のマイグレーションを順に適用する
 */

require_once MODX_CORE_PATH . 'migrator.class.inc.php';

$migrator = new EvoMigrator();

// baseline を通していない DB は、アップデーター（install/）で上げる
// （イベント名の同期・utf8mb4 への変換・同梱の要素の入れ直しはアップデーターだけが行う）
try {
    $requiresInstaller = $migrator->requiresInstaller();
} catch (Throwable $e) {
    cli_usage('Error: ' . $e->getMessage());
}
if ($requiresInstaller) {
    cli_usage(
        'This database has not been upgraded with the installer yet. '
        . 'Upgrade it with the installer (install/) instead of php evo migrate.'
    );
}

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
