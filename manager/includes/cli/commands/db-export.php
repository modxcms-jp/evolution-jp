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

$prefix = db()->config['table_prefix'] ?? '';
$fullTables = [];
if ($tablesArg !== '') {
    $tables = array_filter(array_map('trim', explode(',', $tablesArg)));
    if (!$tables) {
        cli_usage('Usage: php evo db:export [--tables=table1,table2] [--output=path] [--driver=mysqldump|php]');
    }
    foreach ($tables as $table) {
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            $fullTables[] = $table;
        } else {
            $fullTables[] = $prefix . $table;
        }
    }
}

if ($driver === 'php') {
    // Fallback: use built-in Mysqldumper class
    require_once MODX_CORE_PATH . 'mysql_dumper.class.inc.php';
    $dump = new Mysqldumper();
    if ($fullTables) {
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
        cli_out("Written: {$outputPath} (driver=php)");
        exit(0);
    }
    echo $sql;
    exit(0);
}

// Default: mysqldump
$mysqldump = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
if ($mysqldump === '') {
    cli_err('Error: mysqldump command not found.');
    cli_usage('Hint: use --driver=php to export with the built-in PHP dumper.');
}

$defaultsFile = cli_mysql_defaults_file();
$dbname = trim(db()->config['dbase'] ?? '', '`');

$cmd = sprintf(
    'mysqldump --defaults-extra-file=%s --single-transaction --routines --triggers --no-tablespaces %s',
    escapeshellarg($defaultsFile),
    escapeshellarg($dbname)
);

if ($fullTables) {
    foreach ($fullTables as $t) {
        $cmd .= ' ' . escapeshellarg($t);
    }
}

if ($outputPath !== '') {
    $cmd .= ' --result-file=' . escapeshellarg($outputPath);
}

passthru($cmd, $exitCode);

if ($exitCode !== 0) {
    cli_usage("Error: mysqldump exited with code {$exitCode}.", $exitCode);
}

if ($outputPath !== '') {
    cli_out("Written: {$outputPath}");
}
