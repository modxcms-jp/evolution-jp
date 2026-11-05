<?php

/**
 * パスの安全確認（対象ディレクトリの中か、守る場所か）をまとめたクラス。
 * 存在しないパスも、存在する親までを realpath() で解決して判定する。
 */
class PathGuard
{
    /** @var string|false */
    private $baseDir;
    /** @var array */
    private $protectedPaths;
    /** @var string[]|null normalize() して小文字にした守る場所（一覧の項目ごとに解決し直さないよう、初めて使うときに作る） */
    private $normalizedProtected;

    public function __construct($baseDir, array $protectedPaths = [])
    {
        $base = self::normalize($baseDir);
        $this->baseDir = $base === false ? false : rtrim($base, '/');
        $this->protectedPaths = $protectedPaths;
    }

    /**
     * 権限の無い利用者に対して守る場所の一覧。管理者は空。
     */
    public static function protectedPathsFor(DocumentParser $modx)
    {
        if (manager()->isAdmin()) {
            return [];
        }

        $protected = [
            base_path() . 'manager',
            base_path() . 'temp/backup',
            base_path() . 'assets/backup'
        ];

        if (!$modx->hasPermission('save_plugin')) {
            $protected[] = base_path() . 'assets/plugins';
        }
        if (!$modx->hasPermission('save_snippet')) {
            $protected[] = base_path() . 'assets/snippets';
        }
        if (!$modx->hasPermission('save_template')) {
            $protected[] = base_path() . 'assets/templates';
        }
        if (!$modx->hasPermission('save_module')) {
            $protected[] = base_path() . 'assets/modules';
        }
        if (!$modx->hasPermission('empty_cache')) {
            $protected[] = rtrim(MODX_CACHE_PATH, '/');
        }
        if (!$modx->hasPermission('import_static')) {
            $protected[] = base_path() . 'temp/import';
            $protected[] = base_path() . 'assets/import';
        }
        if (!$modx->hasPermission('export_static')) {
            $protected[] = base_path() . 'temp/export';
            $protected[] = base_path() . 'assets/export';
        }
        return $protected;
    }

    /**
     * 存在する部分は realpath() で解決し、存在しない残りは文字列のまま . と .. を畳んでつなぐ。
     * .. を畳んだときは、畳んだ結果をもう一度解決する（畳んで現れた、存在する部分のリンクをたどるため）。
     * ヌルバイトを含むパスは解決できないものとして false を返す。
     */
    public static function normalize($path)
    {
        // ヌルバイトを含むもの、絶対パスでないもの（file:// などの形、相対パス）は解決しない
        if (strpos($path, "\0") !== false || !self::isAbsoluteLocal($path)) {
            return false;
        }
        $is_windows = DIRECTORY_SEPARATOR === '\\';
        if ($is_windows) {
            $path = str_replace('\\', '/', $path);
        }
        $rest = [];
        $realpath = realpath($path);
        while ($realpath === false) {
            $parent = dirname($path);
            if ($parent === $path) {
                return false;
            }
            // PHP 7.4 の basename() はロケールによってマルチバイトの名前を落とすため、使わない
            $trimmed = rtrim($path, '/');
            $pos = strrpos($trimmed, '/');
            array_unshift($rest, $pos === false ? $trimmed : substr($trimmed, $pos + 1));
            $path = $parent;
            $realpath = realpath($path);
        }
        if ($is_windows) {
            $realpath = str_replace('\\', '/', $realpath);
        }
        $segments = explode('/', rtrim($realpath, '/'));
        $folded = false;
        foreach ($rest as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                $folded = true;
                if (count($segments) > 1) {
                    array_pop($segments);
                }
                continue;
            }
            $segments[] = $segment;
        }
        $result = implode('/', $segments);
        if ($result === '') {
            // 一番上まで上がったときは、realpath('') がカレントを返すため、ルートを返す
            $result = '/';
        }
        return $folded ? self::normalize($result) : $result;
    }

    /**
     * ローカルの絶対パスなら true。Linux は / で始まるもの、Windows は ドライブ文字 + : + 区切り で始まるものだけを受け付ける。
     * file:// や php:// や phar:// などの形と、相対パスは受け付けない（PHP のファイル関数は URL の形を別の場所として扱うため）。
     */
    public static function isAbsoluteLocal($path)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return (bool) preg_match('@^[A-Za-z]:[\\\\/]@', $path);
        }
        return strpos($path, '/') === 0;
    }

    /**
     * パスの要素に .. があれば true。区切りは / と、Windows のときは \ も扱う。
     */
    public static function hasParentReference($path)
    {
        $separators = DIRECTORY_SEPARATOR === '\\' ? '/\\\\' : '/';
        return (bool) preg_match('@(^|[' . $separators . '])\.\.([' . $separators . ']|$)@', $path);
    }

    /**
     * パスが、対象ディレクトリそのもの、またはその配下なら true。
     * 許可する側の照合なので大文字小文字を区別して比べる（区別しないと、名前の大小だけが違う別のディレクトリまで許してしまう）。
     * 区別しないファイルシステムでも、realpath() が実在する側の表記に正規化する。
     */
    public function isWithinBase($path)
    {
        $realpath = self::normalize($path);
        if ($this->baseDir === false || $realpath === false) {
            return false;
        }
        return $realpath === $this->baseDir || strpos($realpath, $this->baseDir . '/') === 0;
    }

    /**
     * パスが、対象ディレクトリそのものなら true。
     */
    public function isBase($path)
    {
        $realpath = self::normalize($path);
        return $this->baseDir !== false && $realpath !== false && rtrim($realpath, '/') === $this->baseDir;
    }

    /**
     * 守る場所そのもの、またはその配下なら true。
     * どちらもまだ存在しなくてよい（アップロード先、作成するフォルダ、未作成のバックアップフォルダなど）。
     * 大文字小文字を区別しないファイルシステムもあるため、区別せずに照合する。
     */
    public function isProtected($path)
    {
        if (!$this->protectedPaths) {
            return false;
        }
        // ヌルバイトを含むパスは、止める側に倒す
        if (strpos($path, "\0") !== false) {
            return true;
        }
        $realpath = self::normalize($path);
        if ($realpath === false) {
            return false;
        }
        $realpath = strtolower($realpath);
        foreach ($this->normalizedProtected() as $protected) {
            if ($realpath === $protected || strpos($realpath, $protected . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 守る場所のどれかが、パスの配下にあれば true。
     * そのパスを消す・名前を変えると、中の守る場所ごと動いてしまう（パスそのものが守る場所の場合は isProtected() で見る）。
     */
    public function containsProtected($path)
    {
        if (!$this->protectedPaths) {
            return false;
        }
        // ヌルバイトを含むパスは、止める側に倒す
        if (strpos($path, "\0") !== false) {
            return true;
        }
        $realpath = self::normalize($path);
        if ($realpath === false) {
            return false;
        }
        $prefix = strtolower(rtrim($realpath, '/')) . '/';
        foreach ($this->normalizedProtected() as $protected) {
            if (strpos($protected, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 守る場所を normalize() して小文字にしたもの（解決できないものは除く）
     *
     * @return string[]
     */
    private function normalizedProtected()
    {
        if ($this->normalizedProtected === null) {
            $this->normalizedProtected = [];
            foreach ($this->protectedPaths as $protected) {
                $protected = self::normalize($protected);
                if ($protected !== false) {
                    $this->normalizedProtected[] = strtolower($protected);
                }
            }
        }

        return $this->normalizedProtected;
    }
}
