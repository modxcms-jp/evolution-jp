<?php

include_once(__DIR__ . '/summary.extender.functions.inc.php');

/*
 * Title: Summary
 * Purpose:
 *  	Legacy support for the [+summary+] placeholder
*/

$summaryExtender = new dittoSummary(
    $trunc ?? 1,
    $truncAt ?? '<!-- splitter -->',
    $truncLen ?? 300,
    $truncOffset ?? 30,
    $truncText ?? 'Read more...',
    isset($tplTrunc) ? $ditto->template->fetch($tplTrunc) : false
);

$placeholders['summary'] = ['introtext,content', [$summaryExtender, 'summary']];
$placeholders['summary_cdata'] = ['introtext,content', [$summaryExtender, 'summaryCdata']];
$placeholders['link'] = ['id,introtext,content', [$summaryExtender, 'link']];
