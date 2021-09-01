<?php
/**
 * mm_createTab
 * @version 1.1 (2012-11-13)
 *
 * Create a new tab.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_createtab/1.1
 *
 * @copyright 2012
 */

function mm_createTab($name, $id, $roles = '', $templates = '', $intro = '', $width = '680'){

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ((event()->name != 'OnDocFormRender' && event()->name != 'OnPluginFormRender') || !useThisRule($roles, $templates)) {
        return;
    }

// Plugin page tabs use a differen name for the tab object
    $output = "//  -------------- mm_createTab :: Begin ------------- \n";

    $empty_tab = evo()->parseText('
<div class="tab-page" id="tab[+id+]">
<h2 class="tab">[+name+]</h2>
<div class="tabIntro" id="tab-intro-[+id+]">[+intro+]</div>
<table width="[+width+]" border="0" cellspacing="0" cellpadding="0" id="table-[+id+]">
</table>
</div>
    ',
        array(
            'id'=>$id,
            'name'=>$name,
            'intro'=>$intro,
            'width'=>$width,
        )
    );

    // Clean up for js output
    $output .= sprintf(
        "jQuery('div#'+mm_lastTab).after('%s');\n",
        str_replace(array("\n", "\t", "\r"), '', $empty_tab)
    );
    $output .= sprintf("mm_lastTab = 'tab%s';\n", $id);
    $output .= sprintf(
        '%s.addTabPage( document.getElementById( "tab%s" ) ); '
        , (event()->name === 'OnPluginFormRender') ? 'tp' : 'tpSettings'
        , $id
    );

    $output .= "//  -------------- mm_createTab :: End ------------- \n";

    event()->output($output . "\n");
}
