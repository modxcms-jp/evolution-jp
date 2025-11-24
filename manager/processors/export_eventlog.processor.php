<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('view_eventlog')) {
    alert()->setError(3);
    alert()->dumpError();
}

$search = anyv('search', '');
if (!is_numeric($search)) {
    $search = db()->escape($search);
}

$fields = "el.id, el.type, el.createdon, el.source, el.description, IFNULL(wu.username,mu.username) as 'username'";
$from = '[+prefix+]event_log el';
$from .= ' LEFT JOIN [+prefix+]manager_users mu ON mu.id=el.user AND el.usertype=0';
$from .= ' LEFT JOIN [+prefix+]web_users wu ON wu.id=el.user AND el.usertype=1';

$where = '';
if ($search !== '') {
    if (is_numeric($search)) {
        $where = "(eventid='{$search}') OR ";
    }
    $where .= "(source LIKE '%{$search}%') OR (description LIKE '%{$search}%')";
}

$rs = db()->select($fields, $from, $where, 'el.id DESC');

$logs = [];
while ($row = db()->getRow($rs)) {
    $logs[] = format_event_log($row);
}

if (!$logs) {
    $logs[] = lang('no_records_found');
}

$charset = evo()->getConfig('modx_charset', 'UTF-8');
$filename = 'event-log-' . date('Ymd-His') . '.txt';

header('Content-Type: text/plain; charset=' . $charset);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

echo implode("\n\n", $logs);

function format_event_log($row)
{
    $type = get_log_type((int)$row['type']);
    $date = evo()->toDateFormat($row['createdon']);
    $username = $row['username'] ?: '';
    $description = clean_log_description($row['description']);

    $parts = [
        lang('event_id') . ': ' . $row['id'],
        lang('type') . ': ' . $type,
        lang('source') . ': ' . $row['source'],
        lang('date') . ': ' . $date,
    ];

    if ($username !== '') {
        $parts[] = lang('user') . ': ' . $username;
    }

    $parts[] = lang('description') . ':\n' . $description;

    return implode("\n", $parts);
}

function get_log_type($type)
{
    switch ($type) {
        case 1:
            return lang('information');
        case 2:
            return lang('warning');
        case 3:
            return lang('error');
        default:
            return (string)$type;
    }
}

function clean_log_description($description)
{
    $description = $description ?: '';
    $description = str_replace('&amp;amp;', '&amp;', $description);
    $description = preg_replace('#<br\s*/?>#i', "\n", $description);
    $description = preg_replace('#</tr>#i', "\n", $description);
    $description = strip_tags($description);
    $description = html_entity_decode($description, ENT_QUOTES, evo()->getConfig('modx_charset', 'UTF-8'));
    $description = str_replace("\xC2\xA0", ' ', $description);
    $description = preg_replace('/^[\h]+/m', '', $description);

    return trim($description);
}
