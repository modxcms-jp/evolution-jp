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

// andrazk 20070416 - if session started before install and was not destroyed yet
if (isset($lastInstallTime) && sessionv('modx.session.created.time') && sessionv('mgrValidated')) {
    if (
        (sessionv('modx.session.created.time',0) < $lastInstallTime)
        && sessionv('REQUEST_METHOD') !== 'POST'
    ) {
        if (cookiev(session_name())) {
            session_unset();
            @session_destroy();
        }
        header('HTTP/1.0 307 Redirect');
        header('Location: ' . MODX_MANAGER_URL . 'index.php?installGoingOn=2');
    }
}

$theme_path = MODX_MANAGER_PATH . sprintf('media/style/%s/', evo()->config('manager_theme'));
if (!sessionv('mgrValidated')) {
    if (getv('frame')) {
        $_SESSION['save_uri'] = serverv('REQUEST_URI');
    }
    // include localized overrides
    include_once MODX_CORE_PATH . sprintf('lang/%s.inc.php', evo()->config('manager_language', 'english'));

    if (is_file($theme_path)) {
        include_once($theme_path);
    }

    evo()->setPlaceholder('modx_charset', $modx_manager_charset);
    evo()->setPlaceholder('theme', evo()->config('manager_theme'));
    evo()->setPlaceholder('manager_theme', evo()->config('manager_theme'));
    evo()->setPlaceholder('manager_theme_url'
        , sprintf(
            '%smedia/style/%s/'
            , MODX_MANAGER_URL
            , evo()->config('manager_theme')
        )
    );

    global $tpl, $_lang;

    $touch_path = MODX_BASE_PATH . 'assets/cache/touch.siteCache.idx.php';
    if (is_file($touch_path) && request_time() < filemtime($touch_path) + 300) {
        $modx->safeMode = 1;
        evo()->addLog($_lang['logtitle_login_disp_warning'], $_lang['logmsg_login_disp_warning'], 2);
        $tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/login.tpl');
    } else {
        touch($touch_path);
    }

    // invoke OnManagerLoginFormPrerender event
    $modx->event->vars = array();
    $modx->event->vars['tpl'] = &$tpl;
    $evtOut = evo()->invokeEvent('OnManagerLoginFormPrerender');
    $modx->event->vars = array();
    $html = is_array($evtOut) ? implode('', $evtOut) : '';
    evo()->setPlaceholder('OnManagerLoginFormPrerender', $html);

    evo()->setPlaceholder('site_name', evo()->config('site_name'));
    evo()->setPlaceholder('logo_slogan', $_lang["logo_slogan"]);
    evo()->setPlaceholder('login_message', $_lang["login_message"]);
    evo()->setPlaceholder('year', date('Y'));

    // andrazk 20070416 - notify user of install/update
    if (installGoingOn()) {
        $login_message = array(
            1 => $_lang['login_cancelled_install_in_progress'],
            2 => $_lang['login_cancelled_site_was_updated']
        );
        evo()->setPlaceholder(
            'login_message'
            , sprintf(
                '<p><span class="fail">%s</span>span></p><p>%s</p>'
                , $login_message[installGoingOn()]
                , $_lang['login_message']
            )
        );
    }

    if (evo()->config('use_captcha') == 1) {
        evo()->setPlaceholder(
            'login_captcha_message'
            , sprintf(
                '<p style="margin-top:10px;">%s</p>'
                , $_lang["login_captcha_message"]
            )
        );
        $captcha_image = sprintf(
            '<img id="captcha_image" src="../index.php?get=captcha" alt="%s" />'
            , $_lang["login_captcha_message"]
        );
        $captcha_image = sprintf(
            '<a href="%s" class="loginCaptcha">%s</a>'
            , MODX_MANAGER_URL
            , $captcha_image
        );
        evo()->setPlaceholder('captcha_image', "<div>" . $captcha_image . "</div>");
        evo()->setPlaceholder(
            'captcha_input'
            , sprintf(
                '<label>%s<input type="text" class="text" name="captcha_code" tabindex="3" value="" autocomplete="off" style="margin-bottom:8px;" /></label>'
                , $_lang["captcha_code"]
            )
        );
    }

    // login info
    evo()->setPlaceholder(
        'uid'
        , preg_replace(
            '/[^a-zA-Z0-9\-_@.]*/'
            , ''
            , evo()->input_cookie('modx_remember_manager', '')
        )
    );
    evo()->setPlaceholder('username', $_lang["username"]);
    evo()->setPlaceholder('password', $_lang["password"]);

    // remember me
    evo()->setPlaceholder(
        'remember_me'
        , cookiev('modx_remember_manager') ? 'checked="checked"' : ''
    );
    evo()->setPlaceholder('remember_username', $_lang["remember_username"]);
    evo()->setPlaceholder('login_button', $_lang["login_button"]);

    // load template
    if (!evo()->config('manager_login_tpl')) {
        evo()->config['manager_login_tpl'] = MODX_MANAGER_PATH . 'media/style/common/login.tpl';
    }

    $target = evo()->config('manager_login_tpl');
    if (isset($tpl) && !empty($tpl)) {
        $login_tpl = $tpl;
    } elseif (substr($target, 0, 1) === '@') {
        if (substr($target, 0, 6) === '@CHUNK') {
            $login_tpl = evo()->getChunk(trim(substr($target, 7)));
        } elseif (substr($target, 0, 5) === '@FILE') {
            $login_tpl = file_get_contents(trim(substr($target, 6)));
        }
    } else {
        $chunk = evo()->getChunk($target);
        if ($chunk !== false && !empty($chunk)) {
            $login_tpl = $chunk;
        } elseif (is_file(MODX_BASE_PATH . $target)) {
            $login_tpl = file_get_contents(MODX_BASE_PATH . $target);
        } elseif (is_file($theme_path . "login.tpl")) {
            $login_tpl = file_get_contents($theme_path . "login.tpl");
        } elseif (is_file($theme_path . "html/login.html")) { // ClipperCMS compatible
            $login_tpl = file_get_contents($theme_path . "html/login.html");
        } else {
            $login_tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/login.tpl');
        }
    }
    evo()->output = $login_tpl;
    $evtOut = evo()->invokeEvent('OnManagerLoginFormRender');
    evo()->setPlaceholder(
        'OnManagerLoginFormRender'
        , is_array($evtOut)
        ? sprintf(
            '<div id="onManagerLoginFormRender">%s</div>'
            , implode('', $evtOut)
        )
        : ''
    );

    evo()->output = evo()->parseDocumentSource(evo()->output);

    if (is_file($touch_path) && evo()->output) {
        unlink($touch_path);
    }

    // little tweak for newer parsers
    $regx = strpos(evo()->output, '[[+') !== false ? '~\[\[\+(.*?)\]\]~' : '~\[\+(.*?)\+\]~';
    $modx->output = preg_replace($regx, '', evo()->output); //cleanup
    echo evo()->output;
    exit;

}

