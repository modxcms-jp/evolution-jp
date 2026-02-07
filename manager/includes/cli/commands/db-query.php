<?php

$query = trim(implode(' ', $args));
if ($query === '') {
    cli_usage('Usage: php evo db:query \"SELECT ...\"');
}

$result = db()->query($query);
if ($result === false) {
    $error = db()->getLastError();
    if (!$error) {
        $error = 'Unknown error';
    }
    cli_usage("Error: {$error}");
}

if (db()->isResult($result)) {
    $rows = [];
    while ($row = db()->getRow($result, 'assoc')) {
        $rows[] = $row;
    }
    db()->freeResult($result);

    if (!$rows) {
        cli_out('(no rows)');
        exit(0);
    }

    foreach ($rows as $row) {
        cli_out(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    exit(0);
}

$affected = db()->getAffectedRows();
cli_out("OK ({$affected} rows affected)");
