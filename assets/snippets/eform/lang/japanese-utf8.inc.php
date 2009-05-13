<?php
/**
* snippets/eform/japanese-utf8.inc.php
* 日本語 language file for eForm
*/

//-- JAPANESE LANGUAGE FILE ENCODED IN UTF-8
include_once(dirname(__FILE__).'/english.inc.php'); // fall back to English defaults if needed
/* Set locale to Japanese */
setlocale (LC_ALL, 'ja_JP');

$_lang["ef_thankyou_message"] = "<h3>ありがとうございます。</h3><p>入力された情報は無事送信されました。</p>";
$_lang["ef_no_doc"] = "テンプレートのドキュメントまたはチャンクが見つかりません。 id=";
$_lang["ef_validation_message"] = "<div class=\"errors\"><strong>いくつかのエラーが見つかりました</strong><br />[+ef_wrapper+]</div>";
$_lang["ef_required_message"] = "{fields}は、必須項目です<br />";
$_lang["ef_invalid_number"] = "は、有効な数字ではありません";
$_lang["ef_invalid_date"] = "は、有効な日付形式ではありません";
$_lang["ef_invalid_email"] = "は、有効なメールアドレス形式ではありません";
$_lang["ef_upload_exceeded"] = "は、アップロードの上限を超えています.";
$_lang["ef_failed_default"] = "無効な値です";
$_lang["ef_failed_vericode"] = "有効なコードではありません";
$_lang["ef_failed_range"] = "有効範囲外です";
$_lang["ef_failed_list"] = "有効なリスト項目ではありません";
$_lang["ef_failed_eval"] = "有効な値ではありません";
$_lang["ef_failed_ereg"] = "有効な値ではありません";
$_lang["ef_failed_upload"] = "有効なファイルタイプではありません";
$_lang["ef_error_validation_rule"] = "ルールが正しくありません";
$_lang["ef_tamper_attempt"] = "不正な変更の試みを発見しました!";
$_lang["ef_error_formid"] = "フォームIDまたはフォーム名が無効です";
$_lang["ef_debug_info"] = "デバッグ情報: ";
$_lang["ef_is_own_id"] = "<span class=\"ef-form-error\">フォームテンプレートとして、スニペットコールを含むページのIDが設定されています！スニペットコールと同じドキュメントにフォームを設置することはできません。</span> ID=";
$_lang["ef_sql_no_result"] = " 検証を通過しました。<span style=\"color:red;\">SQLは結果を返しませんでした！</span> ";
$_lang['ef_regex_error'] = '正規表現のエラー ';
$_lang['ef_debug_warning'] = '<p style="color:red;"><span style="font-size:1.5em;font-weight:bold;">警告 - デバッギングが有効</span><br />このフォームを公開する前にデバッギングを無効にしてください！</p>';
$_lang['ef_mail_abuse_subject'] = 'メールフォーム悪用の可能性を検出';
$_lang['ef_mail_abuse_message'] = '<p>メールインジェクション攻撃の可能性がある題名です。送信データの詳細は以下に表示されます。疑わしいテキストは\[..]\タグの中に埋め込まれています。</p> ';
$_lang['ef_mail_abuse_error'] = '<strong>フォームから不正または安全でない入力が検出されました。</strong>.';
$_lang['ef_eval_deprecated'] = '#EVALルールは将来のバージョンで動作しなくなる可能性があります。代わりに#FUNCTIONを使用してください。';
?>
