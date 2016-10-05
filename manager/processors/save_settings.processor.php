<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('settings')) {
	$e->setError(3);
	$e->dumpError();
}
$form_v = $_POST;
// lose the POST now, gets rid of quirky issue with Safari 3 - see FS#972
unset($_POST);

if($form_v['friendly_urls']==='1' && strpos($_SERVER['SERVER_SOFTWARE'],'IIS')===false)
{
	$htaccess        = $modx->config['base_path'] . '.htaccess';
	$sample_htaccess = $modx->config['base_path'] . 'sample.htaccess';
	$dir = '/' . trim($modx->config['base_url'],'/');
	if(is_file($htaccess))
	{
		$_ = file_get_contents($htaccess);
		if(strpos($_,'RewriteBase')===false)
		{
			$warnings[] = $_lang["settings_friendlyurls_alert2"];
		}
		elseif(is_writable($htaccess))
		{
			$_ = preg_replace('@RewriteBase.+@',"RewriteBase {$dir}", $_);
			if(!@file_put_contents($htaccess,$_))
			{
				$warnings[] = $_lang["settings_friendlyurls_alert2"];
			}
		}
	}
	elseif(is_file($sample_htaccess))
	{
		if(!@rename($sample_htaccess,$htaccess))
        {
        	$warnings[] = $_lang["settings_friendlyurls_alert"];
		}
		elseif($modx->config['base_url']!=='/')
		{
			$_ = file_get_contents($htaccess);
			$_ = preg_replace('@RewriteBase.+@',"RewriteBase {$dir}", $_);
			if(!@file_put_contents($htaccess,$_))
			{
				$warnings[] = $_lang["settings_friendlyurls_alert2"];
			}
		}
	}
}
$default_config = include_once(MODX_CORE_PATH . 'default.config.php');

$form_v['filemanager_path'] = str_replace('[(base_path)]',MODX_BASE_PATH,$form_v['filemanager_path']);
$form_v['rb_base_dir']      = str_replace('[(base_path)]',MODX_BASE_PATH,$form_v['rb_base_dir']);
if(!is_dir($form_v['filemanager_path'])) $warnings[] = $_lang["configcheck_filemanager_path"];
if(!is_dir($form_v['rb_base_dir']))      $warnings[] = $_lang["configcheck_rb_base_dir"] ;

if(0< count($warnings))
{
	$modx->manager->saveFormValues('17');
	$msg = join("\n",$warnings);
	$modx->webAlertAndQuit($msg,'index.php?a=17');
	exit;
}

if (isset($form_v) && count($form_v) > 0) {
	$savethese = array();
	foreach ($form_v as $k => $v) {
		switch ($k) {
			case 'site_url':
			case 'base_url':
			case 'rb_base_dir':
			case 'rb_base_url':
			case 'filemanager_path':
				if($v!=='') $v = rtrim($v,'/') . '/';
				break;
			case 'error_page':
			case 'unauthorized_page':
				if (trim($v) !== '' && !is_numeric($v))
				{
					$v = $form_v['site_start'];
				}
				break;
	
			case 'lst_custom_contenttype':
			case 'txt_custom_contenttype':
				// Skip these
				continue 2;
				break;
			case 'manager_language':
				if(!is_file(MODX_CORE_PATH . "lang/{$v}.inc.php"))
					$v = 'english';
				break;
			case 'new_file_permissions':
			case 'new_folder_permissions':
				if(strlen($v)==3) $v = '0' . $v;
				break;
			case 'smtppw':
				if ($v !== '********************') {
					$v = trim($v);
					$v = base64_encode($v) . substr(str_shuffle('abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 7);
					$v = str_replace('=','%',$v);
				}
				else $k = '';
				break;
			case 'a':
			case 'reload_site_unavailable':
			case 'reload_captcha_words':
			case 'reload_emailsubject':
			case 'reload_signupemail_message':
			case 'reload_websignupemail_message':
			case 'reload_system_email_webreminder_message':
				$k = '';
				break;
            case 'topmenu_site':
            case 'topmenu_element':
            case 'topmenu_security':
            case 'topmenu_user':
            case 'topmenu_tools':
            case 'topmenu_reports':
				$v = setModifiedConfig($v,$default_config[$k]);
				break;
			default:
			break;
		}
		$v = is_array($v) ? implode(',', $v) : $v;

		if(!empty($k)) $savethese[] = "('" . $modx->db->escape($k)."', '" . $modx->db->escape($v) . "')";
	}
	
	// Run a single query to save all the values
	$sql = "REPLACE INTO ".$modx->getFullTableName("system_settings")." (setting_name, setting_value)
		VALUES ".implode(', ', $savethese);
	if(!@$rs = $modx->db->query($sql)) {
		echo "Failed to update setting value!";
		exit;
	}
	
	// Reset Template Pages
	if (isset($form_v['reset_template'])) {
		$template = $form_v['default_template'];
		$oldtemplate = $form_v['old_template'];
		$tbl_site_content = $modx->getFullTableName('site_content');
		$reset = $form_v['reset_template'];
		if    ($reset==1) $modx->db->update("template='{$template}'", $tbl_site_content, "type='document'");
		elseif($reset==2) $modx->db->update("template='{$template}'", $tbl_site_content, "template='{$oldtemplate}'");
	}
	
	// empty cache
	$modx->clearCache(); // first empty the cache
}

setPermission($form_v);

header("Location: index.php?a=7&r=10");

function setPermission($config)
{
    if(!is_dir($config['rb_base_dir'].'images')) mkd($config['rb_base_dir'].'images');
    if(!is_dir($config['rb_base_dir'].'files'))  mkd($config['rb_base_dir'].'files');
    if(!is_dir($config['rb_base_dir'].'media'))  mkd($config['rb_base_dir'].'media');
    if(!is_dir($config['rb_base_dir'].'flash'))  mkd($config['rb_base_dir'].'flash');
    if(!is_dir(MODX_BASE_PATH.'temp/export'))    mkd(MODX_BASE_PATH.'temp/export');
    if(!is_dir(MODX_BASE_PATH.'temp/backup'))    mkd(MODX_BASE_PATH.'temp/backup');
    
    if(is_writable(MODX_CORE_PATH.'config.inc.php')) @chmod(MODX_CORE_PATH.'config.inc.php', 0444);
}

function mkd($path) {
	$rs = @mkdir($path, 0777, true);
	if($rs) $rs = @chmod($path, 0777);
	return $rs;
}

function setModifiedConfig($form_v,$defaut_v) {
	if($form_v!==$defaut_v) return "* {$form_v}";
	else                    return $defaut_v;
}