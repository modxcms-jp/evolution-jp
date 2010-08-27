//<?php
/**
 * Personalize
 * 
 * ユーザーのログイン状態によって出力内容を変更
 *
 * @category 	snippet
 * @version 	2.1
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties 
 * @internal	@modx_category Login
 * @author  	Created By Ryan Thrash (modx@vertexworks.com)
 * @author  	powered up by kudo (kudo@kudolink.com)
 * @author  	powered up by yama(yamamoto@kyms.ne.jp)
 */

#::::::::::::::::::::::::::::::::::::::::
# Created By:Ryan Thrash (modx@vertexworks.com), 
#	and then powered up by kudo (kudo@kudolink.com)
#
# Date: Aug 03, 2006
#
# Changelog: 
# Dec 01, 05 -- initial release
# Jun 19, 06 -- updated description
# Jul 19, 06 -- hacked by kudo to output chunks
# Aug 03, 06 -- added placeholder for username
# Aug 27, 10 -- powered up all code
#
#::::::::::::::::::::::::::::::::::::::::
# Description: 	
#	Checks to see if webusers are logged in and displays yesChunk if the user
#	is logged or noChunk if user is not logged. Insert only the chunk name as
#	param, without {{}}. Can use a placeholder to output the username.
#	TESTED: can be used more than once per page.
#	TESTED: chunks can contain snippets.
#	
#	
# Params:
#	&yesChunk [string] [REQUIRED]
#		Output for LOGGED users
#
#	&noChunk [string] [REQUIRED] 
#		Output for NOT logged users
#
#	&ph [string] (optional) 
#		Placeholder for placing the username
#		ATTENTION!: place this ph only in yesChunk!
#	
#	&context [string] (optional) 
#		web|mgr
#
# Example Usage:
#
#	[[LoggedOrNot? &yesChunk=`Link` &noChunk=`Register` &ph=`name`]]
#
#	Having Chunks named {{Link}} and another {{Register}}, the first will be
#	published to registered user, the second to non-registered users.
#
#::::::::::::::::::::::::::::::::::::::::

# prepare params and variables

if     ($this->isFrontend() && isset ($_SESSION['webValidated'])) $current_context = 'web';
elseif ($this->isBackend()  && isset ($_SESSION['mgrValidated'])) $current_context = 'mgr';

$output = '';
$yesChunk = (isset($yesChunk))? $yesChunk : '';
$noChunk  = (isset($noChunk)) ? $noChunk  : '';
$ph       = (isset($ph))      ? $ph       : 'username';
$context  = (isset($context)) ? $context     : $current_context;

/*
$referer = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES);
$ua =      htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES);
$ip =      htmlspecialchars($_SERVER['REMOTE_ADDR'], ENT_QUOTES);
$host =    htmlspecialchars($_SERVER['REMOTE_HOST'], ENT_QUOTES);

$ua_strtolower = strtolower($_SERVER['HTTP_USER_AGENT']);
if    (strpos($ua_strtolower, 'firefox') !== false)     $browser = 'firefox';
elseif(strpos($ua_strtolower, 'trident/4.0') !== false) $browser = 'internet explorer 8';
elseif(strpos($ua_strtolower, 'msie') !== false)        $browser = 'internet explorer';
elseif(strpos($ua_strtolower, 'chrome') !== false)      $browser = 'chrome';
elseif(strpos($ua_strtolower, 'safari') !== false)      $browser = 'safari';
elseif(strpos($ua_strtolower, 'opera') !== false)       $browser = 'opera';
else $browser = 'other';

$modx->setPlaceholder('referer', $referer);
$modx->setPlaceholder('ua',      $ua);
$modx->setPlaceholder('browser', $browser);
$modx->setPlaceholder('ip',      $ip);
$modx->setPlaceholder('host',    $host);
*/

switch($context)
{
    case 'web':
        $username   = $_SESSION['webShortname'];
        $short_name = $_SESSION['webShortname'];
        $full_name  = $_SESSION['webFullname'];
        $email      = $_SESSION['webEmail'];
        $last_login = $_SESSION['webLastlogin'];
        break;
    case 'mgr':
    case 'manager':
        $username   = $_SESSION['mgrShortname'];
        $short_name = $_SESSION['mgrShortname'];
        $full_name  = $_SESSION['mgrFullname'];
        $email      = $_SESSION['mgrEmail'];
        $last_login = $_SESSION['mgrLastlogin'];
        break;
    default:
        $username = '';
}
if (!empty($context))
{
    if($yesChunk!=='')
    {
        $output = $modx->getChunk($yesChunk);
    }
    else
    {
        $output = 'username : ' . $username;
    }
    
    if(empty($last_login)) $last_login_text = 'first login';
    else                   $last_login_text = $modx->toDateFormat($last_login);
    
    $modx->setPlaceholder($ph,$username);
    $modx->setPlaceholder('short_name',  $short_name);
    $modx->setPlaceholder('full_name',   $full_name);
    $modx->setPlaceholder('email',       $email);
    $modx->setPlaceholder('last_login', $last_login_text);
}
else
{
    if($noChunk!=='')
    {
        $output = $modx->getChunk($noChunk);
    }
    else
    {
        $output = 'guest';
    }
}
return $output;
