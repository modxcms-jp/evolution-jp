<?php
/**
 * 管理画面のリクエスト値の扱いと、JS への埋め込みの逸脱を検出する（Issue #493 の M3）。
 *
 * 検出するもの:
 *   sql-embed   processor の中で、リクエストの値（getv() / anyv() / postv() / cookiev() / $_GET など）を
 *               db()->escape() や (int)・*_intvar() を通さずに、SQL の文字列へ埋め込む・DB の呼び出しや where() へ渡す書き方。
 *               値を代入した変数・配列も追う（同じファイルの中で、上から順に）
 *   js-escape   <script> の中へ埋め込む値に addslashes() や db()->escape() を使う書き方（js_literal() を使う）
 *   csrf-action manager/index.php で processor を読み込むアクションのうち、
 *               csrfProtectedActions() の一覧にも、このスクリプトの除外リストにも載っていないもの
 *
 * 実行: php .github/scripts/security-lint.php
 * 違反がなければ "0 violations" と出して終了コード 0、あれば 1 で終わる。
 *
 * 字句の並びだけで判定するので、取りこぼしも誤検出もありうる。誤検出は、書き方を直すか、
 * 行末に「// security-lint: ignore 理由」を書いて抑止する（理由は必須）。
 */

$root = dirname(__DIR__, 2);

// 状態を変えるが、CSRF の一覧に入れない processor のアクション（理由を書く）
$csrfExcludedActions = [
    112 => 'モジュールの実行。更新するかどうかがモジュールの実装次第で、モジュール内のリンクがすべて影響を受ける',
];

function main($root, $csrfExcludedActions)
{
    $violations = [];

    foreach (phpFiles($root . '/manager/processors') as $file) {
        foreach (lintSqlEmbed($file) as $v) {
            $violations[] = $v;
        }
    }
    foreach (phpFiles($root . '/manager', ['manager/includes/controls/', 'manager/media/']) as $file) {
        foreach (lintJsEscape($file) as $v) {
            $violations[] = $v;
        }
    }
    foreach (lintCsrfActions($root, $csrfExcludedActions) as $v) {
        $violations[] = $v;
    }

    $violations = array_values(array_filter($violations, function ($v) use ($root) {
        return !isIgnoredLine($root . '/' . $v['file'], $v['line']);
    }));
    usort($violations, function ($a, $b) {
        return [$a['file'], $a['line'], $a['rule']] <=> [$b['file'], $b['line'], $b['rule']];
    });
    $seen = [];
    foreach ($violations as $v) {
        $key = $v['file'] . ':' . $v['line'] . ':' . $v['rule'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        printf("%s:%d [%s] %s\n", $v['file'], $v['line'], $v['rule'], $v['message']);
    }
    printf("%d violations\n", count($seen));
    exit($seen ? 1 : 0);
}

// ---------------------------------------------------------------------------
// 共通
// ---------------------------------------------------------------------------

function phpFiles($dir, $excludes = [])
{
    global $root;
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') {
            continue;
        }
        $rel = relPath($f->getPathname());
        foreach ($excludes as $ex) {
            if (strpos($rel, $ex) === 0) {
                continue 2;
            }
        }
        $files[] = $f->getPathname();
    }
    sort($files);
    return $files;
}

function relPath($path)
{
    global $root;
    return ltrim(substr($path, strlen($root)), '/');
}

function isIgnoredLine($path, $line)
{
    static $cache = [];
    if (!isset($cache[$path])) {
        $cache[$path] = file($path);
    }
    $text = $cache[$path][$line - 1] ?? '';
    return (bool)preg_match('@//\s*security-lint:\s*ignore\s+\S@', $text);
}

function violation($file, $line, $rule, $message)
{
    return ['file' => relPath($file), 'line' => $line, 'rule' => $rule, 'message' => $message];
}

