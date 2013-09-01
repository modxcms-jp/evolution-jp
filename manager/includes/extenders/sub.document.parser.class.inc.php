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
}