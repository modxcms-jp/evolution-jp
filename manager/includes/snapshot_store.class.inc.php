<?php

/**
 * スナップショット（サーバーの保存先に置くダンプ）の置き場所・保護の .htaccess・名前・一覧・世代を受け持つ。
 * 保存先の決め方（dir()）は、このクラスが持つ。
 */
class EvoSnapshotStore
{
    const AUTO_NAME_PATTERN = '/\A\d{4}-\d{2}-\d{2}-\d{6}-[0-9A-Za-z._-]+\.sql\z/';
    const FILE_NAME_PATTERN = '/\A[0-9A-Za-z][0-9A-Za-z._-]*\.sql\z/';
    const PRE_RESTORE_PREFIX = 'pre-restore-';
    const KEEP = 10;
    const PRE_RESTORE_KEEP = 3;
    const HTACCESS = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
    const DEFAULT_DIR = 'temp/backup/';
    // 以前の置き場所。temp/backup/ が無く、これだけが在るサイトでは、ここを使う
    const LEGACY_DIR = 'assets/backup/';

    public function dir()
    {
        $base = realpath(MODX_BASE_PATH);
        if ($base === false) {
            return self::defaultDir();
        }
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';

        $configured = (string)evo()->config('snapshot_path', '');
        if ($configured !== '') {
            $real = realpath($configured);
            if ($real !== false) {
                $real = rtrim(str_replace('\\', '/', $real), '/') . '/';
                if ($real !== $base && strpos($real, $base) === 0) {
                    return $real;
                }
            }
        }
        if (!is_dir($base . self::DEFAULT_DIR) && is_dir($base . self::LEGACY_DIR)) {
            return $base . self::LEGACY_DIR;
        }
        return $base . self::DEFAULT_DIR;
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
        return self::protect($dir);
    }

    /**
     * 保護の .htaccess を置く（無いか、古い書き方のときだけ書く）。
     * DB の設定を読めないインストーラーからも呼ぶので、置き場所は引数で受ける
     */
    public static function protect($dir)
    {
        $htaccess = rtrim($dir, '/') . '/.htaccess';
        if (is_file($htaccess)) {
            $current = file_get_contents($htaccess);
            if ($current === false) {
                return false;
            }
            if (!self::isLegacyHtaccess($current)) {
                return true;
            }
        }
        if (file_put_contents($htaccess, self::HTACCESS) === false) {
            // 書き込めなくても、保護の .htaccess が既に在れば保存先は使える
            return is_file($htaccess);
        }
        return true;
    }

    /**
     * 既定の保存先（snapshot_path が無いとき）。インストーラーとグローバル設定の保存が作る
     */
    public static function defaultDir()
    {
        return MODX_BASE_PATH . self::DEFAULT_DIR;
    }

    public function autoFilename($prefix = '')
    {
        return sprintf(
            '%s%s-%s.sql',
            $prefix,
            date('Y-m-d-His', request_time() + (int)evo()->config('server_offset_time', 0)),
            preg_replace('/[^0-9A-Za-z._-]/', '-', (string)(evo()->getVersionData('version') ?: 'unknown'))
        );
    }

    /**
     * 利用者が付けた名前の末尾を .sql にそろえる（大文字小文字を問わず .sql で終われば .sql に直し、無ければ足す）。
     * 空ならそのまま（自動の名前になる）
     */
    public function normalizeName($name)
    {
        $name = (string) $name;
        if ($name === '') {
            return '';
        }
        if (preg_match('/\.sql\z/i', $name)) {
            return substr($name, 0, -4) . '.sql';
        }
        return $name . '.sql';
    }

    public function isValidFilename($name)
    {
        return is_string($name) && preg_match(self::FILE_NAME_PATTERN, $name) === 1;
    }

    public function path($name)
    {
        // 既存のファイルを読むので、作るときの規則より緩くする（日本語や空白を含む名前も通す）
        if (!is_string($name) || $name === '' || $name !== basename(str_replace('\\', '/', $name))
            || strpos($name, '..') !== false || strpos($name, "\0") !== false
            || !preg_match('/\.sql\z/i', $name)) {
            return false;
        }
        $dir = realpath($this->dir());
        $path = realpath($this->dir() . $name);
        if ($dir === false || $path === false || !is_file($path)) {
            return false;
        }
        $dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
        if (strpos(str_replace('\\', '/', $path), $dir) !== 0) {
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

    public function prune($max = self::KEEP)
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

    /**
     * 自動の名前のスナップショットの数と、それ以外（名前付き・復元の前の控え）の数
     */
    public function counts()
    {
        $auto = 0;
        $other = 0;
        foreach ($this->files() as $file) {
            if (preg_match(self::AUTO_NAME_PATTERN, $file['name'])) {
                $auto++;
            } else {
                $other++;
            }
        }
        return ['auto' => $auto, 'other' => $other];
    }

    /**
     * バインドで読ませない置き場所（スナップショットの保存先と、以前の置き場所）の実パスの一覧。
     * 小文字にそろえ、末尾に / を付けて返す。存在しない場所は含めない。
     */
    public function denyDirs()
    {
        $dirs = [$this->dir(), MODX_BASE_PATH . self::DEFAULT_DIR, MODX_BASE_PATH . self::LEGACY_DIR];
        $configured = evo()->config('snapshot_path');
        if ($configured) {
            $dirs[] = $configured;
        }

        // 設定を広く誤っても、サイトのルート・assets/・assets/templates/ やその親は一覧に入れない
        $keep = [];
        foreach ([MODX_BASE_PATH, MODX_BASE_PATH . 'assets', MODX_BASE_PATH . 'assets/templates'] as $dir) {
            $real_dir = realpath($dir);
            if ($real_dir !== false) {
                $keep[] = strtolower(rtrim(str_replace('\\', '/', $real_dir), '/')) . '/';
            }
        }

        $result = [];
        foreach ($dirs as $dir) {
            $real_dir = realpath(str_replace('\\', '/', $dir));
            if ($real_dir === false || !is_dir($real_dir)) {
                continue;
            }
            $deny = strtolower(rtrim(str_replace('\\', '/', $real_dir), '/')) . '/';
            foreach ($keep as $protected) {
                if (strpos($protected, $deny) === 0) {
                    continue 2;
                }
            }
            $result[] = $deny;
        }

        return array_values(array_unique($result));
    }

    private static function isLegacyHtaccess($content)
    {
        $lines = array_filter(array_map('trim', explode("\n", strtolower($content))));
        return array_values($lines) === ['order deny,allow', 'deny from all'];
    }
}
