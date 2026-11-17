<?php
# eForm - Electronic Form Snippet
# Original created by: Raymond Irving 15-Dec-2004.
# Extended by: Jelle Jager (TobyL) September 2006

$GLOBALS['optionsName'] = 'eform'; // 書式を書く疑似属性の名前
$GLOBALS['efPostBack'] = false;

// 引数名の大文字小文字の揺れをそろえる
if (isset($eformOnValidate)) $eFormOnValidate = $eformOnValidate;
if (isset($eformOnBeforeMailSent)) $eFormOnBeforeMailSent = $eformOnBeforeMailSent;
if (isset($eformOnMailSent)) $eFormOnMailSent = $eformOnMailSent;
if (isset($eformOnBeforeFormMerge)) $eFormOnBeforeFormMerge = $eformOnBeforeFormMerge;
if (isset($eformOnBeforeFormParse)) $eFormOnBeforeFormParse = $eformOnBeforeFormParse;
if (isset($eFormCSS)) $cssStyle = $eFormCSS;

$from = isset($from) ? $from : config('emailsender');
$formid = (isset($formid)) ? $formid : 'eform';
$vericode = (isset($vericode)) ? $vericode : '';
# 廃止した引数の名前を集める（警告は eForm() が送信のときに残す）
$removedParams = [];
foreach (['mobile', 'mobiletext', 'sendAsHtml', 'reportAbuse', 'sendirect'] as $removedName) {
    if (isset($$removedName)) {
        $removedParams[] = $removedName;
    }
}

$params = [
    'removedParams' => $removedParams,
    'snipPath' => $snipPath,//includes $snip_dir
    'snipFolder' => $snip_dir,

    'vericode' => $vericode,
    'formid' => $formid,
    'from' => $from,
    'fromname' => isset($fromname) ? $fromname : config('site_name'),
    'to' => isset($to) ? $to : config('emailsender'),
    'cc' => isset($cc) ? $cc : '',
    'bcc' => isset($bcc) ? $bcc : '',
    'subject' => isset($subject) ? $subject : '',
    'ccsender' => isset($ccsender) ? $ccsender : 0,
    'mselector' => isset($mailselector) ? $mailselector : 0,
    'autosender' => isset($autosender) ? $autosender : $from,
    'autotext' => isset($automessage) ? $automessage : '',
    'category' => isset($category) ? $category : 0,
    'keywords' => isset($keywords) ? $keywords : '',
    'gid' => isset($gotoid) ? $gotoid : $modx->documentIdentifier,
    'noemail' => isset($noemail) ? ($noemail) : false,
    'saveform' => isset($saveform) ? ($saveform ? true : false) : true,
    'tpl' => isset($tpl) ? $tpl : '',
    'report' => isset($report) ? $report : '',
    'allowhtml' => isset($allowhtml) ? $allowhtml : 0,
    'replyto' => isset($replyto) ? $replyto : '',
    'language' => isset($language) ? $language : config('manager_language', 'english'),
    'thankyou' => isset($thankyou) ? $thankyou : '',
    'isDebug' => isset($debug) ? $debug : 0,
    'disclaimer' => isset($disclaimer) ? $disclaimer : '',
    'sendAsText' => isset($sendAsText) ? $sendAsText : false,
    'sessionVars' => isset($sessionVars) ? $sessionVars : false,
    'postOverides' => isset($postOverides) ? $postOverides : 0,
    'eFormOnBeforeMailSent' => isset($eFormOnBeforeMailSent) ? $eFormOnBeforeMailSent : '',
    'eFormOnMailSent' => isset($eFormOnMailSent) ? $eFormOnMailSent : '',
    'eFormOnValidate' => isset($eFormOnValidate) ? $eFormOnValidate : '',
    'eFormOnBeforeFormMerge' => isset($eFormOnBeforeFormMerge) ? $eFormOnBeforeFormMerge : '',
    'eFormOnBeforeFormParse' => isset($eFormOnBeforeFormParse) ? $eFormOnBeforeFormParse : '',
    'cssStyle' => isset($cssStyle) ? $cssStyle : '',
    'jScript' => isset($jScript) ? $jScript : '',
    'submitLimit' => (isset($submitLimit) && is_numeric($submitLimit)) ? $submitLimit * 60 : 0,
    'protectSubmit' => isset($protectSubmit) ? $protectSubmit : 1,
    'requiredClass' => isset($requiredClass) ? $requiredClass : "required",
    'invalidClass' => isset($invalidClass) ? $invalidClass : "invalid",
    'runSnippet' => (isset($runSnippet) && !is_numeric($runSnippet)) ? $runSnippet : '',
    'autoSenderName' => isset($autoSenderName) ? $autoSenderName : '',
    'version' => isset($version) ? $version : ''
];

// PHx 対応: (( )) を [+ +] に直す
foreach ($params as $key => $val) {
    $params[$key] = str_replace(['((', '))'], ['[+', '+]'], $val);
}


// 既定値の組み立て（上）は呼ぶたびに必要なので、このファイルは include で読む。
// 関数の定義は二重に宣言しないよう、別ファイルを一度だけ読む
require_once __DIR__ . '/eform.functions.inc.php';
