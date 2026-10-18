<?php
//---------------------------------------------------------------------------------
// mm_widget_googlemap ver 0.12
// 2010 / Oori
// Free for all
//
// Features:
// - Based on Google Maps api v2
// - Support multiple maps in single document
// - Double click to set marker
// - Drag and drop support to move markers
// - Address search support (with multiple results)
//
// How to:
// 1. Create one or more TVs that will hold the geolocation (with 'Input Type' as 'Text')
// 2. Add rule to your mm_rules: mm_widget_googlemap(fields, apiKey, default, roles, templates)
//    (mandatory) 'fields' is the TV name(s)
//    (mandatory) 'apiKey' (free) Google Maps API Key (optional when working on localhost dev server)
//    (optional) 'default' is the default position of the map
//
// for example:
// mm_widget_googlemap('eventLocation,nearestTrainStation','long-long-api-key','35.1516,126.8665');
//---------------------------------------------------------------------------------
function mm_widget_googlemap($fields, $googleApiKey = '', $default = '', $roles = '', $templates = '')
{

    global $modx, $mm_fields, $mm_current_page, $modx_lang_attribute;
    $e = &$modx->event;

    if ($e->name === 'OnDocFormRender' && useThisRule($roles, $templates)) {
        $output = '';
        $callBack = '';

        $fields = makeArray($fields);
        $count = tplUseTvs($mm_current_page['template'], $fields);
        if ($count == false) {
            return;
        }

        $output .= "//  -------------- googlemap widget ------------- \n";
        $output .= includeJs($modx->config['base_url'] . 'assets/plugins/managermanager/widgets/googlemap/googlemap.js');

        foreach ($fields as $targetTv) {
            if (!isset($mm_fields[$targetTv])) {
                continue;
            }
            $callBack .= sprintf('mm_gmap.init(%s,%s);', js_literal($mm_fields[$targetTv]['fieldname']), js_literal($default));
        }
        $googleApiKey = rawurlencode((string)$googleApiKey);


        $output .= <<<EOP
        var gmap_script = document.createElement('script');
        gmap_script.src = 'https://maps.googleapis.com/maps/api/js?v=3.51&key=$googleApiKey&callback=initMap';
        gmap_script.async = true;
        window.initMap = function(){
          $callBack
        }
        document.body.appendChild(gmap_script);
EOP;
        $e->output($output . "\n");    // Send the output to the browser
    }
}
