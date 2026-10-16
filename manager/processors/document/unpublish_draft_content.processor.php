<?php
/*
 *  公開設定中の下書きの公開日を削除
 */
if( !defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if( !$modx->hasPermission('save_document') || !$modx->hasPermission('publish_document') ){
	alert()->setError(3);
	alert()->dumpError();
}

$id = request_intvar('id');
if (!$id) {
	alert()->setError(2);
	alert()->dumpError();
}
if (!$modx->checkPermissions($id)) {
	alert()->setError(3);
	alert()->dumpError();
}
if (!manager()->isInAllowedParents($id)) {
	alert()->setError(3, lang('access_permission_denied'));
	alert()->dumpError();
}

$modx->loadExtension('REVISION');
if( $modx->revision->chStandbytoDraft($id) ){
	if( getv('back') === 'publist' ){
		$header = "Location: index.php?a=70&r=1";
	}else{
		$header = "Location: index.php?a=3&id={$id}&r=1";
	}
	header($header);
	exit;
}

$modx->webAlertAndQuit("公開日の削除に失敗しました。");

