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

manager_style_initialize_theme(
    $_style,
    $_lang,
    manager_style_prepare_welcome_options()
);
