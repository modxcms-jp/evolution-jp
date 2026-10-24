<?php

require_once MODX_CORE_PATH . 'site_export_job.class.inc.php';

$usage = 'Usage: php evo export:site [--target=all|allow|ignore] [--ids=1,2,3] [--include-noncache]'
    . ' [--replace-before=TEXT] [--replace-after=TEXT] [--site-url=URL] [--restart]';

$target = 'all';
$idsArg = null;
$includeNoncache = false;
$replBefore = '';
$replAfter = '';
$siteUrl = '';
$restart = false;
foreach ($args as $arg) {
    if (strpos($arg, '--target=') === 0) {
        $target = substr($arg, strlen('--target='));
    } elseif (strpos($arg, '--ids=') === 0) {
        $idsArg = substr($arg, strlen('--ids='));
    } elseif ($arg === '--include-noncache') {
        $includeNoncache = true;
    } elseif (strpos($arg, '--replace-before=') === 0) {
        $replBefore = substr($arg, strlen('--replace-before='));
    } elseif (strpos($arg, '--replace-after=') === 0) {
        $replAfter = substr($arg, strlen('--replace-after='));
    } elseif (strpos($arg, '--site-url=') === 0) {
        $siteUrl = substr($arg, strlen('--site-url='));
    } elseif ($arg === '--restart') {
        $restart = true;
    } else {
        cli_usage($usage);
    }
}

if (!in_array($target, ['all', 'allow', 'ignore'], true)) {
    cli_usage($usage);
}
$ids = [];
if ($target !== 'all') {
    if ($idsArg === null || !preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $idsArg)) {
        cli_usage("Error: --ids=1,2,3 is required when --target={$target}.\n" . $usage);
    }
    $ids = array_map('intval', explode(',', $idsArg));
}
if ($siteUrl !== '' && !preg_match('#^https?://[^\s/]+#i', $siteUrl)) {
    cli_usage("Error: --site-url must start with http:// or https://\n" . $usage);
}

$job = new EvoSiteExportJob();

// 画面のジョブ（temp/export_job.json）と共有する。途中のジョブは --restart が無ければ壊さない
$current = $job->status();
if ($current['state'] === EvoSiteExportJob::STATE_RUNNING && !$restart) {
    cli_usage(sprintf(
        'Error: an export job is already running (%d / %d). Finish it from the manager (a=83), or use --restart to discard it and start over.',
        $current['done'],
        $current['total']
    ));
}

$result = $job->start([
    'target'           => $target,
    'ids'              => $ids,
    'include_noncache' => $includeNoncache,
    'repl_before'      => $replBefore,
    'repl_after'       => $replAfter,
    'site_url'         => $siteUrl,
]);
if ($result['state'] === EvoSiteExportJob::STATE_BUSY) {
    cli_usage('Error: another export is in progress. Try again later.');
}
if ($result['state'] !== EvoSiteExportJob::STATE_RUNNING) {
    cli_usage('Error: ' . ($result['message'] ?? 'failed to start the export.'));
}

// 操作ログ（manager_log）に CLI からの開始として残す。ログインユーザーがいないので、ユーザー名を CLI にする
include_once MODX_CORE_PATH . 'log.class.inc.php';
(new logHandler())->initAndWriteLog('HTML export: start (CLI)', '0', 'CLI');

$printMessages = function (array $messages) {
    foreach ($messages as $message) {
        cli_out(trim(strip_tags($message)));
    }
};
$printMessages($result['messages']);

$progress = function (array $r) {
    cli_out(sprintf(
        '%d / %d (success %d, skipped %d, failed %d)',
        $r['done'],
        $r['total'],
        $r['success'],
        $r['skipped'],
        $r['failed']
    ));
};

cli_out("Exporting {$result['total']} resources...");
$printedBucket = 0;
$budget = EvoSiteExportJob::defaultBudget();
// 持ち時間つきで繰り返す（ベアラートークンを step ごとに発行し直し、1 つを長く使わないため）
while (true) {
    $result = $job->step($budget);
    if ($result['state'] === EvoSiteExportJob::STATE_BUSY) {
        cli_usage('Error: another export is in progress.');
    }
    if ($result['state'] !== EvoSiteExportJob::STATE_RUNNING) {
        break;
    }
    $bucket = intdiv($result['done'], 100);
    if ($bucket > $printedBucket) {
        $printedBucket = $bucket;
        $progress($result);
    }
}

if ($result['state'] === EvoSiteExportJob::STATE_NONE) {
    cli_usage('Error: the export job disappeared (temp/export_job.json was removed).');
}

if (isset($result['total'])) {
    $progress($result);
    $printMessages($result['messages']);
    if ($result['output'] !== '') {
        cli_out(trim(strip_tags($result['output'])));
    }
    cli_out(sprintf('Elapsed: %s sec', $result['elapsed']));

    if ($result['failures']) {
        cli_err('Failures:');
        foreach ($result['failures'] as $f) {
            cli_err(sprintf('  #%d %s: %s', $f['id'], $f['pagetitle'], $f['reason']));
        }
        if ($result['failed'] > count($result['failures'])) {
            cli_err(sprintf('  ... and %d more', $result['failed'] - count($result['failures'])));
        }
    }
}
if ($result['state'] !== EvoSiteExportJob::STATE_DONE) {
    cli_usage('Error: ' . (($result['message'] ?? '') ?: 'the export stopped (' . $result['state'] . ').'));
}
exit($result['failed'] > 0 ? 1 : 0);
