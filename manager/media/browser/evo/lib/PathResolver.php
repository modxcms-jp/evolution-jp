<?php

/**
 * ファイルブラウザのパス解決。realpath()によるベースディレクトリ包含チェックで
 * ../越え等のパストラバーサルを遮断する(旧mcpukの文字列除去方式より堅牢)。
 * ルートと守る場所は範囲(BrowserScope)から受け取り、範囲が何かは知らない。
 */
class PathResolver
{
    /** @var string realpath済み・末尾スラッシュ付きのベースディレクトリ */
    private $baseDir;
    private $guard;

    public function __construct(BrowserScope $scope)
    {
        $baseDir = $scope->baseDir();
        $real = realpath($baseDir);
        $normalized = str_replace('\\', '/', $real !== false ? $real : $baseDir);
        $this->baseDir = rtrim($normalized, '/') . '/';
        $this->guard = $scope->guard();
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
     * ルート配下のfolder(ルートからの相対パス)をベースディレクトリ内へ解決する。
     * 存在しない・ベースディレクトリ外に出る・守る場所に当たる場合は null。
     *
     * @return array{rel:string,real:string}|null rel はルートからの相対パス(ルートそのものは空文字)、real は realpath 済みで末尾 / 付き
     */
    public function resolveDir($folder)
    {
        $segments = [];
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

        if (strpos($real, $this->baseDir) !== 0 || $this->guard->isProtected($real)) {
            return null;
        }

        return ['rel' => $rel, 'real' => $real];
    }

    /** dirReal直下のfileNameを解決する。ベースディレクトリ外・サブディレクトリ越え・守る場所は null */
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

        if (strpos($real, $this->baseDir) !== 0 || $this->guard->isProtected($real)) {
            return null;
        }

        return $real;
    }

    /**
     * dirReal直下に作る・名前を変える・移す先のパスを返す。まだ無くてよい。
     * 名前が 1 要素として使えない、または守る場所に当たるときは null。
     * dirReal は resolveDir() が返したもの(ルートの中)を渡す。
     */
    public function resolveNewChild($dirReal, $name)
    {
        $name = (string) $name;
        if ($name === '' || self::sanitizeSegment($name) !== $name) {
            return null;
        }

        $path = rtrim($dirReal, '/') . '/' . $name;
        if ($this->guard->isProtected($path)) {
            return null;
        }

        return $path;
    }
}
