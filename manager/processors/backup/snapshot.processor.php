<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('bk_manager')) {
    alert()->setError(3);
    alert()->dumpError();
}

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';
$backup = new EvoBackupManager();

$filename = trim((string) postv('file_name', ''));
if ($filename !== '') {
    if (strtolower(substr($filename, -4)) !== '.sql') {
        $filename .= '.sql';
    }
    // 保存先の外や .sql 以外のファイルを作らせないため、ディレクトリを含まない名前に限る
    if (!$backup->isValidFilename($filename)) {
        alert()->setError(1, lang('bkmgr_invalid_file_name'));
        alert()->dumpError();
    }
}

if (!$backup->ensureDir()) {
    echo evo()->parseText(
        lang('bkmgr_alert_mkdir'),
        ['snapshot_path' => hsc($backup->dir())]
    );
    exit;
}

// 空なら自動の名前になる（古い世代の削除は createSnapshot() が行う）
if ($backup->createSnapshot($filename, (bool) postv('contentsOnly')) !== false) {
    sessionv('*result_msg', 'snapshot_ok');
    header('Location: index.php?a=93');
    exit;
}

alert()->setError(1, 'Unable to Backup Database');
alert()->dumpError();
