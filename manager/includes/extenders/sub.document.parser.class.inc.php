<?php
class SubParser {
	function SubParser()
	{
	}
	function sendmail($params=array(), $msg='')
	{
		global $modx;
		if(isset($params) && is_string($params))
		{
			if(strpos($params,'=')===false)
			{
				if(strpos($params,'@')!==false) $p['to']	  = $params;
				else                            $p['subject'] = $params;
			}
			else
			{
				$params_array = explode(',',$params);
				foreach($params_array as $k=>$v)
				{
					$k = trim($k);
					$v = trim($v);
					$p[$k] = $v;
				}
			}
		}
		else
		{
			$p = $params;
			unset($params);
		}
		if(isset($p['sendto'])) $p['to'] = $p['sendto'];
		
		if(isset($p['to']) && preg_match('@^[0-9]+$@',$p['to']))
		{
			$userinfo = $modx->getUserInfo($p['to']);
			$p['to'] = $userinfo['email'];
		}
		if(isset($p['from']) && preg_match('@^[0-9]+$@',$p['from']))
		{
			$userinfo = $modx->getUserInfo($p['from']);
			$p['from']	 = $userinfo['email'];
			$p['fromname'] = $userinfo['username'];
		}
		if($msg==='' && !isset($p['body']))
		{
			$p['body'] = $_SERVER['REQUEST_URI'] . "\n" . $_SERVER['HTTP_USER_AGENT'] . "\n" . $_SERVER['HTTP_REFERER'];
		}
		elseif(is_string($msg) && 0<strlen($msg)) $p['body'] = $msg;
		
		$modx->loadExtension('MODxMailer');
		$sendto = (!isset($p['to']))   ? $modx->config['emailsender']  : $p['to'];
		$sendto = explode(',',$sendto);
		foreach($sendto as $address)
		{
			list($name, $address) = $modx->mail->address_split($address);
			$modx->mail->AddAddress($address,$name);
		}
		if(isset($p['cc']))
		{
			$p['cc'] = explode(',',$sendto);
			foreach($p['cc'] as $address)
			{
				list($name, $address) = $modx->mail->address_split($address);
				$modx->mail->AddCC($address,$name);
			}
		}
		if(isset($p['bcc']))
		{
			$p['bcc'] = explode(',',$sendto);
			foreach($p['bcc'] as $address)
			{
				list($name, $address) = $modx->mail->address_split($address);
				$modx->mail->AddBCC($address,$name);
			}
		}
		if(isset($p['from'])) list($p['fromname'],$p['from']) = $modx->mail->address_split($p['from']);
		$modx->mail->From	 = (!isset($p['from']))  ? $modx->config['emailsender']  : $p['from'];
		$modx->mail->FromName = (!isset($p['fromname'])) ? $modx->config['site_name'] : $p['fromname'];
		$modx->mail->Subject  = (!isset($p['subject']))  ? $modx->config['emailsubject'] : $p['subject'];
		$modx->mail->Body	 = $p['body'];
		$rs = $modx->mail->send();
		return $rs;
	}
	
    function sendRedirect($url, $count_attempts= 0, $type= 'REDIRECT_HEADER', $responseCode= '301')
    {
    	global $modx;
    	
    	if (empty($url)) return false;
    	elseif(preg_match('@^[1-9][0-9]*$@',$url)) {
    		$url = $modx->makeUrl($url,'','','full');
    	}
    	
    	if ($count_attempts == 1) {
    		// append the redirect count string to the url
    		$currentNumberOfRedirects= isset ($_REQUEST['err']) ? $_REQUEST['err'] : 0;
    		if ($currentNumberOfRedirects > 3) {
    			$modx->messageQuit("Redirection attempt failed - please ensure the document you're trying to redirect to exists. <p>Redirection URL: <i>{$url}</i></p>");
    		} else {
    			$currentNumberOfRedirects += 1;
    			if (strpos($url, '?') > 0) $url .= '&';
    			else                       $url .= '?';
    			$url .= "err={$currentNumberOfRedirects}";
    		}
    	}
    	if ($type == 'REDIRECT_REFRESH') $header= "Refresh: 0;URL={$url}";
    	elseif($type == 'REDIRECT_META') {
    		$header= '<META HTTP-EQUIV="Refresh" CONTENT="0; URL=' . $url . '" />';
    		echo $header;
    		exit;
    	}
    	elseif($type == 'REDIRECT_HEADER') {
    		// check if url has /$base_url
    		global $base_url, $site_url;
    		if (substr($url, 0, strlen($base_url)) == $base_url) {
    			// append $site_url to make it work with Location:
    			$url= $site_url . substr($url, strlen($base_url));
    		}
    		if (strpos($url, "\n") === false) $header= 'Location: ' . $url;
    		else $modx->messageQuit('No newline allowed in redirect url.');
    	}
    	if (!empty($responseCode)) {
    		if    (strpos($responseCode, '301') !== false) $responseCode = 301;
    		elseif(strpos($responseCode, '302') !== false) $responseCode = 302;
    		elseif(strpos($responseCode, '303') !== false) $responseCode = 303;
    		elseif(strpos($responseCode, '307') !== false) $responseCode = 307;
    		else $responseCode = '';
    		if(!empty($responseCode))
    		{
        		header($header, true, $responseCode);
        		exit;
    		}
    	}
    	header($header);
    	exit();
    }
}