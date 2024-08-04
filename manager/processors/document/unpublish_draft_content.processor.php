<?php
/*
 *  公開設定中の下書きの公開日を削除
 */
if( !defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if( !$modx->hasPermission('save_document') || !$modx->hasPermission('publish_document') ){
	$e->setError(3);
	$e->dumpError();
}

$id = $_REQUEST['id'];

$modx->loadExtension('REVISION');
if( $modx->revision->chStandbytoDraft($id) ){
	if( $_GET['back'] == 'publist' ){
		$header = "Location: index.php?a=70&r=1";
	}else{
		$header = "Location: index.php?a=3&id={$id}&r=1";
	}
	header($header);
	exit;
}

$modx->webAlertAndQuit("公開日の削除に失敗しました。");

