<!DOCTYPE html>
<html lang="en">
<head>
    <title>[+page_title+]</title>
    <meta charset="[+modx_charset+]">
    <meta name="robots" content="noindex, nofollow"/>
    [+theme_stylesheets+]
    <script type="text/javascript">
        function doLogout() {
            // ログアウトは POST でしか受け付けない
            var form = document.createElement('form');
            form.method = 'post';
            form.action = '[+logouturl+]';
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '[+csrf_token+]';
            form.appendChild(token);
            document.body.appendChild(form);
            form.submit();
        }

    </script>

    <script type="text/javascript">
        if (top.frames.length != 0) {
            top.location = self.document.location;
        }
    </script>

</head>
<body id="login">

<!-- start the login box -->
<div id="mx_loginbox">

    <form method="post" name="loginfrm" id="loginfrm" action="processors/login.processor.php">


        <div id="mx_loginArea">
            <h1 class="siteName">[+site_name+]</h1>
            <div class="loginMessage">[+manager_lockout_message+]</div>
            <br/>
            <input type="button" id="submitButton" value="[+home+]" data-href="[+homeurl+]" onclick="top.location = this.dataset.href; return false;"/>&nbsp;
            <input type="button" id="submitButton" value="[+logout+]" onclick="return doLogout();"/>
        </div>
        <br style="clear:both;height: 1px"/>

    </form>
</div>

<p class="loginLicense">
    <strong>MODX</strong>&trade; is licensed under the GPL license. &copy; 2005-2013 <a href="http://modx.com/"
                                                                                        target="_blank">MODX</a>.
</p>

</body>
</html>
