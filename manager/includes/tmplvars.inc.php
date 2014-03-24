<?php
	function renderFormElement($field_type, $field_id, $default_text, $field_elements, $field_value, $field_style='', $row = array())
		{global $modx;return $modx->renderFormElement($f_type,$f_id,$default_text,$f_elements,$f_value, $f_style,$row);}
	function ParseIntputOptions($v)
		{global $modx;return $modx->ParseIntputOptions($v);}
	function splitOption($value)
		{global $modx;return $modx->splitOption($value);}
	function isSelected($label,$value,$item,$field_value)
		{global $modx;return $modx->isSelected($label,$value,$item,$field_value);}
