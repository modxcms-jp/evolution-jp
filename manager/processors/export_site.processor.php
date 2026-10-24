<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}
if (!hasPermission('export_static')) {
    alert()->setError(3);
    alert()->dumpError();
}

evo()->loadExtension('EXPORT_SITE');

// エクスポートは開始時に出力先の中身を消すため、temp/ の中だけに限る（./ や ../ も解決してから比べる）
$real_dir = realpath(str_replace('[(base_path)]', MODX_BASE_PATH, config('export_dir')));
if ($real_dir === false) {
    return lang('export_site_target_unwritable');
}
$real_temp = realpath(MODX_BASE_PATH . 'temp');
if ($real_temp === false || strpos($real_dir, $real_temp . '/') !== 0) {
    return lang('export_site_target_outside_temp');
}
// 以降の処理は MODX_BASE_PATH で始まるパスを前提にするため、temp/ からの相対で組み直す
$export_dir = MODX_BASE_PATH . 'temp' . substr($real_dir, strlen($real_temp));

if (!is_dir($export_dir) || !is_writable($export_dir)) {
    return lang('export_site_target_unwritable');
}

$maxtime = preg_match('@^[0-9]+$@', postv('maxtime')) ? postv('maxtime') : 60;
$exportstart = microtime(true);

$info = [];
$info['allow_ids'] = getIds('allow_ids');
$info['ignore_ids'] = getIds('ignore_ids');
$info['repl_after'] = postv('repl_after');
$info['repl_before'] = postv('repl_before');
$info['export_dir'] = $export_dir;

$evtOut = evo()->invokeEvent('OnExportPreExec', $info);
if (is_array($evtOut)) {
    echo implode("\n", $evtOut);
}

if (sessionv('export_allow_ids') !== getIds('allow_ids')
    || sessionv('export_ignore_ids') !== getIds('ignore_ids')
    || sessionv('export_includenoncache') !== postv('includenoncache')
    || sessionv('export_repl_before') !== postv('repl_before')
    || sessionv('export_repl_after') !== postv('repl_after')) {
    $modx->clearCache();
}

sessionv('*export_allow_ids', postv('allow_ids'));
sessionv('*export_ignore_ids', postv('ignore_ids'));
sessionv('*export_target', postv('target'));
sessionv('*export_includenoncache', postv('includenoncache', 0));
sessionv('*export_repl_before', postv('repl_before'));
sessionv('*export_repl_after', postv('repl_after'));

if (getIds('allow_ids') !== '') {
    $target = 'allow';
    $target_ids = parseIds(getIds('allow_ids'));
} elseif (getIds('ignore_ids') !== '') {
    $target = 'ignore';
    $target_ids = parseIds(getIds('ignore_ids'));
} else {
    $target = 'all';
    $target_ids = [];
}

$exporter = $modx->export->create([
    'target'           => $target,
    'ids'              => $target_ids,
    'include_noncache' => sessionv('export_includenoncache') == 1,
    'repl_before'      => postv('repl_before'),
    'repl_after'       => postv('repl_after'),
    'export_dir'       => $export_dir,
    'skip_existing'    => true,
]);

$ids = $exporter->targetIds();
$total = count($ids);
$output = sprintf(lang('export_site_numberdocs'), $total);

$lock_file_path = MODX_CACHE_PATH . 'export.lock';
if (!is_file($lock_file_path)) {
    $exporter->clearOutputDir();
}
touch($lock_file_path);

$lines = [];
$count = 0;
foreach ($ids as $id) {
    $count++;
    if (!empty($maxtime) && $maxtime < time() - request_time()) {
        return evo()->parseText(
            lang('export_site_exit_maxtime'),
            ['count' => $count, 'total' => $total, 'maxtime' => $maxtime]
        );
    }

    $result = $exporter->exportOne($id);

    if ($result['reason'] === 'basic_auth') {
        $lines[] = '<div style="color:#a00; font-weight:bold; margin-bottom:1em;">' . lang('export_site_basic_auth_warning') . '</div>';
        break;
    }

    $lines[] = evo()->parseText(
        lang('export_site_exporting_document'),
        [
            'status'    => makeStatusMsg($result['reason'], $result['status'] === 'failed' ? 'fail' : 'success', $export_dir),
            'count'     => $count,
            'id'        => $result['id'],
            'pagetitle' => $result['pagetitle'],
        ]
    );
}

if (is_file($lock_file_path)) {
    unlink($lock_file_path);
}
$output .= implode("\n", $lines);

$totaltime = microtime(true) - $exportstart;
$output .= sprintf('<p>' . lang('export_site_time') . '</p>', round($totaltime, 3));

$info = [];
$info['allow_ids'] = getIds('allow_ids');
$info['ignore_ids'] = getIds('ignore_ids');
$info['repl_after'] = postv('repl_after');
$info['repl_before'] = postv('repl_before');
$info['export_dir'] = $export_dir;
$info['output'] = $output;
$info['totatlime'] = $totaltime;
$evtOut = evo()->invokeEvent('OnExportExec', $info);
if (is_array($evtOut)) {
    echo implode("\n", $evtOut);
}

return $output;

function getIds($target)
{
    if (postv('target') === 'all' || postv('target') !== $target) {
        return '';
    }
    return postv($target);
}

function parseIds($ids)
{
    return array_map('intval', array_map('trim', explode(',', $ids)));
}

function makeStatusMsg($reason, $status, $export_dir)
{
    $messages = [
        'failed_no_write'    => lang('export_site_failed_no_write') . ' - ' . $export_dir . '/',
        'failed_no_retrieve' => lang('export_site_failed_no_retrieve'),
        'failed_no_open'     => lang('export_site_failed_no_open'),
        'success_skip_doc'   => lang('export_site_success_skip_doc'),
        'success_skip_dir'   => lang('export_site_success_skip_dir'),
    ];
    return evo()->parseText(
        ' <span class="[+status+]">[+msg1+]</span> [+msg2+]</span>',
        [
            'status' => $status,
            'msg1'   => $status === 'success' ? lang('export_site_success') : lang('export_site_failed'),
            'msg2'   => isset($messages[$reason]) ? $messages[$reason] : '',
        ]
    );
}
