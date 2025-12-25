<?php
# WebLogin 1.0
# Created By Raymond Irving 2004
#::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

defined('IN_PARSER_MODE') or die();

$output = '';

# load tpl
if (is_numeric($tpl)) {
    $doc = $modx->getDocument($tpl);
    $code = ($doc) ? $doc['content'] : sprintf("Document '%d' not found.", $tpl);
} elseif ($tpl) {
    $chunk = $modx->getChunk($tpl);
    $code = ($chunk) ? $chunk : sprintf("Chunk '%s' not found.", $tpl);
} else {
    $code = getWebLogintpl();
}

// extract declarations
$declare = webLoginExtractDeclarations($code);
$delim = isset($declare['separator']) ? $declare['separator'] : '<!--tpl_separator-->';
$tpls = explode($delim, $code);
unset($code);

if (!isset($tplLogin)) $tplLogin = $tpls[0];
if (!isset($tplReminder)) $tplReminder = (isset($tpls[2])) ? $tpls[2] : '';
if (!isset($tplLogout)) $tplLogout = $tpls[1];

if (!isset($_SESSION['webValidated'])) {
    $username = postv('username') ? hsc(trim(postv('username')), ENT_QUOTES) : '';
    $form = <<< EOT
    <script type="text/JavaScript">
    <!--//--><![CDATA[//><!--
        function getElementById(id){
            var o, d=document;
            if (d.layers) {o=d.layers[id];if(o) o.style=o}
            if (!o && d.getElementById) o=d.getElementById(id);
            if (!o && d.all) o = d.all[id];
            return o;
        }

        function webLoginShowForm(i){
            var a = getElementById('WebLoginLayer0');
            var b = getElementById('WebLoginLayer2');
            if(i==1 && a && b) {
                a.style.display="block";
                b.style.display="none";
                document.forms['loginreminder'].txtpwdrem.value = 0;
            }
            else if(i==2 && a && b) {
                a.style.display="none";
                b.style.display="block";
                document.forms['loginreminder'].txtpwdrem.value = 1;
            }
        }
        function webLoginCheckRemember () {
            if(document.loginfrm.rememberme.value==1) {
                document.loginfrm.rememberme.value=0;
            } else {
                document.loginfrm.rememberme.value=1;
            }
        }
        function webLoginEnter(nextfield,event) {
            if(event && event.keyCode == 13) {
                if(nextfield.name=='cmdweblogin') {
                    document.loginfrm.submit();
                    return false;
                }
                else {
                    nextfield.focus();
                    return false;
                }
            } else {
                return true;
            }
        }
    //--><!]]>
    </script>
EOT;
    if (isset($uid)) {
        $rs = db()->select('*', '[+prefix+]web_users', "id='{$uid}'");
        $row = db()->getRow($rs);
        $username = $row['username'];
    }

    // display login
    $form .= '<div id="WebLoginLayer0" style="position:relative">' . $tplLogin . '</div>';
    $form .= '<div id="WebLoginLayer2" style="position:relative;display:none">' . $tplReminder . '</div>';
    $ref = isset($_REQUEST['refurl']) ? ['refurl' => urlencode($_REQUEST['refurl'])] : [];
    $form = str_replace("[+action+]", preserveUrl($modx->documentIdentifier, '', $ref), $form);
    $form = str_replace("[+rememberme+]", (isset($cookieSet) ? 1 : 0), $form);
    $form = str_replace("[+username+]", (isset($username) ? $username : ''), $form);
    $form = str_replace("[+checkbox+]", (isset($cookieSet) ? 'checked' : ''), $form);
    $form = str_replace("[+logintext+]", $loginText, $form);
    $focus = (!empty($username)) ? 'password' : 'username';
    $form .= <<< EOT
    <script type="text/javascript">
        if (document.loginfrm) document.loginfrm.{$focus}.focus();
    </script>
EOT;
    $output .= $form;
} else {
    $output = '';

    $_SESSION['ip'] = real_ip();

    if (anyv('id') && is_numeric(anyv('id'))) {
        $itemid = anyv('id');
    } else {
        $itemid = 'NULL';
    }
    $sql = sprintf(
        "REPLACE INTO %s (internalKey, username, lasthit, action, id, ip) values(-%s, '%s', '%s', '998', %s, '%s')",
        evo()->getFullTableName('active_users'),
        $_SESSION['webInternalKey'],
        $_SESSION['webShortname'],
        time(),
        $itemid,
        real_ip()
    );
    if (!$rs = db()->query($sql)) {
        $output .= webLoginAlert("error replacing into active users! SQL: {$sql}");
    } else {
        // display logout
        $url = preserveUrl($modx->documentObject['id']);
        $url = $url . ((strpos($url, '?') === false) ? '?' : '&amp;') . 'webloginmode=lo';
        $tplLogout = str_replace('[+action+]', $url, $tplLogout);
        $tplLogout = str_replace('[+logouttext+]', $logoutText, $tplLogout);
        $output .= $tplLogout;
    }
}

# Returns Default WebLogin tpl
function getWebLogintpl()
{
    $src = <<< EOT
    <!-- #declare:separator <hr> -->
    <!-- login form section-->
    <form method="post" name="loginfrm" action="[+action+]">
    <input type="hidden" value="[+rememberme+]" name="rememberme" />
    User : <input type="text" name="username" onkeypress="return webLoginEnter(document.loginfrm.password);" size="8" style="width: 150px;" value="[+username+]" /><br />
    Password : <input type="password" name="password" onkeypress="return webLoginEnter(document.loginfrm.cmdweblogin);" style="width: 150px;" value="" /><br />
    Remember me : <input type="checkbox" id="chkbox" name="chkbox" value="" [+checkbox+] onclick="webLoginCheckRemember()" /><br />
    <input type="submit" value="[+logintext+]" name="cmdweblogin" /><br />
    <a href="#" onclick="webLoginShowForm(2);return false;">Forget Password?</a>
    </form>
    <hr>
    <!-- log out hyperlink section -->
    <a href='[+action+]'>[+logouttext+]</a>
    <hr>
    <!-- Password reminder form section -->
    <form name="loginreminder" method="post" action="[+action+]">
    <input type="hidden" name="txtpwdrem" value="0" />
    Enter the email address of your account<br />
    below to receive your password:<input type="text" name="txtwebemail" size="24" />
    <input type="submit" value="Submit" name="cmdweblogin" />
    <input type="reset" value="Cancel" name="cmdcancel" onclick="webLoginShowForm(1);" />
    </form>
EOT;
    return $src;
}
