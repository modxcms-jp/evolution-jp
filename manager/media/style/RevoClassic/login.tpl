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
            font-family: sans-serif;
            font-size: 16px;
            line-height: 1.6;
        }

        body {
            background: 
                /* Ultra massive gradient shapes - Primary light area */
                radial-gradient(ellipse 80% 95% at 8% 12%, 
                    rgba(140, 200, 220, 0.65) 0%, 
                    rgba(130, 190, 210, 0.58) 9%,
                    rgba(120, 180, 200, 0.52) 16%,
                    rgba(110, 170, 190, 0.46) 23%,
                    rgba(100, 160, 180, 0.41) 30%,
                    rgba(90, 150, 170, 0.36) 37%,
                    rgba(80, 140, 160, 0.3) 44%, 
                    rgba(70, 130, 150, 0.25) 51%,
                    rgba(60, 120, 140, 0.2) 58%,
                    rgba(50, 110, 130, 0.14) 66%,
                    transparent 80%),
                
                /* Ultra massive gradient shapes - Primary dark area */
                radial-gradient(ellipse 85% 90% at 92% 88%, 
                    rgba(0, 2, 5, 0.92) 0%, 
                    rgba(1, 4, 8, 0.86) 8%,
                    rgba(2, 6, 12, 0.8) 15%,
                    rgba(3, 8, 15, 0.75) 22%,
                    rgba(5, 11, 18, 0.7) 28%,
                    rgba(6, 14, 22, 0.65) 34%,
                    rgba(8, 17, 25, 0.6) 40%,
                    rgba(10, 20, 28, 0.54) 46%,
                    rgba(12, 23, 31, 0.48) 52%, 
                    rgba(14, 27, 35, 0.42) 60%,
                    rgba(16, 31, 39, 0.34) 70%,
                    transparent 85%),
                
                /* Secondary large gradients - Layer 1 */
                radial-gradient(ellipse 68% 82% at 18% 68%, 
                    rgba(70, 122, 142, 0.56) 0%, 
                    rgba(64, 114, 134, 0.48) 14%,
                    rgba(58, 106, 126, 0.41) 26%,
                    rgba(52, 98, 118, 0.35) 38%,
                    rgba(46, 90, 110, 0.29) 50%,
                    rgba(40, 82, 102, 0.23) 62%,
                    rgba(34, 74, 94, 0.17) 74%,
                    transparent 84%),
                radial-gradient(ellipse 74% 78% at 82% 32%, 
                    rgba(8, 19, 28, 0.82) 0%, 
                    rgba(11, 23, 33, 0.74) 12%,
                    rgba(14, 27, 37, 0.67) 23%,
                    rgba(17, 31, 41, 0.6) 34%,
                    rgba(20, 35, 45, 0.53) 45%,
                    rgba(23, 39, 49, 0.46) 56%,
                    rgba(26, 43, 53, 0.38) 67%,
                    rgba(29, 47, 57, 0.3) 78%,
                    transparent 87%),
                
                /* Secondary large gradients - Layer 2 */
                radial-gradient(ellipse 64% 76% at 35% 85%, 
                    rgba(56, 104, 124, 0.5) 0%, 
                    rgba(51, 97, 117, 0.43) 16%,
                    rgba(46, 90, 110, 0.37) 30%,
                    rgba(41, 83, 103, 0.31) 44%,
                    rgba(36, 76, 96, 0.25) 58%,
                    rgba(31, 69, 89, 0.19) 72%,
                    transparent 84%),
                radial-gradient(ellipse 70% 68% at 72% 18%, 
                    rgba(12, 26, 36, 0.76) 0%, 
                    rgba(15, 30, 40, 0.68) 14%,
                    rgba(18, 34, 44, 0.61) 27%,
                    rgba(21, 38, 48, 0.54) 40%,
                    rgba(24, 42, 52, 0.47) 53%,
                    rgba(27, 46, 56, 0.39) 66%,
                    rgba(30, 50, 60, 0.31) 79%,
                    transparent 88%),
                
                /* Tertiary mid-size gradients - Multiple positions */
                radial-gradient(ellipse 58% 68% at 28% 48%, 
                    rgba(78, 132, 152, 0.44) 0%,
                    rgba(70, 120, 140, 0.36) 22%,
                    rgba(62, 108, 128, 0.29) 42%,
                    rgba(54, 96, 116, 0.22) 62%,
                    transparent 78%),
                radial-gradient(ellipse 62% 64% at 72% 52%, 
                    rgba(10, 22, 32, 0.66) 0%,
                    rgba(14, 28, 38, 0.56) 20%,
                    rgba(18, 34, 44, 0.46) 40%,
                    rgba(22, 40, 50, 0.36) 60%,
                    transparent 76%),
                radial-gradient(ellipse 54% 62% at 48% 28%, 
                    rgba(65, 115, 135, 0.38) 0%,
                    rgba(58, 104, 124, 0.3) 24%,
                    rgba(51, 93, 113, 0.23) 46%,
                    transparent 68%),
                radial-gradient(ellipse 60% 56% at 52% 72%, 
                    rgba(16, 32, 42, 0.58) 0%,
                    rgba(20, 38, 48, 0.48) 22%,
                    rgba(24, 44, 54, 0.38) 44%,
                    rgba(28, 50, 60, 0.28) 66%,
                    transparent 82%),
                
                /* Small accent gradients - Creating micro-variations */
                radial-gradient(ellipse 42% 52% at 22% 32%, 
                    rgba(88, 145, 165, 0.32) 0%,
                    rgba(76, 130, 150, 0.24) 28%,
                    rgba(64, 115, 135, 0.16) 54%,
                    transparent 74%),
                radial-gradient(ellipse 48% 46% at 78% 68%, 
                    rgba(8, 18, 28, 0.54) 0%,
                    rgba(12, 24, 34, 0.42) 26%,
                    rgba(16, 30, 40, 0.3) 52%,
                    transparent 72%),
                radial-gradient(ellipse 44% 50% at 38% 62%, 
                    rgba(60, 108, 128, 0.36) 0%,
                    rgba(52, 96, 116, 0.27) 26%,
                    rgba(44, 84, 104, 0.18) 52%,
                    transparent 72%),
                radial-gradient(ellipse 50% 48% at 62% 38%, 
                    rgba(14, 28, 38, 0.48) 0%,
                    rgba(18, 34, 44, 0.37) 24%,
                    rgba(22, 40, 50, 0.26) 48%,
                    transparent 68%),
                
                /* Complex directional sweeps - 8 directions */
                linear-gradient(140deg, 
                    rgba(6, 16, 26, 0.9) 0%, 
                    rgba(10, 22, 32, 0.78) 11%,
                    rgba(14, 28, 38, 0.67) 21%,
                    rgba(18, 34, 44, 0.57) 31%, 
                    rgba(22, 40, 50, 0.47) 41%,
                    rgba(26, 46, 56, 0.38) 52%,
                    rgba(30, 52, 62, 0.29) 64%,
                    rgba(34, 58, 68, 0.2) 77%,
                    transparent 88%),
                linear-gradient(175deg, 
                    rgba(82, 140, 160, 0.82) 0%, 
                    rgba(76, 132, 152, 0.72) 12%,
                    rgba(70, 124, 144, 0.63) 23%,
                    rgba(64, 116, 136, 0.55) 34%,
                    rgba(58, 108, 128, 0.47) 45%,
                    rgba(52, 100, 120, 0.39) 56%,
                    rgba(46, 92, 112, 0.31) 67%,
                    rgba(40, 84, 104, 0.23) 78%,
                    transparent 87%),
                linear-gradient(220deg, 
                    rgba(2, 6, 12, 0.94) 0%, 
                    rgba(4, 10, 18, 0.84) 11%,
                    rgba(6, 14, 22, 0.75) 21%,
                    rgba(8, 18, 26, 0.66) 31%,
                    rgba(10, 22, 30, 0.58) 41%,
                    rgba(12, 26, 34, 0.49) 52%,
                    rgba(15, 30, 38, 0.4) 64%,
                    rgba(18, 35, 43, 0.31) 77%,
                    transparent 89%),
                linear-gradient(265deg, 
                    rgba(4, 12, 20, 0.88) 0%, 
                    rgba(7, 17, 26, 0.77) 13%,
                    rgba(10, 22, 31, 0.67) 25%,
                    rgba(13, 27, 36, 0.58) 37%,
                    rgba(16, 32, 41, 0.49) 49%,
                    rgba(19, 37, 46, 0.4) 62%,
                    rgba(22, 42, 51, 0.31) 75%,
                    transparent 86%),
                linear-gradient(50deg, 
                    rgba(54, 98, 118, 0.68) 0%, 
                    rgba(50, 92, 112, 0.58) 15%,
                    rgba(46, 86, 106, 0.49) 29%,
                    rgba(42, 80, 100, 0.41) 43%,
                    rgba(38, 74, 94, 0.33) 57%,
                    rgba(34, 68, 88, 0.25) 71%,
                    transparent 83%),
                linear-gradient(95deg, 
                    rgba(44, 82, 102, 0.62) 0%, 
                    rgba(40, 76, 96, 0.53) 16%,
                    rgba(36, 70, 90, 0.44) 31%,
                    rgba(32, 64, 84, 0.36) 46%,
                    rgba(28, 58, 78, 0.28) 61%,
                    transparent 74%),
                linear-gradient(310deg, 
                    rgba(62, 110, 130, 0.58) 0%, 
                    rgba(56, 102, 122, 0.49) 17%,
                    rgba(50, 94, 114, 0.41) 33%,
                    rgba(44, 86, 106, 0.33) 49%,
                    rgba(38, 78, 98, 0.25) 65%,
                    transparent 79%),
                linear-gradient(355deg, 
                    rgba(20, 38, 48, 0.72) 0%, 
                    rgba(24, 44, 54, 0.61) 18%,
                    rgba(28, 50, 60, 0.51) 34%,
                    rgba(32, 56, 66, 0.41) 50%,
                    rgba(36, 62, 72, 0.31) 66%,
                    transparent 80%),
                
                /* Enhanced primary sources with more stops */
                radial-gradient(ellipse at 3% 6%, 
                    rgba(165, 225, 245, 0.72) 0%, 
                    rgba(152, 212, 232, 0.64) 10%,
                    rgba(140, 200, 220, 0.57) 19%,
                    rgba(128, 188, 208, 0.5) 27%, 
                    rgba(116, 176, 196, 0.43) 35%, 
                    rgba(104, 164, 184, 0.36) 43%,
                    rgba(92, 152, 172, 0.3) 51%,
                    rgba(80, 140, 160, 0.24) 59%,
                    rgba(68, 128, 148, 0.18) 67%,
                    rgba(56, 116, 136, 0.12) 75%,
                    transparent 85%),
                radial-gradient(ellipse at 97% 94%, 
                    rgba(0, 0, 2, 0.96) 0%, 
                    rgba(1, 2, 6, 0.9) 9%,
                    rgba(2, 5, 10, 0.84) 17%,
                    rgba(3, 8, 14, 0.78) 25%,
                    rgba(5, 11, 18, 0.72) 32%, 
                    rgba(7, 14, 22, 0.66) 39%, 
                    rgba(9, 18, 26, 0.6) 46%,
                    rgba(12, 22, 30, 0.54) 53%,
                    rgba(15, 26, 34, 0.47) 61%,
                    rgba(18, 30, 38, 0.4) 69%,
                    rgba(21, 35, 43, 0.32) 78%,
                    transparent 88%),
                
                /* Multi-stage complex vignette */
                radial-gradient(ellipse 135% 135% at 50% 50%, 
                    transparent 0%, 
                    transparent 12%,
                    rgba(8, 16, 24, 0.08) 26%,
                    rgba(6, 14, 22, 0.18) 38%,
                    rgba(5, 12, 20, 0.3) 49%, 
                    rgba(4, 10, 18, 0.43) 59%,
                    rgba(3, 8, 15, 0.56) 69%,
                    rgba(2, 6, 12, 0.69) 79%,
                    rgba(1, 4, 8, 0.8) 89%,
                    rgba(0, 2, 4, 0.88) 100%),
                
                /* Core gradient with 30 stops */
                linear-gradient(135deg, 
                    #000306 0%,
                    #010508 3%,
                    #020a0e 6%,
                    #030b10 9%,
                    #050f14 12%,
                    #061115 15%,
                    #08151a 18%, 
                    #09171d 21%,
                    #0a1a20 24%, 
                    #0c1d25 27%,
                    #0d1f28 30%,
                    #0e2129 33%,
                    #0f2027 36%, 
                    #11242b 39%,
                    #12252c 42%,
                    #14272e 45%,
                    #15282f 48%,
                    #172b35 52%,
                    #182d38 56%,
                    #1a3540 60%, 
                    #1d3b47 64%,
                    #1f3e4a 67%,
                    #22434f 70%,
                    #244555 73%,
                    #27505c 76%,
                    #284d5d 79%,
                    #2c5364 82%, 
                    #305a6a 85%,
                    #35606f 88%,
                    #386975 91%,
                    #3a6a7e 94%, 
                    #3f7084 96%,
                    #427488 98%,
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
        
        /* Mandala Pattern with enhanced depth */
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

        /* Mandala Pattern - Layer 2 with extreme complexity */
        body::after {
            content: '';
            position: absolute;
            top: -30%;
            left: -30%;
            width: 160%;
            height: 160%;
            background: 
                /* Additional massive gradient clouds with multiple stops */
                radial-gradient(ellipse 70% 82% at 38% 48%, 
                    rgba(85, 140, 160, 0.48) 0%, 
                    rgba(75, 128, 148, 0.4) 16%,
                    rgba(66, 116, 136, 0.32) 30%,
                    rgba(56, 104, 124, 0.26) 44%,
                    rgba(46, 92, 112, 0.2) 56%,
                    rgba(36, 80, 100, 0.14) 68%,
                    transparent 80%),
                radial-gradient(ellipse 76% 72% at 68% 60%, 
                    rgba(6, 14, 22, 0.75) 0%, 
                    rgba(9, 19, 28, 0.66) 14%,
                    rgba(12, 24, 34, 0.58) 26%,
                    rgba(15, 29, 39, 0.5) 38%,
                    rgba(18, 34, 44, 0.42) 50%,
                    rgba(21, 39, 49, 0.34) 62%,
                    rgba(24, 44, 54, 0.26) 74%,
                    transparent 84%),
                
                /* Mandala curves with enhanced gradients */
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
            padding: 24px;
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
            padding: 24px;
            background: #ffffff;
        }

        #mx_loginbox #logo {
            display: block !important;
            max-width: 200px !important;
            height: auto !important;
            margin: 0 auto 16px !important;
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
            margin-top: 16px;
            margin-bottom: 8px;
            transition: color 0.3s ease;
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
            margin-top: 8px;
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
            margin: 8px 0 0 0 !important;
        }

        /* Plugin content after submit button */
        #mx_loginbox input.login + * {
            margin-top: 8px !important;
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
            margin-top: 16px;
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
                margin-bottom: 16px !important;
            }

            #mx_loginbox label:not(:first-of-type) {
                margin-top: 16px !important;
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
            <img src="[+style_misc_path+]login-logo.png" alt="[+site_name+]" id="logo"/>
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

        var params = {
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
