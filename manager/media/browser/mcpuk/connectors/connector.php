<?php
/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 *
 * Licensed under the terms of the GNU Lesser General Public License:
 *              http://www.opensource.org/licenses/lgpl-license.php
 *
 * For further information visit:
 *              http://www.fckeditor.net/
 *
 * "Support Open Source software. What about a donation today?"
 *
 * File Name: connector.php
 *      Main connector file, implements the State Pattern to
 *      redirect requests to the appropriate class based on
 *      the command name passed.
 *
 * File Authors:
 *              Grant French (grant@mcpuk.net)
 */
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);

$base_path = str_replace(
    ['\\', 'manager/media/browser/mcpuk/connectors/connector.php'],
    ['/', ''],
    __FILE__
);

require_once $base_path . 'manager/includes/document.parser.class.inc.php';
require_once $base_path . 'manager/includes/helpers.php';
require_once __DIR__ . '/ConnectorRequest.php';
require_once __DIR__ . '/ConnectorKernel.php';

if (!function_exists('unescape')) {
    function unescape($source, $iconv_to = 'UTF-8')
    {
        return ConnectorRequest::unescape($source, $iconv_to);
    }
}

$modx = new DocumentParser();
$modx->getSettings();

global $fckphp_config;
include_once __DIR__ . '/config.php';

$request = ConnectorRequest::fromGlobals(getv(), $_POST, $_FILES, $_SERVER, $_COOKIE);
$kernel = new ConnectorKernel($modx, $fckphp_config, __DIR__ . '/Commands');

$kernel->handle($request, isset($_SESSION) ? $_SESSION : []);
