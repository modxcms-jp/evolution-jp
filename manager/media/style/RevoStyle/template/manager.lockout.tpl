<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<title>MODx Content Manager</title>
<meta http-equiv="content-type" content="text/html; charset=[+modx_charset+]" />
<meta name="robots" content="noindex, nofollow" />
    <style type="text/css">
    /* Neutralize styles, fonts and viewport:
    ---------------------------------------------------------------- */
    html, body, form, fieldset {margin: 0;padding: 0;border:none;}
    fieldset {margin-top:20px;}
    html {background-color:#f4f4f4;
	font-size: 100.01%; /* avoids obscure font-size bug */
	line-height: 1.5; /* http://meyerweb.com/eric/thoughts/2006/02/08/unitless-line-heights/ */
	font-family: Arial, Helvetica, sans-serif;
	height: 100.01%;color: #333;}
    body {font-size: 75%; /* 12px 62.5% for 10px*/margin-bottom: 1px; /* avoid jumping scrollbars */background: #F4F4F4;}
    img, a img {border: 0 !important;text-decoration: none;padding: 0;margin: 0;}
    input {font:inherit;}
    p {margin: 0 0 .5em; /* Reset vertical margins on selected elements */padding: 0;}
    li, dd {margin-left: 1em; /* Left margin only where needed */}

    .warning{color: #821517;font-weight: bold;}
    .success{color: #090;font-weight: bold;}
    a, a:active, a:visited, a:link {color: #333;text-decoration: underline;}
    a:hover {color: #777;}
    input, .inputBox {padding: 1px;}
    form { border: 1px solid #fff; }
    #logo { margin-left: -7px }
    .header {padding: 5px 3px 5px 18px;font-weight: bold;color: #000;background: #EAECEE url(media/style/[+theme+]/images/misc/fade.gif) repeat-x top;border-bottom:1px solid #e0e0e0;}
    .body {padding: 20px 20px 20px;display: block;background: #fff;background-image:url(media/style/[+theme+]/images/misc/tabareabg.gif);background-repeat:repeat-x;}
    #mx_loginbox {width: 460px;margin: 30px auto 0;
                                 box-shadow: 0 0 10px #aaa;
                             -moz-box-shadow: 0 0 10px #aaa;
                             -webkit-box-shadow: 0 0 15px #ccc;
}
    input {margin: 0 0 10px 0;}
    input.login {float: right;clear: right;margin-right: 0px;padding:5px 8px;cursor: hand; cursor: pointer;background: #EAECEE url(media/style/[+theme+]/images/misc/fade.gif) repeat-x top;border:1px solid #ccc;}
    .loginLicense {width: 460px;color: #B2B2B2;margin: 0.5em auto;font-size: 90%;padding-left: 20px;}
    .loginLicense a {color: #B2B2B2;}
    .notice {width: 100%;padding: 5px;border: 1px solid #eee;background-color: #F4F4F4;color: #707070;}
	.loginMessage {font-size:12px;color: #999;padding-top: 10px;}
	#loginfrm {background-color:#fff;padding:20px;}
    </style>

<script type="text/javascript">
    function doLogout() {
        top.location = '[+logouturl+]';
    }

    function gotoHome() {
        top.location = '[+homeurl+]';
    }
</script>

<script type="text/javascript">
/* <![CDATA[ */
    if (top.frames.length!=0) {
        top.location=self.document.location;
    }
/* ]]> */
</script>

</head>
<body id="login">

<!-- start the login box -->
<div id="mx_loginbox">
<form method="post" name="loginfrm" id="loginfrm" action="processors/login.processor.php">

    <!-- the logo -->
    <div id="mx_logobox">
        <img src='media/style/[+theme+]/images/misc/login-logo.png' alt='[+logo_slogan+]' />
    </div>
    <!-- end #mx_logobox -->

    <div id="mx_loginArea">
        <div class="loginMessage">[+manager_lockout_message+]</div>

        <fieldset id="submitSet">
            <input type="button" id="submitButton" value="[+home+]" onclick="return gotoHome();"  />&nbsp;
            <input type="button" id="submitButton" value="[+logout+]" onclick="return doLogout();" />
        </fieldset>
    </div>

    <br style="clear:both;height: 1px"/>

</form>
</div>
<!-- close #mx_loginbox -->


<!-- convert this to a language include -->
<p class="loginLicense">
    <strong>MODX</strong>&trade; is licensed under the GPL license. &copy; 2005-2012 <a href="http://modx.com/" target="_blank">MODX</a>.
</p>


</body>
</html>
