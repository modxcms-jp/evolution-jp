<?php
//---------------------------------------------------------------------------------
// mm_widget_googlemap ver 0.12
// 2010 / Oori
// Free for all
//--------------------------------------------------------------------------------- 
function mm_widget_googlemap($fields, $googleApiKey='', $default='', $roles='', $templates='') {
	
	global $modx, $mm_fields,$mm_current_page;
	$e = &$modx->event;
	
	if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

		$output = '';
		$callBack ='';
		
		$fields = makeArray($fields);
		$count = tplUseTvs($mm_current_page['template'], $fields);
    if ($count == false) {
			return;
		}
		
		$output .= "//  -------------- googlemap widget ------------- \n";
    $output .= includeJs(MODX_BASE_URL . 'assets/plugins/managermanager/widgets/googlemap/googlemap.js');

    foreach ($fields as $targetTv) {
			$tv_id = $mm_fields[$targetTv]['fieldname'];
			$callBack .= "googlemap('$tv_id','$default');";
		}

  $params='';
    if (!empty($googleApiKey)) {
    $params = 'key=' . trim($googleApiKey);
  }

		$output .="
		jQuery.getScript('https://www.google.com/jsapi', function()
		{
			google.load('maps', '3', { other_params: '".$params."', callback: function()
			{
		" .
		$callBack
		 .
		"
			}});
		});
		";
		$e->output($output . "\n");	// Send the output to the browser
}
