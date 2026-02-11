<?php

function cli_out($message)
{
    echo $message . "\n";
}

function cli_err($message)
{
    fwrite(STDERR, $message . "\n");
}

function cli_kv($key, $value)
{
    echo $key . '=' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

function cli_usage($message, $code = 1)
{
    cli_err($message);
    exit($code);
}

/**
 * Create a temporary MySQL defaults-extra-file for secure credential passing.
 * Returns the temp file path. The file is auto-deleted on shutdown.
 */
function cli_mysql_defaults_file()
{
    if (db()->config['host'] === '' || db()->config['user'] === '') {
        cli_usage('Database configuration is missing.');
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'evo_mysql_');
    if ($tmpFile === false) {
        cli_usage('Failed to create temp file.');
    }
    chmod($tmpFile, 0600);

    register_shutdown_function(function () use ($tmpFile) {
        if (is_file($tmpFile)) {
            unlink($tmpFile);
        }
    });

    // Detect MariaDB vs MySQL client for SSL option compatibility
    $isMariaDB = false;
    $verOutput = shell_exec('mysql --version 2>/dev/null') ?? '';
    if (stripos($verOutput, 'mariadb') !== false) {
        $isMariaDB = true;
    }
    $sslLine = $isMariaDB ? "ssl=0" : "ssl-mode=DISABLED";

    $content = sprintf(
        "[client]\nhost=%s\nuser=%s\npassword=%s\n%s\n",
        db()->config['host'],
        db()->config['user'],
        db()->config['pass'],
        $sslLine
    );
    file_put_contents($tmpFile, $content);

    return $tmpFile;
}

function cli_full_table_name($table)
{
    $table = trim($table);
    if ($table === '') {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return '';
    }
    $prefix = db()->config['table_prefix'] ?? '';
    $dbase = db()->config['dbase'] ?? '';
    if ($dbase === '') {
        return '';
    }
    if ($prefix !== '' && str_starts_with($table, $prefix)) {
        $full = $table;
    } else {
        $full = $prefix . $table;
    }
    return sprintf('`%s`.`%s`', $dbase, $full);
}