// log the user action
$_SESSION['ip'] = real_ip();

if (manager()->action != 1) {
    $fields = array(
        'internalKey' => evo()->getLoginUserID(),
        'username' => sessionv('mgrShortname'),
        'lasthit' => serverv('REQUEST_TIME',time()),
        'action' => manager()->action,
        'id' => preg_match('@^[1-9][0-9]*$@', anyv('id')) ? anyv('id') : 0,
        'ip' => real_ip()
    );
    foreach ($fields as $k => $v) {
        $keys[] = $k;
        $values[] = $v;
    }

    $sql = sprintf(
        "REPLACE INTO %s (%s) VALUES ('%s')"
        , evo()->getFullTableName('active_users')
        , implode(',', $keys)
        , implode("','", $values)
    );
    if (!db()->query($sql)) {
        echo "error replacing into active users! SQL: " . $sql . "\n" . db()->getLastError();
        exit;
    }
    $_SESSION['mgrDocgroups'] = manager()->getMgrDocgroups(evo()->getLoginUserID());
}
if (is_file($touch_path)) {
    unlink($touch_path);
}

function installGoingOn() {
    if (checkInstallProc()) {
        return 1;
    }
    if (getv('installGoingOn')) {
        return getv('installGoingOn');
    }
    return false;
}

function checkInstallProc() {
    $instcheck_path = MODX_BASE_PATH . 'assets/cache/installProc.inc.php';
    if (!is_file($instcheck_path)) {
        return false;
    }
    
    global $installStartTime;
    include_once($instcheck_path);
    if (!isset($installStartTime)) {
        return false;
    }

    if ((serverv('REQUEST_TIME', time()) - $installStartTime) > 5 * 60) {
        unlink($instcheck_path);
        return false;
    }

    if (serverv('REQUEST_METHOD') === 'POST') {
        return false;
    }

    if (cookiev(session_name())) {
        session_unset();
        @session_destroy();
    }
    return true;
}
