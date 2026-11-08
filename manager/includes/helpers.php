<?php
/**
 * @return DocumentParser
 */
function evo()
{
    global $modx;
    if (!$modx) {
        return null;
    }
    return $modx;
}

/**
 * @return DBAPI
 */
function db()
{
    return evo()->db;
}

/**
 * @return ManagerAPI
 */
function manager()
{
    return evo()->manager;
}

function hasPermission($key = null)
{
    return evo()->hasPermission($key);
}

function config($key = null, $default = null)
{
    return evo()->config($key, $default);
}

function docid()
{
    if(event()->name === 'OnDocFormSave') {
        return globalv('id');
    }
    return evo()->documentIdentifier;
}

function base_path()
{
    if (defined('MODX_BASE_PATH')) {
        return constant('MODX_BASE_PATH');
    }
    exit('base_path not defined.');
}

function lang($key, $default = null)
{
    global $_lang;
    if (!$_lang) {
        $_lang = evo()->getLexicon();
    }
    return array_get($_lang, $key, $default ? $default : $key);
}

function style($key)
{
    global $_style;
    return array_get($_style, $key);
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }
}

/**
 * <script> の中へ値を埋め込むための、JS の文字列リテラル（引用符付き）を返す。
 * < > ' " & を \uXXXX にするので、</script> や引用符で構文を壊さない。
 * 不正な UTF-8 は置き換え、それでも失敗したら空文字列のリテラルを返す。
 * 値を JS へ埋め込むときは、addslashes() や db()->escape() ではなく必ずこれを使う。
 *
 * @param mixed $value
 * @return string
 */
