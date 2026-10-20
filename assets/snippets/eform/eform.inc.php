<?php
# eForm 1.4.4.8 - Electronic Form Snippet
# Original created by: Raymond Irving 15-Dec-2004.
# Extended by: Jelle Jager (TobyL) September 2006
# -----------------------------------------------------
#
#
# Captcha image support - thanks to Djamoer
# Multi checkbox, radio, select support - thanks to Djamoer
# Form Parser and extended validation - by Jelle Jager
#
# see docs/eform.htm for installation and usage information
#
# VERSION HISTORY
# Work around for setting required class on check & radio labels
# fixed bug: If eform attibute is set on multiple check boxes only the last
#            value is set in values list
# For a full version history see the eform_history.htm file in the docs directory
#
# Some more fixes and problems:
# FIXED: reg expression failed for select and textarea boxes which have regex special
# characters in their name attribute. eg name="multipleSelection[]"
# FIXED: validation of multiple values with #LIST & #SELECT stopped after 1st value
# Caused by repeating $v variable naming (overwriting $v array)
# e.g.
# <select name="multipleSelection[]" multiple="multiple" eform="::1::"/>
#   <option value="1">1</option>
#   <option value="2">2</option>
#   <option value="3">3</option>
# </select>
# would only have the first selected value validated!
#
# bugfix: &jScript parameter doesn't accept chunks, only a link to a JS file if more than one chunk is declared (eg &jScript=`chunk1,chunk2)
# bugfix: &protectSubmit creates hash for all fields instead of fields declared in &protectSubmit
# bugfix: Auto respond email didn't honour the &sendAsText parameter
# bugfix: The #FUNCTION validation rule for select boxes never calls the function
# bugfix: Validation css class isn't being added to labels.
#
# SECURITY FIX: add additional sanitization to fields after stripping slashes to avoid remote tag execution
##

#----------------------------------------------------------
# Modify ZeRo(http://www.petit-power.com) $Revision: 58 $
# $Date: 2009-07-27 16:31:59 +0900 (??, 01 3 2009) $
#----------------------------------------------------------

$GLOBALS['optionsName'] = 'eform'; //name of pseudo attribute used for format settings
$GLOBALS['efPostBack'] = false;

//tidying up some casing errors in parameters
if (isset($eformOnValidate)) $eFormOnValidate = $eformOnValidate;
if (isset($eformOnBeforeMailSent)) $eFormOnBeforeMailSent = $eformOnBeforeMailSent;
if (isset($eformOnMailSent)) $eFormOnMailSent = $eformOnMailSent;
if (isset($eformOnValidate)) $eFormOnValidate = $eformOnValidate;
if (isset($eformOnBeforeFormMerge)) $eFormOnBeforeFormMerge = $eformOnBeforeFormMerge;
if (isset($eformOnBeforeFormParse)) $eFormOnBeforeFormParse = $eformOnBeforeFormParse;
//for sottwell :)
if (isset($eFormCSS)) $cssStyle = $eFormCSS;

# Snippet customize settings
$from = isset($from) ? $from : config('emailsender');
$formid = (isset($formid)) ? $formid : 'eform';
$vericode = (isset($vericode)) ? $vericode : '';
$params = [
    //                       Snippet Path
    'snipPath' => $snipPath,//includes $snip_dir
    'snipFolder' => $snip_dir,

    //                       eForm   Params
    'vericode' => $vericode,
    'formid' => $formid,
    'from' => $from,
    'fromname' => isset($fromname) ? $fromname : config('site_name'),
    'to' => isset($to) ? $to : config('emailsender'),
    'cc' => isset($cc) ? $cc : '',
    'bcc' => isset($bcc) ? $bcc : '',
    'subject' => isset($subject) ? $subject : '',
    'ccsender' => isset($ccsender) ? $ccsender : 0,
    'sendirect' => isset($sendirect) ? $sendirect : 0,
    'mselector' => isset($mailselector) ? $mailselector : 0,
    'mobile' => isset($mobile) ? $mobile : '',
    'mobiletext' => isset($mobiletext) ? $mobiletext : '',
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
    //Added                  by      JJ
    'replyto' => isset($replyto) ? $replyto : '',
    'language' => isset($language) ? $language : config('manager_language', 'english'),
    'thankyou' => isset($thankyou) ? $thankyou : '',
    'isDebug' => isset($debug) ? $debug : 0,
    'reportAbuse' => isset($reportAbuse) ? $reportAbuse : false,
    'disclaimer' => isset($disclaimer) ? $disclaimer : '',
    'sendAsHtml' => isset($sendAsHtml) ? $sendAsHtml : false,
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
    'version' => isset($version) ? $version : '1.4.4.8'
];

// pixelchutes PHx workaround
foreach ($params as $key => $val) {
    $params[$key] = str_replace(['((', '))'], ['[+', '+]'], $val);
}


// 既定値の組み立て（上）は呼ぶたびに必要なので、このファイルは include で読む。
// 関数の定義は二重に宣言しないよう、別ファイルを一度だけ読む
require_once __DIR__ . '/eform.functions.inc.php';
