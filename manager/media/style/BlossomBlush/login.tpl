<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="[+modx_charset+]">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>MODX CMF Manager Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: "Hiragino Sans", "Noto Sans JP", "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background: radial-gradient(circle at 20% 20%, rgba(255, 201, 225, 0.55), transparent 30%),
                        radial-gradient(circle at 80% 15%, rgba(214, 173, 255, 0.50), transparent 26%),
                        radial-gradient(circle at 78% 78%, rgba(255, 210, 221, 0.6), transparent 30%),
                        linear-gradient(135deg, #fff7fd 0%, #ffe8f3 45%, #f1ddff 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 36px 18px;
            color: #4a3044;
            position: relative;
            overflow: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: absolute;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.35;
            z-index: 0;
        }

        body::before {
            background: radial-gradient(circle, #ffb6d9 0%, #ffcfe9 40%, transparent 70%);
            top: -140px;
            left: -120px;
        }

        body::after {
            background: radial-gradient(circle, #c9b6ff 0%, #d8c9ff 40%, transparent 72%);
            bottom: -150px;
            right: -80px;
        }

        #mx_loginbox {
            position: relative;
            z-index: 2;
            width: 480px;
            max-width: 100%;
            margin-bottom: 14px;
        }

        form {
            background: rgba(255, 255, 255, 0.72);
            border-radius: 24px;
            border: 1px solid rgba(255, 183, 214, 0.6);
            box-shadow: 0 18px 42px rgba(228, 158, 190, 0.25), 0 6px 18px rgba(82, 48, 81, 0.08);
            overflow: hidden;
            position: relative;
        }

        form::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 194, 220, 0.35), rgba(206, 169, 255, 0.25));
            opacity: 0.85;
            pointer-events: none;
        }

        form::after {
            content: "";
            position: absolute;
            inset: 1px;
            border-radius: 23px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.92));
            z-index: 0;
        }

        .header {
            position: relative;
            padding: 22px 26px 16px;
            z-index: 1;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #4a3044;
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 0.2px;
        }

        .brand span.icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 14px;
            background: linear-gradient(135deg, #ffb6d9 0%, #d7b8ff 100%);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6), 0 8px 14px rgba(255, 182, 217, 0.45);
            font-size: 16px;
        }

        .body {
            position: relative;
            padding: 0 26px 28px;
            z-index: 1;
        }

        .loginMessage {
            font-size: 14px;
            color: #5a3c55;
            background: rgba(255, 236, 245, 0.85);
            border: 1px solid rgba(255, 183, 214, 0.6);
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 16px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
        }

        label {
            display: block;
            font-size: 13px;
            color: #4c2c43;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.1px;
        }

        .input-wrap {
            position: relative;
            margin-bottom: 16px;
        }

        input.text,
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(215, 160, 205, 0.7);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.9);
            color: #3f2640;
            font-size: 14px;
            transition: box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }

        input.text:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #d45da7;
            box-shadow: 0 0 0 4px rgba(212, 93, 155, 0.15), 0 8px 18px rgba(212, 93, 155, 0.08);
            background: #fff;
        }

        input.text::placeholder,
        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: #b089a4;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            user-select: none;
            color: #b8709c;
            opacity: 0.75;
            transition: opacity 0.2s ease;
        }

        .toggle-password:hover { opacity: 1; }

        .captcha-wrapper img.loginCaptcha {
            border: 1px solid rgba(215, 160, 205, 0.6);
            border-radius: 12px;
            margin-bottom: 8px;
        }

        .remember-row {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-switch {
            position: relative;
            width: 46px;
            height: 26px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: linear-gradient(135deg, #f6d8eb 0%, #e9d3ff 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), 0 6px 14px rgba(212, 93, 155, 0.12);
            transition: background 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .toggle-switch input.checkbox {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .toggle-track {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            overflow: hidden;
        }

        .toggle-knob {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(180deg, #fff5fb 0%, #ffffff 100%);
            box-shadow: 0 6px 12px rgba(212, 93, 155, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.9);
            transition: transform 0.22s ease, background 0.22s ease, box-shadow 0.22s ease;
        }

        .toggle-switch input.checkbox:checked + .toggle-track {
            background: linear-gradient(135deg, #ffb6d9 0%, #d45da7 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 8px 16px rgba(212, 93, 155, 0.18);
        }

        .toggle-switch input.checkbox:checked + .toggle-track .toggle-knob {
            transform: translateX(20px);
            background: linear-gradient(180deg, #fff8fb 0%, #ffe4f2 100%);
            box-shadow: 0 6px 14px rgba(212, 93, 155, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }

        .remember-label {
            color: #5e3d58;
            font-weight: 600;
            font-size: 13px;
            line-height: 26px;
            cursor: pointer;
        }

        input.login,
        input[type="submit"] {
            width: 100%;
            margin-top: 10px;
            padding: 13px 16px;
            border-radius: 16px;
            border: none;
            background: linear-gradient(135deg, #ffb6d9 0%, #d45da7 100%);
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.4px;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(212, 93, 155, 0.28);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.2s ease;
        }

        input.login:hover,
        input[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(212, 93, 155, 0.32);
            filter: brightness(1.02);
        }

        input.login:active,
        input[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 8px 18px rgba(212, 93, 155, 0.28);
        }

        .loginLicense {
            margin-top: 16px;
            text-align: center;
            color: #6f4a60;
            font-size: 12px;
            z-index: 2;
            position: relative;
            line-height: 1.6;
        }

        .forgot-password {
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
            color: #7a546d;
            letter-spacing: 0.05px;
            z-index: 1;
            position: relative;
        }

        .forgot-password a {
            color: #c15b9a;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-password a:hover {
            color: #d45da7;
            text-decoration: underline;
        }

        .loginLicense a {
            color: #d45da7;
            text-decoration: none;
        }

        .loginLicense a:hover { text-decoration: underline; }

        #logo { display: none; }

        @media (max-width: 520px) {
            body { padding: 22px 14px; }
            form { border-radius: 20px; }
            .header { padding: 18px 18px 12px; }
            .body { padding: 0 18px 22px; }
        }
    </style>

    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <link rel="stylesheet" href="media/script/jquery/jquery.alerts.css" type="text/css"/>
    <script src="media/script/jquery/jquery.alerts.js" type="text/javascript"></script>

    <script type="text/javascript">
        if (top.frames.length != 0) {
            top.location = self.document.location;
        }
    </script>
</head>
<body id="login">
<div id="mx_loginbox">
    <form method="post" name="loginfrm" id="loginfrm" action="processors/login.processor.php">
        [+OnManagerLoginFormPrerender+]
        <div class="header">
            <a href="../" class="brand"><span class="icon">✦</span><span>[+site_name+]</span></a>
        </div>
        <div class="body">
            <img src="[+style_misc_path+]login-logo.png" alt="[+site_name+]" id="logo"/>
            <p class="loginMessage">[+login_message+]</p>
            <div class="input-wrap">
                <label for="username">[+username+]</label>
                <input type="text" class="text" name="username" id="username" tabindex="1" value="[+uid+]"/>
            </div>
            <div class="input-wrap">
                <label for="password">[+password+]</label>
                <input type="password" class="text" name="password" id="password" tabindex="2" value=""/>
                <span class="toggle-password" onclick="togglePassword()">♡</span>
            </div>
            <div class="captcha-wrapper">
                [+login_captcha_message+]
                [+captcha_image+]
                [+captcha_input+]
            </div>
            <div class="remember-row">
                <label class="toggle-switch" for="rememberme">
                    <input type="checkbox" id="rememberme" name="rememberme" tabindex="4" value="1" class="checkbox" [+remember_me+] />
                    <span class="toggle-track">
                        <span class="toggle-knob"></span>
                    </span>
                </label>
                <label class="remember-label" for="rememberme">[+remember_username+]</label>
            </div>
            <input type="submit" class="login" onclick="return false;" id="submitButton" value="[+login_button+]"/>
            <div class="forgot-password">[+OnManagerLoginFormRender+]</div>
        </div>
    </form>
</div>

<p class="loginLicense">
    &copy; 2005-[[$_SERVER['REQUEST_TIME']:date(Y)]] by the <a href="http://modx.com/" target="_blank">MODX</a>.
    <strong>MODX</strong>&trade; is licensed under the GPL.
</p>
<script>
    function togglePassword() {
        var passwordField = document.getElementById('password');
        var toggleIcon = document.querySelector('.toggle-password');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.textContent = 'ꕥ';
        } else {
            passwordField.type = 'password';
            toggleIcon.textContent = '♡';
        }
    }

    $('#submitButton').click(function () {
        var username = $('#username').val();
        var password = $('#password').val();
        var rememberme = $('#rememberme').val();
        var captcha_code = $('input[name="captcha_code"]').val();
        var params = {username: username, password: password, rememberme: rememberme, ajax: '1', captcha_code: captcha_code};
        $.post('processors/login.processor.php', params, function (response) {
            var header = response.substr(0, 9);
            if (header.toLowerCase() == 'location:') top.location = response.substr(10);
            else {
                var cimg = document.getElementById('captcha_image');
                if (cimg) cimg.src = '../index.php?get=captcha';
                jAlert(response);
            }
        });
    });
    if ($('#username').val() != '') $('#password').focus();
    else $('#username').focus();
</script>
</body>
</html>
