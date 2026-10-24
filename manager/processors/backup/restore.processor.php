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
if (postv('mode') === 'restore1') {
    if (filev('sqlfile.tmp_name')) {
        $source = (string) file_get_contents(filev('sqlfile.tmp_name'));
    }
} elseif (postv('mode') === 'restore2') {
    $path = $backup->path((string) postv('filename', ''));
    if ($path === false) {
        exit('Error');
    }
    $source = (string) file_get_contents($path);
}

if ($source !== '') {
    $result = $backup->restore($source);
    sessionv('*restore_result', [
        'mode'               => postv('mode'),
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
header('Location: index.php?r=9&a=93');
