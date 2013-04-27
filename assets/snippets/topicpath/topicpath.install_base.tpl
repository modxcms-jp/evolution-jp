//<?php
/**
 * TopicPath
 *
 * Breadcrumbs высокую степень свободы в настройке
 * 
 * @category	snippet
 * @version 	2.0.2
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties &theme=Theme;list;string,list;string
 * @internal	@modx_category Navigation
 * @internal    @installset base, sample
 * @author  	yamamoto http://kyms.jp
 */

include_once($modx->config['base_path'] . 'assets/snippets/topicpath/topicpath.class.inc.php');
$topicpath = new TopicPath();
return $topicpath->getTopicPath();
