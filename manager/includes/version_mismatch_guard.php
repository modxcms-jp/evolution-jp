<?php
if (!function_exists('evo_render_version_mismatch_page')) {
    function evo_render_version_mismatch_page()
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        header($protocol . ' 503 Service Unavailable', true, 503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 600');

        echo '<!DOCTYPE html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Maintenance</title>'
            . '<style>'
            . 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;background:#ffffff;color:#1f1f1f;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem;box-sizing:border-box;}'
            . 'main{max-width:32rem;text-align:center;}'
            . 'h1{margin:0 0 1.25rem;font-size:2rem;font-weight:600;}'
            . 'p{margin:0;font-size:1.0625rem;line-height:1.65;}'
            . '</style>'
            . '</head>'
            . '<body>'
            . '<main>'
            . '<h1>We&#8217;re performing maintenance</h1>'
            . '<p>The site is temporarily unavailable while we complete an update. Please check back soon.</p>'
            . '</main>'
            . '</body>'
            . '</html>';
        exit;
    }
}

if (!function_exists('evo_has_settings_version_mismatch')) {
    function evo_has_settings_version_mismatch($modx)
    {
        if (!$modx || !isset($modx->db)) {
            return false;
        }

        $versionFile = MODX_BASE_PATH . 'manager/includes/version.inc.php';
        if (!is_file($versionFile)) {
            return false;
        }

        include $versionFile;
        if (!isset($modx_version)) {
            return false;
        }

        $tableName = $modx->getFullTableName('system_settings');
        if (!$tableName) {
            return false;
        }

        $result = $modx->db->select(
            'setting_value',
            $tableName,
            "setting_name='settings_version'",
            '',
            '1'
        );
        if (!$result) {
            return false;
        }

        $settingsVersion = $modx->db->getValue($result);
        if ($settingsVersion === null) {
            return false;
        }

        return $settingsVersion !== $modx_version;
    }
}

if (!function_exists('evo_guard_version_mismatch')) {
    function evo_guard_version_mismatch($modx)
    {
        if (!evo_has_settings_version_mismatch($modx)) {
            return;
        }

        evo_render_version_mismatch_page();
    }
}
