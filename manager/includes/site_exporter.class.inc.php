<?php

/**
 * 静的 HTML エクスポートの中身。
 * 対象の一覧・出力先の準備・ページの取得と書き出しを受け持つ。
 * 画面の出力・セッション・exit は持たず、結果は配列で返す。
 */
class EvoSiteExporter
{
    /** 同時に取得する件数。1 にすると 1 件ずつ順に取得する（サーバーへの負荷に関わるので、設定にはしない） */
    const CONCURRENCY = 4;
    /** 1 件の取得の制限時間（秒） */
    const FETCH_TIMEOUT = 10;
    /** 辿るリダイレクトの上限 */
    const MAX_REDIRECTS = 3;
    /** 同時に取得する 1 まとまり全体の締め切り（秒）。送り直しとリダイレクトを含め、これを超えて続けない */
    const CHUNK_DEADLINE = 10;

    /**
     * 実際に同時に取得する件数。curl が無いときは、まとまりの中を順に取るので 1 にして、締め切りを分け合わないようにする。
     */
    public static function concurrency()
    {
        return extension_loaded('curl') ? max(1, (int)self::CONCURRENCY) : 1;
    }

    private $target;
    private $ids;
    private $includeNoncache;
    private $replBefore;
    private $replAfter;
    private $exportDir;
    private $siteUrl;
    private $bearerToken = null;
    private $curlPool = [];
    private $outputDirError = null;

    /**
     * @param array $options target('all'|'allow'|'ignore'), ids(int[]), include_noncache(bool),
     *                       repl_before, repl_after, export_dir,
     *                       site_url(ページを取得する先のサイトの URL。省略時は設定のサイトの URL。書き出す中身は変えない),
     *                       bearer_token(サイトが非公開のときにページの取得へ付ける合言葉。発行と取り消しは呼び出し側)
     */
    public function __construct(array $options = [])
    {
        $target = isset($options['target']) ? $options['target'] : 'all';
        $this->target = in_array($target, ['allow', 'ignore'], true) ? $target : 'all';
        $this->ids = array_map('intval', isset($options['ids']) ? (array)$options['ids'] : []);
        $this->includeNoncache = !empty($options['include_noncache']);
        $this->replBefore = isset($options['repl_before']) ? (string)$options['repl_before'] : '';
        $this->replAfter = isset($options['repl_after']) ? (string)$options['repl_after'] : '';
        $this->siteUrl = !empty($options['site_url']) ? rtrim((string)$options['site_url'], '/') . '/' : '';
        $this->bearerToken = !empty($options['bearer_token']) ? (string)$options['bearer_token'] : null;

        $dir = isset($options['export_dir']) && $options['export_dir'] !== ''
            ? $options['export_dir']
            : MODX_BASE_PATH . 'temp/export';
        $this->resolveOutputDir($dir);
    }

    /**
     * 出力先が使えるか。
     */
    public function isOutputDirValid()
    {
        return $this->outputDirError === null;
    }

    /**
     * 出力先が使えない理由を言語キーで返す。使えるなら null。
     * 'export_site_target_unwritable' | 'export_site_target_outside_temp'
     */
    public function getOutputDirError()
    {
        return $this->outputDirError;
    }

    /**
     * 確認済みの出力先（MODX_BASE_PATH/temp/ からの相対で組み直したパス）。使えないときは null。
     */
    public function getOutputDir()
    {
        return $this->outputDirError === null ? $this->exportDir : null;
    }

    /**
     * 書き出し対象のリソース ID の一覧。件数もこれで数える。
     */
    public function targetIds()
    {
        $where = "deleted=0 AND ((published=1 AND type='document') OR (isfolder=1))";
        if (!$this->includeNoncache) {
            $where .= ' AND cacheable=1';
        }
        if ($this->target !== 'all') {
            $list = implode(',', array_map('intval', $this->ids ?: [0]));
            $where .= $this->target === 'allow'
                ? " AND id IN ({$list})"
                : " AND NOT id IN ({$list})";
        }

        $rs = db()->select('id', '[+prefix+]site_content', $where);
        $result = [];
        while ($row = db()->getRow($rs)) {
            $result[] = (int)$row['id'];
        }
        return $result;
    }

    /**
     * 出力先の中身を空にする。出力先自体は残す。
     */
    public function clearOutputDir()
    {
        if (!$this->isOutputDirValid()) {
            return false;
        }
        return $this->removeDirectoryAll($this->exportDir);
    }

