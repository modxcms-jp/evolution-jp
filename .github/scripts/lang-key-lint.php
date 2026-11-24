<?php
/**
 * 日本語と英語の言語ファイルで、片方にしか無いキーを検出する（Issue #1753）。
 *
 * 対象: install/langs/・manager/includes/lang/ の japanese-utf8.inc.php と english.inc.php。
 * インストーラーは選んだ言語のファイルだけを読み、管理画面は英語の値で補って読む。
 * どちらも、英語に無いキーは補われずにキー名や空のまま画面に出る。
 *
 * 実行: php .github/scripts/lang-key-lint.php
 * 違反がなければ "0 violations" と出して終了コード 0、あれば 1 で終わる（言語ファイルを読めないときは 2）。
 */

$root = dirname(__DIR__, 2);
$dirs = ['install/langs', 'manager/includes/lang'];

/** 言語ファイルのキーを返す。$_lang に書き込む形（install）と、配列を返す形（manager）の両方を読める */
function langKeys($path)
{
    if (!is_file($path)) {
        fwrite(STDERR, "言語ファイルがありません: {$path}\n");
        exit(2);
    }
    $_lang = [];
    $result = include $path;
    $values = is_array($result) ? $result : $_lang;
    if (!$values) {
        fwrite(STDERR, "言語ファイルからキーを読めません: {$path}\n");
        exit(2);
    }
    return array_keys($values);
}

$violations = [];
foreach ($dirs as $dir) {
    $japanese = langKeys("{$root}/{$dir}/japanese-utf8.inc.php");
    $english = langKeys("{$root}/{$dir}/english.inc.php");
    foreach (array_diff($japanese, $english) as $key) {
        $violations[] = "{$dir}/english.inc.php  key-missing  {$key}";
    }
    foreach (array_diff($english, $japanese) as $key) {
        $violations[] = "{$dir}/japanese-utf8.inc.php  key-missing  {$key}";
    }
}

foreach ($violations as $v) {
    echo $v, "\n";
}
echo count($violations), " violations\n";
exit($violations ? 1 : 0);
