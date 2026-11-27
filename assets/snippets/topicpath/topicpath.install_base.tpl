//<?php
/**
 * TopicPath
 *
 * カスタマイズの自由度が高いパン屑リスト
 *
 * &showUnpublished=1（既定）: 非公開の文書を表示しているとき（view_unpublished 権限での確認時）は、
 *   非公開の文書もパン屑に含める。0 で常に非公開の文書を除外する
 *
 * &jsonld=1: パン屑の HTML の後ろに構造化データ（JSON-LD の BreadcrumbList）を付ける。既定は 0（出さない）。
 *   HTML と同じ段を出し、省略（topicGap）したときや、パン屑を出さないページでは出さない。
 *   "@id" は「そのページの URL#breadcrumb」。head の WebPage から次のように参照できる
 *     "breadcrumb": {"@id": "そのページの URL#breadcrumb"}
 *   microdata テーマや手書きの構造化データですでに出しているときは、二重に出さないよう &jsonld を付けない。
 *   新しく構造化データを使うなら、microdata テーマより JSON-LD をすすめる
 *
 * @category	snippet
 * @version 	2.2.1
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties &theme=Theme;list;simple,list,ol,bootstrap,bootstrap5,microdata,raw;simple
 * @internal	@modx_category Navigation
 * @internal    @installset base, sample
 * @author  	yamamoto https://kyms.jp
 */

include_once MODX_BASE_PATH . 'assets/snippets/topicpath/topicpath.class.inc.php';
$topicpath = new TopicPath();
return $topicpath->getTopicPath();
