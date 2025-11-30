<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

if (anyv('a') == '8' || !sessionv('mgrValidated')) {
    return;
}

set_manager_style_placeholders();

evo()->toPlaceholders(
    [
        'modx_charset' => $modx_manager_charset,
        'theme' => evo()->config('manager_theme'),
        'site_name' => evo()->config('site_name'),
        'logo_slogan' => $_lang['logo_slogan'],
        'manager_lockout_message' => $_lang['manager_lockout_message'],
        'home' => $_lang['home'],
        'logout' => $_lang['logout'],
        'logouturl' => './index.php?a=8',
        'manager_theme_url' => sprintf('%smedia/style/%s/', MODX_MANAGER_URL, evo()->config('manager_theme')),
        'year' => date('Y')
    ]
);

if (evo()->config('manager_login_startup')) {
    evo()->setPlaceholder('homeurl', evo()->makeUrl(evo()->config('manager_login_startup')));
} else {
    evo()->setPlaceholder('homeurl', evo()->makeUrl(evo()->config('site_start')));
}

// merge placeholders
echo preg_replace(
    '@\[\+(.*?)\+]@',
    '',
    evo()->mergePlaceholderContent(tpl_content())
);
exit;

function tpl_content()
{
    if (evo()->config('manager_lockout_tpl')) {
        $target = evo()->config('manager_lockout_tpl');
    } else {
        $target = MODX_MANAGER_PATH . 'media/style/_system/manager.lockout.tpl';
    }

    if (strpos($target, '@') === 0) {
        if (strpos($target, '@CHUNK') === 0) {
            return evo()->getChunk(trim(substr($target, 7)));
        }

        if (strpos($target, '@FILE') === 0) {
            return file_get_contents(trim(substr($target, 6)));
        }
        exit('error');
    }

    $chunk = evo()->getChunk($target);
    if ($chunk) {
        return $chunk;
    }

    if (is_file(MODX_BASE_PATH . $target)) {
        return file_get_contents(MODX_BASE_PATH . $target);
    }

    $style_path = MODX_MANAGER_PATH . sprintf('media/style/%s/', evo()->config('manager_theme'));
    if (is_file($style_path . 'manager.lockout.tpl')) {
        return file_get_contents($style_path . 'manager.lockout.tpl');
    }

    // ClipperCMS compatible
    if (is_file($style_path . 'html/manager.lockout.html')) {
        return file_get_contents($style_path . 'html/manager.lockout.html');
    }

    return file_get_contents(MODX_MANAGER_PATH . 'media/style/_system/manager.lockout.tpl');
}
