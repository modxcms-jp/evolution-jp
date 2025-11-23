<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('bk_manager')) {
    alert()->setError(3);
    alert()->dumpError();
}

$snapshot_path = snapshot_path();
check_snapshot_path($snapshot_path);
$filename = filename();

@set_time_limit(120); // set timeout limit to 2 minutes
include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');
$dumper = new Mysqldumper();
$dumper->mode = 'snapshot';
$dumper->contentsOnly = postv('contentsOnly') ? 1 : 0;
$output = $dumper->createDump();
$dumper->snapshot($snapshot_path . $filename, $output);

$pattern = $snapshot_path . '*.sql';
$files = glob($pattern, GLOB_NOCHECK);
$total = ($files[0] !== $pattern) ? count($files) : 0;
arsort($files);
$limit = 0;
while (10 < $total && $limit < 50) {
    $del_file = array_pop($files);
    unlink($del_file);
    $total = count($files);
    $limit++;
}

if ($output) {
    $_SESSION['result_msg'] = 'snapshot_ok';
    header('Location: index.php?a=93');
    exit;
}

alert()->setError(1, 'Unable to Backup Database');
alert()->dumpError();

function snapshot_path()
{
    if (strpos(config('snapshot_path', ''), MODX_BASE_PATH) !== 0) {
        if (is_dir(MODX_BASE_PATH . 'temp/backup/')) {
            return MODX_BASE_PATH . 'temp/backup/';
        }
        if (is_dir(MODX_BASE_PATH . 'assets/backup/')) {
            return MODX_BASE_PATH . 'assets/backup/';
        }
    }
    return config('snapshot_path');
}

function check_snapshot_path($snapshot_path)
{
    if (!is_dir(rtrim($snapshot_path, '/'))) {
        mkdir(rtrim($snapshot_path, '/'));
        @chmod(rtrim($snapshot_path, '/'), 0777);
    }
    if (!is_writable(rtrim($snapshot_path, '/'))) {
        echo evo()->parseText(
            lang('bkmgr_alert_mkdir'),
            ['snapshot_path' => $snapshot_path]
        );
        exit;
    }
    if (!is_file($snapshot_path . '.htaccess')) {
        file_put_contents(
            $snapshot_path . '.htaccess',
            "order deny,allow\ndeny from all\n"
        );
    }
}

function filename()
{
    if (postv('file_name')) {
        return postv('file_name');
    }
    $today = str_replace(
        ['/', ' ', ':'],
        ['-', '-', ''],
        strtolower(
            evo()->toDateFormat(request_time())
        )
    );
    global $settings_version;
    return sprintf('%s-%s.sql', $today, $settings_version);
}
