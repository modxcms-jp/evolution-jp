<?php

$table = $args[0] ?? '';
if (strpos($table, '--') === 0) {
    $table = '';
}

$where = '';
foreach ($args as $arg) {
    if (strpos($arg, '--where=') === 0) {
        $where = substr($arg, strlen('--where='));
        break;
    }
}

$fullTable = cli_full_table_name($table);
if ($fullTable === '') {
    cli_usage('Usage: php evo db:count table_name [--where=condition]');
}

$sql = 'SELECT COUNT(*) AS cnt FROM ' . $fullTable;
if ($where !== '') {
    $sql .= ' WHERE ' . $where;
}

$rs = db()->query($sql);
if ($rs === false) {
    cli_usage('Error: ' . db()->getLastError());
}

$row = db()->getRow($rs, 'assoc');
db()->freeResult($rs);

$count = $row['cnt'] ?? 0;
cli_kv('count', (int) $count);
