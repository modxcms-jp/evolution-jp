<?php

/**
 * ファイルブラウザ(ピッカー)エントリ。
 *
 * ?modal=1 のときは EvoShell モーダル用のHTML断片を、それ以外はテーマCSSを読み込んだ
 * スタンドアロン完全HTML(chromelessフォールバックのポップアップ用)を出力する。
 * 常にピッカーの範囲で開く(URLに何を付けても、管理モードの設定は入れない)。
 */

define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);

$self = 'manager/media/browser/evo/browser.php';
$base_path = str_replace($self, '', str_replace('\\', '/', __FILE__));
require_once $base_path . 'manager/includes/document.parser.class.inc.php';
require_once __DIR__ . '/lib/BrowserConfig.php';
require_once __DIR__ . '/lib/PathGuard.php';
require_once __DIR__ . '/lib/BrowserScope.php';
require_once __DIR__ . '/lib/PickerScope.php';
require_once __DIR__ . '/lib/BrowserView.php';

$modx = new DocumentParser();
$modx->getSettings();

// Type=(大文字)は旧mcpuk互換。カスタム設定(imanager_url等)の移行漏れを救済する
$type = get_strvar('type', get_strvar('Type', 'images'));
$browserConfig = new BrowserConfig($modx);
if (!$browserConfig->isValidType($type)) {
    $type = 'images';
}

$scope = PickerScope::authorize($modx, $browserConfig, $type);
if (is_array($scope)) {
    http_response_code($scope['status']);
    if ($scope['code'] === 'unauthorized') {
        die('<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.');
    }
    die('<b>PERMISSION DENIED</b><br /><br />You do not have permission to access this file!');
}

$isModal = getv('modal') === '1';
$labels = [
    'images' => lang('fb_title_images'),
    'media' => lang('fb_title_media'),
    'files' => lang('fb_title_files'),
];

$jsConfig = BrowserView::jsConfig($modx, $browserConfig, [
    'entry' => 'picker',
    // モーダル断片は/manager/index.php上で動くため、相対パスの基準が異なる
    'apiUrl' => $isModal ? 'media/browser/evo/api.php' : 'api.php',
    'apiParams' => ['type' => $type],
    'initialFolder' => get_strvar('folder'),
    'initialSelect' => get_strvar('select'),
    'rootLabel' => $type,
    'maxUploadSize' => $scope->maxUploadSize(),
]);

if ($isModal) {
    BrowserView::modalFragment($jsConfig, $labels[$type]);
    exit;
}

BrowserView::standalonePage($modx, $jsConfig, $labels[$type]);
