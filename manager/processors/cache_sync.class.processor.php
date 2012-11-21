<?php
// cache & synchronise class

class synccache {
	var $cachePath;
	var $showReport;
	var $deletedfiles = array();
	var $aliases = array();
	var $parents = array();
	var $target;
	var $config = array();

	function synccache()
	{
		if(empty($this->target))      $this->target = 'pagecache,sitecache';
		if(defined('MODX_BASE_PATH')) $this->cachePath = MODX_BASE_PATH . 'assets/cache/';
	}
	
	function setTarget($target)
	{
		$this->target = $target;
	}
	
	function setCachepath($path) {
		$this->cachePath = rtrim($path,'/') . '/';
	}

	function setReport($bool) {
		$this->showReport = $bool;
	}

	function escapeDoubleQuotes($s) {
		$q1 = array("\\","\"","\r","\n","\$");
		$q2 = array("\\\\","\\\"","\\r","\\n","\\$");
		return str_replace($q1,$q2,$s);
	}

	function escapeSingleQuotes($s) {
		$q1 = array("\\","'");
		$q2 = array("\\\\","\\'");
		return str_replace($q1,$q2,$s);
	}

	function getParents($id, $path = '') { // modx:returns child's parent
		global $modx;
		if(empty($this->aliases))
		{
			$fields = "id, IF(alias='', id, alias) AS alias, parent";
			$tbl_site_content = $modx->getFullTableName('site_content');
			$qh = $modx->db->select($fields,$tbl_site_content);
			if ($qh && $modx->db->getRecordCount($qh) > 0)
			{
				while ($row = $modx->db->getRow($qh))
				{
					$this->aliases[$row['id']] = $row['alias'];
					$this->parents[$row['id']] = $row['parent'];
				}
			}
		}
		if (isset($this->aliases[$id]))
		{
			if($path !== '')
			{
				$path = $this->aliases[$id] . '/' . $path;
			}
			else $path = $this->aliases[$id];
			
			return $this->getParents($this->parents[$id], $path);
		}
		return $path;
	}

	function emptyCache($modx = null)
	{
		$instance_name = '';
		if(is_object($modx))
		{
			$instance_name = get_class($modx);
		}
		$instance_name = strtolower($instance_name);
		if($instance_name!=='documentparser') global $modx;
		
		if(!isset($this->cachePath)) exit('Cache path not set.');
		
		if(strpos($this->target,'pagecache')!==false) $result = $this->emptyPageCache('pageCache');
		if(strpos($this->target,'sitecache')!==false) $this->buildCache($modx);
		$this->publish_time_file($modx);
		if(isset($result) && $this->showReport==true) $this->showReport($result);
	}
	
	function emptyPageCache($target)
	{
		$filesincache = 0;
		$deletedfilesincache = 0;
		$pattern = realpath($this->cachePath)."/*.{$target}.php";
		$pattern = str_replace('\\','/',$pattern);
		$files = glob($pattern,GLOB_NOCHECK);
		$filesincache = ($files[0] !== $pattern) ? count($files) : 0;
		$deletedfiles = array();
		if(is_array($files) && 0 < $filesincache)
		{
			while ($file = array_shift($files))
			{
				$name = basename($file);
				if (strpos($name,".{$target}")!==false && !in_array($name, $deletedfiles))
				{
					$deletedfilesincache++;
					$deletedfiles[] = $name;
					unlink($file);
				}
			}
		}
		return array($filesincache,$deletedfilesincache,$deletedfiles);
	}

	function showReport($info)
	{
		list($filesincache,$deletedfilesincache,$deletedfiles) = $info;
		// finished cache stuff.
		global $_lang;
		printf($_lang['refresh_cache'], $filesincache, $deletedfilesincache);
		$limit = count($deletedfiles);
		if($limit > 0)
		{
			echo '<p>'.$_lang['cache_files_deleted'].'</p><ul>';
			for($i=0;$i<$limit; $i++)
			{
				echo '<li>',$deletedfiles[$i],'</li>';
			}
			echo '</ul>';
		}
	}
	
