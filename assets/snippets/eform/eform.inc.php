<?php
# eForm 1.4.4.7 - Electronic Form Snippet
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
$from = (isset($from)) ? $from : $modx->config['emailsender'];
$formid = (isset($formid)) ? $formid : 'eform';
$vericode = (isset($vericode)) ? $vericode : '';
$params = array(
    //                       Snippet Path
    'snipPath' => $snipPath,//includes $snip_dir
    'snipFolder' => $snip_dir,

    //                       eForm   Params
    'vericode' => $vericode,
    'formid' => $formid,
    'from' => $from,
    'fromname' => isset($fromname) ? $fromname : $modx->config['site_name'],
    'to' => isset($to) ? $to : $modx->config['emailsender'],
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
    'language' => isset($language) ? $language : $modx->config['manager_language'],
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
    'version' => isset($version) ? $version : '1.4.4.7'
);

// pixelchutes PHx workaround
foreach ($params as $key => $val) {
    $params[$key] = str_replace(array('((', '))'), array('[+', '+]'), $val);
}

function eForm($modx, $params)
{
    global $_lang;
    global $debugText;
    global $formats, $fields, $efPostBack;

    $fields = array(); //reset fields array - needed in case of multiple forms

// define some variables used as array index
    $_dfnMaxlength = 6;

    extract($params, EXTR_SKIP); // extract params into variables

    #include default language file
    include_once($snipPath . "lang/english.inc.php");

    #include other language file if set.
    $form_language = isset($language) ? $language : $modx->config['manager_language'];
    if ($form_language != "english" && $form_language != '') {
        if (file_exists("{$snipPath}lang/{$form_language}.inc.php")) {
            include_once("{$snipPath}lang/{$form_language}.inc.php");
        } else {
            if ($isDebug) $debugText .= "<strong>Language file '{$form_language}.inc.php' not found!</strong><br />";
        } //always in english!
    }

    # add debug warning - moved again...
    if ($isDebug) {
        $debugText .= $_lang['ef_debug_warning'];
    }

    # check for valid form key - moved to below fetching form template to allow id coming from form template

    $nomail = $noemail; //adjust variable name confusion
    # activate nomail if missing $to
    if (!$to) {
        $nomail = 1;
    }


    # load templates
    if ($tpl == $modx->documentIdentifier) {
        return $_lang['ef_is_own_id'] . "'$tpl'";
    }

    //required
    if (empty($tpl)) {
        $tpl = get_default_tpl();
    } elseif ($tmp = efLoadTemplate($tpl)) $tpl = $tmp;
    else return $_lang['ef_no_doc'] . " '$tpl'";

    // try to get formid from <form> tag id
    if (preg_match('/<form[^>]*?id=[\'"]([^\'"]*?)[\'"]/i', $tpl, $matches)) {
        $formid = $matches[1];
    } elseif (!preg_match('/<input[^>]*?name=[\'"]formid[\'"][^>]*?>/i', $tpl, $matches)) {
    } else {
        $_ = $matches[0];
        if (preg_match('@value=[\'"]([^\'"]*?)[\'"]@', $_, $matches)) {
            $formid = $matches[1];
        }
    }
    //check for <input type='hidden name='formid'...>
    if (!preg_match('/<input[^>]*?name=[\'"]formid[\'"]/i', $tpl)) {
        //insert hidden formid field
        $tpl = str_replace('</form>', sprintf(
            '<input type="hidden" name="formid" value="%s" /></form>'
            , $formid
        ), $tpl);
    }

    $validFormId = (isset($_POST['formid']) && $formid == $_POST['formid']) ? 1 : 0;

    # check if postback mode
    $efPostBack = ($validFormId && $_POST && (strpos($_SERVER["HTTP_REFERER"], $modx->config["site_url"]) !== FALSE)) ? true : false; //retain old variable?


    if ($efPostBack) {
        if ($report) {
            $report = (($tmp = efLoadTemplate($report)) !== false) ? $tmp : $_lang['ef_no_doc'] . " '$report'";
        } else {
            $report = get_default_report();
        }
        if ($thankyou) {
            $thankyou = (($tmp = efLoadTemplate($thankyou)) !== false) ? $tmp : $_lang['ef_no_doc'] . " '$thankyou'";
        }
        if ($autotext) {
            $autotext = (($tmp = efLoadTemplate($autotext)) !== false) ? $tmp : $_lang['ef_no_doc'] . " '$autotext'";
        }
    }

    //these will be added to the HEAD section of the document when the form is displayed!
    if ($cssStyle) {
        $cssStyle = (strpos($cssStyle, ',') && strpos($cssStyle, '<style') === false) ? explode(',', $cssStyle) : array($cssStyle);
        foreach ($cssStyle as $tmp) {
            $startupSource[] = array($tmp, 'css');
        }
    }
    if ($jScript) {
        $jScript = (strpos($jScript, ',') && strpos($jScript, '<script') === false) ? explode(',', $jScript) : array($jScript);
        foreach ($jScript as $tmp) {
            $startupSource[] = array($tmp, 'javascript');
        }
    }

    #New in 1.4.4 - run snippet to include 'event' functions
    if (strlen($runSnippet) > 0) {
        $modx->runSnippet($runSnippet, array('formid' => $formid));
        //Sadly we cannot know if the snippet fails or if it exists as modx->runsnippet's return value
        //is ambiguous
    }

    # invoke onBeforeFormParse event set by another script
    if ($eFormOnBeforeFormParse) {
        if ($isDebug && !function_exists($eFormOnBeforeFormParse)) {
            $fields['debug'] .= "eFormOnBeforeFormParse event: Could not find the function " . $eFormOnBeforeFormParse;
        } else {
            $templates = array('tpl' => $tpl, 'report' => $report, 'thankyou' => $thankyou, 'autotext' => $autotext);
            if ($eFormOnBeforeFormParse($fields, $templates) === false) {
                return "";
            } elseif (is_array($templates))
                extract($templates); // extract back into original variables
        }
    }

# parse form for formats and generate placeholders
    $tpl = eFormParseTemplate($tpl, $isDebug);

    if ($efPostBack) {
        foreach ($formats as $k => $discard) {
            if (!isset($fields[$k])) {
                $fields[$k] = "";
            }
        } // store dummy value inside $fields

        $disclaimer = (($tmp = efLoadTemplate($disclaimer)) !== false) ? $tmp : '';

        //error message containers
        $vMsg = $rMsg = $rClass = array();

        # get user post back data
        foreach ($_POST as $name => $value) {
            if (is_array($value)) {
                //remove empty values
                $fields[$name] = array_filter($value, function ($v) {
                    return $v ? true : false;
                });
            } else {
                if (!$allowhtml || $formats[$name][2] != 'html') {
                    $value = strip_tags($value);
                } else {
                    $value = $modx->htmlspecialchars($value);
                }
                $fields[$name] = $value;
            }
        }

        # sanitize the values with slashes stripped to avoid remote execution of Snippets
        $modx->sanitize_gpc($fields);

        # get uploaded files
        foreach ($_FILES as $name => $value) {
            $fields[$name] = $value;
        }

        # check vericode
        if ($vericode) {
            //add support for captcha code - thanks to Djamoer
            $code = $_SESSION['veriword'] ? $_SESSION['veriword'] : $_SESSION['eForm.VeriCode'];
            if ($fields['vericode'] != $code) {
                $vMsg[count($vMsg)] = $_lang['ef_failed_vericode'];
                $rClass['vericode'] = $invalidClass; //added in 1.4.4
            }
        }

        # validate fields
        foreach ($fields as $name => $value) {
            $fld = isset($formats[$name]) ? $formats[$name] : '';
            if ($fld) {
                $desc = $fld[1];
                $datatype = $fld[2];
                $isRequired = $fld[3];

                if ($isRequired == 1 && $value == "" && $datatype != "file") {
                    $rMsg[count($rMsg)] = (string)$desc;
                    $rClass[$name] = $requiredClass;

                } elseif (!empty($fld[5]) && $value != "" && $datatype != "file") {
                    $value = validateField($value, $fld, $vMsg, $isDebug);

                    if ($value === false) {
                        $rClass[$name] = $invalidClass;
                    } elseif ($value !== true) {
                        //if returned value is not of type boolean replace value...
                        $fields[$name] = $value;
                    } //replace value.

                } else { //value check
                    switch ($datatype) {
                        case "integer":
                        case "float":
                            if (strlen($value) > 0 && !is_numeric($value)) {
                                $vMsg[count($vMsg)] = $desc . $_lang["ef_invalid_number"];
                                $rClass[$name] = $invalidClass;
                            }
                            break;
                        case "date":
                            if (strlen($value) == 0) {
                                break;
                            }
                            //corrected by xwisdom for php version differences
                            $rt = strtotime($value); //php 5.1.0+ returns false while < 5.1.0 returns -1
                            if ($rt === false || $rt === -1) {
                                $vMsg[count($vMsg)] = $desc . $_lang["ef_invalid_date"];
                                $rClass[$name] = $invalidClass;
                            }
                            break;
                        case "email":
                            //stricter email validation - udated to allow + in local name part
                            if (strlen($value) > 0 && !preg_match(
                                    '/^(?:[a-z0-9+_-]+?\.)*?[a-z0-9_+-]+?@(?:[a-z0-9_-]+?\.)*?[a-z0-9_-]+?\.[a-z0-9]{2,5}$/i', $value)) {
                                $vMsg[count($vMsg)] = isset($formats[$name][4]) ? $formats[$name][4] : $desc . $_lang["ef_invalid_email"];
                                $rClass[$name] = $invalidClass;
                            }
                            break;
                        case "file":
                            if ($_FILES[$name]['error'] == 1 || $_FILES[$name]['error'] == 2) {
                                $vMsg[count($vMsg)] = $desc . $_lang['ef_upload_exceeded'];
                                $rClass[$name] = $invalidClass;
                            } elseif ($isRequired == 1 && ($_FILES[$name] && $_FILES[$name]['type'] == '')) {
                                $rMsg[count($rMsg)] = $desc;
                                $rClass[$name] = $requiredClass;
                            } elseif ($_FILES[$name]['tmp_name']) {
                                if (strpos($fld[5], '#LIST') !== 0 || validateField($_FILES[$name]['name'], $fld, $vMsg, $isDebug)) {
                                    if(!isset($attachments)) {
                                        $attachments = [];
                                    }
                                    $attachments[count($attachments)] = $_FILES[$name]['tmp_name'];
                                } else $rClass[$name] = $invalidClass;
                            }
                            break;
                        case "html":
                        case "checkbox":
                        case "string":
                        default:
                            break;
                    }
                }//end required test
            }
        }

// Changed in 1.4.4.5  - now expects 4 parameters
        if ($eFormOnValidate) {
            if ($isDebug && !function_exists($eFormOnValidate)) {
                $fields['debug'] .= "eformOnValidate event: Could not find the function " . $eFormOnValidate;
            } else {
                if ($eFormOnValidate($fields, $vMsg, $rMsg, $rClass) === false) {
                    return '';
                }
            }
        }


        if ($vMsg || $rMsg) {
            //New in 1.4.2 - classes are set in labels and form elements for invalid fields
            foreach ($rClass as $n => $class) {
                $fields[$n . '_class'] = $fields[$n . '_class'] ? $fields[$n . '_class'] . ' ' . $class : $class;
                $fields[$n . '_vClass'] = isset($fields[$n . '_vClass']) ? $fields[$n . '_vClass'] . ' ' . $class : $class;
                //work around for checkboxes
                if (isset($formats[$n][6])) { //have separate id's for check and option tags - set classes as well
                    foreach (explode(',', $formats[$n][6]) as $id) {
                        $fields[$id . '_vClass'] = $fields[$id . '_vClass'] ? $fields[$id . '_vClass'] . ' ' . $class : $class;
                    }
                }
            }

            //add debugging info to fields array
            if ($isDebug) {
                ksort($fields);
                if ($isDebug > 1) {
                    $debugText .= '<br /><strong>Formats array:</strong><pre>' . var_export($formats, true) . '</pre>';
                    $debugText .= '<br /><strong>Fields array:</strong><pre>' . var_export($fields, true) . '</pre>';
                    $debugText .= '<br /><strong>Classes parsed :</strong><pre>' . var_export($rClass, true) . "</pre>";
                }
                $debugText .= "<br /><strong>eForm configuration:</strong><pre>\n" . var_export($params, true) . '</pre>';
                $fields['debug'] = $debugText;
            }

            #set validation message
            $tmp = ($rMsg) ? str_replace("{fields}", implode(", ", $rMsg), $_lang['ef_required_message']) : "";
            $tmp .= implode("<br />", $vMsg);
            if (strpos($tpl, '[+validationmessage+]') === false) {
                $modx->setPlaceholder('validationmessage', str_replace('[+ef_wrapper+]', $tmp, $_lang['ef_validation_message']));
            } else {
                if (!isset($fields['validationmessage'])) $fields['validationmessage'] = '';
                $fields['validationmessage'] .= str_replace('[+ef_wrapper+]', $tmp, $_lang['ef_validation_message']);
            }
        } else {

            # format report fields
            foreach ($fields as $name => $value) {
                $fld = $formats[$name];
                if ($fld) {
                    $datatype = $fld[2];
                    switch ($datatype) {
                        case "integer":
                            $value = number_format((float)$value);    //EM~
                            break;
                        case "float":
                            $localeInfo = localeconv();
                            $th_sep = empty($_lang['ef_thousands_separator']) ? $localeInfo['thousands_sep'] : $_lang['ef_thousands_separator'];
                            $dec_point = $localeInfo['decimal_point'];
                            $debugText .= 'Locale<pre>' . var_export($localeInfo, true) . '</pre>';
                            $value = number_format((float)$value, 2, $dec_point, $th_sep);    //EM~
                            break;
                        case "date":
                            $format_string = isset($_lang['ef_date_format']) ? $_lang['ef_date_format'] : '%d-%b-%Y %H:%M:%S';
                            $value = ($value) ? strftime($format_string, strtotime($value)) : "";
                            $value = str_replace("00:00:00", "", $value);// remove trailing zero time values
                            break;
                        case "html":
                            // convert \n to <br />
                            if (!$sendAsText) {
                                $value = preg_replace('#(\n<br[ /]*?>|<br[ /]*?>\n|\n)#i', '<br />', $value);
                            }
                            break;
                        case "file":
                            // set file name
                            if ($value['type'] != "" && $value['error'] == 0) {
                                $value = $value["name"];
                                $patharray = explode(((strpos($value, "/") === false) ? "\\" : "/"), $value);
                                $value = $patharray[count($patharray) - 1];
                            } else {
                                $value = "";
                            }
                            break;
                    }
                    $fields[$name] = $value;
                }
                if (is_array($value)) {
                    $fields[$name] = join(', ', $value);
                }
            }
            # set postdate
            $fields['postdate'] = strftime($modx->toDateFormat(null, 'formatOnly') . " %H:%M:%S", time());

            //check against email injection and replace suspect content
            if (hasMailHeaders($fields)) {
                //send email to webmaster??
                if ($reportAbuse) { //set in snippet configuration tab
                    $body = $_lang['ef_mail_abuse_message'];
                    $body .= "<table>";
                    foreach ($fields as $key => $value) {
                        $body .= "<tr><td>$key</td><td><pre>$value</pre></td></tr>";
                    }
                    $body .= "</table>";
                    evo()->loadExtension('MODxMailer');
                    # send abuse alert
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = $modx->config['emailsender'];
                    $modx->mail->FromName = $modx->config['site_name'];
                    $modx->mail->Subject = $_lang['ef_mail_abuse_subject'];
                    $modx->mail->Body = $body;
                    AddAddressToMailer($modx->mail, "to", $modx->config['emailsender']);
                    $modx->mail->send(); //ignore mail errors in this case
                }
                //return empty form with error message
                //register css and/or javascript
                if (isset($startupSource)) {
                    efRegisterStartupBlock($startupSource);
                }
                return formMerge($tpl, array('validationmessage' => $_lang['ef_mail_abuse_error']));
            }

            # added in 1.4.2 - Limit the time between form submissions
            if ($submitLimit > 0) {
                if (time() < $submitLimit + $_SESSION[$formid . '_limit']) {
                    return formMerge($_lang['ef_submit_time_limit'], $fields);
                } else unset($_SESSION[$formid . '_limit'], $_SESSION[$formid . '_hash']); //time expired
            }

            # invoke OnBeforeMailSent event set by another script
            if ($eFormOnBeforeMailSent) {
                if ($isDebug && !function_exists($eFormOnBeforeMailSent)) {
                    $fields['debug'] .= "eFormOnBeforeMailSent event: Could not find the function " . $eFormOnBeforeMailSent;
                } elseif ($eFormOnBeforeMailSent($fields) === false) {
                    if (isset($fields['validationmessage']) && !empty($fields['validationmessage'])) {
                        //register css and/or javascript
                        if (isset($startupSource)) {
                            efRegisterStartupBlock($startupSource);
                        }
                        return formMerge($tpl, $fields);
                    }
                    return '';
                }
            }

            if ($protectSubmit) {
                $hash = '';
                # create a hash of key data
                if (!is_numeric($protectSubmit)) { //supplied field names
                    $protectSubmit = (strpos($protectSubmit, ',')) ? explode(',', $protectSubmit) : array($protectSubmit);
                    foreach ($protectSubmit as $fld) {
                        $hash .= isset($fields[$fld]) ? $fields[$fld] : '';
                    }
                } else {
                    //all required fields
                    foreach ($formats as $fld) {
                        $hash .= ($fld[3] == 1) ? $fields[$fld[0]] : '';
                    }
                }
                if ($hash) {
                    $hash = md5($hash);
                }

                if ($isDebug) {
                    $debugText .= "<strong>SESSION HASH</strong>:" . $_SESSION[$formid . '_hash'] . "<br />" . "<b>FORM HASH</b>:" . $hash . "<br />";
                }

                # check if already succesfully submitted with same data
                if (isset($_SESSION[$formid . '_hash']) && $_SESSION[$formid . '_hash'] == $hash && $hash != '') {
                    return formMerge($_lang['ef_multiple_submit'], $fields);
                }
            }

            $fields['disclaimer'] = ($disclaimer) ? formMerge($disclaimer, $fields) : "";
            $from = ($from) ? formMerge($from, $fields) : "";
            $fromname = ($from) ? formMerge($fromname, $fields) : "";
            if (isset($fields['subject'])) {
                $subject = $fields['subject'];
            } else {
                $subject = ($subject) ? formMerge($subject, $fields) : "from {$fromname}";
            }
            $fields['subject'] = $subject; //make subject available in report & thank you page
            if ($report) {
                $report = $modx->mergeConditionalTagsContent($report);
                $report = $modx->parseText($report, $fields);
                $report = formMerge($report, $fields);
            } else $report = '';

            $keywords = ($keywords) ? formMerge($keywords, $fields) : "";

            $to = formMerge($to, $fields);
            if (empty($to) || !strpos($to, '@')) {
                $nomail = 1;
            }

            if (!$nomail) {
                # check for mail selector field - select an email from to list
                if ($mselector && $fields[$mselector]) {
                    $i = (int)$fields[$mselector];
                    $ar = explode(",", $to);
                    if ($i > 0) {
                        $i--;
                    }
                    if ($ar[$i]) {
                        $to = $ar[$i];
                    } else {
                        $to = $ar[0];
                    }
                }

                //set reply-to address
                //$replyto snippet parameter must contain email or fieldname
                if (strpos($replyto, '@') === false) {
                    $replyto = ($fields[$replyto] && strpos($fields[$replyto], '@') !== false) ? $fields[$replyto] : $from;
                }

                # include PHP Mailer
                evo()->loadExtension('MODxMailer');

                # send form
                //defaults to html so only test sendasText
                $isHtml = !($sendAsText == 1 || strpos($sendAsText, 'report') !== false);

                if (!$noemail) {
                    if ($sendirect) {
                        $to = $fields['email'];
                    }
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = $from;
                    $modx->mail->FromName = $fromname;
                    $modx->mail->Subject = $subject;
                    $modx->mail->Body = $report;
                    AddAddressToMailer($modx->mail, "replyto", $replyto);
                    AddAddressToMailer($modx->mail, "to", $to);
                    AddAddressToMailer($modx->mail, "cc", $cc);
                    AddAddressToMailer($modx->mail, "bcc", $bcc);
                    AttachFilesToMailer($modx->mail, $attachments);
                    if (!$modx->mail->send()) {
                        return 'Main mail: ' . $_lang['ef_mail_error'] . $modx->mail->ErrorInfo;
                    }
                }

                # send user a copy of the report
                if ($ccsender && $fields['email']) {
                    evo()->loadExtension('MODxMailer');
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = $from;
                    $modx->mail->FromName = $fromname;
                    $modx->mail->Subject = $subject;
                    $modx->mail->Body = $report;
                    AddAddressToMailer($modx->mail, 'to', $fields['email']);
                    AttachFilesToMailer($modx->mail, $attachments);
                    if (!$modx->mail->send()) {
                        return 'CCSender: ' . $_lang['ef_mail_error'] . $modx->mail->ErrorInfo;
                    }
                }

                # send auto-respond email
                //defaults to html so only test sendasText
                $isHtml = ($sendAsText == 1 || strpos($sendAsText, 'autotext') !== false) ? false : true;
                if ($autotext && $fields['email'] != '') {
                    $autotext = formMerge($autotext, $fields);
                    evo()->loadExtension('MODxMailer');
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = ($autosender) ? $autosender : $from;
                    $modx->mail->FromName = ($autoSenderName) ? $autoSenderName : $fromname;
                    $modx->mail->Subject = $subject;
                    $modx->mail->Body = $autotext;
                    AddAddressToMailer($modx->mail, 'to', $fields['email']);
                    if (!$modx->mail->send()) {
                        return 'AutoText: ' . $_lang['ef_mail_error'] . $modx->mail->ErrorInfo;
                    }
                }

                //defaults to text - test for sendAsHtml
                $isHTML = ($sendAsHTML == 1 || strpos($sendAsHtml, 'mobile') !== false);
                # send mobile email
                if ($mobile && $mobiletext) {
                    $mobiletext = formMerge($mobiletext, $fields);
                    evo()->loadExtension('MODxMailer');
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = $from;
                    $modx->mail->FromName = $fromname;
                    $modx->mail->Subject = $subject;
                    $modx->mail->Body = $mobiletext;
                    AddAddressToMailer($modx->mail, 'to', $mobile);
                    $modx->mail->send();
                }

            }//end test nomail
            # added in 1.4.2 - Protection against multiple submit with same form data
            if ($protectSubmit) {
                $_SESSION[$formid . '_hash'] = $hash;
            } //hash is set earlier

            # added in 1.4.2 - Limit the time between form submissions
            if ($submitLimit > 0) {
                $_SESSION[$formid . '_limit'] = time();
            }

            # invoke OnMailSent event set by another script
            if ($eFormOnMailSent) {
                if ($isDebug && !function_exists($eFormOnMailSent)) {
                    $fields['debug'] .= "eFormOnMailSent event: Could not find the function" . $eFormOnMailSent;
                } else {
                    if ($eFormOnMailSent($fields) === false) return;
                }
            }


            if ($isDebug) {
                $debugText .= sprintf(
                    '<strong>Mail Headers:</strong><br />From: %s (%s)<br />Reply-to:%s<br />To: %s<br />Subject: %s<br />CC: %s<br /> BCC:%s<br />'
                    , $from
                    , $fromname
                    , $replyto
                    , $to
                    , $subject
                    , $cc
                    , $bcc
                );
                if ($isDebug > 1) {
                    $debugText .= "<br /><strong>Formats array:</strong><pre>" . var_export($formats, true) . '</pre>';
                    $debugText .= "<br /><strong>Fields array:</strong><pre>" . var_export($fields, true) . '</pre>';
                }
                $fields['debug'] = $debugText;
            }

            # show or redirect to thank you page
            if ($gid == $modx->documentIdentifier) {
                if (!empty($thankyou)) {
                    if ($isDebug && strpos($thankyou, '[+debug+]') === false) {
                        $thankyou .= '[+debug+]';
                    }
                    if (isset($startupSource)) {
                        efRegisterStartupBlock($startupSource, true);
                    }    //skip scripts
                    if ($sendAsText) {
                        foreach ($formats as $key => $fmt) {
                            if ($fmt[2] === 'html') {
                                $fields[$key] = str_replace("\n", '<br />', $fields[$key]);
                            }
                        }
                    }
                    $thankyou = formMerge($thankyou, $fields);
                    return $thankyou;
                }
                return $_lang['ef_thankyou_message'];
            }

            $url = $modx->makeURL($gid);
            $modx->sendRedirect($url);
            return ''; // stop here
        }
    } else { //not postback

        //add debugging info to fields array
        if ($isDebug) {
            $debugText .= "<br /><strong>eForm configuration:</strong><pre>" . var_export($params, true) . '</pre>';
            $fields['debug'] = $debugText;
        }

        //strip the eform attribute
        $regExpr = "#eform=([\"'])[^\\1]*?\\1#si";
        $tpl = preg_replace($regExpr, '', $tpl);
    }

    // set vericode
    if ($vericode) {
        $_SESSION['eForm.VeriCode'] = $fields['vericode'] = substr(uniqid(''), -5);
        $fields['verimageurl'] = $modx->config['base_url'] . 'index.php?get=captcha';
    }

    # get SESSION data - thanks to sottwell
    if ($sessionVars) {
        $sessionVars = (strpos($sessionVars, ',', 0)) ? explode(',', $sessionVars) : array($sessionVars);
        foreach ($sessionVars as $varName) {
            if (empty($varName)) {
                continue;
            }
            $varName = trim($varName);
            if (isset($_SESSION[$varName]) && !empty($_SESSION[$varName])) {
                if (isset($fields[$varName]) && $postOverides) {
                    $fields[$varName] = $fields[$varName];
                } else {
                    $fields[$varName] = $_SESSION[$varName];
                }
            }
        }
    }

    # invoke OnBeforeFormMerge event set by another script
    if ($eFormOnBeforeFormMerge) {
        if ($isDebug && !function_exists($eFormOnBeforeFormMerge)) {
            $fields['debug'] .= "eFormOnBeforeFormMerge event: Could not find the function " . $eFormOnBeforeFormMerge;
        } else {
            if ($eFormOnBeforeFormMerge($fields) === false) {
                return;
            }
        }
    }

    # build form
    if ($isDebug && !$fields['debug']) {
        $fields['debug'] = $debugText;
    }
    if ($isDebug && strpos($tpl, '[+debug+]') === false) {
        $tpl .= '[+debug+]';
    }
    //register css and/or javascript
    if (isset($startupSource)) {
        efRegisterStartupBlock($startupSource);
    }
    return formMerge($tpl, $fields);
}

