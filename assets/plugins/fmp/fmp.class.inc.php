<?php
class ForgotManagerPassword {
	public $fmp_path;
	private $lang;
	
	function __construct()
	{
		$this->fmp_path = str_replace('\\','/', __DIR__) . '/';
		$this->errors = array();
		$this->setLang();
	}
	
	private function lang($key, $default=null) {
		if(!isset($this->lang[$key])) {
			return $default;
		}
		return $this->lang[$key];
	}

	private function setLang()
	{
		global $_lang;
		
		$en = array();
		$en['forgot_your_password']               = 'Forgot your password?';
		$en['account_email']                      = 'Account email';
		$en['send']                               = 'Send';
		$en['password_change_request']            = 'Password change request';
		$en['forgot_password_email_intro']        = 'A request has been made to change the password on your account.';
		$en['forgot_password_email_link']         = 'Click here to complete the process.';
		$en['forgot_password_email_instructions'] = 'From there you will be able to change your password from the My Account menu.';
		$en['forgot_password_email_fine_print']   = '* The URL above will expire once you change your password or after today.';
		$en['error_sending_email']                = 'Error sending email';
		$en['could_not_find_user']                = 'Could not find user';
		$en['user_doesnt_exist']                  = 'User does not exist';
		$en['email_sent']                         = 'Email sent';
		
		foreach($en as $key=>$value) {
			if(lang($key)) {
				$this->lang = lang($key);
			}
			$this->lang = $value;
		}
	}
	
	function run()
	{
		global $modx;
		
		$action = $this->getVar('action');
		$to     = $this->getVar('email');
		$key    = $this->getVar('fmpkey');
		
		switch($modx->event->name)
		{
			case 'OnManagerLoginFormPrerender':
				$this->redirectLoginProcessor($key);
				break;
			case 'OnManagerLoginFormRender':
				$output = $this->showPrompt($action,$to);
				$modx->event->output($output);
				break;
			case 'OnBeforeManagerLogin':
				$this->unBlock($key);
				break;
			case 'OnManagerAuthentication':
				$status = $this->getAuthStatus($key);
				$modx->event->output($status);
				break;
			case 'OnManagerChangePassword':
				if(isset($_SESSION['mgrForgetPassword']))
					unset($_SESSION['mgrForgetPassword']);
				break;
			default:
				return;
		}
	}
	
	function redirectLoginProcessor($key)
	{
		global $modx;
		if(!$key) {
			return;
		}
		
		$user = $this->getUser($key);
		$username = $user['username'];
		
		if($modx->config['use_captcha']==='1') $captcha = '&captcha_code=ignore';
		else                                   $captcha = '';
		
		$url = "{$modx->config['site_url']}manager/processors/login.processor.php?username={$username}&fmpkey={$key}{$captcha}";
		header("Location:{$url}");
		exit;
	}
	
	function showPrompt($action,$to)
	{
		$link = '<a href="index.php?action=show_form" id="ForgotManagerPassword-show_form">' . $this->lang('forgot_your_password') . '</a>';
		if($action==='show_form') $output = $this->getForm();
		elseif($action==='send_email') {
			if($this->sendEmail($to)) $output = $this->lang('email_sent');
			else                      $output = $this->getErrorOutput() . $link;
		}
		else                          $output = $link;
		
		return $output;
	}
	
	function getErrorOutput()
	{
		if($this->errors) return '<span class="error">'.implode('</span><span class="errors">', $this->errors).'</span>';
		else              return '';
	}
	
	function unBlock($key)
	{
		global $modx;
		
		if(!$key) return;
		
		$user = $this->getUser($key);
		if(!isset($user['id'])) $this->errors[] = $this->lang('user_doesnt_exist');
		elseif(!$this->errors)
    		$modx->db->update('blocked=0,blockeduntil=0,failedlogincount=0', $modx->getFullTableName('user_attributes'), "internalKey='{$user['id']}'");
		else return false;
	}
	
	function getAuthStatus($key)
	{
		if(empty($key)) return;
		$_SESSION['mgrForgetPassword'] = '1';
		$user = $this->getUser($key);
		if($user !== null && count($this->errors) == 0) {
			$captcha_code = $this->getVar('captcha_code');
			if($captcha_code!==false) $_SESSION['veriword'] = $captcha_code;
			$status =  true;
		}
		else $status = false;
		
		return $status;
	}
	
	function getForm()
	{
		$form = <<< EOD
<label id="FMP-email_label">{$this->lang('account_email')}:
<input id="FMP-email" type="text" /></label>
<button id="FMP-email_button" type="button" onclick="window.location = 'index.php?action=send_email&email='+document.getElementById('FMP-email').value;">{$this->lang('send')}</button>
EOD;
		return $form;
	}
	
	/* Get user info including a hash unique to this user, password, and day */
	function getUser($key='',$target='key')
	{
		global $modx;
		
		$tbl_manager_users   = $modx->getFullTableName('manager_users');
		$tbl_user_attributes = $modx->getFullTableName('user_attributes');
		
		$user = null;
		$key = $modx->db->escape($key);
		
		switch($target)
		{
			case 'key':
				$where = "MD5(CONCAT(attr.lastlogin,usr.password))='{$key}'";
				break;
			case 'email':
				$where = "attr.email = '{$key}'";
				break;
			default:
				$where = '';
		}
		
		$user = array();
		if($key && is_string($key))
		{
			$field = "usr.id, usr.username, attr.email, MD5(CONCAT(attr.lastlogin,usr.password)) AS `key`";
			$from[] = "{$tbl_manager_users} usr";
			$from[] = "INNER JOIN {$tbl_user_attributes} attr ON usr.id = attr.internalKey";
			$result = $modx->db->select($field, join(' ', $from), $where,'',1);
			if($result) $user = $modx->db->getRow($result);
		}
		if(!$user) $this->errors[] = $this->lang('could_not_find_user');
		
		return $user;
	}
	
	/* Send an email with a link to login */
	function sendEmail($to)
	{
		global $modx;
		
		$user = $this->getUser($to,'email');
		if(is_null($user)) return;
		
		if($modx->config['use_captcha']==='1') $captcha = '&captcha_code=ignore';
		else                                   $captcha = '';
		
		$ph['intro']        = $this->lang('forgot_password_email_intro');
		$ph['fmpkey']       = $user['key'] . $captcha;
		$ph['link']         = $this->lang('forgot_password_email_link');
		$ph['instructions'] = $this->lang('forgot_password_email_instructions');
		$ph['fine_print']   = $this->lang('forgot_password_email_fine_print');
		
		$tpl = file_get_contents($this->fmp_path . 'sendmail.tpl');
		$body = $modx->parseText($tpl,$ph);
		$body = $modx->parseDocumentSource($body);
		
		$mail['subject'] = $this->lang('password_change_request');
		$mail['sendto'] = $to;
		
		$result = $modx->sendmail($mail,$body);
		
		if(!$result) $this->errors[] = $this->lang('error_sending_email');
		return $result;
	}
	
	function getVar($varName)
	{
		if(!is_string(evo()->input_get($varName))) {
			return false;
		}
		return trim(evo()->input_get($varName));
	}
}
