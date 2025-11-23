<?php

class EXPORT_SITE
{
    public $total;
    public $exportstart;
    public $repl_before;
    public $repl_after;
    public $maxtime;
    private $targetDir;
    private $count;
    private $allow_ids;
    private $ignore_ids;
    private $output = [];
    private $lock_file_path;
    private $bearer_token;
    private $curl = null;
    private $basic_auth_detected = false;

    public function __construct()
    {
        if (!defined('MODX_BASE_PATH')) {
            return;
        }

        $this->exportstart = $this->get_mtime();
        $this->count = 0;
        $this->setUrlMode();
        $this->targetDir = MODX_BASE_PATH . 'temp/export';
        $this->maxtime = 60;
        if (!$this->total) {
            $this->total = $this->getTotal();
        }
        $this->lock_file_path = MODX_CACHE_PATH . 'export.lock';
        if (!evo()->config('site_status')) {
            $this->bearer_token = bin2hex(random_bytes(64));
        }
    }

    private function getPastTime()
    {
        return time() - request_time();
    }

    public function setExportDir($dir)
    {
        $dir = str_replace('\\', '/', $dir);
        $dir = rtrim($dir, '/');
        $this->targetDir = $dir;
    }

    public function get_mtime()
    {
        $mtime = microtime();
        $mtime = explode(' ', $mtime);
        $mtime = $mtime[1] + $mtime[0];
        return $mtime;
    }

    private function setUrlMode()
    {
        global $modx;

        if ($modx->config('friendly_urls') == 0) {
            $modx->config['friendly_urls'] = 1;
            $modx->config['use_alias_path'] = 1;
            $modx->clearCache();
        }
    }

    public function getTotal($allow_ids = '', $ignore_ids = '', $noncache = '0')
    {
        if ($allow_ids !== '') {
            $allow_ids = explode(',', $allow_ids);
            foreach ($allow_ids as $i => $v) {
                $v = db()->escape(trim($v));
                $allow_ids[$i] = "'{$v}'";
            }
            $allow_ids = implode(',', $allow_ids);
            $allow_ids = "AND id IN ({$allow_ids})";
        }
        if ($ignore_ids !== '') {
            $ignore_ids = explode(',', $ignore_ids);
            foreach ($ignore_ids as $i => $v) {
                $v = db()->escape(trim($v));
                $ignore_ids[$i] = "'{$v}'";
            }
            $ignore_ids = implode(',', $ignore_ids);
            $ignore_ids = "AND NOT id IN ({$ignore_ids})";
        }

        $this->allow_ids = $allow_ids;
        $this->ignore_ids = $ignore_ids;

        $rs = db()->select(
            'count(id) as total',
            '[+prefix+]site_content',
            sprintf(
                "deleted=0 AND ((published=1 AND type='document') OR (isfolder=1)) %s %s",
                $noncache == 1 ? '' : 'AND cacheable=1',
                $allow_ids ?: $ignore_ids
            )
        );
        $row = db()->getRow($rs);
        $this->total = $row['total'];
        return $row['total'];
    }

