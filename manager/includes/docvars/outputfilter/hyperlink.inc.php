<?php
	if(!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

	$urls = $this->parseInput($value,'||','array');
	foreach ($urls as $url)
	{
		list($name,$url) = is_array($url) ? $url: explode('==',$url);
		if (!$url) $url = $name;
		if ($url)
		{
			if($o) $o.='<br />';
			$attributes = '';
			// setup the link attributes
			$attr = array(
				'href'   => $url,
				'title'  => $params['title'] ? htmlspecialchars($params['title']) : $name,
				'class'  => $params['linkclass'],
				'style'  => $params['linkstyle'],
				'target' => $params['target'],
			);
			foreach ($attr as $k => $v)
			{
				$attributes.= ($v ? " {$k}=\"{$v}\"" : '');
			}
			$attributes .= ' '.$params['linkattrib']; // add extra
			
			// Output the link
			$o .= '<a'.rtrim($attributes).'>'. ($params['text'] ? htmlspecialchars($params['text']) : $name) .'</a>';
		}
	}

	return $o;
