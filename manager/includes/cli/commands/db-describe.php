<?php

$table = $args[0] ?? '';
require_once MODX_CORE_PATH . 'db_tables.class.inc.php';
$fullTable = EvoDbTables::qualifiedWithDb($table);
if ($fullTable === '') {
    cli_usage('Usage: php evo.php db:describe table_name');
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
