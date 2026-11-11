<?php
/**
 * HTML エクスポートのジョブの JSON の窓口（a=308）。POST のみ。
 *
 * op=start  : ジョブを始める（target, allow_ids, ignore_ids, includenoncache, repl_before, repl_after）
 * op=step   : 持ち時間（EvoSiteExportJob::screenBudget()。3 秒）ぶん進める
 * op=status : 保存した状態を返す
 * op=cancel : 中止する
 * op=auth   : 取得先の Basic 認証の ID・パスワードを受け取る（job_id, user, pass）。PHP のセッションにだけ、そのジョブに
 *             結びつけて持つ（状態のファイル・DB・ログ・応答には出さない）。ジョブが終わったら（完了・中止・エラー）・中止・
 *             新しい開始で消す。途中で画面を離れ、ほかの場所でジョブが終わった・替わったときは、次にこの窓口へ来たとき
 *             （画面を開いたときの op=status など）に消す。ログアウトでもセッションごと消える。
 *             step が 401 に当たると、応答に auth_required（渡したものが通らなかったときは auth_failed も）が付く
 *
 * 応答: {"state":"running|done|cancelled|busy|error|none","job_id":"…","total":…,"done":…,"success":…,"skipped":…,
 *        "failed":…,"failures":[{"id","pagetitle","reason"}],"skips":[{"id","pagetitle","reason"}],"skips_count":…,
 *        "messages":[…],"elapsed":…,"output":"","message":""}
 * skips は理由を知らせたいスキップ（Web の閲覧を制限したページなど。ページを持たないフォルダは含まない）。
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

// Basic 認証の情報が結びついたジョブが、もう進んでいなければ消す（途中で画面を離れたあと、CLI や別の画面でジョブが終わった・替わったとき）
$authJob = sessionv('export_basic_auth.job_id');
if ($authJob !== null) {
    $current = $job->status();
    if ($current['state'] !== EvoSiteExportJob::STATE_RUNNING || ($current['job_id'] ?? '') !== $authJob) {
        sessionv('*export_basic_auth', null);
    }
}

switch (post_choicevar('op', ['start', 'step', 'status', 'cancel', 'auth'], '')) {
    case 'start':
        $target = 'all';
        $ids = '';
        $postedTarget = post_choicevar('target', ['all', 'allow_ids', 'ignore_ids'], '');
        $allowIds = post_strvar('allow_ids');
        $ignoreIds = post_strvar('ignore_ids');
        $replBefore = post_strvar('repl_before');
        $replAfter = post_strvar('repl_after');
        $includeNoncache = post_boolvar('includenoncache');
        if ($postedTarget === 'allow_ids' && trim($allowIds) !== '') {
            $target = 'allow';
            $ids = $allowIds;
        } elseif ($postedTarget === 'ignore_ids' && trim($ignoreIds) !== '') {
            $target = 'ignore';
            $ids = $ignoreIds;
        }

        // 次に画面を開いたときの既定値
        sessionv('*export_allow_ids', $allowIds);
        sessionv('*export_ignore_ids', $ignoreIds);
        sessionv('*export_target', $postedTarget);
        sessionv('*export_includenoncache', $includeNoncache);
        sessionv('*export_repl_before', $replBefore);
        sessionv('*export_repl_after', $replAfter);
        // 前のジョブの Basic 認証の情報は持ち越さない
        sessionv('*export_basic_auth', null);
        // 処理中でも中止などの要求を受けられるよう、セッションのロックを手放す
        session_write_close();

        $result = $job->start([
            'target'           => $target,
            'ids'              => array_map('intval', array_map('trim', explode(',', $ids))),
            'include_noncache' => $includeNoncache,
            'repl_before'      => $replBefore,
            'repl_after'       => $replAfter,
        ]);
        exportSiteLog('HTML export: start', $result);
        break;
    case 'step':
        $jobId = post_strvar('job_id');
        $credentials = exportSiteCredentials($jobId);
        session_write_close();
        $result = $job->step(EvoSiteExportJob::screenBudget(), '', $jobId, $credentials);
        if ($credentials !== '' && $result['state'] !== 'running' && $result['state'] !== 'busy') {
            exportSiteForgetCredentials();
        }
        break;
    case 'auth':
        // パスワードは空白も含めて、送られたとおりに使う。ID は「:」を含められない（Basic 認証の決まり）
        $authJobId = post_strvar('job_id');
        $user = post_strvar('user');
        $pass = post_textvar('pass', '');
        if ($authJobId === '' || $user === '' || strpos($user, ':') !== false) {
            exportSiteJson(['state' => 'error', 'message' => 'Bad Request'], 400);
        }
        sessionv('*export_basic_auth', ['job_id' => $authJobId, 'value' => "{$user}:{$pass}"]);
        session_write_close();
        $result = $job->status();
        break;
    case 'status':
        session_write_close();
        $result = $job->status();
        break;
    case 'cancel':
        sessionv('*export_basic_auth', null);
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

/**
 * そのジョブの Basic 認証の「ID:パスワード」（セッションにだけ持つ）。別のジョブのものは渡さない。
 */
function exportSiteCredentials($jobId)
{
    if ((string)$jobId === '' || sessionv('export_basic_auth.job_id') !== (string)$jobId) {
        return '';
    }
    return (string)sessionv('export_basic_auth.value', '');
}

/**
 * ジョブが終わったら、セッションから Basic 認証の情報を消す（session_write_close() のあとなので開き直す）。
 */
function exportSiteForgetCredentials()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    sessionv('*export_basic_auth', null);
    session_write_close();
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
