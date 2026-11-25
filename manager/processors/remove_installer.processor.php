<?php
/**
 * Installer remover processor
 * --------------------------------
 * インストール・アップデートの完了ページ（install/actions/install.php）から、install フォルダーを消すために呼ばれる。
 * install フォルダーが残っているのはセキュリティ上の問題になるため。
 *
 * 完了ページがセッションに残した 1 回限りの値（installer_cleanup）と、POST された token が一致したときだけ消す。
 * 新規インストールの直後は未ログインなので、ログインの有無ではなく、この値で完了ページから来たことを確かめる。
 * 一致しないときは何も消さず、手で消すよう案内する。
 */
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);
include_once __DIR__ . '/../../index.php';
include_once MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php';
$modx = new DocumentParser;
$modx->getSettings();

require_once __DIR__ . '/../includes/filesystem.class.inc.php';

// 消す対象が無ければ、案内を出さずに戻る（二重の送信や、戻る操作からの再送）
$install_dir = MODX_BASE_PATH . 'install';
if (!is_dir($install_dir)) {
    echo "<script>window.location='../index.php?a=2';</script>";
    exit;
}

$expected = sessionv('installer_cleanup');
$given = post_strvar('token');
if (
    !is_post()
    || !is_string($expected)
    || $expected === ''
    || !hash_equals($expected, $given)
) {
    removeInstallerNotice(lang('remove_installer_manual'));
}
// 1 回限り
sessionv('*installer_cleanup', null);

$failures = [];
if (!Filesystem::deleteTree($install_dir, $failures)) {
    removeInstallerNotice(lang('remove_installer_manual'));
}
echo "<script>window.location='../index.php?a=2';</script>";

function removeInstallerNotice($message)
{
    header('Content-Type: text/html; charset=UTF-8');
    echo sprintf(
        '<script>alert(%s);window.location=%s;</script>',
        js_literal($message),
        js_literal('../index.php?a=2')
    );
    exit;
}
