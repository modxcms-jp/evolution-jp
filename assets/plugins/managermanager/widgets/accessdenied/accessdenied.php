<?php
/**
 * mm_widget_accessdenied
 * @version 1.1 (2012-11-13)
 *
 * Close access for some documents by ids.
 * Icon by designmagus.com
 * Originally written by Metaller
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_widget_accessdenied/1.1
 *
 * @copyright 2012
 */

function mm_widget_accessdenied($ids = '', $message = '', $roles = ''){
	global $modx;

	if ($modx->event->name === 'OnDocFormRender' && useThisRule($roles)){

		$output = "//  -------------- accessdenied widget include ------------- \n";
		
		if (in_array((int)$_GET['id'], makeArray($ids))){
            if (!$message) {
                $message = '<span>Access denied</span>Access to current document closed for security reasons.';
            }
			$output .= includeCss( __DIR__ . '/accessdenied.css');
			
			$output .= '
			$j("input, div, form[name=mutate]").remove(); // Remove all content from the page
			$j("body").prepend(\'<div id="aback"><div id="amessage">'.$message.'</div></div>\');
			$j("#aback").css({height: $j("body").height()} );';
		}

        $modx->event->output($output . "\n");
	}
}