# Form Merge
function formMerge($docText, $docFields, $vClasses = '')
{
    global $formats;
    static $lastitems = array();

    if (!$docText) {
        return '';
    }

    preg_match_all('~\[\+(.*?)\+\]~', $docText, $matches);
    foreach ($matches[1] as $iValue) {
        $name = $iValue;
        if (strpos($name, ':') !== false) {
            list($listName, $listValue) = explode(':', $name);
        } else {
            $listName = $name;
        }
        $value = isset($docFields[$listName]) ? $docFields[$listName] : '';

// support for multi checkbox, radio and select - Djamoer
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $fld = (isset($formats[$name])) ? $formats[$name] : '';
        if (empty($fld)) {
            // listbox, checkbox, radio select
            $colonPost = strpos($name, ':');
            $listName = substr($name, 0, $colonPost);
            $listValue = substr($name, $colonPost + 1);
            $datatype = isset($formats[$listName][2]) ? $formats[$listName][2] : '';
            if (isset($docFields[$listName]) && is_array($docFields[$listName])) {
                if ($datatype === 'listbox' && in_array($listValue, $docFields[$listName])) {
                    $docText = str_replace(
                        sprintf(
                            '[+%s:%s+]'
                            , $listName
                            , $listValue
                        )
                        , "selected='selected'"
                        , $docText
                    );
                }
                if (in_array($datatype, array('checkbox', 'radio')) && in_array($listValue, $docFields[$listName])) {
                    $docText = str_replace(
                        sprintf(
                            '[+%s:%s+]'
                            , $listName
                            , $listValue
                        )
                        , "checked='checked'"
                        , $docText
                    );
                }
            } else {
                if ($datatype === 'listbox' && isset($docFields[$listName]) && $listValue == $docFields[$listName]) {
                    $docText = str_replace(
                        sprintf(
                            '[+%s:%s+]'
                            , $listName
                            , $listValue
                        )
                        , "selected='selected'"
                        , $docText
                    );
                }
                if (in_array($datatype, array('checkbox', 'radio')) && $listValue == $docFields[$listName]) {
                    $docText = str_replace(
                        sprintf(
                            '[+%s:%s+]'
                            , $listName
                            , $listValue
                        )
                        , "checked='checked'"
                        , $docText
                    );
                }
            }
        }

        if (strpos($name, ':') === false) {
            $docText = str_replace("[+$name+]", $value, $docText);
        } else {
            // this might be a listbox item.
            // we'll remove this field later
            $lastitems[count($lastitems)] = "[+$name+]";
        }
    }
    $lastitems[count($lastitems)] = "class=\"\""; //removal off empty class attributes
    $docText = str_replace($lastitems, "", $docText);
    return $docText;
}

