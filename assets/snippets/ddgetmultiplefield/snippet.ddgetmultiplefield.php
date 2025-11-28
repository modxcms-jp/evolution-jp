<?php
if (!defined('MODX_BASE_PATH')) {
    die('What are you doing? Get out of here!');
}

/**
 * ddGetMultipleField
 *
 * A helper snippet for parsing ddMultipleFields TV values.
 * Mirrors the public parameter interface of the upstream implementation
 * to stay compatible with existing calls.
 *
 * @version 1.0.0
 */

$docId = isset($docId) && $docId !== '' ? (int) $docId : (int) evo()->documentIdentifier;
$tv = $tv ?? '';
$inputString = $inputString ?? '';
$columns = $columns ?? '';
$api = $api ?? '';
$owner = $owner ?? '';
$ownerId = isset($ownerId) ? (int) $ownerId : 0;
$tpl = $tpl ?? '';
$rowTpl = $rowTpl ?? '';
$outerTpl = $outerTpl ?? '';
$rowSeparator = $rowSeparator ?? '';
$colSeparator = $colSeparator ?? '::';
$rowDelimiter = $rowDelimiter ?? '||';
$offset = isset($offset) ? (int) $offset : 0;
$display = isset($display) ? $display : '';
$sortBy = $sortBy ?? '';
$sortDir = strtolower($sortDir ?? '');
$where = $where ?? '';
$toPlaceholder = $toPlaceholder ?? '';
$idxPlaceholder = $idxPlaceholder ?? 'idx';

$sortDir = $sortDir === 'desc' ? 'desc' : 'asc';

$displayLower = strtolower((string) $display);
$displayAll = $displayLower === '' || $displayLower === 'all';
$display = $displayAll ? 0 : (int) $display;

if ($tv === '' && $inputString === '') {
    return '';
}

if ($inputString === '') {
    $tvData = evo()->getTemplateVar($tv, '*', $docId);
    $inputString = $tvData['value'] ?? '';
}

$inputString = trim((string) $inputString);
if ($inputString === '') {
    return '';
}

$rowsRaw = explode($rowDelimiter, $inputString);
$rows = [];

if ($columns !== '') {
    $columns = array_values(array_filter(array_map('trim', explode(',', $columns)), 'strlen'));
}

$conditions = [];
if ($where !== '') {
    $decoded = json_decode($where, true);
    if (is_array($decoded)) {
        $conditions = $decoded;
    }
}

foreach ($rowsRaw as $rowIndex => $row) {
    $values = explode($colSeparator, $row);
    if (!count(array_filter($values, 'strlen'))) {
        continue;
    }

    if (!$columns) {
        $columns = [];
        foreach ($values as $index => $value) {
            $columns[] = "col" . ($index + 1);
        }
    }

    $item = [];
    foreach ($values as $index => $value) {
        $key = $columns[$index] ?? "col" . ($index + 1);
        $item[$key] = trim($value);
    }

    if ($conditions) {
        $matched = true;
        foreach ($conditions as $key => $expected) {
            if (($item[$key] ?? null) != $expected) {
                $matched = false;
                break;
            }
        }
        if (!$matched) {
            continue;
        }
    }

    $item[$idxPlaceholder] = $rowIndex + 1;
    $rows[] = $item;
}

if ($sortBy !== '') {
    usort($rows, static function (array $left, array $right) use ($sortBy, $sortDir) {
        $leftValue = $left[$sortBy] ?? '';
        $rightValue = $right[$sortBy] ?? '';
        if ($leftValue == $rightValue) {
            return 0;
        }
        if ($sortDir === 'desc') {
            return $leftValue < $rightValue ? 1 : -1;
        }
        return $leftValue > $rightValue ? 1 : -1;
    });
}

if ($offset > 0) {
    $rows = array_slice($rows, $offset);
}

if (!$displayAll && $display > 0) {
    $rows = array_slice($rows, 0, $display);
}

function ddgmfGetTpl(string $template): string
{
    if ($template === '') {
        return '';
    }
    if (strpos($template, '@CODE:') === 0) {
        return substr($template, 6);
    }
    return evo()->getChunk($template) ?: '';
}

$tpl = ddgmfGetTpl($tpl);
$rowTpl = ddgmfGetTpl($rowTpl ?: $tpl);
$outerTpl = ddgmfGetTpl($outerTpl);

$outputRows = [];
foreach ($rows as $row) {
    $outputRows[] = evo()->parseText($rowTpl, $row);
}

$result = implode($rowSeparator, $outputRows);

if ($outerTpl !== '') {
    $result = evo()->parseText($outerTpl, ['result' => $result]);
}

if ($owner !== '') {
    $ownerPlaceholders = [
        'result' => $result,
        'owner' => $owner,
        'ownerId' => $ownerId,
        'api' => $api,
    ];
    $result = evo()->parseText($outerTpl ?: $rowTpl, $ownerPlaceholders);
}

if ($toPlaceholder !== '') {
    evo()->setPlaceholder($toPlaceholder, $result);
    return '';
}

return $result;
