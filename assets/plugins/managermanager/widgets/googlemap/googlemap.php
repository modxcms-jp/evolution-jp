<?php
//---------------------------------------------------------------------------------
// mm_widget_googlemap ver 0.11
// 2010 / Oori
// Free for all
//--------------------------------------------------------------------------------- 
function mm_widget_googlemap($fields, $googleApiKey='', $default='', $roles='', $templates='') {
	
	global $modx, $mm_fields,$mm_current_page;
	$e = &$modx->event;
	
	if (useThisRule($roles, $templates))
	{
		$output = '';
		$fields = makeArray($fields);
		$count = tplUseTvs($mm_current_page['template'], $fields);
		if ($count == false)
		{
			return;
		}
		
		$output .= "//  -------------- googlemap widget ------------- \n";
		$output .= includeJs($modx->config['base_url'] .'assets/plugins/managermanager/widgets/googlemap/googlemap.js');
		$output .= includeJs('http://maps.google.com/maps?file=api&v=2&sensor=false&key='.$googleApiKey.'&async=2');
		foreach ($fields as $targetTv) {
			$tv_id = $mm_fields[$targetTv]['fieldname'];
			$output .= "googlemap('$tv_id','$default');";
		}
	}
	
	$e->output($output . "\n");	// Send the output to the browser
}
