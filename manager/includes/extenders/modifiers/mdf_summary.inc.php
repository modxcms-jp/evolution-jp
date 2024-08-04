<?php
if (!preg_match('/^[1-9][0-9]*$/', $opt)) {
    $limit = 124;
} else {
    $limit = $opt;
}

$content = str_replace(
    ['。 ', '、 ', ' ・', '。・', '…'],
    ['。', '、', '・', '。', '・・'],
    str_replace(
        ['&nbsp;', '　', "\xc2\xa0", "\r", "\n", "\t", ' '],
        ' ',
        remove_tags(
            evo()->filter->parseDocumentSource($value)
        )
    )
);
$content = preg_replace('@\s+@', ' ', $content);

$content_org = $content;

$strlen = evo()->filter->strlen($content);
$limit = $strlen < $limit ? $strlen : $limit;
$p = strpos($content, '。') !== false ? '[。！？]+' : '[\.\!\?\s]+';
if (!preg_match('@(' . $p . ')@u', $content)) {
    return evo()->filter->substr($content, 0, $limit);
}

$_ = preg_split('@(' . $p . ')@u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
$content = '';
foreach ($_ as $i => $v) {
    if (evo()->filter->strlen($content . $v) < $limit) {
        $content .= $v;
        continue;
    }
    if (preg_match('@(' . $p . ')@u', $v)) {
        $content .= $v;
        continue;
    }
    break;
}

if ($limit < evo()->filter->strlen($content)) {
    $content = evo()->filter->substr($content, 0, $limit);
}

if (trim($content) == '') {
    $content = evo()->filter->substr($content_org, 0, $limit - 4) . ' ...';
}

return $content;
