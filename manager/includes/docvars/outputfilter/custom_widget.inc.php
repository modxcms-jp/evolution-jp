<?php
	if(!defined('IN_PARSER_MODE') || IN_PARSER_MODE != 'true') exit();

	$widget_output = '';
	$o = '';
	/* If we are loading a file */
	$params['output'] = $this->parseText($params['output'],array('value'=>$value,'tvname'=>$name),'[+','+]',false);
	
	if(substr($params['output'], 0, 5)==='<?php') $params['output'] = "@EVAL:\n" . substr($params['output'],5);
	if(substr($params['output'], 0, 5) == '@FILE')
	{
		$file_name = MODX_BASE_PATH . trim(substr($params['output'], 6));
		if(is_file($file_name)) $widget_output = file_get_contents($file_name);
		else                    $widget_output = $file_name . ' does not exist';
	}
	elseif(substr($params['output'], 0, 8) == '@INCLUDE')
	{
		$file_name = MODX_BASE_PATH . trim(substr($params['output'], 9));
		if(is_file($file_name)) include $file_name;
		else                    $widget_output = $file_name . ' does not exist';
		/* The included file needs to set $widget_output. Can be string, array, object */
	}
	elseif(substr($params['output'], 0, 6) == '@CHUNK' && $value !== '')
	{
		$chunk_name = trim(substr($params['output'], 7));
		$widget_output = $this->getChunk($chunk_name);
	}
	elseif(substr($params['output'], 0, 5) == '@EVAL')
	{
		$tvname = $name;
		$eval_str = trim(substr($params['output'], 6));
		$widget_output = eval($eval_str);
	}
	elseif($value==='')
		return;
	else
		$widget_output = $params['output'];
	
	if(is_string($widget_output)) // Except @INCLUDE
	{
		if(strpos($widget_output,'[+')!==false)
			$widget_output = $this->parseText($widget_output,array('value'=>$value,'tvname'=>$name),'[+','+]',false);
		
		$o = $this->parseDocumentSource($widget_output);
	}
	else
	{
		$o = $widget_output;
	}
	return $o;
