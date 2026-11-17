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
    cli_usage('Usage: php evo.php db:export [--tables=table1,table2] [--output=path] [--driver=mysqldump|php]');
}

require_once MODX_CORE_PATH . 'db_tables.class.inc.php';
$fullTables = [];
if ($tablesArg !== '') {
    $tables = array_filter(array_map('trim', explode(',', $tablesArg)));
    if (!$tables) {
        cli_usage('Usage: php evo.php db:export [--tables=table1,table2] [--output=path] [--driver=mysqldump|php]');
    }
    $names = EvoDbTables::names();
    foreach ($tables as $table) {
        $fullTable = EvoDbTables::resolve($table, $names);
        if ($fullTable === '') {
            cli_usage("Error: no such table with the prefix " . db()->table_prefix . ": {$table}");
        }
        $fullTables[] = $fullTable;
    }
}

cli_export_database($driver, $outputPath, $fullTables);
