<?php

$tablesArg = '';
$outputPath = '';
$driver = 'mysqldump';
foreach ($args as $arg) {
    if (strpos($arg, '--tables=') === 0) {
        $tablesArg = substr($arg, strlen('--tables='));
    }
    if (strpos($arg, '--output=') === 0) {
        $outputPath = substr($arg, strlen('--output='));
    }
    if (strpos($arg, '--driver=') === 0) {
        $driver = substr($arg, strlen('--driver='));
    }
}

if ($driver !== 'mysqldump' && $driver !== 'php') {
    cli_usage('Usage: php evo db:export [--tables=table1,table2] [--output=path] [--driver=mysqldump|php]');
}

$prefix = db()->config['table_prefix'] ?? '';
$fullTables = [];
if ($tablesArg !== '') {
    $tables = array_filter(array_map('trim', explode(',', $tablesArg)));
    if (!$tables) {
        cli_usage('Usage: php evo db:export [--tables=table1,table2] [--output=path] [--driver=mysqldump|php]');
    }
    foreach ($tables as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            cli_usage('Usage: php evo db:export [--tables=table1,table2] [--output=path] [--driver=mysqldump|php]');
        }
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            $fullTables[] = $table;
        } else {
            $fullTables[] = $prefix . $table;
        }
    }
}

cli_export_database($driver, $outputPath, $fullTables);
