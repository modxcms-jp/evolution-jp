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
    private $skipExisting;
    private $bearerToken = null;
    private $prepared = false;
    private $curl = null;
    private $basicAuthDetected = false;
    private $outputDirError = null;

    /**
     * @param array $options target('all'|'allow'|'ignore'), ids(int[]), include_noncache(bool),
     *                       repl_before, repl_after, export_dir, skip_existing(bool)
     */
    public function __construct(array $options = [])
    {
        $target = isset($options['target']) ? $options['target'] : 'all';
        $this->target = in_array($target, ['allow', 'ignore'], true) ? $target : 'all';
        $this->ids = array_map('intval', isset($options['ids']) ? (array)$options['ids'] : []);
        $this->includeNoncache = !empty($options['include_noncache']);
        $this->replBefore = isset($options['repl_before']) ? (string)$options['repl_before'] : '';
        $this->replAfter = isset($options['repl_after']) ? (string)$options['repl_after'] : '';
        $this->skipExisting = !empty($options['skip_existing']);

        $dir = isset($options['export_dir']) && $options['export_dir'] !== ''
            ? $options['export_dir']
            : MODX_BASE_PATH . 'temp/export';
        $this->resolveOutputDir($dir);

        if (!evo()->config('site_status')) {
            $this->bearerToken = bin2hex(random_bytes(64));
        }
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
     * @return array id, pagetitle, status('success'|'skipped'|'failed'), reason
     */
    public function exportOne($id)
    {
        $id = (int)$id;
        if (!$this->isOutputDirValid()) {
            return ['id' => $id, 'pagetitle' => '', 'status' => 'failed', 'reason' => 'failed_no_write'];
        }
        $this->prepare();

        $rs = db()->select(
            "id, alias, pagetitle, isfolder, (content = '' AND template = 0) AS wasNull, published",
            '[+prefix+]site_content',
            sprintf('id=%d', $id)
        );
        $row = db()->getRow($rs);
        if (!$row) {
            return ['id' => $id, 'pagetitle' => '', 'status' => 'failed', 'reason' => 'failed_no_retrieve'];
        }

        $path = evo()->getAliasListing($row['id'], 'path');
        $baseDir = $path == '' ? $this->exportDir . '/' : $this->exportDir . '/' . $path . '/';
        $this->makeDir(rtrim($baseDir, '/'));

        $filename = null;
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
            if ($this->skipExisting && is_file($filename) && substr($filename, -10) !== 'index.html') {
                $reason = 'success_skip_doc';
            } elseif ($row['published'] != 1) {
                $reason = 'failed_no_retrieve';
            } else {
                $reason = $this->makeFile($row['id'], $filename);
            }
            if ($this->basicAuthDetected) {
                $reason = 'basic_auth';
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
        if ($this->bearerToken) {
            evo()->saveBearerToken($this->bearerToken, time() + 60 * 60 * 3);
        }
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
        $src = $this->getContents($url);

        if ($src === false || is_file(dirname($filepath))) {
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

    private function getContents($url, $timeout = 10)
    {
        if (!extension_loaded('curl')) {
            $headers = @get_headers($url);
            if (is_array($headers) && isset($headers[0]) && strpos($headers[0], '401') !== false) {
                $this->basicAuthDetected = true;
                return false;
            }
            return @file_get_contents($url);
        }

        if (!$this->curl) {
            $this->initCurl($timeout);
        }
        if (!$this->curl) {
            return false;
        }

        curl_setopt($this->curl, CURLOPT_URL, $url);
        $result = curl_exec($this->curl);

        // 失敗したときだけ最大 2 回やり直す（成功したら抜ける）
        for ($i = 0; $result === false && $i < 2; $i++) {
            usleep(300000);
            $result = curl_exec($this->curl);
        }

        if (curl_getinfo($this->curl, CURLINFO_HTTP_CODE) === 401) {
            $this->basicAuthDetected = true;
            return false;
        }

        return $result;
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
