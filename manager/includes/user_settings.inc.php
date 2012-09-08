<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

// START HACK
if (isset($modx))                          $user_id = $modx->getLoginUserID();
elseif(isset($_SESSION['mgrInternalKey'])) $user_id = $_SESSION['mgrInternalKey'];
else                                       $user_id = '';

// END HACK

if (!empty($user_id))
{
	// Raymond: grab the user settings from the database.
	$tbl_user_settings = $modx->getFullTableName('user_settings');
	$rs = $modx->db->select('setting_name, setting_value',$tbl_user_settings,"user='{$user_id}'");
	$number_of_settings = $modx->db->getRecordCount($rs);
	
	while ($row = $modx->db->getRow($rs))
	{
		$settings[$row['setting_name']] = $row['setting_value'];
		if (isset($modx->config))
		{
			$modx->config[$row['setting_name']] = $row['setting_value'];
		}
	}
	
	extract($settings, EXTR_OVERWRITE);
}
