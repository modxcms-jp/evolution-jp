<?php

$usage = 'Usage: php evo log:clear [--before=YYYY-MM-DD] [--force]';
$before = null;
$force = false;

foreach ($args as $arg) {
    if (strpos($arg, '--before=') === 0) {
        $before = substr($arg, strlen('--before='));
        continue;
    }
    if ($arg === '--force') {
        $force = true;
        continue;
    }

    cli_usage($usage);
}

function cli_stdin_is_interactive(): bool
{
    if (function_exists('stream_isatty')) {
        return stream_isatty(STDIN);
    }
    if (function_exists('posix_isatty')) {
        return posix_isatty(STDIN);
    }

    return false;
}

include_once MODX_CORE_PATH . 'system_log.viewer.inc.php';

if ($before !== null && !SystemLogViewer::isValidDate($before)) {
    cli_usage($usage);
}
$before = (string)$before;

if (!$force) {
    if (!defined('STDIN') || !is_resource(STDIN) || !cli_stdin_is_interactive()) {
        cli_err('Refusing to delete without confirmation. Use --force when STDIN is not interactive.');
        exit(1);
    }
    $target = $before !== '' ? "system logs before {$before}" : 'all system logs';
    fwrite(STDOUT, "Delete {$target}? This cannot be undone. [y/N] ");
    $answer = fgets(STDIN);
    if ($answer === false || !in_array(strtolower(trim($answer)), ['y', 'yes'], true)) {
        cli_out('Canceled.');
        exit(1);
    }
}

$result = SystemLogViewer::clear(MODX_BASE_PATH . 'temp/logs/system/', $before);

cli_out('Deleted: ' . $result['deleted']);
cli_out('Failed: ' . count($result['failed']));
foreach ($result['failed'] as $path => $reason) {
    cli_err($path . ($reason !== '' ? ' (' . $reason . ')' : ''));
}

if ($result['failed']) {
    exit(1);
}