	/****************************************************************************/
	/*  PUBLISH TIME FILE                                                       */
	/****************************************************************************/
	function publish_time_file($modx)
	{
		global $site_sessionname;
		
		// update publish time file
		$tbl_site_content      = $modx->getFullTableName('site_content');
		$tbl_site_htmlsnippets = $modx->getFullTableName('site_htmlsnippets');
		$tbl_system_settings    = $modx->getFullTableName('system_settings');
		$timesArr = array();
		$current_time = time();
		
		$result = $modx->db->select('MIN(pub_date) AS minpub',$tbl_site_content, "{$current_time} < pub_date");
		if(!$result)
		{
			echo "Couldn't determine next publish event!";
		}
		
		$minpub = $modx->db->getValue($result);
		if($minpub!=NULL)
		{
			$timesArr[] = $minpub;
		}
		
		$result = $modx->db->select('MIN(unpub_date) AS minunpub',$tbl_site_content, "{$current_time} < unpub_date");
		if(!$result)
		{
			echo "Couldn't determine next unpublish event!";
		}
		$minunpub = $modx->db->getValue($result);
		if($minunpub!=NULL)
		{
			$timesArr[] = $minunpub;
		}
		
		$result = $modx->db->select('MIN(pub_date) AS minpub',$tbl_site_htmlsnippets, "{$current_time} < pub_date");
		if(!$result)
		{
			echo "Couldn't determine next publish event!";
		}
		
		$minpub = $modx->db->getValue($result);
		if($minpub!=NULL)
		{
			$timesArr[] = $minpub;
		}
		
		$result = $modx->db->select('MIN(unpub_date) AS minunpub',$tbl_site_htmlsnippets, "{$current_time} < unpub_date");
		if(!$result)
		{
			echo "Couldn't determine next unpublish event!";
		}
		$minunpub = $modx->db->getValue($result);
		if($minunpub!=NULL)
		{
			$timesArr[] = $minunpub;
		}
		
		if(count($timesArr)>0) $nextevent = min($timesArr);
		else                   $nextevent = 0;
		
		$rs = $modx->db->select('setting_name,setting_value',$tbl_system_settings);
		while($row = $modx->db->getRow($rs))
		{
			$name  = $row['setting_name'];
			$value = $row['setting_value'];
			$setting[$name] = $value;
		}
		
		// write the file
		$cache_path = $this->cachePath . 'sitePublishing.idx.php';
		$content  = "<?php\n\$cacheRefreshTime = {$nextevent};\n";
		$content .= '$cache_type = ' . "{$setting['cache_type']};\n";
		if(isset($site_sessionname) && !empty($site_sessionname))
		{
			$content .= '$site_sessionname = ' . "'{$site_sessionname}';\n";
		}
		$content .= '$site_status = '      . "'{$setting['site_status']}';\n";
		$content .= '$error_reporting = ' . "'{$setting['error_reporting']}';\n";
		
		if(isset($setting['site_url']) && !empty($setting['site_url']))
		{
			$content .= '$site_url = '      . "'{$setting['site_url']}';\n";
		}
		
		if(isset($setting['base_url']) && !empty($setting['base_url']))
		{
			$content .= '$base_url = '      . "'{$setting['base_url']}';\n";
		}
		
		$rs = file_put_contents($cache_path, $content, LOCK_EX);
		
		if (!$rs) exit("Cannot open file ({$filename})");
	}
	
