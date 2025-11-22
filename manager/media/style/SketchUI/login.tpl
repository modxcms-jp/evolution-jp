<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="[+modx_charset+]">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>MODX CMF Manager Login</title>
    <style>
        :root {
            --paper: #fdfbf5;
            --paper-deep: #f7f1e6;
            --ink: #2f2a32;
            --ink-soft: #514a54;
            --border: 1.5px solid rgba(47, 42, 50, 0.55);
            --border-light: 1.25px solid rgba(47, 42, 50, 0.35);
            --shadow: 0 6px 0 rgba(47, 42, 50, 0.12);
            --shadow-strong: 0 10px 0 rgba(47, 42, 50, 0.16);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            background: var(--paper);
            color: var(--ink);
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 25% 20%, rgba(47, 42, 50, 0.035), transparent 42%),
                radial-gradient(circle at 80% 18%, rgba(47, 42, 50, 0.025), transparent 38%),
                radial-gradient(circle at 50% 80%, rgba(47, 42, 50, 0.03), transparent 40%);
            opacity: 0.6;
            z-index: 0;
        }

        #mx_loginbox {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }

        form {
            background: #fffef8;
            border: var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 28px 24px 24px;
        }

        .header {
            margin-bottom: 18px;
        }

        .header a {
            color: var(--ink);
            font-weight: 700;
            font-size: 20px;
            text-decoration: none;
            display: inline-block;
            padding-bottom: 4px;
            border-bottom: var(--border-light);
        }

        .body {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .loginMessage {
            color: var(--ink-soft);
            line-height: 1.6;
        }

        label {
            color: var(--ink);
            font-weight: 600;
            font-size: 14px;
        }

        input.text,
        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="number"] {
            width: 100%;
            padding: 12px 14px;
            border: var(--border-light);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
            transition: transform 80ms ease, box-shadow 80ms ease, border-color 80ms ease;
        }

        input.text:focus,
        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus {
            outline: 2px solid rgba(47, 42, 50, 0.2);
            box-shadow: var(--shadow);
            transform: translateY(-1px);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            user-select: none;
            color: var(--ink-soft);
            opacity: 0.75;
        }

        img.loginCaptcha {
            border: var(--border-light);
            border-radius: 10px;
            padding: 6px;
            background: #fff;
        }

        input.checkbox {
            width: 16px;
            height: 16px;
            margin-right: 6px;
            vertical-align: middle;
        }

        label[for="rememberme"] {
            display: inline;
            color: var(--ink-soft);
            font-weight: 500;
            vertical-align: middle;
        }

        input.login,
        input[type="submit"] {
            width: 100%;
            margin-top: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            border: var(--border);
            background: #fff;
            color: var(--ink);
            font-weight: 700;
            letter-spacing: 0.4px;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: transform 80ms ease, box-shadow 80ms ease;
        }

        input.login:hover,
        input[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-strong);
        }

        input.login:active,
        input[type="submit"]:active {
            transform: translateY(0);
        }

        .body a {
            color: var(--ink-soft);
            text-decoration: none;
            text-align: center;
            margin-top: 6px;
        }

        .warning, .success {
            padding: 12px;
            border-radius: 12px;
            border: var(--border-light);
            background: var(--paper-deep);
            color: var(--ink);
        }

        .loginLicense {
            text-align: center;
            margin-top: 20px;
            color: rgba(47, 42, 50, 0.65);
            font-size: 12px;
            line-height: 1.6;
        }

        .loginLicense a {
            color: var(--ink);
            text-decoration: none;
        }

        @media (max-width: 520px) {
            form {
                padding: 24px 18px 20px;
            }
        }
    </style>

    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <link rel="stylesheet" href="media/script/jquery/jquery.alerts.css" type="text/css"/>
    <script src="media/script/jquery/jquery.alerts.js" type="text/javascript"></script>
    <script type="text/javascript">
        if (top.frames.length !== 0) {
            top.location = self.document.location;
        }
    </script>
</head>
<body id="login">
<div id="mx_loginbox">
    <form method="post" name="loginfrm" id="loginfrm" action="processors/login.processor.php">
        [+OnManagerLoginFormPrerender+]
        <div class="header"><a href="../">[+site_name+]</a></div>
        <div class="body">
            <p class="loginMessage">[+login_message+]</p>
            <label for="username">[+username+]</label>
            <input type="text" class="text" name="username" id="username" tabindex="1" value="[+uid+]"/>
            <label for="password">[+password+]</label>
            <div class="password-wrapper">
                <input type="password" class="text" name="password" id="password" tabindex="2" value=""/>
                <span class="toggle-password" onclick="togglePassword()">👁️</span>
            </div>
            [+login_captcha_message+]
            [+captcha_image+]
            [+captcha_input+]
            <div>
                <input type="checkbox" id="rememberme" name="rememberme" tabindex="4" value="1" class="checkbox" [+remember_me+]/>
                <label for="rememberme">[+remember_username+]</label>
            </div>
            <input type="submit" class="login" onclick="return false;" id="submitButton" value="[+login_button+]"/>
            [+OnManagerLoginFormRender+]
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
            toggleIcon.textContent = '🙈';
        } else {
            passwordField.type = 'password';
            toggleIcon.textContent = '👁️';
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
            if (header.toLowerCase() === 'location:') {
                top.location = response.substr(10);
            } else {
                var cimg = document.getElementById('captcha_image');
                if (cimg) cimg.src = '../index.php?get=captcha';
                jAlert(response);
            }
        });
    });

    if ($('#username').val() !== '') {
        $('#password').focus();
    } else {
        $('#username').focus();
    }
</script>
</body>
</html>
