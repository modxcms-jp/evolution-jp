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
    // 以前の置き場所。保存先にはしないが、残った控えを読ませないよう、守る場所の一覧には入れる
    const LEGACY_DIR = 'assets/backup/';
    // 保存先が使えないわけ（problem() の戻り値）
    const OUTSIDE = 'outside';
    const SHARED = 'shared';
    const UNWRITABLE = 'unwritable';
    // 保存先に置いてよいもの（これ以外があるフォルダは、スナップショット専用でないとみなす）
    const OWN_ENTRY_PATTERN = '/\A(?:\.htaccess|index\.html|.+\.sql|dump.+|.+\.tmp)\z/i';

    private $configured;

    /**
     * @param string|null $configured snapshot_path の値。null ならグローバル設定から読む（保存の前の値を確かめるときに渡す）
     */
    public function __construct($configured = null)
    {
        $this->configured = $configured;
    }

    /**
     * snapshot_path の値を保存の形にそろえる（前後の空白を除き、空なら既定値、
     * サイトのフォルダの下の絶対パスと相対パスは [(base_path)] の書き方、末尾に /）。
     * 保存（グローバル設定）と読むとき（dir()）が同じ規則を使う
     */
    public static function normalizeValue($value)
    {
        $value = trim(str_replace('\\', '/', (string)$value));
        if ($value === '') {
            return '[(base_path)]' . self::DEFAULT_DIR;
        }
        $base = str_replace('\\', '/', MODX_BASE_PATH);
        if (strpos($value, $base) === 0) {
            $value = '[(base_path)]' . substr($value, strlen($base));
        } elseif (strpos($value, '[(base_path)]') !== 0 && !self::isAbsolute($value)) {
            $value = '[(base_path)]' . ltrim($value, '/');
        }
        return rtrim($value, '/') . '/';
    }

    /**
     * 保存先の絶対パス（末尾に /）。値の解き方は normalizeValue() のとおり（相対パスはサイトのフォルダから。
     * CLI と管理画面で同じ場所になる）。使えるかは problem() で確かめる。使えない値でも、別の場所に切り替えない
     */
    public function dir()
    {
        $value = self::normalizeValue($this->configured ?? (string)evo()->config('snapshot_path', ''));
        return str_replace('[(base_path)]', str_replace('\\', '/', MODX_BASE_PATH), $value);
    }

    /**
     * 保存先が使えないわけ。使えれば ''。
     * OUTSIDE: サイトのフォルダの外か、サイトのフォルダそのもの。
     * SHARED: スナップショット以外のファイルがあり、保護の .htaccess も無いフォルダ（manager/・assets/ などに .htaccess を置かせない）。
     * UNWRITABLE: 無くて作れない、または書き込めない
     */
    public function problem()
    {
        $base = realpath(MODX_BASE_PATH);
        if ($base === false) {
            return self::OUTSIDE;
        }
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';

        // 在る親までを実パスにし、無い残りをつなぐ（. や .. を含む残りは外とみなす）
        $segments = explode('/', rtrim($this->dir(), '/'));
        $rest = [];
        while ($segments && !file_exists(self::joinPath($segments))) {
            $segment = array_pop($segments);
            if ($segment === '..' || $segment === '.' || $segment === '' || !$segments) {
                return self::OUTSIDE;
            }
            array_unshift($rest, $segment);
        }
        $existing = realpath(self::joinPath($segments));
        if ($existing === false) {
            return self::OUTSIDE;
        }
        $real = rtrim(str_replace('\\', '/', $existing), '/') . '/' . ($rest ? implode('/', $rest) . '/' : '');
        if ($real === $base || strpos($real, $base) !== 0) {
            return self::OUTSIDE;
        }
        if (!$rest && is_dir($existing) && !self::isDedicated($existing)) {
            return self::SHARED;
        }
        // 管理画面・テンプレート・ファイルピッカーの置き場所と、その中や親には置かない（保護の .htaccess で表から見えなくなるため）
        foreach (self::siteDirs($base) as $siteDir) {
            if (strpos($real, $siteDir) === 0 || strpos($siteDir, $real) === 0) {
                return self::SHARED;
            }
        }
        if (!is_dir($existing) || !is_writable($existing)) {
            return self::UNWRITABLE;
        }
        return '';
    }

    // 区切りを / でつなぐ。ルート（'' と Windows のドライブ名だけ）は / を付ける（C: はそのドライブの作業フォルダを指すため）
    private static function joinPath(array $segments)
    {
        $path = implode('/', $segments);
        return ($path === '' || preg_match('@\A[A-Za-z]:\z@', $path)) ? $path . '/' : $path;
    }

    // 保存先と重ねられないサイトの置き場所（末尾に /）。サイトのフォルダそのものは含めない。
    // 管理画面・テンプレート・ファイルピッカーは保護の .htaccess で表から見えなくなり、
    // HTML エクスポートの出力先は書き出しの始めに中身を消すため
    private static function siteDirs($base)
    {
        $dirs = [
            MODX_MANAGER_PATH,
            MODX_BASE_PATH . 'assets/templates/',
            str_replace('[(base_path)]', MODX_BASE_PATH, (string)evo()->config('rb_base_dir', '')),
            str_replace('[(base_path)]', MODX_BASE_PATH, (string)evo()->config('export_dir', '[(base_path)]temp/export/')),
        ];
        $result = [];
        foreach ($dirs as $dir) {
            if ($dir === '') {
                continue;
            }
            // まだ無い場所（エクスポートの出力先など）は、サイトのフォルダの実パスに読み替えて比べる
            $real = realpath($dir);
            if ($real === false) {
                $dir = str_replace('\\', '/', $dir);
                $siteBase = str_replace('\\', '/', MODX_BASE_PATH);
                $real = strpos($dir, $siteBase) === 0 ? $base . substr($dir, strlen($siteBase)) : $dir;
            }
            $real = rtrim(str_replace('\\', '/', $real), '/') . '/';
            if ($real !== $base) {
                $result[] = $real;
            }
        }
        return $result;
    }

    // 読ませない .htaccess（今の書き方か古い書き方）があるか、スナップショットのものしか無いフォルダか
    private static function isDedicated($dir)
    {
        $htaccess = $dir . '/.htaccess';
        if (is_file($htaccess)) {
            // 一部のファイルにしか効かないブロック（<Files> など）の中の拒否は数えない
            $current = (string)preg_replace('@<(Files|FilesMatch|If|ElseIf|Else|Location|LocationMatch|Directory|DirectoryMatch)\b.*?</\1>@is', '', (string)file_get_contents($htaccess));
            if (stripos($current, 'require all denied') !== false || stripos($current, 'deny from all') !== false) {
                return true;
            }
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return false;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            // 読ませない .htaccess は上で受け付けた。ここに残る .htaccess は拒否の無いもの（守れないので断る）
            if ($entry === '.htaccess' || is_dir($dir . '/' . $entry) || !preg_match(self::OWN_ENTRY_PATTERN, $entry)) {
                return false;
            }
        }
        return true;
    }

    // 絶対パスか（Windows ではドライブ名から始まるものも）
    private static function isAbsolute($path)
    {
        if (strpos($path, '/') === 0) {
            return true;
        }
        return DIRECTORY_SEPARATOR === '\\' && preg_match('@\A[A-Za-z]:/@', $path) === 1;
    }

    /**
     * 保存先を作り、保護の .htaccess を置く。使えなければ false を返し、システムログに残す（別の場所には切り替えない）
     */
    public function ensureDir()
    {
        $problem = $this->problem();
        $dir = $this->dir();
        if ($problem === '' && !is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $problem = self::UNWRITABLE;
        }
        if ($problem === '' && !is_writable($dir)) {
            $problem = self::UNWRITABLE;
        }
        if ($problem === '' && !self::protect($dir)) {
            $problem = self::UNWRITABLE;
        }
        if ($problem !== '') {
            evo()->logEvent(0, 3, "The snapshot directory {$dir} cannot be used ({$problem}).", 'Snapshot', [
                'snapshot_path' => $dir,
                'problem' => $problem,
            ]);
            return false;
        }
        return true;
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
        if (!in_array($this->problem(), ['', self::UNWRITABLE], true)) {
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
        if (!in_array($this->problem(), ['', self::UNWRITABLE], true)) {
            return [];
        }
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
     * 読ませない置き場所（今の保存先・既定の保存先・以前の置き場所）。存在しない場所も含む。
     * ファイルブラウザーの守る場所の一覧が使う
     */
    public function guardedDirs()
    {
        $dirs = [self::defaultDir(), MODX_BASE_PATH . self::LEGACY_DIR];
        // 使えない値（サイトの親・content/ など）は守る場所に入れない（サイトの置き場所ごと守ってしまうため）
        if (in_array($this->problem(), ['', self::UNWRITABLE], true)) {
            array_unshift($dirs, $this->dir());
        }
        return array_values(array_unique($dirs));
    }

    /**
     * バインドで読ませない置き場所（guardedDirs()）の実パスの一覧。
     * 小文字にそろえ、末尾に / を付けて返す。存在しない場所は含めない。
     */
    public function denyDirs()
    {
        $dirs = $this->guardedDirs();

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
