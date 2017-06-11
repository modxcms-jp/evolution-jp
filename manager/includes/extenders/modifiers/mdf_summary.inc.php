<?php
if(strpos($opt,',')) list($limit,$delim) = explode(',', $opt);
elseif(preg_match('/^[1-9][0-9]*$/',$opt)) {$limit=$opt;$delim='';}
else {$limit=124;$delim='';}

$limit = intval($limit);

$content = $modx->filter->parseDocumentSource($value);

$content = strip_tags($content);
$content = str_replace('&nbsp;', ' ', $content);
$content = str_replace('　', ' ', $content);
$content = str_replace( "\xc2\xa0", " ", $content );
$content = str_replace(array("\r\n","\r","\n","\t",' '),' ',$content);
$content = str_replace(array('。 ','、 ',' ・','。・','…'),array('。','、','・','。','・・'),$content);
$content = preg_replace('@\s+@',' ',$content);

$content_org = $content;

$strlen = $modx->filter->strlen($content);
$limit = $strlen<$limit ? $strlen : $limit;
$p = strpos($content,'。')!==false ? '[。！？]+' : '[\.\!\?\s]+';
if(!preg_match('@('.$p.')@u', $content)) return $modx->filter->substr($content, 0, $limit);

$_ = preg_split('@('.$p.')@u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
$content='';
foreach($_ as $i=>$v) {
    $isToken = preg_match('@('.$p.')@u', $v) ? 1 : 0;
    
    if($modx->filter->strlen($content.$v)<$limit || $isToken) $content .= $v;
    else                                       break;
}

if($limit < $modx->filter->strlen($content)) $content = $modx->filter->substr($content, 0, $limit);

if(trim($content)=='') $content = $modx->filter->substr($content_org, 0, $limit-4).' ...';

return $content;
