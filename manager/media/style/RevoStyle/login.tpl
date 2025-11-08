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
            background: linear-gradient(135deg, #0f2027 0%, #2c5364 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            bottom: -50%;
            left: -50%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            animation: gradient-shift 15s ease infinite;
            z-index: 0;
        }

        @keyframes gradient-shift {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(180deg); }
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
            background: linear-gradient(135deg, #0f2027 0%, #2c5364 100%);
            padding: 24px 32px;
            text-align: center;
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
            padding: 40px 32px;
            background: #ffffff;
        }

        #logo {
            display: block;
            max-width: 200px;
            height: auto;
            margin: 0 auto 24px;
        }

        /* Message */
        .loginMessage {
            color: #666;
            font-size: 14px;
            text-align: center;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .warning {
            color: #dc3545;
            font-weight: 600;
        }

        .success {
            color: #28a745;
            font-weight: 600;
        }

        /* Form Elements */
        label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }

        input.text {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            color: #333;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input.text:focus {
            outline: none;
            background: #ffffff;
            border-color: #2c5364;
            box-shadow: 0 0 0 3px rgba(44, 83, 100, 0.1);
        }

        input.text:hover {
            border-color: #dee2e6;
        }

        /* Captcha */
        img.loginCaptcha {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            width: 148px;
            height: 60px;
            margin-bottom: 12px;
        }

        /* Checkbox */
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }

        input.checkbox {
            width: 18px;
            height: 18px;
            margin: 0 8px 0 0;
            cursor: pointer;
            accent-color: #2c5364;
        }

        label[for="rememberme"] {
            margin: 0;
            cursor: pointer;
            font-weight: 400;
            user-select: none;
        }

        /* Submit Button */
        input.login {
            width: 100%;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, #0f2027 0%, #2c5364 100%);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(44, 83, 100, 0.4);
        }

        input.login:hover {
            background: linear-gradient(135deg, #1a3a47 0%, #3d6a7a 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(44, 83, 100, 0.5);
        }

        input.login:active {
            transform: translateY(0);
        }

        /* License */
        .loginLicense {
            width: 100%;
            max-width: 440px;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            font-size: 13px;
            margin-top: 20px;
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
                padding: 20px 24px;
            }

            .body {
                padding: 32px 24px;
            }

            input.text {
                font-size: 16px; /* Prevent zoom on iOS */
            }

            .loginLicense {
                font-size: 12px;
                padding: 0 16px;
            }
        }

        /* Loading State */
        input.login.loading {
            opacity: 0.6;
            pointer-events: none;
            transform: translateY(0);
        }

        input.login.loading:hover {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(44, 83, 100, 0.4);
        }

        /* Plugin Content Spacing */
        .body > div:not(.header):not(.loginMessage) {
            margin-bottom: 16px;
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
