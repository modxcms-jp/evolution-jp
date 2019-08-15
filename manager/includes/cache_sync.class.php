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
	var $cacheRefreshTime = null;

	function __construct()
	{
		if(!$this->target) {
			$this->target = 'pagecache,sitecache';
		}
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
			$qh = $modx->db->select($fields,'[+prefix+]site_content');
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

	function emptyCache()
	{
		global $modx;
		
		$instance_name = '';
		if(is_object($modx))
		{
			$instance_name = get_class($modx);
		}
		$instance_name = strtolower($instance_name);
		if($instance_name!=='documentparser') global $modx;
		
		if(!isset($this->cachePath)) exit('Cache path not set.');
		
		if(strpos($this->target,'pagecache')!==false)
			$result = $this->purgeCacheFiles('pageCache');
		if(strpos($this->target,'sitecache')!==false)
		{
			$this->purgeCacheFiles('siteCache');
			$this->buildCache();
		}
		$this->publishBasicConfig();
		
		$modx->purgeDBCache();
		
		if(isset($result) && $this->showReport==true) $this->showReport($result);
	}
	
	function purgeCacheFiles($target='pageCache')
	{
		if(strpos($this->cachePath,MODX_BASE_PATH)!==0) return;
		
		$filesincache = 0;
		
		if($target==='pageCache') $pattern = '@\.pageCache\.php$@';
		else                      $pattern = '@\.php$@';
		
		$files = $this->getFileList($this->cachePath, $pattern);
		$files = array_reverse($files);
		
		$filesincache = ($files[0] !== $pattern) ? count($files) : 0;
		
		$deletedfiles = array();
		if(0 < $filesincache)
		{
			$cachedir_len = strlen($this->cachePath);
			while ($file_path = array_shift($files))
			{
				$name = substr($file_path,$cachedir_len);
				if (!in_array($name, $deletedfiles))
				{
					if    (is_file($file_path)) $rs = @unlink($file_path);
					elseif(is_dir($file_path))  $rs = @rmdir($file_path);
					if($rs) $deletedfiles[] = $name;
				}
			}
		}
		$deletedfilesincache = count($deletedfiles);
		return array($filesincache,$deletedfilesincache,$deletedfiles);
	}

	function showReport($info)
	{
		list($filesincache,$deletedfilesincache,$deletedfiles) = $info;
		// finished cache stuff.
		global $_lang;
		if(0<$filesincache)
			echo sprintf($_lang['refresh_cache'], $filesincache, $deletedfilesincache);
		else echo '削除対象のページキャッシュはありません。';
		
		if(0<count($deletedfiles)) {
			echo '<p>'.$_lang['cache_files_deleted'].'</p><ul>';
			foreach($deletedfiles as $i=>$deletedfile)
			{
				echo '<li>',$deletedfile,'</li>';
			}
			echo '</ul>';
		}
	}
	
	/****************************************************************************/
	/*  PUBLISH TIME FILE                                                       */
	/****************************************************************************/
	function publishBasicConfig()
	{
		global $modx,$site_sessionname;

		$cacheRefreshTime = $this->getCacheRefreshTime();
		
		$rs = $modx->db->select('setting_name,setting_value','[+prefix+]system_settings');
		while($row = $modx->db->getRow($rs))
		{
			$name  = $row['setting_name'];
			$value = $row['setting_value'];
			$setting[$name] = $value;
		}
		
		// write the file
		$cache_path = $this->cachePath . 'basicConfig.php';
		$content = array();
		$content[] = '<?php';
		$recent_update = $_SERVER['REQUEST_TIME'] + $modx->config['server_offset_time'];
		$content[] = sprintf('$recent_update = %s; // %s'   , $recent_update, date('Y-m-d H:i:s',$recent_update));
		$content[] = sprintf('$cacheRefreshTime = %s; // %s', $cacheRefreshTime, date('Y-m-d H:i:s',$cacheRefreshTime));
		$content[] = sprintf('$cache_type = %s;',       $setting['cache_type']);
		if(isset($site_sessionname) && !empty($site_sessionname))
			$content[] = sprintf('$site_sessionname = "%s";', $site_sessionname);
		
		$content[] = sprintf('$site_status = %s;',      $setting['site_status']);
		$content[] = sprintf('$error_reporting = "%s";',$setting['error_reporting']);
		
		if($modx->array_get($setting,'site_url') && strpos($setting['site_url'],'[(')===false)
			$content[] = sprintf('$site_url = "%s";',   $setting['site_url']);
		
		if($modx->array_get($setting,'base_url') && strpos($setting['base_url'],'[(')===false)
			$content[] = sprintf('$base_url = "%s";',   $setting['base_url']);
		
		if($modx->array_get($setting,'conditional_get'))
			$content[] = sprintf('$conditional_get = "%s";', $setting['conditional_get']);
		
		$rs = $modx->saveToFile($cache_path, join("\n",$content));
		
		if (!$rs) exit("Cannot open file ({$cache_path})");
		
		$f = array('setting_value'=>$recent_update, 'setting_name'=>'recent_update');
		if(isset($setting['recent_update'])) {
			$modx->db->update($f, '[+prefix+]system_settings', "setting_name='recent_update'");
		} else {
			$modx->db->insert($f, '[+prefix+]system_settings');
		}
	}
	
	function setCacheRefreshTime($unixtime) {
		$this->cacheRefreshTime = $unixtime;
	}

	function getCacheRefreshTime() {
		$time = array($this->cacheRefreshTime);
		
		$time[] = $this->minTime(
			'site_content'
			, 'pub_date'
			, '0 < pub_date and published=0 and pub_date<=unpub_date'
		);
		
		$time[] = $this->minTime(
			'site_content'
			, 'unpub_date'
			, '0 < unpub_date AND published=1 AND pub_date<=unpub_date'
		);
		
		$time[] = $this->minTime(
			'site_htmlsnippets'
			, 'pub_date'
			, '0 < pub_date AND published=0 AND pub_date<=unpub_date'
		);
		
		$time[] = $this->minTime(
			'site_htmlsnippets'
			, 'unpub_date'
			, '0 < unpub_date AND published=1 AND pub_date<=unpub_date'
		);
		
		$time[] = $this->minTime(
			'site_revision'
			, 'pub_date'
			, "0 < pub_date AND status = 'standby'"
		);
		foreach ($time as $i=>$v) {
			if(!$v) {
				unset($time[$i]);
			}
		}
		$min = min($time);
		if(!preg_match('@^[1-9][0-9]*$@',$min)) {
			return 0;
		}
		if($this->cacheRefreshTime==0) {
			return $min;
		}
		if ($min < $this->cacheRefreshTime) {
			return $min;
		}
	}
	
	private function minTime($table_name, $field_name, $where) {
		global $modx;
		$rs = $modx->db->select(
			sprintf('MIN(%s) AS result', $field_name)
			,'[+prefix+]' . $table_name
			, sprintf('%s AND UNIX_TIMESTAMP()<%s', $where, $field_name)
			);
		if(!$rs) {
			return 0;
		}
		return $modx->db->getValue($rs);
	}

	/**
	* build siteCache file
	* @param  DocumentParser $modx
	* @return boolean success
	*/
	function buildCache()
	{
		global $modx,$_lang;
		
		$content = "<?php\n";
		$content .= "if(!defined('MODX_BASE_PATH') || strpos(str_replace('\\\\','/',__FILE__), MODX_BASE_PATH)!==0) exit;\n";
		
		// SETTINGS & DOCUMENT LISTINGS CACHE
		
		$config = $this->_get_settings(); // get settings
		if($modx->config['legacy_cache']) 
			$this->_get_aliases();  // get aliases modx: support for alias path
		$content .= $this->_get_content_types(); // get content types
		$this->_get_chunks();   // WRITE Chunks to cache file
		$this->_get_snippets(); // WRITE snippets to cache file
		$this->_get_plugins();  // WRITE plugins to cache file
		$content .= $this->_get_events();   // WRITE system event triggers
		
		// close and write the file
		$content .= "\n";
		$content = str_replace(array("\x0d\x0a", "\x0a", "\x0d"), "\x0a", $content);
		
		// invoke OnBeforeCacheUpdate event
		if ($modx) $modx->invokeEvent('OnBeforeCacheUpdate');
		
		if( !$modx->saveToFile($this->cachePath .'siteCache.idx.php', $content))
		{
			exit('siteCache.idx.php - '.$_lang['file_not_saved']);
		}
		
		$this->cache_put_contents('config.siteCache.idx.php'      , $config);
		if($modx->config['legacy_cache']) {
			$this->cache_put_contents('aliasListing.siteCache.idx.php', $modx->aliasListing);
			$this->cache_put_contents('documentMap.siteCache.idx.php' , $modx->documentMap);
		}
		$this->cache_put_contents('chunk.siteCache.idx.php'       , $modx->chunkCache);
		$this->cache_put_contents('snippet.siteCache.idx.php'     , $modx->snippetCache);
		$this->cache_put_contents('plugin.siteCache.idx.php'      , $modx->pluginCache);
		
		if(!is_file($this->cachePath . '.htaccess'))
		{
			$modx->saveToFile($this->cachePath . '.htaccess', "order deny,allow\ndeny from all\n");
		}
		// invoke OnCacheUpdate event
		if ($modx) $modx->invokeEvent('OnCacheUpdate');
		
		return true;
	}
	
	function cache_put_contents($filename,$content) {
		global $modx,$_lang;
		if(empty($content)) return;
		if(is_array($content)) {
			$content = var_export($content, 'true');
			if(strpos($filename,'documentMap')!==false)
			{
				$content = str_replace("\n", ''    , $content);
				$content = str_replace('),', "),\n", $content);
			}
			$br = "\n";
			$content = "<?php{$br}return {$content};";
			if(strpos($filename,'documentMap')!==false)
				$content = str_replace('return array (', "return array (\n", $content);
		}
		
		$cache_path = $this->cachePath .$filename;

		if( ! $modx->saveToFile($cache_path, $content) ) {
			$msg = "{$cache_path} - ".$_lang['file_not_saved'];
			if(defined('IN_MANAGER_MODE')) {
				header('Content-Type: text/html; charset='.$modx->config['modx_charset']);
				$params = array('manager_url'=>MODX_MANAGER_URL,'theme'=>$modx->config['manager_theme']);
				echo $modx->parseText('<link rel="stylesheet" type="text/css" href="[+manager_url+]media/style/[+theme+]/style.css" />',$params);
				$msg = '<div class="section"><div class="sectionBody">'.$msg.'</div></div>';
			}
			exit($msg);
		}
	}
	
	function _get_settings()
	{
		global $modx;
		
		$rs = $modx->db->select('setting_name,setting_value','[+prefix+]system_settings');
		$config = array();
		while($row = $modx->db->getRow($rs))
		{
			extract($row);
			$config[$setting_name] = $setting_value;
		}
		return $config;
	}
	
	function _get_aliases()
	{
		global $modx;
		
	    $friendly_urls = $modx->db->getValue('setting_value','[+prefix+]system_settings',"setting_name='friendly_urls'");
		if($friendly_urls==1)
		    $use_alias_path = $modx->db->getValue('setting_value','[+prefix+]system_settings',"setting_name='use_alias_path'");
		else $use_alias_path = '';
		$fields = "IF(alias='', id, alias) AS alias, id, parent, isfolder";
		$rs = $modx->db->select($fields,'[+prefix+]site_content','deleted=0','parent, menuindex');
		$row = array();
		$path = '';
		$modx->aliasListing = array();
		$modx->documentMap  = array();
		while ($row = $modx->db->getRow($rs))
		{
			if($use_alias_path==='1')     $path = $this->getParents($row['parent']);
			elseif($use_alias_path==='0') $path = '';
			else                          $path = $row['parent'];
			
			$docid = $row['id'];
			$modx->aliasListing[$docid] = array('id'=>$docid, 'alias'=>$row['alias'], 'path'=>$path, 'parent'=>$row['parent'], 'isfolder'=>$row['isfolder']);
			$modx->documentMap[] = array($row['parent'] => $docid);
		}
	}
	
	function _get_content_types()
	{
		global $modx;
		
		$rs = $modx->db->select('id, contentType','[+prefix+]site_content',"contentType != 'text/html'");
		$_[] = '$c = &$this->contentTypes;';
		$row = array();
		while ($row = $modx->db->getRow($rs))
		{
			extract($row);
			$_[] = '$c' . "[{$id}] = '{$contentType}';";
		}
		return join("\n", $_) . "\n";
	}
	
	function _get_chunks()
	{
		global $modx;
		
		$rs = $modx->db->select('name,snippet','[+prefix+]site_htmlsnippets', "`published`='1'");
		$row = array();
		$modx->chunkCache = array();
		while ($row = $modx->db->getRow($rs))
		{
			$name = $modx->db->escape($row['name']);
			$modx->chunkCache[$name] = $row['snippet'];
		}
	}
	
	function _get_snippets()
	{
		global $modx;
		
		$fields = 'ss.name,ss.snippet,ss.properties,sm.properties as `sharedproperties`';
		$from = "[+prefix+]site_snippets ss LEFT JOIN [+prefix+]site_modules sm on sm.guid=ss.moduleguid";
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
	
	function _get_plugins()
	{
		global $modx;
		
		$fields = 'sp.name,sp.plugincode,sp.properties,sm.properties as `sharedproperties`';
		$from = "[+prefix+]site_plugins sp LEFT JOIN [+prefix+]site_modules sm on sm.guid=sp.moduleguid";
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
	
	function _get_events()
	{
		global $modx;
		
		$fields  = 'sysevt.name as `evtname`, plugs.name as plgname';
		$from[] = '[+prefix+]system_eventnames sysevt';
		$from[] = 'INNER JOIN [+prefix+]site_plugin_events pe ON pe.evtid = sysevt.id';
		$from[] = 'INNER JOIN [+prefix+]site_plugins plugs ON plugs.id = pe.pluginid';
		$from = join(' ', $from);
		$where   = 'plugs.disabled=0';
		$orderby = 'sysevt.name,pe.priority';
		$rs = $modx->db->select($fields,$from,$where,$orderby);
		$_[] = '$e = &$this->pluginEvent;';
		$events = array();
		$row = array();
		while ($row = $modx->db->getRow($rs))
		{
			$evtname = $row['evtname'];
			if(!isset($events[$evtname])) $events[$evtname] = array();
			$events[$evtname][] = $row['plgname'];
		}
		foreach($events as $evtname => $pluginnames)
		{
			$pluginnames = implode("','",$this->escapeSingleQuotes($pluginnames));
			$_[] = sprintf("\$e['%s'] = array('%s');", $evtname, $pluginnames);
		}
		return join("\n",$_) . "\n";
	}
	
	function tableOpt()
	{
		global $modx;
		
		$modx->db->optimize('[+prefix+]site_content');
		$modx->db->optimize('[+prefix+]active_users');
		$modx->db->optimize('[+prefix+]manager_log');
		$modx->db->optimize('[+prefix+]event_log');
		$modx->db->optimize('[+prefix+]site_htmlsnippets');
	}
	
    function getFileList($dir, $pattern='@\.php$@') {
        $dir = rtrim($dir, '/');
    	$tmp = array_diff(scandir($dir),array('..','.'));
    	$files = array();
    	foreach($tmp as $val){
    		$files[] = $dir . '/' . $val;
        }

        $list = array();
        foreach ((array)$files as $obj) {
            if (is_file($obj) && preg_match($pattern,$obj)) $list[] = $obj;
        	elseif (is_dir($obj))  {
        		$list[] = $obj;
        		$_ = $this->getFileList($obj, $pattern);
        		$list = array_merge($list, $_);
        	}
        }
        return $list;
    }
}