# Adds Addresses to Mailer
function AddAddressToMailer(&$mail, $type, $addr)
{
    if (empty($addr)) {
        return;
    }
    $a = explode(',', $addr);
    foreach ($a as $_) {
        if ($type === 'to') {
            $mail->AddAddress($_);
        } elseif ($type === 'cc') {
            $mail->AddCC($_);
        } elseif ($type === 'bcc') {
            $mail->AddBCC($_);
        } elseif ($type === 'replyto') {
            $mail->AddReplyTo($_);
        }
    }
}

# Attach Files to Mailer
function AttachFilesToMailer(&$mail, &$attachFiles)
{
    if (!$attachFiles) {
        return;
    }
    foreach ($attachFiles as $attachFile) {
        if (!file_exists($attachFile)) {
            continue;
        }
        $FileName = $attachFile;
        $contentType = "application/octetstream";
        if (is_uploaded_file($attachFile)) {
            foreach ($_FILES as $n => $v) {
                if ($_FILES[$n]['tmp_name'] == $attachFile) {
                    $FileName = $_FILES[$n]['name'];
                    $contentType = $_FILES[$n]['type'];
                }
            }
        }
        $patharray = explode(((strpos($FileName, "/") === false) ? "\\" : "/"), $FileName);
        $FileName = $patharray[count($patharray) - 1];
        $mail->AddAttachment($attachFile, $FileName, "base64", $contentType);
    }
}