    /**
     * 1 件を書き出す。exportMany([$id]) と同じ。
     *
     * @return array exportMany() の結果の 1 件分
     */
    public function exportOne($id)
    {
        $results = $this->exportMany([(int)$id]);
        return $results[0];
    }

    /**
     * 複数件を書き出す。取得は CONCURRENCY 件ずつ同時に行う（まとまりごと。1 なら 1 件ずつ順に取る）。
     * ファイルの作成・書き出しは、取得を終えたあとに ID の順に行う（1 件ずつ書き出すときと同じ並びで、フォルダ側の整理も同じ順で行う）。
     *
     * @param int[] $ids
     * @return array ID の順の結果の配列。各要素は id, pagetitle, status('success'|'skipped'|'failed'), reason,
     *               detail(失敗の詳しい内容。取得の失敗なら HTTP のステータスや通信のエラー。無ければ空)
     */
    public function exportMany(array $ids)
    {
        $results = [];
        foreach (array_chunk(array_values($ids), self::concurrency()) as $batch) {
            foreach ($this->exportBatch($batch) as $result) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * 同時に取得する 1 まとまりを書き出す。準備（行の取得・フォルダ作成）→ 取得 → 書き出しの順。
     */
    private function exportBatch(array $ids)
    {
        $items = [];
        $urls = [];
        foreach ($ids as $i => $id) {
            $items[$i] = $this->prepareItem((int)$id);
            if (isset($items[$i]['url'])) {
                $urls[$i] = $items[$i]['url'];
            }
        }

        $fetched = $urls ? $this->fetchAll($urls) : [];

        $results = [];
        foreach ($items as $i => $item) {
            $results[] = isset($item['result'])
                ? $item['result']
                : $this->finishItem($item, isset($fetched[$i]) ? $fetched[$i] : null);
        }
        return $results;
    }

    /**
     * 取得の前の準備。結果がここで決まるなら ['result' => …]、
     * そうでなければ書き出しに使う情報（取得が要るときは url も）を返す。
     */
    private function prepareItem($id)
    {
        if (!$this->isOutputDirValid()) {
            return ['result' => $this->failedResult($id, 'failed_no_write')];
        }

        $rs = db()->select(
            "id, alias, pagetitle, type, isfolder, content, (content = '' AND template = 0) AS wasNull, published",
            '[+prefix+]site_content',
            sprintf('id=%d', $id)
        );
        $row = db()->getRow($rs);
        if (!$row) {
            return ['result' => $this->failedResult($id, 'failed_no_retrieve')];
        }

        $path = evo()->getAliasListing($row['id'], 'path');
        $baseDir = $path == '' ? $this->exportDir . '/' : $this->exportDir . '/' . $path . '/';
        $this->makeDir(rtrim($baseDir, '/'));

        // Web リンクのフォルダは、リンク先がこのサイトのリソース ID（数字）のときだけ取得する。外部の URL などはフォルダだけを作る
        $isOuterLink = $row['type'] === 'reference' && $row['isfolder'] == 1
            && !ctype_digit(trim((string)$row['content']));
        $row['noPage'] = $row['wasNull'] || $isOuterLink;

        $item = ['row' => $row, 'baseDir' => $baseDir];
        if (!$row['noPage'] && $row['published'] == 1) {
            $item['url'] = $this->fetchUrl($row['id']);
        }
        return $item;
    }

    /**
     * 取得した中身の書き出しと、フォルダ側の整理。結果を返す。
     *
     * @param array|null $fetched fetchAll() の 1 件分（取得しなかったときは null）
     */
    private function finishItem(array $item, $fetched)
    {
        $row = $item['row'];
        $baseDir = $item['baseDir'];

        $filename = null;
        $detail = '';
        if ($row['noPage']) {
            $reason = 'success_skip_dir';
        } else {
            $docname = $this->getFileName(
                $row['id'],
                $row['alias'],
                evo()->config('friendly_url_prefix'),
                evo()->config('friendly_url_suffix')
            );
            $filename = $baseDir . $docname;
            if (is_dir($filename)) {
                $filename = rtrim($filename, '/') . '/index.html';
            }
            if ($fetched === null) {
                $reason = 'failed_no_retrieve';
            } elseif ($fetched['basic_auth']) {
                // basic 認証の検出はこの 1 件の取得ごとに判断する（持ち越さない）
                $reason = 'basic_auth';
            } else {
                list($reason, $detail) = $this->writeFile($fetched, $filename);
            }
        }

        $this->prepareFolder($row, $baseDir, $filename);

        if ($reason === 'success') {
            $status = 'success';
        } elseif (strpos($reason, 'success_skip') === 0) {
            $status = 'skipped';
        } else {
            $status = 'failed';
        }

        return [
            'id'        => (int)$row['id'],
            'pagetitle' => $row['pagetitle'],
            'status'    => $status,
            'reason'    => $reason,
            'detail'    => $reason === 'failed_no_open' ? $detail : '',
        ];
    }

    private function failedResult($id, $reason)
    {
        return ['id' => $id, 'pagetitle' => '', 'status' => 'failed', 'reason' => $reason, 'detail' => ''];
    }

    public function __destruct()
    {
        $this->curlPool = [];
    }

    /**
     * 出力先は temp/ の中（temp/ 自体は不可）に限る。開始時に中身を消すため、./ や ../ は解決してから比べる。
     */
    private function resolveOutputDir($dir)
    {
        $real = realpath(str_replace('\\', '/', $dir));
        if ($real === false) {
            $this->outputDirError = 'export_site_target_unwritable';
            return;
        }
        $real = str_replace('\\', '/', $real);
        $realTemp = realpath(MODX_BASE_PATH . 'temp');
        $realTemp = $realTemp === false ? false : str_replace('\\', '/', $realTemp);
        if ($realTemp === false || strpos($real, $realTemp . '/') !== 0) {
            $this->outputDirError = 'export_site_target_outside_temp';
            return;
        }
        // 以降の処理は MODX_BASE_PATH で始まるパスを前提にするため、temp/ からの相対で組み直す
        $this->exportDir = rtrim(MODX_BASE_PATH . 'temp' . substr($real, strlen($realTemp)), '/');
        if (!is_dir($this->exportDir) || !is_writable($this->exportDir)) {
            $this->outputDirError = 'export_site_target_unwritable';
        }
    }


    private function filePermission()
    {
        return octdec((string)evo()->config('new_file_permissions')) ?: 0644;
    }

    private function folderPermission()
    {
        return octdec((string)evo()->config('new_folder_permissions')) ?: 0755;
    }

    private function makeDir($dir)
    {
        if (file_exists($dir)) {
            return;
        }
        // umask に削られず、設定した権限どおりに作る
        $mask = umask(0);
        mkdir($dir, $this->folderPermission(), true);
        umask($mask);
    }

    private function removeDirectoryAll($directory)
    {
        $directory = rtrim($directory, '/');
        if (empty($directory)) {
            return false;
        }
        if (strpos($directory, MODX_BASE_PATH) !== 0) {
            return false;
        }
        if (!is_dir($directory) || !is_readable($directory)) {
            return false;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return false;
        }

        $rs = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $rs = $this->removeDirectoryAll($path) && $rs;
            } else {
                $rs = unlink($path) && $rs;
            }
        }

        if ($directory !== $this->exportDir && $rs) {
            $rs = rmdir($directory);
        }

        return $rs;
    }


