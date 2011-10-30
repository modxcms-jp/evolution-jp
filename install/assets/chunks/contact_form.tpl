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
<p class="error">[+validationmessage+]</p>

<form method="post" action="[~[*id*]~]" id="EmailForm" name="EmailForm">
	<fieldset>
		<h3>お問い合わせフォーム(eFormの機能)</h3>

		<input name="formid" type="hidden" value="ContactForm" />

		<label for="cfName">お名前
		<input name="name" id="cfName" class="text" type="text" eform="Your Name::1:" /> </label>

		<label for="cfEmail">メールアドレス
		<input name="email" id="cfEmail" class="text" type="text" eform="Email Address:email:1" /> </label>

		<label for="cfRegarding">種別</label>
		<select name="subject" id="cfRegarding" eform="Form Subject::1">
			<option value="一般的な質問">一般的な質問</option>
			<option value="取材申し込み">取材申し込み</option>
			<option value="業務提携のご相談">業務提携のご相談</option>
		</select>

		<label for="cfMessage">メッセージ 
		<textarea name="message" id="cfMessage" rows="4" cols="20" eform="Message:textarea:1"></textarea>
		</label>

		<input type="submit" name="contact" id="cfContact" class="button" value="送信する" />

	</fieldset>

</form>
