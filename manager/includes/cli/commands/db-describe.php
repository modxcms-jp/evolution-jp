<?php

$table = $args[0] ?? '';
$fullTable = cli_full_table_name($table);
if ($fullTable === '') {
    cli_usage('Usage: php evo db:describe table_name');
}

$sql = 'DESCRIBE ' . $fullTable;
$rs = db()->query($sql);
if ($rs === false) {
    cli_usage('Error: ' . db()->getLastError());
}

while ($row = db()->getRow($rs, 'assoc')) {
    cli_out(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

db()->freeResult($rs);
