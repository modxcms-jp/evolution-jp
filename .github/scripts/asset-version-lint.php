<?php
/**
 * 管理画面の静的な JS・CSS の読み込みに、版（?...）の付け忘れがないかを検出する（Issue #1358）。
 *
 * 版が無いと、ブラウザが古い JS・CSS をキャッシュから使い続け、アップグレード後に画面が壊れる。
 * PHP からは manager_asset() / site_asset() / asset_version()（manager/includes/helpers.php）で版を付ける。
 * PHP を使えない .tpl は、パスを見える形で残し、版だけを ?[+..._ver+] のプレースホルダーで渡す。
 *
 * 検出するもの: manager/ と assets/ の .php / .tpl / .html にある
 *   <script src="...js"> と <link href="...css"> のうち、値が .js / .css で終わり、? を含まないもの。
 * 対象外: http(s):// と // で始まる外部の URL、assets/plugins/<名前>/tinymce* 以下、下の除外リスト。
 * .php は token_get_all() で読み、HTML の部分（PHP の埋め込みは 1 つの値として扱う）と、PHP の文字列
 * （引用符つき・ヒアドキュメント。{$var} などの埋め込みも 1 つの値）の中を検査する。.tpl / .html は全体を文字列として検査する。
 * 検査できないもの: 連結で URL を組み立てる形（'src="' . $x . '"'）。属性の値が 1 つの文字列に収まらないため。ヘルパーを通すこと。
 *
 * 実行: php .github/scripts/asset-version-lint.php
 * 違反がなければ "0 violations" と出して終了コード 0、あれば 1 で終わる。
 * 誤検出は、行末に「asset-version-lint: ignore 理由」を書いて抑止する（理由は必須）。
 */

$root = dirname(__DIR__, 2);

// 版を付けないファイル（相対パスの前方一致 => 理由）
$excluded = [
    'assets/snippets/ditto/debug/' => 'Ditto のデバッグ表示。サイト側の出力で、管理画面の読み込みではない',
];

function relPath($root, $file)
{
    return ltrim(substr($file, strlen($root)), '/');
}

function targetFiles($root)
{
    $files = [];
    foreach (['manager', 'assets'] as $dir) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && preg_match('/\.(php|tpl|html)$/', $f->getFilename())) {
                $files[] = $f->getPathname();
            }
        }
    }
    sort($files);
    return $files;
}

/**
 * 検査する文字列のまとまり。text に、各部分の始まりの位置と行番号（pieces）を添える
 */
class Chunk
{
    public $text = '';
    public $pieces = [];

    public function add($text, $line)
    {
        $this->pieces[] = [strlen($this->text), $line];
        $this->text .= $text;
    }

    public function addPhp($line)
    {
        // PHP の埋め込みは 1 つの値にまとめる（中の ? を版と取り違えない）
        if (substr($this->text, -7) !== '__PHP__') {
            $this->add('__PHP__', $line);
        }
    }

    public function lineAt($offset)
    {
        $line = 1;
        $start = 0;
        foreach ($this->pieces as $piece) {
            if ($piece[0] > $offset) {
                break;
            }
            $start = $piece[0];
            $line = $piece[1];
        }
        return $line + substr_count($this->text, "\n", $start, $offset - $start);
    }
}

/** .php を字句に分け、HTML と PHP の文字列（引用符つき・ヒアドキュメント）のまとまりにする */
function phpChunks($content)
{
    $chunks = [];
    $html = new Chunk();
    $inPhp = false;
    $interp = null; // 変数を埋め込む文字列（二重引用符・ヒアドキュメント）の中のまとまり
    $line = 1;

    foreach (token_get_all($content) as $token) {
        if (is_array($token)) {
            [$id, $text, $line] = $token;
        } else {
            [$id, $text] = [null, $token];
        }

        if ($interp !== null) {
            if ($id === T_ENCAPSED_AND_WHITESPACE) {
                $interp->add($text, $line);
            } elseif ($id === T_END_HEREDOC || ($id === null && $text === '"')) {
                $chunks[] = $interp;
                $interp = null;
            } else {
                $interp->addPhp($line);
            }
            continue;
        }

        if ($id === T_INLINE_HTML) {
            $html->add($text, $line);
            $inPhp = false;
        } elseif ($id === T_OPEN_TAG || $id === T_OPEN_TAG_WITH_ECHO) {
            $html->addPhp($line);
            $inPhp = true;
        } elseif ($id === T_CLOSE_TAG) {
            $inPhp = false;
            $html->add(substr_count($text, "\n") ? "\n" : '', $line);
        } elseif ($id === T_CONSTANT_ENCAPSED_STRING) {
            $str = new Chunk();
            $str->add(substr($text, 1, -1), $line);
            $chunks[] = $str;
        } elseif ($id === T_START_HEREDOC || ($id === null && $text === '"')) {
            $interp = new Chunk();
        } elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
            // nowdoc の本文
            $str = new Chunk();
            $str->add($text, $line);
            $chunks[] = $str;
        }
    }
    $chunks[] = $html;
    return $chunks;
}

function lintChunk($chunk, $rel, $lines)
{
    $violations = [];
    $pattern = '/<(script|link)\b[^>]*?\b(src|href)\s*=\s*\\\\?(["\'])([^"\'\\\\]*)\\\\?\3/is';
    if (!preg_match_all($pattern, $chunk->text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return [];
    }
    foreach ($matches as $m) {
        $tag = strtolower($m[1][0]);
        $attr = strtolower($m[2][0]);
        $url = $m[4][0];
        if (($tag === 'script') !== ($attr === 'src')) {
            continue;
        }
        $ext = $tag === 'script' ? 'js' : 'css';
        if (!preg_match('/\.' . $ext . '$/i', $url) || strpos($url, '?') !== false) {
            continue;
        }
        if (preg_match('@^(https?:)?//@i', $url)) {
            continue;
        }
        $lineNo = $chunk->lineAt($m[4][1]);
        $text = $lines[$lineNo - 1] ?? '';
        if (preg_match('/asset-version-lint:\s*ignore\s+\S/', $text)) {
            continue;
        }
        $violations[] = sprintf('%s:%d  version-missing  %s', $rel, $lineNo, trim($text));
    }
    return $violations;
}

function lintFile($root, $file, $excluded)
{
    $rel = relPath($root, $file);
    if (preg_match('@^assets/plugins/[^/]+/tinymce[^/]*/@', $rel)) {
        return [];
    }
    foreach ($excluded as $prefix => $reason) {
        if (strpos($rel, $prefix) === 0) {
            return [];
        }
    }

    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    if (substr($file, -4) === '.php') {
        $chunks = phpChunks($content);
    } else {
        // .tpl / .html はそのまま文字列として検査する（{{ }} や [+ +] は値の一部として扱う）
        $chunk = new Chunk();
        $chunk->add($content, 1);
        $chunks = [$chunk];
    }

    $violations = [];
    foreach ($chunks as $chunk) {
        foreach (lintChunk($chunk, $rel, $lines) as $v) {
            $violations[] = $v;
        }
    }
    return $violations;
}

$violations = [];
foreach (targetFiles($root) as $file) {
    foreach (lintFile($root, $file, $excluded) as $v) {
        $violations[] = $v;
    }
}

foreach ($violations as $v) {
    echo $v, "\n";
}
echo count($violations), " violations\n";
exit($violations ? 1 : 0);
