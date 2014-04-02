<?php
	if(!defined('IN_PARSER_MODE') || IN_PARSER_MODE != 'true') exit();

	$images = $this->parseInput($value, '||', 'array');
	$o = '';
	foreach($images as $image)
	{
		if(!is_array($image)) $image = explode('==',$image);
		$src = $image[0];
		
		if($src)
		{
			// We have a valid source
			$src = $this->parseText($params['output'],array('value'=>$src));
			$attr = array(
				'class' => $params['class'],
				'src' => $src,
				'id' => ($params['id'] ? $params['id'] : ''),
				'alt' => htmlspecialchars($params['alttext']),
				'style' => $params['style']
			);
			if(isset($params['align']) && $params['align'] != 'none')
			{
				$attr['align'] = $params['align'];
			}
			$attributes = '';
			foreach ($attr as $k => $v)
			{
				if($v) $attributes .= " {$k}=\"{$v}\"";
			}
			$attributes .= ' '.$params['attrib'];
			
			// Output the image with attributes
			$o .= '<img'.rtrim($attributes).' />';
		}
	}
	return $o;