/*--- Form Parser stuff----------------------*/
function eFormParseTemplate($tpl, $isDebug = false)
{
    global $modx, $formats, $optionsName, $_lang, $debugText, $fields, $validFormId;
    global $efPostBack;

    $formats = array();  //clear formats so values don't persist through multiple snippet calls
    $labels = array();

    $regExpr = "#(<label[^>]*?>)(.*?)</label>#si";
    preg_match_all($regExpr, $tpl, $matches);
    foreach ($matches[1] as $key => $fld) {
        $attr = attr2array($fld);
        if (isset($attr['for'])) {
            $name = substr($attr['for'], 1, -1);
            //add class to fields array
            $fields[$name . "_vClass"] = isset($attr['class']) ? substr($attr['class'], 1, -1) : '';
            $labels[$name] = strip_tags($matches[2][$key]);
            //create placeholder for class
            $attr['class'] = '"[+' . $name . '_vClass+]"';
            $newTag = buildTagPlaceholder('label', $attr, $name);
            $tpl = str_replace($fld, $newTag, $tpl);
        }
    }

    //retrieve all the form fields
    $regExpr = "#(<(input|select|textarea)[^>]*?>)#si";
    preg_match_all($regExpr, $tpl, $matches);

    $fieldTypes = $matches[2];
    $fieldTags = $matches[1];

    for ($i = 0, $iMax = count($fieldTypes); $i < $iMax; $i++) {
        $type = $fieldTypes[$i];

        //get array of html attributes
        $tagAttributes = attr2array($fieldTags[$i]);
        //attribute values are stored including quotes
        //strip quotes as well as any brackets to get the raw name
        $name = str_replace(array("'", '"', '[', ']'), '', $tagAttributes['name']);

        #skip vericode field - updated in 1.4.4
        #special case. We need to set the class placeholder but forget about the rest
        if ($name === 'vericode') {
            if (isset($tagAttributes['class'])) {
                $fields[$name . '_class'] = substr($tagAttributes['class'], 1, -1);
            }
            $tagAttributes['class'] = '"[+' . $name . '_class+]"';
            $tagAttributes['value'] = '';
            $newTag = buildTagPlaceholder('input', $tagAttributes, $name);
            $tpl = str_replace($fieldTags[$i], $newTag, $tpl);
            continue;
        }

        //store the field options
        if (isset($tagAttributes[$optionsName])) {
            //split to max of 5 so validation rule can contain ':'
            $formats[$name] = explode(":", stripTagQuotes($tagAttributes[$optionsName]), 5);
            array_unshift($formats[$name], $name);
        } else {
            if (!isset($formats[$name])) $formats[$name] = array($name, '', '', 0);
        }
        //added for 1.4 - use label if it is defined
        if (empty($formats[$name][1])) {
            $formats[$name][1] = (isset($labels[$name])) ? $labels[$name] : $name;
        }

        if (isset($id)) {
            $formats[6] = $id;
        } //added in 1.4.4.1

        unset($tagAttributes[$optionsName]);

        //added in 1.4.2 - add placeholder to class attribute
        if (isset($tagAttributes['class'])) {
            $fields[$name . '_class'] = substr($tagAttributes['class'], 1, -1);
        }
        $tagAttributes['class'] = '"[+' . $name . '_class+]"';

        switch ($type) {
            case "select":
                //replace with 'cleaned' tag and added placeholder
                $newTag = buildTagPlaceholder('select', $tagAttributes, $name);
                $tpl = str_replace($fieldTags[$i], $newTag, $tpl);
                if ($formats[$name]) $formats[$name][2] = 'listbox';

                //Get the whole select block with option tags
                //escape any regex characters!
                $regExp = "#<select [^><]*?name=" . preg_quote($tagAttributes['name'], '#') . "[^>]*?" . ">(.*?)</select>#si";
                preg_match($regExp, $tpl, $matches);
                $optionTags = $matches[1];

                $select = $newSelect = $matches[0];
                //get separate option tags and split them up
                preg_match_all("#(<option [^>]*?>)#si", $optionTags, $matches);
                $validValues = array();
                foreach ($matches[1] as $option) {
                    $attr = attr2array($option);
//* debug */ print __LINE__.': <pre>'.print_r($attr,true) .'</pre><br />';
                    $value = substr($attr['value'], 1, -1); //strip outer quotes
                    if (trim($value) != '') $validValues[] = $value;
                    $newTag = buildTagPlaceholder('option', $attr, $name);
                    $newSelect = str_replace($option, $newTag, $newSelect);
                    //if no postback, retain any checked values
                    if (!$efPostBack && !empty($attr['selected'])) $fields[$name][] = $value;
                }
                //replace complete select block
                $tpl = str_replace($select, $newSelect, $tpl);
                //add valid values to formats... (extension to $formats)

                if ($formats[$name] && !isset($formats[$name][5])) {
                    $formats[$name][4] = $_lang['ef_failed_default'];
                    //convert commas in values to something else !
                    $formats[$name][5] = "#LIST " . implode(",", str_replace(',', '&#44;', $validValues));
                }
                break;

            case "textarea":
                // add support for maxlength attribute for textarea
                // attribute get's stripped form form //
                if (isset($tagAttributes['maxlength'])) {
                    $formats[$name][$_dfnMaxlength] == $tagAttributes['maxlength'];
                    unset($tagAttributes['maxlength']);
                }
                $newTag = buildTagPlaceholder($type, $tagAttributes, $name);
                $regExp = "#<textarea [^>]*?name=" . $tagAttributes["name"] . "[^>]*?" . ">(.*?)</textarea>#si";
                preg_match($regExp, $tpl, $matches);
                //if nothing Posted retain the content between start/end tags
                $placeholderValue = ($efPostBack) ? "[+$name+]" : $matches[1];

                $tpl = str_replace($matches[0], $newTag . $placeholderValue . "</textarea>", $tpl);
                break;
            default: //all the rest, ie. "input"
                $newTag = buildTagPlaceholder($type, $tagAttributes, $name);
                $fieldType = stripTagQuotes($tagAttributes['type']);
                //validate on maxlength...
                if ($fieldType == 'text' && isset($tagAttributes['maxlength']) && $tagAttributes['maxlength']) {
                    $formats[$name][$_dfnMaxlength] == $tagAttributes['maxlength'];
                }
                if ($formats[$name] && !$formats[$name][2]) $formats[$name][2] = ($fieldType == 'text') ? "string" : $fieldType;
                //populate automatic validation values for hidden, checkbox and radio fields
                if ($fieldType == 'hidden') {
                    if (!$isDebug) $formats[$name][1] = "[undefined]"; //do not want to disclose hidden field names
                    if (!isset($formats[$name][4])) $formats[$name][4] = $_lang['ef_tamper_attempt'];
                    if (!isset($formats[$name][5])) $formats[$name][5] = "#VALUE " . stripTagQuotes($tagAttributes['value']);
                } elseif ($fieldType == 'checkbox' || $fieldType == 'radio') {
                    $formats[$name][4] = $_lang['ef_failed_default'];
                    $formats[$name][5] .= isset($formats[$name][5]) ? "," : "#LIST ";
                    //convert embedded comma's in values!
                    $formats[$name][5] .= str_replace(',', '&#44;', stripTagQuotes($tagAttributes['value']));
                    //store the id as well
                    //if no postback, retain any checked values
                    if (!$efPostBack && !empty($tagAttributes['checked'])) $fields[$name][] = stripTagQuotes($tagAttributes['value']);
                    //
                    $formats[$name][6] .= (isset($formats[$name][6]) ? "," : "") . stripTagQuotes($tagAttributes['id']);
                } elseif (empty($fields[$name])) { //plain old text input field
                    //retain default value set in form template if not already set in code
                    $fields[$name] = (isset($tagAttributes['value'])) ? stripTagQuotes($tagAttributes['value']) : '';
                }

                $tpl = str_replace($fieldTags[$i], $newTag, $tpl);
                break;
        }
    }
    if ($isDebug > 2) $debugText .= "<strong>Parsed template</strong><p style=\"border:1px solid black;padding:2px;\">" . str_replace("\n", '<br />', str_replace('+', '&#043;', htmlspecialchars($tpl))) . "</p><hr>";
    return $tpl;
}

