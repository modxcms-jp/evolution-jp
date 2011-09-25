/**
 * ContactForm
 * 
 * 問い合わせフォーム(eform用)
 * 
 * @category	chunk
 * @version 	1.0
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal 	@modx_category Demo Content
 * @internal    @overwrite false
 * @internal    @installset base, sample
 */
<style type="text/css">
	label          {display:block;}
	label input, select,
	label textarea {display:block;margin-top:8px;margin-bottom:15px;}
	textarea       {width:500px;height:150px;}
	div.errors     {color:#f00;}
</style>
<p class="error">[+validationmessage+]</p>
<form method="post" action="[~[*id*]~]">
<!-- formパーツのname値でemail/subject/formidの3つだけが特別な値。eform属性は入力必須項目などを設定します -->
	<fieldset>
		<h3>お問い合わせフォーム(eFormの機能)</h3>
		<input name="formid" type="hidden" value="ContactForm" />
		<label>お名前
		<input name="お名前" class="text" type="text" /> </label>
		<label>メールアドレス
		<input name="email" class="text" type="text" eform="メールアドレス:email:1" /> </label>
		<label>種別</label>
		<select name="subject">
			<option value="一般的な質問">一般的な質問</option>
			<option value="取材申し込み">取材申し込み</option>
			<option value="業務提携のご相談">業務提携のご相談</option>
		</select>
		<label>メッセージ 
		<textarea name="問い合わせ内容" rows="4" cols="20"></textarea>
		</label>
		<input type="submit" name="contact" class="button" value="送信する" />
	</fieldset>
</form>
