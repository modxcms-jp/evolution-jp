<?php

if(!isset($recent_update)||empty($recent_update)) return;
if(!isset($conditional_get)||$conditional_get!=1) return;
if(!empty($_POST))return;
if(defined('MODX_API_MODE')) return;

session_name($site_sessionname);
session_cache_limiter('');
session_start();
if(isset($_SESSION['mgrValidated'])) return;

$last_modified = gmdate('D, d M Y H:i:s T', $recent_update);
$etag          = md5($last_modified);

$HTTP_IF_MODIFIED_SINCE = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
$HTTP_IF_NONE_MATCH     = isset($_SERVER['HTTP_IF_NONE_MATCH'])     ? $_SERVER['HTTP_IF_NONE_MATCH']     : false;

header('Pragma: no-cache');
if ($HTTP_IF_MODIFIED_SINCE == $last_modified || strpos($HTTP_IF_NONE_MATCH,$etag)!==false)
{
	header('HTTP/1.1 304 Not Modified');
	header('Content-Length: 0');
	exit;
}
else
{
	header("Last-Modified: {$last_modified}");
	header("ETag: '{$etag}'");
}