function stripTagQuotes($value)
{
    return substr($value, 1, -1);
}

function buildTagPlaceholder($tag, $attributes, $name)
{
    $type = (isset($attributes["type"])) ? stripTagQuotes($attributes["type"]) : '';
    $quotedValue = (isset($attributes['value'])) ? $attributes['value'] : '';
    $val = stripTagQuotes($quotedValue);

    $t = '';
    foreach ($attributes as $k => $v) {
        $t .= ($k != 'value' && $k != 'checked' && $k != 'selected') ? sprintf(' %s=%s', $k, $v) : "";
    }

    switch ($tag) {
        case "select":
            return "<$tag$t>"; //only the start tag mind you
        case "option":
            return "<$tag$t value=" . $quotedValue . " [+$name:$val+]>";
        case "input":
            switch ($type) {
                case 'radio':
                case 'checkbox':
                    return sprintf(
                        '<input%s value=%s [+%s:%s+] />'
                        , $t
                        , $quotedValue
                        , $name
                        , $val
                    );
                case 'text':
                    if ($name == 'vericode') {
                        return "<input$t value=\"\" />";
                    }
                //else pass on to next
                case 'password':
                    return "<input$t value=\"[+$name+]\" />";
                default: //leave as is - no placeholder
                    return "<input$t value=" . $quotedValue . " />";
            }
        case "file": //no placeholder!
        case "textarea": //placeholder needs to be added in calling code
            return "<$tag$t>";
        case "label":
            return "<$tag$t>";
        default:
            return "<input$t value=\"[+$name+]\" />";
    } // switch
}

