<?php

/**
 * ファイルブラウザのパス解決。realpath()によるベースディレクトリ包含チェックで
 * ../越え等のパストラバーサルを遮断する(旧mcpukの文字列除去方式より堅牢)。
 */
class PathResolver
{
    /** @var string realpath済み・末尾スラッシュ付きのベースディレクトリ */
    private $baseDir;

    public function __construct($baseDir)
    {
        $real = realpath($baseDir);
        $normalized = str_replace('\\', '/', $real !== false ? $real : $baseDir);
        $this->baseDir = rtrim($normalized, '/') . '/';
    }

    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * ファイル名/フォルダ名の1セグメントを無害化する。
     * スラッシュ・null byte・親ディレクトリ参照を許さない単一セグメント名にする。
     */
    public static function sanitizeSegment($value)
    {
        $value = str_replace("\0", '', (string) $value);
        $value = str_replace('\\', '/', $value);

        while (strpos($value, '..') !== false) {
            $value = str_replace('..', '', $value);
        }

        $value = str_replace('/', '', $value);

        return trim($value);
    }

    /**
     * type(images/media/files)配下のfolderをベースディレクトリ内へ解決する。
     * 存在しない・ベースディレクトリ外に出る場合は null。
     *
     * @return array{rel:string,real:string}|null
     */
    public function resolveDir($type, $folder)
    {
        $segments = [$type];
        foreach (explode('/', str_replace('\\', '/', (string) $folder)) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $clean = self::sanitizeSegment($segment);
            if ($clean !== '') {
                $segments[] = $clean;
            }
        }

        $rel = implode('/', $segments);
        $real = realpath($this->baseDir . $rel);

        if ($real === false || !is_dir($real)) {
            return null;
        }

        $real = rtrim(str_replace('\\', '/', $real), '/') . '/';

        if (strpos($real, $this->baseDir) !== 0) {
            return null;
        }

        return ['rel' => $rel, 'real' => $real];
    }

    /**
     * dirReal直下のfileNameを解決する。ベースディレクトリ外・サブディレクトリ越えは null。
     */
    public function resolveFile($dirReal, $fileName)
    {
        $name = self::sanitizeSegment($fileName);
        if ($name === '') {
            return null;
        }

        $full = rtrim($dirReal, '/') . '/' . $name;
        $real = realpath($full);

        if ($real === false || !is_file($real)) {
            return null;
        }

        $real = str_replace('\\', '/', $real);

        if (strpos($real, rtrim($this->baseDir, '/')) !== 0) {
            return null;
        }

        return $real;
    }
}
