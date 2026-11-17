<?php

/**
 * 直下の古い CLI の入口 evo を消す（入口は evo.php）。
 * evo は拡張子が無く、ブラウザで開くとソースがそのまま表示される。配布物で上書きするアップデートでは古いファイルが残るので、ここで消す。
 * 同じ名前の別のファイルを消さないよう、CLI の bootstrap を読む中身のときだけ消す。2 回目は、消すものが無いので何もしない。
 */
return static function (EvoMigrationHelper $m) {
    $path = MODX_BASE_PATH . 'evo';
    if (!is_file($path)) {
        return;
    }
    if (strpos((string) file_get_contents($path), 'manager/includes/cli/bootstrap.php') === false) {
        return;
    }
    if (@unlink($path)) {
        $m->log('Removed the old CLI entry point. Use evo.php instead', ['file' => 'evo']);
        return;
    }
    $m->log('Could not remove the old CLI entry point. Delete it by hand', ['file' => 'evo'], 2);
};