	/**
	* build siteCache file
	* @param  DocumentParser $modx
	* @return boolean success
	*/
	function buildCache($modx)
	{
		$content = "<?php\n";
		$content .= "if(!defined('MODX_BASE_PATH') || strpos(str_replace('\\\\','/',__FILE__), MODX_BASE_PATH)!==0) exit;\n";
		
		// SETTINGS & DOCUMENT LISTINGS CACHE
		
		$this->_get_settings($modx); // get settings
		$this->_get_aliases($modx);  // get aliases modx: support for alias path
		$content .= $this->_get_content_types($modx); // get content types
		$this->_get_chunks($modx);   // WRITE Chunks to cache file
		$this->_get_snippets($modx); // WRITE snippets to cache file
		$this->_get_plugins($modx);  // WRITE plugins to cache file
		$content .= $this->_get_events($modx);   // WRITE system event triggers
		
		// close and write the file
		$content .= "\n";
		$content = str_replace(array("\x0d\x0a", "\x0a", "\x0d"), "\x0a", $content);
		
		// invoke OnBeforeCacheUpdate event
		if ($modx) $modx->invokeEvent('OnBeforeCacheUpdate');
		
		if(!file_put_contents($this->cachePath .'siteCache.idx.php', $content, LOCK_EX))
		{
			exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
		}
		
		$str = serialize($this->config);
		if(!file_put_contents($this->cachePath .'config.siteCache.idx.php', $str, LOCK_EX))
		{
			exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
		}
		
		$str = serialize($modx->aliasListing);
		if(!file_put_contents($this->cachePath .'aliasListing.siteCache.idx.php', $str, LOCK_EX))
		{
			exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
		}
		
		$str = serialize($modx->documentMap);
		if(!file_put_contents($this->cachePath .'documentMap.siteCache.idx.php', $str, LOCK_EX))
		{
			exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
		}
		
		$str = serialize($modx->chunkCache);
		if(!file_put_contents($this->cachePath .'chunk.siteCache.idx.php', $str, LOCK_EX))
		{
			exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
		}
		
		$str = serialize($modx->snippetCache);
		if(!file_put_contents($this->cachePath .'snippet.siteCache.idx.php', $str, LOCK_EX))
		{
			exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
		}
		
		$str = serialize($modx->pluginCache);
		if(!file_put_contents($this->cachePath .'plugin.siteCache.idx.php', $str, LOCK_EX))
		{
			exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
		}
		
		// invoke OnCacheUpdate event
		if ($modx) $modx->invokeEvent('OnCacheUpdate');
		
		return true;
	}
	
	function _get_settings($modx)
	{
		$tbl_system_settings    = $modx->getFullTableName('system_settings');
		
		$rs = $modx->db->select('setting_name,setting_value',$tbl_system_settings);
		$row = array();
		while($row = $modx->db->getRow($rs))
		{
			$setting_name  = $row['setting_name'];
			$setting_value = $row['setting_value'];
			$this->config[$setting_name] = $setting_value;
		}
	}
	
	function _get_aliases($modx)
	{
		$tbl_system_settings    = $modx->getFullTableName('system_settings');
		$tbl_site_content       = $modx->getFullTableName('site_content');
		
		$friendly_urls = $modx->db->getValue($modx->db->select('setting_value',$tbl_system_settings,"setting_name='friendly_urls'"));
		if($friendly_urls==1)
		{
			$use_alias_path = $modx->db->getValue($modx->db->select('setting_value',$tbl_system_settings,"setting_name='use_alias_path'"));
		}
		$fields = "IF(alias='', id, alias) AS alias, id, parent";
		$rs = $modx->db->select($fields,$tbl_site_content,'deleted=0','parent, menuindex');
		$row = array();
		$path = '';
		while ($row = $modx->db->getRow($rs))
		{
			if ($friendly_urls == 1 && $use_alias_path == 1)
			{
				$path = $this->getParents($row['parent']);
			}
			else
			{
				$path = '';
			}
			$alias = $modx->db->escape($row['alias']);
			$docid = $row['id'];
			$path = $modx->db->escape($path);
			$parent = $row['parent'];
			$modx->aliasListing[$docid] = array('id' => $docid, 'alias' => $alias, 'path' => $path, 'parent' => $parent);
			$modx->documentMap[] = array($parent => $docid);
		}
	}
	