    private function removeDirectoryAll($directory = '')
    {
        if (empty($directory)) {
            $directory = $this->targetDir;
        }
        $directory = rtrim($directory, '/');
        // if the path is not valid or is not a directory ...
        if (empty($directory)) {
            return false;
        }
        if (strpos($directory, MODX_BASE_PATH) !== 0) {
            return false;
        }

        if (!is_dir($directory)) {
            return false;
        }

        if (!is_readable($directory)) {
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

        if ($directory !== $this->targetDir && $rs) {
            $rs = rmdir($directory);
        }

        return $rs;
    }

    private function makeFile($docid, $filepath)
    {
        global $modx, $_lang;

        $pastTime = $this->getPastTime();
        if (!empty($this->maxtime) && $this->maxtime < $pastTime) {
            $msg = $modx->parseText($_lang['export_site_exit_maxtime'],
                ['count' => $this->count, 'total' => $this->total, 'maxtime' => $this->maxtime]);
            exit($msg);
        }

        $url = $modx->makeUrl($docid, '', '', 'full');
        $src = $this->get_contents($url);

        if ($src === false || is_file(dirname($filepath))) {
            return 'failed_no_open';
        }

        if ($this->repl_before !== $this->repl_after) {
            $src = str_replace($this->repl_before, $this->repl_after, $src);
        }

        $result = file_put_contents($filepath, $src);

        if ($result === false) {
            return 'failed_no_write';
        }

        $file_permission = octdec($modx->config('new_file_permissions'));
        @chmod($filepath, 0666);
        return 'success';
    }

    private function getFileName($docid, $alias, $prefix, $suffix)
    {
        global $modx;

        if ($alias === '') {
            $filename = $prefix . $docid . $suffix;
        } else {
            if ($modx->config['suffix_mode'] === '1' && strpos($alias, '.') !== false) {
                $suffix = '';
            }
            $filename = $prefix . $alias . $suffix;
        }
        return $filename;
    }

    public function run()
    {
        global $_lang;
        global $modx;

        $rs = db()->select(
            "id, alias, pagetitle, isfolder, (content = '' AND template = 0) AS wasNull, published",
            '[+prefix+]site_content',
            sprintf(
                "deleted=0 AND ((published=1 AND type='document') OR (isfolder=1)) %s %s",
                ($modx->config('export_includenoncache') == 1
                    ? ''
                    : 'AND cacheable=1'),
                $this->allow_ids ?: $this->ignore_ids
            )
        );

        $ph = [];
        $ph['total'] = $this->total;
        $folder_permission = octdec($modx->config['new_folder_permissions']);

        if (!is_file($this->lock_file_path)) {
            $this->removeDirectoryAll($this->targetDir);
        }
        touch($this->lock_file_path);

        if ($this->bearer_token) {
            evo()->saveBearerToken($this->bearer_token, time() + 60*60*3);
        }

    // Initialize a reusable curl handle before starting the loop (if available)
    $this->initCurl();

        $mask = umask();
        while ($row = db()->getRow($rs)) {
            $_ = $modx->getAliasListing($row['id'], 'path');
            $target_base_path = $_ == ''
                ? sprintf('%s/', $this->targetDir)
                : sprintf('%s/%s/', $this->targetDir, $_)
            ;
            unset($_);
            $_ = rtrim($target_base_path, '/');
            umask(000);
            if (!file_exists($_)) {
                mkdir($_, 0777, true);
            }
            umask($mask);
            unset($_);

            $this->count++;
            $row['count'] = $this->count;

            $this->processRow($row, $target_base_path, $_lang, $mask);

            // If a basic auth (HTTP 401) was detected during fetching, show warning and stop
            if ($this->basic_auth_detected) {
                // use language string for the warning (can contain HTML)
                $this->output[] = '<div style="color:#a00; font-weight:bold; margin-bottom:1em;">' . $_lang['export_site_basic_auth_warning'] . '</div>';
                break;
            }
        }

        // Close reusable curl handle
        if ($this->curl) {
            @curl_close($this->curl);
            $this->curl = null;
        }

        if (is_file($this->lock_file_path)) {
            unlink($this->lock_file_path);
        }

        return implode("\n", $this->output);
    }

    private function processRow($row, $target_base_path, $_lang, $mask)
    {
        $filename = null;

        if (!$row['wasNull']) { // needs writing a document
            $docname = $this->getFileName(
                $row['id'],
                $row['alias'],
                evo()->config('friendly_url_prefix'),
                evo()->config('friendly_url_suffix')
            );
            $filename = $target_base_path . $docname;
            if (is_dir($filename)) {
                $filename = rtrim($filename, '/') . '/index.html';
            }
            if (!is_file($filename) || substr($filename, -10) === 'index.html') {
                if ($row['published'] == 1) {
                    $status = $this->makeFile($row['id'], $filename);
                    switch ($status) {
                        case 'failed_no_write':
                            $row['status'] = $this->makeMsg('failed_no_write', 'fail');
                            break;
                        case 'failed_no_open':
                            $row['status'] = $this->makeMsg('failed_no_open', 'fail');
                            break;
                        default:
                            $row['status'] = $this->makeMsg('success');
                    }
                } else {
                    $row['status'] = $this->makeMsg('failed_no_retrieve', 'fail');
                }
            } else {
                $row['status'] = $this->makeMsg('success_skip_doc');
            }
            if (!$this->basic_auth_detected) {
                $this->output[] = evo()->parseText($_lang['export_site_exporting_document'], $row);
            }
        } else {
            $row['status'] = $this->makeMsg('success_skip_dir');
            if (!$this->basic_auth_detected) {
                $this->output[] = evo()->parseText($_lang['export_site_exporting_document'], $row);
            }
        }

        if ($row['isfolder'] != 1) {
            return;
        }

        if (evo()->config('suffix_mode') == 1 && strpos($row['alias'], '.') !== false) {
            return;
        }

        $end_dir = ($row['alias'] !== '') ? $row['alias'] : $row['id'];
        $folder_path = $target_base_path . $end_dir;
        if (strpos($folder_path, MODX_BASE_PATH) !== 0) {
            return false;
        }

        if (!is_dir($folder_path)) {
            if (is_file($folder_path)) {
                @unlink($folder_path);
            }
            umask(000);
            mkdir($folder_path, 0777);
            umask($mask);
        }

        if (evo()->config('make_folders') != 1 || $row['published'] != 1) {
            return;
        }

        if ($filename && is_file($filename)) {
            rename($filename, $folder_path . '/index.html');
        }
    }

    private function get_contents($url, $timeout = 10)
    {
        if (!extension_loaded('curl')) {
            // try to detect 401 from headers when curl isn't available
            $headers = @get_headers($url);
            if (is_array($headers) && isset($headers[0]) && strpos($headers[0], '401') !== false) {
                $this->basic_auth_detected = true;
                return false;
            }
            return @file_get_contents($url);
        }

        // ensure reusable curl handle is initialized
        if (!$this->curl) {
            $this->initCurl($timeout);
        }

        if (!$this->curl) {
            return false;
        }

        // set URL for this request and execute using the reusable handle
        curl_setopt($this->curl, CURLOPT_URL, $url);
        $result = curl_exec($this->curl);

        if ($result === false) {
            $i = 0;
            while ($i < 2) {
                usleep(300000);
                $result = curl_exec($this->curl);
                $i++;
            }
        }

        // check HTTP status and mark basic auth detected on 401
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if ($http_code === 401) {
            $this->basic_auth_detected = true;
            return false;
        }

        return $result;
    }

    // initialize a reusable curl handle with common options
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
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }

