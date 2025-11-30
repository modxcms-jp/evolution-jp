<?php
/**
 * Filename:       media/style/$modx->config['manager_theme']/style.php
 * Function:       Manager style variables for images and icons.
 * Encoding:       UTF-8
 * Credit:         icons by Mark James of FamFamFam http://www.famfamfam.com/lab/icons/
 * Date:           18-Mar-2010
 * Version:        1.1
 * MODX version:   1.0.6-
 */

$tab_your_info = 1;
$tab_online = 1;

$iconResources = 1;
$iconNewDoc = 1;
$iconSearch = 1;
$iconMessage = 1;

$iconElements = 1;
$iconSettings = 1;
$iconFileManager = 1;
$iconEventLog = 1;
$iconSysInfo = 1;
$iconHelp = 1;

include_once(__DIR__ . '/welcome.php');
if (is_file(__DIR__ . '/config.php')) {
    include __DIR__ . '/config.php';
}

require_once __DIR__ . '/../common/style_defaults.php';

$managerMenuHeight = isset($managerMenuHeight) ? (int)$managerMenuHeight : 86;
manager_style_set_default_menu_height($managerMenuHeight);

if ($tab_your_info == 1) tabYourInfo();
if ($tab_online == 1) tabOnlineUser();

if ($iconResources == 1) iconResources();
if ($iconNewDoc == 1) iconNewDoc();
if ($iconSearch == 1) iconSearch();
if ($iconMessage == 1) iconMessage();

if ($iconElements == 1) iconElements();
if ($iconFileManager == 1) iconFileManager();

if ($iconSettings == 1) iconSettings();
if ($iconEventLog == 1) iconEventLog();
if ($iconSysInfo == 1) iconSysInfo();
if ($iconHelp == 1) iconHelp();

$icon_path = manager_style_image_path('icons');
$tree_path = manager_style_image_path('tree');
$misc_path = manager_style_image_path('misc');

manager_style_set_defaults($_style, $_lang, $icon_path, $tree_path, $misc_path);
