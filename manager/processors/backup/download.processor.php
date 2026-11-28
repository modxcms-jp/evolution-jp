<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

// 送り先はバックアップの画面の非表示の iframe。失敗は、親の画面が iframe の読み込みで #evoBackupError を読んで知らせる
$backupFailed = function ($message) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><p id="evoBackupError">'
        . hsc($message) . '</p></body></html>';
    exit;
};
if (!evo()->hasPermission('bk_manager')) {
    $backupFailed(lang('error_no_privileges'));
}

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';

$requested = postv('chk', []);
// 書き終えてからヘッダーを送る（失敗したときに壊れたファイルをダウンロードさせない）
$path = (new EvoBackupManager())->writeDownload(is_array($requested) ? $requested : [], post_boolvar('droptables'));
if ($path === 'no_tables') {
    $backupFailed(lang('bkmgr_backup_no_tables'));
}
if ($path === 'failed') {
    $backupFailed(lang('bkmgr_backup_failed'));
}
header('Expires: 0');
header('Cache-Control: private');
header('Pragma: cache');
header('Content-Type: application/download');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . EvoBackupManager::downloadName() . '"');
readfile($path);
unlink($path);
exit;
