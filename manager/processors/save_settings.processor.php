<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings')) {
	$e->setError(3);
	$e->dumpError();
}
$data = $_POST;
// lose the POST now, gets rid of quirky issue with Safari 3 - see FS#972
unset($_POST);

if($data['friendly_urls']==='1' && strpos($_SERVER['SERVER_SOFTWARE'],'IIS')===false)
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
$data['filemanager_path'] = str_replace('[(base_path)]',MODX_BASE_PATH,$data['filemanager_path']);
$data['rb_base_dir']      = str_replace('[(base_path)]',MODX_BASE_PATH,$data['rb_base_dir']);
if(!is_dir($data['filemanager_path'])) $warnings[] = $_lang["configcheck_filemanager_path"];
if(!is_dir($data['rb_base_dir']))      $warnings[] = $_lang["configcheck_rb_base_dir"] ;

if(0< count($warnings))
{
	$modx->manager->saveFormValues('17');
	$msg = join("\n",$warnings);
	$modx->webAlertAndQuit($msg,'index.php?a=17');
	exit;
}

if (isset($data) && count($data) > 0) {
	$savethese = array();
	$data['sys_files_checksum'] = $modx->manager->getSystemChecksum($data['check_files_onlogin']);
	foreach ($data as $k => $v) {
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
					$v = $data['site_start'];
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
	if (isset($data['reset_template'])) {
		$template = $data['default_template'];
		$oldtemplate = $data['old_template'];
		$tbl_site_content = $modx->getFullTableName('site_content');
		$reset = $data['reset_template'];
		if    ($reset==1) $modx->db->update("template='{$template}'", $tbl_site_content, "type='document'");
		elseif($reset==2) $modx->db->update("template='{$template}'", $tbl_site_content, "template='{$oldtemplate}'");
	}
	
	// empty cache
	$modx->clearCache(); // first empty the cache
}
header("Location: index.php?a=7&r=10");
