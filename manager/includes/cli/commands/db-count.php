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

require_once MODX_CORE_PATH . 'db_tables.class.inc.php';
$fullTable = EvoDbTables::qualifiedWithDb($table);
if ($fullTable === '') {
    cli_usage('Usage: php evo.php db:count table_name [--where=condition]');
}

$sql = 'SELECT COUNT(*) AS cnt FROM ' . $fullTable;
if ($where !== '') {
    $sql .= ' WHERE ' . $where;
}

$rs = db()->query($sql);
if ($rs === false) {
    cli_usage('Error: ' . db()->getLastError());
}
if (!db()->isResult($rs)) {
    cli_usage('Error: unexpected result for COUNT query.');
}

$row = db()->getRow($rs, 'assoc');
db()->freeResult($rs);

$count = $row['cnt'] ?? 0;
cli_kv('count', (int) $count);
