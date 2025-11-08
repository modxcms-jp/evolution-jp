<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="[+modx_charset+]">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>MODX CMF Manager Login</title>
    <link rel="stylesheet" type="text/css" href="media/style/[+theme+]/style.css"/>
    <style type="text/css">
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
            font-size: 16px;
            line-height: 1.6;
        }

        body {
            background: 
                radial-gradient(ellipse at 10% 15%, rgba(90, 140, 160, 0.12) 0%, transparent 35%),
                radial-gradient(ellipse at 90% 85%, rgba(10, 25, 32, 0.25) 0%, transparent 38%),
                radial-gradient(ellipse at 30% 70%, rgba(44, 83, 100, 0.08) 0%, transparent 45%),
                radial-gradient(ellipse at 70% 30%, rgba(15, 32, 39, 0.15) 0%, transparent 42%),
                radial-gradient(ellipse at 50% 50%, rgba(60, 100, 120, 0.06) 0%, transparent 55%),
                linear-gradient(165deg, rgba(15, 32, 39, 0.4) 0%, transparent 40%),
                linear-gradient(200deg, rgba(44, 83, 100, 0.3) 0%, transparent 50%),
                linear-gradient(135deg, 
                    #08151a 0%, 
                    #0a1a20 15%, 
                    #0f2027 30%, 
                    #1a3540 50%, 
                    #2c5364 70%, 
                    #3a6a7e 85%, 
                    #487a90 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Mandala Pattern - Layer 1 (Inner circles) */
        body::before {
            content: '';
            position: absolute;
            top: -30%;
            left: -30%;
            width: 160%;
            height: 160%;
            background: 
                radial-gradient(
                    ellipse 100% 100% at 50% 50%,
                    transparent 20%,
                    rgba(255, 255, 255, 0.08) 20.05%,
                    rgba(255, 255, 255, 0.08) 20.1%,
                    transparent 20.2%
                ),
                radial-gradient(
                    ellipse 100% 100% at 50% 50%,
                    transparent 32%,
                    rgba(255, 255, 255, 0.075) 32.05%,
                    rgba(255, 255, 255, 0.075) 32.1%,
                    transparent 32.2%
                ),
                radial-gradient(
                    ellipse 100% 100% at 50% 50%,
                    transparent 44%,
                    rgba(255, 255, 255, 0.073) 44.05%,
                    rgba(255, 255, 255, 0.073) 44.1%,
                    transparent 44.2%
                ),
                radial-gradient(
                    ellipse 100% 100% at 50% 50%,
                    transparent 56%,
                    rgba(255, 255, 255, 0.07) 56.05%,
                    rgba(255, 255, 255, 0.07) 56.1%,
                    transparent 56.2%
                );
            z-index: 0;
            pointer-events: none;
        }

        /* Mandala Pattern - Layer 2 (Radiating curves) */
        body::after {
            content: '';
            position: absolute;
            top: -30%;
            left: -30%;
            width: 160%;
            height: 160%;
            background: 
                radial-gradient(
                    ellipse 140% 80% at 50% 50%,
                    transparent 26%,
                    rgba(255, 255, 255, 0.069) 26.05%,
                    rgba(255, 255, 255, 0.069) 26.1%,
                    transparent 26.2%
                ),
                radial-gradient(
                    ellipse 80% 140% at 50% 50%,
                    transparent 26%,
                    rgba(255, 255, 255, 0.069) 26.05%,
                    rgba(255, 255, 255, 0.069) 26.1%,
                    transparent 26.2%
                ),
                radial-gradient(
                    ellipse 120% 90% at 50% 50%,
                    transparent 38%,
                    rgba(255, 255, 255, 0.071) 38.05%,
                    rgba(255, 255, 255, 0.071) 38.1%,
                    transparent 38.2%
                ),
                radial-gradient(
                    ellipse 90% 120% at 50% 50%,
                    transparent 38%,
                    rgba(255, 255, 255, 0.071) 38.05%,
                    rgba(255, 255, 255, 0.071) 38.1%,
                    transparent 38.2%
                ),
                radial-gradient(
                    ellipse 130% 85% at 50% 50%,
                    transparent 50%,
                    rgba(255, 255, 255, 0.068) 50.05%,
                    rgba(255, 255, 255, 0.068) 50.1%,
                    transparent 50.2%
                ),
                radial-gradient(
                    ellipse 85% 130% at 50% 50%,
                    transparent 50%,
                    rgba(255, 255, 255, 0.068) 50.05%,
                    rgba(255, 255, 255, 0.068) 50.1%,
                    transparent 50.2%
                );
            transform: rotate(22.5deg);
            z-index: 0;
            pointer-events: none;
        }

        /* Main Container */
        #mx_loginbox {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .header {
            background: 
                linear-gradient(135deg, rgba(15, 32, 39, 0.95) 0%, rgba(44, 83, 100, 0.95) 100%),
                radial-gradient(ellipse at 20% 30%, rgba(76, 120, 140, 0.3) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(15, 32, 39, 0.4) 0%, transparent 50%),
                linear-gradient(135deg, #0f2027 0%, #2c5364 100%);
            padding: 32px;
            text-align: center;
            position: relative;
        }

        .header a {
            color: #ffffff;
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: opacity 0.3s ease;
        }

        .header a:hover {
            opacity: 0.9;
        }

        /* Body */
        .body {
            padding: 32px;
            background: #ffffff;
        }

        #mx_loginbox #logo {
            display: block !important;
            max-width: 200px !important;
            height: auto !important;
            margin: 0 auto 32px !important;
        }

        /* Message */
        #mx_loginbox .loginMessage {
            color: #666 !important;
            font-size: 14px !important;
            text-align: center !important;
            margin-bottom: 32px !important;
            line-height: 1.5 !important;
        }

        #mx_loginbox .warning {
            color: #dc3545 !important;
            font-weight: 600 !important;
        }

        #mx_loginbox .success {
            color: #28a745 !important;
            font-weight: 600 !important;
        }

        /* Form Elements */
        #mx_loginbox label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            margin-top: 0;
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }

        /* 2つ目以降のラベルには上部に余白を追加 */
        #mx_loginbox label:not(:first-of-type) {
            margin-top: 24px;
        }

        #mx_loginbox input.text,
        #mx_loginbox input[type="text"],
        #mx_loginbox input[type="password"] {
            width: 100% !important;
            padding: 12px 16px !important;
            font-size: 15px !important;
            color: #333 !important;
            background: #f8f9fa !important;
            border: 2px solid #e9ecef !important;
            border-radius: 8px !important;
            margin: 0 !important;
            transition: all 0.3s ease !important;
            font-family: inherit !important;
            box-sizing: border-box !important;
        }

        #mx_loginbox input.text:focus,
        #mx_loginbox input[type="text"]:focus,
        #mx_loginbox input[type="password"]:focus {
            outline: none !important;
            background: #ffffff !important;
            border-color: #2c5364 !important;
            box-shadow: 0 0 0 3px rgba(44, 83, 100, 0.1) !important;
        }

        #mx_loginbox input.text:hover,
        #mx_loginbox input[type="text"]:hover,
        #mx_loginbox input[type="password"]:hover {
            border-color: #dee2e6 !important;
        }

        /* ブラウザの自動補完スタイルを上書き */
        #mx_loginbox input.text:-webkit-autofill,
        #mx_loginbox input[type="text"]:-webkit-autofill,
        #mx_loginbox input[type="password"]:-webkit-autofill,
        #mx_loginbox input.text:-webkit-autofill:hover,
        #mx_loginbox input[type="text"]:-webkit-autofill:hover,
        #mx_loginbox input[type="password"]:-webkit-autofill:hover,
        #mx_loginbox input.text:-webkit-autofill:focus,
        #mx_loginbox input[type="text"]:-webkit-autofill:focus,
        #mx_loginbox input[type="password"]:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px #f8f9fa inset !important;
            -webkit-text-fill-color: #333 !important;
            transition: background-color 5000s ease-in-out 0s !important;
            border: 2px solid #e9ecef !important;
        }

        /* Captcha */
        #mx_loginbox img.loginCaptcha {
            border: 2px solid #e9ecef !important;
            border-radius: 8px !important;
            width: 148px !important;
            height: 60px !important;
            margin-top: 24px !important;
            margin-bottom: 0 !important;
            display: block !important;
        }

        /* Captcha Message */
        #mx_loginbox .loginCaptchaMessage {
            margin-top: 24px !important;
            margin-bottom: 16px !important;
            color: #666 !important;
            font-size: 14px !important;
        }

        /* Captcha Input Field */
        #mx_loginbox input[name="captcha_code"] {
            margin-top: 16px !important;
        }

        /* Checkbox */
        #mx_loginbox .checkbox-wrapper {
            display: flex;
            align-items: center;
            margin-top: 24px;
            margin-bottom: 24px;
        }

        #mx_loginbox input.checkbox,
        #mx_loginbox input[type="checkbox"] {
            width: 18px !important;
            height: 18px !important;
            margin: 0 8px 0 0 !important;
            cursor: pointer !important;
            accent-color: #2c5364 !important;
        }

        #mx_loginbox label[for="rememberme"] {
            margin: 0 !important;
            cursor: pointer;
            font-weight: 400;
            user-select: none;
        }

        /* Submit Button */
        #mx_loginbox input.login,
        #mx_loginbox input[type="submit"] {
            width: 100% !important;
            padding: 16px 24px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            background: linear-gradient(135deg, #0f2027 0%, #2c5364 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 12px rgba(44, 83, 100, 0.4) !important;
            margin-top: 8px !important;
            margin-bottom: 0 !important;
        }

        /* Plugin content after submit button */
        #mx_loginbox input.login + * {
            margin-top: 16px !important;
        }

        #mx_loginbox input.login:hover,
        #mx_loginbox input[type="submit"]:hover {
            background: linear-gradient(135deg, #1a3a47 0%, #3d6a7a 100%) !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(44, 83, 100, 0.5) !important;
        }

        #mx_loginbox input.login:active,
        #mx_loginbox input[type="submit"]:active {
            transform: translateY(0);
        }

        /* License */
        .loginLicense {
            width: 100%;
            max-width: 440px;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            font-size: 13px;
            margin-top: 32px;
            padding: 0 20px;
            line-height: 1.6;
        }

        .loginLicense a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .loginLicense a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            #mx_loginbox {
                border-radius: 12px;
            }

            .header {
                padding: 24px;
            }

            .body {
                padding: 24px;
            }

            #mx_loginbox #logo {
                margin-bottom: 24px !important;
            }

            #mx_loginbox .loginMessage {
                margin-bottom: 24px !important;
            }

            #mx_loginbox label:not(:first-of-type) {
                margin-top: 20px !important;
            }

            #mx_loginbox .checkbox-wrapper {
                margin-top: 20px !important;
                margin-bottom: 20px !important;
            }

            #mx_loginbox img.loginCaptcha {
                margin-top: 20px !important;
            }

            #mx_loginbox .loginCaptchaMessage {
                margin-top: 20px !important;
            }

            #mx_loginbox input.text,
            #mx_loginbox input[type="text"],
            #mx_loginbox input[type="password"] {
                font-size: 16px !important; /* Prevent zoom on iOS */
            }

            .loginLicense {
                font-size: 12px;
                padding: 0 16px;
                margin-top: 24px;
            }
        }

        /* Loading State */
        #mx_loginbox input.login.loading,
        #mx_loginbox input[type="submit"].loading {
            opacity: 0.6 !important;
            pointer-events: none !important;
            transform: translateY(0) !important;
        }

        #mx_loginbox input.login.loading:hover,
        #mx_loginbox input[type="submit"].loading:hover {
            transform: translateY(0) !important;
            box-shadow: 0 4px 12px rgba(44, 83, 100, 0.4) !important;
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
        <div class="header">
            <a href="../">[+site_name+]</a>
        </div>
        <div class="body">
            <img src="media/style/[+theme+]/images/misc/login-logo.png" alt="[+site_name+]" id="logo"/>
            <p class="loginMessage">[+login_message+]</p>
            <label for="username">[+username+]</label>
            <input type="text" class="text" name="username" id="username" tabindex="1" value="[+uid+]" autocomplete="username"/>
            <label for="password">[+password+]</label>
            <input type="password" class="text" name="password" id="password" tabindex="2" value="" autocomplete="current-password"/>
            [+login_captcha_message+]
            [+captcha_image+]
            [+captcha_input+]
            <div class="checkbox-wrapper">
                <input type="checkbox" id="rememberme" name="rememberme" tabindex="4" value="1" class="checkbox" [+remember_me+]/>
                <label for="rememberme">[+remember_username+]</label>
            </div>
            <input type="submit" class="login" id="submitButton" value="[+login_button+]"/>
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
    $('#submitButton').click(function (e) {
        e.preventDefault(); // Prevent form submission

        var $form = $('#loginfrm');
        var $button = $(this);
        var username = $('#username').val();
        var password = $('#password').val();
        var rememberme = $('#rememberme').is(':checked') ? 1 : 0;
        var captcha_code = $('input[name="captcha_code"]').val();

        // Add loading state
        $button.addClass('loading').val('Loading...');

        params = {
            'username': username,
            'password': password,
            'rememberme': rememberme,
            'ajax': '1',
            'captcha_code': captcha_code
        };

        $.post('processors/login.processor.php', params, function (response) {
            var header = response.substr(0, 9);
            if (header.toLowerCase() == 'location:') {
                top.location = response.substr(10);
            } else {
                // Remove loading state
                $button.removeClass('loading').val('[+login_button+]');
                var cimg = document.getElementById('captcha_image');
                if (cimg) cimg.src = '../index.php?get=captcha';
                jAlert(response);
            }
        }).fail(function() {
            // Remove loading state on error
            $button.removeClass('loading').val('[+login_button+]');
        });
    });

    if ($('#username').val() != '') {
        $('#password').focus();
    } else {
        $('#username').focus();
    }
</script>
</body>
</html>
