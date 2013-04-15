<?php
return array (
  'Ditto' => 'return @require MODX_BASE_PATH.\'assets/snippets/ditto/snippet.ditto.php\';
',
  'eForm' => '# eForm 1.4.4.7 - Electronic Form Snippet
# Original created by Raymond Irving 15-Dec-2004.
# Version 1.3+ extended by Jelle Jager (TobyL) September 2006
# -----------------------------------------------------
# Captcha image support - thanks to Djamoer
# Multi checkbox, radio, select support - thanks to Djamoer
# Form Parser and extened validation - by Jelle Jager
#

# Set Snippet Paths
$snip_dir = isset($snip_dir) ? $snip_dir : \'eform\';
$snipPath = "{$modx->config[\'base_path\']}assets/snippets/{$snip_dir}/";

# check if inside manager
if ($modx->isBackend()) return \'\'; // don\'t go any further when inside manager

# Start processing

$version = \'1.4.4.7\';
include_once ("{$snipPath}eform.inc.php");

$output = eForm($modx,$params);

# Return
return $output;',
  'eFormProps' => '&sendAsText=テキストで送る;string;1 ',
  'TopicPath' => 'include_once($modx->config[\'base_path\'] . \'assets/snippets/topicpath/topicpath.class.inc.php\');
$topicpath = new TopicPath();
return $topicpath->getTopicPath();
',
  'TopicPathProps' => '&theme=Theme;list;string,list;string ',
  'Wayfinder' => '/*
::::::::::::::::::::::::::::::::::::::::
 Snippet name: Wayfinder
 Short Desc: builds site navigation
 Version: 2.0
 Authors: 
	Kyle Jaebker (muddydogpaws.com)
	Ryan Thrash (vertexworks.com)
 Date: February 27, 2006
::::::::::::::::::::::::::::::::::::::::
Description:
    Totally refactored from original DropMenu nav builder to make it easier to
    create custom navigation by using chunks as output templates. By using templates,
    many of the paramaters are no longer needed for flexible output including tables,
    unordered- or ordered-lists (ULs or OLs), definition lists (DLs) or in any other
    format you desire.
::::::::::::::::::::::::::::::::::::::::
Example Usage:
    [[Wayfinder? &startId=`0`]]
::::::::::::::::::::::::::::::::::::::::
*/

$wf_base_path = $modx->config[\'base_path\'] . \'assets/snippets/wayfinder/\';
$config_path = "{$wf_base_path}configs/";

//Include a custom config file if specified
@include("{$config_path}default.config.php");

$config = (!isset($config)) ? \'default\' : trim($config);

if(substr($config, 0, 6) == \'@CHUNK\')                           eval(\'?>\' . $modx->getChunk(trim(substr($config, 7))));
elseif(substr($config, 0, 5) == \'@FILE\')                        include_once($modx->config[\'base_path\'] . trim(substr($config, 6)));
elseif(is_file("{$config_path}{$config}.config.php"))           include_once("{$config_path}{$config}.config.php");
elseif(is_file("{$config_path}{$config}"))                      include_once("{$config_path}{$config}");
elseif(is_file($modx->config[\'base_path\'].ltrim($config, \'/\'))) include_once($modx->config[\'base_path\'] . ltrim($config, \'/\'));

include_once($wf_base_path . \'wayfinder.inc.php\');

if (class_exists(\'Wayfinder\')) $wf = new Wayfinder();
else                           return \'error: Wayfinder class not found\';

$wf->_config = array(
	\'id\' => isset($startId) ? $startId : $modx->documentIdentifier,
	\'level\' => isset($level) ? $level : 0,
	\'includeDocs\' => isset($includeDocs) ? $includeDocs : 0,
	\'excludeDocs\' => isset($excludeDocs) ? $excludeDocs : 0,
	\'ph\' => isset($ph) ? $ph : FALSE,
	\'debug\' => isset($debug) ? TRUE : FALSE,
	\'ignoreHidden\' => isset($ignoreHidden) ? $ignoreHidden : FALSE,
	\'hideSubMenus\' => isset($hideSubMenus) ? $hideSubMenus : FALSE,
	\'useWeblinkUrl\' => isset($useWeblinkUrl) ? $useWeblinkUrl : TRUE,
	\'fullLink\' => isset($fullLink) ? $fullLink : FALSE,
	\'nl\' => isset($removeNewLines) ? \'\' : "\\n",
	\'sortOrder\' => isset($sortOrder) ? strtoupper($sortOrder) : \'ASC\',
	\'sortBy\' => isset($sortBy) ? $sortBy : \'menuindex\',
	\'limit\' => isset($limit) ? $limit : 0,
	\'cssTpl\' => isset($cssTpl) ? $cssTpl : FALSE,
	\'jsTpl\' => isset($jsTpl) ? $jsTpl : FALSE,
	\'rowIdPrefix\' => isset($rowIdPrefix) ? $rowIdPrefix : FALSE,
	\'textOfLinks\' => isset($textOfLinks) ? $textOfLinks : \'menutitle\',
	\'titleOfLinks\' => isset($titleOfLinks) ? $titleOfLinks : \'pagetitle\',
	\'displayStart\' => isset($displayStart) ? $displayStart : FALSE,
	\'showPrivate\' => isset($showPrivate) ? $showPrivate : FALSE,
);

//get user class definitions
$wf->_css = array(
	\'first\' => isset($firstClass) ? $firstClass : \'\',
	\'last\' => isset($lastClass) ? $lastClass : \'last\',
	\'here\' => isset($hereClass) ? $hereClass : \'active\',
	\'parent\' => isset($parentClass) ? $parentClass : \'\',
	\'row\' => isset($rowClass) ? $rowClass : \'\',
	\'outer\' => isset($outerClass) ? $outerClass : \'\',
	\'inner\' => isset($innerClass) ? $innerClass : \'\',
	\'level\' => isset($levelClass) ? $levelClass: \'\',
	\'self\' => isset($selfClass) ? $selfClass : \'\',
	\'weblink\' => isset($webLinkClass) ? $webLinkClass : \'\',
);

//get user templates
$wf->_templates = array(
	\'outerTpl\' => isset($outerTpl) ? $outerTpl : \'\',
	\'rowTpl\' => isset($rowTpl) ? $rowTpl : \'\',
	\'parentRowTpl\' => isset($parentRowTpl) ? $parentRowTpl : \'\',
	\'parentRowHereTpl\' => isset($parentRowHereTpl) ? $parentRowHereTpl : \'\',
	\'hereTpl\' => isset($hereTpl) ? $hereTpl : \'\',
	\'innerTpl\' => isset($innerTpl) ? $innerTpl : \'\',
	\'innerRowTpl\' => isset($innerRowTpl) ? $innerRowTpl : \'\',
	\'innerHereTpl\' => isset($innerHereTpl) ? $innerHereTpl : \'\',
	\'activeParentRowTpl\' => isset($activeParentRowTpl) ? $activeParentRowTpl : \'\',
	\'categoryFoldersTpl\' => isset($categoryFoldersTpl) ? $categoryFoldersTpl : \'\',
	\'startItemTpl\' => isset($startItemTpl) ? $startItemTpl : \'\',
	\'rowTplLast\' => isset($rowTplLast) ? $rowTplLast : \'\',
);

//Process Wayfinder
$output = $wf->run();

if($wf->_config[\'debug\']) $output .= $wf->renderDebugOutput();

//Ouput Results
if($wf->_config[\'ph\']) $modx->setPlaceholder($wf->_config[\'ph\'],$output);
else                   return $output;
',
  'WebLogin' => '# Created By Raymond Irving 2004
#::::::::::::::::::::::::::::::::::::::::
# Params:	
#
#	&loginhomeid 	- (Optional)
#		redirects the user to first authorized page in the list.
#		If no id was specified then the login home page id or 
#		the current document id will be used
#
#	&logouthomeid 	- (Optional)
#		document id to load when user logs out	
#
#	&pwdreqid 	- (Optional)
#		document id to load after the user has submited
#		a request for a new password
#
#	&pwdactid 	- (Optional)
#		document id to load when the after the user has activated
#		their new password
#
#	&logintext		- (Optional) 
#		Text to be displayed inside login button (for built-in form)
#
#	&logouttext 	- (Optional)
#		Text to be displayed inside logout link (for built-in form)
#	
#	&tpl			- (Optional)
#		Chunk name or document id to as a template
#	
#	Note: Templats design:
#			section 1: login template
#			section 2: logout template 
#			section 3: password reminder template 
#
#			See weblogin.tpl for more information
#
# Examples:
#
#	[!WebLogin? &loginhomeid=`8` &logouthomeid=`1`!] 
#
#	[!WebLogin? &loginhomeid=`8,18,7,5` &tpl=`Login`!]

# Set Snippet Paths 
$snipPath = $modx->config[\'base_path\'] . "assets/snippets/";

# check if inside manager
if ($m = $modx->isBackend()) {
	return \'\'; // don\'t go any further when inside manager
}

# deprecated params - only for backward compatibility
if(isset($loginid))  $loginhomeid=$loginid;
if(isset($logoutid)) $logouthomeid = $logoutid;
if(isset($template)) $tpl = $template;

# Snippet customize settings
$liHomeId   = isset($loginhomeid) ? explode(\',\',$loginhomeid):array($modx->config[\'login_home\'],$modx->documentIdentifier);
$loHomeId   = isset($logouthomeid)? $logouthomeid:$modx->documentIdentifier;
$pwdReqId   = isset($pwdreqid)    ? $pwdreqid:0;
$pwdActId   = isset($pwdactid)    ? $pwdactid:0;
$loginText  = isset($logintext)   ? $logintext:\'Login\';
$logoutText = isset($logouttext)  ? $logouttext:\'Logout\';
$tpl        = isset($tpl)         ? $tpl:\'\';

# System settings
$webLoginMode  = isset($_REQUEST[\'webloginmode\'])? $_REQUEST[\'webloginmode\']: \'\';
$isLogOut      = $webLoginMode==\'lo\' ? 1:0;
$isPWDActivate = $webLoginMode==\'actp\' ? 1:0;
$isPostBack    = count($_POST) && (isset($_POST[\'cmdweblogin\']) || isset($_POST[\'cmdweblogin_x\']));
$txtPwdRem     = isset($_REQUEST[\'txtpwdrem\'])? $_REQUEST[\'txtpwdrem\']: 0;
$isPWDReminder = $isPostBack && $txtPwdRem==\'1\' ? 1:0;

$site_id = isset($site_id) ? $site_id: \'\';
$cookieKey = substr(md5("{$site_id}Web-User"),0,15);

# Start processing
include_once("{$snipPath}weblogin/weblogin.common.inc.php");
include_once("{$snipPath}weblogin/crypt.class.inc.php");

if ($isPWDActivate || $isPWDReminder || $isLogOut || $isPostBack) {
	# include the logger class
	include_once $modx->config[\'base_path\'] . "manager/includes/log.class.inc.php";
	include_once "{$snipPath}weblogin/weblogin.processor.inc.php";
}

include_once "{$snipPath}weblogin/weblogin.inc.php";

# Return
return $output;
',
  'WebLoginProps' => '&loginhomeid=Login Home Id;string; &logouthomeid=Logout Home Id;string; &logintext=Login Button Text;string; &logouttext=Logout Button Text;string; &tpl=Template;string; ',
);