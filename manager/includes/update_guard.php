<?php
if (!function_exists('evo_render_update_pending_page')) {
    function evo_render_update_pending_page()
    {
        $protocol = serverv('SERVER_PROTOCOL', 'HTTP/1.1');
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

if (!function_exists('evo_has_pending_migrations')) {
    /**
     * アップデート待ち（未適用のマイグレーションがある）かどうか。判定は EvoMigrator::hasPending() の 1 つだけ。
     * リクエストごとに走るので、ファイルの一覧（glob 1 回）と記録の表の SELECT 1 回以上は足さない。例外は外に出さない
     */
    function evo_has_pending_migrations($modx)
    {
        if (!$modx || !isset($modx->db)) {
            return false;
        }

        try {
            require_once MODX_CORE_PATH . 'migrator.class.inc.php';
            return (new EvoMigrator())->hasPending();
        } catch (Throwable $e) {
            // ガード自身の失敗で公開側を壊さないよう、ここでは通す（hasPending() は記録を読めなければ true に倒す）
            return false;
        }
    }
}

if (!function_exists('evo_guard_update_pending')) {
    function evo_guard_update_pending($modx)
    {
        if (!evo_has_pending_migrations($modx)) {
            return;
        }

        evo_render_update_pending_page();
    }
}
