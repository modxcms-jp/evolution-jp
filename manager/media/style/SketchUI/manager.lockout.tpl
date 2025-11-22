<!DOCTYPE html>
<html lang="ja">
<head>
    <title>MODX Content Manager</title>
    <meta http-equiv="content-type" content="text/html; charset=[+modx_charset+]"/>
    <meta name="robots" content="noindex, nofollow"/>
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body { width: 100%; height: 100%; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            background: var(--paper);
            color: var(--ink);
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 30% 18%, rgba(47, 42, 50, 0.035), transparent 42%),
                radial-gradient(circle at 78% 16%, rgba(47, 42, 50, 0.025), transparent 38%),
                radial-gradient(circle at 50% 82%, rgba(47, 42, 50, 0.03), transparent 40%);
            opacity: 0.6;
            z-index: 0;
        }

        #mx_loginbox {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            background: #fffef8;
            border: var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 24px 22px 20px;
        }

        .header {
            margin-bottom: 12px;
            border-bottom: var(--border-light);
            padding-bottom: 8px;
            font-weight: 700;
            font-size: 20px;
        }

        .loginMessage {
            background: var(--paper-deep);
            border: var(--border-light);
            border-radius: 14px;
            padding: 16px 14px;
            line-height: 1.6;
            color: var(--ink-soft);
            margin-bottom: 16px;
        }

        fieldset {
            border: none;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        input[type="button"] {
            flex: 1 1 140px;
            padding: 12px 14px;
            border-radius: 14px;
            border: var(--border);
            background: #fff;
            color: var(--ink);
            font-weight: 700;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: transform 80ms ease, box-shadow 80ms ease;
        }

        input[type="button"]:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-strong);
        }

        .loginLicense {
            text-align: center;
            margin-top: 18px;
            color: rgba(47, 42, 50, 0.65);
            font-size: 12px;
            line-height: 1.6;
        }

        .loginLicense a {
            color: var(--ink);
            text-decoration: none;
        }
    </style>

    <script type="text/javascript">
        function doLogout() {
            top.location = '[+logouturl+]';
        }

        function gotoHome() {
            top.location = '[+homeurl+]';
        }

        if (top.frames.length !== 0) {
            top.location = self.document.location;
        }
    </script>
</head>
<body id="login">
<div id="mx_loginbox">
    <div class="header">MODX Manager</div>
    <div class="loginMessage">[+manager_lockout_message+]</div>
    <fieldset>
        <input type="button" id="homeButton" value="[+home+]" onclick="return gotoHome();"/>
        <input type="button" id="logoutButton" value="[+logout+]" onclick="return doLogout();"/>
    </fieldset>
</div>

<p class="loginLicense">
    <strong>MODX</strong>&trade; is licensed under the GPL license. &copy; 2005-2012 <a href="http://modx.com/" target="_blank">MODX</a>.
</p>
</body>
</html>
