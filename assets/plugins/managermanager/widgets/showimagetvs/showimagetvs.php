<?php
/**
 * mm_widget_showimagetvs
 * @version 1.1 (2012-11-13)
 *
 * Shows a preview of image TVs.
 * Emulates showimagestv plugin, which is not compatible with ManagerManager.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_widget_showimagetvs/1.1
 *
 * @copyright 2012
 */

function mm_widget_showimagetvs($tvs = '', $w = 300, $h = 100, $thumbnailerUrl = '', $roles = '', $templates = ''){
	global $modx, $mm_current_page;
	$e = &$modx->event;

	if ($e->name === 'OnDocFormRender' && useThisRule($roles, $templates)){
		$output = '';
		
		$base_url = $modx->config['base_url'];
		
		if (isset($w) || isset($h)){
			$w = isset($w) ? $w : 300;
			$h = isset($h) ? $h : 100;
			$style = "'max-width:{$w}px; max-height:{$h}px; margin: 4px 0; cursor: pointer;'";
		}else{
			$style = '';
		}
		
        // Does this page's template use any image TVs? If not, quit now!
		if($tvs) {
			$tvs = tplUseTvs($mm_current_page['template'], $tvs);
		} else {
			$tvs = tplUseTvs($mm_current_page['template'], $tvs, 'image');
		}
		if (!$tvs){
			return;
		}
		
		$output .= "//  -------------- mm_widget_showimagetvs :: Begin ------------- \n";
		
		// Go through each TV
		foreach ($tvs as $tv){
			$output .= '
// Adding preview for tv'.$tv['id'].'
$j("#tv'.$tv['id'].'").addClass("imageField").bind("change load", function(){
	var $this = $j(this),
		// Get the new URL
		url = $this.val();
	
	$this.data("lastvalue", url);
	
	$this.addClass("imageField");
	var url = $j(this).val();
	url = (url != "" && url.search(/^@[a-z]+/i) == -1) ? url : url.replace(new RegExp(/^@[a-z]+/i), "");
				url = (url != "" && url.search(/https?:\/\//i) == -1 && url.search(/^\//i) == -1) ? ("'.$base_url.'" + url) : url;
			';
			
			// If we have a PHPThumb URL
			if (!empty($thumbnailerUrl)){
				$output .= 'url = "'.$thumbnailerUrl.'?src="+escape(url)+"&w='.$w.'&h='.$h.'"; ' . "\n";
			}
			
			$output .= '
	// Remove the old preview tv'.$tv['id'].'
	$j("#tv'.$tv['id'].'PreviewContainer").remove();
	
	if (url != ""){
		// Create a new preview
		$j("#tv'.$tv['id'].'").parents("td").append("<div class=\"tvimage\" id=\"tv'.$tv['id'].'PreviewContainer\"><img src=\""+url+"\" style=\""+'.$style.'+"\" id=\"tv'.$tv['id'].'Preview\"/></div>");
		
		// Attach a browse event to the picture, so it can trigger too
		$j("#tv'.$tv['id'].'Preview").click(function(){
			BrowseServer("tv'.$tv['id'].'");
		});
	}
}).trigger("load"); // Trigger a change event on load

			';
		}
		
		$output .= '
		
// Monitor the image TVs for changes
checkImageTVupdates = function(){
	$j(".imageField").each(function(){
		var $this = $j(this);
		if ($this.val() != $this.data("lastvalue")){
			$this.trigger("change");
		}
	});
}

setInterval ("checkImageTVupdates();", 250);

		';
		
		$output .= "//  -------------- mm_widget_showimagetvs :: End ------------- \n";
		
		$e->output($output . "\n");
	}
}
