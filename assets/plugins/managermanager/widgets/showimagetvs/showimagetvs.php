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

    if ($w || $h) {
        if (!$w) $w = 300;
        if (!$h) $h = (int)$w * 0.3;
        $style = sprintf(
            'float:left;max-width:%dpx; max-height:%dpx; margin: 4px 0; cursor: pointer;',
            $w,
            $h
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

    $output = "//  -------------- mm_widget_showimagetvs :: Begin ------------- \n";
    $output .= includeJs(MODX_BASE_URL . 'assets/plugins/managermanager/widgets/showimagetvs/showimagetvs.js');

    $settings = js_value([
        'baseUrl' => MODX_BASE_URL,
        'thumbnailerUrl' => (string)$thumbnailerUrl,
        'w' => (int)$w,
        'h' => (int)$h,
        'style' => $style,
    ]);
    // Go through each TV
    foreach ($tvs as $tv) {
        $output .= sprintf('mmImageTvs.attach(%s, %s);' . "\n", js_literal('tv' . $tv['id']), $settings);
    }

    // Monitor the image TVs for changes
    $output .= "mmImageTvs.watch();\n";
    $output .= "//  -------------- mm_widget_showimagetvs :: End -------------\n";

    event()->output($output . "\n");
}

