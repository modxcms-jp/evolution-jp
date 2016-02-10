<?php
/*
 * ************************************************************************
  MODX Content Management System and PHP Application Framework
  Managed and maintained by Raymond Irving, Ryan Thrash and the
  MODX community
 * ************************************************************************
  MODX is an opensource PHP/MySQL content management system and content
  management framework that is flexible, adaptable, supports XHTML/CSS
  layouts, and works with most web browsers, including Safari.

  MODX is distributed under the GNU General Public License
 * ************************************************************************

  MODX CMS and Application Framework ("MODX")
  Copyright 2005 and forever thereafter by Raymond Irving & Ryan Thrash.
  All rights reserved.

  This file and all related or dependant files distributed with this filie
  are considered as a whole to make up MODX.

  MODX is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  MODX is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with MODX (located in "/install/"); if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA

  For more information on MODX please visit http://modx.com/

 * *************************************************************************
  Originally based on Etomite by Alex Butter
 * *************************************************************************
 */

/**
 * Initialize Document Parsing
 * -----------------------------
 */
if(!isset($_SERVER['REQUEST_TIME_FLOAT'])) $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
$mstart = memory_get_usage();
$base_path = str_replace('index.php','', str_replace('\\', '/',__FILE__));
define('MODX_BASE_PATH', $base_path);

$cache_type = 1;
$cacheRefreshTime = 0;
$site_sessionname = '';
$site_status = '1';
if(is_file($base_path . 'assets/cache/basicConfig.php')) {
	include_once($base_path . 'assets/cache/basicConfig.php');
	if(isset($conditional_get)&&$conditional_get==1)
		include_once("{$base_path}/manager/includes/conditional_get.inc.php");
}
if (!defined('MODX_API_MODE')
    && $cache_type == 2
    && $site_status != 0
    && count($_POST) < 1
    && (time() < $cacheRefreshTime || $cacheRefreshTime==0)) {
    session_name($site_sessionname);
    session_cache_limiter('');
    session_start();
    if (!isset($_SESSION['mgrValidated'])) {
        session_write_close();
        $target = $base_path . 'assets/cache/pages/' . md5($_SERVER['REQUEST_URI']) . '.pageCache.php';
        if (is_file($target)) {
            $handle = fopen($target, 'rb');
            $output = fread($handle, filesize($target));
            unset($handle);
            list($head,$output) = explode('<!--__MODxCacheSpliter__-->',$output,2);
            if(strpos($head,'"text/html";')===false)
            {
            	$type=unserialize($head);
            	header('Content-Type:' . $type . '; charset=utf-8');
            }
            else header('Content-Type:text/html; charset=utf-8');
            $mem = (function_exists('memory_get_peak_usage')) ? memory_get_peak_usage() : memory_get_usage();
            $msize = $mem - $mstart;
            $units = array('B', 'KB', 'MB');
            $pos = 0;
            while ($msize >= 1024) {
                $msize /= 1024;
                $pos++;
            }
            $msize = round($msize, 2) . ' ' . $units[$pos];
            $totalTime = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
            $totalTime = sprintf('%2.4f s', $totalTime);
            $incs = get_included_files();
            $r = array('[^q^]'=>'0','[^qt^]'=>'0s','[^p^]'=>$totalTime,'[^t^]'=>$totalTime,'[^s^]'=>'bypass_cache','[^m^]'=>$msize,'[^f^]'=>count($incs));
            $output = strtr($output,$r);
            if (is_file("{$base_path}autoload.php"))
                $loaded_autoload = include_once("{$base_path}autoload.php");
            if ($output !== false) {
                echo $output;
                exit;
            }
        }
    }
}
if (!isset($loaded_autoload) && is_file("{$base_path}autoload.php"))
    include_once("{$base_path}autoload.php");

// initiate a new document parser
$modx = include_once('manager/includes/document.parser.class.inc.php');
$modx->mstart = $mstart;
$modx->cacheRefreshTime = $cacheRefreshTime;
if(isset($error_reporting)) $modx->error_reporting = $error_reporting;

// execute the parser if index.php was not included
if (defined('IN_PARSER_MODE')&&IN_PARSER_MODE==='true') {
    $result = $modx->executeParser();
    echo $result;
}