function js_literal($value): string
{
    $json = json_encode(
        (string)$value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    return $json === false ? '""' : $json;
}

/**
 * URL のクエリにある CSRF トークンの値を伏せる（ログに URL を残すとき用）。
 * PHP は csrf%5Ftoken や csrf.token のような名前も csrf_token として受け取るため、
 * 名前は parse_str() に通して PHP と同じ解釈で判定する。
 *
 * @param mixed $uri
 * @return string
 */
function mask_csrf_token_in_uri($uri): string
{
    $uri = (string)$uri;
    $qpos = strpos($uri, '?');
    if ($qpos === false) {
        return $uri;
    }
    $query = substr($uri, $qpos + 1);
    $fragment = '';
    $hpos = strpos($query, '#');
    if ($hpos !== false) {
        $fragment = substr($query, $hpos);
        $query = substr($query, 0, $hpos);
    }
    $pairs = explode('&', $query);
    foreach ($pairs as $i => $pair) {
        $eq = strpos($pair, '=');
        if ($eq === false) {
            continue;
        }
        parse_str(substr($pair, 0, $eq) . '=1', $parsed);
        if (array_key_exists('csrf_token', $parsed)) {
            $pairs[$i] = substr($pair, 0, $eq) . '=***';
        }
    }
    return substr($uri, 0, $qpos + 1) . implode('&', $pairs) . $fragment;
}

function hsc($string = '', $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, $encode = null, $double_encode = true)
{
    if ($string === null) {
        return '';
    }

    if (is_object($string)) {
        return $string;
    }

    if (is_array($string)) {
        foreach ($string as $i => $v) {
            $string[$i] = hsc($v, $flags, $encode, $double_encode);
        }

        return $string;
    }

    if ($encode === null) {
        $encode = 'utf-8';
    }

    if (!is_string($string)) {
        if (is_bool($string)) {
            $string = $string ? '1' : '';
        } else {
            $string = (string) $string;
        }
    }

    return htmlspecialchars($string, $flags, $encode, $double_encode);
}

/**
 * 要素（テンプレート変数など）の説明を HTML として出すための変換。
 * 要素の一覧など、説明を並べて出すとき用。hsc() のあと、属性なしで対になった b・strong タグだけを元に戻す。
 * 対の取れない開きタグ・閉じタグや、ほかのタグは文字として出る。
 * テンプレート変数の入力欄に添える説明は tv_input_description_html() を使う。
 *
 * @param string|null $text 説明（DB の NULL は空文字として扱う）
 * @return string
 */
function element_description_html(?string $text): string
{
    $html = hsc((string) $text);
    for ($i = 0; $i < 10; $i++) {
        $replaced = preg_replace(
            '@&lt;(b|strong)&gt;(.*?)&lt;/\1&gt;@is',
            '<$1>$2</$1>',
            $html
        );
        if ($replaced === $html) {
            break;
        }
        $html = $replaced;
    }
    return $html;
}

/**
 * テンプレート変数の入力欄に添える説明を出す（リソースの編集画面・QuickManager・DocManager）。
 * 説明は入力の手引きとして、テンプレート変数の編集者が書いた HTML をそのまま出す。
 * テンプレート変数を編集できる人は @EVAL で PHP をもともと実行できるので、エスケープしない。
 * 説明を並べて出す画面（要素の一覧など）は element_description_html() を使う。
 *
 * @param string|null $text 説明（DB の NULL は空文字として扱う）
 * @return string
 */
function tv_input_description_html(?string $text): string
{
    return (string) $text;
}

function manager_safe_mode_can_toggle()
{
    return defined('IN_MANAGER_MODE')
        && IN_MANAGER_MODE === 'true'
        && evo()
        && evo()->isLoggedin()
        && (
            evo()->hasPermission('save_plugin')
            || evo()->hasPermission('edit_plugin')
            || evo()->hasPermission('delete_plugin')
        );
}

function manager_apply_safe_mode_request()
{
    $requested = postv('manager_safe_mode');
    if (
        !is_post()
        || $requested === null
        || anyv('a') !== '1'
        || anyv('f') !== 'menu'
        || !manager_safe_mode_can_toggle()
    ) {
        return false;
    }

    checkCsrfToken();
    sessionv('*safeMode', $requested === '1' ? 1 : 0);
    return true;
}

function manager_safe_mode_toggle_value()
{
    $isSafeMode = sessionv('safeMode') == 1;
    return $isSafeMode ? '0' : '1';
}

// 切り替えたあとは管理画面のトップへ移すので、シェル（shell.js）に横取りさせず画面全体で送る（data-no-ajax）。
// エラーの表示は #mainPane の断片や、ファイルブラウザなどの iframe の中に出ることがあるため、送り先は最上位の画面にする（target="_top"）
function manager_safe_mode_toggle_form($label, $className = '', $style = '')
{
    $classAttr = $className !== '' ? ' class="' . hsc($className) . '"' : '';
    $styleAttr = $style !== '' ? ' style="' . hsc($style) . '"' : '';

    return sprintf(
        '<form method="post" action="%s" style="display:inline;" target="_top" data-no-ajax>'
        . '<input type="hidden" name="a" value="1">'
        . '<input type="hidden" name="f" value="menu">'
        . '<input type="hidden" name="manager_safe_mode" value="%s">'
        . '<input type="hidden" name="manager_safe_mode_redirect" value="1">'
        . '%s'
        . '<button type="submit"%s%s>%s</button>'
        . '</form>',
        hsc(MODX_MANAGER_URL . 'index.php'),
        hsc(manager_safe_mode_toggle_value()),
        csrfTokenField(),
        $classAttr,
        $styleAttr,
        hsc($label)
    );
}

// ナビにはセーフモード中だけ「解除」を出す。セーフモードに入る入口は、エラーの表示のボタン（manager_safe_mode_button_html()）
function manager_safe_mode_nav_item_html()
{
    if (sessionv('safeMode') != 1 || !manager_safe_mode_can_toggle()) {
        return '';
    }

    return sprintf(
        '<span class="supplementalNav__item supplementalNav__item--safe-mode">%s</span>',
        manager_safe_mode_toggle_form(
            lang('manager_safe_mode_nav_disable'),
            'supplementalNav__link',
            'background:none;border:0;padding:0;color:inherit;cursor:pointer;font:inherit;'
        )
    );
}

// 管理画面のどの画面のエラーの表示にも出す（ペインとして読み込まれた画面を含む）
function manager_safe_mode_button_html()
{
    if (!manager_safe_mode_can_toggle()) {
        return '';
    }

    $isSafeMode = sessionv('safeMode') == 1;
    $label = $isSafeMode ? lang('manager_safe_mode_button_disable') : lang('manager_safe_mode_button_enable');
    $background = $isSafeMode ? '#333' : '#c62828';

    // 上部のメニュー（ログアウトなど）に重ならないよう、右下に出す
    return sprintf(
        '<div style="position:fixed;bottom:12px;right:12px;z-index:2147483647;">'
        . '%s</div>',
        manager_safe_mode_toggle_form(
            $label,
            '',
            'display:inline-block;padding:9px 14px;border-radius:4px;'
            . 'background:' . $background . ';color:#fff;font:14px/1.2 Arial,sans-serif;text-decoration:none;'
            . 'box-shadow:0 2px 8px rgba(0,0,0,.25);cursor:pointer;border:0;'
        )
    );
}

function parseText($tpl, $ph, $left = '[+', $right = '+]', $execModifier = false)
{
    if (evo()) {
        return evo()->parseText($tpl, $ph, $left, $right, $execModifier);
    }
    foreach ($ph as $k => $v) {
        $k = sprintf('[+%s+]', $k);
        $tpl = str_replace($k, $v, $tpl);
    }
    return $tpl;
}

/**
 * HTML タグを組み立てる。属性値も中身もエスケープしない。
 * DB や設定の値は、呼び出し側で hsc() してから渡す。中身には HTML を渡せる。
 */
function html_tag($tag_name, $attrib = [], $content = null)
{
    return evo()->html_tag($tag_name, $attrib, $content);
}

function input_text_tag($props = [])
{
    $props['type'] = 'text';
    $props['maxlength'] = array_get($props, 'maxlength', 255);
    $props['class'] = array_get($props, 'class', 'inputBox');
    foreach ($props as $k => $v) {
        if ($v === false) {
            unset($props[$k]);
        }
    }
    return evo()->html_tag('input', $props);
}

function textarea_tag($props = [], $content='')
{
    $props['class'] = array_get($props, 'class', 'inputBox');
    return evo()->html_tag('textarea', $props, $content);
}

function select_tag($props = [], $options='')
{
    $props['class'] = array_get($props, 'class', 'inputBox');
    if (is_array($options)) {
        $options = implode("\n", $options);
    }
    return evo()->html_tag('select', $props, $options);
}

function img_tag($src, $props = [])
{
    $props['src'] = $src;
    return evo()->html_tag('img', $props);
}

function alert()
{
    static $e = null;
    if ($e) {
        return $e;
    }
    include_once(__DIR__ . '/error.class.inc.php');
    $e = new errorHandler;
    return $e;
}

/**
 * ユーザーがブロック中かを判定する（管理ユーザー・Web ユーザーで共通）
 *
 * 編集画面の「ブロック中」の表示と、ログインの可否は、どちらもこれで決める。
 * blocked が 1、blockeduntil が未来、blockedafter が過去のどれかでブロック中とみなす。
 * 失敗回数による一時ロックは blockeduntil だけで表すので、期限が過ぎれば外れる。
 *
 * 日時はタイムスタンプで受ける（差し戻しの値は、呼び出し側で ManagerAPI::restoredDateValue() で戻す）。
 * 空文字や、解釈できずに文字列のまま差し戻した日付などの数値でない値は 0 として扱う。
 *
 * @param array $row blocked / blockeduntil / blockedafter を持つ配列
 */
function isUserBlocked($row)
{
    if (!is_array($row)) {
        return false;
    }
    $int = static function ($key) use ($row) {
        return isset($row[$key]) && is_numeric($row[$key]) ? (int)$row[$key] : 0;
    };
    $now = time();
    $until = $int('blockeduntil');
    $after = $int('blockedafter');

    return $int('blocked') === 1
        || ($until !== 0 && $until > $now)
        || ($after !== 0 && $after < $now);
}

/**
 * 失敗回数による一時ロックかを判定する（期限が過ぎたものも含む）
 *
 * 一時ロックは、失敗回数が failed_login_attempts 以上のときの blockeduntil で表す（blocked は使わない）。
 * 管理者が付けた blockeduntil と同じ列なので、失敗回数で見分ける。
 *
 * @param array $row blockeduntil / failedlogincount を持つ配列
 */
function isTemporaryLoginLock($row)
{
    if (!is_array($row)) {
        return false;
    }
    $until = isset($row['blockeduntil']) && is_scalar($row['blockeduntil']) ? (int)$row['blockeduntil'] : 0;
    $failed = isset($row['failedlogincount']) && is_scalar($row['failedlogincount']) ? (int)$row['failedlogincount'] : 0;

    return $until !== 0 && (int)config('failed_login_attempts', 0) <= $failed;
}

/**
 * 以前の版の一時ロックの形（blocked も 1 にしていた）の SQL の条件
 *
 * 以前の版は、失敗回数が failed_login_attempts に達すると blockeduntil に加えて blocked も 1 にしていた。
 * この形の行を外すマイグレーション（20261107_180000_unblock_legacy_temporary_login_locks）と、
 * マイグレーションの前のログイン（manager/processors/login.processor.functions.php）で同じ条件を使う。
 * 以前の版は失敗の直後に書くので、失敗回数は 1 以上。上限が 0 や空でも、期限のある blocked=1 をすべて対象にしない
 *
 * @param mixed $failedLoginAttempts グローバル設定 failed_login_attempts の値
 */
function legacyTemporaryLoginLockSqlCondition($failedLoginAttempts)
{
    $limit = max(1, (int)$failedLoginAttempts);
    return "blocked=1 AND blockeduntil<>0 AND failedlogincount>={$limit}";
}

/**
 * 失敗回数による一時ロックを除いて、ブロック中かを判定する（管理者が付けたブロック）
 *
 * パスワードの再設定は、これが真のユーザーには受け付けない。再設定で外してよいのは一時ロックだけ。
 *
 * @param array $row blocked / blockeduntil / blockedafter / failedlogincount を持つ配列
 */
function isUserBlockedByAdmin($row)
{
    if (!is_array($row)) {
        return false;
    }
    if (isTemporaryLoginLock($row)) {
        $row['blockeduntil'] = 0;
    }
    return isUserBlocked($row);
}

/**
 * isUserBlocked() と同じ規則の SQL の条件（ユーザーの一覧で、ブロック中の表示と並び順に使う）
 *
 * @param string $alias 属性の表の別名（例: 'mua'）。空なら列名だけ
 */
function userBlockedSqlCondition($alias = '')
{
    $col = $alias !== '' ? "{$alias}." : '';
    $now = time();
    return "({$col}blocked=1"
        . " OR {$col}blockeduntil>{$now}"
        . " OR ({$col}blockedafter<>0 AND {$col}blockedafter<{$now}))";
}

function array_get($array, $key = null, $default = null, $validate = null)
{
    if ($key === null || trim($key) == '') {
        return $array;
    }

    if (isset($array[$key])) {
        if ($validate && is_callable($validate) && !$validate($array[$key])) {
            return $default;
        }
        return $array[$key];
    }
    $segments = explode('.', $key);
    foreach ($segments as $segment) {
        if (!is_array($array) || !isset($array[$segment])) {
            return $default;
        }
        $array = $array[$segment];
    }
    if ($validate && is_callable($validate) && !$validate($array)) {
        return $default;
    }
    return $array;
}

function array_set(&$array, $key, $value)
{
    if (is_null($key)) {
        return $array = $value;
    }

    $keys = explode('.', $key);
    foreach ($keys as $i => $key) {
        if (count($keys) === 1) {
            break;
        }
        unset($keys[$i]);
        if (!isset($array[$key]) || !is_array($array[$key])) {
            $array[$key] = [];
        }

        $array = &$array[$key];
    }

    $array[array_shift($keys)] = $value;

    return $array;
}

// リクエスト値の取り出し方（生の値と型つき、受け取り元の選び方）は assets/docs/request-input.md を参照。
// id は (int) で読まない。(int) は "5abc" を 5 にしてしまうので、*_intvar() で読む（正の整数なら int、それ以外は 0）

// GET の値だけを id として読む
function get_intvar($key)
{
    return to_intvar(getv($key, 0));
}

// POST の値だけを id として読む
function post_intvar($key)
{
    return to_intvar(postv($key, 0));
}

// GET と POST の両方（anyv()）から id として読む。両方で呼ばれる処理だけに使う
function request_intvar($key)
{
    return to_intvar(anyv($key, 0));
}

// 0 以上の整数として読む（GET / POST / 両方）。読めないときは $default（既定は null。0 と区別できる）。
// 先頭のゼロは受ける（'02' は 2）。*_intvar()（正の整数だけ、読めなければ 0）と違い、0 も値になる
function get_uintvar($key, $default = null)
{
    return to_uintvar(getv($key), $default);
}

function post_uintvar($key, $default = null)
{
    return to_uintvar(postv($key), $default);
}

function request_uintvar($key, $default = null)
{
    return to_uintvar(anyv($key), $default);
}

// id が整数として読めないときは、エラーで止める（新規作成など別の処理として続けない）。
// $required が false（新規作成の画面など）なら、id が無いときと 0 のときは通す。
// 編集の画面のように id が必ず要るときは $required を true にする
function abort_if_invalid_id($key = 'id', $required = false)
{
    $value = anyv($key);
    if (!$required && ($value === null || $value === '' || $value === '0')) {
        return;
    }
    if (!to_intvar($value)) {
        alert()->setError(2);
        alert()->dumpError();
    }
}

function to_intvar($value)
{
    if (is_int($value)) {
        return $value > 0 ? $value : 0;
    }
    if (!is_string($value) || !preg_match('@\A[1-9][0-9]{0,18}\z@', $value)) {
        return 0;
    }
    $int = (int) $value;
    return (string) $int === $value ? $int : 0;
}

// 0 以上の int はそのまま。数字だけの文字列（先頭のゼロを除いて PHP_INT_MAX 以下）は int。それ以外は $default
function to_uintvar($value, $default = null)
{
    if (is_int($value)) {
        return $value >= 0 ? $value : $default;
    }
    if (!is_string($value) || !preg_match('@\A[0-9]+\z@', $value)) {
        return $default;
    }
    $digits = ltrim($value, '0');
    if ($digits === '') {
        return 0;
    }
    $int = (int) $digits;
    return (string) $int === $digits ? $int : $default;
}

// 空でない文字列ならそのまま、それ以外（null・空文字・配列・数値など）は $default
function to_strvar($value, $default = '')
{
    return is_string($value) && $value !== '' ? $value : $default;
}

// 文字列ならそのまま（空・空白だけでも加工しない）、それ以外（null・配列・数値など）は $default
function to_textvar($value, $default = null)
{
    return is_string($value) ? $value : $default;
}

// bool はそのまま。1・0 の int と、'1'・'on'・'true'・'yes' / '0'・'off'・'false'・'no'（前後の空白と大文字小文字は無視）を真偽値にする。それ以外は $default
function to_boolvar($value, $default = false)
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === 1) {
        return true;
    }
    if ($value === 0) {
        return false;
    }
    if (!is_string($value)) {
        return $default;
    }
    $value = strtolower(trim($value));
    if (in_array($value, ['1', 'on', 'true', 'yes'], true)) {
        return true;
    }
    if (in_array($value, ['0', 'off', 'false', 'no'], true)) {
        return false;
    }
    return $default;
}

