<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
// 復元は DB を丸ごと書き換えるため、管理者だけに許す
if (!evo()->hasPermission('bk_manager') || !manager()->isAdmin()) {
    alert()->setError(3);
    alert()->dumpError();
}

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';
$backup = new EvoBackupManager();

$source = '';
$failure = '';
$mode = post_choicevar('mode', ['restore1', 'restore2'], '');
// 保存先が使えなければ、復元の前の控えを取れないので戻さない（別の場所には切り替えない）
if (!$backup->store()->ensureDir()) {
    $failure = $backup->store()->problem() === EvoSnapshotStore::UNWRITABLE ? 'restore_no_snapshot' : 'restore_path_unusable';
} elseif ($mode === 'restore1') {
    $uploadError = (int) filev('sqlfile.error', UPLOAD_ERR_NO_FILE);
    $tmpName = (string) filev('sqlfile.tmp_name', '');
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        $failure = 'restore_no_file';
    } elseif ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        $failure = 'restore_too_large';
    } elseif ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
        $failure = 'restore_upload_failed';
    } else {
        $source = (string) file_get_contents($tmpName);
        if ($source === '') {
            $failure = 'restore_no_file';
        }
    }
} elseif ($mode === 'restore2') {
    $path = $backup->store()->path(post_strvar('filename'));
    if ($path === false) {
        $failure = 'restore_invalid_file';
    } else {
        $source = (string) file_get_contents($path);
        if ($source === '') {
            $failure = 'restore_invalid_file';
        }
    }
}

if ($failure !== '') {
    sessionv('*restore_result', ['mode' => $mode]);
    sessionv('*result_msg', $failure);
}

if ($failure === '' && $source !== '') {
    $result = $backup->restore($source);
    sessionv('*restore_result', [
        'mode'               => $mode,
        'ok'                 => $result['ok'],
        'error'              => $result['error'],
        'failed_at'          => $result['failed_at'],
        'pre_restore'        => $result['pre_restore'],
        'pre_restore_failed' => $result['pre_restore_failed'],
    ]);
    if ($result['pre_restore_failed']) {
        sessionv('*result_msg', 'restore_no_snapshot');
    } elseif (!$result['ok']) {
        sessionv('*result_msg', 'restore_failed');
    } elseif ($result['error'] !== '') {
        sessionv('*result_msg', 'restore_warn');
    } else {
        sessionv('*result_msg', 'import_ok');
    }
}
header('Location: index.php?refresh=all&a=93');