	function _get_content_types($modx)
	{
		$tbl_site_content       = $modx->getFullTableName('site_content');
		
		$rs = $modx->db->select('id, contentType',$tbl_site_content,"contentType != 'text/html'");
		$tmpPHP = '$c = &$this->contentTypes;' . "\n";
		$row = array();
		while ($row = $modx->db->getRow($rs))
		{
			$tmpPHP .= '$c['.$row['id'].']'." = '".$row['contentType']."';\n";
		}
		return $tmpPHP;
	}
	
	function _get_chunks($modx)
	{
		$tbl_site_htmlsnippets  = $modx->getFullTableName('site_htmlsnippets');
		
		$rs = $modx->db->select('name,snippet',$tbl_site_htmlsnippets, "`published`='1'");
		$row = array();
		while ($row = $modx->db->getRow($rs))
		{
			$name = $modx->db->escape($row['name']);
			$modx->chunkCache[$name] = $row['snippet'];
		}
	}
	
	function _get_snippets($modx)
	{
		$tbl_site_snippets      = $modx->getFullTableName('site_snippets');
		$tbl_site_modules       = $modx->getFullTableName('site_modules');
		
		$fields = 'ss.name,ss.snippet,ss.properties,sm.properties as `sharedproperties`';
		$from = "{$tbl_site_snippets} ss LEFT JOIN {$tbl_site_modules} sm on sm.guid=ss.moduleguid";
		$rs = $modx->db->select($fields,$from);
		$row = array();
		while ($row = $modx->db->getRow($rs))
		{
			$name = $row['name'];
			$snippet = $row['snippet'];
			$modx->snippetCache[$name] = $snippet;
			if ($row['properties'] != '' || $row['sharedproperties'] != '')
			{
				$properties = $row['properties'] . ' ' . $row['sharedproperties'];
				$modx->snippetCache["{$name}Props"] = $properties;
			}
		}
	}
	
	function _get_plugins($modx)
	{
		$tbl_site_modules       = $modx->getFullTableName('site_modules');
		$tbl_site_plugins       = $modx->getFullTableName('site_plugins');
		
		$fields = 'sp.name,sp.plugincode,sp.properties,sm.properties as `sharedproperties`';
		$from = "{$tbl_site_plugins} sp LEFT JOIN {$tbl_site_modules} sm on sm.guid=sp.moduleguid";
		$rs = $modx->db->select($fields,$from,'sp.disabled=0');
		$row = array();
		while ($row = $modx->db->getRow($rs))
		{
			$name = $modx->db->escape($row['name']);
			$plugincode = $row['plugincode'];
			$properties = $row['properties'].' '.$row['sharedproperties'];
			$modx->pluginCache[$name]          = $plugincode;
			if ($row['properties']!='' || $row['sharedproperties']!='')
			{
				$modx->pluginCache["{$name}Props"] = $properties;
			}
		}
	}
	
	function _get_events($modx)
	{
		$tbl_site_plugins       = $modx->getFullTableName('site_plugins');
		$tbl_system_eventnames  = $modx->getFullTableName('system_eventnames');
		$tbl_site_plugin_events = $modx->getFullTableName('site_plugin_events');
		
		$fields  = 'sysevt.name as `evtname`, plugs.name';
		$from    = "{$tbl_system_eventnames} sysevt INNER JOIN {$tbl_site_plugin_events} pe ON pe.evtid = sysevt.id INNER JOIN {$tbl_site_plugins} plugs ON plugs.id = pe.pluginid";
		$where   = 'plugs.disabled=0';
		$orderby = 'sysevt.name,pe.priority';
		$rs = $modx->db->select($fields,$from,$where,$orderby);
		$tmpPHP = '$e = &$this->pluginEvent;' . "\n";
		$events = array();
		$row = array();
		while ($row = $modx->db->getRow($rs))
		{
			if(!$events[$row['evtname']])
			{
				$events[$row['evtname']] = array();
			}
			$events[$row['evtname']][] = $row['name'];
		}
		foreach($events as $evtname => $pluginnames)
		{
			$tmpPHP .= '$e[\''.$evtname.'\'] = array(\''.implode("','",$this->escapeSingleQuotes($pluginnames))."');\n";
		}
		return $tmpPHP;
	}
}
