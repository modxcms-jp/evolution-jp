<?php

require_once __DIR__ . '/site_exporter.class.inc.php';

/**
 * HTML エクスポートのジョブ。
 *
 * 開始（start）で対象の ID 一覧と設定を状態ファイルに保存し、続きを進める（step）を
 * 決めた秒数ぶんだけ繰り返し呼んで最後まで進める。1 回のリクエストが短いので、
 * PHP の制限時間やプロキシのタイムアウトに掛からない。
 *
 * 状態ファイル: temp/export_job.json（書き換えは一時ファイルからの rename で、読む側は常に完全な内容を見る）
 * 排他:         temp/export_job.lock を flock する。step・cancel は取れなければ待たずに busy を返す。start だけは処理の切れ目まで少し待つ（startWaitSeconds）
 * 中止の印:     temp/export_job.cancel。中止するジョブの id（状態の job_id）を書く。ロックを取らずに書ける。
 *               step() は 1 ページごとに、自分のジョブの id の印だけを見る（別のタブが始めた新しいジョブは止まらない）
 *
 * 画面・セッション・exit は持たない。結果は配列で返す（processor が JSON にする）。
 */
class EvoSiteExportJob
{
    const STATE_RUNNING = 'running';
    const STATE_DONE = 'done';
    const STATE_CANCELLED = 'cancelled';
    const STATE_ERROR = 'error';
    const STATE_BUSY = 'busy';
    const STATE_NONE = 'none';

    /** 返す失敗の一覧の上限（状態ファイルにもこれだけ持つ。件数は別に数える） */
    const MAX_FAILURES = 200;
    /** ベアラートークンの期限（秒）。続きを進めるたびに発行し直す */
    const TOKEN_TTL = 600;
    /** 1 回の続きを進めるの持ち時間の上限（秒） */
    const MAX_BUDGET = 10;

    private $stateFile;
    private $lockFile;
    private $cancelFile;
    private $lockHandle = null;

    public function __construct()
    {
        $dir = MODX_BASE_PATH . 'temp/';
        $this->stateFile = $dir . 'export_job.json';
        $this->lockFile = $dir . 'export_job.lock';
        $this->cancelFile = $dir . 'export_job.cancel';
    }

    /**
     * 続きを進める 1 回の持ち時間（秒）。10 秒と PHP の制限時間の 3 分の 1 の小さいほう。制限時間が 0 なら 10 秒。
     */
    public static function defaultBudget()
    {
        $max = (int)ini_get('max_execution_time');
        if ($max <= 0) {
            return (float)self::MAX_BUDGET;
        }
        return min((float)self::MAX_BUDGET, $max / 3);
    }

    /**
     * start() がロックを待つ上限（秒）。相手の step() の持ち時間より少し長い時間（持ち時間 + 3 秒）。
     * 待ったあとに出力先を空にする時間も PHP の制限時間に収まるよう、制限時間の半分を超えない。制限時間が 0 なら持ち時間 + 3 秒。
     */
    public static function startWaitSeconds()
    {
        $wait = self::defaultBudget() + 3;
        $max = (int)ini_get('max_execution_time');
        return $max > 0 ? min($wait, $max / 2) : $wait;
    }

