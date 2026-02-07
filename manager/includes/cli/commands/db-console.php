<?php

if (!isset(db()->config['dbase'])) {
    db()->config['dbase'] = '';
}
if (!isset(db()->config['table_prefix'])) {
    db()->config['table_prefix'] = '';
}

if (db()->config['host'] === '' || db()->config['user'] === '') {
    fwrite(STDERR, "Database configuration is missing.\\n");
    exit(1);
}

$tmpConfig = tempnam(sys_get_temp_dir(), 'evo_mysql_');
if ($tmpConfig === false) {
    fwrite(STDERR, "Failed to create temp file.\n");
    exit(1);
}
chmod($tmpConfig, 0600);

register_shutdown_function(function () use ($tmpConfig) {
    if (is_file($tmpConfig)) {
        unlink($tmpConfig);
    }
});

$configContent = sprintf(
    "[client]\nhost=%s\nuser=%s\npassword=%s\ndatabase=%s\n",
    db()->config['host'],
    db()->config['user'],
    db()->config['pass'],
    db()->config['dbase']
);

file_put_contents($tmpConfig, $configContent);

echo "Connecting to MySQL...\n";
echo "Database: " . db()->config['dbase'] . "\n";
echo "Prefix: " . db()->config['table_prefix'] . "\n";
echo "---\n";

$cmd = 'mysql --defaults-extra-file=' . escapeshellarg($tmpConfig);
passthru($cmd, $exitCode);
exit($exitCode);
