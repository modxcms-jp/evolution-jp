<?php

include_once(__DIR__ . '/summary.extender.functions.inc.php');

/*
 * Title: Summary
 * Purpose:
 *  	Legacy support for the [+summary+] placeholder
*/

$placeholders['summary'] = ['introtext,content', 'determineSummary', '@GLOBAL ditto_summary_type'];
$placeholders['link'] = ['id', 'determineLink'];

$trunc = isset($trunc) ? $trunc : 1;
$splitter = isset($truncAt) ? $truncAt : '<!-- splitter -->';
$length = isset($truncLen) ? $truncLen : 300;
$offset = isset($truncOffset) ? $truncOffset : 30;
$text = isset($truncText) ? $truncText : 'Read more...';
$trunc_tpl = isset($tplTrunc) ? $ditto->template->fetch($tplTrunc) : false;

$GLOBALS['ditto_summary_link'] = '';
$GLOBALS['ditto_summary_params'] = compact('trunc', 'splitter', 'length', 'offset', 'text', 'trunc_tpl');
$GLOBALS['ditto_object'] = $ditto;
