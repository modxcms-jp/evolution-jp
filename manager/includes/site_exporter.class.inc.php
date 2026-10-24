<?php

/**
 * 静的 HTML エクスポートの中身。
 * 対象の一覧・出力先の準備・1 件の書き出しを受け持つ。
 * 画面の出力・セッション・exit は持たず、結果は配列で返す。
 */
class EvoSiteExporter
{
    private $target;
    private $ids;
    private $includeNoncache;
    private $replBefore;
    private $replAfter;
    private $exportDir;
    private $siteUrl;
    private $bearerToken = null;
    private $prepared = false;
    private $curl = null;
    private $basicAuthDetected = false;
    private $outputDirError = null;
    private $fetchDetail = '';

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
     * 1 件を書き出す。
     *
     * @return array id, pagetitle, status('success'|'skipped'|'failed'), reason,
     *               detail(失敗の詳しい内容。取得の失敗なら HTTP のステータスや通信のエラー。無ければ空)
     */
    public function exportOne($id)
    {
        $id = (int)$id;
        if (!$this->isOutputDirValid()) {
            return ['id' => $id, 'pagetitle' => '', 'status' => 'failed', 'reason' => 'failed_no_write', 'detail' => ''];
        }
        $this->prepare();

        $rs = db()->select(
            "id, alias, pagetitle, isfolder, (content = '' AND template = 0) AS wasNull, published",
            '[+prefix+]site_content',
            sprintf('id=%d', $id)
        );
        $row = db()->getRow($rs);
        if (!$row) {
            return ['id' => $id, 'pagetitle' => '', 'status' => 'failed', 'reason' => 'failed_no_retrieve', 'detail' => ''];
        }

        $path = evo()->getAliasListing($row['id'], 'path');
        $baseDir = $path == '' ? $this->exportDir . '/' : $this->exportDir . '/' . $path . '/';
        $this->makeDir(rtrim($baseDir, '/'));

        $filename = null;
        $this->fetchDetail = '';
        if ($row['wasNull']) {
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
            if ($row['published'] != 1) {
                $reason = 'failed_no_retrieve';
            } else {
                // basic 認証の検出はこの 1 件の取得ごとに判断する（持ち越さない）
                $this->basicAuthDetected = false;
                $reason = $this->makeFile($row['id'], $filename);
                if ($this->basicAuthDetected) {
                    $reason = 'basic_auth';
                }
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
            'detail'    => $reason === 'failed_no_open' ? $this->fetchDetail : '',
        ];
    }

    public function __destruct()
    {
        $this->curl = null;
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

    private function prepare()
    {
        if ($this->prepared) {
            return;
        }
        $this->prepared = true;
        $this->initCurl();
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

    private function makeFile($docid, $filepath)
    {
        $url = evo()->makeUrl($docid, '', '', 'full');
        // 取得の先だけを替える（CLI ではサイトの URL が http://localhost/ になるため）。書き出す中身は替えない
        $siteUrl = (string)evo()->config('site_url');
        if ($this->siteUrl !== '' && $siteUrl !== '' && strpos($url, $siteUrl) === 0) {
            $url = $this->siteUrl . substr($url, strlen($siteUrl));
        }
        $src = $this->getContents($url);

        if ($src === false || is_file(dirname($filepath))) {
            if ($src !== false) {
                $this->fetchDetail = 'output path is a file: ' . dirname($filepath);
            }
            return 'failed_no_open';
        }

        if ($this->replBefore !== $this->replAfter) {
            $src = str_replace($this->replBefore, $this->replAfter, $src);
        }

        if (file_put_contents($filepath, $src) === false) {
            return 'failed_no_write';
        }

        @chmod($filepath, $this->filePermission());
        return 'success';
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
     * 1 件を取得する。通信の失敗（接続できない・タイムアウト）だけ最大 2 回送り直す。
     * HTTP のステータスで答えが返った取得（401・404・500 など）は、送り直しても同じなので送り直さない。
     * 401 は basic 認証として印を立てる。
     */
    private function getContents($url, $timeout = 10)
    {
        if (extension_loaded('curl') && !$this->curl) {
            $this->initCurl($timeout);
        }

        for ($i = 0; $i < 3; $i++) {
            if ($i > 0) {
                usleep(300000);
            }
            list($body, $status, $error) = $this->fetch($url, $timeout);
            $this->fetchDetail = $status !== 0
                ? 'HTTP ' . $status
                : ($error !== '' ? $error : 'no response');
            if ($status === 401) {
                $this->basicAuthDetected = true;
                return false;
            }
            if ($status !== 0) {
                return $status >= 400 ? false : $body;
            }
            if ($body !== false) {
                return $body;
            }
        }
        return false;
    }

    /**
     * @return array [本文 または false, HTTP ステータス（応答が無ければ 0）, 通信のエラー（無ければ空）]
     */
    private function fetch($url, $timeout)
    {
        if (!extension_loaded('curl')) {
            $header = ['User-Agent: ' . (serverv('HTTP_USER_AGENT') ?: 'Evolution CMS JP export')];
            if ($this->bearerToken) {
                $header[] = 'Authorization: Bearer ' . $this->bearerToken;
            }
            $context = stream_context_create([
                'http' => [
                    'method'        => 'GET',
                    'header'        => implode("\r\n", $header),
                    'timeout'       => $timeout,
                    'max_redirects' => 3,
                    // 4xx・5xx でも応答を受け取り（false を返さず）、ステータス行を見て判断する
                    'ignore_errors' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            $status = 0;
            if (!empty($http_response_header) && is_array($http_response_header)) {
                // リダイレクトを辿ったときは最後のステータス行を使う
                foreach ($http_response_header as $line) {
                    if (preg_match('@^HTTP/\S+\s+(\d{3})@', $line, $m)) {
                        $status = (int)$m[1];
                    }
                }
            }
            $error = '';
            if ($body === false && $status === 0) {
                $last = error_get_last();
                $error = $last ? trim(preg_replace('@^file_get_contents\([^)]*\):\s*@', '', $last['message'])) : '';
            }
            return [$body, $status, $error];
        }

        if (!$this->curl) {
            return [false, 0, 'curl is not available'];
        }
        curl_setopt($this->curl, CURLOPT_URL, $url);
        $body = curl_exec($this->curl);
        $status = (int)curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        return [$body, $status, $body === false && $status === 0 ? curl_error($this->curl) : ''];
    }

    private function initCurl($timeout = 10)
    {
        if (!extension_loaded('curl')) {
            return false;
        }
        if ($this->curl) {
            return true;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        if (ini_get('open_basedir') == '') {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        if (defined('CURLOPT_AUTOREFERER')) {
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if (serverv('HTTP_USER_AGENT')) {
            curl_setopt($ch, CURLOPT_USERAGENT, serverv('HTTP_USER_AGENT'));
        }
        if ($this->bearerToken) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->bearerToken
            ]);
        }

        $this->curl = $ch;
        return true;
    }
}
