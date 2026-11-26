<?php
/**
 * Commonly used login functions
 * Writen By Raymond Irving April, 2005
 *
 */

// extract declarations
function webLoginExtractDeclarations(&$html)
{
    $declare = [];
    if (strpos($html, '<!-- #declare:') === false) return $declare;
    $matches = [];
    if (preg_match_all("/<\!-- \#declare\:(.*)[^-->]?-->/i", $html, $matches)) {
        for ($i = 0; $i < count($matches[1]); $i++) {
            $tag = explode(' ', $matches[1][$i]);
            $tagname = trim($tag[0]);
            $tagvalue = trim($tag[1] ?? '');
            $declare[$tagname] = $tagvalue;
        }
        // remove declarations
        $html = str_replace($matches[0], '', $html);
    }
    return $declare;
}

// 自サイトの URL（サイトの URL で始まる絶対 URL、またはホストを含まない相対 URL）か
function webLoginIsSiteUrl($url)
{
    $url = (string)$url;
    if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) {
        return false;
    }
    if (stripos($url, MODX_SITE_URL) === 0) {
        return true;
    }
    // スキームやホストを含むものは不可（//host、http:、javascript: など）
    return strpos($url, '//') !== 0 && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $url);
}

// show javascript alert
function webLoginAlert($msg, $ph = [])
{
    global $modx;
    return sprintf(
        '<script>window.setTimeout(function(){alert(%s);},10);</script>',
        js_literal(fmplang($msg, $ph))
    );
}

// generate new password
function webLoginGeneratePassword($length = 10, $allow_chars = '')
{
    if (empty($allow_chars)) {
        $allow_chars = 'abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    }
    // 重複なしの並べ替え（str_shuffle() と同じ形）を、暗号用の乱数で行う
    $chars = str_split($allow_chars);
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return substr(implode('', $chars), 0, $length);
}

// ログイン後の戻り先。権限が無くてログインページへ転送したときは、元の文書を優先する
function webLoginRefUrl()
{
    if (evo()->unauthorizedDocumentId) {
        return (string) evo()->unauthorizedDocumentId;
    }
    return request_strvar('refurl');
}

function preserveUrl($docid = '', $alias = '', $array_values = [], $suffix = false)
{
    $array_get = getv();

    unset($array_get['id'], $array_get['q'], $array_get['webloginmode']);

    $array_url = array_merge($array_get, $array_values);
    // 配列の値（name[]=...）も含め、null を除いて組み立てる
    $url = http_build_query($array_url, '', '&');
    if ($suffix) {
        if (empty($url)) {
            $url = '?';
        } else {
            $url .= '&';
        }
    }
    return evo()->makeUrl($docid, $alias, $url, 'full');
}

function fmplang($key, $ph = [])
{
    $_lang = [
        'Invalid password activation key. Your password was NOT activated.' => 'アクティベーションキーが無効になっています。',
        'Your new password was successfully activated.' => 'アクティベーションしました。新しいパスワードでログインできます。',
        'Incorrect username or password entered!' => 'メールアドレスまたはパスワードが間違っています。',
        'You cannot log in right now. Please try again later.' => 'ただいまログインできません。しばらくしてからお試しください。',
        'Please enter the security code shown in the image.' => '画像のセキュリティコードを入力してください。',
        'Security code' => 'セキュリティコード',
        'Error while loading user account. Please contact the Site Administrator' => 'ユーザー情報を読み込めませんでした。サイトの管理者に連絡してください。',
        'It was over expiration time' => 'パスワード再設定の有効期限が切れています。もう一度、再設定の手続きをしてください。',
        'Error while activating password.' => 'パスワードのアクティベーションに失敗しました。',
        'Please enter your email address.' => 'メールアドレスを入力してください。',
        'We are sorry! We cannot locate an account using that email.' => 'このメールアドレスのアカウントが見つかりません。',
        'Error while sending mail to [+email+]. Please contact the Site Administrator' => '[+email+] へのメール送信に失敗しました。サイトの管理者に連絡してください。',
        'Please check your email account ([+email+]) for login instructions.' => 'ログイン方法をメール([+email+])で送りました。ご確認ください。',
        "The security code you entered didn't validate! Please try to login again!" => '入力されたセキュリティコードが間違っています。もう一度ログインしてください。',
        'You are not allowed to login from this location.' => 'この場所からはログインできません。',
        'You are not allowed to login at this time. Please try again later.' => 'この曜日はログインできません。',
        'error replacing into active users!' => 'ログイン状態の記録に失敗しました。'
    ];

    // 翻訳が無いメッセージはそのまま返す
    $value = $_lang[$key] ?? $key;
    if (!$ph || !is_array($ph)) {
        return $value;
    }

    foreach ($ph as $k => $v) {
        $k = '[+' . $k . '+]';
        $value = str_replace($k, $v, $value);
    }
    return $value;
}
