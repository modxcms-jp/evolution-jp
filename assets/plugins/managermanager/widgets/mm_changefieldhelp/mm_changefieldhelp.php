<?php
/**
 * mm_changeFieldHelp
 * @version 1.1 (2012-11-13)
 *
 * Change the help text of a field.
 * 
 * @uses ManagerManager plugin 0.4.
 * 
 * @link http://code.divandesign.biz/modx/mm_changefieldhelp/1.1
 * 
 * @copyright 2012
 */

function mm_changeFieldHelp($field, $helptext='', $roles='', $templates=''){
	global $mm_fields, $modx;
	$e = &$modx->event;

	if ($helptext == ''){
		return;
	}

	// if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
	if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }
		
		// What type is this field?
		if (isset($mm_fields[$field])){
        $output = "//  -------------- mm_changeFieldHelp :: Begin ------------- \n";
			$fieldtype = $mm_fields[$field]['fieldtype'];
			$fieldname = $mm_fields[$field]['fieldname'];
			
			//Is this TV?
			if ($mm_fields[$field]['tv']){
            $output .= sprintf(
                'jQuery("%s[name=%s]").parents("td:first").prev("td").children("span.comment").html("%s");'
                , $fieldtype
                , $fieldname
                , jsSafe($helptext)
            );
				//Or document field
			}else{
				// Give the help button an ID, and modify the alt/title text
            $output .= sprintf(
                'jQuery("%s[name=%s]").siblings("img.tooltip").prop("id", "%s-help").prop("title", "%s").prop("alt", "%s").data("powertip", "%s"); '
                , $fieldtype
                , $fieldname
                , $fieldname
                , jsSafe($helptext)
                , jsSafe($helptext)
                , jsSafe($helptext)
            );
			}
        $output .= "//  -------------- mm_changeFieldHelp :: End ------------- \n";
		}
		
		$e->output($output . "\n");
	}