// $value（文字列か int）が $choices のいずれかと文字列として一致したら、その $choices の要素（元の型）を返す。それ以外は $default
function to_choicevar($value, array $choices, $default)
{
    if (!is_string($value) && !is_int($value)) {
        return $default;
    }
    $value = (string) $value;
    foreach ($choices as $choice) {
        if ((is_string($choice) || is_int($choice)) && (string) $choice === $value) {
            return $choice;
        }
    }
    return $default;
}

// 配列、またはカンマ区切りの文字列（区切りの前後の空白は除く）の各要素を id として読み、0 と重複を除いた int の配列にする。それ以外は []
function to_idsvar($value)
{
    if (is_string($value)) {
        $value = array_map('trim', explode(',', $value));
    }
    if (!is_array($value)) {
        return [];
    }
    $ids = [];
    foreach ($value as $v) {
        $id = to_intvar($v);
        if ($id) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

// 文字列として読む（GET / POST / 両方）
function get_strvar($key, $default = '')
{
    return to_strvar(getv($key), $default);
}

function post_strvar($key, $default = '')
{
    return to_strvar(postv($key), $default);
}

function request_strvar($key, $default = '')
{
    return to_strvar(anyv($key), $default);
}

// 文字列として読む。空や空白もそのまま（GET / POST / 両方）
function get_textvar($key, $default = null)
{
    return to_textvar(getv($key), $default);
}

function post_textvar($key, $default = null)
{
    return to_textvar(postv($key), $default);
}

function request_textvar($key, $default = null)
{
    return to_textvar(anyv($key), $default);
}

// 真偽値として読む（GET / POST / 両方）
function get_boolvar($key, $default = false)
{
    return to_boolvar(getv($key), $default);
}

function post_boolvar($key, $default = false)
{
    return to_boolvar(postv($key), $default);
}

function request_boolvar($key, $default = false)
{
    return to_boolvar(anyv($key), $default);
}

// 選択肢の中の値として読む（GET / POST / 両方）
function get_choicevar($key, array $choices, $default)
{
    return to_choicevar(getv($key), $choices, $default);
}

function post_choicevar($key, array $choices, $default)
{
    return to_choicevar(postv($key), $choices, $default);
}

function request_choicevar($key, array $choices, $default)
{
    return to_choicevar(anyv($key), $choices, $default);
}

// id の配列として読む（GET / POST / 両方）
function get_idsvar($key)
{
    return to_idsvar(getv($key));
}

function post_idsvar($key)
{
    return to_idsvar(postv($key));
}

function request_idsvar($key)
{
    return to_idsvar(anyv($key));
}

/**
 * @return SystemEvent
 */
function event()
{
    return evo()->event;
}

function parent($docid)
{
    if (evo()) {
        return evo()->getParentID($docid ? $docid : docid());
    }
}

function uparent($docid = null, $top = 0)
{
    if (evo()) {
        return evo()->getUltimateParentId($docid ? $docid : docid(), $top);
    }
}

function exprintf()
{
    $args = func_get_args();
    $args[0] = str_replace('@{%([0-9]+)}@', '%$1s', $args[0]);
    return call_user_func_array(
        'sprintf',
        $args
    );
}

function getv($key = null, $default = null)
{
    return array_get($_GET, $key, $default);
}

function postv($key = null, $default = null)
{
    return array_get($_POST, $key, $default);
}

function tv_url_prefixes()
{
    return ['--', 'http://', 'https://', 'ftp://', 'mailto:', 'DocID'];
}

function normalize_url_tv_value($value, $prefix = '--')
{
    $value = (string)($value ?? '');
    $prefix = in_array($prefix, tv_url_prefixes(), true) ? $prefix : '--';

    if ($prefix === 'DocID') {
        return preg_match('/\A[0-9]+\z/', $value) ? '[~' . $value . '~]' : $value;
    }

    if ($prefix === '--') {
        return $value;
    }

    foreach (tv_url_prefixes() as $knownPrefix) {
        if ($knownPrefix === '--' || $knownPrefix === 'DocID') {
            continue;
        }
        if (strpos($value, $knownPrefix) === 0) {
            $value = substr($value, strlen($knownPrefix));
            break;
        }
    }

    return $prefix . $value;
}

// GET と POST の両方から読む。$_REQUEST は php.ini の設定次第で cookie を含むので読まない。
// まとめ方は php.ini-production の request_order = "GP" の $_REQUEST と同じ（同じ名前は POST を優先し、配列は入れ子までまとめる）。
// 起動時に $_REQUEST を無害化したリクエスト（フロント）では、返す値も同じ処理で無害化する
function anyv($key = null, $default = null)
{
    $request = array_replace_recursive($_GET, $_POST);
    if (evo() && evo()->requestSanitized) {
        evo()->sanitize_gpc($request);
    }
    return array_get($request, $key, $default);
}

function serverv($key = null, $default = null)
{
    return array_get($_SERVER, strtoupper($key), $default);
}

/**
 * Check if the current request is a POST request
 *
 * @return bool
 */
function is_post()
{
    return serverv('REQUEST_METHOD') === 'POST';
}

/**
 * Check if the current request is a GET request
 *
 * @return bool
 */
function is_get()
{
    return serverv('REQUEST_METHOD') === 'GET';
}

function sessionv($key = null, $default = null)
{
    if (strpos($key, '*') === 0) {
        return array_set($_SESSION, ltrim($key, '*'), $default);
    }
    return array_get($_SESSION, $key, $default);
}

function globalv($key = null, $default = null)
{
    if (strpos($key, '*') === 0) {
        return array_set($GLOBALS, ltrim($key, '*'), $default);
    }
    return array_get($GLOBALS, $key, $default);
}

function cookiev($key = null, $default = null)
{
    return array_get($_COOKIE, $key, $default);
}

function filev($key = null, $default = null)
{
    return array_get($_FILES, $key, $default);
}

function pr($content)
{
    if (is_array($content)) {
        echo '<pre>' . print_r(array_map('hsc', $content), true) . '</pre>';
        return;
    }
    echo '<pre>' . hsc($content) . '</pre>';
}

function doc($key, $default = '')
{
    global $modx, $docObject;
    $doc = [];
    if (isset($docObject)) {
        $doc = &$docObject;
    } elseif (!empty($modx->documentObject)) {
        $doc = &$modx->documentObject;
    } elseif (!empty(evo()->documentIdentifier)) {
        $docObject = evo()->getDocumentObject('id', evo()->documentIdentifier);
        $doc = &$docObject;
    }
    if (empty(evo()->documentIdentifier)) {
        if (!empty($doc['id'])) {
            $modx->documentIdentifier = $doc['id'];
        } else {
            global $id;
            if (!empty($id)) {
                $modx->documentIdentifier = $id;
            }
        }
    }
    if (strpos($key, '*') === 0) {
        $value = $default;
        $doc[substr($key, 1)] = $value;
        return $value;
    }
    if (str_contains($key, '@parent')) {
        $a = evo()->getDocumentObject('id', doc('parent'));
        $key = str_replace('@parent', '', $key);
    } elseif (str_contains($key, '@up')) {
        $a = evo()->getDocumentObject('id', uparent());
        $key = str_replace('@up', '', $key);
    } elseif (str_contains($key, '@inherit')) {
        $key = str_replace('@inherit', '', $key);
        $a = evo()->getDocumentObject(
            'id',
            evo()->inheritDocId($key, docid())
        );
    } elseif (evo()->isFrontEnd()) {
        $a = evo()->documentObject;
    } else {
        $a = $doc;
    }
    if (strpos($key, ':') !== false) {
        // modifierを設定
        $modifiers = explode(':', $key);
        $key = array_shift($modifiers);
        $value = isset($a[$key]) && is_array($a[$key])
            ? array_get($a, $key . '.value', $default)
            : array_get($a, $key, $default);
        return evo()->applyFilter($value, implode(':', $modifiers), $key);
    }
    // $keyが「|」で区切られている場合は値が有効なキーを探す
    if (strpos($key, '|') !== false) {
        $keys = explode('|', $key);
        foreach ($keys as $key) {
            if (array_get($a, $key)) {
                return array_get($a, $key, $default);
            }
            if (array_get($a, $key . '.value')) {
                return array_get($a, $key . '.value', $default);
            }
        }
    }
    return isset($a[$key]) && is_array($a[$key])
        ? array_get($a, $key . '.value', $default)
        : array_get($a, $key, $default)
    ;
}

function ob_get_include($path)
{
    if (!is_file($path)) {
        return false;
    }
    ob_start();
    $return = include $path;
    return ob_get_clean() ?: $return;
}

function easy_hash($seed)
{
    return strtr(rtrim(base64_encode(pack('H*', hash('adler32', $seed))), '='), '+/', '-_');
}

function device()
{
    if (!serverv('http_user_agent')) {
        return 'pc';
    }

    $ua = strtolower(serverv('http_user_agent'));

    if (strpos($ua, 'ipad') !== false) {
        return 'tablet';
    }
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipod') !== false) {
        return 'smartphone';
    }

    if (strpos($ua, 'android') === false) {
        if (strpos($ua, 'windows phone') !== false) {
            return 'smartphone';
        }
        if (strpos($ua, 'docomo') !== false || strpos($ua, 'softbank') !== false) {
            return 'mobile';
        }
        if (strpos($ua, 'up.browser') !== false) {
            return 'mobile';
        }
        if (strpos($ua, 'bot') !== false || strpos($ua, 'spider') !== false) {
            return 'bot';
        }
        return 'pc';
    }

    if (strpos($ua, 'mobile') !== false) {
        return 'smartphone';
    }

    return 'tablet';
}

function datetime_format($format, $timestamp = '', $default = '')
{
    if (!$timestamp || strpos($timestamp, '0000-00-00') === 0) {
        return $default;
    }
    if (!preg_match('@^[0-9]+$@', $timestamp)) {
        $timestamp = strtotime(
            preg_replace('@^([0-9]+)/([0-9]+)/([0-9]+)@', '$1-$2-$3', $timestamp)
        );
    }
    if (strpos($format, '%') === false) {
        return date($format, $timestamp);
    }

    return evo()->mb_strftime($format, $timestamp);
}

function request_uri()
{
    return serverv('request_uri');
}

function request_time()
{
    static $request_time = null;

    if ($request_time) {
        return $request_time;
    }

    $request_time = serverv('request_time') ?: time();

    return $request_time;
}

function real_ip()
{
    // クライアントが詐称できるヘッダー（client-ip / x-forwarded-for）も候補に含めるが、
    // 返す値は必ず IP アドレスの形式に限定する。
    // ここを通す値はログやアクティブユーザー表の SQL にそのまま渡されるため、
    // IP として妥当でない文字列（SQL 片など）を返さないことが安全上の要になる。
    $candidates = [
        serverv('http_client_ip', ''),
        serverv('http_x_forwarded_for', ''),
        serverv('remote_addr', ''),
    ];
    foreach ($candidates as $candidate) {
        // x-forwarded-for は "client, proxy1, proxy2" のように連結されることがある
        foreach (explode(',', (string) $candidate) as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }
    }
    return 'UNKNOWN';
}

function user_agent()
{
    return serverv('http_user_agent', '');
}

function remove_tags($value, $params = '')
{
    if (stripos($params, 'style') === false && stripos($value, '</style>') !== false) {
        $value = preg_replace('#<style.*?>.*?</style>#is', '', $value);
    }
    if (stripos($params, 'script') === false && stripos($value, '</script>') !== false) {
        $value = preg_replace('@<script.*?>.*?</script>@is', '', $value);
    }
    if (strpos($params, '[[') === false && strpos($value, ']]') !== false) {
        $value = preg_replace('@\[\[.+?\]\]@s', '', $value);
    }
    return strip_tags($value, $params);
}

function manager_style_image_path($subdir = '')
{
    $theme = config('manager_theme', '');
    $subdir = trim((string)$subdir, '/');
    $segments = $subdir !== '' ? '/' . $subdir : '';
    $relative = sprintf('media/style/%s/images%s', $theme, $segments);
    $absolute = MODX_MANAGER_PATH . $relative;

    if ($theme && is_dir($absolute)) {
        return rtrim($relative, '/') . '/';
    }

    $relative = 'media/style/common/images' . $segments;
    return rtrim($relative, '/') . '/';
}

function manager_style_image_url($subdir = '')
{
    return MODX_MANAGER_URL . manager_style_image_path($subdir);
}

function manager_style_placeholders()
{
    static $paths = null;
    if ($paths !== null) {
        return $paths;
    }

    $paths = [
        'style_images_path' => manager_style_image_path(),
        'style_icons_path' => manager_style_image_path('icons'),
        'style_misc_path' => manager_style_image_path('misc'),
        'style_tree_path' => manager_style_image_path('tree'),
    ];

    return $paths;
}

function set_manager_style_placeholders()
{
    foreach (manager_style_placeholders() as $key => $value) {
        evo()->setPlaceholder($key, $value);
    }
}

if (!function_exists('env')) {
    /**
     * 環境変数を取得するヘルパー関数
     *
     * @param string $key 環境変数のキー
     * @param mixed $default デフォルト値（環境変数が存在しない場合）
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        // "true", "false", "null" 文字列を適切な型に変換
        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        return $value;
    }
}

if (!function_exists('isEvoPaneRequest')) {
    /**
     * AJAXシェル(shell.js)からの断片要求かどうかを判定する。
     * manager/index.php と manager/includes/accesscontrol.inc.php の両方から
     * 同一の判定を行うための共通ヘルパー(条件のズレを防ぐ)。
     *
     * X-Requested-With は Evo.fetch や（アドオンが読み込んだ）jQuery の AJAX も付けるため、
     * shell.js以外の既存AJAX呼び出しまで断片要求と誤判定してしまう
     * (システムアラートの誤消費、サードパーティ互換破壊)。
     * shell.jsが明示的に付与する専用ヘッダーで判定する。
     *
     * @return bool
     */
    function isEvoPaneRequest(): bool
    {
        return serverv('HTTP_X_EVO_SHELL') === '1'
            || getv('ajax') === 'pane';
    }
}

if (!function_exists('evoIsPaneResponse')) {
    /**
     * この応答をAJAX断片(X-Evo-Pane: 1)として返しているかどうかを判定する。
     * shell.jsは応答ヘッダーX-Evo-Paneの有無だけで断片かどうかを決めるため、
     * header/footerの出力可否もリクエストではなく送信済みの応答ヘッダーで判定する
     * (execute_moduleのように途中でヘッダーを外す処理とも食い違わない)。
     *
     * @return bool
     */
    function evoIsPaneResponse(): bool
    {
        foreach (headers_list() as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2
                && strcasecmp(trim($parts[0]), 'X-Evo-Pane') === 0
                && trim($parts[1]) === '1') {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('evoRenderPaneFooterExtras')) {
    /**
     * footer.inc.php相当の後処理(システムアラート表示とDatePicker読み込み)を出力する。
     * AJAX断片応答(manager/index.php)と通常のフルページ応答(footer.inc.php)の
     * 両方から呼び出し、重複実装によるズレを防ぐ。
     * 断片応答ではprocessor等が読み込むfooter.inc.phpとindex.php末尾の両方から呼ばれ得るため、
     * 1リクエストにつき1回だけ出力する(システムアラートの二重表示を防ぐ)。
     *
     * @return void
     */
    function evoRenderPaneFooterExtras(): void
    {
        global $modx;
        static $rendered = false;

        if ($rendered) {
            return;
        }
        $rendered = true;

        if (is_array($modx->SystemAlertMsgQueque) && count($modx->SystemAlertMsgQueque) > 0) {
            echo manager()->sysAlert($modx->SystemAlertMsgQueque);
        }

        if (in_array(manager()->action, [85, 27, 4, 72, 131, 132, 133, 74, 13, 11, 12, 77, 78, 87, 88], true)) {
            echo manager()->loadDatePicker(
                $modx->config(
                    'mgr_date_picker_path',
                    'media/script/datepicker/datepicker.inc.php'
                )
            );
        }
    }
}

if (!function_exists('manager_is_dashboard_action')) {
    function manager_is_dashboard_action(): bool
    {
        return defined('IN_MANAGER_MODE')
            && IN_MANAGER_MODE === 'true'
            && manager()
            && (int)manager()->action === 2;
    }
}

/**
 * php.ini の容量表記(128M・1G・512K・数値のみ。K・M・G は大文字小文字を問わない)をバイト数にする。
 * 解釈できない書式は 0。-1(無制限)は -1 のまま返す
 */
function ini_size_to_bytes($value)
{
    if (!preg_match('/^(-?\d+)\s*([KMG]?)$/i', trim((string) $value), $m)) {
        return 0;
    }
    $multipliers = ['' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824];
    return (int) $m[1] * $multipliers[strtoupper($m[2])];
}

/**
 * GD で画像を読み込んで縮小しても、memory_limit の残りに収まるかを、読み込む前に見積もる。
 * imagecreatefrom*() はメモリ不足だと例外にならず致命的エラーで処理ごと止まるため、呼び出し側は先にこれで確かめる。
 * 固定の画素数の上限にしないのは、実害が memory_limit との差で決まるため。
 * memory_limit が -1・0・解釈できない書式のときは無制限として true を返す。
 *
 * 1 画素あたりに GD が確保するバイト数は、PHP 8.4 同梱の GD で memory_get_peak_usage(true) を測った値。
 * JPEG: 展開後の本体のみで約 4。PNG: 本体 4 + デコード領域(1 行分×高さ)約 4 で約 8
 * (パレット・グレースケールの PNG は約 2 だが、getimagesize() では見分けられないので安全側の 8)。
 * GIF: 実測は約 1(パレット)だが、安全側に JPEG と同じ 4 とする。縮小先は imagecreatetruecolor() で 4。
 *
 * @param int $width 元画像の幅
 * @param int $height 元画像の高さ
 * @param int $imageType IMAGETYPE_JPEG・IMAGETYPE_PNG・IMAGETYPE_GIF
 * @param int $destWidth 縮小先の幅
 * @param int $destHeight 縮小先の高さ
 */
function gd_can_load_image($width, $height, $imageType, $destWidth, $destHeight)
{
    $limit = ini_size_to_bytes(ini_get('memory_limit'));
    if ($limit <= 0) {
        return true;
    }

    $bytesPerPixel = ((int) $imageType === IMAGETYPE_PNG) ? 8 : 4;
    // 縮小先と、そのほかの確保の余裕(4MB)
    $need = $width * $height * $bytesPerPixel + $destWidth * $destHeight * 4 + 4194304;
    return $need <= $limit - memory_get_usage(true);
}
