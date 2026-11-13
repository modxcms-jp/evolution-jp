<?php
/**
 * 同梱の要素（snippet / plugin / module）の雛形で、@version の上げ忘れを検出する（Issue #1442）。
 *
 * アップデーター（install/）は、雛形 *.install_base.tpl の @version が DB の description 先頭の版と
 * 同じなら、その要素を入れ直さない。雛形を変えたのに @version が同じだと、DB のコードが古いまま残る。
 * @version は要素自身の版で、雛形を変えたら上げる。決まりは assets/docs/migrations.md。
 *
 * 比べる相手: release-* のタグのうち、版（release- と末尾の J を除いたもの）が
 *   manager/includes/version.inc.php の $modx_version（末尾の J を除く）以下で、version_compare で一番新しいもの。
 *   main の祖先でなくてもよい。release-* が 1 つも読めなければ（git fetch --tags が要る）終了コード 2、
 *   タグはあるが今の版以下のものが無ければ、その旨を出して成功で終える。
 * 対象: install/functions.php の collectTpls() と同じ範囲
 *   （assets/{snippets,plugins,modules}/ の直下と 1 階層下にある *.install_base.tpl）。plugins の /mgr_custom/ は除く。
 *   タグに無い（新しい）ファイルは飛ばす。
 * 判定: タグの中身と今の中身が違うのに、@version の値が同じなら違反。
 *
 * 実行: php .github/scripts/element-version-lint.php [比べるタグ]
 * 違反がなければ "0 violations" と出して終了コード 0、あれば 1 で終わる（タグや版を読めないときは 2）。
 * 要 git。CI では actions/checkout に fetch-depth: 0 と fetch-tags: true を付ける。
 */

$root = dirname(__DIR__, 2);

/** git を、シェルを通さず引数の配列で実行する（PHP 7.4 以上の proc_open）。[終了コード, 標準出力] を返す */
function git($root, array $args)
{
    $proc = proc_open(
        array_merge(['git', '-C', $root], $args),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($proc)) {
        fwrite(STDERR, "git を実行できません\n");
        exit(2);
    }
    $out = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), $out];
}

function releaseOf($tag)
{
    return preg_replace('/^release-|J$/', '', $tag);
}

function currentVersion($root)
{
    $src = file_get_contents($root . '/manager/includes/version.inc.php');
    if (!preg_match('/\$modx_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $m)) {
        fwrite(STDERR, "version.inc.php から \$modx_version を読めません\n");
        exit(2);
    }
    return preg_replace('/J$/', '', $m[1]);
}

/** 比べるタグを返す。release-* が 1 つも読めなければ exit 2、今の版以下のものが無ければ null */
function baseTag($root, $arg)
{
    if ($arg !== null) {
        return $arg;
    }
    $current = currentVersion($root);
    [$code, $list] = git($root, ['tag', '-l', 'release-*']);
    $tags = array_filter(explode("\n", $list));
    if ($code !== 0 || !$tags) {
        fwrite(STDERR, "release-* タグを読めません。git fetch --tags でタグを取得してください（CI では actions/checkout に fetch-depth: 0 と fetch-tags: true を付ける）\n");
        exit(2);
    }
    $best = null;
    foreach ($tags as $tag) {
        $v = releaseOf($tag);
        if (version_compare($v, $current, '>')) {
            continue;
        }
        if ($best === null || version_compare($v, releaseOf($best), '>')) {
            $best = $tag;
        }
    }
    return $best;
}

function targetFiles($root)
{
    $files = [];
    foreach (['snippets', 'plugins', 'modules'] as $dir) {
        $base = $root . '/assets/' . $dir . '/';
        $found = array_merge(
            (array)glob($base . '*/*.install_base.tpl'),
            (array)glob($base . '*.install_base.tpl')
        );
        foreach ($found as $f) {
            $rel = substr($f, strlen($root) + 1);
            if ($dir === 'plugins' && strpos($rel, '/mgr_custom/') !== false) {
                continue;
            }
            $files[] = $rel;
        }
    }
    sort($files);
    return $files;
}

function docVersion($content)
{
    // install/functions.php の parse_docblock() と同じく、行の残り全体を trim する
    return preg_match('/^\s+\*\s+(?:@internal\s+)?@version[ \t]+(.+)/m', $content, $m) ? trim($m[1]) : null;
}

$tag = baseTag($root, $argv[1] ?? null);
if ($tag === null) {
    echo "今の版（" . currentVersion($root) . "）以下の release-* タグが無いので、検査を飛ばします\n";
    echo "0 violations\n";
    exit(0);
}
[$code] = git($root, ['rev-parse', '--verify', '--quiet', $tag . '^{commit}']);
if ($code !== 0) {
    fwrite(STDERR, "タグ {$tag} が見つかりません\n");
    exit(2);
}
echo "比べるタグ: {$tag}\n";

$violations = [];
foreach (targetFiles($root) as $rel) {
    [$code, $old] = git($root, ['show', $tag . ':' . $rel]);
    if ($code !== 0) {
        continue; // タグに無い（新しい）ファイル
    }
    if ($old === file_get_contents($root . '/' . $rel)) {
        continue;
    }
    $oldVer = docVersion($old);
    if ($oldVer !== null && $oldVer === docVersion(file_get_contents($root . '/' . $rel))) {
        $violations[] = sprintf(
            '%s  @version %s が %s から変わっていません。雛形を変えたので、@version（要素の版）を上げてください',
            $rel,
            $oldVer,
            $tag
        );
    }
}

foreach ($violations as $v) {
    echo $v, "\n";
}
echo count($violations), " violations\n";
exit($violations ? 1 : 0);
