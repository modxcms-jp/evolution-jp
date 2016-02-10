<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ja" xml:lang="ja">
<head>
    <title>MODX CMF Manager Login</title>
    <meta http-equiv="content-type" content="text/html; charset=[+modx_charset+]" />
    <meta name="robots" content="noindex, nofollow" />
    <style type="text/css">
    /* Neutralize styles, fonts and viewport:
    ---------------------------------------------------------------- */
    html, body, form, fieldset {margin: 0;padding: 0;}
    html {
	font-size: 100.01%; /* avoids obscure font-size bug */
	line-height: 1.5; /* http://meyerweb.com/eric/thoughts/2006/02/08/unitless-line-heights/ */
	font-family: Arial,"Helvetica Neue",Helvetica,Meiryo,"Hiragino Kaku Gothic Pro","MS PGothic",sans-serif;
	height: 100.01%;color: #333;}
    body {height:auto;font-size: 75%; /* 12px 62.5% for 10px*/margin-bottom: 1px;background-color:#EEEEEE;}
    img, a img {border: 0 !important;text-decoration: none;padding: 0;margin: 0;}
    input {font:inherit;}
    h1, h2, h3, h4, h5, h6, p, pre,
    blockquote, ul, ol, dl, address {margin: 0 0 .5em; /* Reset vertical margins on selected elements */padding: 0;}
    li, dd, blockquote {margin-left: 1em; /* Left margin only where needed */}

    /* Headers and Paragraphs:
    ---------------------------------------------------------------- */
    h1, h2, h3, h4, h5, h6 {font-weight: normal;}
    h1 { font-size: 218%; }
    .warning{color: #821517;font-weight: bold;}
    .success{color: #090;font-weight: bold;}
    a, a:active, a:visited, a:link {color: #333;text-decoration: underline;}
    a:hover {color: #777;}
    input, .inputBox {padding: 1px;}
    form { border: 1px solid #fff; }
    #logo { margin-left: -7px }
    .header {padding: 5px 3px 5px 18px;font-weight: bold;color: #000;background-color: #EAECEE;border-bottom:1px solid #e0e0e0;}
    .body {padding: 20px 20px 20px;display: block;background: #fff;
    box-shadow: inset 0px 5px 10px 0px rgba(70, 70, 70, 0.1);}
    #mx_loginbox {width: 460px;margin: 30px auto 0;
                                 box-shadow: 0 0 10px #aaa;
                             -moz-box-shadow: 0 0 10px #aaa;
                             -webkit-box-shadow: 0 0 15px #ccc;
}
    img.loginCaptcha {border: 1px solid #039;width: 148px;height: 60px;}
    label {display: block;font-weight: bold;}
    input {margin: 0 0 10px 0;}
    input.checkbox {float: left;clear: left;margin-right: 3px;}
    form input.text,input#FMP-email {margin-bottom:8px;line-height:1;ime-mode:inactive;letter-spacing:1px;font-family: Verdana; width: 400px;background-color: #fff;border:1px solid #ccc;padding:3px;}
    input.login {float: right;clear: right;margin-right: 0px;padding:5px 8px;cursor: hand; cursor: pointer;background-color: #EAECEE;border:1px solid #ccc;}
    .loginLicense {width: 460px;color: #B2B2B2;margin: 0.5em auto;font-size: 90%;padding-left: 20px;}
    .loginLicense a {color: #B2B2B2;}
    .notice {width: 100%;padding: 5px;border: 1px solid #eee;background-color: #F4F4F4;color: #707070;}
	.loginMessage {font-size:12px;color: #999;padding-top: 10px;}
	input#FMP-email {width:300px;}
	label#FMP-email_label {padding-left:0;}
    </style>

    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <link rel="stylesheet" href="media/script/jquery/jquery.alerts.css" type="text/css" />
    <script src="media/script/jquery/jquery.alerts.js" type="text/javascript"></script>

    <script type="text/javascript">
    /* <![CDATA[ */
        var $j = jQuery.noConflict();
        if (top.frames.length!=0) {
            top.location=self.document.location;
        }
        
        jQuery(function() {
            jQuery('#submitButton').click(function(e) {
				var $form = jQuery('#loginfrm');
				var username = jQuery('#username').val();
				var password = jQuery('#password').val();
				var rememberme = jQuery('#rememberme').val();
				var captcha_code = jQuery('input[name="captcha_code"]').val();
				params = {"username":username,"password":password,"rememberme":rememberme,"ajax":'1',"captcha_code":captcha_code};
				jQuery.post('processors/login.processor.php',params,function(response){
					var header = response.substr(0,9);
					if (header.toLowerCase()=='location:') top.location = response.substr(10);
					else {
					var cimg = document.getElementById('captcha_image');
					if (cimg) {
					cimg.src = '../captcha.php';
					}
					jAlert(response);
					}
				
				});
            });  
			
			// Initial focus
			if (jQuery('#username').val() != '') jQuery('#password').focus();
			else                                 jQuery('#username').focus();
			
        });
    /* ]]> */
    </script>
</head>
<body id="login">
<div id="mx_loginbox">
    <form method="post" name="loginfrm" id="loginfrm" action="processors/login.processor.php">
    <!-- anything to output before the login box via a plugin? -->
    [+OnManagerLoginFormPrerender+]
        <div class="header"><a href="../">[+site_name+]</a></div>
        <div class="body">
			<img src="media/style/common/images/misc/login-logo.png" alt="[+site_name+]" id="logo" />
            <p class="loginMessage">[+login_message+]</p>
            <label for="username">[+username+] </label>
            <input type="text" class="text" name="username" id="username" tabindex="1" value="[+uid+]" />
            <label for="password">[+password+] </label>
            <input type="password" class="text" name="password" id="password" tabindex="2" value="" />
            [+login_captcha_message+]
            [+captcha_image+]
            [+captcha_input+]
            <input type="checkbox" id="rememberme" name="rememberme" tabindex="4" value="1" class="checkbox" [+remember_me+] /><label for="rememberme" style="cursor:pointer;display:inline;">[+remember_username+]</label>
            <input type="submit" class="login" onclick="return false;" id="submitButton" value="[+login_button+]" />
            <!-- anything to output before the login box via a plugin ... like the forgot password link? -->
            [+OnManagerLoginFormRender+]
        </div>
    </form>
</div>
<!-- close #mx_loginbox -->

<!-- convert this to a language include -->
<p class="loginLicense">
&copy; 2005-[[$_SERVER['REQUEST_TIME']:date(Y)]] by the <a href="http://modx.com/" target="_blank">MODX</a>. <strong>MODX</strong>&trade; is licensed under the GPL.
</p>
</body>
</html>