        if ($this->bearer_token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer '. $this->bearer_token
            ]);
        }

        // cookie persistence can be enabled if needed
        // curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/export_cookies.txt');
        // curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/export_cookies.txt');

        $this->curl = $ch;
        return true;
    }

    private function makeMsg($cond, $status = 'success')
    {
        global $modx, $_lang;

        $tpl = ' <span class="[+status+]">[+msg1+]</span> [+msg2+]</span>';
        $ph = [];
        $ph['status'] = $status;
        $ph['msg1'] = ($status === 'success')
            ? $_lang['export_site_success']
            : $_lang['export_site_failed'];

        if ($cond === 'failed_no_write') {
            $ph['msg2'] = $_lang["export_site_failed_no_write"] . ' - ' . $this->targetDir . '/';
        } elseif ($cond === 'failed_no_retrieve') {
            $ph['msg2'] = $_lang["export_site_failed_no_retrieve"];
        } elseif ($cond === 'failed_no_open') {
            $ph['msg2'] = $_lang["export_site_failed_no_open"];
        } elseif ($cond === 'success_skip_doc') {
            $ph['msg2'] = $_lang['export_site_success_skip_doc'];
        } elseif ($cond === 'success_skip_dir') {
            $ph['msg2'] = $_lang['export_site_success_skip_dir'];
        } else {
            $ph['msg2'] = '';
        }

        return $modx->parseText($tpl, $ph);
    }
}

$this->export = new EXPORT_SITE;
