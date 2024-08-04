<?php
/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 *
 * Licensed under the terms of the GNU Lesser General Public License:
 * 		http://www.opensource.org/licenses/lgpl-license.php
 *
 * For further information visit:
 * 		http://www.fckeditor.net/
 *
 * "Support Open Source software. What about a donation today?"
 *
 * File Name: connector.php
 * 	Main connector file, implements the State Pattern to
 * 	redirect requests to the appropriate class based on
 * 	the command name passed.
 *
 * File Authors:
 * 		Grant French (grant@mcpuk.net)
 */
//Errors in the config.php could still cause problems.
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE', true);
$base_path = str_replace(
    array('\\', 'manager/media/browser/mcpuk/connectors/connector.php'),
    array('/', ''),
    __FILE__
);
include_once($base_path . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

if (!isset($_SESSION['mgrValidated']) && !isset($_SESSION['webValidated'])) {
    die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
}
$mcpuk_path = $base_path . "manager/media/browser/mcpuk/";

$modx->getSettings();

global $fckphp_config;
include_once 'config.php';

outputHeaders();

//Get the passed data
$command = getv('Command', '');
$type = getv('Type') ? strtolower(getv('Type')) : 'files';
$cwd = getv('CurrentFolder') ? str_replace('..', '', unescape(getv('CurrentFolder'))) : '/';
$extra = getv('ExtraParams', '');

if ($fckphp_config['Debug'] === true) {
    $msg = '$command=' . "{$command}\n";
    $msg .= '$type=' . $type . "\n";
    $msg .= '$cwd=' . $cwd . "\n";
    $msg .= '$extra=' . $extra . "\n";
    $msg .= '$_GET=' . print_r($_GET, true) . "\n";
    $msg .= '$_POST=' . print_r($_POST, true) . "\n";
    $msg .= '$_SERVER=' . print_r($_SERVER, true) . "\n";
    $msg .= '$_SESSIONS=' . print_r($_SESSION, true) . "\n";
    $msg .= '$_COOKIE=' . print_r($_COOKIE, true) . "\n";
    $msg .= '$_FILES=' . print_r($_FILES, true) . "\n";
    $modx->logEvent(
        0,
        1,
        nl2br(
            str_replace(' ', '&nbsp;', hsc($msg))
        ),
        'mcpuk connector'
    );
}

if (!in_array($command, $fckphp_config['Commands'])) {
    $modx->logEvent(0, 3, 'Invalid command.(No reason for me to be here)');
    exit(0);
}

if (!in_array($type, $fckphp_config['ResourceTypes'])) {
    $modx->logEvent(0, 3, 'Invalid resource type.');
    exit(0);
}

$rs = include($mcpuk_path . "connectors/Commands/" . $command . ".php");

$action = new $command($fckphp_config, $type, $cwd);
$action->run();

function outputHeaders()
{
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

function unescape($source, $iconv_to = 'UTF-8')
{
    $decodedStr = '';
    $pos = 0;
    $len = strlen($source);
    while ($pos < $len) {
        $charAt = substr($source, $pos, 1);
        if ($charAt !== '%') {
            $decodedStr .= $charAt;
            $pos++;
            continue;
        }
        $pos++;
        $charAt = substr($source, $pos, 1);
        if ($charAt !== 'u') {
            // we have an escaped ascii character
            $decodedStr .= chr(
                hexdec(
                    substr($source, $pos, 2)
                )
            );
            $pos += 2;
            continue;
        }
        // we got a unicode character
        $pos++;
        $decodedStr .= code2utf(
            hexdec(
                substr($source, $pos, 4)
            )
        );
        $pos += 4;
    }

    if ($iconv_to !== "UTF-8") {
        return iconv("UTF-8", $iconv_to, $decodedStr);
    }

    return $decodedStr;
}

/**
 * Function coverts number of utf char into that character.
 * Function taken from: http://sk2.php.net/manual/en/function.utf8-encode.php#49336
 *
 * @param int $num
 * @return string
 */
function code2utf($num)
{
    if ($num < 128) {
        return chr($num);
    }
    if ($num < 2048) {
        return chr(($num >> 6) + 192)
            . chr(($num & 63) + 128);
    }
    if ($num < 65536) {
        return chr(($num >> 12) + 224)
            . chr((($num >> 6) & 63) + 128)
            . chr(($num & 63) + 128);
    }
    if ($num < 2097152) {
        return chr(($num >> 18) + 240)
            . chr((($num >> 12) & 63) + 128)
            . chr((($num >> 6) & 63) + 128)
            . chr(($num & 63) + 128);
    }
    return '';
}
