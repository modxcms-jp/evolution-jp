<?php

/**
 * ファイルシステム操作の共通処理。コアの他のクラスに依存しない
 * (remove_installer.processor.php のようにコアを起動せずに読む場所からも使うため)。
 * シンボリックリンクの扱いの決まりは assets/docs/file-storage.md。
 */
class Filesystem
{
    /**
     * $fn を呼ぶ間だけ警告を横取りし、外へ出さない(evo の phpError に拾われて HTML の 500 になるのを防ぐ)。
     * 警告の文は $reason に入る。set_error_handler の中では error_get_last() が更新されないため、自前で受け取る。
     */
    public static function attempt(callable $fn, &$reason = null)
    {
        $reason = '';
        // 警告だけを受ける。E_DEPRECATED・E_NOTICE などは元のハンドラへ渡し、本当の原因を隠さない
        // (set_error_handler の種類の指定では、外れた種類は元のハンドラでなく PHP 標準の処理に行ってしまう)
        $previous = null;
        $previous = set_error_handler(function ($no, $message, $file = '', $line = 0) use (&$reason, &$previous) {
            if (!($no & (E_WARNING | E_USER_WARNING))) {
                return $previous ? $previous($no, $message, $file, $line) : false;
            }
            $reason = $reason === '' ? $message : $reason;
            return true;
        });
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * ディレクトリを中身ごと消す。最後まで消して、消せないものがあれば false を返し、
     * 失敗したパスを $failures に(パス => 理由 で)集める。
     *
     * - シンボリックリンクはリンクだけを消し、先へは入らない。
     * - 中へ入ってよい範囲は、最初に渡された $path の実パスの中だけ。範囲外のものは $failures[$path] = '' で false。
     * - $keepRoot = true のときは中身だけを消し、$path 自体は残す。このとき $path がシンボリックリンクなら、
     *   呼び出し側が明示した置き場所としてたどり、リンク先の中身を空にする(リンクは残す)。
     *   $keepRoot = false で $path がリンクなら、リンクだけを消す。
     * - $path が存在しなければ、消すものがないので true。ディレクトリでなければ $failures[$path] = '' で false。
     */
    public static function deleteTree($path, array &$failures, $keepRoot = false)
    {
        $path = rtrim((string)$path, '/\\');
        if ($path === '') {
            $failures[$path] = '';
            return false;
        }

        if (is_link($path) && !$keepRoot) {
            return self::unlinkEntry($path, $failures);
        }
        if (!file_exists($path)) {
            return true;
        }
        if (!is_dir($path)) {
            $failures[$path] = '';
            return false;
        }

        $scope = self::realDirWithSlash($path);
        if ($scope === '') {
            $failures[$path] = '';
            return false;
        }
        return self::deleteTreeIn($path, $failures, $scope, !$keepRoot);
    }

    /**
     * $root の下で中が空になったフォルダを、下から消す。$root 自身は残す。
     * シンボリックリンクには入らず、消しもしない。消したフォルダの数を返す。
     */
    public static function pruneEmptyDirs($root)
    {
        $root = rtrim((string)$root, '/\\');
        if ($root === '' || !is_dir($root)) {
            return 0;
        }
        $entries = self::attempt(function () use ($root) {
            return scandir($root);
        });
        if ($entries === false) {
            return 0;
        }

        $count = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $root . '/' . $entry;
            if (is_link($full) || !is_dir($full)) {
                continue;
            }
            $count += self::pruneEmptyDirs($full);
            $rest = self::attempt(function () use ($full) {
                return scandir($full);
            });
            if (is_array($rest) && count($rest) === 2 && self::attempt(function () use ($full) {
                return rmdir($full);
            })) {
                $count++;
            }
        }
        return $count;
    }

    private static function deleteTreeIn($path, array &$failures, $scope, $removeSelf)
    {
        if (strpos(self::realDirWithSlash($path), $scope) !== 0) {
            $failures[$path] = '';
            return false;
        }

        $entries = self::attempt(function () use ($path) {
            return scandir($path);
        }, $reason);
        if ($entries === false) {
            $failures[$path] = $reason;
            return false;
        }

        $ok = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            if (is_link($full) || !is_dir($full)) {
                $ok = self::unlinkEntry($full, $failures) && $ok;
            } elseif (!self::deleteTreeIn($full, $failures, $scope, true)) {
                $ok = false;
            }
        }

        if ($ok && $removeSelf && !self::attempt(function () use ($path) {
            return rmdir($path);
        }, $reason)) {
            $failures[$path] = $reason;
            $ok = false;
        }

        return $ok;
    }

    private static function unlinkEntry($path, array &$failures)
    {
        if (self::attempt(function () use ($path) {
            return unlink($path);
        }, $reason)) {
            return true;
        }
        $failures[$path] = $reason;
        return false;
    }

    private static function realDirWithSlash($path)
    {
        $real = realpath($path);
        return $real === false ? '' : rtrim(str_replace('\\', '/', $real), '/') . '/';
    }
}
