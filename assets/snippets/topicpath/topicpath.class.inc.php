<?php
class TopicPath {
	function TopicPath() {}
	function get_first_crumb($tpl,$crumb,$stylePrefix)
	{
		$ph = array();
		$ph['firstCrumbClass'] = $stylePrefix.'firstCrumb';
		$ph['firstCrumbSpanA'] = $crumb;
		return $this->parse_ph($tpl,$ph);

	}
	
	function get_last_crumb($tpl,$crumb,$stylePrefix)
	{
		$ph = array();
		$ph['lastCrumbClass'] = $stylePrefix.'lastCrumb';
		$ph['lastCrumbSpanA'] = $crumb;
		return $this->parse_ph($tpl,$ph);
	}
	
	function get_crumbs_info($params,$modx)
	{
		extract($params);
		$crumbs = array();
		
		if($showCurrentCrumb)
		{
			$current_crumb = $this->get_crumb_info($modx->documentObject);
		}
		
		$between_crumbs = $this->get_between_crumb($params,$modx);
		if ( $showHomeCrumb && $homeId != $modx->documentIdentifier)
		{
			$field = 'id,parent,pagetitle,longtitle,menutitle,description,published,hidemenu';
			$documentObject_home = $modx->getPageInfo($homeId,0,$field);
			$home_crumb = $this->get_crumb_info($documentObject_home);
		}
		
		if($current_crumb)  $crumbs[] = $current_crumb;
		if($between_crumbs) $crumbs += $between_crumbs;
		if($home_crumb)     $crumbs[] = $home_crumb;
		return $crumbs;
	}
	
	function get_condition($params,$modx)
	{
		extract($params);
		if(!$showCrumbsAtHome && $homeId == $modx->documentIdentifier )
		{
			return false;
		}
		// Return blank if necessary: specified pages
		if($hideOn || $hideUnder)
		{
			// Create array of hide pages
			$hideOn = str_replace(' ','',$hideOn);
			$hideOn = explode(',',$hideOn);
			
			// Get more hide pages based on parents if needed
			if ( $hideUnder )
			{
				$hiddenKids = array();
				// Get child pages to hide
				$hideKidsQuery = $modx->db->select('id',$modx->getFullTableName('site_content'),"parent IN ({$hideUnder})");
				while($row = $modx->db->getRow($hideKidsQuery))
				{
					$hiddenKids[] = $row['id'];
				}
				// Merge with hideOn pages
				$hideOn = array_merge($hideOn,$hiddenKids);
			}
			if (in_array($modx->documentIdentifier,$hideOn))
			{
				return false;
			}
		}
	}
	
	function parse_ph($tpl,$ph)
	{
		foreach($ph as $k=>$v)
		{
			$k = '[+' . $k . '+]';
			$tpl = str_replace($k,$v,$tpl);
		}
		return $tpl;
	}
	
	function process_each_crumb($params,$modx)
	{
		extract($params);
		$maxCrumbs += ($showCurrentCrumb) ? 1 : 0;
		$crumbGap = str_replace('||','=',$crumbGap);
		// Process each crumb ----------------------------------------------------------
		$pretemplateCrumbs = array();
		$linkDescField = $this->str2array($linkDescField);
		$linkTextField = $this->str2array($linkTextField);
		foreach ($crumbs as $c )
		{
			// Skip if we've exceeded our crumb limit but we're waiting to get to home
			if ( count($pretemplateCrumbs) > $maxCrumbs && $c['id'] != $homeId )
			{
				continue;
			}
			
			$text = '';
			$title = '';
			$pretemplateCrumb = '';
			
			if($c['id'] == $homeId && $homeCrumbTitle)
			{	// Determine appropriate span/link text: home link specified
				$text = $homeCrumbTitle;
			}
			else
			{	// Determine appropriate span/link text: home link not specified
				$count_linkTextField = count($linkTextField);
				for ($i = 0; !$text && $i < $count_linkTextField; $i++)
				{
					if ( $c[$linkTextField[$i]] )
					{
						$text = $c[$linkTextField[$i]];
					}
				}
			}
			
			// Determine link/span class(es)
			if ( $c['id'] == $homeId )
			{
				$crumbClass = $stylePrefix.'homeCrumb';
			}
			elseif ( $modx->documentIdentifier == $c['id'] )
			{
				$crumbClass = $stylePrefix.'currentCrumb';
			}
			else
			{
				$crumbClass = $stylePrefix.'crumb';
			}
		
			// Make link
			if (
				( $c['id'] != $modx->documentIdentifier && $showCrumbsAsLinks ) ||
				( $c['id'] == $modx->documentIdentifier && $currentAsLink )
			)
			{	// Determine appropriate title for link: home link specified
				if ( $c['id'] == $homeId && $homeCrumbDescription )
				{
					$title = htmlspecialchars($homeCrumbDescription);
				}
				else
				{	// Determine appropriate title for link: home link not specified
					for ($i = 0; !$title && $i < count($linkDescField); $i++)
					{
						if ( $c[$linkDescField[$i]] )
						{
							$title = htmlspecialchars($c[$linkDescField[$i]]);
						}
					}
				}
				
				$href = ($c['id'] == $modx->config['site_start']) ? $modx->config['site_url'] : $modx->makeUrl($c['id'],'','','full');
				$pretemplateCrumb .= '<a class="'.$crumbClass.'" href="'.$href.'" title="'.$title.'">'.$text.'</a>';
			}
			else
			{	// Make a span instead of a link
				$pretemplateCrumb .= '<span class="'.$crumbClass.'">'.$text.'</span>';
			}
			
			// Add crumb to pretemplate crumb array
			$pretemplateCrumbs[] = $pretemplateCrumb;
			
			// If we have hit the crumb limit
			if ( count($pretemplateCrumbs) == $maxCrumbs )
			{
				if ( count($crumbs) > ($maxCrumbs + (($showHomeCrumb) ? 1 : 0)) )
				{
					// Add gap
					$pretemplateCrumbs[] = '<span class="'.$stylePrefix.'hideCrumb'.'">'.$crumbGap.'</span>';
				}
				
				// Stop here if we're not looking for the home crumb
				if ( !$showHomeCrumb )
				{
					break;
				}
			}
		}
		return array_reverse($pretemplateCrumbs);
	}
	
