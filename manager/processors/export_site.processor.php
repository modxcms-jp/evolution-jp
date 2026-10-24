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

$modx->export->maxtime = preg_match('@^[0-9]+$@', postv('maxtime')) ? postv('maxtime') : 60;
$modx->export->setExportDir($export_dir);

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

$total = $modx->export->getTotal(
    getIds('allow_ids'),
    getIds('ignore_ids'),
    sessionv('export_includenoncache')
);

$output = sprintf(lang('export_site_numberdocs'), $total);
$modx->export->total = $total;

$modx->export->repl_before = postv('repl_before');
$modx->export->repl_after = postv('repl_after');

$output .= $modx->export->run();

$exportend = $modx->export->get_mtime();
$totaltime = ($exportend - $modx->export->exportstart);
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
