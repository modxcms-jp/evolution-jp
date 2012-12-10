<?php
# WebLogin 1.0
# Created By Raymond Irving 2004
#::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

defined('IN_PARSER_MODE') or die();

# load tpl
if(is_numeric($tpl)) $tpl = ($doc=$modx->getDocument($tpl)) ? $doc['content'] : "Document '{$tpl}' not found.";
elseif($tpl)         $tpl = ($chunk=$modx->getChunk($tpl)) ? $chunk : "Chunk '{$tpl}' not found.";
else                 $tpl = getWebLogintpl();

// extract declarations
$declare = webLoginExtractDeclarations($tpl);
$delim = isset($declare['separator']) ? $declare['separator'] : '<!--tpl_separator-->';
$tpls = explode($delim,$tpl);

if(!isset($_SESSION['webValidated']))
{
	$output = <<< EOT
    <script type="text/JavaScript">
    <!--//--><![CDATA[//><!--
        function getElementById(id){
            var o, d=document;
            if (d.layers) {o=d.layers[id];if(o) o.style=o};
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
        };
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
	// display login
	$output .= '<div id="WebLoginLayer0" style="position:relative">' . $tpls[0] . '</div>';
	$output .= '<div id="WebLoginLayer2" style="position:relative;display:none">' . $tpls[2] . '</div>';
	$ref = isset($_REQUEST['refurl']) ? array('refurl' => urlencode($_REQUEST['refurl'])) : array();
	$output = str_replace("[+action+]",preserveUrl($modx->documentIdentifier,'',$ref),$output);
	$output = str_replace("[+rememberme+]",(isset($cookieSet) ? 1 : 0),$output);
	$output = str_replace("[+username+]",$uid,$output);
	$output = str_replace("[+checkbox+]",(isset($cookieSet) ? 'checked' : ''),$output);
	$output = str_replace("[+logintext+]",$loginText,$output);
	$focus = (!empty($uid)) ? 'password' : 'username';
	$output .= <<< EOT
    <script type="text/javascript">
        if (document.loginfrm) document.loginfrm.{$focus}.focus();
    </script>
EOT;
}
else
{
	$output= '';
	
	if ($_SERVER['HTTP_CLIENT_IP'])          $ip = $_SERVER['HTTP_CLIENT_IP'];
	elseif($_SERVER['HTTP_X_FORWARDED_FOR']) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	elseif($_SERVER['REMOTE_ADDR'])          $ip = $_SERVER['REMOTE_ADDR'];
	else                                     $ip = 'unknown';
	
	$_SESSION['ip'] = $ip;
	
	$itemid = (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) ? $_REQUEST['id'] : 'NULL';
	$lasthittime = time();
	$tbl_active_users = $modx->getFullTableName('active_users');
	$sql = "REPLACE INTO {$tbl_active_users} (internalKey, username, lasthit, action, id, ip) values(-".$_SESSION['webInternalKey'].", '".$_SESSION['webShortname']."', '{$lasthittime}', '998', {$itemid}, '{$ip}')";
	if(!$rs = $modx->db->query($sql))
	{
		$output = "error replacing into active users! SQL: {$sql}";
	}
	else
	{
		// display logout
		$tpl = $tpls[1];
		$url = preserveUrl($modx->documentObject['id']);
		$url = $url.((strpos($url,'?')===false) ? '?':'&amp;') . 'webloginmode=lo';
		$tpl = str_replace('[+action+]',$url,$tpl);
		$tpl = str_replace('[+logouttext+]',$logoutText,$tpl);
		$output .= $tpl;
	}
	return $output;
}

# Returns Default WebLogin tpl
function getWebLogintpl(){
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
