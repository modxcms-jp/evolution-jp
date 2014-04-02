<?php
	if(!defined('IN_PARSER_MODE') || IN_PARSER_MODE != 'true') exit();

	$value = $this->parseInput($value,'||','array');
	$tagid = $params['tagid'];
	$tagname = ($params['tagname']) ? $params['tagname'] : 'div';
	// Loop through a list of tags
	$count = count($value);
	for ($i = 0; $i < $count; $i++)
	{
		$tagvalue = is_array($value[$i]) ? implode(' ', $value[$i]) : $value[$i];
		if (!$tagvalue) continue;
		
		$tagvalue = $this->parseText($params['output'],array('value'=>$tagvalue));
		$attributes = '';
		$attr = array(
			'id' => ($tagid ? $tagid : $tagname) . ($i==0 ? '' : "-{$i}"), // 'tv' already added to id
			'class' => $params['class'],
			'style' => $params['style'],
		);
		foreach ($attr as $k => $v)
		{
			$attributes.= ($v ? " {$k}=\"{$v}\"" : '');
		}
		$attributes .= ' '.$params['attrib']; // add extra
		$attributes = rtrim($attributes);
		
		// Output the HTML Tag
		switch($tagname)
		{
			case 'img':
				$o .= "<img src=\"{$tagvalue}\" {$attributes} />\n";
				break;
			default:
				$o .= "<{$tagname}{$attributes}>{$tagvalue}</{$tagname}>\n";
		}
	}

	return $o;
