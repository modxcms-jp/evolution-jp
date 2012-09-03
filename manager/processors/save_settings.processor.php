<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings')) {
	$e->setError(3);
	$e->dumpError();
}
if($_POST['friendly_urls']==='1' && strpos($_SERVER['SERVER_SOFTWARE'],'IIS')===false)
{
	$htaccess        = $modx->config['base_path'] . '.htaccess';
	$sample_htaccess = $modx->config['base_path'] . 'sample.htaccess';
	if(!file_exists($htaccess))
	{
		if(file_exists($sample_htaccess))
		{
			if(!@rename($sample_htaccess,$htaccess))
{
	$warnings[] = $_lang["settings_friendlyurls_alert"];
			}
			elseif($modx->config['base_url']!=='/')
			{
				$subdir = rtrim($modx->config['base_url'],'/');
				$_ = file_get_contents($htaccess);
				$_ = preg_replace('@RewriteBase.+@',"RewriteBase {$subdir}", $_);
				if(!@file_put_contents($htaccess,$_))
				{
					$warnings[] = $_lang["settings_friendlyurls_alert2"];
				}
			}
		}
	}
	else
	{
		$_ = file_get_contents($htaccess);
		if(strpos($_,'RewriteBase')===false)
		{
			$warnings[] = $_lang["settings_friendlyurls_alert2"];
		}
	}
}
if(!file_exists($_POST['rb_base_dir']))      $warnings[] = $_lang["configcheck_rb_base_dir"] ;
if(!file_exists($_POST['filemanager_path'])) $warnings[] = $_lang["configcheck_filemanager_path"];

if(0< count($warnings))
{
	$modx->manager->saveFormValues('17');
	$msg = join("\n",$warnings);
	$modx->webAlert($msg,'index.php?a=17');
	exit;
}

if (isset($_POST) && count($_POST) > 0) {
	$savethese = array();
	foreach ($_POST as $k => $v) {
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
				if (trim($v) == '' || !is_numeric($v))
				{
					$v = $_POST['site_start'];
				}
				break;
	
			case 'lst_custom_contenttype':
			case 'txt_custom_contenttype':
				// Skip these
				continue 2;
				break;
			case 'manager_language':
				$langFile = realpath(MODX_BASE_PATH . "manager/includes/lang/{$v}.inc.php");
				if(!file_exists($langFile))
				{
					$v = 'english';
				}
			default:
			break;
		}
		$v = is_array($v) ? implode(',', $v) : $v;

		$savethese[] = "('" . $modx->db->escape($k)."', '" . $modx->db->escape($v) . "')";
	}
	
	// Run a single query to save all the values
	$sql = "REPLACE INTO ".$modx->getFullTableName("system_settings")." (setting_name, setting_value)
		VALUES ".implode(', ', $savethese);
	if(!@$rs = $modx->db->query($sql)) {
		echo "Failed to update setting value!";
		exit;
	}
	
	// Reset Template Pages
	if (isset($_POST['reset_template'])) {
		$template = $_POST['default_template'];
		$oldtemplate = $_POST['old_template'];
		$tbl_site_content = $modx->getFullTableName('site_content');
		$reset = $_POST['reset_template'];
		if    ($reset==1) $modx->db->update("template='{$template}'", $tbl_site_content, "type='document'");
		elseif($reset==2) $modx->db->update("template='{$template}'", $tbl_site_content, "template='{$oldtemplate}'");
	}
	// lose the POST now, gets rid of quirky issue with Safari 3 - see FS#972
	unset($_POST);
	
	// empty cache
	$modx->clearCache(); // first empty the cache
}
header("Location: index.php?a=7&r=10");
