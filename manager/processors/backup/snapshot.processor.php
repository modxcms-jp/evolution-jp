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

$filename = $backup->store()->normalizeName(trim(post_strvar('file_name')));

// 失敗も成功と同じく、バックアップの画面のスナップショットのタブに出す
if ($filename !== '' && !$backup->store()->isValidFilename($filename)) {
    // 保存先の外や .sql 以外のファイルを作らせないため、ディレクトリを含まない名前に限る
    $result = 'snapshot_invalid_file_name';
} elseif (!$backup->store()->ensureDir()) {
    $result = 'snapshot_mkdir_failed';
} elseif ($backup->createSnapshot($filename, post_boolvar('contentsOnly')) === false) {
    // 空なら自動の名前になる（古い世代の削除は createSnapshot() が行う）
    $result = 'snapshot_failed';
} else {
    $result = 'snapshot_ok';
}
sessionv('*result_msg', $result);
header('Location: index.php?a=93');
exit;
