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

function mm_widget_showimagetvs($tvs = '', $w = 300, $h = 100, $thumbnailerUrl = '', $roles = '', $templates = '')
{
    global $mm_current_page;

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = '';

    if ($w || $h) {
        if (!$w) $w = 300;
        if (!$h) $h = (int)$w * 0.3;
        $style = sprintf(
            "'float:left;max-width:%dpx; max-height:%dpx; margin: 4px 0; cursor: pointer;'"
            , $w
            , $h
        );
    } else {
        $style = '';
    }

    // Does this page's template use any image TVs? If not, quit now!
    if ($tvs) {
        $tvs = tplUseTvs($mm_current_page['template'], $tvs);
    } else {
        $tvs = tplUseTvs($mm_current_page['template'], $tvs, 'image');
    }
    if (!$tvs) {
        return;
    }

    $output .= "//  -------------- mm_widget_showimagetvs :: Begin ------------- \n";

    $tpl1 = '
// Adding preview for tv[+id+]\
jQuery("#tv[+id+]").addClass("imageField").bind("change load", function(){
	var $this = jQuery(this),
		// Get the new URL
		url = $this.val();

	$this.data("lastvalue", url);

	$this.addClass("imageField");
	var url = jQuery(this).val();
	url = (url != "" && url.search(/^@[a-z]+/i) == -1) ? url : url.replace(new RegExp(/^@[a-z]+/i), "");
	url = (url != "" && url.search(/https?:\\/\\//i) == -1 && url.search(/^\\//i) == -1) ? ("[+base_url+]" + url) : url;' . "\n";

    $tpl2 = '
	// Remove the old preview tv[+id+]
	jQuery("#tv[+id+]PreviewContainer").remove();

	if (url != "" && !url.match("/.*::.*/")){
		// Create a new preview
		jQuery("#tv[+id+]").parents("td").append(\'<div class="tvimage" id="tv[+id+]PreviewContainer"><img src="\'+url+\'" style="\'+[+style+]+\'" id="tv[+id+]Preview"/></div>\');

		// Attach a browse event to the picture, so it can trigger too
		jQuery("#tv[+id+]Preview").click(function(){
			BrowseServer("tv[+id+]");
		});
	}
}).trigger("load"); // Trigger a change event on load

			' . "\n";
    // Go through each TV
    $ph = array(
        'base_url' => MODX_BASE_URL,
        'thumbnailerUrl' => $thumbnailerUrl,
        'w' => $w,
        'h' => $h
    );
    foreach ($tvs as $tv) {
        $ph['id'] = $tv['id'];
        $output .= evo()->parseText($tpl1, $ph);

        if ($thumbnailerUrl) {
            $output .= evo()->parseText(
                'url = "%s?src="+escape(url)+"&w=%d&h=%d";' . "\n"
                , $ph
            );
        }
        $ph['style'] = $style;
        $output .= evo()->parseText($tpl2, $ph);
    }

    $output .= '

// Monitor the image TVs for changes
checkImageTVupdates = function(){
	jQuery(".imageField").each(function(){
		var $this = jQuery(this);
		if ($this.val() != $this.data("lastvalue")){
			$this.trigger("change");
		}
	});
}

setInterval ("checkImageTVupdates();", 250);

//  -------------- mm_widget_showimagetvs :: End -------------

';
    event()->output($output . "\n");
}