    /**
     * 取得する URL。取得の先だけを替える（CLI ではサイトの URL が http://localhost/ になるため）。書き出す中身は替えない
     */
    private function fetchUrl($docid)
    {
        $url = evo()->makeUrl($docid, '', '', 'full');
        if (!$url) {
            return '';
        }
        $siteUrl = (string)evo()->config('site_url');
        if ($this->siteUrl !== '' && $siteUrl !== '' && strpos($url, $siteUrl) === 0) {
            $url = $this->siteUrl . substr($url, strlen($siteUrl));
        }
        return $url;
    }

    /**
     * 取得した中身を置換して書き出す。
     *
     * @return array [reason, detail]
     */
    private function writeFile(array $fetched, $filepath)
    {
        $src = $fetched['body'];

        if ($src === false) {
            return ['failed_no_open', $fetched['detail']];
        }
        if (is_file(dirname($filepath))) {
            return ['failed_no_open', 'output path is a file: ' . dirname($filepath)];
        }

        if ($this->replBefore !== $this->replAfter) {
            $src = str_replace($this->replBefore, $this->replAfter, $src);
        }

        if (file_put_contents($filepath, $src) === false) {
            return ['failed_no_write', ''];
        }

        @chmod($filepath, $this->filePermission());
        return ['success', ''];
    }


    private function getFileName($docid, $alias, $prefix, $suffix)
    {
        if ($alias === '') {
            return $prefix . $docid . $suffix;
        }
        if (evo()->config('suffix_mode') === '1' && strpos($alias, '.') !== false) {
            $suffix = '';
        }
        return $prefix . $alias . $suffix;
    }

