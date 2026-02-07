<?php

$tablesArg = '';
$outputPath = '';
foreach ($args as $arg) {
    if (strpos($arg, '--tables=') === 0) {
        $tablesArg = substr($arg, strlen('--tables='));
    }
    if (strpos($arg, '--output=') === 0) {
        $outputPath = substr($arg, strlen('--output='));
    }
}

require_once MODX_CORE_PATH . 'mysql_dumper.class.inc.php';

$dump = new Mysqldumper();

if ($tablesArg !== '') {
    $tables = array_filter(array_map('trim', explode(',', $tablesArg)));
    if (!$tables) {
        cli_usage('Usage: php evo db:export [--tables=table1,table2] [--output=path]');
    }
    $prefix = db()->config['table_prefix'] ?? '';
    $fullTables = [];
    foreach ($tables as $table) {
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            $fullTables[] = $table;
        } else {
            $fullTables[] = $prefix . $table;
        }
    }
    $dump->setDBtables($fullTables);
}

$sql = $dump->createDump();
if ($sql === false || $sql === '') {
    cli_usage('Error: export failed.');
}

if ($outputPath !== '') {
    $written = file_put_contents($outputPath, $sql);
    if ($written === false) {
        cli_usage('Error: failed to write output.');
    }
    cli_out("Written: {$outputPath}");
    exit(0);
}

echo $sql;