/**
 * 空白とコメントを除いたトークン列。各要素は [id, text, line]（1 文字のトークンは id が文字そのもの）
 */
function tokens($file)
{
    $out = [];
    $line = 1;
    foreach (token_get_all(file_get_contents($file)) as $t) {
        if (is_array($t)) {
            $line = $t[2];
            if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out[] = [$t[0], $t[1], $t[2]];
            $line += substr_count($t[1], "\n");
            continue;
        }
        $out[] = [$t, $t, $line];
    }
    return $out;
}

function isTok($t, $id, $text = null)
{
    if ($t === null || $t[0] !== $id) {
        return false;
    }
    return $text === null || strcasecmp($t[1], $text) === 0;
}

// $i の開き括弧に対応する閉じ括弧の位置
function matchParen($toks, $i)
{
    $open = $toks[$i][1];
    $close = ['(' => ')', '[' => ']', '{' => '}'][$open];
    $depth = 0;
    for ($n = count($toks); $i < $n; $i++) {
        $id = $toks[$i][0];
        if ($id === $open || ($open === '{' && in_array($id, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
        } elseif ($id === $close) {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }
    return count($toks) - 1;
}

/**
 * 文（; と { } で区切った範囲）の [開始, 終了] の一覧。文字列の中の { } と、括弧の中の ; は区切りにしない
 */
function statements($toks)
{
    $list = [];
    $start = 0;
    $paren = 0;
    $inString = false;
    $strBrace = 0;
    $n = count($toks);
    for ($i = 0; $i < $n; $i++) {
        $t = $toks[$i];
        if ($t[0] === '"' || $t[0] === T_START_HEREDOC || $t[0] === T_END_HEREDOC) {
            $inString = $t[0] === T_START_HEREDOC ? true : ($t[0] === T_END_HEREDOC ? false : !$inString);
            continue;
        }
        if ($inString) {
            if ($t[0] === T_CURLY_OPEN || $t[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $strBrace++;
            } elseif ($t[0] === '}' && $strBrace > 0) {
                $strBrace--;
            }
            continue;
        }
        if ($t[0] === '(') {
            $paren++;
        } elseif ($t[0] === ')') {
            $paren = max(0, $paren - 1);
        }
        $boundary = ($t[0] === ';' && $paren === 0)
            || $t[0] === '{' || $t[0] === '}'
            || in_array($t[0], [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_INLINE_HTML], true);
        if ($boundary) {
            if ($i > $start) {
                $list[] = [$start, $i - 1];
            }
            $start = $i + 1;
            if ($t[0] === '{' || $t[0] === '}') {
                $paren = 0;
            }
        }
    }
    if ($start < $n) {
        $list[] = [$start, $n - 1];
    }
    return $list;
}

// ---------------------------------------------------------------------------
// sql-embed
// ---------------------------------------------------------------------------

const REQUEST_FUNCS = ['getv', 'anyv', 'postv', 'cookiev'];
const REQUEST_VARS = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE'];

// 引数の値が、そのまま文字列として結果に出てこない関数（判定・整数化・エスケープ・ハッシュなど）
const SAFE_FUNCS = [
    'isset', 'empty', 'is_numeric', 'is_array', 'is_string', 'is_int', 'is_scalar', 'ctype_digit',
    'in_array', 'array_key_exists', 'array_search', 'count', 'strlen', 'mb_strlen', 'intval', 'floatval', 'boolval', 'abs',
    'to_intvar', 'get_intvar', 'post_intvar', 'request_intvar', 'escape', 'where_in', 'and_where_in',
    'md5', 'sha1', 'hash', 'crc32', 'password_hash', 'password_verify', 'preg_match', 'strpos', 'stripos',
    'strcmp', 'strcasecmp', 'hash_equals', 'max', 'min', 'round', 'floor', 'ceil', 'number_format',
    'filter_var', 'checkdate', 'strtotime', 'date', 'gmdate', 'mktime', 'is_file', 'is_dir', 'file_exists',
    'hasPermission', 'isCsrfProtectedAction', 'validateCsrfToken', 'HashPassword', 'newCategory', 'checkCategory',
];

// SQL を受け取る DBAPI のメソッド
const DB_METHODS = [
    'exec', 'query', 'rawQuery', 'delete', 'select', 'update', 'insert', 'insert_ignore', 'replace', 'save',
    'getObject', 'getObjectSql', 'getObjects', 'getValue', 'getColumn', 'importSql',
];

// 値をエスケープしないで SQL にする関数（ex_dbapi.php）
const SQL_FUNCS = ['where', 'and_where'];

function lintSqlEmbed($file)
{
    $toks = tokens($file);
    // 変数名 => 汚染したブロックの深さ。代入し直しで汚染を外すのは、その深さ以下（同じブロックか外側）で代入したときだけ。
    // 条件・ループの中だけで代入し直しても、通らない経路では元の値が残るため
    $tainted = [];
    $depths = braceDepths($toks);
    $out = [];
    // 関数の本体は別のスコープ。入るときに外側の状態を退避して空にし、出るときに戻す
    $scopes = functionBodies($toks);
    $stack = [];
    foreach (statements($toks) as [$s, $e]) {
        while ($stack && end($stack)['end'] < $s) {
            $tainted = array_pop($stack)['tainted'];
        }
        while ($scopes && $scopes[0][0] < $s) {
            [$bodyStart, $bodyEnd] = array_shift($scopes);
            if ($bodyEnd < $s) {
                continue;
            }
            $stack[] = ['end' => $bodyEnd, 'tainted' => $tainted];
            $tainted = [];
        }
        // ブロックを出たら、中で汚染した変数は出た先の深さで汚染したものとして扱う
        $depth = $depths[$s];
        foreach ($tainted as $name => $d) {
            if ($d > $depth) {
                $tainted[$name] = $depth;
            }
        }
        // 代入: $var = 式 / $var[...] = 式 / $var .= 式
        $assignTo = null;
        $exprStart = $s;
        $elementAssign = false;
        if (isTok($toks[$s], T_VARIABLE)) {
            $j = $s + 1;
            while ($j <= $e && $toks[$j][0] === '[') {
                $j = matchParen($toks, $j) + 1;
                $elementAssign = true;
            }
            if ($j <= $e && in_array($toks[$j][0], ['=', T_CONCAT_EQUAL, T_COALESCE_EQUAL], true)) {
                $assignTo = $toks[$s][1];
                $exprStart = $j + 1;
                $elementAssign = $elementAssign || $toks[$j][0] !== '=';
            }
        }

        // foreach (式 as $k => $v)
        if (isTok($toks[$s], T_FOREACH)) {
            $as = null;
            for ($j = $s; $j <= $e; $j++) {
                if (isTok($toks[$j], T_AS)) {
                    $as = $j;
                    break;
                }
            }
            if ($as !== null && rawSources($toks, $s + 2, $as - 1, $tainted)) {
                for ($j = $as + 1; $j <= $e; $j++) {
                    if (isTok($toks[$j], T_VARIABLE)) {
                        $tainted[$toks[$j][1]] = $depth + 1;
                    }
                }
            }
        }

        foreach (sinks($toks, $exprStart, $e, $tainted) as $v) {
            $out[] = violation($file, $v[0], 'sql-embed', $v[1]);
        }

        if ($assignTo !== null) {
            $raw = rawSources($toks, $exprStart, $e, $tainted);
            if ($raw) {
                $tainted[$assignTo] = min($depth, $tainted[$assignTo] ?? $depth);
            } elseif (!$elementAssign && isset($tainted[$assignTo]) && $depth <= $tainted[$assignTo]) {
                unset($tainted[$assignTo]);
            }
        }
    }
    return $out;
}

// 各トークンの前の { } の深さ（文字列の中の {$x} は数えない）
function braceDepths($toks)
{
    $depths = [];
    $depth = 0;
    $inString = false;
    $strBrace = 0;
    foreach ($toks as $i => $t) {
        $depths[$i] = $depth;
        if ($t[0] === '"' || $t[0] === T_START_HEREDOC || $t[0] === T_END_HEREDOC) {
            $inString = $t[0] === T_START_HEREDOC ? true : ($t[0] === T_END_HEREDOC ? false : !$inString);
            continue;
        }
        if ($inString) {
            if ($t[0] === T_CURLY_OPEN || $t[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $strBrace++;
            } elseif ($t[0] === '}' && $strBrace > 0) {
                $strBrace--;
            }
            continue;
        }
        if ($t[0] === '{') {
            $depth++;
        } elseif ($t[0] === '}') {
            $depth = max(0, $depth - 1);
        }
    }
    return $depths;
}

// function の本体（{ } の位置）の一覧。開始位置の順
function functionBodies($toks)
{
    $list = [];
    $n = count($toks);
    for ($i = 0; $i < $n; $i++) {
        if ($toks[$i][0] !== T_FUNCTION) {
            continue;
        }
        for ($j = $i + 1; $j < $n && $toks[$j][0] !== '{' && $toks[$j][0] !== ';'; $j++) {
        }
        if ($j < $n && $toks[$j][0] === '{') {
            $list[] = [$j, matchParen($toks, $j)];
        }
    }
    return $list;
}

/**
 * $from..$to の中で、値がそのまま文字列として流れるリクエストの値の位置（トークン番号）の一覧
 */
function rawSources($toks, $from, $to, $tainted)
{
    $raw = [];
    for ($i = $from; $i <= $to; $i++) {
        $end = sourceEnd($toks, $i, $to, $tainted);
        if ($end === null) {
            continue;
        }
        if (!isNeutralized($toks, $i, $end, $from, $to)) {
            $raw[] = $i;
        }
        $i = $end;
    }
    return $raw;
}

// $i がリクエストの値の始まりなら、その終わりの位置を返す
function sourceEnd($toks, $i, $to, $tainted)
{
    $t = $toks[$i];
    if ($t[0] === T_STRING && in_array(strtolower($t[1]), REQUEST_FUNCS, true)
        && isTok($toks[$i + 1] ?? null, '(')
        && !in_array($toks[$i - 1][0] ?? null, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
        return min(matchParen($toks, $i + 1), $to);
    }
    if ($t[0] === T_VARIABLE && (in_array($t[1], REQUEST_VARS, true) || isset($tainted[$t[1]]))) {
        $j = $i;
        while ($j + 1 <= $to && $toks[$j + 1][0] === '[') {
            $j = matchParen($toks, $j + 1);
        }
        return $j;
    }
    return null;
}

// 判定・整数化・エスケープなどを通っていて、値がそのまま流れないか
function isNeutralized($toks, $i, $end, $from, $to)
{
    $prev = $toks[$i - 1] ?? null;
    $next = $toks[$end + 1] ?? null;
    if ($prev && in_array($prev[0], [T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST, '!', T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL,
            T_IS_NOT_IDENTICAL, '<', '>', T_IS_SMALLER_OR_EQUAL, T_IS_GREATER_OR_EQUAL, '+', '-', '*', '/', '%', T_ISSET, T_EMPTY], true)) {
        return true;
    }
    if ($next && in_array($next[0], [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL, '<', '>',
            T_IS_SMALLER_OR_EQUAL, T_IS_GREATER_OR_EQUAL, '+', '-', '*', '/', '%', T_INSTANCEOF], true)) {
        return true;
    }
    // 三項演算子の条件（?: は値が流れる）
    if ($next && $next[0] === '?' && !isTok($toks[$end + 2] ?? null, ':')) {
        return true;
    }
    // 配列の添字として使う（$a[getv('x')]）
    if ($prev && $prev[0] === '[' && $next && $next[0] === ']') {
        return true;
    }
    // 囲んでいる括弧をたどる
    $depth = 0;
    for ($j = $i - 1; $j >= $from; $j--) {
        $s = $toks[$j][0];
        if ($s === ')' || $s === ']') {
            $depth++;
            continue;
        }
        if ($s === '(' || $s === '[') {
            if ($depth > 0) {
                $depth--;
                continue;
            }
            $before = $toks[$j - 1] ?? null;
            if ($s === '(' && $before) {
                if ($before[0] === T_STRING && in_array(strtolower($before[1]), array_map('strtolower', SAFE_FUNCS), true)) {
                    return true;
                }
                if (in_array($before[0], [T_ISSET, T_EMPTY, T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST], true)) {
                    return true;
                }
                // DB の呼び出しの引数（戻り値は結果セットなどで、値は流れない。引数そのものは sinks() で見る）
                if ($before[0] === T_STRING && in_array($before[1], DB_METHODS, true)
                    && isTok($toks[$j - 2] ?? null, T_OBJECT_OPERATOR) && isDbObject($toks, $j - 3)) {
                    return true;
                }
            }
            // 括弧の外の (int)(...) や、比較の中も同じ扱い
            $close = matchParen($toks, $j);
            if ($s === '(' && $before && in_array($before[0], [T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST, '!'], true)) {
                return true;
            }
            $after = $toks[$close + 1] ?? null;
            if ($s === '(' && $after && in_array($after[0], [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL], true)
                && !($before && in_array($before[0], [T_STRING, T_VARIABLE], true))) {
                return true;
            }
            // 比較や判定の部分式（例: (postv('a') == '1') ?）は、関数の引数でない括弧で比較が中にあれば値が流れない
            if ($s === '(' && !($before && in_array($before[0], [T_STRING, T_VARIABLE, T_ARRAY, T_LIST, T_IF, T_ELSEIF, T_WHILE, T_SWITCH, T_FOREACH, T_FOR], true))
                && hasTopComparison($toks, $j + 1, $close - 1)) {
                return true;
            }
            if ($s === '(' && $before && in_array($before[0], [T_IF, T_ELSEIF, T_WHILE, T_SWITCH], true)) {
                return true;
            }
        }
    }
    return false;
}

function hasTopComparison($toks, $from, $to)
{
    $depth = 0;
    for ($j = $from; $j <= $to; $j++) {
        $s = $toks[$j][0];
        if ($s === '(' || $s === '[') {
            $depth++;
        } elseif ($s === ')' || $s === ']') {
            $depth--;
        } elseif ($depth === 0 && in_array($s, [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL, '<', '>',
                T_IS_SMALLER_OR_EQUAL, T_IS_GREATER_OR_EQUAL, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR], true)) {
            return true;
        }
    }
    return false;
}

/**
 * 文の中で、リクエストの値が SQL に入る箇所 [[行, メッセージ], ...]
 */
function sinks($toks, $from, $to, $tainted)
{
    $out = [];
    for ($i = $from; $i <= $to; $i++) {
        $t = $toks[$i];
        // DB の呼び出し・where() の引数
        $callee = null;
        if ($t[0] === T_STRING && isTok($toks[$i + 1] ?? null, '(')) {
            $name = $t[1];
            if (isTok($toks[$i - 1] ?? null, T_OBJECT_OPERATOR) && in_array($name, DB_METHODS, true) && isDbObject($toks, $i - 2)) {
                $callee = "db()->{$name}()";
            } elseif (!in_array($toks[$i - 1][0] ?? null, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)
                && in_array(strtolower($name), SQL_FUNCS, true)) {
                $callee = "{$name}()";
            }
        }
        if ($callee !== null) {
            $close = matchParen($toks, $i + 1);
            foreach (rawSources($toks, $i + 2, $close - 1, $tainted) as $p) {
                $out[] = [$toks[$p][2], sprintf('%s へ、エスケープ・整数化していないリクエストの値 %s を渡している', $callee, sourceLabel($toks, $p))];
            }
            continue;
        }
        // sprintf の書式が SQL
        if ($t[0] === T_STRING && in_array(strtolower($t[1]), ['sprintf', 'vsprintf'], true) && isTok($toks[$i + 1] ?? null, '(')
            && isTok($toks[$i + 2] ?? null, T_CONSTANT_ENCAPSED_STRING) && looksSql(trim($toks[$i + 2][1], '\'"'), true)) {
            $close = matchParen($toks, $i + 1);
            foreach (rawSources($toks, $i + 3, $close - 1, $tainted) as $p) {
                $out[] = [$toks[$p][2], sprintf('SQL の文字列へ、エスケープ・整数化していないリクエストの値 %s を埋め込んでいる', sourceLabel($toks, $p))];
            }
        }
    }
    // 文字列の連結・展開で、直前の文字列が SQL
    foreach (rawSources($toks, $from, $to, $tainted) as $p) {
        $before = literalBefore($toks, $p, $from);
        if ($before !== null && looksSql($before, false)) {
            $out[] = [$toks[$p][2], sprintf('SQL の文字列へ、エスケープ・整数化していないリクエストの値 %s を埋め込んでいる', sourceLabel($toks, $p))];
        }
    }
    return $out;
}

// db() / ->db / $modx->db / $this->db のどれかか（$i は -> の左のトークン）
function isDbObject($toks, $i)
{
    $t = $toks[$i] ?? null;
    if ($t === null) {
        return false;
    }
    if ($t[0] === ')' && isTok($toks[$i - 1] ?? null, '(') && isTok($toks[$i - 2] ?? null, T_STRING, 'db')) {
        return true;
    }
    if ($t[0] === T_STRING && $t[1] === 'db' && isTok($toks[$i - 1] ?? null, T_OBJECT_OPERATOR)) {
        return true;
    }
    return false;
}

function sourceLabel($toks, $p)
{
    $t = $toks[$p];
    if ($t[0] === T_STRING) {
        $arg = $toks[$p + 2][1] ?? '';
        return "{$t[1]}({$arg})";
    }
    return $t[1];
}

// 連結（.）か文字列の展開で、値の直前にある文字列の部分
function literalBefore($toks, $p, $from)
{
    $prev = $toks[$p - 1] ?? null;
    if ($prev === null) {
        return null;
    }
    // "...{$x}" / "...$x"
    if ($prev[0] === T_CURLY_OPEN || $prev[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
        $prev = $toks[$p - 2] ?? null;
        return $prev && $prev[0] === T_ENCAPSED_AND_WHITESPACE ? $prev[1] : ($prev && $prev[0] === '"' ? '' : null);
    }
    if ($prev[0] === T_ENCAPSED_AND_WHITESPACE) {
        return $prev[1];
    }
    // '...' . $x
    if ($prev[0] === '.' && $p - 2 >= $from) {
        $lit = $toks[$p - 2];
        if ($lit[0] === T_CONSTANT_ENCAPSED_STRING) {
            return substr($lit[1], 1, -1);
        }
        if ($lit[0] === '"' && ($toks[$p - 3][0] ?? null) === T_ENCAPSED_AND_WHITESPACE) {
            return $toks[$p - 3][1];
        }
    }
    return null;
}

/**
 * 値を埋め込む直前の文字列が SQL らしいか。
 * 「列 = '」「列 IN (」「列 LIKE '」のような形か、大文字の SQL のキーワードを含むもの。
 * URL のクエリ（?a= / &id=）は対象外にする
 */
function looksSql($text, $isFormat)
{
    if ($isFormat) {
        return (bool)preg_match('/(^|[\s(,`.])`?[A-Za-z_][\w`.]*`?\s*(=|<>|!=|<=|>=|<|>|\sLIKE|\sIN\s*\()\s*[\'"(]?\s*%s/i', $text)
            || (bool)preg_match('/\b(SELECT|UPDATE|INSERT INTO|DELETE FROM|WHERE)\b[^%]*%s/', $text);
    }
    return (bool)preg_match('/(^|[\s(,`.])`?[A-Za-z_][\w`.]*`?\s*(=|<>|!=|<=|>=|<|>|\sLIKE|\sIN\s*\()\s*[\'"(]\s*$/i', $text)
        || (bool)preg_match('/(^|[\s(,`])`?[A-Za-z_][\w`.]*`?\s*(=|<>|!=|<=|>=|\sIN\s*\()\s*$/i', $text) && !preg_match('/[?&]\w*=\s*$|https?:|index\.php|Location:/i', $text)
        || (bool)preg_match('/\b(SELECT|UPDATE|INSERT INTO|DELETE FROM|WHERE|ORDER BY|LIMIT)\b/', $text);
}

// ---------------------------------------------------------------------------
// js-escape
// ---------------------------------------------------------------------------

function lintJsEscape($file)
{
    $toks = tokens($file);
    $out = [];
    $inScript = false;
    $echoBlock = false;
    $jsUnsafe = []; // addslashes() / escape() の結果を入れた変数
    $jsUnsafeAny = []; // ファイルのどこかで addslashes() / escape() の結果を入れた変数（HTML の部分で使う）
    // PHP の文字列で <script> を組み立てる文
    foreach (statements($toks) as [$s, $e]) {
        $hasScript = false;
        for ($i = $s; $i <= $e; $i++) {
            if (in_array($toks[$i][0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) && stripos($toks[$i][1], '<script') !== false) {
                $hasScript = true;
                break;
            }
        }
        $assignTo = (isTok($toks[$s], T_VARIABLE) && in_array($toks[$s + 1][0] ?? null, ['=', T_CONCAT_EQUAL], true)) ? $toks[$s][1] : null;
        $unsafeHere = false;
        for ($i = $s; $i <= $e; $i++) {
            if (isJsUnsafeCall($toks, $i)) {
                $unsafeHere = true;
                if ($hasScript) {
                    $out[] = violation($file, $toks[$i][2], 'js-escape', '<script> へ ' . $toks[$i][1] . '() で値を埋め込んでいる（js_literal() を使う）');
                }
            }
            if ($hasScript && $toks[$i][0] === T_VARIABLE && isset($jsUnsafe[$toks[$i][1]]) && $i !== $s) {
                $out[] = violation($file, $toks[$i][2], 'js-escape', '<script> へ、addslashes() / escape() した ' . $toks[$i][1] . ' を埋め込んでいる（js_literal() を使う）');
            }
        }
        if ($assignTo !== null) {
            if ($unsafeHere) {
                $jsUnsafe[$assignTo] = true;
                $jsUnsafeAny[$assignTo] = true;
            } elseif ($toks[$s + 1][0] === '=') {
                unset($jsUnsafe[$assignTo]);
            }
        }
    }
    // HTML の部分の <script> の中
    $n = count($toks);
    for ($i = 0; $i < $n; $i++) {
        $t = $toks[$i];
        if ($t[0] === T_INLINE_HTML) {
            $inScript = scriptStateAfter($t[1], $inScript);
            continue;
        }
        if ($t[0] === T_OPEN_TAG_WITH_ECHO || $t[0] === T_OPEN_TAG) {
            $echoBlock = $inScript;
            continue;
        }
        if ($t[0] === T_CLOSE_TAG) {
            $echoBlock = false;
            continue;
        }
        if ($echoBlock && isJsUnsafeCall($toks, $i)) {
            $out[] = violation($file, $t[2], 'js-escape', '<script> の中へ ' . $t[1] . '() で値を埋め込んでいる（js_literal() を使う）');
        }
        if ($echoBlock && $t[0] === T_VARIABLE && isset($jsUnsafeAny[$t[1]])) {
            $out[] = violation($file, $t[2], 'js-escape', '<script> の中へ、addslashes() / escape() した ' . $t[1] . ' を埋め込んでいる（js_literal() を使う）');
        }
    }
    return $out;
}

function isJsUnsafeCall($toks, $i)
{
    $t = $toks[$i];
    if ($t[0] !== T_STRING || !isTok($toks[$i + 1] ?? null, '(')) {
        return false;
    }
    if (strcasecmp($t[1], 'addslashes') === 0 && !in_array($toks[$i - 1][0] ?? null, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
        return true;
    }
    return $t[1] === 'escape' && isTok($toks[$i - 1] ?? null, T_OBJECT_OPERATOR) && isDbObject($toks, $i - 2);
}

// HTML の断片を読んだあと、<script> の中にいるか
function scriptStateAfter($html, $inScript)
{
    $open = strripos($html, '<script');
    $close = strripos($html, '</script');
    if ($open === false && $close === false) {
        return $inScript;
    }
    if ($close === false) {
        return true;
    }
    if ($open === false) {
        return false;
    }
    return $open > $close;
}

// ---------------------------------------------------------------------------
// csrf-action
// ---------------------------------------------------------------------------

function lintCsrfActions($root, $excluded)
{
    $indexFile = $root . '/manager/index.php';
    $toks = tokens($indexFile);
    $protected = csrfProtectedActionList($root . '/manager/includes/csrf_token.php');
    $out = [];
    $pending = [];
    $n = count($toks);
    for ($i = 0; $i < $n; $i++) {
        $t = $toks[$i];
        if ($t[0] === T_CASE && isTok($toks[$i + 1] ?? null, T_LNUMBER)) {
            $pending[(int)$toks[$i + 1][1]] = $toks[$i + 1][2];
            continue;
        }
        if ($t[0] === T_BREAK) {
            $pending = [];
            continue;
        }
        if ($t[0] === T_VARIABLE && $t[1] === '$prc_path' && $pending) {
            foreach ($pending as $action => $line) {
                if (!isset($protected[$action]) && !isset($excluded[$action])) {
                    $out[] = violation($indexFile, $line, 'csrf-action', sprintf(
                        'a=%d は processor を読み込むが、csrfProtectedActions() の一覧にも、security-lint.php の除外リストにもない',
                        $action
                    ));
                }
            }
            $pending = [];
        }
    }
    return $out;
}

// csrfProtectedActions() が返す配列のキー（読み込みはせず、字句から読む）
function csrfProtectedActionList($file)
{
    $toks = tokens($file);
    $n = count($toks);
    $keys = [];
    for ($i = 0; $i < $n; $i++) {
        if (!isTok($toks[$i], T_FUNCTION) || !isTok($toks[$i + 1] ?? null, T_STRING, 'csrfProtectedActions')) {
            continue;
        }
        for ($j = $i; $j < $n && !isTok($toks[$j], T_RETURN); $j++) {
        }
        $open = $j + 1;
        $close = matchParen($toks, $open);
        $depth = 0;
        for ($k = $open + 1; $k < $close; $k++) {
            $s = $toks[$k][0];
            if ($s === '[' || $s === '(') {
                $depth++;
            } elseif ($s === ']' || $s === ')') {
                $depth--;
            } elseif ($depth === 0 && $s === T_LNUMBER && isTok($toks[$k + 1] ?? null, T_DOUBLE_ARROW)) {
                $keys[(int)$toks[$k][1]] = true;
            }
        }
        break;
    }
    if (!$keys) {
        fwrite(STDERR, "csrfProtectedActions() の一覧を読み取れなかった\n");
        exit(2);
    }
    return $keys;
}

main($root, $csrfExcludedActions);
