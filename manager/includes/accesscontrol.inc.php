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
        (sessionv('modx.session.created.time', 0) < $lastInstallTime)
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
$touch_path = MODX_BASE_PATH . 'temp/cache/touch.siteCache.idx.php';
if (!sessionv('mgrValidated')) {
    include __DIR__ . '/accesscontrol-not-mgr.inc.php';
    exit;
}

// log the user action
$_SESSION['ip'] = real_ip();

if (manager()->action != 1) {
    $fields = [
        'internalKey' => evo()->getLoginUserID(),
        'username'    => sessionv('mgrShortname'),
        'lasthit'     => serverv('REQUEST_TIME', time()),
        'action'      => manager()->action,
        'id'          => preg_match('@^[1-9][0-9]*$@', anyv('id')) ? anyv('id') : 0,
        'ip'          => real_ip()
    ];

    $sql = sprintf(
        "REPLACE INTO %s (%s) VALUES ('%s')",
        evo()->getFullTableName('active_users'),
        implode(',', array_keys($fields)),
        implode("','", array_values($fields))
    );
    if (!db()->query($sql)) {
        echo "error replacing into active users! SQL: " . $sql . "\n" . db()->getLastError();
        exit;
    }
    $_SESSION['mgrDocgroups'] = manager()->getMgrDocgroups(evo()->getLoginUserID());
}

if ($touch_path && is_file($touch_path)) {
    unlink($touch_path);
}

function loadLoginTpl($html) {
    if (!empty($html)) {
        return $html;
    }

    $style_path = MODX_MANAGER_PATH . 'media/style/';
    if (empty(evo()->config('manager_login_tpl'))) {
        if (is_file($style_path . evo()->config('manager_theme') . '/login.tpl')) {
            return file_get_contents($style_path . evo()->config('manager_theme') . '/login.tpl');
        }
        return file_get_contents($style_path . 'common/login.tpl');
    }

    $target = evo()->config('manager_login_tpl');
    if (strpos($target, '@') === 0) {
        $result = evo()->atBind($target);
        if($result) {
            return $result;
        }
    }

    $chunk = evo()->getChunk($target);
    if (!empty($chunk)) {
        return $chunk;
    }
    if (is_file(MODX_BASE_PATH . $target)) {
        return file_get_contents(MODX_BASE_PATH . $target);
    }
    return file_get_contents($style_path . 'common/login.tpl');
}

function installGoingOn()
{
    if (checkInstallProc()) {
        return 1;
    }
    if (getv('installGoingOn')) {
        return getv('installGoingOn');
    }
    return false;
}

function checkInstallProc()
{
    $instcheck_path = MODX_BASE_PATH . 'temp/cache/installProc.inc.php';
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
