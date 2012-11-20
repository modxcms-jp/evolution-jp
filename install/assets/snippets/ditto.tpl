//<?php
/**
 * Ditto
 * 
 * リソースの一覧を出力。ブログ・索引・目録・新着情報一覧・履歴一覧など
 *
 * @category 	snippet
 * @version 	2.1.4
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties 
 * @internal	@modx_category Content
 * @internal    @installset base, sample
 */

/* Description:
 *      Aggregates documents to create blogs, article/news
 *      collections, and more,with full support for templating.
 * 
 * Author: 
 *      Mark Kaplan for MODx CMF
*/

//---Core Settings---------------------------------------------------- //

$ditto_version = "2.1.4";
    // Ditto version being executed

/*
    Param: ditto_base
    
    Purpose:
    Location of Ditto files

    Options:
    Any valid folder location containing the Ditto source code with a trailing slash

    Default:
    [(base_path)]assets/snippets/ditto/
*/
$ditto_base = isset($ditto_base) ? $modx->config['base_path'] . ltrim($ditto_base,'/') : $modx->config['base_path']."assets/snippets/ditto/";
$rs = include_once("{$ditto_base}/loader.inc.php");
return $rs;
