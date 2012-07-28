//<?php
/**
 * ダッシュボード・MODXニュース
 * 
 * ダッシュボードにMODXニュースを表示します。
 *
 * @category 	plugin
 * @version 	0.1
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@events OnManagerWelcomeRender
 * @internal	@modx_category Manager and Admin
 * @internal    @installset base
 *
 * @author yama  / created: 2012/07/28
 */

global $_lang;

$modxnews = new MODXNEWS();
$feedData = $modxnews->run();

$modx_news_content             = $feedData['modx_news_content'];
$modx_security_notices_content = $feedData['modx_security_notices_content'];

// modx news
$ph['modx_news']         = $_lang["modx_news_tab"];
$ph['modx_news_title']   = $_lang["modx_news_title"];
$ph['modx_news_content'] = $modx_news_content;

// security notices
$ph['modx_security_notices'] = $_lang["security_notices_tab"];
$ph['modx_security_notices_title'] = $_lang["security_notices_title"];
$ph['modx_security_notices_content'] = $modx_security_notices_content;

$block = <<< EOT
<div class="tab-page" id="tabNews" style="padding-left:0; padding-right:0">
<!-- modx news -->
	<h2 class="tab">[+modx_news+]</h2>
	<script type="text/javascript">tpPane.addTabPage( document.getElementById( "tabNews" ) );</script>
	<div class="sectionHeader">[+modx_news_title+]</div>
	<div class="sectionBody">
		[+modx_news_content+]
	</div>
<!-- security notices -->
	<div class="sectionHeader">[+modx_security_notices_title+]</div>
	<div class="sectionBody">
		[+modx_security_notices_content+]
	</div>
</div>
EOT;
$block = $modx->parsePlaceholder($block,$ph);
$modx->event->output($block);


 /*
 *  MODx Manager Home Page Implmentation by pixelchutes (www.pixelchutes.com)
 *  Based on kudo's kRSS Module v1.0.72
 *
 *  Written by: kudo, based on MagpieRSS
 *  Contact: kudo@kudolink.com
 *  Created: 11/05/2006 (November 5)
 *  For: MODx cms (modxcms.com)
 *  Name: kRSS
 *  Version (MODx Module): 1.0.72
 *  Version (Magpie): 0.72
 */
class MODXNEWS {

	function MODXNEWS()
	{
	}
	
	function run()
	{
		global $modx;
		
		$itemsNumber = '3';
		
		$urls['modx_news_content']             = $modx->config['rss_url_news'];
		$urls['modx_security_notices_content'] = $modx->config['rss_url_security'];
		
		/* End of configuration
		NO NEED TO EDIT BELOW THIS LINE
		---------------------------------------------- */
		
		// include MagPieRSS
		require_once($modx->config['base_path'] . 'manager/media/rss/rss_fetch.inc');
		
		$feedData = array();
		$itemtpl = '<li><a href="[+link+]" target="_blank">[+title+]</a> - <b>[+pubdate+]</b><br />[+description+]</li>';
		
		// create Feed
		foreach ($urls as $section=>$url)
		{
			if(!$url)
			{
				$feedData[$section] = ' - ';
				continue;
			}
			$output = '';
			// While getting RSS, SESSION is closed temporarily.  
			if ( !headers_sent() )
			{
				$tmp_sessionname=session_name();
				session_write_close();
			}
			$rss = @fetch_rss($url);
			if ( isset($tmp_sessionname) )
			{
				session_start($tmp_sessionname);
			}
			if( !$rss )
			{
				$feedData[$section] = 'Failed to retrieve ' . $url;
				continue;
			}
			$output .= '<ul>';
			
			$items = array_slice($rss->items, 0, $itemsNumber);
			foreach ($items as $item)
			{
				$href    = $item['link'];
				$item['pubdate'] = $modx->toDateFormat(strtotime($item['pubdate']));
				$description = strip_tags($item['description']);
				if (strlen($description) > 199)
				{
					$description = mb_substr($description, 0, 200);
					$description .= $modx->parsePlaceholder('...<br />Read <a href="[+link+]" target="_blank">more</a>.',$item);
				}
				$output .= $modx->parsePlaceholder($itemtpl,$item);
			}
			$output .= '</ul>';
			$feedData[$section] = $output;
		}
		return $feedData;
	}
}
