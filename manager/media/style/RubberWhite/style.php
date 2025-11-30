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

include_once(__DIR__ . '/welcome.php');
if (is_file(__DIR__ . '/config.php')) {
    include __DIR__ . '/config.php';
}

require_once __DIR__ . '/../common/style_defaults.php';

$welcomeOptions = [
    'tab_your_info' => &$tab_your_info,
    'tab_online' => &$tab_online,
    'iconResources' => &$iconResources,
    'iconNewDoc' => &$iconNewDoc,
    'iconSearch' => &$iconSearch,
    'iconMessage' => &$iconMessage,
    'iconElements' => &$iconElements,
    'iconSettings' => &$iconSettings,
    'iconFileManager' => &$iconFileManager,
    'iconEventLog' => &$iconEventLog,
    'iconSysInfo' => &$iconSysInfo,
    'iconHelp' => &$iconHelp,
];

manager_style_set_default_welcome_options($welcomeOptions);
unset($welcomeOptions);

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

manager_style_set_defaults($_style, $_lang);
