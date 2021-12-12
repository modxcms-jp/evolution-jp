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

    $base_url = $modx->config['base_url'];

    // Which template is this page using?
    if (isset($content['template'])) {
        $page_template = $content['template'];
    } else {
        // If no content is set, it's likely we're adding a new page at top level.
        // So use the site default template. This may need some work as it might interfere with a default template set by MM?
        $page_template = $modx->config['default_template'];
    }

    // Does this page's template use any image or file or text TVs?
    $tvs = tplUseTvs($page_template, $tvs, 'text,textarea');
    if ($tvs == false) {
        return;
    }


    $output .= "// ---------------- mm_ddMaxLength :: Begin ------------- \n";
    //General functions
    $output .= includeJs($base_url . 'assets/plugins/managermanager/widgets/ddmaxlength/jquery.ddmaxlength-1.0.min.js');
    $output .= includeCss($base_url . 'assets/plugins/managermanager/widgets/ddmaxlength/ddmaxlength.css');

    foreach ($tvs as $tv) {
        $output .= '
jQuery("#tv' . $tv['id'] . '").addClass("ddMaxLengthField").each(function(){
jQuery(this).parent().append("<div class=\"ddMaxLengthCount\"><span></span></div>");
}).ddMaxLength({
max: ' . $length . ',
containerSelector: "div.ddMaxLengthCount span",
warningClass: "maxLenghtWarning"
});
        ';
    }

    $output .= '
jQuery("#mutate").submit(function(){
var ddErrors = new Array();
jQuery("div.ddMaxLengthCount span").each(function(){
    var $this = jQuery(this), field = $this.parents(".ddMaxLengthCount:first").parent().find(".ddMaxLengthField");
    if (parseInt($this.text()) < 0){
        field.addClass("maxLenghtErrorField").focus(function(){
            field.removeClass("maxLenghtErrorField");
        });
        ddErrors.push(field.parents("tr").find("td:first-child .warning").text());
    }
});

if(ddErrors.length > 0){
    alert("Некорректно заполнены поля: " + ddErrors.join(","));

    return false;
} else {
    return true;
}
});
    ';

    $output .= "\n// ---------------- mm_ddMaxLength :: End -------------";

    $e->output($output . "\n");
} // end of widget
