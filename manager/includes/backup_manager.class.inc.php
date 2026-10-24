<?php

/**
 * スナップショット（サーバー上の保存先に置く DB のバックアップ）の
 * 保存先・ファイル名・一覧・世代・作成・復元を受け持つ。
 */
class EvoBackupManager
{
    const AUTO_NAME_PATTERN = '/\A\d{4}-\d{2}-\d{2}-\d{6}-[0-9A-Za-z._-]+\.sql\z/';
    const FILE_NAME_PATTERN = '/\A[0-9A-Za-z][0-9A-Za-z._-]*\.sql\z/';
    const PRE_RESTORE_PREFIX = 'pre-restore-';
    const PRE_RESTORE_KEEP = 3;
    const HTACCESS = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";

    public function dir()
    {
        $base = realpath(MODX_BASE_PATH);
        if ($base === false) {
            return MODX_BASE_PATH . 'temp/backup/';
        }
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';

        $configured = (string)evo()->config('snapshot_path', '');
        if ($configured !== '') {
            $real = realpath($configured);
            if ($real !== false) {
                $real = rtrim(str_replace('\\', '/', $real), '/') . '/';
                if (strpos($real, $base) === 0) {
                    return $real;
                }
            }
        }
        if (!is_dir($base . 'temp/backup') && is_dir($base . 'assets/backup')) {
            return $base . 'assets/backup/';
        }
        return $base . 'temp/backup/';
    }

    public function ensureDir()
    {
        $dir = $this->dir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        if (!is_writable($dir)) {
            return false;
        }

        $htaccess = $dir . '.htaccess';
        if (is_file($htaccess)) {
            $current = file_get_contents($htaccess);
            if ($current === false) {
                return false;
            }
            if (!$this->isLegacyHtaccess($current)) {
                return true;
            }
        }
        return file_put_contents($htaccess, self::HTACCESS) !== false;
    }

    public function autoFilename($prefix = '')
    {
        return sprintf(
            '%s%s-%s.sql',
            $prefix,
            date('Y-m-d-His', request_time() + (int)evo()->config('server_offset_time', 0)),
            evo()->config('settings_version') ?: 'unknown'
        );
    }

    public function isValidFilename($name)
    {
        return is_string($name) && preg_match(self::FILE_NAME_PATTERN, $name) === 1;
    }

    public function path($name)
    {
        if (!$this->isValidFilename($name)) {
            return false;
        }
        $path = realpath($this->dir() . $name);
        if ($path === false || !is_file($path)) {
            return false;
        }
        return $path;
    }

