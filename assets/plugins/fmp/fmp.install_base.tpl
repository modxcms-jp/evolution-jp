//<?php
/**
 * Forgot Manager Login
 * 
 * 管理画面のログインパスワードを忘れた時に、一時的に無条件ログインできるURLを発行
 *
 * @category 	plugin
 * @version 	1.1.9r2
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@events OnManagerLoginFormPrerender,OnBeforeManagerLogin,OnManagerAuthentication,OnManagerLoginFormRender,OnManagerChangePassword 
 * @internal	@modx_category Manager and Admin
 * @internal    @installset base
 */

if(!class_exists('ForgotManagerPassword'))
{
	class ForgotManagerPassword
	{
		function ForgotManagerPassword()
		{
			$this->errors = array();
			$this->checkLang();
		}
	
		function getLink()
		{
			global $_lang;
			
			$link = <<<EOD
<a id="ForgotManagerPassword-show_form" href="index.php?action=show_form">{$_lang['forgot_your_password']}</a>
EOD;
			return $link;
		}
			
		function getForm()
		{
			global $_lang;
			
			$form = <<< EOD
<label id="FMP-email_label" for="FMP_email">{$_lang['account_email']}:</label>
<input id="FMP-email" type="text" />
<button id="FMP-email_button" type="button" onclick="window.location = 'index.php?action=send_email&email='+document.getElementById('FMP-email').value;">{$_lang['send']}</button>
EOD;
			return $form;
		}
		
		/* Get user info including a hash unique to this user, password, and day */
		function getUser($key='',$target='key')
		{
			global $modx, $_lang;
			
			$tbl_manager_users   = $modx->getFullTableName('manager_users');
			$tbl_user_attributes = $modx->getFullTableName('user_attributes');
			
			$site_id = $modx->config['site_id'];
			$today = date('Yz'); // Year and day of the year
			$user = null;
			
			$key = $modx->db->escape($key);
			
			switch($target)
			{
				case 'key':
					$where = "CONV(MD5(CONCAT(usr.username,usr.password,'{$site_id}','{$today}')),16,36) = '{$key}'";
					break;
				case 'email':
					$where = "attr.email = '{$key}'";
					break;
				default:
					$where = '';
			}
			
			if(!empty($key) && is_string($key))
			{
				$field = "usr.id, usr.username, attr.email, CONV(MD5(CONCAT(usr.username,usr.password,'{$site_id}','{$today}')),16,36) AS `key`";
				$from = "{$tbl_manager_users} usr INNER JOIN {$tbl_user_attributes} attr ON usr.id = attr.internalKey";
				if($result = $modx->db->select($field,$from,$where,'',1))
				{
					if($modx->db->getRecordCount($result)==1)
					{
						$user = $modx->db->getRow($result);
					}
				}
			}
			
			if(is_null($user)) $this->errors[] = $_lang['could_not_find_user'];
			
			return $user;
		}
		
		/* Send an email with a link to login */
		function sendEmail($to)
		{
			global $modx, $_lang;
			
			$user = $this->getUser($to,'email');
			if(is_null($user)) return;
			
			if($modx->config['use_captcha']==='1') $captcha = '&captcha_code=ignore';
			else                                   $captcha = '';
			
			$body = <<< EOT
{$_lang['forgot_password_email_intro']}

{$modx->config['site_url']}manager/index.php?fmpkey={$user['key']}{$captcha}
{$_lang['forgot_password_email_link']}

{$_lang['forgot_password_email_instructions']}
{$_lang['forgot_password_email_fine_print']}
EOT;
			$mail['subject'] = $_lang['password_change_request'];
			$mail['sendto'] = $to;
			
			$result = $modx->sendmail($mail,$body);
			
			if(!$result) $this->errors[] = $_lang['error_sending_email'];
			return $result;
		}
		
		function unblockUser($user_id)
		{
			global $modx, $_lang;
			
			$tbl_user_attributes = $modx->getFullTableName('user_attributes');
			$modx->db->update('blocked=0,blockeduntil=0,failedlogincount=0', $tbl_user_attributes, "internalKey='{$user_id}'");
			
			if(!$modx->db->getAffectedRows()) $this->errors[] = $_lang['user_doesnt_exist'];
		}
		
		function checkLang()
		{
			global $_lang;
			
			$eng = array();
			$eng['forgot_your_password'] = 'Forgot your password?';
			$eng['account_email'] = 'Account email';
			$eng['send'] = 'Send';
			$eng['password_change_request'] = 'Password change request';
			$eng['forgot_password_email_intro'] = 'A request has been made to change the password on your account.';
			$eng['forgot_password_email_link'] = 'Click here to complete the process.';
			$eng['forgot_password_email_instructions'] = 'From there you will be able to change your password from the My Account menu.';
			$eng['forgot_password_email_fine_print'] = '* The URL above will expire once you change your password or after today.';
			$eng['error_sending_email'] = 'Error sending email';
			$eng['could_not_find_user'] = 'Could not find user';
			$eng['user_doesnt_exist'] = 'User does not exist';
			$eng['email_sent'] = 'Email sent';
			
			foreach($eng as $key=>$value)
			{
				if(empty($_lang[$key])) $_lang[$key] = $value;
			}
		}
		
		function getErrorOutput()
		{
			if($this->errors) $output = '<span class="error">'.implode('</span><span class="errors">', $this->errors).'</span>';
			else              $output = '';
			
			return $output;
		}
		
		function getVar($varName)
		{
			if(isset($_GET[$varName]) && !empty($_GET[$varName]) && is_string($_GET[$varName]))
			     return trim($_GET[$varName]);
			else return false;
		}
	}
}



global $_lang;

$forgot = new ForgotManagerPassword();

$action = $forgot->getVar('action');
$to     = $forgot->getVar('email');
$key    = $forgot->getVar('fmpkey');

$output = '';

switch($modx->event->name)
{
	case 'OnManagerLoginFormPrerender':
		if(empty($key)) return;
		$user = $forgot->getUser($key);
		$username = $user['username'];
		
		if($modx->config['use_captcha']==='1')
		{
			$captcha = '&captcha_code=ignore';
		}
		else $captcha = '';
		
		$url = "{$modx->config['site_url']}manager/processors/login.processor.php?username={$username}&fmpkey={$key}{$captcha}";
		header("Location:{$url}");
		exit;
		break;
	case 'OnManagerLoginFormRender':
		if($action==='show_form')
		{
			$output = $forgot->getForm();
		}
		elseif($action==='send_email')
		{
			if($forgot->sendEmail($to))
				$output = $_lang['email_sent'];
			else
				$output = $forgot->getErrorOutput() . $forgot->getLink();
		}
		else $output = $forgot->getLink();
		
		$modx->event->output($output);
		break;
	case 'OnBeforeManagerLogin':
		if(empty($key)) return;
		$user = $forgot->getUser($key);
		if($user && is_array($user) && !$forgot->errors)
		{
			$forgot->unblockUser($user['id']);
		}
		break;
	case 'OnManagerAuthentication':
		if(empty($key)) return;
		$_SESSION['mgrForgetPassword'] = '1';
		$user = $forgot->getUser($key);
		if($user !== null && count($forgot->errors) == 0)
		{
			$captcha_code = $forgot->getVar('captcha_code');
			if($captcha_code!==false) $_SESSION['veriword'] = $captcha_code;
			$output =  true;
		}
		else $output = false;
		$modx->event->output($output);
		break;
	case 'OnManagerChangePassword':
		if(isset($_SESSION['mgrForgetPassword'])) unset($_SESSION['mgrForgetPassword']);
		break;
	default:
		return;
}
