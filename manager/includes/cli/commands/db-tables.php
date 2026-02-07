<?php

$pattern = $args[0] ?? '';
if (strpos($pattern, '--pattern=') === 0) {
    $pattern = substr($pattern, strlen('--pattern='));
}
$pattern = trim($pattern);

$sql = 'SHOW TABLES';
if ($pattern !== '') {
    $sql .= " LIKE '" . db()->escape($pattern) . "'";
}

$rs = db()->query($sql);
if ($rs === false) {
    cli_usage('Error: ' . db()->getLastError());
}

$tables = [];
while ($row = db()->getRow($rs, 'num')) {
    $tables[] = $row[0];
}

db()->freeResult($rs);

foreach ($tables as $table) {
    cli_out($table);
}
