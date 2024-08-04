<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
    <title>MODX CMF Manager Login</title>
    <meta http-equiv="content-type" content="text/html; charset=[+modx_charset+]"/>
    <meta name="robots" content="noindex, nofollow"/>
</head>
<body style="padding:3em;">
<form method="post" action="processors/login.processor.php">
    [+OnManagerLoginFormPrerender+]
    Login Name<br/>
    <input type="text" name="username" value="[+uid+]"/><br/>
    Password<br/>
    <input type="password" name="password" value=""/><br/>
    <input type="checkbox" id="rememberme" name="rememberme" value="1" class="checkbox" [+remember_me+]/>
    [+remember_username+]
    <div>[+captcha_image+]</div>
    [+captcha_input+]
    <input type="submit" value="Login"/>
    [+OnManagerLoginFormRender+]
</form>
<p>
    &copy; 2005-[+year+] by the <a href="http://modx.com/" target="_blank">MODX</a>. <strong>MODX</strong>&trade; is
    licensed under the GPL.
</p>
</body>
</html>