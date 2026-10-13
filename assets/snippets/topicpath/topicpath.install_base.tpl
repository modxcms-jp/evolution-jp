//<?php
/**
 * TopicPath
 *
 * カスタマイズの自由度が高いパン屑リスト
 *
 * &showUnpublished=1（既定）: 非公開の文書を表示しているとき（view_unpublished 権限での確認時）は、
 *   非公開の文書もパン屑に含める。0 で常に非公開の文書を除外する
 * 
 * @category	snippet
 * @version 	2.1.0
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties &theme=Theme;list;simple,list,bootstrap,bootstrap5,microdata,raw;simple
 * @internal	@modx_category Navigation
 * @internal    @installset base, sample
 * @author  	yamamoto https://kyms.jp
 */

include_once MODX_BASE_PATH . 'assets/snippets/topicpath/topicpath.class.inc.php';
$topicpath = new TopicPath();
return $topicpath->getTopicPath();
