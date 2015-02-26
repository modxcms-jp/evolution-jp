<?php
if(!defined('MODX_BASE_PATH')){die('What are you doing? Get out of here!');}
/*
::::::::::::::::::::::::::::::::::::::::
 Snippet name: Wayfinder
 Short Desc: builds site navigation
 Version: 2.0.7
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

$wf_base_path = $modx->config['base_path'] . 'assets/snippets/wayfinder/';
$config_path = "{$wf_base_path}configs/";

//Include a custom config file if specified
@include("{$config_path}default.config.php");

$config = (!isset($config)) ? 'default' : trim($config);

if(substr($config, 0, 6) == '@CHUNK')                           eval('?>' . $modx->getChunk(trim(substr($config, 7))));
elseif(substr($config, 0, 5) == '@FILE')                        include($modx->config['base_path'] . trim(substr($config, 6)));
elseif(is_file("{$config_path}{$config}.config.php"))           include("{$config_path}{$config}.config.php");
elseif(is_file("{$config_path}{$config}"))                      include("{$config_path}{$config}");
elseif(is_file($modx->config['base_path'].ltrim($config, '/'))) include($modx->config['base_path'] . ltrim($config, '/'));

include_once($wf_base_path . 'wayfinder.inc.php');

if (class_exists('Wayfinder')) $wf = new Wayfinder();
else                           return 'error: Wayfinder class not found';

$wf->_config = array(
	'id' => isset($startId) ? $startId : $modx->documentIdentifier,
	'level' => isset($level) ? intval($level) : 0,
	'includeDocs' => isset($includeDocs) ? $includeDocs : 0,
	'excludeDocs' => isset($excludeDocs) ? $excludeDocs : 0,
	'where' => isset($where) ? $where : '',
	'ph' => isset($ph) ? $ph : FALSE,
	'debug' => isset($debug) ? TRUE : FALSE,
	'ignoreHidden' => isset($ignoreHidden) ? $ignoreHidden : FALSE,
	'hideSubMenus' => isset($hideSubMenus) ? $hideSubMenus : FALSE,
	'useWeblinkUrl' => isset($useWeblinkUrl) ? $useWeblinkUrl : TRUE,
	'fullLink' => isset($fullLink) ? $fullLink : true,
	'nl' => isset($removeNewLines) ? '' : "\n",
	'sortOrder' => isset($sortOrder) ? strtoupper($sortOrder) : 'ASC',
	'sortBy' => isset($sortBy) ? $sortBy : 'menuindex',
	'limit' => isset($limit) ? $limit : 0,
	'cssTpl' => isset($cssTpl) ? $cssTpl : FALSE,
	'jsTpl' => isset($jsTpl) ? $jsTpl : FALSE,
	'rowIdPrefix' => isset($rowIdPrefix) ? $rowIdPrefix : FALSE,
	'textOfLinks' => isset($textOfLinks) ? $textOfLinks : 'menutitle',
	'titleOfLinks' => isset($titleOfLinks) ? $titleOfLinks : 'pagetitle',
	'displayStart' => isset($displayStart) ? $displayStart : FALSE,
	'showPrivate' => isset($showPrivate) ? $showPrivate : FALSE,
	'entityEncode' => isset($entityEncode) ? $entityEncode : FALSE,
	'hereId' => isset($hereId) ? intval($hereId) : $modx->documentIdentifier
);

//get user class definitions
$wf->_css = array(
	'first' => isset($firstClass) ? $firstClass : '',
	'last' => isset($lastClass) ? $lastClass : 'last',
	'here' => isset($hereClass) ? $hereClass : 'active',
	'parent' => isset($parentClass) ? $parentClass : '',
	'row' => isset($rowClass) ? $rowClass : '',
	'outer' => isset($outerClass) ? $outerClass : '',
	'inner' => isset($innerClass) ? $innerClass : '',
	'level' => isset($levelClass) ? $levelClass: '',
	'self' => isset($selfClass) ? $selfClass : '',
	'weblink' => isset($webLinkClass) ? $webLinkClass : '',
);

//get user templates
$wf->_templates = array(
	'outerTpl' => isset($outerTpl) ? $outerTpl : '',
	'rowTpl' => isset($rowTpl) ? $rowTpl : '',
	'parentRowTpl' => isset($parentRowTpl) ? $parentRowTpl : '',
	'parentRowHereTpl' => isset($parentRowHereTpl) ? $parentRowHereTpl : '',
	'hereTpl' => isset($hereTpl) ? $hereTpl : '',
	'innerTpl' => isset($innerTpl) ? $innerTpl : '',
	'innerRowTpl' => isset($innerRowTpl) ? $innerRowTpl : '',
	'innerHereTpl' => isset($innerHereTpl) ? $innerHereTpl : '',
	'activeParentRowTpl' => isset($activeParentRowTpl) ? $activeParentRowTpl : '',
	'categoryFoldersTpl' => isset($categoryFoldersTpl) ? $categoryFoldersTpl : '',
	'startItemTpl' => isset($startItemTpl) ? $startItemTpl : '',
	'rowTplLast' => isset($rowTplLast) ? $rowTplLast : '',
);

//Process Wayfinder
$output = $wf->run();

if ($wf->_config['debug']) {
	$output .= $wf->renderDebugOutput();
}

//Ouput Results
if ($wf->_config['ph']) {
    $modx->setPlaceholder($wf->_config['ph'],$output);
    return;
} else {
    return $output;
}
?>