	function get_between_crumb($params,$modx)
	{
		extract($params);
		// Iterate through parents till we hit root or a reason to stop
		$parent = $modx->documentObject['parent'];
		$loopSafety = 0;
		$stopIds = $this->str2array($stopIds);
		$ignoreIds     = $this->str2array($ignoreIds);
		while ($parent && $parent!=$modx->config['site_start'] && $loopSafety < 1000 )
		{
			// Get next crumb
			$tempCrumb = $modx->getPageInfo($parent,0,'id,parent,pagetitle,longtitle,menutitle,description,published,hidemenu');
			
			// Check for include conditions & add to crumbs
			if(
				$tempCrumb['published'] &&
				( !$tempCrumb['hidemenu'] || !$respectHidemenu ) &&
				!in_array($tempCrumb['id'],$ignoreIds)
			)
			{
				// Add crumb
				$crumbs[] = $this->get_crumb_info($tempCrumb);
			}
			// Check stop conditions
			if(
				in_array($tempCrumb['id'],$stopIds) ||  // Is one of the stop IDs
				!$tempCrumb['parent'] || // At root
				( !$tempCrumb['published'] && !$pathThruUnPub ) // Unpublished
			)
			{	// Halt making crumbs
				break;
			}
			
			// Reset parent
			$parent = $tempCrumb['parent'];
			
			// Increment loop safety
			$loopSafety++;
		}
		return $crumbs;
	}
	
	function get_crumb_info($array)
	{
		$params = array();
		$params['id']          = $array['id'];
		$params['parent']      = $array['parent'];
		$params['pagetitle']   = $array['pagetitle'];
		$params['longtitle']   = $array['longtitle'];
		$params['menutitle']   = $array['menutitle'];
		$params['description'] = $array['description'];
		return $params;
	}
	
	function str2array($str)
	{
		$str = str_replace(' ','',$str);
		return explode(',',$str);
	}
	
	function get_default_tpl($templateSet)
	{
		$templateSet = strtolower($templateSet);
		switch($templateSet)
		{
			case 'defaultlist':
			case 'list':
			case 'li':
			{
				$tpl['crumb']             = '<li>[+crumb+]</li>';
				$tpl['separator']         = '';
				$tpl['crumbContainer']    = '<ul class="[+crumbBoxClass+]">[+crumbs+]</ul>';
				$tpl['firstCrumbWrapper'] = '[+firstCrumbSpanA+]';
				$tpl['lastCrumbWrapper']  = '[+lastCrumbSpanA+]';
				break;
			}
			case 'defaultstring':
			default:
			{
				$tpl['crumb']             = '[+crumb+]';
				$tpl['separator']         = ' &raquo; ';
				$tpl['crumbContainer']    = '<span class="[+crumbBoxClass+]">[+crumbs+]</span>';
				$tpl['firstCrumbWrapper'] = '<span class="[+firstCrumbClass+]">[+firstCrumbSpanA+]</span>';
				$tpl['lastCrumbWrapper']  = '<span class="[+lastCrumbClass+]">[+lastCrumbSpanA+]</span>';
			}
		}
		return $tpl;
	}
	
	function get_chunk_tpl($templateSet)
	{
		global $modx;
		$src = $modx->getChunk($templateSet);
		$tpl = array();
		if(empty($src)) return $tpl;
		$lines = explode("\n", $src);
		foreach($lines as $line)
		{
			$line = ltrim($line);
			if(!empty($line) && $line[0] == '&' && strpos($line,'=')!==false)
			{
				list($key,$value) = explode('=',$line,2);
				$key   = trim($key,'& ');
				$value = trim($value,'`');
				$tpl[$key] = $value;
			}
		}
		return $tpl;
	}
	
	function fetch($value)
	{
		global $modx;
		$template = '';
		
		if(substr($value, 0, 5) == '@FILE')
		{
			$value = substr($value, 6);
			$value = trim($value);
			$value = MODX_BASE_PATH . ltrim($value,'/');
			$template = file_get_contents($value);
		}
		elseif(substr($value, 0, 5) == '@CODE')
		{
			$template = substr($value, 6);
		}
		elseif($modx->getChunk($value) != '')
		{
			$template = $modx->getChunk($value);
		}
		else
		{
			$template = $value;
		}
		return $template;
	}
}
