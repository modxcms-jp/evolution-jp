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
 * PHP の埋め込み（山括弧 + ? で始まるタグ）は 1 つの値として扱う。
 *
 * 実行: php .github/scripts/asset-version-lint.php
 * 違反がなければ "0 violations" と出して終了コード 0、あれば 1 で終わる。
 * 誤検出は、行末に「asset-version-lint: ignore 理由」を書いて抑止する（理由は必須）。
 */

$root = dirname(__DIR__, 2);

// 版を付けないファイル（相対パスの前方一致 => 理由）
$excluded = [
    'assets/snippets/ditto/debug/' => 'Ditto のデバッグ表示。サイト側の出力で、管理画面の読み込みではない',
    'assets/plugins/qm/inc/on_web_page_prerender.php' => 'QuickManager のサイト側の出力で、管理画面の読み込みではない',
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
    // PHP の埋め込みは 1 つの値にまとめる（中の ? を版と取り違えない）。行数は変えない
    $flat = preg_replace_callback(
        '/<\?.*?\?>/s',
        function ($m) {
            return '__PHP__' . str_repeat("\n", substr_count($m[0], "\n"));
        },
        $content
    );
    $lines = explode("\n", $content);
    $violations = [];

    $pattern = '/<(script|link)\b[^>]*?\b(src|href)\s*=\s*\\\\?(["\'])([^"\'\\\\]*)\\\\?\3/is';
    if (!preg_match_all($pattern, $flat, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
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
        $lineNo = substr_count($flat, "\n", 0, $m[4][1]);
        $text = $lines[$lineNo] ?? '';
        if (preg_match('/asset-version-lint:\s*ignore\s+\S/', $text)) {
            continue;
        }
        $violations[] = sprintf('%s:%d  version-missing  %s', $rel, $lineNo + 1, trim($text));
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
