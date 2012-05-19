<?php
/*******************************************************
 *
 * MODxMailer Class extends PHPMailer
 * Created by ZeRo (http://www.petit-power.com/)
 * updated by yama (http://kyms.jp/)
 *
 * -----------------------------------------------------
 * [History]
 * Ver1.4.4.7    2011/06/07
 * -----------------------------------------------------
 * Update $Date: 2011-06-07 23:28:18 +0900 $ 
 *        $Revision: 61 $
 *******************************************************
 */

include_once MODX_MANAGER_PATH . 'includes/controls/class.phpmailer.php';

/* ------------------------------------------------------------------
 *
 * MODxMailer - Extended PHPMailer
 *
 * -----------------------------------------------------------------
 */

class MODxMailer extends PHPMailer
{
	var $mb_language          = null;
	var $encode_header_method = null;
	
	function MODxMailer()
	{
		global $modx;
		
		$this->IsMail();
		$this->From     = $modx->config['emailsender'];
		$this->FromName = $modx->config['site_name'];
		
		switch(strtolower($modx->config['manager_language']))
		{
			case 'japanese-utf8':
			case 'japanese-euc':
				$this->CharSet     = 'ISO-2022-JP';
				$this->Encoding    = '7bit';
				$this->mb_language = 'Japanese';
				$this->encode_header_method = 'mb_encode_mimeheader';
				$this->IsHTML(false);
				break;
			case 'russian-utf8':
				$this->CharSet     = 'UTF-8';
				$this->Encoding    = 'base64';
				$this->mb_language = 'UNI';
				break;
			case 'english':
				$this->CharSet     = 'iso-8859-1';
				$this->Encoding    = 'quoted-printable';
				$this->mb_language = 'English';
				break;
			default:
				$this->CharSet     = 'UTF-8';
				$this->Encoding    = 'base64';
				$this->mb_language = 'UNI';
		}
	    if(extension_loaded('mbstring'))
		{
			mb_language($this->mb_language);
			mb_internal_encoding($modx->config['modx_charset']);
		}
	}
	
	function EncodeHeader($str, $position = 'text')
	{
		global $modx;
		if($this->encode_header_method=='mb_encode_mimeheader') return mb_encode_mimeheader($str,$this->CharSet);
		
		switch(strtolower($modx->config['manager_language']))
		{
			case 'japanese-utf8':
			case 'japanese-euc':
				return $str;
				break;
			default:
				return parent::EncodeHeader($str, $position);
		}
	}
	
	function MailSend($header, $body)
	{
		global $modx;
		
		if(ini_get('safe_mode')) return parent::MailSend($header, $body);
		
		switch(strtolower($modx->config['manager_language']))
		{
			case 'japanese-utf8':
			case 'japanese-euc':
				return $this->mbMailSend($header, $body);
				break;
			default:
				return parent::MailSend($header, $body);
		}
	}
		
	function mbMailSend($header, $body)
	{
		$to = '';
		for($i = 0; $i < count($this->to); $i++)
		{
			if($i != 0) { $to .= ', '; }
			$to .= $this->AddrFormat($this->to[$i]);
		}
		
		$toArr = explode(',', $to);
		
		$params = sprintf("-oi -f %s", $this->Sender);
		if ($this->Sender != '' && strlen(ini_get('safe_mode')) < 1)
		{
			$old_from = ini_get('sendmail_from');
			ini_set('sendmail_from', $this->Sender);
			if ($this->SingleTo === true && count($toArr) > 1)
			{
				foreach ($toArr as $key => $val)
				{
					$rt = @mb_send_mail($val, $this->Subject, $body, $header, $params); 
				}
			}
			else
			{
				$rt = @mb_send_mail($to, $this->Subject, $body, $header, $params);
			}
		}
		else
		{
			if ($this->SingleTo === true && count($toArr) > 1)
			{
				foreach ($toArr as $key => $val)
				{
					$rt = @mb_send_mail($val, $this->Subject, $body, $header, $params);
				}
			}
			else
			{
				$rt = @mb_send_mail($to, $this->Subject, $body, $header);
			}
		}
		
		if (isset($old_from))
		{
			ini_set('sendmail_from', $old_from);
		}
		if(!$rt)
		{
			$msg  = $this->Lang('instantiate') . "<br />\n";
			$msg .= "{$this->Subject}<br />\n";
			$msg .= "{$this->FromName}&lt;{$this->From}&gt;<br />\n";
			$msg .= $body;
			$this->SetError($msg);
			return false;
		}
		
		return true;
	}
	
	function SetError($msg)
	{
		global $modx;
		$modx->config['send_errormail'] = '0';
		$modx->logEvent(0, 3, $msg,'phpmailer');
		return parent::SetError($msg);
	}
}
