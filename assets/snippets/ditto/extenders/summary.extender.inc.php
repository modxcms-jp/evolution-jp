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
/*
Param: trunc

Purpose:
Enable truncation on the summary placeholder

Options:
0 - off
1 - on

Default:
1 - on
*/
$splitter = isset($truncAt) ? $truncAt : '<!-- splitter -->';
/*
Param: truncAt

Purpose:
Location to split the content at

Options:
Any unique text or code string that is contained
in the content of each document

Default:
'<!-- splitter -->'
*/
$length = isset($truncLen) ? $truncLen : 300;
/*
Param: truncLen

Purpose:
Number of characters to show of the content

Options:
Any number greater than <truncOffset>

Default:
300
*/
$offset = isset($truncOffset) ? $truncOffset : 30;
/*
Param: truncOffset

Purpose:
Number of charactars to 'wander' either way of <truncLen>

Options:
Any number greater less than <truncLen>

Default:
30
*/
$text = isset($truncText) ? $truncText : 'Read more...';
/*
Param: truncText

Purpose:
Text to be displayed in [+link+]

Options:
Any valid text or html

Default:
'Read more...'
*/
$trunc_tpl = isset($tplTrunc) ? template::fetch($tplTrunc) : false;
/*
Param: tplTrunc

Purpose:
Template to be used for [+link+]

Options:
- Any valid chunk name
- Code via @CODE:
- File via @FILE:

Placeholders:
[+url+] - URL of the document
[+text+] - &truncText

Default:
&truncText
*/

$GLOBALS['ditto_summary_link'] = '';
$GLOBALS['ditto_summary_params'] = compact('trunc', 'splitter', 'length', 'offset', 'text', 'trunc_tpl');
$GLOBALS['ditto_object'] = $ditto;