    /**
     * ジョブを始める。途中のジョブがあれば捨てて、出力先を空にして最初から始める。
     *
     * @param array $options target('all'|'allow'|'ignore'), ids(int[]), include_noncache(bool),
     *                       repl_before, repl_after
     * @return array 応答（state: running | busy | error）
     */
    public function start(array $options)
    {
        if (!evo()->config('friendly_urls')) {
            return $this->errorResponse(lang('export_site_friendly_urls_disabled'));
        }
        $denied = $this->acquireLock(self::startWaitSeconds());
        if ($denied) {
            return $denied;
        }
        try {
            $exporter = new EvoSiteExporter($this->exporterOptions($options, null, $this->configuredExportDir()));
            if (!$exporter->isOutputDirValid()) {
                return $this->errorResponse(lang($exporter->getOutputDirError()));
            }

            $target = isset($options['target']) ? $options['target'] : 'all';
            $target = in_array($target, ['allow', 'ignore'], true) ? $target : 'all';
            $ids = array_map('intval', isset($options['ids']) ? (array)$options['ids'] : []);
            $options = [
                'target'           => $target,
                'ids'              => $ids,
                'include_noncache' => !empty($options['include_noncache']),
                'repl_before'      => isset($options['repl_before']) ? (string)$options['repl_before'] : '',
                'repl_after'       => isset($options['repl_after']) ? (string)$options['repl_after'] : '',
            ];

            $began = microtime(true);
            $exportDir = $exporter->getOutputDir();

            // 出力先を空にする前に、前のジョブを無効にする（空にできなくても、欠けたまま再開されない）
            if (!$this->discardPreviousJob()) {
                return $this->errorResponse(lang('export_site_state_unwritable'));
            }
            // 出力先を空にする前に、状態を書けることを確かめる。書けないまま空にしない。
            // 空にできなかったときはこの error の状態が残り、画面は export_site_clear_failed を案内する
            $failed = lang('export_site_clear_failed');
            $pending = [
                'state' => self::STATE_ERROR,
                'job_id' => '',
                'export_dir' => $exportDir,
                'options' => $options,
                'ids' => [],
                'pos' => 0,
                'success' => 0,
                'skipped' => 0,
                'failed' => 0,
                'failures' => [],
                'messages' => [],
                'elapsed' => 0,
                'error' => $failed,
                'output' => '',
            ];
            if (!$this->writeState($pending)) {
                return $this->errorResponse(lang('export_site_state_unwritable'));
            }
            if (!$exporter->clearOutputDir()) {
                return $this->errorResponse($failed);
            }
            // 出力先を空にできた。ここから先で止まったときの文言に替える（保存できなくても、最後の running の保存で判断する）
            $pending['error'] = lang('export_site_start_interrupted');
            $this->writeState($pending);
            $messages = $this->invokeEvent('OnExportPreExec', $options, $exportDir);

            $state = [
                'state' => self::STATE_RUNNING,
                'job_id' => bin2hex(random_bytes(8)),
                'export_dir' => $exportDir,
                'options' => $options,
                'ids' => $exporter->targetIds(),
                'pos' => 0,
                'success' => 0,
                'skipped' => 0,
                'failed' => 0,
                'failures' => [],
                'messages' => $messages,
                'elapsed' => microtime(true) - $began,
                'error' => '',
                'output' => '',
            ];
            if (!$this->writeState($state)) {
                return $this->errorResponse(lang('export_site_state_unwritable'));
            }
            return $this->toResponse($state);
        } finally {
            $this->unlock();
        }
    }

    /**
     * 持ち時間ぶん書き出して進める。持ち時間を過ぎたら、処理中の 1 件を終えて返す。
     *
     * @param float|null $budgetSeconds 持ち時間（秒）。null か 0 以下なら終わるまで続ける（CLI 用）
     * @param string $siteUrl ページを取得する先のサイトの URL。空なら設定のサイトの URL。
     *                        状態には保存しない（実行する側が、そのつど渡す）
     * @return array 応答（state: running | done | cancelled | error | busy | none）
     */
    public function step($budgetSeconds = null, $siteUrl = '')
    {
        $denied = $this->acquireLock();
        if ($denied) {
            return $denied;
        }
        try {
            $state = $this->readState();
            if (!$state) {
                return ['state' => self::STATE_NONE];
            }
            if ($state['state'] !== self::STATE_RUNNING) {
                return $this->toResponse($state);
            }

            $began = microtime(true);
            if (!evo()->config('friendly_urls')) {
                return $this->finish($state, self::STATE_ERROR, $began, lang('export_site_friendly_urls_disabled'));
            }
            // 出力先は開始時に確かめたものを使う（設定が途中で変わっても、別の場所へ書き出さない）
            $exporter = new EvoSiteExporter($this->exporterOptions(
                $state['options'],
                $this->issueToken(),
                isset($state['export_dir']) ? $state['export_dir'] : '',
                $siteUrl
            ));
            if (!$exporter->isOutputDirValid()) {
                return $this->finish($state, self::STATE_ERROR, $began, lang($exporter->getOutputDirError()));
            }

            $total = count($state['ids']);
            $startPos = $state['pos'];
            while ($state['pos'] < $total) {
                if ($this->hasCancelMark($state['job_id'])) {
                    return $this->finish($state, self::STATE_CANCELLED, $began);
                }
                // 少なくとも 1 件は進める。持ち時間を過ぎていたら、次の 1 件には掛からず返す
                if ($budgetSeconds > 0 && $state['pos'] > $startPos
                    && microtime(true) - $began >= $budgetSeconds) {
                    break;
                }

                $result = $exporter->exportOne($state['ids'][$state['pos']]);
                $state['pos']++;

                if ($result['reason'] === 'basic_auth') {
                    $this->addFailure($state, $result);
                    return $this->finish($state, self::STATE_ERROR, $began, lang('export_site_basic_auth_warning'));
                }
                if ($result['status'] === 'success') {
                    $state['success']++;
                } elseif ($result['status'] === 'skipped') {
                    $state['skipped']++;
                } else {
                    $this->addFailure($state, $result);
                }
            }

            if ($state['pos'] >= $total) {
                return $this->finish($state, self::STATE_DONE, $began);
            }
            $state['elapsed'] += microtime(true) - $began;
            if (!$this->writeState($state)) {
                return $this->stateUnwritable();
            }
            return $this->toResponse($state);
        } finally {
            $this->unlock();
        }
    }

