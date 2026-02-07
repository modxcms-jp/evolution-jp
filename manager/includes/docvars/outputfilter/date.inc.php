<?php
if (!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

if ($value === '' && ($params['default'] ?? '') !== 'Yes') {
    return '';
}

$p = ($params['dateformat'] ?? '') ?: $this->toDateFormat(null, 'formatOnly');
$timestamp = $this->getUnixtimeFromDateString($value);

if (strpos($p, '%') !== false) {
    return evo()->mb_strftime($p, $timestamp);
}

return date($p, $timestamp);
