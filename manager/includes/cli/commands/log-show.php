<?php

$limit = 20;
$typeFilter = '';
foreach ($args as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = max(1, min(1000, (int)substr($arg, strlen('--limit='))));
    }
    if (strpos($arg, '--type=') === 0) {
        $typeFilter = substr($arg, strlen('--type='));
    }
}

$typeMap = ['1' => 'INFO', '2' => 'WARN', '3' => 'ERROR'];
$typeNames = ['info' => '1', 'warn' => '2', 'warning' => '2', 'error' => '3'];

$where = '';
if ($typeFilter !== '') {
    $t = $typeNames[strtolower($typeFilter)] ?? $typeFilter;
    if (!isset($typeMap[$t])) {
        cli_usage('Usage: php evo log:show [--limit=N] [--type=info|warn|error]');
    }
    $where = "type='{$t}'";
}

$rs = db()->select(
    'id, type, source, description, createdon',
    '[+prefix+]event_log',
    $where,
    'id DESC',
    $limit
);

if (!db()->count($rs)) {
    cli_out('(no log entries)');
    exit(0);
}

// Collect rows and reverse to show oldest first (newest at bottom)
$rows = [];
while ($row = db()->getRow($rs)) {
    $rows[] = $row;
}
$rows = array_reverse($rows);

foreach ($rows as $row) {
    $label = $typeMap[$row['type']] ?? '?';
    $date = date('Y-m-d H:i:s', (int)$row['createdon']);
    $source = $row['source'] ?? '';
    $desc = cleanHtml($row['description'] ?? '');

    // Truncate long descriptions
    if (mb_strlen($desc) > 200) {
        $desc = mb_substr($desc, 0, 200) . '...';
    }

    cli_out(sprintf('[%5s] %s  %s', $label, $date, $source));
    if ($desc !== '') {
        cli_out("        {$desc}");
    }
}

function cleanHtml($text)
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Convert <br> to newline before stripping
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Collapse whitespace
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}
