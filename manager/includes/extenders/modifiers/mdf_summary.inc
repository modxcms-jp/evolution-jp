<?php
if(strpos($opt,',')) list($limit,$delim) = explode(',', $opt);
elseif(preg_match('/^[1-9][0-9]*$/',$opt)) {$limit=$opt;$delim='';}
else {$limit=100;$delim='';}

if($delim==='') $delim = $modx->config['manager_language']==='japanese-utf8' ? '。' : '.';
$limit = intval($limit);

if($value==='' && isset($modx->documentObject['content']))
    $value = $modx->documentObject['content'];

$value = $modx->filter->parseDocumentSource($value);

$value = strip_tags($value);

$value = str_replace(array("\r\n","\r","\n","\t",'&nbsp;'),' ',$value);
if(preg_match('/\s+/',$value))
    $value = preg_replace('/\s+/',' ',$value);
$value = trim($value);

$pos = $modx->filter->strpos($value, $delim);

if($pos!==false && $pos<$limit) {
    $_ = explode($delim, $value);
    $text = '';
    foreach($_ as $value) {
        if($limit <= $modx->filter->strlen($text.$value.$delim)) break;
        $text .= $value.$delim;
    }
    $value = $text;
}

if($limit<$modx->filter->strlen($value) && strpos($value,' ')!==false) {
    $_ = explode(' ', $value);
    $text = '';
    foreach($_ as $value) {
        if($limit <= $modx->filter->strlen($text.$value.' ')) break;
        $text .= $value . ' ';
    }
    if($text!=='') $value = $text;
}

if($limit < $modx->filter->strlen($value)) $value = $modx->filter->substr($value, 0, $limit);

return $value;
