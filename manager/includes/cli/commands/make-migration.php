<?php
/**
 * Command: make:migration
 * Description: マイグレーションの雛形を manager/includes/migrations/ に作る
 */

require_once MODX_CORE_PATH . 'migrator.class.inc.php';

$description = trim($args[0] ?? '');
if (!preg_match('/\A[a-z0-9_]+\z/', $description)) {
    cli_usage('Usage: php evo make:migration <description>  (description: a-z, 0-9, _)');
}

$migrator = new EvoMigrator();
$dir = $migrator->dir();
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    cli_usage("Failed to create directory: {$dir}");
}

$filePath = $dir . date('Ymd_His') . '_' . $description . '.php';
if (is_file($filePath)) {
    cli_usage("Migration already exists: {$filePath}");
}

$template = <<<'PHP'
<?php

// 存在確認や最終形との比較を省かない（もう一度流しても何も変わらないようにする）。
// 表を作るのは create_tables.sql だけ。ここでは既にある表を変える。
return function (EvoMigrationHelper $m) {
};
PHP;

if (file_put_contents($filePath, $template . "\n") === false) {
    cli_usage("Failed to write: {$filePath}");
}

chmod($filePath, 0644);

cli_out("Created: {$filePath}");