function attr2array($tag)
{
    preg_match_all("#([a-z0-9_-]*?)=(([\"'])[^\\3]*?\\3)#si", $tag, $matches);
    foreach ($matches[1] as $i => $key) {
        $rt[$key] = $matches[2][$i];
    }
    if (isset($rt)) {
        return $rt;
    }
    return '';
}

function validateField($value, $fld, &$vMsg, $isDebug = false)
{
    global $modx, $_lang, $debugText, $fields;
    $output = true;
    $desc = $fld[1];
    $fldMsg = trim($fld[4]);
    if (empty($fld[5])) return $output; //if no rule value validates

    list($cmd, $param) = explode(" ", trim($fld[5]), 2);
    $cmd = strtoupper(trim($cmd));
    if (substr($cmd, 0, 1) != '#') {
        $vMsg[count($vMsg)] = "$desc &raquo;" . $_lang['ef_error_validation_rule'];
        return false;
    }

    $valList = (is_array($value)) ? $value : array($value);
    //init vars
    $errMsg = '';
    unset($vlist);

    foreach ($valList as $i => $iValue) {
        $value = $iValue; //re-using $value, destroying original!!
        switch ($cmd) {
            //really only used internally for hidden fields
            case "#VALUE":
                if ($value != $param) $errMsg = $_lang['ef_failed_default'];
                break;
            case "#RANGE":
                if (!isset($vlist)) $vlist = explode(',', strtolower(trim($param))); //cached
                //the crude way first - will have to refine this
                foreach ($vlist as $p) {
                    if (strpos($p, '~') > 0) {
                        $range = explode('~', $p);
                    } else {
                        $range = array($p, $p);
                    } //yes,.. I know - cheating :)

                    if ($isDebug && (!is_numeric($range[0]) || !is_numeric($range[1])))
                        $modx->messageQuit('Error in validating form field!', '', $false, E_USER_WARNING, __FILE__, '', '#RANGE rule contains non-numeric values: ' . $fld[5], __LINE__);
                    sort($range);
                    if ($value >= $range[0] && $value <= $range[1]) break 2; //valid
                }
                $errMsg = $_lang['ef_failed_range'];
                break;

            case "#LIST":        // List of comma separated values (not case sensitive)
                //added in 1.4 - file upload filetype test
//FS#960 - removed trimming of $param - values with leading or trailing spaces would always fail validation
                if ($fld[2] == 'file') $value = substr($value, strrpos($value, '.') + 1); //file extension
                if (!isset($vlist)) {
                    $vlist = explode(',', strtolower($param)); //cached
                    foreach ($vlist as $k => $v) {
                        $vlist[$k] = str_replace('&#44;', ',', $v);
                    }

                } //changes to make sure embedded commma's in values are recognized

                if ($isDebug && count($vlist) == 1 && empty($vlist[0])) {
                    //if debugging bail out big time
                    $modx->messageQuit('Error in validating form field!', '', $false, E_USER_WARNING, __FILE__, '', '#LIST rule declared but no list values supplied: ' . $fld[5], __LINE__);
                } elseif (!in_array(strtolower($value), $vlist))
                    $errMsg = ($fld[2] == 'file') ? $_lang["ef_failed_upload"] : $_lang['ef_failed_list'];
                break;

            case "#SELECT":    //validates against a list of values from the cms database
                #cache all this
                if (!isset($vlist)) {
                    $rt = array();
                    $param = str_replace('{DBASE}', $modx->db->config['dbase'], $param);
                    $param = str_replace('{PREFIX}', $modx->db->config['table_prefix'], $param);
                    //added in 1.4
                    if (preg_match("/{([^}]*?)\}/", $param, $matches)) {
                        $tmp = db()->escape(formMerge('[+' . $matches[1] . '+]', $fields));
                        $param = str_replace('{' . $matches[1] . '}', $tmp, $param);
                    }
                    $rs = db()->query("SELECT $param;");
                    //select value from 1st field in records only (not case sensitive)
                    while ($v = db()->getValue($rs)) $vlist[] = strtolower($v);
                }
                if (!is_array($vlist)) {
                    //WARNING! if not debugging (and query fails) error is ignored, and value will validate!!
                    //version 1.4 - replaced fatal error with friendly debug message when debugging
                    $debugText .= ($isDebug) ? "'<strong>$fld[1]</strong>' " . $_lang['ef_sql_no_result'] . '<br />' : '';
                } elseif (!in_array(strtolower($value), $vlist)) {
                    $errMsg = $_lang['ef_failed_list'];
                }
                break;

            case "#EVAL":    // php code should return true or false
                $tmp = $cmd; //just in case eval destroys cmd
                if (eval($param) === false)
                    $errMsg = $_lang['ef_failed_eval'];
                if ($isDebug) $debugText .= "<strong>$fld[1]</strong>: " . $_lang['ef_eval_deprecated'] . " $param";
                $cmd = $tmp;
                break;
            //added in 1.4
            case "#FUNCTION":
                $tmp = $cmd; //just in case function destroys cmd
                if (function_exists($param))
                    if (!@$param($value)) $errMsg = $_lang['ef_failed_eval'];
                    else
                        if ($isDebug) $debugText .= "<strong>$fld[1]</strong>: " . $_lang['ef_no_function'] . " $param";
                $cmd = $tmp;
                break;

            case "#REGEX":
                if (!$tmp = preg_match($param, $value)) {
                    $errMsg = $_lang['ef_failed_ereg'];
                }
                if ($isDebug && $tmp === false) {
                    $debugText .= "<strong>$fld[1]</strong>: " . $_lang['ef_regex_error'] . " $param";
                }
                break;

            case "#FILTER":
                $valList[$i] = filterEformValue($value, $param);
                break;

            default:
                $errMsg = $_lang['ef_error_validation_rule'];

        }//end switch
        if ($isDebug) {
            $debugText .= "'<strong>$fld[1]</strong>' ";
            $debugText .= (empty($errMsg)) ? 'passed' : '<span style="color:red;">Failed</span>';
            $debugText .= " using rule: $cmd " . $param . ', (input=' . htmlspecialchars($iValue) . ")<br />\n";
        }
        if (!empty($errMsg)) {
            $errMsg = ($fldMsg) ? "$desc &raquo; $fldMsg" : "$desc &raquo; $errMsg";
            $vMsg[count($vMsg)] = $errMsg;
            $output = false;
            break; //quit testing more values
        }
    }//end for


    //make sure we have correct return value in case of #filter
    $valList = (!is_array($value)) ? implode('', $valList) : $valList;

    return ($cmd == "#FILTER") ? $valList : $output;
}//end validateField