    private function prepareFolder($row, $baseDir, $filename)
    {
        if ($row['isfolder'] != 1) {
            return;
        }
        if (evo()->config('suffix_mode') == 1 && strpos($row['alias'], '.') !== false) {
            return;
        }

        $endDir = ($row['alias'] !== '') ? $row['alias'] : $row['id'];
        $folderPath = $baseDir . $endDir;
        if (strpos($folderPath, MODX_BASE_PATH) !== 0) {
            return;
        }

        if (!is_dir($folderPath)) {
            if (is_file($folderPath)) {
                @unlink($folderPath);
            }
            $mask = umask(0);
            mkdir($folderPath, $this->folderPermission());
            umask($mask);
        }

        if (evo()->config('make_folders') != 1 || $row['published'] != 1) {
            return;
        }

        if ($filename && is_file($filename)) {
            rename($filename, $folderPath . '/index.html');
        }
    }


    /**
     * 複数の URL を同時に取得する。通信の失敗（接続できない・タイムアウト・本文の途中の切断）だけ最大 2 回送り直す。
     * HTTP のステータスで答えが返った取得（401・404・500 など）は、送り直しても同じなので送り直さない。
     * 401 は basic 認証として印を立てる（結果ごと）。
     * 3xx は curl の経路でもストリームの経路でも自分で Location を辿る（最大 MAX_REDIRECTS 回、最初の URL と同じオリジンの中だけ）。
     * 同じホスト・既定のポートでの http から https への切り替えも辿る。
     * 別のオリジンへのリダイレクトや辿れない 3xx は失敗にする。最初の URL がこのサイト（取得先）の http・https でなければ取得しない。Authorization を付けるのは同じオリジンへの要求だけになる。
     * このまとまり全体に CHUNK_DEADLINE 秒の締め切りがあり、過ぎたら送り直しとリダイレクトの追跡を打ち切って失敗にする。
     *
     * @param array $urls キー => URL
     * @return array キーごとの [body(本文 または false), detail(HTTP のステータスや通信のエラー), basic_auth(bool)]
     */
    private function fetchAll(array $urls)
    {
        $deadline = microtime(true) + self::CHUNK_DEADLINE;
        $siteOrigin = $this->originOf($this->siteUrl !== '' ? $this->siteUrl : (string)evo()->config('site_url'));
        $state = [];
        $out = [];
        foreach ($urls as $key => $url) {
            if (!$url) {
                $out[$key] = $this->failure('not fetched: no URL for the resource');
                continue;
            }
            // 取得先はそのサイトの http・https に限る（最初の URL がサイトの外なら取得しない）
            $origin = $this->originOf($url);
            if ($origin === null || $siteOrigin === null || $origin !== $siteOrigin) {
                $out[$key] = $this->failure('not fetched: the URL is outside the site (' . $this->shortUrl($url) . ')');
                continue;
            }
            $state[$key] = ['url' => $url, 'origin' => $origin, 'hops' => 0, 'tries' => 0];
        }

        while ($state) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                foreach ($state as $key => $s) {
                    $out[$key] = $this->failure('aborted: time limit for the batch exceeded');
                }
                break;
            }

            $requests = [];
            foreach ($state as $key => $s) {
                $requests[$key] = $s['url'];
            }
            $raw = $this->requestAll($requests, $deadline);

