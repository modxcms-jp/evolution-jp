<?php
/**
 * HTML エクスポートのジョブの JSON の窓口（a=308）。POST のみ。
 *
 * op=start  : ジョブを始める（target, allow_ids, ignore_ids, includenoncache, repl_before, repl_after）
 * op=step   : 持ち時間（EvoSiteExportJob::screenBudget()。3 秒）ぶん進める
 * op=status : 保存した状態を返す
 * op=cancel : 中止する
 *
 * 応答: {"state":"running|done|cancelled|busy|error|none","job_id":"…","total":…,"done":…,"success":…,"skipped":…,
 *        "failed":…,"failures":[{"id","pagetitle","reason"}],"messages":[…],"elapsed":…,"output":"","message":""}
 * job_id は始めたジョブの識別子（start・step・status・cancel の応答に含む）。画面は別のジョブに替わったことの検出に使う。
 * messages はプラグインのイベントの出力（HTML）、message は言語ファイルの案内（HTML）。
 * pagetitle など利用者のデータは、画面で出すときに必ずエスケープする。
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

require_once MODX_CORE_PATH . 'site_export_job.class.inc.php';

if (!is_post()) {
    exportSiteJson(['state' => 'error', 'message' => 'Method Not Allowed'], 405);
}
if (!hasPermission('export_static')) {
    exportSiteJson(['state' => 'error', 'message' => lang('error_no_privileges')], 403);
}

$job = new EvoSiteExportJob();

switch (postv('op')) {
    case 'start':
        $target = 'all';
        $ids = '';
        if (postv('target') === 'allow_ids' && trim((string)postv('allow_ids')) !== '') {
            $target = 'allow';
            $ids = postv('allow_ids');
        } elseif (postv('target') === 'ignore_ids' && trim((string)postv('ignore_ids')) !== '') {
            $target = 'ignore';
            $ids = postv('ignore_ids');
        }

        // 次に画面を開いたときの既定値
        sessionv('*export_allow_ids', postv('allow_ids'));
        sessionv('*export_ignore_ids', postv('ignore_ids'));
        sessionv('*export_target', postv('target'));
        sessionv('*export_includenoncache', postv('includenoncache', 0));
        sessionv('*export_repl_before', postv('repl_before'));
        sessionv('*export_repl_after', postv('repl_after'));
        // 処理中でも中止などの要求を受けられるよう、セッションのロックを手放す
        session_write_close();

        $result = $job->start([
            'target'           => $target,
            'ids'              => array_map('intval', array_map('trim', explode(',', (string)$ids))),
            'include_noncache' => postv('includenoncache', 0) == 1,
            'repl_before'      => postv('repl_before'),
            'repl_after'       => postv('repl_after'),
        ]);
        exportSiteLog('HTML export: start', $result);
        break;
    case 'step':
        session_write_close();
        $result = $job->step(EvoSiteExportJob::screenBudget(), '', (string)postv('job_id', ''));
        break;
    case 'status':
        session_write_close();
        $result = $job->status();
        break;
    case 'cancel':
        session_write_close();
        $result = $job->cancel();
        exportSiteLog('HTML export: cancel', $result);
        break;
    default:
        exportSiteJson(['state' => 'error', 'message' => 'Bad Request'], 400);
}

exportSiteJson($result);

/**
 * 開始と中止だけ操作ログ（manager_log）に残す。step と status は何度も呼ばれるので残さない。
 * この窓口は exit で終えるため、manager/index.php の共通の記録が走らない。
 * 受け付けられなかった要求（busy・error・none）や、すでに終わっていたジョブへの中止は残さない。
 */
function exportSiteLog($message, array $result)
{
    if (!in_array($result['state'], ['running', 'cancelled'], true)) {
        return;
    }
    include_once MODX_CORE_PATH . 'log.class.inc.php';
    $log = new logHandler();
    $log->initAndWriteLog($message);
}

function exportSiteJson(array $data, $status = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    // 続きを進めるたびに操作ログが増えないよう、ここで終える
    exit;
}
