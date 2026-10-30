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
     * 既にあるファイル・フォルダーを指す名前（パスの 1 要素）を確かめる。
     * 名前は書き換えずにそのまま返し、要素として使えないものは空文字を返す。
     * - / \ NUL を含むもの（複数の要素にまたがる名前）
     * - ドットと空白だけのもの（. や .. など。Windows では末尾のドット・空白が落ちて .. と同じになる）
     * 名前の途中の .. （v1..2 など）はそのままの名前として扱う（作る側の sanitizeFilename() と揃える）。
     */
    public static function sanitizeSegment($value)
    {
        $value = (string) $value;
        if (strpbrk($value, "/\\\0") !== false) {
            return '';
        }
        $value = trim($value);
        if (trim($value, '. ') === '') {
            return '';
        }

        return $value;
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
            if ($segment === '' || $segment === '.') {
                continue;
            }
            // .. などは、飛ばすと別のフォルダーを指してしまうため、見つからない扱いにする
            $clean = self::sanitizeSegment($segment);
            if ($clean === '') {
                return null;
            }
            $segments[] = $clean;
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

        if (strpos($real, $this->baseDir) !== 0) {
            return null;
        }

        return $real;
    }
}