            $next = [];
            $retry = false;
            foreach ($state as $key => $s) {
                list($body, $status, $error, $redirect, $errno) = $raw[$key];
                $detail = 'HTTP ' . $status;

                if ($status === 401) {
                    $out[$key] = ['body' => false, 'detail' => $detail, 'basic_auth' => true];
                } elseif ($status >= 300 && $status < 400) {
                    if ($redirect === '') {
                        $out[$key] = $this->failure($detail);
                    } elseif (($to = $this->originOf($redirect)) === null
                        || ($to !== $s['origin'] && !$this->isHttpsUpgrade($s['origin'], $to))) {
                        $out[$key] = $this->failure(
                            $detail . ' → another site (set the site URL to match the redirect target)'
                        );
                    } elseif ($s['hops'] >= self::MAX_REDIRECTS) {
                        $out[$key] = $this->failure($detail . ' → too many redirects');
                    } else {
                        $s['url'] = $redirect;
                        $s['origin'] = $to;
                        $s['hops']++;
                        $next[$key] = $s;
                    }
                } elseif ($errno !== 0 && $status < 400) {
                    // 応答が無い、または本文の途中で切れた
                    if (++$s['tries'] < 3) {
                        $next[$key] = $s;
                        $retry = true;
                    } else {
                        $out[$key] = $this->failure($error !== '' ? $error : 'no response');
                    }
                } else {
                    $out[$key] = [
                        'body'       => $status >= 400 ? false : $body,
                        'detail'     => $detail,
                        'basic_auth' => false,
                    ];
                }
            }
            $state = $next;
            if ($state && $retry) {
                usleep(300000);
            }
        }
        return $out;
    }

    private function shortUrl($url)
    {
        $url = (string)$url;
        return strlen($url) > 100 ? substr($url, 0, 100) . '...' : $url;
    }

    /**
     * 同じホスト・既定のポートでの http から https への切り替えか。
     */
    private function isHttpsUpgrade($from, $to)
    {
        return preg_match('@^http://(.+):80$@', (string)$from, $m) === 1 && $to === 'https://' . $m[1] . ':443';
    }

    private function failure($detail)
    {
        return ['body' => false, 'detail' => $detail, 'basic_auth' => false];
    }

    /**
     * URL のオリジン（scheme・host・port）。http・https 以外や解釈できない URL は null。
     */
    private function originOf($url)
    {
        $p = parse_url((string)$url);
        if (!$p || empty($p['host']) || empty($p['scheme'])) {
            return null;
        }
        $scheme = strtolower($p['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        $port = isset($p['port']) ? (int)$p['port'] : ($scheme === 'https' ? 443 : 80);
        return $scheme . '://' . strtolower($p['host']) . ':' . $port;
    }

    /**
     * Location の値を、リダイレクト元の URL に対する絶対 URL にする。
     */
    private function resolveLocation($base, $location)
    {
        $location = trim((string)$location);
        if ($location === '') {
            return '';
        }
        $b = parse_url($base);
        if (!$b || empty($b['scheme']) || empty($b['host'])) {
            return '';
        }
        if (preg_match('@^[a-zA-Z][a-zA-Z0-9+.-]*:@', $location)) {
            return $location;
        }
        // フラグメントは取得に関係ないので捨てる
        $location = preg_replace('@#.*$@', '', $location);
        if (strpos($location, '//') === 0) {
            return $b['scheme'] . ':' . $location;
        }
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        $path = isset($b['path']) ? $b['path'] : '/';
        if ($location === '') {
            return $origin . $path . (isset($b['query']) ? '?' . $b['query'] : '');
        }
        if ($location[0] === '?') {
            return $origin . $path . $location;
        }
        if ($location[0] !== '/') {
            $location = substr($path, 0, strrpos($path, '/') + 1) . $location;
        }
        $query = '';
        $pos = strpos($location, '?');
        if ($pos !== false) {
            $query = substr($location, $pos);
            $location = substr($location, 0, $pos);
        }
        return $origin . $this->normalizePath($location) . $query;
    }

    /**
     * パスの . と .. を整理する（ルートより上には行かない）。
     */
    private function normalizePath($path)
    {
        $out = [];
        $segments = explode('/', $path);
        $last = count($segments) - 1;
        foreach ($segments as $i => $seg) {
            if ($seg === '..') {
                array_pop($out);
                if ($i === $last) {
                    $out[] = '';
                }
            } elseif ($seg === '.') {
                if ($i === $last) {
                    $out[] = '';
                }
            } elseif ($seg !== '' || $i === $last) {
                $out[] = $seg;
            }
        }
        return '/' . implode('/', $out);
    }

    /**
     * 1 回ぶんの取得（送り直しも Location を辿ることもしない）。
     *
     * @param array $urls キー => URL（CONCURRENCY 件まで）
     * @param float $deadline まとまりの締め切り（microtime(true) の値）。1 件の制限時間は FETCH_TIMEOUT と締め切りまでの残りの小さいほう
     * @return array キーごとの [本文 または false, HTTP ステータス（応答が無ければ 0）, 通信のエラー（無ければ空）, 辿るべき Location（絶対 URL。無ければ空）, 通信の結果（0 なら正常）]
     */
    private function requestAll(array $urls, $deadline)
    {
        if (!extension_loaded('curl')) {
            $out = [];
            foreach ($urls as $key => $url) {
                $out[$key] = $this->requestByStream($url, $deadline);
            }
            return $out;
        }

        $mh = curl_multi_init();
        $handles = [];
        $i = 0;
        foreach ($urls as $key => $url) {
            $ch = $this->curlHandle($i++);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, max(1, (int)($this->timeoutUntil($deadline) * 1000)));
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $running = 0;
        do {
            $rc = curl_multi_exec($mh, $running);
        } while ($rc === CURLM_CALL_MULTI_PERFORM);
        while ($running && $rc === CURLM_OK) {
            if (curl_multi_select($mh, 1.0) === -1) {
                usleep(1000);
            }
            do {
                $rc = curl_multi_exec($mh, $running);
            } while ($rc === CURLM_CALL_MULTI_PERFORM);
        }

        $errnos = [];
        while ($msg = curl_multi_info_read($mh)) {
            foreach ($handles as $key => $ch) {
                if ($ch === $msg['handle']) {
                    $errnos[$key] = (int)$msg['result'];
                }
            }
        }

        $out = [];
        foreach ($handles as $key => $ch) {
            $errno = isset($errnos[$key]) ? $errnos[$key] : -1;
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = $errno === 0 ? (string)curl_multi_getcontent($ch) : false;
            $error = '';
            if ($errno !== 0 && $status < 400) {
                $error = curl_error($ch) ?: ($errno > 0 ? (string)curl_strerror($errno) : '');
            }
            $redirect = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $out[$key] = [$body, $status, $error, $redirect, $errno];
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    private function timeoutUntil($deadline)
    {
        return min((float)self::FETCH_TIMEOUT, $deadline - microtime(true));
    }

    /**
     * @return array requestAll() の 1 件分
     */
    private function requestByStream($url, $deadline)
    {
        $timeout = $this->timeoutUntil($deadline);
        if ($timeout <= 0) {
            return [false, 0, 'aborted: time limit for the batch exceeded', '', -1];
        }
        if ($this->originOf($url) === null) {
            return [false, 0, 'not fetched: only http and https are allowed', '', -1];
        }
        $header = ['User-Agent: ' . (serverv('HTTP_USER_AGENT') ?: 'Evolution CMS JP export')];
        if ($this->bearerToken) {
            $header[] = 'Authorization: Bearer ' . $this->bearerToken;
        }
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", $header),
                'timeout'         => $timeout,
                // リダイレクトは fetchAll() が同じオリジンの中だけ辿る
                'follow_location' => 0,
                // 4xx・5xx でも応答を受け取り（false を返さず）、ステータス行を見て判断する
                'ignore_errors'   => true,
            ],
        ]);
        error_clear_last();
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        $location = '';
        $length = null;
        if (!empty($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('@^HTTP/\S+\s+(\d{3})@', $line, $m)) {
                    $status = (int)$m[1];
                } elseif (preg_match('@^Location:\s*(.+)$@i', $line, $m)) {
                    $location = $m[1];
                } elseif (preg_match('@^Content-Length:\s*(\d+)\s*$@i', $line, $m)) {
                    $length = (int)$m[1];
                }
            }
        }
        $error = '';
        if ($body === false && $status < 400) {
            $last = error_get_last();
            $error = $last ? trim(preg_replace('@^file_get_contents\([^)]*\):\s*@', '', $last['message'])) : '';
        } elseif ($body !== false && $status < 400 && $length !== null && strlen($body) < $length) {
            // 本文の途中で切れた（PHP は途中までを返す）
            $error = 'transfer closed with ' . ($length - strlen($body)) . ' bytes remaining to read';
            $body = false;
        }
        return [
            $body,
            $status,
            $error,
            $status >= 300 && $status < 400 ? $this->resolveLocation($url, $location) : '',
            $body === false && $status < 400 ? -1 : 0,
        ];
    }

    /**
     * 取得に使う curl のハンドル（番号ごとに使い回す）。
     */
    private function curlHandle($index)
    {
        if (isset($this->curlPool[$index])) {
            return $this->curlPool[$index];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        // リダイレクトは curl に辿らせず、fetchAll() が同じオリジンの中だけ辿る（別のオリジンへ Authorization を送らない）
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        // 取得の経路も http・https に限る
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS_STR, 'http,https');
        } else {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        if (serverv('HTTP_USER_AGENT')) {
            curl_setopt($ch, CURLOPT_USERAGENT, serverv('HTTP_USER_AGENT'));
        }
        if ($this->bearerToken) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->bearerToken
            ]);
        }

        $this->curlPool[$index] = $ch;
        return $ch;
    }
}
