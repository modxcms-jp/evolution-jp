/**
 * FormSignup
 * 
 * ウェブサインアップフォーム(WebSignupスニペット用)
 * 
 * @category	chunk
 * @version 	1.0
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal 	@modx_category Login
 * @internal    @overwrite false
 * @internal    @installset base
 */
<!-- #declare:separator <hr> --> 
<!-- login form section-->
<form id="websignupfrm" method="post" name="websignupfrm" action="[+action+]">
    <fieldset>
        <h3>ユーザー情報</h3>
        <p> * : 必須</p>
		<label for="su_username">ユーザーID:* <input type="text" name="username" id="su_username" class="inputBox" size="20" maxlength="30" value="[+username+]" /></label>
        <label for="fullname">フルネーム: <input type="text" name="fullname" id="fullname" class="inputBox" size="20" maxlength="100" value="[+fullname+]" /></label>
		<label for="email">メールアドレス:* <input type="text" name="email" id="email" class="inputBox" size="20" value="[+email+]" /></label>
	</fieldset>
	
	<fieldset>
	    <h3>パスワード</h3>
	    <label for="su_password">パスワード:* <input type="password" name="password" id="su_password" class="inputBox" size="20" /></label>
	    <label for="confirmpassword">パスワード（確認）:* <input type="password" name="confirmpassword" id="confirmpassword" class="inputBox" size="20" /></label>
	</fieldset>
	
	<fieldset>
		<h3>オプションプロフィール</h3>
		<label for="country">Country:</label>
		<select size="1" name="country" id="country">
			<option value="" selected="selected">&nbsp;</option>
			<option value="107">Japan</option>
			<option value="223">United States</option>
			<option value="224">United States Minor Outlying Islands</option>
			</select>
        </fieldset>
        
        <fieldset>
            <h3>画像認証</h3>
            <p>見えている文字を入力してください。読みづらい場合は、画像をクリックするとコードを変えることができます。</p>
            <p><a href="[+action+]"><img align="top" src="manager/includes/veriword.php" width="148" height="60" alt="If you have trouble reading the code, click on the code itself to generate a new random code." style="border: 1px solid #039" /></a></p>
        <label>認証コード:* 
            <input type="text" name="formcode" class="inputBox" size="20" /></label>
            </fieldset>
        
        <fieldset>
            <input type="submit" value="登録" name="cmdwebsignup" />
	</fieldset>
</form>

<script language="javascript" type="text/javascript"> 
	var id = "[+country+]";
	var f = document.websignupfrm;
	var i = parseInt(id);	
	if (!isNaN(i)) f.country.options[i].selected = true;
</script>
<hr>
<!-- notification section -->
<p class="message">登録完了！<br />アカウントは正しく作成されました。 登録された情報をあなたのメールアドレスに送信しました。</p>