function filterEformValue($value, $param)
{
    list($cmd, $param) = explode(" ", trim($param), 2);
    $cmd = trim($cmd);

    switch (strtoupper($cmd)) {
        case "#REGEX":
            list($src, $dst) = explode("||", $param, 2);
            $value = ($v = preg_replace($src, $dst, $value)) ? $v : $value;
            break;

        case "#LIST":
            $param = explode("||", $param, 2);
            $src = strpos($param[0], ',') ? explode(',', $param[0]) : $param[0];
            $dst = strpos($param[1], ',') ? explode(',', $param[1]) : $param[1];
            $value = str_replace($src, $dst, $value);
            break;

        case "#EVAL":
            $value = ($v = @eval($param)) ? $v : $value;
            break;
    }
    return $value;
}

function efLoadTemplate($key)
{
    global $modx;

    $tpl = '';
    if (strpos($key, '@FILE') === 0) {
        $path = substr($key, 6);
        $path = trim($path);
        $path = ltrim($path, '/');
        $path = MODX_BASE_PATH . $path;
        $tpl = file_get_contents($path);
    } elseif (strpos($key, '@CODE') === 0) {
        $tpl = substr($key, 6);
    } elseif (is_numeric($key)) { //get from document id
        //try unpublished docs first
        $tpl = ($doc = $modx->getDocument($key, 'content', 0)) ? $doc['content'] : false;
        if (!$tpl)
            $tpl = ($doc = $modx->getDocument($key, 'content', 1)) ? $doc['content'] : false;

    } elseif ($key) {
        $tpl = ($doc = $modx->getChunk($key)) ? $doc : false;
        //try snippet if chunk is not found
        if (!$tpl) $tpl = ($doc = $modx->runSnippet($key)) ? $doc : false;
    }
    $modx->parseDocumentSource($tpl);
    return $tpl;
}


