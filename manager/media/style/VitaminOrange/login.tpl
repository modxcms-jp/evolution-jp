<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="[+modx_charset+]">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>MODX CMF Manager Login</title>
    <style>
        /* ============================================
           基本設定
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            height: 100%;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
            overflow-y: auto;
        }

        body {
            min-height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            background: #0c0a0a;
            color: #ffe8d1;
        }

        /* ============================================
           背景 - ビタミンオレンジの輝き
           ============================================ */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background:
                radial-gradient(circle at 18% 22%, rgba(255, 154, 60, 0.22) 0%, rgba(255, 154, 60, 0.05) 26%, transparent 46%),
                radial-gradient(circle at 82% 12%, rgba(255, 122, 31, 0.18) 0%, rgba(255, 122, 31, 0.05) 24%, transparent 48%),
                radial-gradient(circle at 52% 72%, rgba(34, 16, 8, 0.45) 0%, rgba(12, 12, 18, 0.35) 35%, transparent 60%),
                linear-gradient(135deg, #0d0a0a 0%, #0c0f16 42%, #0a0c12 100%);
            z-index: 0;
        }

        /* 微細なテクスチャ */
        body::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0.35;
            background-image:
                repeating-linear-gradient(
                    0deg,
                    transparent,
                    transparent 2px,
                    rgba(255, 186, 110, 0.02) 2px,
                    rgba(255, 186, 110, 0.02) 4px
                );
            z-index: 0;
        }

        /* ============================================
           メインコンテナ
           ============================================ */
        #mx_loginbox {
            position: relative;
            z-index: 10;
            width: 520px;
            margin: 0;
        }

        /* ============================================
           フォームコンテナ - グラデーションカード
           ============================================ */
        form {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            background: linear-gradient(160deg, rgba(22, 18, 14, 0.92) 0%, rgba(14, 16, 22, 0.96) 52%, rgba(10, 12, 18, 0.98) 100%);
            border: 1px solid rgba(255, 154, 60, 0.24);
            box-shadow:
                0 20px 45px rgba(0, 0, 0, 0.45),
                0 0 0 1px rgba(255, 154, 60, 0.06),
                0 12px 24px rgba(255, 154, 60, 0.12);
            backdrop-filter: blur(4px);
        }

        /* 上部のハイライトライン */
        form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ff9a3c 0%, #ff7a1f 50%, #ff9a3c 100%);
        }

        /* ============================================
           ヘッダー
           ============================================ */
        .header {
            background: linear-gradient(180deg, rgba(255, 154, 60, 0.08), rgba(255, 122, 31, 0.04));
            padding: 26px 28px;
            border-bottom: 1px solid rgba(255, 154, 60, 0.18);
            position: relative;
        }

        .header a {
            color: #ffe6c7;
            text-decoration: none;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.4px;
            transition: color 0.2s ease;
            display: inline-block;
            text-shadow: 0 1px 8px rgba(255, 154, 60, 0.25);
        }

        .header a:hover {
            color: #ffb86a;
        }

        /* ============================================
           ボディ
           ============================================ */
        .body {
            background: radial-gradient(ellipse at 30% 0%, rgba(255, 154, 60, 0.08), transparent 60%), rgba(12, 13, 19, 0.7);
            padding: 28px;
            position: relative;
        }

        /* ロゴ - 非表示 */
        #logo {
            display: none;
        }

        /* ============================================
           ラベル
           ============================================ */
        label {
            display: block;
            color: #ffcfa1;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.2px;
            text-align: left;
        }

        label[for="username"] {
            margin-top: 0;
        }

        label[for="password"] {
            margin-top: 16px;
        }

        /* ============================================
           入力フィールド
           ============================================ */
        input.text,
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 0;
            background: rgba(12, 13, 18, 0.85);
            border: 1px solid rgba(255, 154, 60, 0.25);
            border-radius: 8px;
            color: #ffe8d1;
            font-size: 14px;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
        }

        #username {
            margin-bottom: 0;
        }

        #password {
            margin-bottom: 0;
        }

        /* パスワードフィールドラッパー */
        .password-wrapper {
            position: relative;
            margin-bottom: 24px;
        }

        /* パスワード表示/非表示切り替えアイコン */
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 20px;
            user-select: none;
            opacity: 0.5;
            transition: opacity 0.2s ease;
            z-index: 10;
            color: #ffb86a;
        }

        .toggle-password:hover {
            opacity: 0.8;
        }

        /* オートフィル時のスタイル */
        input.text:-webkit-autofill,
        input[type="text"]:-webkit-autofill,
        input[type="password"]:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px rgba(12, 13, 18, 0.85) inset !important;
            -webkit-text-fill-color: #ffe8d1 !important;
            border: 1px solid rgba(255, 154, 60, 0.5) !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        input.text:-webkit-autofill:focus,
        input[type="text"]:-webkit-autofill:focus,
        input[type="password"]:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px rgba(14, 15, 20, 0.9) inset !important;
            border: 1px solid #ff9a3c !important;
        }

        input.text::placeholder,
        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: rgba(255, 232, 209, 0.55);
        }

        input.text:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            background: rgba(14, 15, 20, 0.9);
            border-color: #ff9a3c;
            box-shadow:
                0 0 0 3px rgba(255, 154, 60, 0.16),
                0 10px 30px rgba(0, 0, 0, 0.35);
        }

        /* ============================================
           Captcha
           ============================================ */
        img.loginCaptcha {
            border: 1px solid rgba(255, 154, 60, 0.35);
            border-radius: 8px;
            margin: 0 0 8px 0;
            width: 148px;
            height: 60px;
            display: block;
            background: rgba(12, 13, 18, 0.7);
        }

        input[name="captcha_code"] {
            margin-bottom: 0;
        }

        label[for="captcha_code"] {
            margin-top: 16px;
        }

        /* ============================================
           チェックボクス
           ============================================ */
        input.checkbox {
            display: inline-block;
            margin-right: 8px;
            margin-top: 0;
            margin-bottom: 0;
            width: 16px;
            height: 16px;
            cursor: pointer;
            vertical-align: middle;
        }

        label[for="rememberme"] {
            color: #f7c08e;
            cursor: pointer;
            display: inline;
            margin-top: 0;
            margin-bottom: 0;
            line-height: 20px;
            vertical-align: middle;
        }

        /* ============================================
           ログインボタン - グラデーション＆グロー
           ============================================ */
        input.login,
        input[type="submit"] {
            width: 100%;
            max-width: 380px;
            display: block;
            margin: 24px auto 0;
            padding: 16px 32px;
            background: linear-gradient(135deg, #ff9a3c 0%, #ff7a1f 100%);
            border: 1px solid #ff9a3c;
            border-radius: 10px;
            color: #1c0d05;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            box-shadow:
                0 12px 32px rgba(255, 122, 31, 0.32),
                0 2px 6px rgba(0, 0, 0, 0.24);
        }

        input.login:hover,
        input[type="submit"]:hover {
            background: linear-gradient(135deg, #ffb86a 0%, #ff9a3c 100%);
            border-color: #ffb86a;
            box-shadow:
                0 16px 40px rgba(255, 154, 60, 0.42),
                0 4px 10px rgba(0, 0, 0, 0.28);
        }

        input.login:active,
        input[type="submit"]:active {
            background: linear-gradient(135deg, #d95f00 0%, #b84800 100%);
            border-color: #d95f00;
            box-shadow:
                0 8px 16px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 234, 210, 0.35);
            color: #ffe6c7;
        }

        /* ============================================
           メッセージ
           ============================================ */
        .loginMessage {
            color: #f7c08e;
            padding-bottom: 0;
            margin-bottom: 16px;
            line-height: 1.6;
            text-align: left;
        }

        /* リンク */
        .body a {
            color: #ff9a3c;
            text-decoration: none;
            transition: color 0.2s ease, opacity 0.2s ease;
            display: block;
            text-align: center;
            margin-top: 24px;
            font-weight: 600;
        }

        .body a:hover {
            color: #ffb86a;
            text-decoration: underline;
        }

        /* 警告メッセージ */
        .warning {
            color: #ffb86a;
            font-weight: 600;
            padding: 12px;
            background: rgba(255, 122, 31, 0.08);
            border-left: 3px solid #ff7a1f;
            border-radius: 6px;
            margin-bottom: 16px;
            text-align: left;
        }

        /* 成功メッセージ */
        .success {
            color: #58d39f;
            font-weight: 600;
            padding: 12px;
            background: rgba(88, 211, 159, 0.08);
            border-left: 3px solid #58d39f;
            border-radius: 6px;
            margin-bottom: 16px;
            text-align: left;
        }

        /* ============================================
           フッター
           ============================================ */
        .loginLicense {
            text-align: center;
            color: rgba(255, 232, 209, 0.7);
            font-size: 12px;
            margin-top: 32px;
            padding: 0;
            line-height: 1.6;
            max-width: 500px;
            width: 100%;
        }

        .loginLicense a {
            color: #ffb86a;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .loginLicense a:hover {
            color: #ff9a3c;
        }

        /* ============================================
           レスポンシブ対応
           ============================================ */
        @media (max-width: 560px) {
            body {
                padding: 24px 16px;
            }

            #mx_loginbox {
                width: 100%;
                max-width: 440px;
            }

            .header {
                padding: 22px;
            }

            .body {
                padding: 24px;
            }

            input.text,
            input[type="text"],
            input[type="password"] {
                font-size: 16px; /* iOS ズーム防止 */
            }

            input.login,
            input[type="submit"] {
                max-width: 100%;
            }

            .loginLicense {
                margin-top: 24px;
                max-width: 440px;
            }
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
        <!-- anything to output before the login box via a plugin? -->
        [+OnManagerLoginFormPrerender+]
        <div class="header"><a href="../">[+site_name+]</a></div>
        <div class="body">
            <img src="[+style_misc_path+]login-logo.png" alt="[+site_name+]" id="logo"/>
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
            <input type="checkbox" id="rememberme" name="rememberme" tabindex="4" value="1" class="checkbox"
                   [+remember_me+]/><label for="rememberme">[+remember_username+]</label>
            <input type="submit" class="login" onclick="return false;" id="submitButton" value="[+login_button+]"/>
            <!-- anything to output before the login box via a plugin ... like the forgot password link? -->
            [+OnManagerLoginFormRender+]
        </div>
    </form>
</div>
<!-- close #mx_loginbox -->

<p class="loginLicense">
    &copy; 2005-[[$_SERVER['REQUEST_TIME']:date(Y)]] by the <a href="http://modx.com/" target="_blank">MODX</a>.
    <strong>MODX</strong>&trade; is licensed under the GPL.
</p>
<script>
    // パスワード表示/非表示切り替え
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

    $('#submitButton').click(function (e) {
        var $form = $('#loginfrm');
        var username = $('#username').val();
        var password = $('#password').val();
        var rememberme = $('#rememberme').val();
        var captcha_code = $('input[name="captcha_code"]').val();
        params = {'username':username,'password':password,'rememberme':rememberme,'ajax':'1','captcha_code':captcha_code};
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
