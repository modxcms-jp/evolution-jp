<?php
/**
 * Import HTML モジュールの入口（雛形 importhtml.install_base.tpl から読み込まれる）
 *
 * 画面の HTML を文字列で返す。モジュールの実行（a=112）がシェルの中に表示する。
 */
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE !== 'true' || !evo()->isLoggedin()) {
    exit;
}

require_once __DIR__ . '/classes/ImportHtml.php';

$importHtml = new ImportHtml(
    (int) (evo()->moduleObject['id'] ?? 0),
    ImportHtml::loadLang(config('manager_language', 'english'))
);

return $importHtml->handle();
