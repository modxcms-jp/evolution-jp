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
            $tagvalue = trim($tag[1]);
            $declare[$tagname] = $tagvalue;
        }
        // remove declarations
        $html = str_replace($matches[0], '', $html);
    }
    return $declare;
}

// show javascript alert
function webLoginAlert($msg, $ph = [])
{
    global $modx;
    return sprintf(
        '<script>window.setTimeout("alert(\'%s\')",10);</script>',
        addslashes(db()->escape(fmplang($msg, $ph)))
    );
}

// generate new password
function webLoginGeneratePassword($length = 10, $allow_chars = '')
{
    if (empty($allow_chars)) {
        $allow_chars = 'abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    }
    return substr(str_shuffle($allow_chars), 0, $length);
}

// Send new password to the user
function webLoginSendNewPassword($email, $uid, $pwd, $ufn)
{
    global $modx, $site_url;
    $ph = $modx->getConfig();
    $ph['sname'] = $modx->config('site_name');
    $ph['uid'] = $uid;
    $ph['pwd'] = $pwd;
    $ph['ufn'] = $ufn;
    $ph['surl'] = MODX_SITE_URL;
    $message = $modx->parseText(
        sprintf($modx->config('websignupemail_message'), $uid, $pwd),
        $ph
    );
    $emailsubject = $modx->config('emailsubject');

    $sent = $modx->sendmail($email, $message);         //ignore mail errors in this cas

    if (!$sent) {
        webLoginAlert('Error while sending mail to ' . $modx->config('mailto'), 1);
    }
    return true;
}

function preserveUrl($docid = '', $alias = '', $array_values = [], $suffix = false)
{
    $array_get = getv();
    $urlstring = [];

    unset($array_get['id'], $array_get['q'], $array_get['webloginmode']);

    $array_url = array_merge($array_get, $array_values);
    foreach ($array_url as $name => $value) {
        if ($value !== null) {
            $urlstring[] = urlencode($name) . '=' . urlencode($value);
        }
    }

    $url = implode('&', $urlstring);
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
        'You are blocked and cannot log in!' => 'メールアドレスまたはパスワードが間違っています。'
    ];

    $value = ($_lang[$key]) ? $_lang[$key] : $key;
    if (!$ph) {
        return $value;
    }

    foreach ($ph as $k => $v) {
        $k = '[+' . $k . '+]';
        $value = str_replace($k, $v, $value);
    }
    return $value;
}

function webLoginGetCode($target)
{
    global $modx;
    if (preg_match('@^[0-9][1-9]*$@', $target)) {
        $content = $modx->getField('content', $target);
        if (!$content) {
            return sprintf("Document '%s' not found.", $target);
        }
        return $content;
    }

    $result = $modx->getChunk($target);
    if (!$result) {
        return sprintf("Chunk '%s' not found.", $target);
    }
    return $result;
}
