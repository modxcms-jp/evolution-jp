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

$prefix = db()->config['table_prefix'] ?? '';
$excluded = [
    $prefix . 'system_cache',
];

if (strpos($source, "\r") !== false) {
    $source = str_replace(["\r\n", "\r"], "\n", $source);
}

$statements = preg_split('@;[ \t]*\n@', $source);
$skipped = 0;
$executed = 0;

$cacheTable = $prefix . 'system_cache';
$rs = db()->query("SHOW TABLES LIKE '" . db()->escape($cacheTable) . "'");
if ($rs !== false && db()->count($rs) > 0) {
    $fullCacheTable = cli_full_table_name('system_cache');
    if ($fullCacheTable !== '') {
        db()->query('TRUNCATE TABLE ' . $fullCacheTable);
    }
}
if ($rs !== false) {
    db()->freeResult($rs);
}

$preserveKeys = [
    'site_url',
    'base_url',
    'filemanager_path',
    'rb_base_dir',
];
$preserved = [];
$settingsTable = $prefix . 'system_settings';
$rs = db()->query("SHOW TABLES LIKE '" . db()->escape($settingsTable) . "'");
if ($rs !== false && db()->count($rs) > 0) {
    $fullSettingsTable = cli_full_table_name('system_settings');
    if ($fullSettingsTable !== '') {
        foreach ($preserveKeys as $key) {
            $sql = sprintf(
                "SELECT setting_value FROM %s WHERE setting_name='%s' LIMIT 1",
                $fullSettingsTable,
                db()->escape($key)
            );
            $valueRs = db()->query($sql);
            if ($valueRs !== false) {
                $row = db()->getRow($valueRs, 'assoc');
                db()->freeResult($valueRs);
                if ($row && array_key_exists('setting_value', $row)) {
                    $preserved[$key] = $row['setting_value'];
                }
            }
        }
    }
}
if ($rs !== false) {
    db()->freeResult($rs);
}

foreach ($statements as $sql) {
    $sql = trim($sql);
    if ($sql === '') {
        continue;
    }

    $skip = false;
    if (preg_match('/^(INSERT INTO|REPLACE INTO|DROP TABLE IF EXISTS|CREATE TABLE|TRUNCATE TABLE)\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        $table = $m[2] ?? '';
        if ($table !== '' && in_array($table, $excluded, true)) {
            $skip = true;
        }
    }

    if ($skip) {
        $skipped++;
        continue;
    }

    $rs = db()->query($sql);
    if ($rs === false) {
        cli_usage('Error: ' . db()->getLastError());
    }
    $executed++;
}

if ($preserved) {
    $fullSettingsTable = cli_full_table_name('system_settings');
    if ($fullSettingsTable !== '') {
        foreach ($preserved as $key => $value) {
            $sql = sprintf(
                "UPDATE %s SET setting_value='%s' WHERE setting_name='%s'",
                $fullSettingsTable,
                db()->escape($value),
                db()->escape($key)
            );
            $rs = db()->query($sql);
            if ($rs === false) {
                cli_usage('Error: ' . db()->getLastError());
            }
        }
    }
}

cli_out("Import completed. Executed: {$executed}, Skipped: {$skipped}, Preserved: " . count($preserved));
