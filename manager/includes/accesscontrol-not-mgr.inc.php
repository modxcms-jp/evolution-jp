<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

if (!serverv('HTTP_ACCEPT_LANGUAGE')) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

if (sessionv('mgrValidated') && sessionv('usertype') !== 'manager') {
    @session_destroy();
}

// ログイン画面は、開こうとした画面の URL のままで出る。ログイン後にその画面へ戻れるよう、
// 画面のクエリをログインフォームに持たせる（行き先は login.processor.functions.php の loginRedirectUri()）。
// POST と状態を変える操作は、開き直しても同じ画面にならないので持たせない
$loginReturn = '';
if (is_get() && anyv('a') && !isCsrfProtectedAction(manager()->action)) {
    $loginReturn = serverv('QUERY_STRING', '');
}
// 言語は manager/index.php の loadLexicon() で読み込み済み

$theme_path = MODX_MANAGER_PATH . sprintf('media/style/%s/', evo()->config('manager_theme'));
if (is_file($theme_path)) {
    include_once($theme_path);
}

set_manager_style_placeholders();

evo()->setPlaceholder('modx_charset', $modx_manager_charset);
evo()->setPlaceholder('theme', evo()->config('manager_theme'));
evo()->setPlaceholder('manager_theme', evo()->config('manager_theme'));
evo()->setPlaceholder('manager_theme_url',
    sprintf(
        '%smedia/style/%s/',
        MODX_MANAGER_URL,
        evo()->config('manager_theme')
    )
);

evo()->setPlaceholder('theme_css_ver', manager_theme_css_version());
evo()->setPlaceholder('evo_dialog_js_ver', asset_version(MODX_MANAGER_PATH . 'media/script/evo-dialog.js'));
evo()->setPlaceholder('login_js_ver', asset_version(MODX_MANAGER_PATH . 'media/script/login.js'));

global $tpl, $_lang;

$touch_mtime = is_file($touch_path) ? @filemtime($touch_path) : false;
if ($touch_mtime && request_time() < $touch_mtime + 300) {
    $modx->safeMode = 1;
    evo()->addLog($_lang['logtitle_login_disp_warning'], $_lang['logmsg_login_disp_warning'], 2);
    $tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/login.tpl');
} else {
    touch($touch_path);
}

// invoke OnManagerLoginFormPrerender event
$modx->event->vars = [];
$modx->event->vars['tpl'] = &$tpl;
$evtOut = evo()->invokeEvent('OnManagerLoginFormPrerender');
$modx->event->vars = [];
$html = is_array($evtOut) ? implode('', $evtOut) : '';
evo()->setPlaceholder('OnManagerLoginFormPrerender', $html);

evo()->setPlaceholder('site_name', hsc(evo()->config('site_name')));
evo()->setPlaceholder('logo_slogan', $_lang["logo_slogan"]);
evo()->setPlaceholder('login_message', $_lang["login_message"]);
evo()->setPlaceholder('year', date('Y'));

// andrazk 20070416 - notify user of install/update
if (installGoingOn()) {
    $login_message = [
        1 => $_lang['login_cancelled_install_in_progress'],
        2 => $_lang['login_cancelled_site_was_updated']
    ];
    evo()->setPlaceholder(
        'login_message',
        sprintf(
            '<p><span class="fail">%s</span></p><p>%s</p>',
            $login_message[installGoingOn()],
            $_lang['login_message']
        )
    );
}

if (evo()->config('use_captcha') == 1) {
    evo()->setPlaceholder(
        'login_captcha_message',
        sprintf(
            '<p class="loginCaptchaMessage">%s</p>',
            $_lang["login_captcha_message"]
        )
    );
    $captcha_image = sprintf(
        '<img id="captcha_image" class="loginCaptcha" src="../index.php?get=captcha" alt="%s" />',
        $_lang["captcha_code"]
    );
    $captcha_image = sprintf(
        '<a href="%s" class="loginCaptchaLink">%s</a>',
        MODX_MANAGER_URL,
        $captcha_image
    );
    evo()->setPlaceholder('captcha_image', "<div>" . $captcha_image . "</div>");
    evo()->setPlaceholder(
        'captcha_input',
        sprintf(
            '<label for="captcha_code">%s</label><input type="text" class="text" id="captcha_code" name="captcha_code" tabindex="3" value="" autocomplete="off" style="margin-bottom:8px;" />',
            $_lang["captcha_code"]
        )
    );
}

// login info
evo()->setPlaceholder(
    'uid',
    preg_replace(
        '/[^a-zA-Z0-9\-_@.]*/',
        '',
        to_strvar(cookiev('modx_remember_manager'))
    )
);
evo()->setPlaceholder('username', $_lang["username"]);
evo()->setPlaceholder('password', $_lang["password"]);
evo()->setPlaceholder('show_password', $_lang['show_password']);

// remember me
evo()->setPlaceholder(
    'remember_me',
    cookiev('modx_remember_manager') ? 'checked="checked"' : ''
);
evo()->setPlaceholder('remember_username', $_lang["remember_username"]);
evo()->setPlaceholder('login_button', $_lang["login_button"]);
// ログイン画面は下の parseDocumentSource() で解析し直すので、リクエストから来た値に [ ] { } が残ると
// タグとして評価される。hsc() に加えて文字参照にする（ブラウザは元の文字に戻して送る）
evo()->setPlaceholder(
    'login_return',
    strtr(hsc($loginReturn), ['[' => '&#91;', ']' => '&#93;', '{' => '&#123;', '}' => '&#125;'])
);

$loginTplOutput = loadLoginTpl($tpl);
$evtOut = evo()->invokeEvent('OnManagerLoginFormRender');
evo()->setPlaceholder(
    'OnManagerLoginFormRender',
    is_array($evtOut)
    ? sprintf(
        '<div id="onManagerLoginFormRender">%s</div>',
        implode('', $evtOut)
    )
    : ''
);

$loginTplOutput = evo()->parseDocumentSource($loginTplOutput);
clearstatcache(true, $touch_path);
if (is_file($touch_path) && $loginTplOutput) {
    @unlink($touch_path);
}

// little tweak for newer parsers
$regx = strpos($loginTplOutput, '[[+') !== false ? '~\[\[\+(.*?)\]\]~' : '~\[\+(.*?)\+\]~';
echo preg_replace($regx, '', $loginTplOutput); //cleanup
