<?php

$defaultsFile = cli_mysql_defaults_file();
$dbname = trim(db()->config['dbase'] ?? '', '`');

echo "Connecting to MySQL...\n";
echo "Database: " . $dbname . "\n";
echo "Prefix: " . (db()->config['table_prefix'] ?? '') . "\n";
echo "---\n";

$cmd = sprintf(
    'mysql --defaults-extra-file=%s %s',
    escapeshellarg($defaultsFile),
    escapeshellarg($dbname)
);
passthru($cmd, $exitCode);
exit($exitCode);
