<?php
	function renderFormElement($field_type, $field_id, $default_text, $field_elements, $field_value, $field_style='', $row = array())
		{global $modx;$modx->loadExtension('SubParser');return $modx->sub->renderFormElement($field_type, $field_id, $default_text, $field_elements, $field_value, $field_style,$row);}
	function ParseInputOptions($v)
		{global $modx;$modx->loadExtension('SubParser');return $modx->sub->ParseInputOptions($v);}
	function splitOption($value)
		{global $modx;$modx->loadExtension('SubParser');return $modx->sub->splitOption($value);}
	function isSelected($label,$value,$item,$field_value)
		{global $modx;$modx->loadExtension('SubParser');return $modx->sub->isSelected($label,$value,$item,$field_value);}