    /**
     * 保存した状態を返す。ジョブが無ければ state が none。ロックは取らない（処理中でも進み具合が読める）。
     */
    public function status()
    {
        $state = $this->readState();
        return $state ? $this->toResponse($state) : ['state' => self::STATE_NONE];
    }

    /**
     * 中止する。中止の印を書き、処理中の step() があればそれが次のページの前に止まる。
     * 処理中の step() が無ければ、その場で cancelled にしてトークンを取り消す。
     * 中止したジョブは再開できない（書き出し済みのファイルは消さない）。
     */
    public function cancel()
    {
        $state = $this->readState();
        if (!$state || $state['state'] !== self::STATE_RUNNING) {
            return $state ? $this->toResponse($state) : ['state' => self::STATE_NONE];
        }

        // ロックを取らずに書ける。処理中の step() はこれを見て止まる。
        // 印には止めたいジョブの id を書く。別のタブが新しいジョブを始めていても、新しいほうは止まらない
        $jobId = (string)$state['job_id'];
        @file_put_contents($this->cancelFile, $jobId);

        $denied = $this->acquireLock();
        if ($denied) {
            // 処理中の step() が止まって後始末をする。その応答が cancelled になる
            return $denied['state'] === self::STATE_BUSY ? $this->toResponse($state) : $denied;
        }
        try {
            $state = $this->readState();
            if ($state && $state['state'] === self::STATE_RUNNING && (string)$state['job_id'] === $jobId) {
                return $this->finish($state, self::STATE_CANCELLED, microtime(true));
            }
            return $state ? $this->toResponse($state) : ['state' => self::STATE_NONE];
        } finally {
            $this->unlock();
        }
    }

    // ------------------------------------------------------------------

    /**
     * ジョブを終える（done / cancelled / error）。トークンを取り消し、状態を保存する。
     */
    private function finish(array $state, $finalState, $began, $error = '')
    {
        $state['elapsed'] += microtime(true) - $began;
        $state['state'] = $finalState;
        $state['error'] = $error;
        $this->removeCancelMark();
        evo()->revokeBearerToken();

        if ($finalState === self::STATE_DONE) {
            $state['output'] = sprintf(lang('export_site_time'), round($state['elapsed'], 3));
        }
        // OnExportExec を呼ぶ前に、状態を保存できることを確かめる
        if (!$this->writeState($state)) {
            return $this->stateUnwritable();
        }
        if ($finalState === self::STATE_DONE) {
            $info = $this->eventInfo($state['options'], isset($state['export_dir']) ? $state['export_dir'] : '');
            $info['output'] = $state['output'];
            $info['totaltime'] = $state['elapsed'];
            $state['messages'] = array_merge($state['messages'], $this->collectEvent('OnExportExec', $info));
            // イベントの出力は応答に含める。ここで保存に失敗しても、ジョブは終わっている
            $this->writeState($state);
        }
        return $this->toResponse($state);
    }

    private function issueToken()
    {
        if (evo()->config('site_status')) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        evo()->saveBearerToken($token, time() + self::TOKEN_TTL);
        return $token;
    }

    private function configuredExportDir()
    {
        return str_replace('[(base_path)]', MODX_BASE_PATH, (string)config('export_dir'));
    }

    private function exporterOptions(array $options, $token, $exportDir, $siteUrl = '')
    {
        return [
            'target'           => isset($options['target']) ? $options['target'] : 'all',
            'ids'              => isset($options['ids']) ? $options['ids'] : [],
            'include_noncache' => !empty($options['include_noncache']),
            'repl_before'      => isset($options['repl_before']) ? $options['repl_before'] : '',
            'repl_after'       => isset($options['repl_after']) ? $options['repl_after'] : '',
            'site_url'         => (string)$siteUrl,
            'export_dir'       => $exportDir,
            'bearer_token'     => $token,
        ];
    }

    /**
     * プラグインに渡す値。名前は従来のまま（allow_ids・ignore_ids・repl_before・repl_after・export_dir）。
     */
    private function eventInfo(array $options, $exportDir)
    {
        $ids = implode(',', array_map('intval', $options['ids']));
        return [
            'allow_ids'   => $options['target'] === 'allow' ? $ids : '',
            'ignore_ids'  => $options['target'] === 'ignore' ? $ids : '',
            'repl_after'  => $options['repl_after'],
            'repl_before' => $options['repl_before'],
            'export_dir'  => $exportDir,
        ];
    }