    public function files()
    {
        $paths = glob($this->dir() . '*.sql');
        if ($paths === false) {
            return [];
        }
        $files = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $files[] = [
                'name'  => basename($path),
                'size'  => (int)filesize($path),
                'mtime' => (int)filemtime($path),
            ];
        }
        usort($files, function ($a, $b) {
            if ($a['mtime'] !== $b['mtime']) {
                return $b['mtime'] - $a['mtime'];
            }
            return strcmp($b['name'], $a['name']);
        });
        return $files;
    }

    public function createSnapshot($name = '', $contentsOnly = false)
    {
        if ($name === '') {
            $name = $this->autoFilename();
        }
        if (!$this->isValidFilename($name)) {
            return false;
        }
        if (!$this->ensureDir()) {
            return false;
        }

        @set_time_limit(120);
        global $modx;
        include_once MODX_CORE_PATH . 'mysql_dumper.class.inc.php';
        $dumper = new Mysqldumper();
        $dumper->mode = 'snapshot';
        $dumper->contentsOnly = $contentsOnly ? 1 : 0;
        if (!$dumper->dumpToFile($this->dir() . $name)) {
            return false;
        }

        $this->prune();
        return $name;
    }

    public function prune($max = 10)
    {
        $max = max(1, (int)$max);
        $auto = [];
        $preRestore = [];
        foreach ($this->files() as $file) {
            if (strpos($file['name'], self::PRE_RESTORE_PREFIX) === 0) {
                $name = substr($file['name'], strlen(self::PRE_RESTORE_PREFIX));
                if (preg_match(self::AUTO_NAME_PATTERN, $name)) {
                    $preRestore[] = $file['name'];
                }
                continue;
            }
            if (preg_match(self::AUTO_NAME_PATTERN, $file['name'])) {
                $auto[] = $file['name'];
            }
        }

        // files() は新しい順。上限から溢れた古いものを消す
        $targets = array_merge(
            array_slice($auto, $max),
            array_slice($preRestore, self::PRE_RESTORE_KEEP)
        );
        $removed = [];
        foreach ($targets as $name) {
            if (@unlink($this->dir() . $name)) {
                $removed[] = $name;
            }
        }
        return $removed;
    }

    public function restore($sql, $preRestore = null)
    {
        $result = [
            'ok'                 => false,
            'executed'           => 0,
            'skipped'            => 0,
            'error'              => '',
            'failed_at'          => 0,
            'pre_restore'        => '',
            'pre_restore_failed' => false,
        ];

        // 失敗したときに戻せるよう、書き換える前に今の DB を保存する
        $name = $this->autoFilename(self::PRE_RESTORE_PREFIX);
        if ($preRestore === null) {
            $saved = $this->createSnapshot($name) !== false;
        } else {
            $saved = $this->ensureDir() && call_user_func($preRestore, $this->dir() . $name);
        }
        if (!$saved) {
            $result['pre_restore_failed'] = true;
            return $result;
        }
        $result['pre_restore'] = $name;
        $this->prune();

        if (strpos($sql, "\r") !== false) {
            $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        }

        $prefix = db()->table_prefix;
        $cacheTable = $prefix . 'system_cache';
        $rs = db()->query("SHOW TABLES LIKE '" . db()->escape($cacheTable) . "'");
        if ($rs !== false) {
            if (db()->count($rs) > 0) {
                db()->query('TRUNCATE TABLE `' . $cacheTable . '`');
            }
            db()->freeResult($rs);
        }

        $keep = $this->getKeptSettings();

        $statements = preg_split('@;[ \t]*\n@', $sql);
        foreach ($statements as $i => $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            if (preg_match(
                '/^(INSERT INTO|REPLACE INTO|DROP TABLE IF EXISTS|CREATE TABLE|TRUNCATE TABLE)\s+`?([a-zA-Z0-9_]+)`?/i',
                $statement,
                $m
            ) && $m[2] === $cacheTable) {
                $result['skipped']++;
                continue;
            }
            if (db()->query($statement, false) === false) {
                $result['error'] = db()->getLastError();
                $result['failed_at'] = $i + 1;
                return $result;
            }
            $result['executed']++;
        }

        $this->restoreSettings($keep);
        evo()->clearCache();
        $result['ok'] = true;
        return $result;
    }

    private function isLegacyHtaccess($content)
    {
        $lines = array_filter(array_map('trim', explode("\n", strtolower($content))));
        return array_values($lines) === ['order deny,allow', 'deny from all'];
    }

    // 取り込み先にある、環境ごとに違う設定の値
    private function getKeptSettings()
    {
        $keep = [];
        $keys = ['site_url', 'base_url', 'filemanager_path', 'rb_base_dir', 'fixed_site_url'];
        foreach ($keys as $key) {
            $value = db()->getValue(
                'setting_value',
                '[+prefix+]system_settings',
                sprintf("setting_name='%s'", db()->escape($key))
            );
            if ($value !== null) {
                $keep[$key] = $value;
            }
        }
        return $keep;
    }

    private function restoreSettings($keep)
    {
        $placeholders = [
            'site_url'         => ['[(site_url)]', MODX_SITE_URL],
            'base_url'         => ['[(base_url)]', MODX_BASE_URL],
            'filemanager_path' => ['[(base_path)]', MODX_BASE_PATH],
            'rb_base_dir'      => ['[(base_path)]', MODX_BASE_PATH],
        ];

        foreach ($placeholders as $key => $replace) {
            $where = sprintf("setting_name='%s'", db()->escape($key));
            if (isset($keep[$key])) {
                $value = $keep[$key];
            } else {
                $value = db()->getValue('setting_value', '[+prefix+]system_settings', $where);
                if ($value === null || strpos($value, $replace[0]) === false) {
                    continue;
                }
                $value = str_replace($replace[0], $replace[1], $value);
            }
            db()->update(
                db()->escape(['setting_value' => $value]),
                '[+prefix+]system_settings',
                $where
            );
        }

        // 取り込み先の値に戻す。ダンプに行が無くても戻し、取り込み先に無ければ消す
        db()->delete('[+prefix+]system_settings', "setting_name='fixed_site_url'");
        if (isset($keep['fixed_site_url'])) {
            db()->insert(
                db()->escape(['setting_name' => 'fixed_site_url', 'setting_value' => $keep['fixed_site_url']]),
                '[+prefix+]system_settings'
            );
        }
    }
}
