<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (isset($_SESSION['mgrValidated']) && $_SESSION['usertype']!='manager')
{
	@session_destroy();
}

// andrazk 20070416 - if installer is running, destroy active sessions
$instcheck_path = $modx->config['base_path'] . 'assets/cache/installProc.inc.php';
if (file_exists($instcheck_path))
{
	include_once($instcheck_path);
	if (isset($installStartTime))
	{
		if ((time() - $installStartTime) > 5 * 60)
		{ // if install flag older than 5 minutes, discard
			unset($installStartTime);
			@ chmod($instcheck_path, 0755);
			unlink($instcheck_path);
		} 
		else
		{
			if ($_SERVER['REQUEST_METHOD'] != 'POST')
			{
				if (isset($_COOKIE[session_name()]))
				{
					session_unset();
					@session_destroy();
				}
				$installGoingOn = 1;
			}
		}
	}
}

// andrazk 20070416 - if session started before install and was not destroyed yet
if (isset($lastInstallTime) && isset($_SESSION['mgrValidated']))
{
	if (isset($_SESSION['modx.session.created.time']) && ($_SESSION['modx.session.created.time'] < $lastInstallTime))
	{
		if ($_SERVER['REQUEST_METHOD'] != 'POST')
		{
			if (isset($_COOKIE[session_name()]))
			{
				session_unset();
				@session_destroy();
			}
			header('HTTP/1.0 307 Redirect');
			header('Location: '.MODX_MANAGER_URL.'index.php?installGoingOn=2');
		}
	}
}

if(!isset($_SESSION['mgrValidated']))
{
	if(isset($manager_language)) include_once("lang/{$manager_language}.inc.php");// include localized overrides
	else                         include_once('lang/english.inc.php');

	$modx->setPlaceholder('modx_charset',$modx_manager_charset);
	$modx->setPlaceholder('theme',$manager_theme);

	global $tpl;
	// invoke OnManagerLoginFormPrerender event
	$evtOut = $modx->invokeEvent('OnManagerLoginFormPrerender');
	if(!isset($tpl) || empty($tpl))
	{
		// load template file
		$tplFile = MODX_BASE_PATH . 'assets/templates/manager/login.html';
		if(file_exists($tplFile)==false)
		{
			$tplFile = MODX_BASE_PATH . 'manager/media/style/' . $modx->config['manager_theme'] . '/manager/login.html';
		}
		$tpl = file_get_contents($tplFile);
	}
	
	$html = is_array($evtOut) ? implode('',$evtOut) : '';
	$modx->setPlaceholder('OnManagerLoginFormPrerender',$html);

	$modx->setPlaceholder('site_name',$site_name);
	$modx->setPlaceholder('logo_slogan',$_lang["logo_slogan"]);
	$modx->setPlaceholder('login_message',$_lang["login_message"]);

	// andrazk 20070416 - notify user of install/update
	if (isset($_GET['installGoingOn'])) $installGoingOn = $_GET['installGoingOn'];
	if (isset($installGoingOn))
	{
		switch ($installGoingOn)
		{
		 case 1 : $login_message = $_lang["login_cancelled_install_in_progress"]; break;
		 case 2 : $login_message = $_lang["login_cancelled_site_was_updated"]   ; break;
		}
		$modx->setPlaceholder('login_message','<p><span class="fail">'.$login_message.'</p><p>'.$_lang["login_message"].'</p>');
	}

	if($use_captcha==1)
	{
		$modx->setPlaceholder('login_captcha_message',$_lang["login_captcha_message"]);
		$captcha_image = '<img id="captcha_image" src="../captcha.php?rand=' . mt_rand() . '" alt="'.$_lang["login_captcha_message"].'" />';
		$captcha_image = '<a href="'.MODX_MANAGER_URL.'" class="loginCaptcha">' . $captcha_image . '</a>';
		$modx->setPlaceholder('captcha_image',$captcha_image);
		$modx->setPlaceholder('captcha_input','<label>'.$_lang["captcha_code"].'<input type="text" class="text" name="captcha_code" tabindex="3" value="" /></label>');
	}

	// login info
	$uid =  isset($_COOKIE['modx_remember_manager']) ? preg_replace('/[^a-zA-Z0-9\-_@\.]*/', '',  $_COOKIE['modx_remember_manager']) :''; 
	$modx->setPlaceholder('uid',$uid);
	$modx->setPlaceholder('username',$_lang["username"]);
	$modx->setPlaceholder('password',$_lang["password"]);

	// remember me
	$html =  isset($_COOKIE['modx_remember_manager']) ? 'checked="checked"' :'';
	$modx->setPlaceholder('remember_me',$html);
	$modx->setPlaceholder('remember_username',$_lang["remember_username"]);
	$modx->setPlaceholder('login_button',$_lang["login_button"]);
	
	// invoke OnManagerLoginFormRender event
	$evtOut = $modx->invokeEvent('OnManagerLoginFormRender');
	$html = is_array($evtOut) ? '<div id="onManagerLoginFormRender">'.implode('',$evtOut).'</div>' : '';
	$modx->setPlaceholder('OnManagerLoginFormRender',$html);

    // merge placeholders
    $tpl = $modx->parseDocumentSource($tpl);
    $regx = strpos($tpl,'[[+')!==false ? '~\[\[\+(.*?)\]\]~' : '~\[\+(.*?)\+\]~'; // little tweak for newer parsers
    $tpl = preg_replace($regx, '', $tpl); //cleanup

    echo $tpl;

    exit;

}
else
{
	// log the user action
	if    (isset($_SERVER['HTTP_CLIENT_IP']))       $ip = $_SERVER['HTTP_CLIENT_IP'];
	elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	elseif(isset($_SERVER['REMOTE_ADDR']))          $ip = $_SERVER['REMOTE_ADDR'];
	else                                            $ip = 'UNKNOWN';
	
	$_SESSION['ip'] = $ip;

	$action = isset($_REQUEST['a']) ? (int) $_REQUEST['a'] : 1;
	
	$fields['internalKey'] = $modx->getLoginUserID();
	$fields['username']    = $_SESSION['mgrShortname'];
	$fields['lasthit']     = time();
	$fields['action']      = $action;
	$fields['id']          = (preg_match('@^[0-9]+$@',$_REQUEST['id'])) ? $_REQUEST['id'] : 0;
	$fields['ip']          = $ip;
	
	if($action !== 1)
	{
		foreach($fields as $k=>$v)
		{
			$keys[]   = $k;
			$values[] = $v;
		}
		$join_key   = join(',', $keys);
		$join_value = "'" . join("','", $values) . "'";
		
		$tbl_active_users = $modx->getFullTableName('active_users');
		$sql = "REPLACE INTO {$tbl_active_users} ({$join_key}) VALUES ({$join_value})";
		if(!$rs = $modx->db->query($sql))
		{
			echo "error replacing into active users! SQL: {$sql}\n" . $modx->db->getLastError();
			exit;
		}
	}
}
