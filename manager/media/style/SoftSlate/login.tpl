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
            background-color: #f8f9fa;
        }

        /* ============================================
           背景 - 繊細で上品なグラデーション
           ============================================ */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: 
                /* 中央からの柔らかい光 */
                radial-gradient(ellipse at 50% 40%, 
                    rgba(74, 106, 165, 0.025) 0%, 
                    rgba(74, 106, 165, 0.012) 25%, 
                    transparent 50%
                ),
                /* 左上の微かな青 */
                radial-gradient(ellipse at 15% 20%, 
                    rgba(44, 82, 130, 0.02) 0%, 
                    rgba(44, 82, 130, 0.01) 30%, 
                    transparent 55%
                ),
                /* 右下の繊細な青 */
                radial-gradient(ellipse at 85% 80%, 
                    rgba(96, 125, 175, 0.015) 0%, 
                    transparent 50%
                ),
                /* ベースのグラデーション */
                linear-gradient(135deg, 
                    #f8f9fa 0%,
                    #f1f3f5 25%,
                    #e9ecef 50%,
                    #f1f3f5 75%,
                    #f8f9fa 100%
                );
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
            opacity: 0.3;
            background-image:
                repeating-linear-gradient(
                    0deg,
                    transparent,
                    transparent 2px,
                    rgba(100, 100, 100, 0.01) 2px,
                    rgba(100, 100, 100, 0.01) 4px
                );
            z-index: 0;
        }

        /* ============================================
           メインコンテナ
           ============================================ */
        #mx_loginbox {
            position: relative;
            z-index: 10;
            width: 500px;
            margin: 0;
        }

        /* ============================================
           フォームコンテナ - 上品なシンプルデザイン
           ============================================ */
        form {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 
                0 1px 2px rgba(0, 0, 0, 0.04),
                0 0 0 1px rgba(0, 0, 0, 0.03);
        }

        /* 上部の繊細なライン */
        form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, 
                #4a6fa5 0%,
                #5a7eb5 50%,
                #4a6fa5 100%
            );
        }

        /* ============================================
           ヘッダー
           ============================================ */
        .header {
            background: #fafbfc;
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }

        .header a {
            color: #2d3748;
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: color 0.2s ease;
            display: inline-block;
        }

        .header a:hover {
            color: #4a6fa5;
        }

        /* ============================================
           ボディ
           ============================================ */
        .body {
            background: #ffffff;
            padding: 24px;
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
            color: #4a5568;
            font-weight: 500;
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
            background: #fafbfc;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: #2d3748;
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
            opacity: 0.4;
            transition: opacity 0.2s ease;
            z-index: 10;
        }

        .toggle-password:hover {
            opacity: 0.7;
        }

        /* オートフィル時のスタイル */
        input.text:-webkit-autofill,
        input[type="text"]:-webkit-autofill,
        input[type="password"]:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px #fafbfc inset !important;
            -webkit-text-fill-color: #2d3748 !important;
            border: 1px solid #4a6fa5 !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        input.text:-webkit-autofill:focus,
        input[type="text"]:-webkit-autofill:focus,
        input[type="password"]:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            border: 1px solid #4a6fa5 !important;
        }

        input.text::placeholder,
        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: #a0aec0;
        }

        input.text:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            background: #ffffff;
            border-color: #4a6fa5;
            box-shadow: 
                0 0 0 3px rgba(74, 111, 165, 0.08),
                0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* ============================================
           Captcha
           ============================================ */
        img.loginCaptcha {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            margin: 0 0 8px 0;
            width: 148px;
            height: 60px;
            display: block;
        }

        input[name="captcha_code"] {
            margin-bottom: 0;
        }

        label[for="captcha_code"] {
            margin-top: 16px;
        }

        /* ============================================
           チェックボックス
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
            color: #718096;
            cursor: pointer;
            display: inline;
            margin-top: 0;
            margin-bottom: 0;
            line-height: 20px;
            vertical-align: middle;
        }

        /* ============================================
           ログインボタン - フラットデザイン
           ============================================ */
        input.login,
        input[type="submit"] {
            width: 100%;
            max-width: 380px;
            display: block;
            margin: 24px auto 0;
            padding: 16px 32px;
            background: #3d4f5d;
            border: 1px solid #3d4f5d;
            border-radius: 6px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }

        input.login:hover,
        input[type="submit"]:hover {
            background: #6f8fa9;
            border-color: #6f8fa9;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.10);
        }

        input.login:active,
        input[type="submit"]:active {
            background: #3d5a85;
            border-color: #3d5a85;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        /* ============================================
           メッセージ
           ============================================ */
        .loginMessage {
            color: #718096;
            padding-bottom: 0;
            margin-bottom: 16px;
            line-height: 1.6;
            text-align: left;
        }

        /* リンク */
        .body a {
            color: #4a6fa5;
            text-decoration: none;
            transition: color 0.2s ease;
            display: block;
            text-align: center;
            margin-top: 24px;
        }

        .body a:hover {
            color: #2c5282;
            text-decoration: underline;
        }

        /* 警告メッセージ */
        .warning {
            color: #c53030;
            font-weight: 500;
            padding: 12px;
            background: #fff5f5;
            border-left: 3px solid #fc8181;
            border-radius: 4px;
            margin-bottom: 16px;
            text-align: left;
        }

        /* 成功メッセージ */
        .success {
            color: #2f855a;
            font-weight: 500;
            padding: 12px;
            background: #f0fff4;
            border-left: 3px solid #68d391;
            border-radius: 4px;
            margin-bottom: 16px;
            text-align: left;
        }

        /* ============================================
           フッター
           ============================================ */
        .loginLicense {
            text-align: center;
            color: #a0aec0;
            font-size: 12px;
            margin-top: 32px;
            padding: 0;
            line-height: 1.6;
            max-width: 500px;
            width: 100%;
        }

        .loginLicense a {
            color: #4a6fa5;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .loginLicense a:hover {
            color: #2c5282;
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
                padding: 24px;
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

    <script src="media/script/evo-dialog.js" type="text/javascript"></script>
    <script src="media/script/login.js" type="text/javascript"></script>

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
</script>
</body>
</html>
