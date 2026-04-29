<?php

$type = 'system';
$lines = 20;
$level = '';
$fileLimit = 100;
$json = false;

foreach ($args as $arg) {
    if (strpos($arg, '--type=') === 0) {
        $type = strtolower(substr($arg, strlen('--type=')));
        continue;
    }
    if (strpos($arg, '--lines=') === 0) {
        $lines = max(1, min(1000, (int)substr($arg, strlen('--lines='))));
        continue;
    }
    if (strpos($arg, '--limit=') === 0) {
        $lines = max(1, min(1000, (int)substr($arg, strlen('--limit='))));
        continue;
    }
    if (strpos($arg, '--level=') === 0) {
        $level = strtolower(substr($arg, strlen('--level=')));
        continue;
    }
    if (strpos($arg, '--files=') === 0) {
        $fileLimit = max(1, min(1000, (int)substr($arg, strlen('--files='))));
        continue;
    }
    if ($arg === '--json') {
        $json = true;
        continue;
    }

    cli_usage('Usage: php evo log:tail [--type=system] [--lines=N] [--level=LEVEL] [--files=N] [--json]');
}

if ($type !== 'system') {
    cli_usage('Usage: php evo log:tail [--type=system] [--lines=N] [--level=LEVEL] [--files=N] [--json]');
}

$allowedLevels = ['', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
if (!in_array($level, $allowedLevels, true)) {
    cli_usage('Usage: php evo log:tail [--type=system] [--lines=N] [--level=LEVEL] [--files=N] [--json]');
}

include_once MODX_CORE_PATH . 'system_log.viewer.inc.php';

$root = MODX_BASE_PATH . 'temp/logs/system/';
$files = SystemLogViewer::latestFiles(SystemLogViewer::files($root), $fileLimit);
$result = SystemLogViewer::readLatestEntries($root, $files, $level, '', '', '', 0, $lines);

if (!$result['entries']) {
    cli_out('(no system log entries)');
    exit(0);
}

foreach ($result['entries'] as $entry) {
    if ($json) {
        cli_out(json_encode($entry['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        continue;
    }

    cli_out(cli_system_log_format_entry($entry));
}
