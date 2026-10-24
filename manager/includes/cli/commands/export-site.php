<?php

require_once MODX_CORE_PATH . 'site_export_job.class.inc.php';

// 画面用の文言（HTML）は、タグを外してエンティティを戻した文字で出す
$plain = function ($text) {
    return trim(html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
};
// 失敗は必ずここを通り、終了コード 1 で終わる
$fail = function ($message) use ($plain) {
    cli_usage($plain($message), 1);
};

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
        $fail($usage);
    }
}

if (!in_array($target, ['all', 'allow', 'ignore'], true)) {
    $fail($usage);
}
$ids = [];
if ($target !== 'all') {
    if ($idsArg === null || !preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $idsArg)) {
        $fail("Error: --ids=1,2,3 is required when --target={$target}.\n" . $usage);
    }
    $ids = array_map('intval', explode(',', $idsArg));
}
if ($siteUrl !== '' && !preg_match('#^https?://[^\s/]+#i', $siteUrl)) {
    $fail("Error: --site-url must start with http:// or https://\n" . $usage);
}

$job = new EvoSiteExportJob();

// 画面のジョブ（temp/export_job.json）と共有する。途中のジョブは --restart が無ければ壊さない
$current = $job->status();
if ($current['state'] === EvoSiteExportJob::STATE_RUNNING && !$restart) {
    $fail(sprintf(
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
]);
if ($result['state'] === EvoSiteExportJob::STATE_BUSY) {
    $fail('Error: another export is in progress. Try again later.');
}
if ($result['state'] !== EvoSiteExportJob::STATE_RUNNING) {
    $fail('Error: ' . ($result['message'] ?? 'failed to start the export.'));
}

// 操作ログ（manager_log）に CLI からの開始として残す。ログインユーザーがいないので、ユーザー名を CLI にする
include_once MODX_CORE_PATH . 'log.class.inc.php';
// CLI では input_any('a') が 0 になるため、画面からの開始と同じ action（308）を渡して書く
(new logHandler())->initAndWriteLog('HTML export: start (CLI)', '0', 'CLI', 308, 'x', '-');

$printMessages = function (array $messages) use ($plain) {
    foreach ($messages as $message) {
        cli_out($plain($message));
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
$jobId = $result['job_id'];
$printedBucket = 0;
$budget = EvoSiteExportJob::defaultBudget();
// 持ち時間つきで繰り返す（ベアラートークンを step ごとに発行し直し、1 つを長く使わないため）
while (true) {
    // 取得先（--site-url）は状態に保存されないので、そのつど渡す
    $result = $job->step($budget, $siteUrl);
    if ($result['state'] === EvoSiteExportJob::STATE_BUSY) {
        // 画面のタブなど、ほかが処理中。間をおいて送り直す
        sleep(1);
        continue;
    }
    if ($result['state'] === EvoSiteExportJob::STATE_NONE) {
        break;
    }
    if ($result['job_id'] !== $jobId) {
        $fail('Error: the export job was replaced by another one (restarted elsewhere). Stopping this command.');
    }
    if ($result['state'] !== EvoSiteExportJob::STATE_RUNNING) {
        break;
    }
    // 処理が戻るたびに、100 件の区切りを越えていれば出す
    $bucket = intdiv($result['done'], 100);
    if ($bucket > $printedBucket) {
        $printedBucket = $bucket;
        $progress($result);
    }
}

if ($result['state'] === EvoSiteExportJob::STATE_NONE) {
    $fail('Error: the export job disappeared (temp/export_job.json was removed).');
}

if (isset($result['total'])) {
    $progress($result);
    $printMessages($result['messages']);
    if ($result['output'] !== '') {
        cli_out($plain($result['output']));
    }
    cli_out(sprintf('Elapsed: %s sec', $result['elapsed']));

    if ($result['failures']) {
        cli_err('Failures:');
        foreach ($result['failures'] as $f) {
            cli_err('  ' . $plain(sprintf('#%d %s: %s', $f['id'], $f['pagetitle'], $f['reason'])));
        }
        if ($result['failed'] > count($result['failures'])) {
            cli_err(sprintf('  ... and %d more', $result['failed'] - count($result['failures'])));
        }
    }
}
if ($result['state'] !== EvoSiteExportJob::STATE_DONE) {
    $fail('Error: ' . (($result['message'] ?? '') ?: 'the export stopped (' . $result['state'] . ').'));
}
exit($result['failed'] > 0 ? 1 : 0);
