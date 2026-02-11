<?php

$confirmed = false;
foreach ($args as $arg) {
    if ($arg === '--yes') {
        $confirmed = true;
        break;
    }
}

if (!$confirmed) {
    cli_usage('Usage: php evo log:clear --yes');
}

$before = (int)db()->getValue(
    db()->select('COUNT(*)', '[+prefix+]event_log')
);

$fullTable = cli_full_table_name('event_log');
if ($fullTable === '') {
    cli_usage('Error: event_log table is not available.');
}

$ok = db()->query('TRUNCATE TABLE ' . $fullTable);
if ($ok === false) {
    cli_usage('Error: ' . db()->getLastError());
}

cli_out("Event log cleared. Removed: {$before}");
