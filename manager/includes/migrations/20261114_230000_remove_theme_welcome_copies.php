<?php

/**
 * 同梱テーマの welcome.tpl の写し（AromaHop・SakuraFlow・RevoStyle）を消す。
 * ダッシュボードはテーマに welcome.tpl があればそれを、無ければ common/welcome.tpl を読む。
 * 配布から外した写しが残ると、common/welcome.tpl の直しが届かない。配布から外したファイルなので、利用者の選択ではない。
 * 2 回目は、消すものが無いので何もしない。
 */
return static function (EvoMigrationHelper $m) {
    $removed = [];
    $failed = [];
    foreach (['AromaHop', 'SakuraFlow', 'RevoStyle'] as $theme) {
        $path = MODX_MANAGER_PATH . "media/style/{$theme}/welcome.tpl";
        if (!is_file($path)) {
            continue;
        }
        if (@unlink($path)) {
            $removed[] = "{$theme}/welcome.tpl";
        } else {
            $failed[] = "manager/media/style/{$theme}/welcome.tpl";
        }
    }
    if ($removed) {
        $m->log('Removed old copies of bundled theme files', ['files' => $removed]);
    }
    if ($failed) {
        $m->log('Could not remove old copies of bundled theme files. Delete them by hand', ['files' => $failed], 2);
    }
};
