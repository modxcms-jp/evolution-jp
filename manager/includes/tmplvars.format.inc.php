<?php
/*
* Template Variable Display Format
* Created by Raymond Irving Feb, 2005
*/

// Added by Raymond 20-Jan-2005
function getTVDisplayFormat($name,$value,$format,$paramstring='',$tvtype='',$docid='', $sep='')
{
	global $modx;
	
	// process any TV commands in value
	$docid= intval($docid) ? intval($docid) : $modx->documentIdentifier;
	switch($tvtype)
	{
		case 'dropdown':
		case 'listbox':
		case 'listbox-multiple':
		case 'checkbox':
		case 'option':
			$src = $tvtype;
			$values = explode('||',$value);
			$i = 0;
			foreach($values as $i=>$v)
			{
				$values[$i] = ProcessTVCommand($v, $name, $docid, $src);
				$i++;
			}
			$value = join('||', $values);
			break;
		default:
			$src = 'docform';
			$value = ProcessTVCommand($value, $name, $docid, $src);
	}
	
	$param = array();
	if($paramstring)
	{
		$cp = explode('&',$paramstring);
		foreach($cp as $p => $v)
		{
			$v = trim($v); // trim
			$ar = explode('=',$v);
			if (is_array($ar) && count($ar)==2)
			{
				$params[$ar[0]] = decodeParamValue($ar[1]);
			}
		}
	}

	$id = "tv{$name}";
	switch($format)
	{
		case 'image':
			$images = parseInput($value, '||', 'array');
			$o = '';
			foreach($images as $image)
			{
				if(!is_array($image)) $image = explode('==',$image);
				$src = $image[0];
				
				if($src)
				{
					// We have a valid source
					$src = $modx->parsePlaceholder($params['output'],array('value'=>$src));
					$attributes = '';
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
					foreach ($attr as $k => $v)
					{
						$attributes.= ($v ? " {$k}=\"{$v}\"" : '');
					}
					$attributes .= ' '.$params['attrib'];
					
					// Output the image with attributes
					$o .= '<img'.rtrim($attributes).' />';
				}
			}
			break;
		case 'delim':	// display as delimitted list
			$value = parseInput($value,'||');
			$p = $params['delim'] ? $params['delim']:',';
			if ($p=="\\n") $p = "\n";
			$o = str_replace('||',$p,$value);
			break;
		case 'string':
			$value = parseInput($value);
			$format = strtolower($params['format']);
			if($format=='zen-han')            $o = mb_convert_kana($value,'as',$modx->config['modx_charset']);
			else if($format=='han-zen')       $o = mb_convert_kana($value,'AS',$modx->config['modx_charset']);
			else if($format=='upper case')    $o = strtoupper($value);
			else if($format=='lower case')    $o = strtolower($value);
			else if($format=='sentence case') $o = ucfirst($value);
			else if($format=='capitalize')    $o = ucwords($value);
			else if($format=='nl2br')         $o = nl2br($value);
			else if($format=='number format') $o = number_format($value);
			else if($format=='htmlspecialchars') $o = htmlspecialchars($value,ENT_QUOTES,$modx->config['modx_charset']);
			else if($format=='htmlentities')  $o = htmlentities($value,ENT_QUOTES,$modx->config['modx_charset']);
			else $o = $value;
			break;
		case 'date':
		case 'dateonly':
			if ($value !='' || $params['default']=='Yes')
			{
				$timestamp = getUnixtimeFromDateString($value);
				$p = $params['format'] ? $params['format'] : $modx->toDateFormat(null, 'formatOnly');
				$o = strftime($p,$timestamp);
			}
			else
			{
				$value='';
			}
			break;
		case 'hyperlink':
			$value = parseInput($value,'||','array');
			for ($i = 0; $i < count($value); $i++)
			{
				list($name,$url) = is_array($value[$i]) ? $value[$i]: explode('==',$value[$i]);
				if (!$url) $url = $name;
				if ($url)
				{
					if($o) $o.='<br />';
					$attributes = '';
					// setup the link attributes
					$attr = array(
						'href'   => $url,
						'title'  => $params['title'] ? htmlspecialchars($params['title']) : $name,
						'class'  => $params['class'],
						'style'  => $params['style'],
						'target' => $params['target'],
					);
					foreach ($attr as $k => $v)
					{
						$attributes.= ($v ? " {$k}=\"{$v}\"" : '');
					}
					$attributes .= ' '.$params['attrib']; // add extra
					
					// Output the link
					$o .= '<a'.rtrim($attributes).'>'. ($params['text'] ? htmlspecialchars($params['text']) : $name) .'</a>';
				}
			}
			break;
		case 'htmltag':
			$value = parseInput($value,'||','array');
			$tagid = $params['tagid'];
			$tagname = ($params['tagname']) ? $params['tagname'] : 'div';
			// Loop through a list of tags
			for ($i = 0; $i < count($value); $i++)
			{
				$tagvalue = is_array($value[$i]) ? implode(' ', $value[$i]) : $value[$i];
				if (!$tagvalue) continue;
				
				$tagvalue = $modx->parsePlaceholder($params['output'],array('value'=>$tagvalue));
				$attributes = '';
				$attr = array(
					'id' => ($tagid ? $tagid : $tagname) . ($i==0?'':'-'.$i), //１周目は指定されたidをそのまま付加する。'tv' already added to id
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
			break;
		case 'richtext':
			$value = parseInput($value);
			$w = $params['w']? $params['w']:'100%';
			$h = $params['h']? $params['h']:'400px';
			$richtexteditor = $params['edt']? $params['edt']: '';
			$o = '<div class="MODX_RichTextWidget"><textarea id="'.$id.'" name="'.$id.'" style="width:'.$w.'; height:'.$h.';">';
			$o.= htmlspecialchars($value);
			$o.= '</textarea></div>';
			$replace_richtext = array($id);
			// setup editors
			if (!empty($replace_richtext) && !empty($richtexteditor))
			{
				// invoke OnRichTextEditorInit event
				$evtOut = $modx->invokeEvent('OnRichTextEditorInit',
				array(
					'editor'      => $richtexteditor,
					'elements'    => $replace_richtext,
					'forfrontend' => 1,
					'width'       => $w,
					'height'      => $h
				));
				if(is_array($evtOut)) $o.= implode('',$evtOut);
			}
			break;
		case 'unixtime':
			$value = parseInput($value);
			$o = getUnixtimeFromDateString($value);
			break;
		case 'datagrid':
			include_once MODX_BASE_PATH.'manager/includes/controls/datagrid.class.php';
			$grd = new DataGrid('',$value);
			$grd->noRecordMsg		=$params['egmsg'];
			
			$grd->columnHeaderClass	=$params['chdrc'];
			$grd->cssClass			=$params['tblc'];
			$grd->itemClass			=$params['itmc'];
			$grd->altItemClass		=$params['aitmc'];
			
			$grd->columnHeaderStyle	=$params['chdrs'];
			$grd->cssStyle			=$params['tbls'];
			$grd->itemStyle			=$params['itms'];
			$grd->altItemStyle		=$params['aitms'];
			
			$grd->columns			=$params['cols'];
			$grd->fields			=$params['flds'];
			$grd->colWidths			=$params['cwidth'];
			$grd->colAligns			=$params['calign'];
			$grd->colColors			=$params['ccolor'];
			$grd->colTypes			=$params['ctype'];
			
			$grd->cellPadding		=$params['cpad'];
			$grd->cellSpacing		=$params['cspace'];
			$grd->header			=$params['head'];
			$grd->footer			=$params['foot'];
			$grd->pageSize			=$params['psize'];
			$grd->pagerLocation		=$params['ploc'];
			$grd->pagerClass		=$params['pclass'];
			$grd->pagerStyle		=$params['pstyle'];
			
			$grd->cdelim			=$params['cdelim'];
			$grd->cwrap				=$params['cwrap'];
			$grd->src_encode		=$params['enc'];
			$grd->detectHeader		=$params['detecthead'];
			
			$o = $grd->render();
			break;
		case 'htmlentities':
			$value= parseInput($value);
			if($tvtype=='checkbox'||$tvtype=='listbox-multiple')
			{
				// remove delimiter from checkbox and listbox-multiple TVs
				$value = str_replace('||','',$value);
			}
			$o = htmlentities($value, ENT_NOQUOTES, $modx->config['modx_charset']);
			break;
		case 'custom_widget':
			$widget_output = '';
			$o = '';
			/* If we are loading a file */
			$params['output'] = $modx->parsePlaceholder($params['output'],array('value'=>$value,'tvname'=>$name));
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
				$widget_output = $modx->getChunk($chunk_name);
			}
			elseif(substr($params['output'], 0, 5) == '@EVAL')
			{
				$tvname = $name;
				$eval_str = trim(substr($params['output'], 6));
				$widget_output = eval($eval_str);
			}
			elseif($value !== '')
			{
				$widget_output = $params['output'];
			}
			else
			{
				$widget_output = '';
			}
			if(is_string($widget_output)) // Except @INCLUDE
			{
			if(strpos($widget_output,'[+')!==false)
			{
				$widget_output = $modx->parsePlaceholder($widget_output,array('value'=>$value,'tvname'=>$name));
			}
				$o = $modx->parseDocumentSource($widget_output);
			}
			else
			{
				$o = $widget_output;
			}
			break;
		
		default:
			$value = parseInput($value);
			if($tvtype=='checkbox'||$tvtype=='listbox-multiple')
			{
				// add separator
				$value = explode('||',$value);
				$value = implode($sep,$value);
			}
			$o = $value;
			break;
	}
	return $o;
}

function decodeParamValue($s)
{
	$s = str_replace('%3B',';',$s); // ;
	$s = str_replace('%3D','=',$s); // =
	$s = str_replace('%26','&',$s); // &
	$s = str_replace('%2C',',',$s); // ,
	$s = str_replace('%5C','\\',$s); // \

	return $s;
}

// returns an array if a delimiter is present. returns array is a recordset is present
function parseInput($src, $delim='||', $type='string', $columns=true)
{ // type can be: string, array
	global $modx;
	
	if (is_resource($src))
	{
		// must be a recordset
		$rows = array();
		$nc = mysql_num_fields($src);
		while ($cols = $modx->db->getRow($src,'num'))
		{
			$rows[] = ($columns)? $cols : implode(' ',$cols);
		}
		return ($type=='array')? $rows : implode($delim,$rows);
	}
	else
	{
		// must be a text
		if($type=='array') return explode($delim,$src);
		else               return $src;
	}
}

function getUnixtimeFromDateString($value)
{
	$timestamp = false;
	// Check for MySQL or legacy style date
	$date_match_1 = '/^([0-9]{2})-([0-9]{2})-([0-9]{4})\ ([0-9]{2}):([0-9]{2}):([0-9]{2})$/';
	$date_match_2 = '/^([0-9]{4})-([0-9]{2})-([0-9]{2})\ ([0-9]{2}):([0-9]{2}):([0-9]{2})$/';
	$matches= array();
	if(strpos($value,'-')!==false)
	{
		if(preg_match($date_match_1, $value, $matches))
		{
			$timestamp = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[1], $matches[3]);
		}
		elseif(preg_match($date_match_2, $value, $matches))
		{
			$timestamp = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);
		}
	}
	// If those didn't work, use strtotime to figure out the date
	if($timestamp === false || $timestamp === -1)
	{
		$timestamp = strtotime($value);
	}
	return $timestamp;
}
