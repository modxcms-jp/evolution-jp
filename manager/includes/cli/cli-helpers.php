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
