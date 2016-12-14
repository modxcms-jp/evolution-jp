<?php
/**
 * Commonly used login functions
 * Writen By Raymond Irving April, 2005
 *
 */

// extract declarations
function webLoginExtractDeclarations(&$html)
{
	$declare  = array();
	if(strpos($html,'<!-- #declare:')===false) return $declare;
	$matches= array();
	if (preg_match_all("/<\!-- \#declare\:(.*)[^-->]?-->/i",$html,$matches)) {
	for($i=0;$i<count($matches[1]);$i++) {
	$tag = explode(' ',$matches[1][$i]);
	$tagname=trim($tag[0]);
	$tagvalue=trim($tag[1]);
	$declare[$tagname] = $tagvalue;
	}
	// remove declarations
	$html = str_replace($matches[0],'',$html);
	}
	return $declare;
}

// show javascript alert
function webLoginAlert($msg, $ph=array())
{
	global $modx;
	$msg = lang($msg,$ph);
	return "<script>window.setTimeout(\"alert('".addslashes($modx->db->escape($msg))."')\",10);</script>";
}

// generate new password
function webLoginGeneratePassword($length = 10, $allow_chars='')
{
	if(empty($allow_chars)) $allow_chars = 'abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	return substr(str_shuffle($allow_chars), 0, $length);
}

// Send new password to the user
function webLoginSendNewPassword($email,$uid,$pwd,$ufn)
{
	global $modx, $site_url;
	
	$mailto = $modx->config['mailto'];
	$websignupemail_message = $modx->config['websignupemail_message'];
	$emailsubject = $modx->config['emailsubject'];
	$emailsender = $modx->config['emailsender'];
	$site_name = $modx->config['site_name'];
	$site_start = $modx->config['site_start'];
	$message = sprintf($websignupemail_message, $uid, $pwd); // use old method
	// replace placeholders
	$message = str_replace("[+uid+]",$uid,$message);
	$message = str_replace("[+pwd+]",$pwd,$message);
	$message = str_replace("[+ufn+]",$ufn,$message);
	$message = str_replace("[+sname+]",$site_name,$message);
	$message = str_replace("[+semail+]",$emailsender,$message);
	$message = str_replace("[+surl+]",$site_url,$message);
	
	$sent = $modx->sendmail($email,$message) ;         //ignore mail errors in this cas
	
	if (!$sent) webLoginAlert("Error while sending mail to {$mailto}",1);
	return true;
}
	
function preserveUrl($docid = '', $alias = '', $array_values = array(), $suffix = false)
{
	global $modx;
	$array_get = $_GET;
	$urlstring = array();
	
	unset($array_get["id"]);
	unset($array_get["q"]);
	unset($array_get["webloginmode"]);
	
	$array_url = array_merge($array_get, $array_values);
	foreach ($array_url as $name => $value)
	{
		if (!is_null($value))
		{
			$urlstring[] = urlencode($name) . '=' . urlencode($value);
		}
	}
	
	$url = join('&',$urlstring);
	if ($suffix)
	{
		if (empty($url)) $url = '?';
		else             $url .= '&';
	}
	return $modx->makeUrl($docid, $alias, $url,'full');
}
	
	function lang($key,$ph=array())
	{
		$_lang['Invalid password activation key. Your password was NOT activated.'] = 'アクティベーションキーが無効になっています。';
		$_lang['Your new password was successfully activated.'] = 'アクティベーションしました。新しいパスワードでログインできます。';
		$_lang['Incorrect username or password entered!'] = 'メールアドレスまたはパスワードが間違っています。';
		$_lang['You are blocked and cannot log in!'] = 'メールアドレスまたはパスワードが間違っています。';
		
		$value = ($_lang[$key]) ? $_lang[$key] : $key;
		if(!empty($ph))
		{
			foreach($ph as $k=>$v)
			{
			$k = "[+{$k}+]";
				$value = str_replace($k,$v,$value);
			}
		}
		return $value;
}

function webLoginGetCode($target)
{
	global $modx;
	if(preg_match('@^[0-9][1-9]*$@',$target))
	{
		$doc = $modx->getDocument($target);
		if($doc)
			$result = $doc['content'];
		else $result = "Document '{$tpl}' not found.";
	}
	else
	{
		$result = $modx->getChunk($target);
		if(!$result) $result = "Chunk '{$tpl}' not found.";
	}
	return $result;
}
