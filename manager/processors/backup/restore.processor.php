<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('bk_manager')) {
    alert()->setError(3);
    alert()->dumpError();
}

// Backup Manager by Raymond:

$source = '';
if (postv('mode') === 'restore1') {
    if (postv('textarea')) {
        $source = trim(postv('textarea'));
        $_SESSION['textarea'] = $source . "\n";
    } elseif (filev('sqlfile.tmp_name')) {
        $source = file_get_contents(filev('sqlfile.tmp_name'));
    }
} elseif (postv('mode') === 'restore2') {
    if (!config('snapshot_path') || strpos(config('snapshot_path'), MODX_BASE_PATH) === false) {
        if (is_dir(MODX_BASE_PATH . 'temp/backup/')) {
            $snapshot_path = MODX_BASE_PATH . 'temp/backup/';
        } elseif (is_dir(MODX_BASE_PATH . 'assets/backup/')) {
            $snapshot_path = MODX_BASE_PATH . 'assets/backup/';
        }
    } else {
        $snapshot_path = config('snapshot_path');
    }

    if (strpos(postv('filename'), '..') === false) {
        $snapshot_path .= postv('filename');
    }
    if (!is_file($snapshot_path)) {
        exit('Error');
    }
    $source = file_get_contents($snapshot_path);
}

if ($source) {
    include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');
    $dumper = new Mysqldumper();
    $dumper->import_sql($source);
}
header('Location: index.php?r=9&a=93');
