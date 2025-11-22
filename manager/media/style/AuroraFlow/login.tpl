<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="[+modx_charset+]">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>MODX CMF Manager Login</title>
    <!-- 既存のstyle.cssは読み込まない（独自スタイルを使用） -->
    <style>
        /* グラスモーフィズム - 洗練されたログイン画面 */
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
            background: #0a0e1a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Option 1: 複雑な多層グラデーション（10層） - より深みのある光の表現 */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: 
                /* メイン光源 - 中心からの広がり */
                radial-gradient(ellipse at 50% 45%, rgba(59, 130, 246, 0.20) 0%, rgba(59, 130, 246, 0.12) 15%, rgba(59, 130, 246, 0.06) 30%, transparent 55%),
                
                /* 左上の青い光 */
                radial-gradient(ellipse at 18% 25%, rgba(37, 99, 235, 0.22) 0%, rgba(37, 99, 235, 0.14) 20%, rgba(37, 99, 235, 0.08) 35%, transparent 52%),
                
                /* 右下の深い青 */
                radial-gradient(ellipse at 82% 75%, rgba(30, 64, 175, 0.24) 0%, rgba(30, 64, 175, 0.16) 18%, rgba(30, 64, 175, 0.09) 32%, transparent 50%),
                
                /* 左下のシアン系光 */
                radial-gradient(ellipse at 15% 80%, rgba(14, 165, 233, 0.18) 0%, rgba(14, 165, 233, 0.11) 22%, rgba(14, 165, 233, 0.06) 38%, transparent 54%),
                
                /* 右上の紫がかった青 */
                radial-gradient(ellipse at 85% 20%, rgba(99, 102, 241, 0.16) 0%, rgba(99, 102, 241, 0.10) 25%, rgba(99, 102, 241, 0.05) 42%, transparent 58%),
                
                /* 中央やや右の微細な光 */
                radial-gradient(circle at 65% 55%, rgba(59, 130, 246, 0.09) 0%, rgba(59, 130, 246, 0.05) 25%, transparent 48%),
                
                /* 中央やや左の補助光 */
                radial-gradient(circle at 35% 48%, rgba(37, 99, 235, 0.08) 0%, rgba(37, 99, 235, 0.04) 22%, transparent 45%),
                
                /* 右中央の繊細な光 */
                radial-gradient(ellipse at 88% 50%, rgba(14, 165, 233, 0.07) 0%, rgba(14, 165, 233, 0.04) 28%, transparent 50%),
                
                /* 左中央の柔らかい光 */
                radial-gradient(ellipse at 12% 55%, rgba(30, 64, 175, 0.06) 0%, rgba(30, 64, 175, 0.03) 30%, transparent 52%),
                
                /* ベースの複雑なグラデーション */
                linear-gradient(145deg, 
                    rgba(37, 99, 235, 0.06) 0%,
                    rgba(30, 64, 175, 0.05) 12%,
                    rgba(59, 130, 246, 0.04) 25%,
                    transparent 38%,
                    rgba(14, 165, 233, 0.04) 52%,
                    transparent 65%,
                    rgba(99, 102, 241, 0.03) 78%,
                    rgba(59, 130, 246, 0.05) 90%,
                    rgba(37, 99, 235, 0.07) 100%
                );
        }

        /* Option 2: 微細なノイズテクスチャ（フィルムグレイン） */
        body::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0.4;
            background-image:
                /* 細かいノイズパターン1 */
                repeating-radial-gradient(
                    circle at 20% 30%,
                    transparent 0,
                    rgba(255, 255, 255, 0.008) 1px,
                    transparent 2px,
                    transparent 3px
                ),
                /* 細かいノイズパターン2 */
                repeating-radial-gradient(
                    circle at 80% 70%,
                    transparent 0,
                    rgba(59, 130, 246, 0.006) 1px,
                    transparent 2px,
                    transparent 3px
                ),
                /* 微細な横ラインテクスチャ */
                repeating-linear-gradient(
                    0deg,
                    transparent,
                    transparent 1px,
                    rgba(255, 255, 255, 0.004) 1px,
                    rgba(255, 255, 255, 0.004) 2px
                ),
                /* 微細な縦ラインテクスチャ */
                repeating-linear-gradient(
                    90deg,
                    transparent,
                    transparent 1px,
                    rgba(255, 255, 255, 0.003) 1px,
                    rgba(255, 255, 255, 0.003) 2px
                ),
                /* 斜めの微細なテクスチャ */
                repeating-linear-gradient(
                    45deg,
                    transparent,
                    transparent 2px,
                    rgba(59, 130, 246, 0.002) 2px,
                    rgba(59, 130, 246, 0.002) 3px
                ),
                /* ベースの柔らかいグラデーション */
                linear-gradient(
                    180deg,
                    rgba(255, 255, 255, 0.01) 0%,
                    transparent 50%,
                    rgba(0, 0, 0, 0.02) 100%
                );
        }

        /* メインコンテナ */
        #mx_loginbox {
            position: relative;
            z-index: 10;
            width: 460px;
            margin: 0;
        }

        /* ガラス効果のフォームコンテナ */
        form {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 
                0 8px 32px 0 rgba(37, 99, 235, 0.2),
                0 4px 16px 0 rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.05),
                inset 0 1px 1px 0 rgba(255, 255, 255, 0.08);
        }

        /* 内側のグロー効果 */
        form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent,
                rgba(255, 255, 255, 0.1) 30%,
                rgba(255, 255, 255, 0.1) 70%,
                transparent
            );
        }

        /* ヘッダー */
        .header {
            background: rgba(20, 24, 38, 0.6);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            padding: 24px;
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg,
                transparent,
                rgba(59, 130, 246, 0.3),
                transparent
            );
        }

        .header a {
            color: rgba(255, 255, 255, 0.95);
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            display: inline-block;
            opacity: 0.7;
        }

        .header a:hover {
            color: rgba(59, 130, 246, 1);
            text-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
            opacity: 1;
        }

        /* ボディ */
        .body {
            font-family: Helvetica, sans-serif;
            background: rgba(15, 18, 30, 0.55);
            backdrop-filter: blur(50px) saturate(160%);
            -webkit-backdrop-filter: blur(50px) saturate(160%);
            padding: 24px;
            position: relative;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        /* ロゴ - 非表示 */
        #logo {
            display: none;
        }

        /* ラベル - 左揃え */
        label {
            display: block;
            color: rgba(255, 255, 255, 0.85);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
            text-align: left;
        }

        /* 最初のラベル（ログイン名）は上マージンなし */
        label[for="username"] {
            margin-top: 0;
        }

        /* パスワードラベルに上マージン */
        label[for="password"] {
            margin-top: 16px;
        }

        /* インプットフィールド */
        input.text,
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 0;
            background: rgba(30, 35, 55, 0.4);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.95);
            font-size: 14px;
            letter-spacing: 3px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* ユーザー名フィールド */
        #username {
            margin-bottom: 0;
        }

        /* パスワードフィールド */
        #password {
            margin-bottom: 0;
        }

        /* パスワードフィールドラッパー（アイコン配置用） */
        .password-wrapper {
            position: relative;
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
            transition: opacity 0.3s ease;
            z-index: 10;
        }

        .toggle-password:hover {
            opacity: 0.8;
        }

        /* オートフィル時の黄色背景を上書き */
        input.text:-webkit-autofill,
        input[type="text"]:-webkit-autofill,
        input[type="password"]:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px rgba(30, 35, 55, 0.9) inset !important;
            -webkit-text-fill-color: rgba(255, 255, 255, 0.95) !important;
            border: 1px solid rgba(59, 130, 246, 0.4) !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        input.text:-webkit-autofill:focus,
        input[type="text"]:-webkit-autofill:focus,
        input[type="password"]:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px rgba(30, 35, 55, 0.95) inset !important;
            border: 1px solid rgba(59, 130, 246, 0.5) !important;
        }

        input.text::placeholder,
        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        input.text:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            background: rgba(30, 35, 55, 0.6);
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 
                0 0 0 3px rgba(59, 130, 246, 0.1),
                0 4px 12px rgba(37, 99, 235, 0.15);
        }

        /* Captcha画像 - 左寄せ */
        img.loginCaptcha {
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 8px;
            margin: 0 0 8px 0;
            width: 148px;
            height: 60px;
            display: block;
        }

        /* Captcha入力フィールド */
        input[name="captcha_code"] {
            margin-bottom: 0;
        }

        /* Captchaラベルに上マージン */
        label[for="captcha_code"] {
            margin-top: 16px;
        }

        /* チェックボックスセクション全体の上マージン */
        .password-wrapper {
            margin-bottom: 24px;
        }

        /* チェックボックス - 左揃え */
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
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            cursor: pointer;
            display: inline;
            margin-top: 0;
            margin-bottom: 0;
            line-height: 20px;
            vertical-align: middle;
        }

        /* ログインボタン - ガラス効果で質感を統一、センタリング */
        input.login,
        input[type="submit"] {
            width: 100%;
            max-width: 360px;
            display: block;
            margin: 24px auto 0;
            padding: 16px 32px;
            background: rgba(37, 99, 235, 0.15);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.95);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 
                0 4px 16px rgba(37, 99, 235, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 0 0 rgba(59, 130, 246, 0);
        }

        input.login:hover,
        input[type="submit"]:hover {
            background: rgba(37, 99, 235, 0.25);
            border-color: rgba(59, 130, 246, 0.6);
            box-shadow: 
                0 6px 24px rgba(37, 99, 235, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 20px rgba(59, 130, 246, 0.3);
            transform: translateY(-1px);
        }

        input.login:active,
        input[type="submit"]:active {
            transform: translateY(0);
            background: rgba(37, 99, 235, 0.3);
            box-shadow: 
                0 2px 8px rgba(37, 99, 235, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 12px rgba(59, 130, 246, 0.2);
        }

        /* メッセージ - 左揃え */
        .loginMessage {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            padding-bottom: 0;
            margin-bottom: 16px;
            line-height: 1.6;
            text-align: left;
        }

        /* プラグインで追加されるリンク（パスワード忘れなど） - センタリング */
        .body a {
            color: rgba(59, 130, 246, 0.85);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            display: block;
            text-align: center;
            margin-top: 24px;
        }

        .body a:hover {
            color: rgba(59, 130, 246, 1);
            text-shadow: 0 0 8px rgba(59, 130, 246, 0.4);
        }

        .warning {
            color: rgba(251, 113, 133, 0.9);
            font-weight: 500;
            padding: 12px;
            background: rgba(251, 113, 133, 0.1);
            border-left: 3px solid rgba(251, 113, 133, 0.5);
            border-radius: 6px;
            margin-bottom: 16px;
            text-align: left;
        }

        .success {
            color: rgba(74, 222, 128, 0.9);
            font-weight: 500;
            padding: 12px;
            background: rgba(74, 222, 128, 0.1);
            border-left: 3px solid rgba(74, 222, 128, 0.5);
            border-radius: 6px;
            margin-bottom: 16px;
            text-align: left;
        }

        /* フッター */
        .loginLicense {
            text-align: center;
            color: rgba(255, 255, 255, 0.3);
            font-size: 12px;
            margin-top: 32px;
            padding: 0;
            line-height: 1.6;
            max-width: 460px;
            width: 100%;
        }

        .loginLicense a {
            color: rgba(59, 130, 246, 0.6);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .loginLicense a:hover {
            color: rgba(59, 130, 246, 0.9);
        }

        /* レスポンシブ対応 */
        @media (max-width: 520px) {
            body {
                padding: 24px 16px;
            }

            #mx_loginbox {
                width: 100%;
                max-width: 400px;
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
                max-width: 400px;
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

<!-- convert this to a language include -->
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
            toggleIcon.textContent = '🙈'; // 非表示アイコン
        } else {
            passwordField.type = 'password';
            toggleIcon.textContent = '👁️'; // 表示アイコン
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
