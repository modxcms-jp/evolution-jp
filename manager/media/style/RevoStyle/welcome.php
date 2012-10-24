<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
welcomeRevoStyle($modx,$_lang);

function welcomeRevoStyle($modx,$_lang)
{
	$tpl = '<a class="hometblink" href="[+action+]"><img src="[(site_url)]manager/media/style/RevoStyle/images/[+imgpath+]" /><br />[+title+]</a>' . "\n";
	$tpl = '<span class="wm_button" style="border:0">' . $tpl . '</span>';
	
	if($modx->hasPermission('new_document')||$modx->hasPermission('save_document')) {
		$ph['imgpath'] = 'icons/32x/newdoc.png';
		$ph['action']    = 'index.php?a=4';
		$ph['title']   = $_lang['add_resource'];
		$src = $modx->parsePlaceholder($tpl,$ph);
		$modx->setPlaceholder('NewDocIcon',$src);
	}
	
	if($modx->hasPermission('settings')) {
		$ph['imgpath'] = 'icons/32x/settings.png';
		$ph['action']    = 'index.php?a=17';
		$ph['title']   = $_lang['edit_settings'];
		$src = $modx->parsePlaceholder($tpl,$ph);
		$modx->setPlaceholder('SettingsIcon',$src);
	}
}
