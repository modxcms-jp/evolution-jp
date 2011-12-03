//<?php
/**
 * TopicPath
 *
 * カスタマイズの自由度が高いパン屑リスト
 * 
 * @category	snippet
 * @version 	1.0.5
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties &templateSet=TemplateSet;list;defaultString,defaultList;defaultString
 * @internal	@modx_category Navigation
 */

include_once($modx->config['base_path'] . 'assets/snippets/topicpath/topicpath.class.inc.php');
$topicpath = new TopicPath();
if(isset($tpl)) {$templateSet = $tpl; unset($tpl);}

/* General setup */
if(!isset($maxCrumbs))         $maxCrumbs         = 100;
if(!isset($pathThruUnPub))     $pathThruUnPub     = 1;
if(!isset($respectHidemenu))   $respectHidemenu   = 1;
if(!isset($showCurrentCrumb))  $showCurrentCrumb  = 1;
if(!isset($currentAsLink))     $currentAsLink     = 0;
if(!isset($linkTextField))     $linkTextField     = 'menutitle,pagetitle,longtitle';
if(!isset($linkDescField))     $linkDescField     = 'description,longtitle,pagetitle,menutitle';
if(!isset($showCrumbsAsLinks)) $showCrumbsAsLinks = 1;
if(!isset($templateSet))       $templateSet       = 'defaultString';
if(!isset($crumbGap))          $crumbGap          = '...';
if(!isset($stylePrefix))       $stylePrefix       = 'B_';

/* Home link parameters */
if(!isset($showHomeCrumb))        $showHomeCrumb  = 1;
if(!isset($homeId))               $homeId = $modx->config['site_start'];
if(!isset($homeCrumbTitle))       $homeCrumbTitle = '';
if(!isset($homeCrumbDescription)) $homeCrumbDescription = '';

/* Custom behaviors */
if(!isset($showCrumbsAtHome)) $showCrumbsAtHome = 0;
if(!isset($hideOn))           $hideOn    = '';
if(!isset($hideUnder))        $hideUnder = '';
if(!isset($stopIds))          $stopIds   = '';
if(!isset($ignoreIds))        $ignoreids = '';

/* Templates */
$tpl = $topicpath->get_default_tpl($templateSet);
switch(strtolower($templateSet))
{
	case 'defaultstring':
	case 'defaultlist':
	case 'list':
	case 'li':
		break;
	default:
		$chunk_tpl = $topicpath->get_chunk_tpl($templateSet);
		print_r($chunk_tpl);
		$tpl = array_merge($tpl,$chunk_tpl);
}

if(isset($crumb))             $tpl['crumb']             = $topicpath->fetch($crumb);
if(isset($separator))         $tpl['separator']         = $topicpath->fetch($separator);
if(isset($crumbContainer))    $tpl['crumbContainer']    = $topicpath->fetch($crumbContainerv);
if(isset($lastCrumbWrapper))  $tpl['lastCrumbWrapper']  = $topicpath->fetch($lastCrumbWrapper);
if(isset($firstCrumbWrapper)) $tpl['firstCrumbWrapper'] = $topicpath->fetch($firstCrumbWrapper);

/* main */
$params = compact('showCrumbsAtHome','homeId','hideOn','hideUnder','showHomeCrumb','homeId','showCurrentCrumb','respectHidemenu','ignoreIds','pathThruUnPub','stopIds','showCurrentCrumb','homeId','homeCrumbTitle','linkTextField','stylePrefix','showCrumbsAsLinks','currentAsLink','homeCrumbDescription','linkDescField','showHomeCrumb','maxCrumbs','crumbGap');
if($topicpath->get_condition($params,$modx)===false) return;

$params['crumbs'] = $topicpath->get_crumbs_info($params,$modx);
$last = (count($tpl_crumbs)-1);

$tpl_crumbs        = $topicpath->process_each_crumb($params,$modx);
$tpl_crumbs[0]     = $topicpath->get_first_crumb($tpl['firstCrumbWrapper'],$tpl_crumbs[0],$stylePrefix);
$tpl_crumbs[$last] = $topicpath->get_last_crumb($tpl['lastCrumbWrapper'],$tpl_crumbs[$last],$stylePrefix);

$crumbs = array();
foreach ($tpl_crumbs as $pc)
{
	$crumbs[] = str_replace('[+crumb+]',$pc,$tpl['crumb']);
}

$ph = array();
$ph['crumbBoxClass'] = $stylePrefix.'crumbBox';
$ph['crumbs']        = join($tpl['separator'],$crumbs);

return $topicpath->parse_ph($tpl['crumbContainer'],$ph);
