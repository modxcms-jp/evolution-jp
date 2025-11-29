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

        /* MojitoGreen ログイン画面
           カラーパレット: #FDFDFB(氷白), #FAFFF4(極淡ライム), #DFF5D7, #CBEEC5, #B6E7B3
           アクセント: #EAFCCF(レモンイエロー), #F7FFDE(淡黄緑) */
        body {
            /* ベースグラデーション - 氷白からライムへ */
            background: 
                /* 氷の鋭い反射ハイライト */
                radial-gradient(ellipse 2px 3px at 25% 18%, #FDFDFB 0%, transparent 100%),
                radial-gradient(ellipse 3px 2px at 72% 22%, #FDFDFB 0%, transparent 100%),
                radial-gradient(ellipse 2px 4px at 45% 35%, #FAFFF4 0%, transparent 100%),
                /* 炭酸泡のきらめき */
                radial-gradient(circle 4px at 18% 45%, rgba(253, 253, 251, 0.9) 0%, rgba(250, 255, 244, 0.4) 40%, transparent 70%),
                radial-gradient(circle 3px at 82% 38%, rgba(253, 253, 251, 0.85) 0%, rgba(250, 255, 244, 0.35) 45%, transparent 75%),
                radial-gradient(circle 5px at 35% 72%, rgba(253, 253, 251, 0.8) 0%, rgba(223, 245, 215, 0.3) 50%, transparent 80%),
                radial-gradient(circle 3px at 68% 65%, rgba(253, 253, 251, 0.85) 0%, transparent 60%),
                /* ライムエッジの淡い黄緑ライン */
                radial-gradient(ellipse 60% 25% at 15% 85%, rgba(234, 252, 207, 0.5) 0%, transparent 70%),
                radial-gradient(ellipse 40% 20% at 85% 75%, rgba(247, 255, 222, 0.45) 0%, transparent 65%),
                /* 柔らかなライムグラデーション */
                radial-gradient(circle at 20% 20%, rgba(203, 238, 197, 0.4), transparent 35%),
                radial-gradient(circle at 80% 15%, rgba(182, 231, 179, 0.35), transparent 30%),
                radial-gradient(circle at 75% 80%, rgba(223, 245, 215, 0.45), transparent 35%),
                /* ベース */
                linear-gradient(180deg, #FDFDFB 0%, #FAFFF4 35%, #F7FFDE 70%, #EAFCCF 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 36px 18px;
            color: #3A6A40;
            position: relative;
            overflow: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            filter: blur(50px);
            opacity: 0.3;
            z-index: 0;
        }

        body::before {
            background: radial-gradient(circle, #CBEEC5 0%, #DFF5D7 45%, transparent 70%);
            top: -120px;
            left: -100px;
        }

        body::after {
            background: radial-gradient(circle, #B6E7B3 0%, #CBEEC5 45%, transparent 72%);
            bottom: -130px;
            right: -70px;
        }

        #mx_loginbox {
            position: relative;
            z-index: 2;
            width: 480px;
            max-width: 100%;
            margin-bottom: 14px;
        }

        form {
            background: rgba(253, 253, 251, 0.82);
            border-radius: 24px;
            border: 1px solid rgba(182, 231, 179, 0.5);
            box-shadow: 0 18px 42px rgba(90, 154, 94, 0.15), 0 6px 18px rgba(58, 106, 64, 0.06);
            overflow: hidden;
            position: relative;
        }

        form::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(203, 238, 197, 0.3), rgba(223, 245, 215, 0.2));
            opacity: 0.85;
            pointer-events: none;
        }

        form::after {
            content: "";
            position: absolute;
            inset: 1px;
            border-radius: 23px;
            background: linear-gradient(180deg, rgba(253, 253, 251, 0.75), rgba(250, 255, 244, 0.92));
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
            color: #3A6A40;
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
            background: linear-gradient(135deg, #CBEEC5 0%, #B6E7B3 100%);
            color: #3A6A40;
            box-shadow: inset 0 1px 0 rgba(253, 253, 251, 0.7), 0 8px 14px rgba(90, 154, 94, 0.25);
            font-size: 16px;
        }

        .body {
            position: relative;
            padding: 0 26px 28px;
            z-index: 1;
        }

        .loginMessage {
            font-size: 14px;
            color: #3A6A40;
            background: rgba(234, 252, 207, 0.7);
            border: 1px solid rgba(182, 231, 179, 0.5);
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 16px;
            box-shadow: inset 0 1px 0 rgba(253, 253, 251, 0.7);
        }

        label {
            display: block;
            font-size: 13px;
            color: #2E5535;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.1px;
        }

        .input-wrap {
            position: relative;
            margin-bottom: 16px;
        }

        .input-field {
            position: relative;
        }

        input.text,
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(182, 231, 179, 0.6);
            border-radius: 14px;
            background: rgba(253, 253, 251, 0.95);
            color: #2E5535;
            font-size: 14px;
            transition: box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }

        .input-field input[type="password"] {
            padding-right: 44px;
        }

        input.text:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #B6E7B3;
            box-shadow: 0 0 0 4px rgba(182, 231, 179, 0.2), 0 8px 18px rgba(90, 154, 94, 0.08);
            background: #FDFDFB;
        }

        input.text::placeholder,
        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: #8DC78A;
        }

        /* Autofill styles - override browser's yellow background */
        input.text:-webkit-autofill,
        input[type="text"]:-webkit-autofill,
        input[type="password"]:-webkit-autofill,
        input.text:-webkit-autofill:hover,
        input[type="text"]:-webkit-autofill:hover,
        input[type="password"]:-webkit-autofill:hover,
        input.text:-webkit-autofill:focus,
        input[type="text"]:-webkit-autofill:focus,
        input[type="password"]:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px rgba(250, 255, 244, 0.95) inset !important;
            -webkit-text-fill-color: #2E5535 !important;
            border-color: rgba(182, 231, 179, 0.6);
            transition: background-color 5000s ease-in-out 0s;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            user-select: none;
            color: #8DC78A;
            opacity: 0.75;
            transition: opacity 0.2s ease;
        }

        .toggle-password:hover { opacity: 1; }

        .captcha-wrapper img.loginCaptcha {
            border: 1px solid rgba(182, 231, 179, 0.5);
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
            height: 24px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: linear-gradient(135deg, #EAFCCF 0%, #DFF5D7 100%);
            box-shadow: inset 0 1px 0 rgba(253, 253, 251, 0.7), 0 6px 14px rgba(90, 154, 94, 0.1);
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
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: linear-gradient(180deg, #FDFDFB 0%, #FAFFF4 100%);
            box-shadow: 0 6px 12px rgba(90, 154, 94, 0.15), inset 0 1px 0 rgba(253, 253, 251, 0.95);
            transition: transform 0.22s ease, background 0.22s ease, box-shadow 0.22s ease;
        }

        .toggle-switch input.checkbox:checked + .toggle-track {
            background: linear-gradient(135deg, #CBEEC5 0%, #B6E7B3 100%);
            box-shadow: inset 0 1px 0 rgba(253, 253, 251, 0.8), 0 8px 16px rgba(90, 154, 94, 0.15);
        }

        .toggle-switch input.checkbox:checked + .toggle-track .toggle-knob {
            transform: translateX(22px);
            background: linear-gradient(180deg, #FDFDFB 0%, #F7FFDE 100%);
            box-shadow: 0 6px 14px rgba(90, 154, 94, 0.2), inset 0 1px 0 rgba(253, 253, 251, 0.98);
        }

        .remember-label {
            color: #3A6A40;
            font-weight: 600;
            font-size: 13px;
            line-height: 24px;
            cursor: pointer;
        }

        input.login,
        input[type="submit"] {
            width: 100%;
            margin-top: 10px;
            padding: 13px 16px;
            border-radius: 16px;
            border: none;
            background: linear-gradient(135deg, #CBEEC5 0%, #B6E7B3 50%, #8DC78A 100%);
            color: #2E5535;
            font-weight: 700;
            letter-spacing: 0.4px;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(90, 154, 94, 0.2), inset 0 1px 0 rgba(253, 253, 251, 0.6);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.2s ease;
        }

        input.login:hover,
        input[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(90, 154, 94, 0.25), inset 0 1px 0 rgba(253, 253, 251, 0.7);
            filter: brightness(1.02);
        }

        input.login:active,
        input[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 8px 18px rgba(90, 154, 94, 0.2);
        }

        .loginLicense {
            margin-top: 16px;
            text-align: center;
            color: #5A9A5E;
            font-size: 12px;
            z-index: 2;
            position: relative;
            line-height: 1.6;
        }

        .forgot-password {
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
            color: #5A9A5E;
            letter-spacing: 0.05px;
            z-index: 1;
            position: relative;
        }

        .forgot-password a {
            color: #3A6A40;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-password a:hover {
            color: #2E5535;
            text-decoration: underline;
        }

        .loginLicense a {
            color: #3A6A40;
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
            <a href="../" class="brand"><span class="icon">🌿</span><span>[+site_name+]</span></a>
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
                <div class="input-field">
                    <input type="password" class="text" name="password" id="password" tabindex="2" value=""/>
                    <span class="toggle-password" onclick="togglePassword()">🍃</span>
                </div>
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
            toggleIcon.textContent = '🍈';
        } else {
            passwordField.type = 'password';
            toggleIcon.textContent = '🍃';
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
