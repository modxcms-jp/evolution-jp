<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if($modx->hasPermission('new_document')||$modx->hasPermission('save_document')) {
	$src = get_icon($_lang['add_resource'], 4, '[(site_url)]assets/templates/manager/images/32x32/newdoc.png');
	$modx->setPlaceholder('NewDocIcon',$src);
}

if($modx->hasPermission('settings')) {
	$src = get_icon($_lang['edit_settings'], 17, '[(site_url)]assets/templates/manager/images/32x32/settings.png');
	$modx->setPlaceholder('SettingsIcon',$src);
}
