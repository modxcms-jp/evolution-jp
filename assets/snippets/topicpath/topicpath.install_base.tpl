//<?php
/**
 * TopicPath
 *
 * カスタマイズの自由度が高いパン屑リスト
 * 
 * @category	snippet
 * @version 	2.0.4
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties &theme=Theme;list;simple,list,bootstrap,bootstrap5,microdata,raw;simple
 * @internal	@modx_category Navigation
 * @internal    @installset base, sample
 * @author  	yamamoto https://kyms.jp
 */

include_once MODX_BASE_PATH . 'assets/snippets/topicpath/topicpath.class.inc.php';
$topicpath = new TopicPath();
return $topicpath->getTopicPath();
