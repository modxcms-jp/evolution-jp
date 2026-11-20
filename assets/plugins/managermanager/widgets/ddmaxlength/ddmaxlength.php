<?php
/**
 * mm_ddMaxLength
 * @version 1.0.1 (2012-01-13)
 *
 * Позволяет ограничить количество вводимых символов в TV.
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

function mm_ddMaxLength($tvs = '', $roles = '', $templates = '', $length = 150)
{

    global $modx, $content;
    $e = &$modx->Event;

    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = '';

    $base_url = config('base_url');

    // Which template is this page using?
    if (isset($content['template'])) {
        $page_template = $content['template'];
    } else {
        // If no content is set, it's likely we're adding a new page at top level.
        // So use the site default template. This may need some work as it might interfere with a default template set by MM?
        $page_template = config('default_template');
    }

    // Does this page's template use any image or file or text TVs?
    $tvs = tplUseTvs($page_template, $tvs, 'text,textarea');
    if ($tvs == false) {
        return;
    }


    $output .= "// ---------------- mm_ddMaxLength :: Begin ------------- \n";
    //General functions
    $output .= includeJs($base_url . 'assets/plugins/managermanager/widgets/ddmaxlength/ddmaxlength.js');
    $output .= includeCss($base_url . 'assets/plugins/managermanager/widgets/ddmaxlength/ddmaxlength.css');

    $fieldIds = [];
    foreach ($tvs as $tv) {
        $fieldIds[] = 'tv' . $tv['id'];
    }
    $output .= 'mmDdMaxLength.attach(' . js_value($fieldIds) . ', ' . (int)$length . ');' . "\n";
    $output .= 'mmDdMaxLength.guardSubmit();' . "\n";

    $output .= "\n// ---------------- mm_ddMaxLength :: End -------------";

    $e->output($output . "\n");
} // end of widget