# registers css and/or javascript to modx class
function efRegisterStartupBlock($src_array, $noScript = false)
{
    global $modx;

    if (!array($src_array)) {
        return;
    }

    foreach ($src_array as $item) {
        list($src, $type) = $item;
        //skip scripts if told to do so
        if ($type === 'javascript' && $noScript) {
            continue;
        }
        //try to load from document or chunk
        if ($tmp = efLoadtemplate($src)) {
            $src = $tmp;
        }
        $src = trim($src);

        if ($type === 'css') {
            $modx->regClientCSS($src);
        } elseif ($type === 'javascript') {
            $modx->regClientStartupScript($src);
        } else {
            $modx->regClientStartupHTMLBlock($src);
        }

    }//end foreach
}

/**
 * adapted from http://php.mirrors.ilisys.com.au/manual/en/ref.mail.php
 * If any field contains newline followed by email-header specific string it is replaced by a harmless substitute
 * (we hope) If any subsitutiosn are made the function returns true
 * Currently tests for
 *     Content-Transfer-Encoding:
 *     MIME-Version:
 *     content-type:
 *     to:
 *     cc:
 *     bcc:
 */
function hasMailHeaders(&$fields)
{
    $injectionAttempt = false;
    $re = "/(%0A|%0D|\n+|\r+)(Content-Transfer-Encoding:|MIME-Version:|content-type:|to:|cc:|bcc:)/i";
    foreach ($fields as $fld => $text) {
        if (($match = preg_replace($re, '\\[\2]\\', $text)) != $text) {
            $injectionAttempt = true;
            $fields[$fld] = $match; //replace with 'disabled' version
        }
    }
    return ($injectionAttempt) ? true : false;
}

function get_default_tpl()
{
    return <<< EOT
    <p class="error">[+validationmessage+]</p>
    <form method="post" action="[~[*id*]~]">
    <input name="formid" type="hidden" value="eform" />
    Name : <input name="name" class="text" type="text" size="30" eform="Your Name::1:" /><br />
    Email : <input name="email" class="text" type="text" size="30" eform="Email Address:email:1" /><br />
    Message : <textarea name="message" rows="4" cols="20" eform="Message:textarea:1"></textarea><br />
    <input type="submit" name="contact" class="button" value="send" />
    </form>
EOT;
}

function get_default_report()
{
    return <<< EOT
Name : [+name+]
Email :  [+email+]
Message  :
[+message+]
EOT;
}
