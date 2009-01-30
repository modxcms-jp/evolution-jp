<?php
/**
* snippets/eform/japanese-utf8.inc.php
* 日本語 language file for eForm
*/

//-- JAPANESE LANGUAGE FILE ENCODED IN UTF-8
include_once(dirname(__FILE__).'/english.inc.php'); // fall back to English defaults if needed
/* Set locale to Japanese */
setlocale (LC_ALL, "ja_JP.UTF-8");

$_lang["ef_thankyou_message"] = "<h3>ありがとうございます。</h3><p>入力された情報を受け付けました。</p>";
$_lang["ef_no_doc"] = "テンプレートのドキュメントまたはチャンクが見つかりません。 id=";
//$_lang["ef_no_chunk"] = ""; //deprecated
//$_lang["ef_validation_message"] = "<strong>Some errors were detected in your form:</strong><br />";
$_lang["ef_validation_message"] = "<div class=\"errors\"><strong>いくつかのエラーが見つかりました</strong><br />[+ef_wrapper+]</div>";
$_lang['ef_rule_passed'] = 'Passed using rule [+rule+] (input="[+input+]").';
$_lang['ef_rule_failed'] = '<span style="color:red;">Failed</span> using rule [+rule+] (input="[+input+]")';
$_lang["ef_required_message"] = "{fields}は必須項目です<br />";
$_lang['ef_error_list_rule'] = 'Error in validating form field! #LIST rule declared but no list values Found: ';
$_lang["ef_invalid_number"] = "は有効な数字ではありません";
$_lang["ef_invalid_date"] = "は有効な日付形式ではありません";
$_lang["ef_invalid_email"] = "は有効なメールアドレス形式ではありません";
$_lang["ef_upload_exceeded"] = "はアップロードの上限を超えています.";
$_lang["ef_upload_error"] = ": error in uploading file."; //NEW
$_lang["ef_failed_default"] = "無効な値です";
$_lang["ef_failed_vericode"] = "有効なコードではありません";
$_lang["ef_failed_range"] = "有効範囲外です";
$_lang["ef_failed_list"] = "有効なリスト項目ではありません";
$_lang["ef_failed_eval"] = "無効な値です";
$_lang["ef_failed_ereg"] = "無効な値です";
$_lang["ef_failed_upload"] = "ファイルタイプが無効です";
$_lang["ef_error_validation_rule"] = "ルールが正しくありません";
$_lang["ef_error_filter_rule"] = "Text filter not recognized";
$_lang["ef_tamper_attempt"] = "不正な変更の試みを発見しました!";
$_lang["ef_error_formid"] = "フォームIDまたはフォーム名が無効です";
$_lang["ef_debug_info"] = "デバッグ情報: ";
$_lang["ef_is_own_id"] = "<span class=\"ef-form-error\">Form template set to id of page containing snippet call! You can not have the form in the same document as the snippet call.</span> id=";
$_lang["ef_sql_no_result"] = " silently passed validation. <span style=\"color:red;\"> SQL returned no result!</span> ";
$_lang['ef_regex_error'] = 'error in regular expression ';
$_lang['ef_debug_warning'] = '<p style="color:red;"><span style="font-size:1.5em;font-weight:bold;">WARNING - DEBUGGING IS ON</span> <br />Make sure you turn debugging off before making this form live!</p>';
$_lang['ef_mail_abuse_subject'] = 'Potential email form abuse detected for form id';
$_lang['ef_mail_abuse_message'] = '<p>A form on your website may have been the subject of an email injection attempt. The details of the posted values are printed below. Suspected text has been embedded in \[..]\ tags.  </p>';
$_lang['ef_mail_abuse_error'] = '<strong>Invalid or insecure entries were detected in your form</strong>.';
$_lang['ef_eval_deprecated'] = 'The #EVAL rule is deprecated and may not work in future versions. Use #FUNCTION instead.';
$_lang['ef_multiple_submit'] = "<p>This form was already submitted succesfully. There is no need to submit your information multiple times.</p>";
$_lang['ef_submit_time_limit'] = "<p>This form was already submitted succesfully. Re-submission of the form is disabled for ".($submitLimit/60)." minutes.</p>";
$_lang['ef_version_error'] = "<strong>WARNING!</strong> The version of the eForm snippet (version:&nbsp;$version) is different from the included eForm file (version:&nbsp;$fileVersion). Please make sure you use the same version for both.";
$_lang['ef_thousands_separator'] = ''; //leave empty to use (php) locale, only needed if you want to overide locale setting!
$_lang['ef_date_format'] = '%Y/%m/%d - %H:%M:%S';
$_lang['ef_mail_error'] = 'Mailer was unable to send mail';
?>
