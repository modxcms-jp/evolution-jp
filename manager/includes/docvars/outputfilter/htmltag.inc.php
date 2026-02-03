<?php
if (!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

$values = $this->parseInput($value, '||', 'array');
unset($value);
$tagid = $params['tagid'] ?? '';
$tagname = ($params['tagname'] ?? '') ?: 'div';
// Loop through a list of tags
$i = 0;
$o = '';
foreach ($values as $value) {
    $tagvalue = is_array($value) ? implode(' ', $value) : $value;
    if (!$tagvalue) continue;

    $tagvalue = $this->parseText($params['tagoutput'] ?? '', ['value' => $tagvalue]);

    $attr['id'] = ($tagid ?: $tagname) . ($i == 0 ? '' : "-{$i}"); // 'tv' already added to id
    $attr['class'] = $params['tagclass'] ?? '';
    $attr['style'] = $params['tagstyle'] ?? '';

    $_ = [];
    foreach ($attr as $k => $v) {
        if ($v) $_[] = "{$k}=\"{$v}\"";
    }
    if ($params['tagattrib'] ?? '') $_[] = $params['tagattrib']; // add extra
    $attributes = implode(' ', $_);
    if ($attributes !== '') $attributes = ' ' . $attributes;

    // Output the HTML Tag
    switch ($tagname) {
        case 'img':
            $o .= "<img src=\"{$tagvalue}\"{$attributes} />\n";
            break;
        default:
            $o .= "<{$tagname}{$attributes}>{$tagvalue}</{$tagname}>\n";
    }
    $i++;
}

return $o;
