<?php
# eForm - Electronic Form Snippet
# Original created by: Raymond Irving 15-Dec-2004.
# Extended by: Jelle Jager (TobyL) September 2006

function eForm($modx, $params)
{
    global $_lang;
    global $debugText;
    global $formats, $fields, $efPostBack;

    $fields = []; // 同じページに複数のフォームがあってもよいように、毎回空にする

    extract($params, EXTR_SKIP);

    // バインディングが無効のときは、引数で指定したフックを呼ばない（フックが無いときと同じ扱い）
    if (config('enable_bindings', 0) != 1) {
        $eFormOnBeforeFormParse = '';
        $eFormOnValidate = '';
        $eFormOnBeforeMailSent = '';
        $eFormOnMailSent = '';
        $eFormOnBeforeFormMerge = '';
    }

    // read every call: another form on the page may use another language or &submitLimit
    include($snipPath . "lang/english.inc.php");

    $form_language = isset($language) ? $language : config('manager_language', 'english');
    if (!preg_match('/^[\w\-]+$/', (string) $form_language)) {
        $form_language = 'english';
    }
    if ($form_language != "english" && $form_language != '') {
        if (file_exists("{$snipPath}lang/{$form_language}.inc.php")) {
            include("{$snipPath}lang/{$form_language}.inc.php");
        } else {
            if ($isDebug) $debugText .= "<strong>Language file '{$form_language}.inc.php' not found!</strong><br />";
        }
    }

    if ($isDebug) {
        $debugText .= $_lang['ef_debug_warning'];
    }

    $nomail = $noemail;
    // 宛先が無いときはメールを送らない
    if (!$to) {
        $nomail = 1;
    }

    if ($tpl == $modx->documentIdentifier) {
        return $_lang['ef_is_own_id'] . "'$tpl'";
    }

    if (empty($tpl)) {
        $tpl = get_default_tpl();
    } elseif ($tmp = efLoadTemplate($tpl)) $tpl = $tmp;
    else return $_lang['ef_no_doc'] . " '$tpl'";

    // フォームの id は <form> タグの id、無ければ hidden の formid から取る
    if (preg_match('/<form[^>]*?id=[\'"]([^\'"]*?)[\'"]/i', $tpl, $matches)) {
        $formid = $matches[1];
    } elseif (!preg_match('/<input[^>]*?name=[\'"]formid[\'"][^>]*?>/i', $tpl, $matches)) {
    } else {
        $_ = $matches[0];
        if (preg_match('@value=[\'"]([^\'"]*?)[\'"]@', $_, $matches)) {
            $formid = $matches[1];
        }
    }
    if (!preg_match('/<input[^>]*?name=[\'"]formid[\'"]/i', $tpl)) {
        $tpl = str_replace('</form>', sprintf(
            '<input type="hidden" name="formid" value="%s" /></form>',
            $formid
        ), $tpl);
    }

    $postedFormId = postv('formid'); // 文字列だけを受け付ける（配列は不正）
    $validFormId = is_string($postedFormId) && $formid == $postedFormId ? 1 : 0;

    $efPostBack = $validFormId && is_post() && efIsSameHostRequest();

    // 廃止した引数が書かれていたら、送信のときに警告を残す（表示のたびに残すとログがあふれる。処理は止めない）
    $removed = $removedParams ?? [];
    if ($efPostBack && $removed) {
        evo()->logEvent(
            0,
            2,
            'eForm: 廃止した引数が指定されています。効果はありません: ' . implode(', ', $removed),
            'eForm',
            ['formid' => $formid, 'params' => $removed]
        );
    }

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

    // フォームの表示時に HEAD へ足す
    if ($cssStyle) {
        $cssStyle = (strpos($cssStyle, ',') && strpos($cssStyle, '<style') === false) ? explode(',', $cssStyle) : [$cssStyle];
        foreach ($cssStyle as $tmp) {
            $startupSource[] = [$tmp, 'css'];
        }
    }
    if ($jScript) {
        $jScript = (strpos($jScript, ',') && strpos($jScript, '<script') === false) ? explode(',', $jScript) : [$jScript];
        foreach ($jScript as $tmp) {
            $startupSource[] = [$tmp, 'javascript'];
        }
    }

    // イベント用の関数を定義するスニペットを実行する
    if (strlen($runSnippet) > 0) {
        $modx->runSnippet($runSnippet, ['formid' => $formid]);
        // runSnippet の戻り値は曖昧で、失敗したかどうかは分からない
    }

    if ($eFormOnBeforeFormParse) {
        if ($isDebug && !function_exists($eFormOnBeforeFormParse)) {
            $fields['debug'] = ($fields['debug'] ?? '') . "eFormOnBeforeFormParse event: Could not find the function " . $eFormOnBeforeFormParse;
        } else {
            $templates = ['tpl' => $tpl, 'report' => $report, 'thankyou' => $thankyou, 'autotext' => $autotext];
            if ($eFormOnBeforeFormParse($fields, $templates) === false) {
                return "";
            } elseif (is_array($templates))
                extract($templates);
        }
    }

    $tpl = eFormParseTemplate($tpl, $isDebug);

    if ($efPostBack) {
        foreach ($formats as $k => $discard) {
            if (!isset($fields[$k])) {
                $fields[$k] = "";
            }
        }

        $disclaimer = (($tmp = efLoadTemplate($disclaimer)) !== false) ? $tmp : '';

        $vMsg = $rMsg = $rClass = [];

        $nestedFields = []; // 入れ子の配列が送られた項目（不正な値として扱う）
        foreach (postv() as $name => $value) {
            // eForm が組み立てる項目は、送られた値で上書きさせない
            if (in_array($name, ['validationmessage', 'debug', 'disclaimer'], true)) {
                continue;
            }
            // 単一の値と配列の各値に同じ処理を通す（allowhtml と html 書式のときだけタグを許す）
            $cleanValue = function ($v) use ($allowhtml, $formats, $name, $modx) {
                if (!$allowhtml || ($formats[$name][2] ?? '') != 'html') {
                    return strip_tags($v);
                }
                return $modx->htmlspecialchars($v);
            };
            if (is_array($value) && count($value) !== count($value, COUNT_RECURSIVE)) {
                $nestedFields[$name] = true;
                $fields[$name] = ''; // 再表示・メールで扱える形にする
            } elseif (is_array($value)) {
                // 空文字の要素だけ除く（"0" は有効な値として残す）
                $fields[$name] = array_filter(array_map($cleanValue, $value), function ($v) {
                    return $v !== '';
                });
            } else {
                $fields[$name] = $cleanValue($value);
            }
        }

        // スニペットのタグが実行されないように無害化する
        $modx->sanitize_gpc($fields);

        foreach ($_FILES as $name => $value) {
            if (($formats[$name][2] ?? '') == 'file') {
                $fields[$name] = $value;
                continue;
            }
            // file 以外の項目には、アップロードの情報を入れない（POST の値があればそれを残す）
            if (is_array($value['name'] ?? null) && isset($formats[$name])) {
                // 書式のある項目に入れ子のアップロードが来たときは、不正な値として扱う
                $nestedFields[$name] = true;
                $fields[$name] = '';
                continue;
            }
            if (!isset($fields[$name])) {
                $fields[$name] = '';
            }
        }

        if ($vericode) {
            $code = (string)sessionv('veriword');
            $postedCode = post_strvar('vericode');
            // 認証コードは 1 回だけ使える。答えが空のときは一致させない
            sessionv('*veriword', null);
            if ($code === '' || !hash_equals($code, $postedCode)) {
                $vMsg[count($vMsg)] = $_lang['ef_failed_vericode'];
                $rClass['vericode'] = $invalidClass;
            }
        }

        foreach ($fields as $name => $value) {
            $fld = isset($formats[$name]) ? $formats[$name] : '';
            if ($fld) {
                $desc = $fld[1] ?? $name;
                $datatype = $fld[2] ?? '';
                $isRequired = $fld[3] ?? 0;

                // 単一の値を前提にした書式は、配列（name[]）の入力を不正な値として扱う
                if ($datatype != 'file') {
                    $isSingleValue = in_array($datatype, ['integer', 'float', 'date', 'email'], true);
                    if (isset($nestedFields[$name]) || ($isSingleValue && is_array($value) && $value !== [])) {
                        $vMsg[count($vMsg)] = $desc . ' &raquo;' . $_lang['ef_failed_default'];
                        $rClass[$name] = $invalidClass;
                        continue;
                    }
                    if ($value === []) {
                        $value = ''; // 空の配列は、書式にかかわらず未入力と同じ
                        $fields[$name] = ''; // コールバックやメールにも単一の値として渡す
                    }
                }

                if ($isRequired == 1 && $value == "" && $datatype != "file") {
                    $rMsg[count($rMsg)] = (string)$desc;
                    $rClass[$name] = $requiredClass;

                } elseif (!empty($fld[5]) && $value != "" && $datatype != "file") {
                    $value = validateField($value, $fld, $vMsg, $isDebug);

                    if ($value === false) {
                        $rClass[$name] = $invalidClass;
                    } elseif ($value !== true) {
                        // #FILTER は値を返すので置き換える
                        $fields[$name] = $value;
                    }

                } else {
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
                            if (strtotime($value) === false) {
                                $vMsg[count($vMsg)] = $desc . $_lang["ef_invalid_date"];
                                $rClass[$name] = $invalidClass;
                            }
                            break;
                        case "email":
                            if (strlen($value) > 0 && !efIsValidEmail($value)) {
                                $vMsg[count($vMsg)] = isset($formats[$name][4]) ? $formats[$name][4] : $desc . $_lang["ef_invalid_email"];
                                $rClass[$name] = $invalidClass;
                            }
                            break;
                        case "file":
                            $file = isset($_FILES[$name]) && is_array($_FILES[$name]) ? $_FILES[$name] : [];
                            $fileError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
                            if (is_array($file['name'] ?? '')) {
                                // name[] 形式のアップロードは扱わない
                                $vMsg[count($vMsg)] = $desc . ' &raquo;' . $_lang['ef_failed_default'];
                                $rClass[$name] = $invalidClass;
                            } elseif ($fileError == UPLOAD_ERR_INI_SIZE || $fileError == UPLOAD_ERR_FORM_SIZE) {
                                $vMsg[count($vMsg)] = $desc . $_lang['ef_upload_exceeded'];
                                $rClass[$name] = $invalidClass;
                            } elseif ($isRequired == 1 && ($file['type'] ?? '') == '') {
                                $rMsg[count($rMsg)] = $desc;
                                $rClass[$name] = $requiredClass;
                            } elseif (!empty($file['tmp_name'])) {
                                if (strpos($fld[5] ?? '', '#LIST') !== 0 || validateField($file['name'], $fld, $vMsg, $isDebug)) {
                                    if(!isset($attachments)) {
                                        $attachments = [];
                                    }
                                    $attachments[count($attachments)] = $file['tmp_name'];
                                } else $rClass[$name] = $invalidClass;
                            }
                            break;
                        case "html":
                        case "checkbox":
                        case "string":
                        default:
                            break;
                    }
                }
            }
        }

        if ($eFormOnValidate) {
            if ($isDebug && !function_exists($eFormOnValidate)) {
                $fields['debug'] = ($fields['debug'] ?? '') . "eformOnValidate event: Could not find the function " . $eFormOnValidate;
            } else {
                if ($eFormOnValidate($fields, $vMsg, $rMsg, $rClass) === false) {
                    return '';
                }
            }
        }

        if ($vMsg || $rMsg) {
            // 不正な項目のラベルと入力欄に class を付ける
            foreach ($rClass as $n => $class) {
                $fields[$n . '_class'] = !empty($fields[$n . '_class']) ? $fields[$n . '_class'] . ' ' . $class : $class;
                $fields[$n . '_vClass'] = isset($fields[$n . '_vClass']) ? $fields[$n . '_vClass'] . ' ' . $class : $class;
                // チェックボックスは選択肢ごとの id にも class を付ける
                if (isset($formats[$n][6])) {
                    foreach (explode(',', $formats[$n][6]) as $id) {
                        $fields[$id . '_vClass'] = !empty($fields[$id . '_vClass']) ? $fields[$id . '_vClass'] . ' ' . $class : $class;
                    }
                }
            }

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

            $tmp = ($rMsg) ? str_replace("{fields}", implode(", ", $rMsg), $_lang['ef_required_message']) : "";
            $tmp .= implode("<br />", $vMsg);
            if (strpos($tpl, '[+validationmessage+]') === false) {
                $modx->setPlaceholder('validationmessage', str_replace('[+ef_wrapper+]', $tmp, $_lang['ef_validation_message']));
            } else {
                if (!isset($fields['validationmessage'])) $fields['validationmessage'] = '';
                $fields['validationmessage'] .= str_replace('[+ef_wrapper+]', $tmp, $_lang['ef_validation_message']);
            }
        } else {

            foreach ($fields as $name => $value) {
                $fld = $formats[$name] ?? '';
                if ($fld) {
                    $datatype = $fld[2] ?? '';
                    switch ($datatype) {
                        case "integer":
                            $value = number_format((float)$value);
                            break;
                        case "float":
                            $localeInfo = localeconv();
                            $th_sep = empty($_lang['ef_thousands_separator']) ? $localeInfo['thousands_sep'] : $_lang['ef_thousands_separator'];
                            $dec_point = $localeInfo['decimal_point'];
                            $debugText .= 'Locale<pre>' . var_export($localeInfo, true) . '</pre>';
                            $value = number_format((float)$value, 2, $dec_point, $th_sep);
                            break;
                        case "date":
                            $format_string = isset($_lang['ef_date_format']) ? $_lang['ef_date_format'] : '%d-%b-%Y %H:%M:%S';
                            $value = ($value) ? evo()->mb_strftime($format_string, strtotime($value)) : "";
                            $value = str_replace("00:00:00", "", $value);
                            break;
                        case "html":
                            if (!$sendAsText) {
                                $value = preg_replace('#(\n<br[ /]*?>|<br[ /]*?>\n|\n)#i', '<br />', $value);
                            }
                            break;
                        case "file":
                            if (is_array($value) && ($value['type'] ?? '') != "" && ($value['error'] ?? UPLOAD_ERR_NO_FILE) == UPLOAD_ERR_OK) {
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
                    $fields[$name] = implode(', ', $value);
                }
            }
            $fields['postdate'] = evo()->mb_strftime($modx->toDateFormat(null, 'formatOnly') . " %H:%M:%S", time());

            // 送信の間隔を制限する
            if ($submitLimit > 0) {
                if (time() < $submitLimit + sessionv($formid . '_limit', 0)) {
                    return formMerge($_lang['ef_submit_time_limit'], efHtmlFields($fields));
                } else {
                    sessionv('*' . $formid . '_limit', null);
                    sessionv('*' . $formid . '_hash', null);
                }
            }

            if ($eFormOnBeforeMailSent) {
                if ($isDebug && !function_exists($eFormOnBeforeMailSent)) {
                    $fields['debug'] = ($fields['debug'] ?? '') . "eFormOnBeforeMailSent event: Could not find the function " . $eFormOnBeforeMailSent;
                } elseif ($eFormOnBeforeMailSent($fields) === false) {
                    if (isset($fields['validationmessage']) && !empty($fields['validationmessage'])) {
                        if (isset($startupSource)) {
                            efRegisterStartupBlock($startupSource);
                        }
                        return formMerge($tpl, efHtmlFields($fields));
                    }
                    return '';
                }
            }

            if ($protectSubmit) {
                $hash = '';
                if (!is_numeric($protectSubmit)) {
                    $protectSubmit = (strpos($protectSubmit, ',')) ? explode(',', $protectSubmit) : [$protectSubmit];
                    foreach ($protectSubmit as $fld) {
                        $hash .= isset($fields[$fld]) ? $fields[$fld] : '';
                    }
                } else {
                    // 必須項目すべて
                    foreach ($formats as $fld) {
                        $hash .= (($fld[3] ?? 0) == 1) ? ($fields[$fld[0]] ?? '') : '';
                    }
                }
                if ($hash) {
                    $hash = md5($hash);
                }

                $sessionHash = sessionv($formid . '_hash', '');
                if ($isDebug) {
                    $debugText .= "<strong>SESSION HASH</strong>:" . $sessionHash . "<br />" . "<b>FORM HASH</b>:" . $hash . "<br />";
                }

                // 同じ内容で送信済みなら止める
                if ($sessionHash === $hash && $hash != '') {
                    return formMerge($_lang['ef_multiple_submit'], efHtmlFields($fields));
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
            $fields['subject'] = $subject;
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
                if ($mselector && !empty($fields[$mselector])) {
                    $i = (int)$fields[$mselector];
                    $ar = explode(",", $to);
                    if ($i > 0) {
                        $i--;
                    }
                    if (!empty($ar[$i])) {
                        $to = $ar[$i];
                    } else {
                        $to = $ar[0];
                    }
                }

                // $replyto にはアドレスか項目名を指定する
                // 項目名のときは、利用者の入力が正しいアドレスの場合だけ 1 件使う
                $replytoIsInput = false;
                if (strpos($replyto, '@') === false) {
                    $replytoIsInput = isset($fields[$replyto]) && efIsValidEmail($fields[$replyto]);
                    $replyto = $replytoIsInput ? $fields[$replyto] : $from;
                }

                evo()->loadExtension('MODxMailer');

                // 既定は HTML
                $isHtml = !($sendAsText == 1 || strpos($sendAsText, 'report') !== false);

                if (!$noemail) {
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = $from;
                    $modx->mail->FromName = $fromname;
                    $modx->mail->Subject = $subject;
                    $modx->mail->Body = $report;
                    if ($replytoIsInput) {
                        $modx->mail->AddReplyTo($replyto);
                    } else {
                        AddAddressToMailer($modx->mail, "replyto", $replyto);
                    }
                    AddAddressToMailer($modx->mail, "to", $to);
                    AddAddressToMailer($modx->mail, "cc", $cc);
                    AddAddressToMailer($modx->mail, "bcc", $bcc);
                    AttachFilesToMailer($modx->mail, $attachments);
                    if (!$modx->mail->send()) {
                        return 'Main mail: ' . $_lang['ef_mail_error'] . $modx->mail->ErrorInfo;
                    }
                }

                $emailIsValid = efIsValidEmail($fields['email'] ?? '');
                if (!empty($fields['email']) && !$emailIsValid && ($ccsender || $autotext)) {
                    evo()->logEvent(0, 2, 'eForm: 宛先が正しくないため、控えと自動返信を送りませんでした', 'eForm', ['formid' => $formid]);
                }

                if ($ccsender && $emailIsValid) {
                    evo()->loadExtension('MODxMailer');
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = $from;
                    $modx->mail->FromName = $fromname;
                    $modx->mail->Subject = $subject;
                    $modx->mail->Body = $report;
                    $modx->mail->AddAddress($fields['email']);
                    AttachFilesToMailer($modx->mail, $attachments);
                    if (!$modx->mail->send()) {
                        return 'CCSender: ' . $_lang['ef_mail_error'] . $modx->mail->ErrorInfo;
                    }
                }

                // 既定は HTML
                $isHtml = ($sendAsText == 1 || strpos($sendAsText, 'autotext') !== false) ? false : true;
                if ($autotext && $emailIsValid) {
                    $autotext = formMerge($autotext, $fields);
                    evo()->loadExtension('MODxMailer');
                    $modx->mail->IsHTML($isHtml);
                    $modx->mail->From = ($autosender) ? $autosender : $from;
                    $modx->mail->FromName = ($autoSenderName) ? $autoSenderName : $fromname;
                    $modx->mail->Subject = $subject;
                    $modx->mail->Body = $autotext;
                    $modx->mail->AddAddress($fields['email']);
                    if (!$modx->mail->send()) {
                        return 'AutoText: ' . $_lang['ef_mail_error'] . $modx->mail->ErrorInfo;
                    }
                }
            }

            // 同じ内容の二重送信を防ぐ（ハッシュは前の処理で作ってある）
            if ($protectSubmit) {
                sessionv('*' . $formid . '_hash', $hash);
            }

            if ($submitLimit > 0) {
                sessionv('*' . $formid . '_limit', time());
            }

            if ($eFormOnMailSent) {
                if ($isDebug && !function_exists($eFormOnMailSent)) {
                    $fields['debug'] = ($fields['debug'] ?? '') . "eFormOnMailSent event: Could not find the function" . $eFormOnMailSent;
                } else {
                    if ($eFormOnMailSent($fields) === false) return;
                }
            }

            if ($isDebug) {
                $debugText .= sprintf(
                    '<strong>Mail Headers:</strong><br />From: %s (%s)<br />Reply-to:%s<br />To: %s<br />Subject: %s<br />CC: %s<br /> BCC:%s<br />',
                    $from,
                    $fromname,
                    $replyto,
                    $to,
                    $subject,
                    $cc,
                    $bcc
                );
                if ($isDebug > 1) {
                    $debugText .= "<br /><strong>Formats array:</strong><pre>" . var_export($formats, true) . '</pre>';
                    $debugText .= "<br /><strong>Fields array:</strong><pre>" . var_export($fields, true) . '</pre>';
                }
                $fields['debug'] = $debugText;
            }

            if ($gid == $modx->documentIdentifier) {
                if (!empty($thankyou)) {
                    if ($isDebug && strpos($thankyou, '[+debug+]') === false) {
                        $thankyou .= '[+debug+]';
                    }
                    if (isset($startupSource)) {
                        efRegisterStartupBlock($startupSource, true);
                    }
                    $htmlFields = efHtmlFields($fields);
                    // the disclaimer is merged with the posted values: rebuild it from the escaped ones
                    $htmlFields['disclaimer'] = $disclaimer ? formMerge($disclaimer, $htmlFields) : '';
                    if ($sendAsText) {
                        foreach ($formats as $key => $fmt) {
                            if (($fmt[2] ?? '') === 'html' && isset($htmlFields[$key])) {
                                $htmlFields[$key] = str_replace("\n", '<br />', $htmlFields[$key]);
                            }
                        }
                    }
                    $thankyou = formMerge($thankyou, $htmlFields);
                    return $thankyou;
                }
                return $_lang['ef_thankyou_message'];
            }

            $url = $modx->makeURL($gid);
            $modx->sendRedirect($url);
            return '';
        }
    } else {
        if ($isDebug) {
            $debugText .= "<br /><strong>eForm configuration:</strong><pre>" . var_export($params, true) . '</pre>';
            $fields['debug'] = $debugText;
        }

        // eform 属性は表示しない
        $regExpr = "#eform=([\"'])[^\\1]*?\\1#si";
        $tpl = preg_replace($regExpr, '', $tpl);
    }

    if ($vericode) {
        $fields['verimageurl'] = $modx->config('base_url') . 'index.php?get=captcha';
    }

    if ($sessionVars) {
        $sessionVars = (strpos($sessionVars, ',', 0)) ? explode(',', $sessionVars) : [$sessionVars];
        foreach ($sessionVars as $varName) {
            if (empty($varName)) {
                continue;
            }
            $varName = trim($varName);
            $sessionValue = sessionv($varName);
            if ($sessionValue !== null && $sessionValue !== '') {
                if (!isset($fields[$varName]) || !$postOverides) {
                    $fields[$varName] = $sessionValue;
                }
            }
        }
    }

    if ($eFormOnBeforeFormMerge) {
        if ($isDebug && !function_exists($eFormOnBeforeFormMerge)) {
            $fields['debug'] = ($fields['debug'] ?? '') . "eFormOnBeforeFormMerge event: Could not find the function " . $eFormOnBeforeFormMerge;
        } else {
            if ($eFormOnBeforeFormMerge($fields) === false) {
                return;
            }
        }
    }

    if ($isDebug && empty($fields['debug'])) {
        $fields['debug'] = $debugText;
    }
    if ($isDebug && strpos($tpl, '[+debug+]') === false) {
        $tpl .= '[+debug+]';
    }
    if (isset($startupSource)) {
        efRegisterStartupBlock($startupSource);
    }
    return formMerge($tpl, efHtmlFields($fields));
}

# メールアドレスとして正しいか。引用符つきのローカル部・カンマ・ドメインリテラルは認めない
function efIsValidEmail($value)
{
    return is_string($value)
        && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
        && strpbrk($value, "\",[]") === false;
}

# 送信元（Origin、無ければ Referer）のホストとポートが、サイトの URL と同じか
# スキームは見ない（TLS を手前で終える構成では site_url が http のことがある）
function efIsSameHostRequest()
{
    $site = parse_url(config('site_url', MODX_SITE_URL));
    if (empty($site['host'])) {
        return false;
    }
    // Origin が "null" など host を取れないときは Referer を見る
    $from = [];
    foreach ([serverv('HTTP_ORIGIN', ''), serverv('HTTP_REFERER', '')] as $source) {
        $from = is_string($source) && $source !== '' ? parse_url($source) : [];
        if (!empty($from['host'])) {
            break;
        }
    }
    if (empty($from['host'])) {
        return false;
    }
    return strtolower($from['host']) === strtolower($site['host'])
        && ($from['port'] ?? null) === ($site['port'] ?? null);
}

# Escape field values for pages shown in the browser (not for mail bodies).
# validationmessage / debug / disclaimer are HTML built by eForm itself.
function efHtmlFields($fields)
{
    global $formats;
    foreach ($fields as $key => $value) {
        if (in_array($key, ['validationmessage', 'debug', 'disclaimer'], true)) {
            continue;
        }
        $value = hsc($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, null, false);
        // keep the line breaks eForm itself has turned into <br /> (html fields only)
        if (($formats[$key][2] ?? '') === 'html') {
            $value = str_replace('&lt;br /&gt;', '<br />', $value);
        }
        $fields[$key] = $value;
    }
    return $fields;
}

# 選択肢が、送られた値（単一の値か配列）の中にあるか。エスケープの有無にかかわらず元の文字で比べる
function efIsChosen($choice, $posted)
{
    $decode = function ($v) {
        return is_string($v) ? html_entity_decode($v, ENT_QUOTES | ENT_HTML5) : $v;
    };
    $choice = $decode($choice);
    foreach ((array)$posted as $v) {
        if ($choice == $decode($v)) {
            return true;
        }
    }
    return false;
}

# Form Merge
function formMerge($docText, $docFields, $vClasses = '')
{
    global $formats;
    $lastitems = [];

    if (!$docText) {
        return '';
    }

    preg_match_all('~\[\+(.*?)\+\]~', $docText, $matches);
    foreach ($matches[1] as $iValue) {
        $name = $iValue;
        if (strpos($name, ':') !== false) {
            [$listName, $listValue] = explode(':', $name);
        } else {
            $listName = $name;
        }
        $value = isset($docFields[$listName]) ? $docFields[$listName] : '';

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
            $mark = ['listbox' => "selected='selected'", 'checkbox' => "checked='checked'", 'radio' => "checked='checked'"][$datatype] ?? '';
            if ($mark && isset($docFields[$listName]) && efIsChosen($listValue, $docFields[$listName])) {
                $docText = str_replace(sprintf('[+%s:%s+]', $listName, $listValue), $mark, $docText);
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
    $lastitems[count($lastitems)] = "class=\"\"";
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
    global $formats, $optionsName, $_lang, $debugText, $fields;
    global $efPostBack;

    $formats = []; // スニペットを複数回呼んでも値が残らないようにする
    $autoLists = []; // 選択肢から自動で作った #LIST の項目（利用者が書いたルールとは区別する）
    $labels = [];

    $regExpr = "#(<label[^>]*?>)(.*?)</label>#si";
    preg_match_all($regExpr, $tpl, $matches);
    foreach ($matches[1] as $key => $fld) {
        $attr = attr2array($fld);
        if (isset($attr['for'])) {
            $name = substr($attr['for'], 1, -1);
            $fields[$name . "_vClass"] = isset($attr['class']) ? substr($attr['class'], 1, -1) : '';
            $labels[$name] = strip_tags($matches[2][$key]);
            $attr['class'] = '"[+' . $name . '_vClass+]"';
            $newTag = buildTagPlaceholder('label', $attr, $name);
            $tpl = str_replace($fld, $newTag, $tpl);
        }
    }

    $regExpr = "#(<(input|select|textarea)[^>]*?>)#si";
    preg_match_all($regExpr, $tpl, $matches);

    $fieldTypes = $matches[2];
    $fieldTags = $matches[1];

    for ($i = 0, $iMax = count($fieldTypes); $i < $iMax; $i++) {
        $type = $fieldTypes[$i];

        $tagAttributes = attr2array($fieldTags[$i]) ?: [];
        // 属性の値は引用符つきで持っている。引用符と角かっこを除いて項目名にする
        if (!isset($tagAttributes['name'])) {
            continue;
        }
        $name = str_replace(["'", '"', '[', ']'], '', $tagAttributes['name']);

        // vericode は class のプレースホルダーだけ作り、書式は持たない
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

        if (isset($tagAttributes[$optionsName])) {
            // 検証ルールに ':' を書けるように 5 つまでに分ける
            $formats[$name] = explode(":", stripTagQuotes($tagAttributes[$optionsName]), 5);
            array_unshift($formats[$name], $name);
            unset($autoLists[$name]);
        } else {
            if (!isset($formats[$name])) $formats[$name] = [$name, '', '', 0];
        }
        // ラベルがあれば項目の説明にする
        if (empty($formats[$name][1])) {
            $formats[$name][1] = (isset($labels[$name])) ? $labels[$name] : $name;
        }

        unset($tagAttributes[$optionsName]);

        if (isset($tagAttributes['class'])) {
            $fields[$name . '_class'] = substr($tagAttributes['class'], 1, -1);
        }
        $tagAttributes['class'] = '"[+' . $name . '_class+]"';

        switch ($type) {
            case "select":
                $newTag = buildTagPlaceholder('select', $tagAttributes, $name);
                $tpl = str_replace($fieldTags[$i], $newTag, $tpl);
                if ($formats[$name]) $formats[$name][2] = 'listbox';

                // option を含む select 全体を取り出す（項目名の正規表現の記号はエスケープする）
                preg_match(
                    "#<select [^><]*?name=" . preg_quote($tagAttributes['name'], '#') . "[^>]*?" . ">(.*?)</select>#si",
                    $tpl,
                    $matches
                );

                if (!$matches) {
                    break;
                }
                $select = $matches[0];
                // replace each option tag (identical tags such as <option> are handled one by one)
                $validValues = [];
                $newSelect = preg_replace_callback(
                    '#<option(\s[^>]*)?>([^<]*)#si',
                    function ($m) use (&$validValues, &$fields, $name, $efPostBack) {
                        $attr = attr2array('<option' . ($m[1] ?? '') . '>') ?: [];
                        // the value the browser sends: the value attribute, or the option text (as in HTML),
                        // with character references decoded
                        $raw = isset($attr['value']) ? substr($attr['value'], 1, -1) : trim($m[2]);
                        $value = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        // write the attribute in one notation (&#38; -> &amp;) so that it matches
                        // the escaped field values when restoring the selection
                        $attr['value'] = '"' . hsc($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) . '"';
                        if (trim($value) != '') $validValues[] = $value;
                        //if no postback, retain any checked values
                        if (!$efPostBack && !empty($attr['selected'])) $fields[$name][] = $value;
                        return buildTagPlaceholder('option', $attr, $name) . $m[2];
                    },
                    $select
                );
                $tpl = str_replace($select, $newSelect, $tpl);
                if ($formats[$name] && !isset($formats[$name][5])) {
                    $formats[$name][4] = $_lang['ef_failed_default'];
                    $formats[$name][5] = "#LIST " . implode(",", str_replace(',', '&#44;', $validValues));
                }
                break;

            case "textarea":
                // maxlength は検証しない（$formats の 6 番目はチェックボックスの id 用）
                unset($tagAttributes['maxlength']);
                $newTag = buildTagPlaceholder($type, $tagAttributes, $name);
                $regExp = "#<textarea\\s[^>]*?name=" . preg_quote($tagAttributes["name"], '#') . "[^>]*?" . ">(.*?)</textarea>#si";
                if (!preg_match($regExp, $tpl, $matches)) {
                    break;
                }
                // 送信前は開始タグと終了タグの間の内容を残す
                $placeholderValue = ($efPostBack) ? "[+$name+]" : $matches[1];

                $tpl = str_replace($matches[0], $newTag . $placeholderValue . "</textarea>", $tpl);
                break;
            default: //all the rest, ie. "input"
                $fieldType = isset($tagAttributes['type']) ? strtolower(stripTagQuotes($tagAttributes['type'])) : 'text';
                // a checkbox / radio without a value attribute sends "on" (as in HTML)
                if (($fieldType == 'checkbox' || $fieldType == 'radio') && !isset($tagAttributes['value'])) {
                    $tagAttributes['value'] = '"on"';
                }
                $newTag = buildTagPlaceholder($type, $tagAttributes, $name);
                if ($formats[$name] && empty($formats[$name][2])) $formats[$name][2] = ($fieldType == 'text') ? "string" : $fieldType;
                // hidden・チェックボックス・ラジオの検証値は自動で決める
                if ($fieldType == 'hidden') {
                    if (!$isDebug) $formats[$name][1] = "[undefined]"; //do not want to disclose hidden field names
                    if (!isset($formats[$name][4])) $formats[$name][4] = $_lang['ef_tamper_attempt'];
                    if (!isset($formats[$name][5])) $formats[$name][5] = "#VALUE " . stripTagQuotes($tagAttributes['value'] ?? '');
                } elseif ($fieldType == 'checkbox' || $fieldType == 'radio') {
                    // 利用者が eform 属性にルールを書いた項目は、ルールもメッセージもそのまま使う
                    $choice = str_replace(',', '&#44;', stripTagQuotes($tagAttributes['value'] ?? ''));
                    if (!isset($formats[$name][5])) {
                        if (($formats[$name][4] ?? '') === '') {
                            $formats[$name][4] = $_lang['ef_failed_default'];
                        }
                        $formats[$name][5] = '#LIST ' . $choice;
                        $autoLists[$name] = true;
                    } elseif (isset($autoLists[$name])) {
                        $formats[$name][5] .= ',' . $choice;
                    }
                    // 送信前は checked の値を残す
                    if (!$efPostBack && !empty($tagAttributes['checked'])) $fields[$name][] = stripTagQuotes($tagAttributes['value'] ?? '');
                    if (isset($tagAttributes['id'])) {
                        $formats[$name][6] = (isset($formats[$name][6]) ? $formats[$name][6] . "," : "") . stripTagQuotes($tagAttributes['id']);
                    }
                } elseif (empty($fields[$name])) {
                    // テンプレートに書かれた既定値を残す
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
    // an input without type is a text input
    $type = (isset($attributes["type"])) ? strtolower(stripTagQuotes($attributes["type"])) : ($tag === 'input' ? 'text' : '');
    $quotedValue = (isset($attributes['value'])) ? $attributes['value'] : '""';
    $val = stripTagQuotes($quotedValue);
    // keep "no value attribute" as is (a submit button shows its default label)
    $valueAttr = isset($attributes['value']) ? ' value=' . $attributes['value'] : '';

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
                        '<input%s%s [+%s:%s+] />',
                        $t,
                        $valueAttr,
                        $name,
                        $val
                    );
                case 'submit':
                case 'button':
                case 'reset':
                case 'image':
                case 'file':
                case 'hidden': //leave as is - no placeholder
                    return "<input$t$valueAttr />";
                default: //text, password, email, tel, number, url, date, search ...
                    if ($name == 'vericode') {
                        return "<input$t value=\"\" />";
                    }
                    return "<input$t value=\"[+$name+]\" />";
            }
        case "file": //no placeholder!
        case "textarea": //placeholder needs to be added in calling code
            return "<$tag$t>";
        case "label":
            return "<$tag$t>";
        default:
            return "<input$t value=\"[+$name+]\" />";
    }
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
    $desc = $fld[1] ?? '';
    $fldMsg = trim($fld[4] ?? '');
    if (empty($fld[5])) return $output;

    [$cmd, $param] = explode(" ", trim($fld[5]), 2) + [1 => ''];
    $cmd = strtoupper(trim($cmd));
    if (substr($cmd, 0, 1) != '#') {
        $vMsg[count($vMsg)] = "$desc &raquo;" . $_lang['ef_error_validation_rule'];
        return false;
    }

    // 知らない #FILTER のルールは、値を変えず警告を 1 回だけ残す
    if ($cmd === '#FILTER') {
        $filterCmd = strtoupper(trim(explode(' ', trim($param), 2)[0]));
        if (!in_array($filterCmd, ['#REGEX', '#LIST'], true)) {
            evo()->logEvent(0, 2, "eForm: 知らない #FILTER のルールです: {$filterCmd}", 'eForm', [
                'field' => $fld[0] ?? '',
                'rule' => $fld[5],
            ]);
            return $value;
        }
    }

    $valueIsArray = is_array($value);
    $valList = $valueIsArray ? $value : [$value];
    $errMsg = '';

    foreach ($valList as $i => $iValue) {
        $value = $iValue;
        switch ($cmd) {
            // hidden の項目の検証に内部で使う
            case "#VALUE":
                if ($value != $param) $errMsg = $_lang['ef_failed_default'];
                break;
            case "#RANGE":
                if (!isset($vlist)) $vlist = explode(',', strtolower(trim($param)));
                foreach ($vlist as $p) {
                    if (strpos($p, '~') > 0) {
                        $range = explode('~', $p);
                    } else {
                        $range = [$p, $p];
                    }

                    if ($isDebug && (!is_numeric($range[0]) || !is_numeric($range[1])))
                        $modx->messageQuit('Error in validating form field!', '', false, E_USER_WARNING, __FILE__, '', '#RANGE rule contains non-numeric values: ' . $fld[5], __LINE__);
                    sort($range);
                    if ($value >= $range[0] && $value <= $range[1]) break 2;
                }
                $errMsg = $_lang['ef_failed_range'];
                break;

            case "#LIST":        // カンマ区切りの値（大文字小文字は区別しない）
                // ファイルは拡張子で調べる。$param は前後の空白も値なので trim しない
                if ($fld[2] == 'file') $value = substr($value, strrpos($value, '.') + 1);
                if (!isset($vlist)) {
                    $vlist = explode(',', strtolower($param));
                    foreach ($vlist as $k => $v) {
                        $vlist[$k] = str_replace('&#44;', ',', $v);
                    }
                } // 値に含まれるカンマ（&#44;）は戻す

                if ($isDebug && count($vlist) == 1 && empty($vlist[0])) {
                    $modx->messageQuit('Error in validating form field!', '', false, E_USER_WARNING, __FILE__, '', '#LIST rule declared but no list values supplied: ' . $fld[5], __LINE__);
                } elseif (!in_array(strtolower($value), $vlist))
                    $errMsg = ($fld[2] == 'file') ? $_lang["ef_failed_upload"] : $_lang['ef_failed_list'];
                break;

            case "#SELECT":    // DB から取った値の一覧で検証する
                if (!isset($vlist)) {
                    $rt = [];
                    $param = str_replace('{DBASE}', db()->get('dbase'), $param);
                    $param = str_replace('{PREFIX}', db()->get('table_prefix'), $param);
                    if (preg_match("/{([^}]*?)\}/", $param, $matches)) {
                        $tmp = db()->escape(formMerge('[+' . $matches[1] . '+]', $fields));
                        $param = str_replace('{' . $matches[1] . '}', $tmp, $param);
                    }
                    // SQL が失敗したときは値を確かめられないので、画面は止めずに検証を通さない
                    $rs = db()->query("SELECT $param;", false);
                    if ($rs === false) {
                        $errMsg = $_lang['ef_failed_list'];
                        if ($isDebug) {
                            $debugText .= "'<strong>$fld[1]</strong>' SQL error: " . htmlspecialchars(db()->getLastError()) . '<br />';
                        }
                        break;
                    }
                    // 1 列目の値だけを使う（大文字小文字は区別しない）
                    while ($v = db()->getValue($rs)) $vlist[] = strtolower($v);
                }
                if (!is_array($vlist)) {
                    // 結果が無いときは検証を通す（デバッグ時だけ知らせる）
                    $debugText .= ($isDebug) ? "'<strong>$fld[1]</strong>' " . $_lang['ef_sql_no_result'] . '<br />' : '';
                } elseif (!in_array(strtolower($value), $vlist)) {
                    $errMsg = $_lang['ef_failed_list'];
                }
                break;

            case "#FUNCTION":
                $tmp = $cmd; // 関数が $cmd を書き換えても影響しないように退避する
                // バインディングが無効のときは関数を呼ばず、検証は通す
                if (evo()->config('enable_bindings') != 1) {
                    if ($isDebug) {
                        $debugText .= "<strong>$fld[1]</strong>: " . $_lang['ef_function_bindings_disabled'] . " $param";
                    }
                } elseif (function_exists($param)) {
                    if (!@$param($value)) {
                        $errMsg = $_lang['ef_failed_eval'];
                    }
                } elseif ($isDebug) {
                    $debugText .= "<strong>$fld[1]</strong>: " . $_lang['ef_no_function'] . " $param";
                }
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
                evo()->logEvent(0, 2, "eForm: 知らない検証ルールです: {$cmd}", 'eForm', [
                    'field' => $fld[0] ?? '',
                    'rule' => $fld[5],
                ]);
                $errMsg = $_lang['ef_error_validation_rule'];
        }
        if ($isDebug) {
            $debugText .= "'<strong>$fld[1]</strong>' ";
            $debugText .= (empty($errMsg)) ? 'passed' : '<span style="color:red;">Failed</span>';
            $debugText .= " using rule: $cmd " . $param . ', (input=' . htmlspecialchars($iValue) . ")<br />\n";
        }
        if (!empty($errMsg)) {
            $errMsg = ($fldMsg) ? "$desc &raquo; $fldMsg" : "$desc &raquo; $errMsg";
            $vMsg[count($vMsg)] = $errMsg;
            $output = false;
            break;
        }
    }

    // #FILTER は変換後の値を返す
    $valList = $valueIsArray ? $valList : implode('', $valList);

    return ($cmd == "#FILTER") ? $valList : $output;
}

function filterEformValue($value, $param)
{
    [$cmd, $param] = explode(" ", trim($param), 2);
    $cmd = trim($cmd);

    switch (strtoupper($cmd)) {
        case "#REGEX":
            [$src, $dst] = explode("||", $param, 2);
            $replaced = preg_replace($src, $dst, $value);
            if ($replaced !== null) {
                $value = $replaced;
            }
            break;

        case "#LIST":
            $param = explode("||", $param, 2);
            $src = strpos($param[0], ',') ? explode(',', $param[0]) : $param[0];
            $dst = strpos($param[1], ',') ? explode(',', $param[1]) : $param[1];
            $value = str_replace($src, $dst, $value);
            break;

    }
    return $value;
}

function efLoadTemplate($key)
{
    global $modx;

    $tpl = '';
    if (strpos($key, '@FILE') === 0) {
        $path = evo()->findBindFile(substr($key, 6));
        if (!$path) {
            $tpl = false;
        } elseif (evo()->getExtention($path) === '.php') {
            $tpl = 'Could not retrieve PHP file.';
        } else {
            $tpl = file_get_contents($path);
        }
    } elseif (strpos($key, '@CODE') === 0) {
        $tpl = substr($key, 6);
    } elseif (is_numeric($key)) {
        // 未公開の文書を先に調べる
        $tpl = ($doc = $modx->getDocument($key, 'content', 0)) ? $doc['content'] : false;
        if (!$tpl)
            $tpl = ($doc = $modx->getDocument($key, 'content', 1)) ? $doc['content'] : false;

    } elseif ($key) {
        $tpl = ($doc = $modx->getChunk($key)) ? $doc : false;
        if (!$tpl) $tpl = ($doc = $modx->runSnippet($key)) ? $doc : false;
    }
    return $tpl;
}


# CSS・JavaScript を登録する
function efRegisterStartupBlock($src_array, $noScript = false)
{
    global $modx;

    if (!$src_array) {
        return;
    }

    foreach ($src_array as $item) {
        [$src, $type] = $item;
        if ($type === 'javascript' && $noScript) {
            continue;
        }
        if ($tmp = efLoadTemplate($src)) {
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

    }
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