    private function invokeEvent($name, array $options, $exportDir)
    {
        return $this->collectEvent($name, $this->eventInfo($options, $exportDir));
    }

    /**
     * イベントの出力（プラグインの作者が書く HTML）を配列で返す。
     */
    private function collectEvent($name, array $info)
    {
        $out = evo()->invokeEvent($name, $info);
        if (!is_array($out)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $out), function ($v) {
            return $v !== '';
        }));
    }

    private function addFailure(array &$state, array $result)
    {
        $state['failed']++;
        if (count($state['failures']) < self::MAX_FAILURES) {
            $state['failures'][] = [
                'id'        => (int)$result['id'],
                'pagetitle' => (string)$result['pagetitle'],
                'reason'    => (string)$result['reason'],
                'detail'    => isset($result['detail']) ? (string)$result['detail'] : '',
            ];
        }
    }

    private function toResponse(array $state)
    {
        $failures = [];
        foreach ($state['failures'] as $f) {
            $failures[] = [
                'id'        => $f['id'],
                'pagetitle' => $f['pagetitle'],
                'reason'    => $this->reasonText($f['reason'], isset($f['detail']) ? $f['detail'] : ''),
            ];
        }
        return [
            'state'    => $state['state'],
            'job_id'   => (string)$state['job_id'],
            'total'    => count($state['ids']),
            'done'     => $state['pos'],
            'success'  => $state['success'],
            'skipped'  => $state['skipped'],
            'failed'   => $state['failed'],
            'failures' => $failures,
            'messages' => $state['messages'],
            'elapsed'  => round($state['elapsed'], 3),
            'output'   => $state['output'],
            'message'  => $state['error'],
        ];
    }

    private function reasonText($reason, $detail = '')
    {
        $keys = [
            'failed_no_write'    => 'export_site_failed_no_write',
            'failed_no_retrieve' => 'export_site_failed_no_retrieve',
            'failed_no_open'     => 'export_site_failed_no_open',
            'basic_auth'         => 'export_site_basic_auth_failed',
        ];
        $text = isset($keys[$reason]) ? lang($keys[$reason]) : (string)$reason;
        return $detail !== '' ? "{$text} ({$detail})" : $text;
    }

    private function errorResponse($message)
    {
        return ['state' => self::STATE_ERROR, 'message' => $message];
    }

    /**
     * 状態を保存できなかった。トークンを取り消して error を返す（続きは進めない）。
     */
    private function stateUnwritable()
    {
        evo()->revokeBearerToken();
        return $this->errorResponse(lang('export_site_state_unwritable'));
    }

    /**
     * 前のジョブを無効にする。状態を消し、中止の印とトークンも片付ける。
     *
     * @return bool 消せなかったら false
     */
    private function discardPreviousJob()
    {
        evo()->revokeBearerToken();
        $this->removeCancelMark();
        return !is_file($this->stateFile) || @unlink($this->stateFile);
    }

    // ----- 状態ファイル・ロック・中止の印 -----

    /**
     * ロックを取る。取れたら null。取れなければ返す応答を返す
     * （ほかが処理中なら busy、ロックファイルを開けない＝ temp/ に書けないなら error）。
     *
     * @param float $waitSeconds 取れないときに、0.2 秒おきに試し直して待つ秒数（0 なら待たない）
     */
    private function acquireLock($waitSeconds = 0)
    {
        $handle = @fopen($this->lockFile, 'c');
        if (!$handle) {
            return $this->errorResponse(lang('export_site_state_unwritable'));
        }
        $until = microtime(true) + $waitSeconds;
        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $until) {
                fclose($handle);
                return ['state' => self::STATE_BUSY];
            }
            usleep(200000);
        }
        $this->lockHandle = $handle;
        return null;
    }

    private function unlock()
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    private function readState()
    {
        if (!is_file($this->stateFile)) {
            return null;
        }
        $json = @file_get_contents($this->stateFile);
        $state = $json === false ? null : json_decode($json, true);
        if (!is_array($state) || !isset($state['state'], $state['ids'], $state['options'])) {
            return null;
        }
        return $state;
    }

    private function writeState(array $state)
    {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }
        $tmp = $this->stateFile . '.tmp';
        if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $this->stateFile)) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
            return false;
        }
        return true;
    }

    private function hasCancelMark($jobId)
    {
        clearstatcache(true, $this->cancelFile);
        if (!is_file($this->cancelFile)) {
            return false;
        }
        $id = @file_get_contents($this->cancelFile);
        return $id !== false && trim($id) === (string)$jobId;
    }

    private function removeCancelMark()
    {
        if (is_file($this->cancelFile)) {
            @unlink($this->cancelFile);
        }
    }
}
