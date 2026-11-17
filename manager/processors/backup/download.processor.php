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

require_once MODX_CORE_PATH . 'sql_dump.class.inc.php';

// 書き出すのはこのサイトの表だけ
$tables = postv('chk', '');
$tables = is_array($tables) ? array_values(array_intersect(EvoDbTables::names(), $tables)) : [];
if (!$tables) {
    $backupFailed(lang('bkmgr_backup_no_tables'));
}

@set_time_limit(120);
// 書き終えてからヘッダーを送る（失敗したときに壊れたファイルをダウンロードさせない）
$tempfile = tempnam(MODX_CACHE_PATH, 'bktemp');
if ($tempfile !== false) {
    // 途中で止められても DB 全体のダンプを残さない
    register_shutdown_function(function () use ($tempfile) {
        if (is_file($tempfile)) {
            @unlink($tempfile);
        }
    });
}
if ($tempfile === false || !EvoSqlDump::writeFile($tempfile, $tables, ['drop' => postv('droptables') !== null])) {
    if ($tempfile !== false && is_file($tempfile)) {
        unlink($tempfile);
    }
    $backupFailed(lang('bkmgr_backup_failed'));
}
header('Expires: 0');
header('Cache-Control: private');
header('Pragma: cache');
header('Content-Type: application/download');
header('Content-Length: ' . filesize($tempfile));
header(sprintf(
    'Content-Disposition: attachment; filename="%s-%s_database_backup.sql"',
    strtolower(str_replace('/', '-', evo()->toDateFormat(time(), 'dateOnly'))),
    preg_replace('/[^0-9A-Za-z._-]/', '-', (string) evo()->getVersionData('version'))
));
readfile($tempfile);
unlink($tempfile);
exit;
