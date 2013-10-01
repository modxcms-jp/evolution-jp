<?php
/**
 *	MODX Document Parser
 *	Function: This class contains the main document parsing functions
 *
 */

class DocumentParser {
    var $db; // db object
    var $event, $Event; // event object
    var $pluginEvent = array();
    var $config= null;
    var $rs;
    var $result;
    var $sql;
    var $table_prefix;
    var $debug;
    var $documentIdentifier;
    var $documentMethod;
    var $documentGenerated;
    var $documentContent;
    var $tstart;
    var $mstart;
    var $minParserPasses;
    var $maxParserPasses;
    var $documentObject;
    var $templateObject;
    var $snippetObjects;
    var $stopOnNotice;
    var $executedQueries;
    var $queryTime;
    var $currentSnippet;
    var $aliases;
    var $entrypage;
    var $documentListing;
    var $dumpSnippets;
    var $snipCode;
    var $chunkCache;
    var $snippetCache;
    var $contentTypes;
    var $dumpSQL;
    var $queryCode;
    var $virtualDir;
    var $placeholders;
    var $sjscripts = array();
    var $jscripts = array();
    var $loadedjscripts = array();
    var $documentMap;
    var $forwards= 3;
    var $referenceListing;
    var $childrenList;
    var $safeMode;
    var $qs_hash;
    var $cacheRefreshTime;
    var $error_reporting;
    var $processCache;
    var $http_status_code;
    var $directParse;

	function __call($name,$args) {
		include_once(MODX_BASE_PATH . 'manager/includes/extenders/deprecated.functions.inc.php');
		if(method_exists($this->old,$name)) return call_user_func_array(array($this->old,$name),$args);
	}
	
    // constructor
	function __construct()
	{
        global $database_server;
        if(substr(PHP_OS,0,3) === 'WIN' && $database_server==='localhost') $database_server = '127.0.0.1';
        
		$this->loadExtension('DBAPI') or die('Could not load DBAPI class.'); // load DBAPI class
		if($this->isBackend()) $this->loadExtension('ManagerAPI');
		
		// events
		$this->event= new SystemEvent();
		$this->Event= & $this->event; //alias for backward compatibility
		$this->minParserPasses = 1; // min number of parser recursive loops or passes
		$this->maxParserPasses = 10; // max number of parser recursive loops or passes
		$this->dumpSQL = false;
		$this->dumpSnippets = false; // feed the parser the execution start time
		$this->stopOnNotice = false;
		$this->safeMode     = false;
		$this->directParse  = 0;
		// set track_errors ini variable
		@ ini_set('track_errors', '1'); // enable error tracking in $php_errormsg
		$this->error_reporting = 1;
		// Don't show PHP errors to the public
		if($this->checkSession()===false) @ini_set('display_errors','0');
		if(!isset($this->tstart))
		{
			$mtime = explode(' ',microtime());
			$this->tstart = $mtime['1'] + $mtime['0'];
			$this->mstart = memory_get_usage();
		}
	}

	// loads an extension from the extenders folder
	function loadExtension($extname)
	{
        $extname = trim(str_replace(array("..","/","\\"),"",mb_strtolower($extname)));
        $filename = MODX_MANAGER_PATH."includes/extenders/{$extname}.extenders.inc.php";

        return is_file($filename) ? include $filename : false;
	}
	
	function executeParser($id='')
	{
		ob_start();
		set_error_handler(array(& $this,'phpError'), E_ALL); //error_reporting(0);
		
		$this->http_status_code = '200';
		
		if(!empty($_SERVER['QUERY_STRING']) && $id==='')
		{
			$qs = $_GET;
			if(isset($qs['id'])) unset($qs['id']);
			if(0 < count($qs)) $this->qs_hash = '_' . md5(join('&',$qs));
			else $this->qs_hash = '';
		}
		
		// get the settings
		if(!$this->db->conn)      $this->db->connect();
		if(!isset($this->config)) $this->config = $this->getSettings();
		if(!$this->processCache)  $this->initProcessCache();
		
		if(preg_match('@^[0-9]+$@',$id))
		{
			$_REQUEST['id'] = $id;
			$_GET['id'] = $id;
			$_SERVER['REQUEST_URI'] = $this->config['base_url'] . 'index.php?id=' . $id;
			$this->directParse = 1;
		}
		
		if(!isset($_REQUEST['id']))
		{
			$_REQUEST['q'] = substr($_SERVER['REQUEST_URI'],strlen($this->config['base_url']));
			if(strpos($_REQUEST['q'],'?')) $_REQUEST['q'] = substr($_REQUEST['q'],0,strpos($_REQUEST['q'],'?'));
		}
		if(isset($_REQUEST['q']) && strpos($_REQUEST['q'],'?')!==false && !isset($_GET['id'])) $_REQUEST['q'] = '';
		elseif(isset($_REQUEST['q']) && $_REQUEST['q']=='index.php') $_REQUEST['q'] = '';
		
		if(0 < count($_POST) && $id==='') $this->config['cache_type'] = 0;
		
		if($id==='')
		{
			$this->documentOutput = $this->get_static_pages();
			if(!empty($this->documentOutput))
			{
				$this->documentOutput = $this->parseDocumentSource($this->documentOutput);
				$this->invokeEvent('OnWebPagePrerender');
				echo $this->documentOutput;
				$this->invokeEvent('OnWebPageComplete');
				exit;
			}
		}
		
		// IIS friendly url fix
		if (strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false)
		{
			$this->_IIS_furl_fix();
		}
		
		// check site settings
		$site_status = $this->checkSiteStatus();
		if($this->directParse==1)
		{
			$this->documentMethod     = 'id';
			$this->documentIdentifier = $id;
		}
		elseif ($site_status===false)
		{
			header("Content-Type: text/html; charset={$this->config['charset']}");
			header('HTTP/1.0 503 Service Unavailable');
			if (!$this->config['site_unavailable_page'])
			{
				// display offline message
				echo $this->config['site_unavailable_message'];
				exit; // stop processing here, as the site's offline
			}
			else
			{
				// setup offline page document settings
				$this->documentMethod= 'id';
				$this->documentIdentifier= $this->config['site_unavailable_page'];
			}
		}
		else
		{
			// make sure the cache doesn't need updating
			$this->checkPublishStatus();
			
			// find out which document we need to display
			$this->documentMethod= $this->getDocumentMethod();
			$this->documentIdentifier= $this->getDocumentIdentifier($this->documentMethod);
		}
		
		if ($this->documentMethod == 'none' || ($_SERVER['REQUEST_URI']===$this->config['base_url'] && $site_status!==false))
		{
			$this->documentMethod= 'id'; // now we know the site_start, change the none method to id
			$this->documentIdentifier = $this->config['site_start'];
		}
		elseif ($this->documentMethod == 'alias')
		{
			$this->documentIdentifier= $this->cleanDocumentIdentifier($this->documentIdentifier);
		}
		
		if ($this->documentMethod === 'alias')
		{
			// Check use_alias_path and check if $this->virtualDir is set to anything, then parse the path
			if ($this->config['use_alias_path'] === '1')
			{
				$alias = $this->documentIdentifier;
				
				if(strlen($this->virtualDir) > 0)
				{
					$alias = $this->virtualDir . '/' . $alias;
				}
				
				if ($this->getIdFromAlias($alias)!==false)
				{
					$this->documentIdentifier= $this->getIdFromAlias($alias);
				}
				else
				{
					$alias .= $this->config['friendly_url_suffix'];
					if ($this->getIdFromAlias($alias)!==false)
					{
						$this->documentIdentifier= $this->getIdFromAlias($alias);
					}
					else $this->sendErrorPage();
				}
			}
			else
			{
				$this->documentIdentifier= $this->getIdFromAlias($this->documentIdentifier);
			}
			$this->documentMethod= 'id';
		}
		if($this->documentMethod==='id' && isset($alias))
		{
			switch($this->documentIdentifier)
			{
				case $this->config['site_start']:
				case $this->config['site_unavailable_page']:
				case $this->config['unauthorized_page']:
					break;
				default:
					if($this->getIdFromAlias($alias)===false) $this->sendErrorPage();
			}
		}
		// invoke OnWebPageInit event
		$this->invokeEvent('OnWebPageInit');
		
		$result = $this->prepareResponse();
		return $result;
	}
	
	function prepareResponse()
	{
		// we now know the method and identifier, let's check the cache
		$this->documentContent= $this->checkCache($this->documentIdentifier);
		if ($this->documentContent != '')
		{
			$this->invokeEvent('OnLoadWebPageCache'); // invoke OnLoadWebPageCache  event
		}
		else
		{
			// get document object
			
			$this->documentObject= $this->getDocumentObject($this->documentMethod, $this->documentIdentifier);
			
			// validation routines
			if ($this->documentObject['deleted'] == 1)
			{
				if($this->http_status_code == '200') $this->sendErrorPage();
			}
			//  && !$this->checkPreview()
			if ($this->documentObject['published'] == 0)
			{
			// Can't view unpublished pages
				if (!$this->hasPermission('view_unpublished'))
				{
					if($this->http_status_code == '200') $this->sendErrorPage();
				}
				else
				{
					// Inculde the necessary files to check document permissions
					include_once ($this->config['base_path'] . 'manager/processors/user_documents_permissions.class.php');
					$udperms= new udperms();
					$udperms->user= $this->getLoginUserID();
					$udperms->document= $this->documentIdentifier;
					$udperms->role= $_SESSION['mgrRole'];
					// Doesn't have access to this document
					if (!$udperms->checkPermissions())
					{
						if($this->http_status_code == '200') $this->sendErrorPage();
					}
				}
			}
			// check whether it's a reference
			if($this->documentObject['type'] == 'reference')
			{
				if(preg_match('@^[0-9]+$@',$this->documentObject['content']))
				{
					// if it's a bare document id
					$this->documentObject['content']= $this->makeUrl($this->documentObject['content']);
				}
				$this->documentObject['content']= $this->parseDocumentSource($this->documentObject['content']);
				$this->sendRedirect($this->documentObject['content'], 0, '', 'HTTP/1.0 301 Moved Permanently');
			}
			// check if we should not hit this document
			if($this->documentObject['donthit'] == 1)
			{
				$this->config['track_visitors']= 0;
			}
			// get the template and start parsing!
			if(!$this->documentObject['template'])
			{
				$this->documentContent= '[*content*]'; // use blank template
			}
			else
			{
				$template= $this->db->getObject('site_templates',"id='{$this->documentObject['template']}'");
				if($template->id)
				{
					if(!empty($template->parent))
					{
						$parent = $this->db->getObject('site_templates',"id='{$template->parent}'");
						$loopcount = 0;
						while($loopcount<20)
						{
							$loopcount++;
							if($template->id !== $parent->id)
							{
								$template->content = str_replace('[*content*]', $template->content, $parent->content);
								if(!empty($parent->parent)) $parent = $this->db->getObject('site_templates',"id='{$parent->parent}'");
								else break;
							}
							else break;
						}
					}
					$this->documentContent = $template->content;
					
					if(!empty($template->doc_encoding))
					{
						$this->config['doc_encoding'] = $template->doc_encoding;
						$this->config['charset']      = $this->getMimeName($template->doc_encoding);
					}
				}
				else
				{
					$this->messageQuit('Template does not exist. Or it was deleted.');
				}
			}
			// invoke OnLoadWebDocument event
			$this->invokeEvent('OnLoadWebDocument');
			
			// Parse document source
			$this->documentContent= $this->parseDocumentSource($this->documentContent);
		}
		if($this->directParse==0)
		{
			register_shutdown_function(array (
			& $this,
			'postProcess'
			)); // tell PHP to call postProcess when it shuts down
		}
		$result = $this->outputContent();
		return $result;
	}
	
	function outputContent($noEvent= false)
	{
		global $site_sessionname;
		
		$this->documentOutput= $this->documentContent;
		
		if ($this->documentGenerated           == 1
		 && $this->documentObject['cacheable'] == 1
		 && $this->documentObject['type']      == 'document'
		 && $this->documentObject['published'] == 1)
		{
			if (!empty($this->sjscripts)) $this->documentObject['__MODxSJScripts__'] = $this->sjscripts;
			if (!empty($this->jscripts))  $this->documentObject['__MODxJScripts__'] = $this->jscripts;
		}
		
		// check for non-cached snippet output
		if (strpos($this->documentOutput, '[!') !== false)
		{
			if($this->config['cache_type']==2) $this->config['cache_type'] = 1;
			
			// Parse document source
			$passes = $this->minParserPasses;
			
			for ($i= 0; $i < $passes; $i++)
			{
				if($i == ($passes -1)) $st= md5($this->documentOutput);
				
				$this->documentOutput = str_replace(array('[!','!]'), array('[[',']]'), $this->documentOutput);
				$this->documentOutput = $this->parseDocumentSource($this->documentOutput);
				
				if($i == ($passes -1) && $i < ($this->maxParserPasses - 1))
				{
					$et = md5($this->documentOutput);
					if($st != $et) $passes++;
				}
			}
		}
		
		// Moved from prepareResponse() by sirlancelot
		if ($js= $this->getRegisteredClientStartupScripts())
		{
			$this->documentOutput= str_ireplace('</head>', "{$js}\n</head>", $this->documentOutput);
		}
		
		// Insert jscripts & html block into template - template must have a </body> tag
		if ($js= $this->getRegisteredClientScripts())
		{
			$this->documentOutput= str_ireplace('</body>', "{$js}\n</body>", $this->documentOutput);
		}
		// End fix by sirlancelot
		
		// remove all unused placeholders
		if (strpos($this->documentOutput, '[+') !==false)
		{
			$matches= array ();
			preg_match_all('~\[\+(.*?)\+\]~s', $this->documentOutput, $matches);
			if ($matches['0'])
			$this->documentOutput= str_replace($matches['0'], '', $this->documentOutput);
		}
		
		if(strpos($this->documentOutput,'[~')!==false) $this->documentOutput = $this->rewriteUrls($this->documentOutput);
		
		// send out content-type and content-disposition headers
		if (IN_PARSER_MODE == 'true')
		{
			$type = $this->documentObject['contentType'];
			if(empty($type)) $type = 'text/html';
			
			header("Content-Type: {$type}; charset={$this->config['charset']}");
			//            if (($this->documentIdentifier == $this->config['error_page']) || $redirect_error)
			//                header('HTTP/1.0 404 Not Found');
			if ($this->documentObject['content_dispo'] == 1)
			{
				if ($this->documentObject['alias'])
				{
					$name= urldecode($this->documentObject['alias']);
				}
				else
				{
					// strip title of special characters
					$name= $this->documentObject['pagetitle'];
					$name= strip_tags($name);
					$name= preg_replace('/&.+?;/', '', $name); // kill entities
					$name= preg_replace('/\s+/', '-', $name);
					$name= preg_replace('|-+|', '-', $name);
					$name= trim($name, '-');
				}
				$header= 'Content-Disposition: attachment; filename=' . $name;
				header($header);
			}
		}
		if($this->config['cache_type'] !=2)
		{
			$this->documentOutput = $this->mergeBenchmarkContent($this->documentOutput);
		}
		
		if ($this->dumpSQL)
		{
			$this->documentOutput = preg_replace("/(<\/body>)/i", $this->queryCode . "\n\\1", $this->documentOutput);
		}
		if ($this->dumpSnippets)
		{
			$this->documentOutput = preg_replace("/(<\/body>)/i", $this->snipCode . "\n\\1", $this->documentOutput);
		}
		
		// invoke OnLogPageView event
		if ($this->config['track_visitors'] == 1)
		{
			$this->invokeEvent('OnLogPageHit');
		}
		
		// invoke OnWebPagePrerender event
		if (!$noEvent)
		{
			$this->invokeEvent('OnWebPagePrerender');
		}
		
		if(strpos($this->documentOutput,'[^')) echo $this->mergeBenchmarkContent($this->documentOutput);
		else                                   echo $this->documentOutput;
		
		$result = ob_get_clean();
		if(strtolower($this->config['doc_encoding'])!=='utf-8')
		{
			$result = mb_convert_encoding($result, $this->config['doc_encoding'], 'utf-8');
		}
		return $result;
	}
	
	function postProcess()
	{
		// if the current document was generated, cache it!
		if ($this->documentGenerated           == 1
		 && $this->documentObject['cacheable'] == 1
		 && $this->documentObject['type']      == 'document'
		 && $this->documentObject['published'] == 1)
		{
			$docid = $this->documentIdentifier;
			
			// invoke OnBeforeSaveWebPageCache event
			$this->invokeEvent('OnBeforeSaveWebPageCache');
			// get and store document groups inside document object. Document groups will be used to check security on cache pages
			$dsq = $this->db->select('document_group', '[+prefix+]document_groups', "document='{$docid}'");
			$docGroups= $this->db->getColumn('document_group', $dsq);
			
			// Attach Document Groups and Scripts
			if (is_array($docGroups))
			{
				$this->documentObject['__MODxDocGroups__'] = implode(',', $docGroups);
			}
			
			$base_path = $this->config['base_path'];
			
			switch($this->config['cache_type'])
			{
				case '1':
					$cacheContent  = "<?php die('Unauthorized access.'); ?>\n";
					$cacheContent .= serialize($this->documentObject);
					$cacheContent .= "<!--__MODxCacheSpliter__-->{$this->documentContent}";
					$filename = "docid_{$docid}{$this->qs_hash}";
					break;
				case '2':
					$cacheContent .= serialize($this->documentObject['contentType']);
					$cacheContent .= "<!--__MODxCacheSpliter__-->{$this->documentOutput}";
					$filename = md5($_SERVER['REQUEST_URI']);
					break;
			}
			
			switch($this->http_status_code)
			{
				case '404':
				case '403':
					$filename = md5($this->makeUrl($docid));
					break;
			}
			
			if(mt_rand(0,99) < 5)
			{
				$file_count = count(glob($this->config['base_path'].'assets/cache/*.php'));
				if(1000 < $file_count) $this->clearCache();
			}
			
			$page_cache_path = "{$base_path}assets/cache/{$filename}.pageCache.php";
			file_put_contents($page_cache_path, $cacheContent, LOCK_EX);
		}
		
		// Useful for example to external page counters/stats packages
		$this->invokeEvent('OnWebPageComplete');
		
		// end post processing
	}
	
	private function join($delim=',', $array, $prefix='')
	{
		foreach($array as $i=>$v)
		{
			$array[$i] = $prefix . trim($v);
		}
		$str = join($delim,$array);
		
		return $str;
	}
	
	function setOption($key, $value='')
	{
		$this->config[$key] = $value;
	}
	
	function regOption($key, $value='')
	{
		$this->config[$key] = $value;
		$f['setting_name']  = $key;
		$f['setting_value'] = $this->db->escape($value);
		$key = $this->db->escape($key);
		$rs = $this->db->select('*','[+prefix+]system_settings', "setting_name='{$key}'");
		
		if($this->db->getRecordCount($rs)==0)
		{
			$this->db->insert($f,'[+prefix+]system_settings');
			$diff = $this->db->getAffectedRows();
			if(!$diff)
			{
				$this->messageQuit('Error while inserting new option into database.', $this->db->lastQuery);
				exit();
			}
		}
		else
		{
			$this->db->update($f,'[+prefix+]system_settings', "setting_name='{$key}'");
		}
	}
	
	function getOption($key, $default = null, $options = null, $skipEmpty = false)
	{
		$option= $default;
		if (is_array($key) || strpos($key,',')!==false)
		{
			$key = explode(',',$key);
			
			if (!is_array($option))
			{
				$default= $option;
				$option= array();
			}
			foreach ($key as $k)
			{
				$k = trim($k);
				$option[$k]= $this->getOption($k, $default, $options);
			}
		}
		elseif (is_string($key) && !empty($key))
		{
			if (is_array($options) && !empty($options) && array_key_exists($key, $options) && (!$skipEmpty || ($skipEmpty && $options[$key] !== '')))
			{
				$option= $options[$key];
			}
			elseif(is_array($this->config) && !empty($this->config) && array_key_exists($key, $this->config) && (!$skipEmpty || ($skipEmpty && $this->config[$key] !== '')))
			{
				$option= $this->config[$key];
			}
		}
		return $option;
	}
	
	function getMicroTime()
	{
		list ($usec, $sec)= explode(' ', microtime());
		return ((float) $usec + (float) $sec);
	}
	
	function get_static_pages()
	{
		$filepath = $_SERVER['REQUEST_URI'];
		if(strpos($filepath,'?')!==false) $filepath = substr($filepath,0,strpos($filepath,'?'));
		$filepath = substr($filepath,strlen($this->config['base_url']));
		if(substr($filepath,-1)==='/' || empty($filepath)) $filepath .= 'index.html';
		$static_resources_path = $this->config['base_path'] . 'assets/static_resources/';
		$filepath = $static_resources_path . $filepath;
		if(is_file($filepath)!==false)
		{
			$ext = strtolower(substr($filepath,strrpos($filepath,'.')));
			switch($ext)
			{
				case '.html':
				case '.htm':
					$mime_type = 'text/html'; break;
				case '.xml':
				case '.rdf':
					$mime_type = 'text/xml'; break;
				case '.css':
					$mime_type = 'text/css'; break;
				case '.js':
					$mime_type = 'text/javascript'; break;
				case '.txt':
					$mime_type = 'text/plain'; break;
				case '.ico':
				case '.jpg':
				case '.jpeg':
				case '.png':
				case '.gif':
					if($ext==='.ico') $mime_type = 'image/x-icon';
					else
					{
						$info = getImageSize($filepath);
						$mime_type = $info['mime'];
					}
					header("Content-type: {$mime_type}");
					readfile($filepath);
					exit;
				default:
					exit;
			}
			header("Content-type: {$mime_type}");
			$src = file_get_contents($filepath);
		}
		else $src = false;
		
		return $src;
	}
	
	function getSiteCache()
	{
		$cpath = MODX_BASE_PATH . 'assets/cache/siteCache.idx.php';
		
		if(is_file($cpath)) $included= include_once ($cpath);
		
		if(!isset($included)||!$included)
		{
			$rs = $this->rebuildCache();
			if($rs && is_file($cpath)) include($cpath);
		}
	}
	
	function rebuildCache($params=array())
	{
		include_once MODX_MANAGER_PATH . 'processors/cache_sync.class.processor.php';
		$cache = new synccache();
		$cache->setCachepath(MODX_BASE_PATH . 'assets/cache/');
		$cache->setReport(false);
		if(isset($params['cacheRefreshTime'])) $cache->cacheRefreshTime = $params['cacheRefreshTime'];
		$rs = $cache->buildCache($this);
		if($rs) $cache->publish_time_file($this);
		return $rs;
	}
	
	function getSettings()
	{
		if(!isset($this->config) || !is_array($this->config) || empty ($this->config))
		{
			$cpath = MODX_BASE_PATH . 'assets/cache/config.siteCache.idx.php';
			if(is_file($cpath))
			{
				$config = @include_once($cpath);
				if(isset($config) && is_array($config)) $this->config = $config;
			}
			if(!isset($this->config) || !is_array($this->config) || empty ($this->config))
			{
				$this->clearCache();
				if(is_file($cpath))
				{
					$config = @include_once($cpath);
					if(isset($config) && is_array($config)) $this->config = $config;
				}
				if(!isset($this->config) || !is_array($this->config) || empty ($this->config))
				{
					$result= $this->db->select('setting_name, setting_value','[+prefix+]system_settings');
					while ($row= $this->db->getRow($result, 'both'))
					{
						$this->config[$row['0']]= $row['1'];
					}
				}
			}
			$this->getSiteCache();
			
			// added for backwards compatibility - garry FS#104
			$this->config['etomite_charset'] = & $this->config['modx_charset'];
			
			// store base_url and base_path inside config array
			if(!isset($this->config['base_url']) || empty($this->config['base_url']))
			{
				$this->config['base_url']= MODX_BASE_URL;
			}
			$this->config['base_path']= MODX_BASE_PATH;
			$this->config['core_path']= MODX_CORE_PATH;
			if(!isset($this->config['site_url']) || empty($this->config['site_url']))
			{
				$this->config['site_url']= MODX_SITE_URL;
			}
		}
		// load user setting if user is logged in
		$usrSettings= array();
		if ($id= $this->getLoginUserID())
		{
			$usrType= $this->getLoginUserType();
			if (isset ($usrType) && $usrType == 'manager')
			{
				$usrType= 'mgr';
			}
			
			if ($usrType == 'mgr' && $this->isBackend())
			{
				// invoke the OnBeforeManagerPageInit event, only if in backend
				$this->invokeEvent('OnBeforeManagerPageInit');
			}
			if (isset ($_SESSION["{$usrType}UsrConfigSet"]) && 0 < count($_SESSION["{$usrType}UsrConfigSet"]))
			{
				$usrSettings= & $_SESSION["{$usrType}UsrConfigSet"];
			}
			else
			{
				if ($usrType == 'web')
				{
					$from  = '[+prefix+]web_user_settings';
					$where ="webuser='{$id}'";
				}
				else
				{
					$from  = '[+prefix+]user_settings';
					$where = "user='{$id}'";
				}
				$result= $this->db->select('setting_name, setting_value',$from,$where);
				while ($row= $this->db->getRow($result, 'both'))
				{
					$usrSettings[$row['0']]= $row['1'];
				}
				if (isset ($usrType))
				{
					$_SESSION[$usrType . 'UsrConfigSet']= $usrSettings; // store user settings in session
				}
			}
		}
		if($this->isFrontend() && $mgrid= $this->getLoginUserID('mgr'))
		{
			$musrSettings= array ();
			if(isset ($_SESSION['mgrUsrConfigSet']))
			{
				$musrSettings= & $_SESSION['mgrUsrConfigSet'];
			}
			else
			{
				if($result= $this->db->select('setting_name, setting_value','[+prefix+]user_settings',"user='{$mgrid}'"))
				{
					while ($row= $this->db->getRow($result, 'both'))
					{
						$usrSettings[$row['0']]= $row['1'];
					}
					$_SESSION['mgrUsrConfigSet']= $musrSettings; // store user settings in session
				}
			}
			if(!empty ($musrSettings))
			{
				$usrSettings= array_merge($musrSettings, $usrSettings);
			}
		}
		$this->config= array_merge($this->config, $usrSettings);
		if(isset($this->config['filemanager_path']))
		{
			$this->config['filemanager_path'] = str_replace('[(base_path)]',MODX_BASE_PATH,$this->config['filemanager_path']);
		}
		if(isset($this->config['rb_base_dir']))
		{
			$this->config['rb_base_dir']      = str_replace('[(base_path)]',MODX_BASE_PATH,$this->config['rb_base_dir']);
		}
		return $this->config;
	}
	
	function getDocumentMethod()
	{
		// function to test the query and find the retrieval method
		if(isset($_REQUEST['q']))       return 'alias';
		elseif(isset($_REQUEST['id']))  return 'id';
		else                            return 'none';
	}
	
	function getDocumentIdentifier($method)
	{
		// function to test the query and find the retrieval method
		switch ($method)
		{
			case 'alias' :
				$docIdentifier= $this->db->escape($_REQUEST['q']);
				break;
			case 'id' :
				if (!preg_match('@^[0-9]+$@', $_REQUEST['id']))
				{
					$this->sendErrorPage();
				}
				else
				{
					$docIdentifier= intval($_REQUEST['id']);
				}
				break;
			default:
				$docIdentifier= $this->config['site_start'];
		}
		return $docIdentifier;
	}
	
	// check for manager login session
	function checkSession()
	{
		if(isset($_SESSION['mgrValidated']) && !empty($_SESSION['mgrValidated']))
		{
			return true;
		}
		else return false;
	}

	function checkPreview()
	{
		if ($this->checkSession() == true)
		{
			if (isset ($_REQUEST['z']) && $_REQUEST['z'] == 'manprev')
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}
	
	// check if site is offline
	function checkSiteStatus()
	{
		if($this->config['site_status'] == 1)
		{
			return true; // site online
		}
		elseif($this->config['site_status'] == 0 && $this->checkSession())
		{
			return true; // site offline but launched via the manager
		}
		else
		{
			return false; // site is offline
		}
	}
	
	function cleanDocumentIdentifier($qOrig)
	{
		if(empty($qOrig)) $qOrig = $this->config['site_start'];
		$q = trim($qOrig,'/');
		/* Save path if any */
		/* FS#476 and FS#308: only return virtualDir if friendly paths are enabled */
		if ($this->config['use_alias_path'] == 1)
		{
			$this->virtualDir = dirname($q);
			$this->virtualDir = ($this->virtualDir === '.') ? '' : $this->virtualDir;
			$q = explode('/', $q);
			$q = end($q);
		}
		else
		{
			$this->virtualDir= '';
		}
		$prefix = $this->config['friendly_url_prefix'];
		$suffix = $this->config['friendly_url_suffix'];
		if(!empty($prefix) && strpos($q,$prefix)!==false) $q = preg_replace('@^' . $prefix . '@',  '', $q);
		if(!empty($suffix) && strpos($q,$suffix)!==false) $q = preg_replace('@'  . $suffix . '$@', '', $q);
		if (preg_match('@^[1-9][0-9]*$@',$q))
		{ /* we got an ID returned, check to make sure it's not an alias */
			/* FS#476 and FS#308: check that id is valid in terms of virtualDir structure */
			if ($this->config['use_alias_path'] == 1)
			{
				$vdir = $this->virtualDir;
				if (
					(
						($vdir != '' && !$this->getIdFromAlias("{$vdir}/{$q}"))
						||
						($vdir == '' && !$this->getIdFromAlias($q))
					)
					&&
					(
						($vdir != '' && in_array($q, $this->getChildIds($this->getIdFromAlias($vdir), 1)))
						||
						($vdir == '' && in_array($q, $this->getChildIds(0, 1)))
					))
				{
					$this->documentMethod = 'id';
					return $q;
				}
				else
				{ /* not a valid id in terms of virtualDir, treat as alias */
					$this->documentMethod = 'alias';
					return $q;
				}
			}
			else
			{
				$this->documentMethod = 'id';
				return $q;
			}
		}
		else
		{ /* we didn't get an ID back, so instead we assume it's an alias */
			if ($this->config['friendly_alias_urls'] !== '1')
			{
				$q= $qOrig;
			}
			$this->documentMethod= 'alias';
			return $q;
			}
		}

	function checkCache($id)
	{
		
		if(isset($this->config['cache_type']) && $this->config['cache_type'] == 0) return ''; // jp-edition only
		$cacheFile = "{$this->config['base_path']}assets/cache/docid_{$id}{$this->qs_hash}.pageCache.php";
		
		if(isset($_SESSION['mgrValidated']) || 0 < count($_POST)) $this->config['cache_type'] = '1';
		
		if(is_file($cacheFile) && isset($this->config['cache_expire']) && !empty($this->config['cache_expire']))
		{
			$timestamp = filemtime($cacheFile);
			$timestamp += $this->config['cache_expire'];
			if($timestamp <time() )
			{
				@unlink($cacheFile);
				$this->documentGenerated = 1;
				return '';
			}
		}
		
		if($this->config['cache_type'] == 2)
		{
			$this->documentGenerated = 1;
			return '';
		}
		elseif(is_file($cacheFile))
		{
			$flContent = file_get_contents($cacheFile, false);
		}
		if(!is_file($cacheFile) || empty($flContent))
		{
			$this->documentGenerated = 1;
			return '';
		}
		
		$this->documentGenerated = 0;
		
		$flContent = substr($flContent, 37); // remove php header
		$a = explode('<!--__MODxCacheSpliter__-->', $flContent, 2);
		if(count($a) == 1)
		{
			return $a['0']; // return only document content
		}
		
		$docObj = unserialize(trim($a['0'])); // rebuild document object
		// add so - check page security(admin(mgrRole=1) is pass)
		if(!(isset($_SESSION['mgrRole']) && $_SESSION['mgrRole'] == 1) 
		    && $docObj['privateweb'] && isset ($docObj['__MODxDocGroups__']))
		{
			$pass = false;
			$usrGrps = $this->getUserDocGroups();
			$docGrps = explode(',',$docObj['__MODxDocGroups__']);
			// check is user has access to doc groups
			if(is_array($usrGrps))
			{
				foreach ($usrGrps as $k => $v)
				{
					$v = trim($v);
					if(in_array($v, $docGrps))
					{
						$pass = true;
						break;
					}
				}
			}
			// diplay error pages if user has no access to cached doc
			if(!$pass)
			{
				if($this->config['unauthorized_page'])
				{
					// check if file is not public
					$secrs = $this->db->select('id', '[+prefix+]document_groups', "document='{$id}'",'',1);
					if($secrs)
					{
						$seclimit = $this->db->getRecordCount($secrs);
					}
				}
				if($seclimit > 0)
				{
					// match found but not publicly accessible, send the visitor to the unauthorized_page
					$this->sendUnauthorizedPage();
				}
				else
				{
					// no match found, send the visitor to the error_page
					$this->sendErrorPage();
				}
			}
			// Grab the Scripts
			if(isset($docObj['__MODxSJScripts__'])) $this->sjscripts = $docObj['__MODxSJScripts__'];
			if(isset($docObj['__MODxJScripts__']))  $this->jscripts  = $docObj['__MODxJScripts__'];
			
			// Remove intermediate variables
			unset($docObj['__MODxDocGroups__'], $docObj['__MODxSJScripts__'], $docObj['__MODxJScripts__']);
		}
		$this->documentObject = $docObj;
		return $a['1']; // return document content
	}
	
	function checkPublishStatus()
	{
		$cache_path= "{$this->config['base_path']}assets/cache/sitePublishing.idx.php";
		if($this->cacheRefreshTime=='')
		{
			if(is_file($cache_path))
			{
				global $cacheRefreshTime;
				include_once($cache_path);
				$this->cacheRefreshTime = $cacheRefreshTime;
			}
			else $this->cacheRefreshTime = 0;
		}
		$timeNow= time() + $this->config['server_offset_time'];
		
		if ($timeNow < $this->cacheRefreshTime || $this->cacheRefreshTime == 0) return;
		
		// now, check for documents that need publishing
		$fields = "published='1', publishedon=pub_date";
		$where = "pub_date <= {$timeNow} AND pub_date!=0 AND published=0";
		$rs = $this->db->update($fields,'[+prefix+]site_content',$where);
		
		// now, check for documents that need un-publishing
		$fields = "published='0', publishedon='0'";
		$where = "unpub_date <= {$timeNow} AND unpub_date!=0 AND published=1";
		$rs = $this->db->update($fields,'[+prefix+]site_content',$where);
	
		// now, check for chunks that need publishing
		$fields = "published='1'";
		$where = "pub_date <= {$timeNow} AND pub_date!=0 AND published=0";
		$rs = $this->db->update($fields,'[+prefix+]site_htmlsnippets',$where);
		
		// now, check for chunks that need un-publishing
		$fields = "published='0'";
		$where = "unpub_date <= {$timeNow} AND unpub_date!=0 AND published=1";
		$rs = $this->db->update($fields,'[+prefix+]site_htmlsnippets',$where);
	
		unset($this->chunkCache);
		$this->setChunkCache();
	
		// clear the cache
		$this->clearCache();
	}
	
    function getTagsFromContent($content,$left='[+',$right='+]') {
        $hash = explode($left,$content);
        foreach($hash as $i=>$v) {
          if(0<$i) $hash[$i] = $left.$v;
        }
        
        $i=0;
        $count = count($hash);
        $safecount = 0;
        $temp_hash = array();
        while(0<$count) {
            $open  = 1;
            $close = 0;
            $safecount++;
            if(1000<$safecount) break;
            while($close < $open && 0 < $count) {
                $safecount++;
                if(!isset($temp_hash[$i])) $temp_hash[$i] = '';
                if(1000<$safecount) break;
                $temp_hash[$i] .= array_shift($hash);
                $count = count($hash);
                if($i===0) {
                    $i++;
                    continue;
                }
                if(strpos($temp_hash[$i],$right)===false) $open++;
                else {
                    $right_count = substr_count($temp_hash[$i],$right);
                    $close += $right_count;
                }
            }
            $i++;
        }
        $matches=array();
        $i = 0;
        foreach($temp_hash as $v) {
            if(strpos($v,$left)!==false) {
                $v = substr($v,0,strrpos($v,$right));
                $matches[0][$i] = $v . $right;
                $matches[1][$i] = substr($v,strlen($left));
                $i++;
            }
        }
        return $matches;
    }
    
	// mod by Raymond
	function mergeDocumentContent($content)
	{
		if(!isset($this->documentIdentifier)) return $content;
		if(strpos($content,'[*')===false) return $content;
		if(!isset($this->documentObject) || empty($this->documentObject)) return $content;
		
		$replace= array ();
		$matches = $this->getTagsFromContent($content,'[*','*]');
		if(empty($matches)) return $content;
		$core_path= $this->config['base_path'] . 'manager/includes/';
		include_once("{$core_path}tmplvars.format.inc.php");
		include_once("{$core_path}tmplvars.commands.inc.php");
		$i= 0;
		foreach($matches['1'] as $key)
		{
			$key= substr($key, 0, 1) == '#' ? substr($key, 1) : $key; // remove # for QuickEdit format
			if(strpos($key,':')!==false && $this->config['output_filter']==='1')
			{
				list($key,$modifiers) = explode(':', $key, 2);
			}
			else $modifiers = false;
			if(isset($this->documentObject[$key]))
			{
				$value= $this->documentObject[$key];
				if (is_array($value))
				{
					$value= getTVDisplayFormat($value['0'], $value['1'], $value['2'], $value['3'], $value['4']);
				}
				if($modifiers!==false)
				{
					$this->loadExtension('PHx') or die('Could not load PHx class.');
					$value = $this->phx->phxFilter($key,$value,$modifiers);
				}
			}
			else
			{
				if(strpos($key,'[*')!==false) $key = $this->mergeDocumentContent($key);
				$value = '';
			}
			
			$replace[$i]= $value;
			$i++;
		}
		$content= str_replace($matches['0'], $replace, $content);
		return $content;
	}
		
	function mergeSettingsContent($content)
	{
		if(strpos($content,'[(')===false) return $content;
		
		$replace= array ();
		$matches = $this->getTagsFromContent($content,'[(',')]');
		if($matches)
		{
			$i= 0;
			foreach($matches['1'] as $key)
			{
				if(strpos($key,':')!==false && $this->config['output_filter']==='1') {
					list($key,$modifiers) = explode(':', $key, 2);
				}
				else $modifiers = false;
				
				if(isset($this->config[$key])) {
					$value = $this->config[$key];
					if($modifiers!==false) {
						$this->loadExtension('PHx') or die('Could not load PHx class.');
						$value = $this->phx->phxFilter($key,$value,$modifiers);
					}
					$replace[$i]= $value;
				}
				else $replace[$i]= $key;
				$i++;
			}
			$content= str_replace($matches['0'], $replace, $content);
		}
		return $content;
	}
	
	function mergeChunkContent($content)
	{
		if(strpos($content,'{{')===false) return $content;
		
		$replace= array ();
		$matches = $this->getTagsFromContent($content,'{{','}}');
		if ($matches) {
			$i= 0;
			foreach($matches['1'] as $key) {
				if(strpos($key,':')!==false && $this->config['output_filter']==='1') {
					list($key,$modifiers) = explode(':', $key, 2);
				}
				else $modifiers = false;
				
				if(strpos($key,'?')!==false && strpos($key,'=')!==false) {
					list($key,$properties) = explode('?', $key, 2);
					$ph = $this->getProperties($properties);
				}
				
				if ($this->getChunk($key)!==false) {
					$value= $this->getChunk($key);
				}
				else {
					if(!isset($this->chunkCache)) $this->setChunkCache();
					$escaped_name = $this->db->escape($key);
					$where = "`name`='{$escaped_name}' AND `published`='1'";
					$result= $this->db->select('snippet','[+prefix+]site_htmlsnippets',$where);
					$total= $this->db->getRecordCount($result);
					if ($total < 1) {
						$where = "`name`='{$escaped_name}' AND `published`='0'";
						$result= $this->db->select('snippet','[+prefix+]site_htmlsnippets',$where);
						$total= $this->db->getRecordCount($result);
						if(0 < $total) {
							$this->chunkCache[$key]= '';
							$value= '';
						} else {
							$this->chunkCache[$key]= $key;
							$value= $key;
						}
					} else {
						$row= $this->db->getRow($result);
						$this->chunkCache[$key]= $row['snippet'];
						$value= $row['snippet'];
					}
				}
				if($modifiers!==false) {
					$this->loadExtension('PHx') or die('Could not load PHx class.');
					$value = $this->phx->phxFilter($key,$value,$modifiers);
				}
				if(isset($ph) && 0<count($ph))
					$value = $this->parsePlaceholder($value, $ph);
				$replace[$i] = $value;
				$i++;
			}
			$content= str_replace($matches['0'], $replace, $content);
			$content=$this->mergeSettingsContent($content);
		}
		return $content;
	}
	
	function getProperties($properties='')
	{
	    if(strpos($properties,'=')===false) return $properties;
	    
	    $properties = ltrim($properties,'?');
		$_ = explode('&',$properties);
		$ph = array();
		foreach($_ as $pair)
		{
			if(strpos($pair,'=')!==false) list($k,$v) = explode('=',$pair,2);
			else {
				$k = $pair;
				$v = '';
			}
			if(substr($k,0,4) === 'amp;') $k = substr($k,4);
			$ph[$k] = $this->removeDelim($v);
		}
		return $ph;
	}
	
	function removeDelim($str)
	{
		$v = trim($str);
		$left  = substr($v,0,1);
		$right = substr($v,-1);
		if( ($right===$left) && ($left==='"' || $left==="'" || $left==='`') )
		{
			 $rs = substr($v,1,-1);
		}
		else $rs = $str;
		
		return $rs;
	}

	function mergePh($content)
	{
		return mergePlaceholderContent($content);
	}
	
	// Added by Raymond
	function mergePlaceholderContent($content)
	{
		if(strpos($content,'[+')===false) return $content;
		
		$replace= array ();
		$content=$this->mergeSettingsContent($content);
		$matches = $this->getTagsFromContent($content,'[+','+]');
		if($matches)
		{
			$i= 0;
			foreach($matches['1'] as $key)
			{
				if(strpos($key,':')!==false && $this->config['output_filter']==='1')
				{
					list($name,$modifiers) = explode(':', $key, 2);
				}
				else
				{
					$name = $key;
					$modifiers = false;
				}
				
				$value= '';
				if (is_array($this->placeholders) && isset($this->placeholders[$name]))
				{
					$value= $this->placeholders[$name];
					if ($value !== '')
					{
						if($modifiers!==false)
						{
							$this->loadExtension('PHx') or die('Could not load PHx class.');
							$value = $this->phx->phxFilter($name,$value,$modifiers);
						}
					}
					$replace[$i]= $value;
				}
				else $replace[$i]= '[+' . $key . '+]';
				
				$i++;
			}
			$content= str_replace($matches['0'], $replace, $content);
		}
		return $content;
	}
	
	function mergeCommentedTagsContent($content, $left='<!--@MODX:', $right='-->')
	{
		if(strpos($content,$left)===false) return $content;
		$matches = $this->getTagsFromContent($content,$left,$right);
		if(!empty($matches))
		{
			foreach($matches['1'] as $i=>$v)
			{
				$matches['1'][$i] = $this->parseDocumentSource($v);
			}
			$content = str_replace($matches['0'],$matches['1'],$content);
		}
		return $content;
	}
	
	function ignoreCommentedTagsContent($content, $left='<!--@IGNORE:BEGIN-->', $right='<!--@IGNORE:END-->')
	{
		if(strpos($content,$left)===false) return $content;
		$matches = $this->getTagsFromContent($content,$left,$right);
		if(!empty($matches))
		{
			$content = str_replace($matches['0'],'',$content);
		}
		return $content;
	}
	
	function mergeBenchmarkContent($content)
	{
		if(strpos($content,'[^')===false) return $content;
		
		$totalTime= ($this->getMicroTime() - $this->tstart);
		$queryTime= $this->queryTime;
		$phpTime= $totalTime - $queryTime;
		
		$queryTime= sprintf("%2.4f s", $queryTime);
		$totalTime= sprintf("%2.4f s", $totalTime);
		$phpTime= sprintf("%2.4f s", $phpTime);
		$source= ($this->documentGenerated == 1 || $this->config['cache_type'] ==0) ? 'database' : 'full_cache';
		$queries= isset ($this->executedQueries) ? $this->executedQueries : 0;
		$mem = (function_exists('memory_get_peak_usage')) ? memory_get_peak_usage()  : memory_get_usage() ;
		$total_mem = $this->nicesize($mem - $this->mstart);
		$incs = get_included_files();
		
		$content= str_replace('[^q^]', $queries, $content);
		$content= str_replace('[^qt^]', $queryTime, $content);
		$content= str_replace('[^p^]', $phpTime, $content);
		$content= str_replace('[^t^]', $totalTime, $content);
		$content= str_replace('[^s^]', $source, $content);
		$content= str_replace('[^m^]', $total_mem, $content);
		$content= str_replace('[^f^]', count($incs), $content);
		
		return $content;
	}
	
    // evalPlugin
	function evalPlugin($pluginCode, $params)
	{
		$etomite= $modx= & $this;
		$modx->event->params = $params; // store params inside event object
		if (is_array($params))
		{
			extract($params, EXTR_SKIP);
		}
		ob_start();
		$result = eval($pluginCode);
		$msg= ob_get_contents();
		ob_end_clean();
		if ($msg && isset ($php_errormsg))
		{
			$error_info = error_get_last();
			if($error_info['type']===2048 || $error_info['type']===8192) $error_type = 2;
			else                                                         $error_type = 3;
			if(1<$this->config['error_reporting'] || 2<$error_type)
			{
				extract($error_info);
				if($msg===false) $msg = 'ob_get_contents() error';
				$result = $this->messageQuit('PHP Parse Error', '', true, $type, $file, 'Plugin', $text, $line, $msg);
				if ($this->isBackend())
				{
					$this->event->alert("An error occurred while loading. Please see the event log for more information.<p>{$msg}</p>");
				}
			}
		}
		else
		{
			echo $msg . $result;
		}
		unset ($modx->event->params);
	}
	
	function evalSnippet($phpcode, $params)
	{
		$etomite= $modx= & $this;
		if(isset($params) && is_array($params))
		{
			while(list($k,$v) = each($params))
			{
				if($v==='false')    $params[$k] = false;
				elseif($v==='true') $params[$k] = true;
			}
		}
		$modx->event->params = $params; // store params inside event object
		if (is_array($params))
		{
			extract($params, EXTR_SKIP);
		}
		ob_start();
		$result= eval($phpcode);
		$msg= ob_get_contents();
		ob_end_clean();
		
		if ((0<$this->config['error_reporting']) && $msg && isset($php_errormsg))
		{
			$error_info = error_get_last();
			if($error_info['type']===2048 || $error_info['type']===8192) $error_type = 2;
			else                                                         $error_type = 3;
			if(1<$this->config['error_reporting'] || 2<$error_type)
			{
				extract($error_info);
				$result = $this->messageQuit('PHP Parse Error', '', true, $type, $file, 'Snippet', $text, $line, $msg);
				if ($this->isBackend())
				{
					$this->event->alert("An error occurred while loading. Please see the event log for more information<p>{$msg}</p>");
				}
			}
		}
		unset ($modx->event->params);
		$this->currentSnippet = '';
		return $msg . $result;
	}
	
	function evalSnippets($documentSource)
	{
		if(strpos($documentSource,'[[')===false) return $documentSource;
		
		$etomite= & $this;
		
		$stack = $documentSource;
		unset($documentSource);
		
		$passes = $this->minParserPasses;
		
		if(!$this->snippetCache) $this->setSnippetCache();
		
		for($i= 0; $i < $passes; $i++)
		{
			$stack=$this->mergeSettingsContent($stack);
			if($i == ($passes -1)) $bt = md5($stack);
			$pieces = array();
			$pieces = explode('[[', $stack);
			$stack = '';
			$loop_count = 0;
			
			foreach($pieces as $piece)
			{
				if($loop_count < 1)                 $result = $piece;
				elseif(strpos($piece,']]')===false) $result = '[[' . $piece;
				else                                $result = $this->_get_snip_result($piece);
				
				$stack .= $result;
				$loop_count++;
			}
			if($i == ($passes -1) && $i < ($this->maxParserPasses - 1))
			{
				if($bt != md5($stack)) $passes++;
			}
		}
		return $stack;
	}
	
	private function _get_snip_result($piece)
	{
		$snip_call        = $this->_split_snip_call($piece);
		$snip_name        = $snip_call['name'];
		$except_snip_call = $snip_call['except_snip_call'];
		
		$key = $snip_call['name'];
		if(strpos($key,':')!==false && $this->config['output_filter']==='1')
		{
			list($key,$modifiers) = explode(':', $key, 2);
			$snip_call['name'] = $key;
		}
		else $modifiers = false;
		
		$snippetObject = $this->_get_snip_properties($snip_call);
		
		$params   = array ();
		$this->currentSnippet = $snippetObject['name'];
		
		if(isset($snippetObject['properties'])) $params = $this->parseProperties($snippetObject['properties']);
		else                                    $params = '';
		// current params
		if(!empty($snip_call['params']))
		{
			$snip_call['params'] = ltrim($snip_call['params'], '?');
			
			$i = 0;
			$limit = 50;
			$params_stack = $snip_call['params'];
			while(!empty($params_stack) && $i < $limit)
			{
				if(strpos($params_stack,'=')!==false) list($pname,$params_stack) = explode('=',$params_stack,2);
				else {
					$pname=$params_stack;
					$params_stack = '';
				}
				$params_stack = trim($params_stack);
				$delim = substr($params_stack, 0, 1);
				$temp_params = array();
				switch($delim)
				{
					case '`':
					case '"':
					case "'":
						$params_stack = substr($params_stack,1);
						list($pvalue,$params_stack) = explode($delim,$params_stack,2);
						$params_stack = trim($params_stack);
						if(substr($params_stack, 0, 2)==='//')
						{
							$params_stack = strstr($params_stack, "\n");
						}
						break;
					default:
						if(strpos($params_stack, '&')!==false)
						{
							list($pvalue,$params_stack) = explode('&',$params_stack,2);
						}
						else $pvalue = $params_stack;
						$pvalue = trim($pvalue);
				}
				if($delim !== "'")
				{
					$pvalue = (strpos($pvalue,'[*')!==false) ? $this->mergeDocumentContent($pvalue) : $pvalue;
				}
				
				$pname  = str_replace('&amp;', '', $pname);
				$pname  = trim($pname);
				$pname  = trim($pname,'&');
				$params[$pname] = $pvalue;
				$params_stack = trim($params_stack);
				if($params_stack!=='') $params_stack = '&' . ltrim($params_stack,'&');
				$i++;
			}
			unset($temp_params);
		}
		$value = $this->evalSnippet($snippetObject['content'], $params);
		if($modifiers!==false)
		{
			$this->loadExtension('PHx') or die('Could not load PHx class.');
			$value = $this->phx->phxFilter($key,$value,$modifiers);
		}
		
		if($this->dumpSnippets == 1)
		{
			$this->snipCode .= '<fieldset><legend><b>' . $snippetObject['name'] . '</b></legend><textarea style="width:60%;height:200px">' . htmlentities($value,ENT_NOQUOTES,$this->config['modx_charset']) . '</textarea></fieldset>';
		}
		return $value . $except_snip_call;
	}
	
	private function _split_snip_call($src)
	{
		list($call,$snip['except_snip_call']) = explode(']]', $src, 2);
		if(strpos($call, '?') !== false && strpos($call, "\n")!==false && strpos($call, '?') < strpos($call, "\n"))
		{
			list($name,$params) = explode('?',$call,2);
		}
		elseif(strpos($call, '?') !== false && strpos($call, "\n")!==false && strpos($call, "\n") < strpos($call, '?'))
		{
			list($name,$params) = explode("\n",$call,2);
		}
		elseif(strpos($call, '?') !== false)
		{
			list($name,$params) = explode('?',$call,2);
		}
		elseif((strpos($call, '&') !== false) && (strpos($call, '=') !== false) && (strpos($call, '?') === false))
		{
			list($name,$params) = explode('&',$call,2);
			$params = "&{$params}";
		}
		elseif(strpos($call, "\n") !== false)
		{
			list($name,$params) = explode("\n",$call,2);
		}
		else
		{
			$name   = $call;
			$params = '';
		}
		$snip['name']   = trim($name);
		$snip['params'] = $params;
		return $snip;
	}
	
	private function _get_snip_properties($snip_call)
	{
		$snip_name  = $snip_call['name'];
		
		if(isset($this->snippetCache[$snip_name]))
		{
			$snippetObject['name']    = $snip_name;
			$snippetObject['content'] = $this->snippetCache[$snip_name];
			if(isset($this->snippetCache[$snip_name . 'Props']))
			{
				$snippetObject['properties'] = $this->snippetCache[$snip_name . 'Props'];
			}
		}
		else
		{
			$esc_snip_name = $this->db->escape($snip_name);
			// get from db and store a copy inside cache
			$result= $this->db->select('name,snippet,properties','[+prefix+]site_snippets',"name='{$esc_snip_name}'");
			$added = false;
			if($this->db->getRecordCount($result) == 1)
			{
				$row = $this->db->getRow($result);
				if($row['name'] == $snip_name)
				{
					$snippetObject['name']       = $row['name'];
					$snippetObject['content']    = $this->snippetCache[$snip_name]           = $row['snippet'];
					$snippetObject['properties'] = $this->snippetCache[$snip_name . 'Props'] = $row['properties'];
					$added = true;
				}
			}
			if($added === false)
			{
				$snippetObject['name']       = $snip_name;
				$snippetObject['content']    = $this->snippetCache[$snip_name] = 'return false;';
				$snippetObject['properties'] = '';
			}
		}
		return $snippetObject;
	}
	
	function setChunkCache()
	{
		$chunk = @include_once(MODX_BASE_PATH . 'assets/cache/chunk.siteCache.idx.php');
		if($chunk) $this->chunkCache = $chunk;
		else $this->chunkCache = array();
	}
	
	function setSnippetCache()
	{
		$snippets = @include_once(MODX_BASE_PATH . 'assets/cache/snippet.siteCache.idx.php');
		if($snippets) $this->snippetCache = $snippets;
		else return false;
	}
	
	function setPluginCache()
	{
		$plugins = @include_once(MODX_BASE_PATH . 'assets/cache/plugin.siteCache.idx.php');
		if($plugins) $this->pluginCache = $plugins;
		else return false;
	}
	
    function setdocumentMap()
    {
        if(isset($this->documentMap)) return;
        
        $documentmap_cache_path = MODX_BASE_PATH . 'assets/cache/documentMap.siteCache.idx.php';
        if(is_file($documentmap_cache_path)) {
            $d = @include_once($documentmap_cache_path);
            if($d) $this->documentMap = $d;
            else return false;
        } else {
            $fields = "id, parent";
            $rs = $this->db->select($fields,'[+prefix+]site_content','deleted=0','parent');
            $row = array();
            while ($row = $this->db->getRow($rs)) {
                $this->documentMap[] = array($row['parent'] => $row['id']);
            }
            $str = "<?php\n" . 'return ' . var_export($this->documentMap, true) . ';';
            $rs = file_put_contents($documentmap_cache_path, $str, LOCK_EX);
            if(!$rs) {
                exit("Cannot write main MODX cache file! Make sure the '{$this->cachePath}' directory is writable!");
            }
        }
    }
	
	function setAliasListing()
	{
		$aliases = @include_once(MODX_BASE_PATH . 'assets/cache/aliasListing.siteCache.idx.php');
		if($aliases) $this->aliasListing = $aliases;
		else return false;
	}
	
	function set_aliases()
	{
		$path_aliases = MODX_BASE_PATH . 'assets/cache/aliases.pageCache.php';
		if(is_file($path_aliases))
		{
			$aliases = @include_once($path_aliases);
			$this->aliases = $aliases;
		}
		else
		{
			if(!$this->aliasListing) $this->setAliasListing();
			
			$aliases= array ();
			foreach ($this->aliasListing as $doc)
			{
				$aliases[$doc['id']]= (strlen($doc['path']) > 0 ? $doc['path'] . '/' : '') . $doc['alias'];
			}
			$cache = "<?php\n" . 'return ' . var_export($aliases, true) . ';';
			file_put_contents($path_aliases, $cache, LOCK_EX);
			$this->aliases = $aliases;
		}
		return $this->aliases;
	}

	/**
	* name: getDocumentObject  - used by parser
	* desc: returns a document object - $method: alias, id
	*/
	function getDocumentObject($method='id', $identifier='')
	{
		if(isset($_SESSION['mgrValidated']) && !empty($_POST) && isset($_GET['mode']) && $_GET['mode']==='prev')
		{
			$this->loadExtension('ManagerAPI');
			
            $input = $_POST;
             $docid = (isset($_GET['id']) && preg_match('@^[0-9]+$@',$_GET['id'])) ? $_GET['id'] : '0';
             $input['id'] = $docid;
            $this->documentIdentifier = $docid;
            $rs = $this->db->select('id,name','[+prefix+]site_tmplvars');
            while($row = $this->db->getRow($rs))
            {
            	$tvid = 'tv' . $row['id'];
            	$tvname[$tvid] = $row['name'];
            }
            
            foreach($input as $k=>$v)
            {
            	if(isset($tvname[$k]))
            	{
            		unset($input[$k]);
            		$k = $tvname[$k];
            		$input[$k] = $v;
            	}
            	elseif($k==='ta')
            	{
            		$input['content'] = $v;
            		unset($input['ta']);
            	}
            }
            $this->directParse = 1;
			return $input;
		}
			
		if(empty($identifier) && $method !== 'id' && $method !== 'alias')
		{
			$identifier = $method;
			if(empty($identifier)) $identifier = $this->documentIdentifier;
			if(preg_match('/^[0-9]+$/', $method)) $method = 'id';
			else                                  $method = 'alias';
		}
		
		// allow alias to be full path
		if($method === 'alias' && $this->config['use_alias_path']==='1')
		{
			$identifier = $this->getIdFromAlias($identifier);
			if($identifier!==false)
				$method = 'id';
			else return false;
		}
		
		// get document groups for current user
		if ($docgrp= $this->getUserDocGroups())
		{
			$docgrp= implode(',', $docgrp);
		}
		// get document (add so)
		if($this->isFrontend()) $access= "sc.privateweb=0";
		else                    $access= "sc.privatemgr=0";
		if($docgrp) $access .= " OR dg.document_group IN ({$docgrp})";
		$access .= " OR 1='{$_SESSION['mgrRole']}'";
		
		$from = "[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id";
		$where ="sc.{$method}='{$identifier}' AND ($access)";
		$result= $this->db->select('sc.*',$from,$where,'',1);
		if ($this->db->getRecordCount($result) < 1)
		{
			if ($this->config['unauthorized_page'])
			{
				// method may still be alias, while identifier is not full path alias, e.g. id not found above
				if ($method === 'alias')
				{
					$field = 'dg.id';
					$from = "[+prefix+]document_groups dg, [+prefix+]site_content sc";
					$where =  "dg.document = sc.id AND sc.alias = '{$identifier}'";
				}
				else
				{
					$field = 'id';
					$from = '[+prefix+]document_groups';
					$where =  "document = '{$identifier}'";
				}
				// check if file is not public
				$seclimit= $this->db->getRecordCount($this->db->select($field,$from,$where,'',1));
			}
			if ($seclimit > 0)
			{
				// match found but not publicly accessible, send the visitor to the unauthorized_page
				if ($this->isBackend()) return false;
				else $this->sendUnauthorizedPage();
			}
			else
			{
				if ($this->isBackend()) return false;
				else $this->sendErrorPage();
			}
		}
		
		# this is now the document :) #
		$documentObject= $this->db->getRow($result);
		$docid = $documentObject['id'];
		
		// load TVs and merge with document - Orig by Apodigm - Docvars
		
		$field = "tv.name, IF(tvc.value!='',tvc.value,tv.default_text) as value,tv.display,tv.display_params,tv.type";
		$from  = "[+prefix+]site_tmplvars tv ";
		$from .= "INNER JOIN [+prefix+]site_tmplvar_templates tvtpl ON tvtpl.tmplvarid = tv.id ";
		$from .= "LEFT JOIN [+prefix+]site_tmplvar_contentvalues tvc ON tvc.tmplvarid=tv.id AND tvc.contentid = '{$docid}'";
		$where = "tvtpl.templateid = '{$documentObject['template']}'";
		$rs = $this->db->select($field,$from,$where);
		$rowCount= $this->db->getRecordCount($rs);
		if ($rowCount > 0)
		{
			while ($row= $this->db->getRow($rs))
			{
				$tmplvars[$row['name']]= array
				(
					$row['name'],
					$row['value'],
					$row['display'],
					$row['display_params'],
					$row['type']
				);
			}
			$documentObject= array_merge($documentObject, $tmplvars);
		}
		if(!isset($documentObject['url'] ))
		{
			if($docid===$this->config['site_start']) $documentObject['url'] = $this->config['site_url'];
			else                                     $documentObject['url'] = $this->makeUrl($docid,'','','full');
		}
		if(!isset($documentObject['path'] ))
		{
			if($docid===$this->config['site_start']) $documentObject['path'] = '';
			else                                     $documentObject['path'] = $this->makeUrl($docid,'','','root_rel');
		}
		return $documentObject;
	}
	
	function parseDoc($source)
	{
		return $this->parseDocumentSource($source);
	}
	
	/**
	* name: parseDocumentSource - used by parser
	* desc: return document source aftering parsing tvs, snippets, chunks, etc.
	*/
	function parseDocumentSource($source)
	{
		$passes= $this->minParserPasses;
		for ($i= 0; $i < $passes; $i++)
		{
			// get source length if this is the final pass
			if ($i == ($passes -1)) $bt= md5($source);
			if ($this->dumpSnippets == 1)
			{
				$this->snipCode .= "<fieldset><legend><b style='color: #821517;'>PARSE PASS " . ($i +1) . "</b></legend>The following snippets (if any) were parsed during this pass.<div style='width:100%' align='center'>";
			}
			
			// invoke OnParseDocument event
			$this->documentOutput= $source; // store source code so plugins can
			$this->invokeEvent('OnParseDocument', array('loopcount'=>$i)); // work on it via $modx->documentOutput
			$source = $this->mergeSettingsContent($this->documentOutput);
			
			if(strpos($source,'<!--@IGNORE:BEGIN-->')!==false) $source= $this->ignoreCommentedTagsContent($source);
			if(strpos($source,'<!--@MODX:')!==false) $source= $this->mergeCommentedTagsContent($source);
			// combine template and document variables
			if(strpos($source,'[*')!==false) $source= $this->mergeDocumentContent($source);
			// replace settings referenced in document
			if(strpos($source,'[(')!==false) $source= $this->mergeSettingsContent($source);
			// replace HTMLSnippets in document
			if(strpos($source,'{{')!==false) $source= $this->mergeChunkContent($source);
			// insert META tags & keywords
			if(isset($this->config['show_meta']) && $this->config['show_meta']==1)
			{
				$source= $this->mergeDocumentMETATags($source);
			}
			// find and merge snippets
			if(strpos($source,'[[')!==false) $source= $this->evalSnippets($source);
			// find and replace Placeholders (must be parsed last) - Added by Raymond
			if(strpos($source,'[+')!==false) {
				$source= $this->mergePlaceholderContent($source);
				$source = $this->mergeSettingsContent($source);
			}
			if ($this->dumpSnippets == 1)
			{
				$this->snipCode .= '</div></fieldset>';
			}
			if ($i == ($passes -1) && $i < ($this->maxParserPasses - 1))
			{
				// check if source length was changed
				if ($bt != md5($source))
				{
					$passes++; // if content change then increase passes because
				}
			} // we have not yet reached maxParserPasses
			if(strpos($source,'[~')!==false && strpos($source,'[~[+')===false) $source = $this->rewriteUrls($source);
		}
		return $source;
	}
	
	/***************************************************************************************/
	/* API functions																/
	/***************************************************************************************/

	function getParentIds($id='', $height= 10)
	{
		if($id==='') $id = $this->documentIdentifier;
		$parents= array ();
		
		if(!isset($this->aliasListing)) $this->setAliasListing();
		
		while( $id && 0<$height)
		{
			$current_id = $id;
			$id = $this->aliasListing[$id]['parent'];
			if(!$id) break;
			$parents[$current_id] = $id;
			$height--;
		}
		return $parents;
	}

	function set_childrenList()
	{
		$path_documentmapcache = MODX_BASE_PATH . 'assets/cache/documentmap.pageCache.php';
		if(is_file($path_documentmapcache))
		{
			$src = file_get_contents($path_documentmapcache);
			$this->childrenList = unserialize($src);
		}
		else
		{
			$childrenList= array ();
			
			if(!$this->documentMap) $this->setdocumentMap();
			
			foreach ($this->documentMap as $document)
			{
				while(list($p, $c) = each($document))
				{
					$childrenList[$p][] = $c;
				}
			}
			file_put_contents($path_documentmapcache,serialize($childrenList), LOCK_EX);
			$this->childrenList = $childrenList;
		}
		return $this->childrenList;
	}

	function getChildIds($id, $depth= 10, $children= array ())
	{
		// Initialise a static array to index parents->children
		if(!count($this->childrenList))
			$childrenList = $this->set_childrenList();
		else
			$childrenList = $this->childrenList;
		
		// Get all the children for this parent node
		if (isset($childrenList[$id]))
		{
			$depth--;
			
			if(!$this->aliasListing) $this->setAliasListing();
			
			foreach ($childrenList[$id] as $childId)
			{
				$pkey = $this->aliasListing[$childId]['alias'];
				if($this->aliasListing[$childId]['path']!=='')
				{
					$pkey = "{$this->aliasListing[$childId]['path']}/{$pkey}";
				}
				
				if ($pkey==='') $pkey = $childId;
				$children[$pkey] = $childId;
				
				if ($depth)
				{
					$children += $this->getChildIds($childId, $depth);
				}
			}
		}
		return $children;
	}

    # Returns true if user has the currect permission
    function hasPermission($pm) {
        $state= false;
        $pms= $_SESSION['mgrPermissions'];
        if ($pms)
            $state= ($pms[$pm] == 1);
        return $state;
    }

	function getMimeName($encode)
	{
		$rs = $encode;
		$encode = strtolower($encode);
		if(strpos($encode,'utf-8')!==false)     $rs = 'UTF-8';
		elseif(strpos($encode,'sjis')!==false)  $rs = 'Shift_JIS';
		elseif(strpos($encode,'eucjp')!==false) $rs = 'EUC-JP';
		elseif(strpos($encode,'cp950')!==false) $rs = 'BIG5';
		return $rs;
	}
	
    # Returns true if parser is executed in backend (manager) mode
    function isBackend() {
		if(defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true')
		{
			return true;
		}
		else return false;
    }

	# Returns true if parser is executed in frontend mode
	function isFrontend()
	{
		if(defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true')
		{
			return false;
		}
		else return true;
	}
	
	function getAllChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle',$where=false)
	{
		// modify field names to use sc. table reference
		$fields= $this->join(',', explode(',',$fields),'sc.');
		$sort  = $this->join(',', explode(',',$sort),'sc.');
		
		// build query
		$from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
		if($where===false)
		{
			// get document groups for current user
			if ($docgrp= $this->getUserDocGroups())
			{
				$docgrp= implode(',', $docgrp);
				$cond = "OR dg.document_group IN ({$docgrp}) OR 1='{$_SESSION['mgrRole']}'";
			}
			else $cond = '';
			$context = ($this->isFrontend() ? 'web' : 'mgr');
			$where = "sc.parent = '{$id}' AND (sc.private{$context}=0 {$cond}) GROUP BY sc.id";
		}
		$orderby = "{$sort} {$dir}";
		$result= $this->db->select("DISTINCT {$fields}",$from,$where,$orderby);
		$resourceArray= array ();
		for ($i= 0; $i < $this->db->getRecordCount($result); $i++)
		{
			$resourceArray[] = $this->db->getRow($result);
		}
		return $resourceArray;
	}
	
	function getActiveChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle')
	{
		// get document groups for current user
		if ($docgrp= $this->getUserDocGroups())
		{
			$docgrp= implode(',', $docgrp);
			$cond = " OR dg.document_group IN ({$docgrp})";
		}
		else $cond = '';
		if($this->isFrontend()) $context = 'sc.privateweb=0';
		else                    $context = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0";
		$where = "sc.parent = '{$id}' AND sc.published=1 AND sc.deleted=0 AND ({$context} {$cond}) GROUP BY sc.id";
		
		$resourceArray = $this->getAllChildren($id, $sort, $dir, $fields,$where);
		
		return $resourceArray;
	}
	
	function getDocumentChildren($parentid= 0, $published= 1, $deleted= 0, $fields= '*', $where= '', $sort= 'menuindex', $dir= 'ASC', $limit= '')
	{
		// modify field names to use sc. table reference
		$fields = $this->join(',', explode(',',$fields),'sc.');
		if($where != '') $where= "AND {$where}";
		// get document groups for current user
		if ($docgrp= $this->getUserDocGroups()) $docgrp= implode(',', $docgrp);
		// build query
		$access  = $this->isFrontend() ? 'sc.privateweb=0' : "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0";
		$access .= !$docgrp ? '' : " OR dg.document_group IN ({$docgrp})";
		$from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
		$where = "sc.parent = '{$parentid}' AND sc.published={$published} AND sc.deleted={$deleted} {$where} AND ({$access}) GROUP BY sc.id";
		$sort = ($sort != '') ? $this->join(',', explode(',',$sort),'sc.') : '';
		$orderby = $sort ? "{$sort} {$dir}" : '';
		$result= $this->db->select("DISTINCT {$fields}",$from,$where,$orderby,$limit);
		$resourceArray= array ();
		for ($i= 0; $i < $this->db->getRecordCount($result); $i++)
		{
			$resourceArray[] = $this->db->getRow($result);
		}
		return $resourceArray;
	}
	
	function getDocuments($ids= array(), $published= 1, $deleted= 0, $fields= '*', $where= '', $sort= 'menuindex', $dir= 'ASC', $limit= '')
	{
		if (count($ids) == 0 || empty($ids))
		{
			return false;
		}
		else
		{
			if(is_string($ids))
			{
				$ids = explode(',',$ids);
				while(list($i,$id) = each($ids))
				{
					$ids[$i] = trim($id);
				}
			}
			
			// modify field names to use sc. table reference
			$fields = $this->join(',', explode(',',$fields),'sc.');
			
			if($sort !== '')  $sort = $this->join(',', explode(',',$sort),'sc.');
			if ($where != '') $where= "AND {$where}";
			// get document groups for current user
			if ($docgrp= $this->getUserDocGroups()) $docgrp= implode(',', $docgrp);
			$context = ($this->isFrontend()) ? 'web' : 'mgr';
			$cond = $docgrp ? "OR dg.document_group IN ({$docgrp})" : '';
			
			$fields = "DISTINCT {$fields}";
			$from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
			$ids_str = implode(',',$ids);
			if(!is_null($published)) $published = (string)$published;
			if($published==='1' || $published==='0')
				$where_published = "AND sc.published='{$published}'";
			else
				$where_published = '';
			
			$where = "(sc.id IN ({$ids_str}) {$where_published} AND sc.deleted={$deleted} {$where}) AND (sc.private{$context}=0 {$cond} OR 1='{$_SESSION['mgrRole']}') GROUP BY sc.id";
			$orderby = ($sort) ? "{$sort} {$dir}" : '';
			$result= $this->db->select($fields,$from,$where,$orderby,$limit);
			$resourceArray= array ();
			for ($i= 0; $i < $this->db->getRecordCount($result); $i++)
			{
				$resourceArray[] = $this->db->getRow($result);
			}
			return $resourceArray;
		}
	}

	function getDocument($id= 0, $fields= '*', $published= 1, $deleted= 0)
	{
		if ($id == 0) return false;
		else
		{
			$tmpArr[]= $id;
			$docs= $this->getDocuments($tmpArr, $published, $deleted, $fields, '', '', '', 1);
			
			if ($docs != false) return $docs['0'];
			else                return false;
		}
	}

	function getField($field='content', $docid='')
	{
		if(empty($docid) && isset($this->documentIdentifier))
			$docid = $this->documentIdentifier;
		elseif(!preg_match('@^[0-9]+$@',$docid))
			$docid = $this->getIdFromAlias($identifier);
		
		if(empty($docid)) return false;
		
		$doc = $this->getDocumentObject('id', $docid);
		if(is_array($doc[$field]))
		{
			$tvs= $this->getTemplateVarOutput($field, $docid);
			return $tvs[$field];
		}
		return $doc[$field];
	}
	
	function getPageInfo($docid= 0, $activeOnly= 1, $fields= 'id, pagetitle, description, alias')
	{
		if($docid === 0 || !preg_match('/^[0-9]+$/',$docid)) return false;
		else
		{
			// modify field names to use sc. table reference
			$fields = preg_replace("/\s/i", '',$fields);
			$fields = $this->join(',',explode(',',$fields),'sc.');
			
			$published = ($activeOnly == 1) ? "AND sc.published=1 AND sc.deleted='0'" : '';
			
			// get document groups for current user
			if($docgrp= $this->getUserDocGroups())
			{
				$docgrp= implode(',', $docgrp);
			}
			if($this->isFrontend()) $context = "sc.privateweb='0'";
			else                    $context = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr='0'";
			$cond   =  ($docgrp) ? "OR dg.document_group IN ({$docgrp})" : '';
			
			$from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
			$where = "(sc.id='{$docid}' {$published}) AND ({$context} {$cond})";
			$result = $this->db->select($fields,$from,$where,'',1);
			$pageInfo = $this->db->getRow($result);
			return $pageInfo;
		}
	}

	function getParent($pid= -1, $activeOnly= 1, $fields= 'id, pagetitle, description, alias, parent')
	{
		if ($pid == -1)
		{
			$pid= $this->documentObject['parent'];
			return ($pid == 0) ? false : $this->getPageInfo($pid, $activeOnly, $fields);
		}
		elseif ($pid == 0)
		{
			return false;
		}
		else
		{
			// first get the child document
			$child= $this->getPageInfo($pid, $activeOnly, "parent");
			
			// now return the child's parent
			$pid= ($child['parent']) ? $child['parent'] : 0;
			
			return ($pid == 0) ? false : $this->getPageInfo($pid, $activeOnly, $fields);
		}
	}
	
	private function _getReferenceListing()
	{
		$referenceListing = array();
		$rs = $this->db->select('id,content', '[+prefix+]site_content', "type='reference'");
		$rows = $this->db->makeArray($rs);
		foreach($rows as $row)
		{
			extract($row);
			$content = trim($content);
			if((strpos($content,'[')!==false || strpos($content,'{')!==false) && strpos($content,'[~')===false)
			{
				$content = $this->parseDocumentSource($content);
			}
			elseif(strpos($content,'[~')===0)
			{
				$content = substr($content,2,-2);
				if(strpos($content,'[')!==false || strpos($content,'{')!==false)
				{
					$content = $this->parseDocumentSource($content);
				}
			}
			$referenceListing[$id] = $content;
		}
		
		$this->referenceListing = $referenceListing;
		
		return $referenceListing;
	}
	
	function makeUrl($id, $alias= '', $args= '', $scheme= '')
	{
		$url= '';
		$f_url_prefix = $this->config['friendly_url_prefix'];
		$f_url_suffix = $this->config['friendly_url_suffix'];
		if (!preg_match('@^[0-9]+$@',$id))
		{
			$this->messageQuit("'{$id}' is not numeric and may not be passed to makeUrl()");
		}
		
		if(!isset($this->referenceListing)) $this->_getReferenceListing();
		
		if(isset($this->referenceListing[$id]))
		{
			if(preg_match('/^[0-9]+$/',$this->referenceListing[$id]))
			{
				$id = $this->referenceListing[$id];
			}
			else
			{
				return $this->referenceListing[$id];
			}
		}
		
		if ($this->config['friendly_urls'] == 1)
		{
			$alPath = '';
			if(empty($alias))
			{
				$alias = $id;
				if ($this->config['friendly_alias_urls'] == 1)
				{
					if(!isset($this->aliasListing)) $this->setAliasListing();
					
					$al= $this->aliasListing[$id];
					$alPath = ($al && !empty($al['path'])) ? $al['path'] . '/' : '';
					if ($al && $al['alias']) $alias  = $al['alias'];
				}
			}
			
			if(strpos($alias, '.') !== false && $this->config['suffix_mode']==='1')
			{
				$f_url_suffix = '';
			}
			elseif($al['isfolder']==='1' && $this->config['make_folders']==='1' && $id != $this->config['site_start'])
			{
				$f_url_suffix = '/';
			}
			
			$url = $alPath . $f_url_prefix . $alias . $f_url_suffix;
		}
		else
		{
			$url= "index.php?id={$id}";
		}
		
		if($args!=='')
		{
			$args = ltrim($args,'?&');
			if(strpos($url,'?')===false) $url .= "?{$args}";
			else                         $url .= "&{$args}";
		}
		
		$host = ($scheme !== 'root_rel') ? $this->config['base_url'] : '';
		// check if scheme argument has been set
		if ($scheme !== '' && $scheme !== 'root_rel')
		{
			// for backward compatibility - check if the desired scheme is different than the current scheme
			if (is_numeric($scheme) && $scheme != $_SERVER['HTTPS'])
			{
				$scheme= ($_SERVER['HTTPS'] ? 'http' : 'https');
			}
		
			// to-do: check to make sure that $site_url incudes the url :port (e.g. :8080)
			$host= ($scheme == 'full') ? $this->config['site_url'] : $scheme . '://' . $_SERVER['HTTP_HOST'] . $host;
		}
		
		if ($this->config['xhtml_urls'])
		{
			$url = preg_replace("/&(?!amp;)/",'&amp;', $host . $url);
		}
		else
		{
			$url = $host . $url;
		}
		$rs = $this->invokeEvent('OnMakeUrl',
				array(
					"id"    => $id,
					"alias" => $alias,
					"args"  => $args,
					"scheme"=> $scheme,
					"url"   => $url
				)
			);
		if (!empty($rs))
		{
			$url = end($rs);
		}
		return $url;
	}
	
	function rewriteUrls($documentSource)
	{
		// rewrite the urls
		$pieces = preg_split('/(\[~|~\])/',$documentSource);
		$maxidx = sizeof($pieces);
		$documentSource = '';
		if(!isset($this->referenceListing))
		{
			$this->referenceListing = $this->_getReferenceListing();
		}
		
		if ($this->config['friendly_urls'] == 1)
		{
			if(!isset($this->aliases) || empty($this->aliases))
				$aliases = $this->set_aliases();
			else
				$aliases = $this->aliases;
			
			$use_alias = $this->config['friendly_alias_urls'];
			$prefix    = $this->config['friendly_url_prefix'];
			$suffix    = $this->config['friendly_url_suffix'];
			
			for ($idx = 0; $idx < $maxidx; $idx++)
			{
				$documentSource .= $pieces[$idx];
				$idx++;
				if ($idx < $maxidx)
				{
					$target = trim($pieces[$idx]);
					$target = $this->mergeDocumentContent($target);
					$target = $this->mergeSettingsContent($target);
					$target = $this->mergeChunkContent($target);
					$target = $this->evalSnippets($target);
					
					if(preg_match('/^[0-9]+$/',$target))
					{
						$id = $target;
						if(isset($this->referenceListing[$id]) && preg_match('/^[0-9]+$/',$this->referenceListing[$id] ))
						{
							$id = $this->referenceListing[$id];
						}
							$path = $this->makeUrl($id,'','','root_rel');
					}
					else
					{
						$path = $target;
					}
					$documentSource .= $path;
				}
			}
			unset($aliases);
		}
		else
		{
			for ($idx = 0; $idx < $maxidx; $idx++)
			{
				$documentSource .= $pieces[$idx];
				$idx++;
				if ($idx < $maxidx)
				{
					$target = trim($pieces[$idx]);
					if(isset($this->referenceListing[$target]) && preg_match("/^[0-9]+$/",$this->referenceListing[$target]))
						$target = $this->referenceListing[$target];
					
					if($target === $this->config['site_start'])
						$path = '';
					elseif(isset($this->referenceListing[$target]) && preg_match('@^https?://@', $this->referenceListing[$target]))
						$path = $this->referenceListing[$target];
					else
						$path = 'index.php?id=' . $target;
					$documentSource .= $path;
				}
			}
		}
		return $documentSource;
	}
    
	function getConfig($name= '', $default='')
	{
		if(empty($this->config[$name]))
		{
			if($default==='') return false;
			else              return $default;
		}
		else                            return $this->config[$name];
	}
		
	function getVersionData()
	{
		require_once($this->config["base_path"] . 'manager/includes/version.inc.php');
		$v= array ();
		$v['version']= $modx_version;
		$v['branch']= $modx_branch;
		$v['release_date']= $modx_release_date;
		$v['full_appname']= $modx_full_appname;
		return $v;
	}
		
	function getChunk($key)
	{
		if(!$this->chunkCache) $this->setChunkCache();
		
		if(isset($this->chunkCache[$key]))
		{
			return $this->chunkCache[$key];
		}
		else return false;
	}
	
	function parseChunk($chunkName, $chunkArr, $left= '[+', $right= '+]',$mode='chunk')
	{
		if (!is_array($chunkArr)) return false;
		
		if($mode==='chunk') $content = $this->getChunk($chunkName);
		else                $content = $chunkName;
		
		if (!is_array($chunkArr)) return false;
		
		if(strpos($content,':')===false)
		{
    		foreach($chunkArr as $key=>$value)
    		{
    			$content= str_replace("{$left}{$key}{$right}", $value, $content);
    		}
		}
		else
		{
    		$replace= array ();
    		$matches= array ();
    		$matches = $this->getTagsFromContent($content,$left,$right);
    		if($matches)
    		{
    			$i= 0;
    			foreach($matches['1'] as $key)
    			{
    				if(strpos($key,':')!==false && $this->config['output_filter']==='1')
    				{
    					list($key,$modifiers) = explode(':', $key, 2);
    				}
    				else $modifiers = false;
    				
    				if(isset($chunkArr[$key]))
    				{
    					
    					$value = $chunkArr[$key];
    					if($modifiers!==false)
    					{
    						$this->loadExtension('PHx') or die('Could not load PHx class.');
    						$value = $this->phx->phxFilter($key,$value,$modifiers);
    					}
    					
    					$replace[$i]= $value;
    				}
    				else $replace[$i]= $key;
    				$i++;
    			}
    			
    			$content= str_replace($matches['0'], $replace, $content);
    		}
		}
		return $content;
	}

	function parsePlaceholder($src='', $ph=array(), $left= '[+', $right= '+]',$mode='ph')
	{
		if(!$ph) return $src;
		elseif(is_string($ph) && strpos($ph,'='))
		{
			if(strpos($ph,',')) $pairs   = explode(',',$ph);
			else                $pairs[] = $ph;
			
			unset($ph);
			$ph = array();
			foreach($pairs as $pair)
			{
				list($k,$v) = explode('=',trim($pair));
				$ph[$k] = $v;
			}
		}
		return $this->parseChunk($src, $ph, $left, $right, $mode);
	}
	
	function toDateFormat($timestamp = 0, $mode = '')
	{
		$timestamp = trim($timestamp);
		$timestamp = intval($timestamp) + $this->config['server_offset_time'];
		
		switch($this->config['datetime_format'])
		{
			case 'YYYY/mm/dd':
				$dateFormat = '%Y/%m/%d';
				break;
			case 'dd-mm-YYYY':
				$dateFormat = '%d-%m-%Y';
				break;
			case 'mm/dd/YYYY':
				$dateFormat = '%m/%d/%Y';
				break;
		}
		
		if (empty($mode))
		{
			$strTime = $this->mb_strftime($dateFormat . " %H:%M:%S", $timestamp);
		}
		elseif ($mode == 'dateOnly')
		{
			$strTime = $this->mb_strftime($dateFormat, $timestamp);
		}
		elseif ($mode == 'formatOnly')
		{
			$strTime = $dateFormat;
		}
		return $strTime;
	}
	
	function toTimeStamp($str)
	{
		$str = trim($str);
		if (empty($str)) return '';
		if(preg_match('@^[0-9]+$@',$str)) return $str;
		
		switch($this->config['datetime_format'])
		{
			case 'YYYY/mm/dd':
				if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}[0-9 :]*$/', $str))
				{
					return '';
				}
				list ($Y, $m, $d, $H, $M, $S) = sscanf($str, '%4d/%2d/%2d %2d:%2d:%2d');
				break;
			case 'dd-mm-YYYY':
				if (!preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{4}[0-9 :]*$/', $str))
				{
					return '';
				}
				list ($d, $m, $Y, $H, $M, $S) = sscanf($str, '%2d-%2d-%4d %2d:%2d:%2d');
				break;
			case 'mm/dd/YYYY':
				if (!preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4}[0-9 :]*$/', $str))
				{
					return '';
				}
				list ($m, $d, $Y, $H, $M, $S) = sscanf($str, '%2d/%2d/%4d %2d:%2d:%2d');
				break;
		}
		if (!$H && !$M && !$S)
		{
			$H = 0;
			$M = 0;
			$S = 0;
		}
		$timeStamp = mktime($H, $M, $S, $m, $d, $Y);
		$timeStamp = intval($timeStamp);
		return $timeStamp;
	}
	
	function mb_strftime($format='%Y/%m/%d', $timestamp='')
	{
		$a = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
		$A = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
		$w         = strftime('%w', $timestamp);
		$p = array('am'=>'AM', 'pm'=>'PM');
		$P = array('am'=>'am', 'pm'=>'pm');
		$ampm = (strftime('%H', $timestamp) < 12) ? 'am' : 'pm';
		if($timestamp==='') return '';
		if(substr(PHP_OS,0,3) == 'WIN') $format = str_replace('%-', '%#', $format);
		$pieces    = preg_split('@(%[\-#]?[a-zA-Z%])@',$format,null,PREG_SPLIT_DELIM_CAPTURE);
		
		$str = '';
		foreach($pieces as $v)
		{
		if    ($v == '%a')             $str .= $a[$w];
		elseif($v == '%A')             $str .= $A[$w];
		elseif($v == '%p')             $str .= $p[$ampm];
		elseif($v == '%P')             $str .= $P[$ampm];
		elseif(strpos($v,'%')!==false) $str .= strftime($v, $timestamp);
		else                           $str .= $v;
		}
		return $str;
	}
	
	#::::::::::::::::::::::::::::::::::::::::
	# Added By: Raymond Irving - MODx
	#
	
	function getDocumentChildrenTVars($parentid= 0, $tvidnames= array (), $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC', $tvfields= '*', $tvsort= 'rank', $tvsortdir= 'ASC')
	{
		$docs= $this->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
		if (!$docs) return false;
		else
		{
			foreach($docs as $doc)
			{
				$result[] = $this->getTemplateVars($tvidnames, $tvfields, $doc['id'],$published);
			}
			return $result;
		}
	}
		
	function getDocumentChildrenTVarOutput($parentid= 0, $tvidnames= array (), $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC')
	{
		$docs= $this->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
		if (!$docs) return false;
		else
		{
			$result= array ();
			foreach($docs as $doc)
			{
				$tvs= $this->getTemplateVarOutput($tvidnames, $doc['id'], $published, '', '');
				if ($tvs) $result[$doc['id']]= $tvs; // Use docid as key - netnoise 2006/08/14
			}
			return $result;
		}
	}
	
	// Modified by Raymond for TV - Orig Modified by Apodigm - DocVars
	# returns a single TV record. $idnames - can be an id or name that belongs the template that the current document is using
	function getTemplateVar($idname= '', $fields= '*', $docid= '', $published= 1)
	{
		if ($idname == '') return false;
		else
		{
			$result= $this->getTemplateVars(array($idname), $fields, $docid, $published, '', ''); //remove sorting for speed
			return ($result != false) ? $result['0'] : false;
		}
	}

	# returns an array of TV records. $idnames - can be an id or name that belongs the template that the current document is using
	function getTemplateVars($idnames=array(),$fields='*',$docid= '',$published= 1,$sort='rank',$dir='ASC')
	{
		if (is_array($idnames) && count($idnames) == 0) return false;
		if($idnames!=='*' && !is_array($idnames)) $idnames = array($idnames);
		
		// get document record
		if (empty($docid)) {
			if(isset($this->documentIdentifier) && !empty($this->documentIdentifier)) {
				$docid = $this->documentIdentifier;
				$resource= $this->documentObject;
			}
			else return false;
		}
		else
		{
			$resource= $this->getDocument($docid, '*', $published);
			if (!$resource) return false;
		}
		// get user defined template variables
		$fields= ($fields == '') ? 'tv.*' : $this->join(',',explode(',',$fields),'tv.');
		$sort= ($sort == '')     ? ''     : $this->join(',',explode(',',$sort),'tv.');
		
		if ($idnames == '*') $where= 'tv.id<>0';
		elseif (preg_match('@^[0-9]+$@',$idnames['0']))
		{
			$where= "tv.id='{$idnames['0']}'";
		}
		else
		{
			$i = 0;
			foreach($idnames as $idname)
			{
				$idnames[$i] = $this->db->escape(trim($idname));
				$i++;
			}
			$tvnames = "'" . join("','", $idnames) . "'";
			$where = (is_numeric($idnames['0'])) ? 'tv.id' : "tv.name IN ({$tvnames})";
		}
		$fields  = "{$fields}, IF(tvc.value!='',tvc.value,tv.default_text) as value";
		$from    = '[+prefix+]site_tmplvars tv';
		$from   .= ' INNER JOIN [+prefix+]site_tmplvar_templates tvtpl  ON tvtpl.tmplvarid = tv.id';
		$from   .= " LEFT JOIN [+prefix+]site_tmplvar_contentvalues tvc ON tvc.tmplvarid=tv.id AND tvc.contentid='{$docid}'";
		$where  = "{$where} AND tvtpl.templateid={$resource['template']}";
		if ($sort)
		{
			 $orderby = "{$sort} {$dir}";
		}
		else $orderby = '';
		$rs= $this->db->select($fields,$from,$where,$orderby);
		$result= array ();
		while($row = $this->db->getRow($rs))
		{
			if(!empty($row['display']))
			{
                include_once $this->config['base_path'] . 'manager/includes/tmplvars.format.inc.php';
                include_once $this->config['base_path'] . 'manager/includes/tmplvars.commands.inc.php';
                $row['output'] = getTVDisplayFormat($row['name'], $row['value'], $row['display'], $row['display_params'], $row['type'], $docid);
			}
			else $row['output'] = $row['value'];
			$result[] = $row;
		}
		
		// get default/built-in template variables
		ksort($resource);
		while(list($key, $value) = each($resource))
		{
			if ($idnames == '*' || in_array($key, $idnames))
			{
				$result[] = array ('name'=>$key,'value'=>$value);
			}
		}
		return $result;
	}

	# returns an associative array containing TV rendered output values. $idnames - can be an id or name that belongs the template that the current document is using
	function getTemplateVarOutput($idnames= array (), $docid= '', $published= 1, $sep='')
	{
		if (is_array($idnames) && count($idnames) == 0)
		{
			return false;
		}
		else
		{
			$output= array ();
			if(is_string($idnames)&&strpos($idnames,',')!==false) $idnames = explode(',', $idnames);
			$vars   = ($idnames == '*' || is_array($idnames)) ? $idnames : array ($idnames);
			$docid  = intval($docid) ? intval($docid) : $this->documentIdentifier;
			$result = $this->getTemplateVars($vars, '*', $docid, $published, '', ''); // remove sort for speed
			
			if ($result == false) return false;
			else
			{
				$core_path = $this->config['core_path'];
				include_once "{$core_path}tmplvars.format.inc.php";
				include_once "{$core_path}tmplvars.commands.inc.php";
				foreach($result as $row)
				{
					if (!$row['id'])
					{
						$output[$row['name']] = $row['value'];
					}
					else
					{
						$output[$row['name']] = getTVDisplayFormat($row['name'], $row['value'], $row['display'], $row['display_params'], $row['type'], $docid, $sep);
					}
				}
				return $output;
			}
		}
	}

# returns the full table name based on db settings
	function getFullTableName($tbl)
	{
		$dbase = trim($this->db->config['dbase'],'`');
		return "`{$dbase}`.`{$this->db->config['table_prefix']}{$tbl}`";
	}

    # return placeholder value
    function getPlaceholder($name) {
        return $this->placeholders[$name];
    }

    # sets a value for a placeholder
    function setPh($name, $value) {
        $this->placeholders[$name]= $value;
    }
    
    function setPlaceholder($name, $value) {
        $this->placeholders[$name]= $value;
    }

    # set arrays or object vars as placeholders
    function toPlaceholders($subject, $prefix= '') {
        if (is_object($subject)) {
            $subject= get_object_vars($subject);
        }
        if (is_array($subject)) {
            while(list($key, $value) = each($subject))
            {
                $this->toPlaceholder($key, $value, $prefix);
            }
        }
    }

    function toPlaceholder($key, $value, $prefix= '') {
        if (is_array($value) || is_object($value)) {
            $this->toPlaceholders($value, "{$prefix}{$key}.");
        } else {
            $this->setPlaceholder("{$prefix}{$key}", $value);
        }
    }

    # returns the virtual relative path to the manager folder
    function getManagerPath() {
        return $this->config['base_url'] . 'manager/';
    }

    # returns the virtual relative path to the cache folder
    function getCachePath() {
        return $this->config['base_url'] . 'assets/cache/';
    }
	
	# Returns current user id
	function getLoginUserID($context= '')
	{
		if ($context && isset ($_SESSION["{$context}Validated"]))
		{
			return $_SESSION["{$context}InternalKey"];
		}
		elseif ($this->isFrontend() && isset ($_SESSION['webValidated']))
		{
			return $_SESSION['webInternalKey'];
		}
		elseif ($this->isBackend() && isset ($_SESSION['mgrValidated']))
		{
			return $_SESSION['mgrInternalKey'];
		}
		else return false;
	}

    # Returns current user name
    function getLoginUserName($context= '') {
        if (!empty($context) && isset ($_SESSION[$context . 'Validated'])) {
            return $_SESSION[$context . 'Shortname'];
        }
        elseif ($this->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webShortname'];
        }
        elseif ($this->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return $_SESSION['mgrShortname'];
        }
        else return false;
    }

    # Returns current login user type - web or manager
    function getLoginUserType() {
        if ($this->isFrontend() && isset ($_SESSION['webValidated'])) {
            return 'web';
        }
        elseif ($this->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return 'manager';
        } else {
            return '';
        }
    }

	# Returns a record for the manager user
	function getUserInfo($uid)
	{
		$field = 'mu.username, mu.password, mua.*';
		$from  = '[+prefix+]manager_users mu INNER JOIN [+prefix+]user_attributes mua ON mua.internalkey=mu.id';
		$rs= $this->db->select($field,$from,"mu.id = '$uid'");
		$limit= $this->db->getRecordCount($rs);
		if ($limit == 1)
		{
			$row= $this->db->getRow($rs);
			if (!$row['usertype']) $row['usertype']= 'manager';
			return $row;
		}
		else return false;
	}
	
	# Returns a record for the web user
	function getWebUserInfo($uid)
	{
		$field = 'wu.username, wu.password, wua.*';
		$from = '[+prefix+]web_users wu INNER JOIN [+prefix+]web_user_attributes wua ON wua.internalkey=wu.id';
		$rs= $this->db->select($field,$from,"wu.id='$uid'");
		$limit= $this->db->getRecordCount($rs);
		if ($limit == 1)
		{
			$row= $this->db->getRow($rs);
			if (!$row['usertype']) $row['usertype']= 'web';
			return $row;
		}
		else return false;
	}

    # Returns an array of document groups that current user is assigned to.
    # This function will first return the web user doc groups when running from frontend otherwise it will return manager user's docgroup
    # Set $resolveIds to true to return the document group names
	function getUserDocGroups($resolveIds= false)
	{
		$dg  = array(); // add so
		$dgn = array();
		if($this->isFrontend() && isset($_SESSION['webDocgroups']) && !empty($_SESSION['webDocgroups']) && isset($_SESSION['webValidated']))
		{
			$dg = $_SESSION['webDocgroups'];
			if(isset($_SESSION['webDocgrpNames']))
			{
				$dgn = $_SESSION['webDocgrpNames']; //add so
			}
		}
		if(isset($_SESSION['mgrDocgroups']) && !empty($_SESSION['mgrDocgroups']) && isset($_SESSION['mgrValidated']))
		{
			if($this->config['allow_mgr2web']==='1' || $this->isBackend())
			{
				$dg = array_merge($dg, $_SESSION['mgrDocgroups']);
				if(isset($_SESSION['mgrDocgrpNames']))
				{
					$dgn = array_merge($dgn, $_SESSION['mgrDocgrpNames']);
				}
			}
		}
		if(!$resolveIds)
		{
			return $dg;
		}
		elseif(!empty($dgn) || empty($dg))
		{
			return $dgn; // add so
		}
		elseif(is_array($dg))
		{
			// resolve ids to names
			$dgn = array ();
			$imploded_dg = implode(',', $dg);
			$ds = $this->db->select('name', '[+prefix+]documentgroup_names', "id IN ({$imploded_dg})");
			while ($row = $this->db->getRow($ds))
			{
				$dgn[count($dgn)] = $row['name'];
			}
			// cache docgroup names to session
			if($this->isFrontend()) $_SESSION['webDocgrpNames'] = $dgn;
			else                    $_SESSION['mgrDocgrpNames'] = $dgn;
			return $dgn;
		}
	}
	
	# returns true if the current web user is a member the specified groups
	function isMemberOfWebGroup($groupNames= array ())
	{
		if (!is_array($groupNames)) return false;
		
		// check cache
		$grpNames= isset ($_SESSION['webUserGroupNames']) ? $_SESSION['webUserGroupNames'] : false;
		if (!is_array($grpNames))
		{
			$uid = $this->getLoginUserID();
			$from  = '[+prefix+]webgroup_names wgn' .
			         " INNER JOIN [+prefix+]web_groups wg ON wg.webgroup=wgn.id AND wg.webuser='{$uid}'";
			$rs = $this->db->select('wgn.name', $from);
			$grpNames= $this->db->getColumn('name', $rs);
			
			// save to cache
			$_SESSION['webUserGroupNames']= $grpNames;
		}
		foreach ($groupNames as $k => $v)
		{
			if (in_array(trim($v), $grpNames)) return true;
		}
		return false;
	}

    # Remove unwanted html tags and snippet, settings and tags
    function stripTags($html, $allowed= '')
    {
        $t= strip_tags($html, $allowed);
        $t= preg_replace('~\[\*(.*?)\*\]~', '', $t); //tv
        $t= preg_replace('~\[\[(.*?)\]\]~', '', $t); //snippet
        $t= preg_replace('~\[\!(.*?)\!\]~', '', $t); //snippet
        $t= preg_replace('~\[\((.*?)\)\]~', '', $t); //settings
        $t= preg_replace('~\[\+(.*?)\+\]~', '', $t); //placeholders
        $t= preg_replace('~{{(.*?)}}~', '', $t); //chunks
        return $t;
    }
	
    # remove all event listners - only for use within the current execution cycle
    function removeAllEventListener() {
        unset ($this->pluginEvent);
        $this->pluginEvent= array ();
    }

	# invoke an event. $extParams - hash array: name=>value
	function invokeEvent($evtName, $extParams= array ())
	{
		if (!empty($this->safeMode))               return false;
		if (!$evtName)                             return false;
		if (!isset ($this->pluginEvent[$evtName])) return false;
		
		$el= $this->pluginEvent[$evtName];
		$results= array ();
		$numEvents= count($el);
		if ($numEvents > 0)
		{
			if(!isset($this->pluginCache)) $this->setPluginCache();
			
			for ($i= 0; $i < $numEvents; $i++)
			{ // start for loop
				$pluginName= $el[$i];
				$pluginName = stripslashes($pluginName);
				// reset event object
				$e= & $this->event;
				$e->_resetEventObject();
				$e->name= $evtName;
				$e->activePlugin= $pluginName;
				
				// get plugin code
				if (isset ($this->pluginCache[$pluginName]))
				{
					$pluginCode= $this->pluginCache[$pluginName];
					$pluginProperties= isset($this->pluginCache["{$pluginName}Props"]) ? $this->pluginCache["{$pluginName}Props"] : '';
				}
				else
				{
					$fields = '`name`, plugincode, properties';
					$where = "`name`='{$pluginName}' AND disabled=0";
					$result= $this->db->select($fields,'[+prefix+]site_plugins',$where);
					if ($this->db->getRecordCount($result) == 1)
					{
						$row= $this->db->getRow($result);
						
						$pluginCode                      = $row['plugincode'];
						$this->pluginCache[$row['name']] = $row['plugincode']; 
						$pluginProperties= $this->pluginCache["{$row['name']}Props"]= $row['properties'];
					}
					else
					{
						$pluginCode                      = 'return false;';
						$this->pluginCache[$pluginName]  = 'return false;';
						$pluginProperties= '';
					}
				}
				
				// load default params/properties
				$parameter= $this->parseProperties($pluginProperties);
				if (!empty($extParams))
					$parameter= array_merge($parameter, $extParams);
				
				// eval plugin
				$this->evalPlugin($pluginCode, $parameter);
				$e->setAllGlobalVariables();
				if ($e->_output != '')
					$results[]= $e->_output;
				if ($e->_propagate != true)
					break;
			}
		}
		$e->activePlugin= '';
		return $results;
	}

	# parses a resource property string and returns the result as an array
	function parseProperties($propertyString)
	{
		$parameter= array ();
		if (empty($propertyString)) return $parameter;
		
		$tmpParams= explode('&', $propertyString);
		foreach ($tmpParams as $tmpParam)
		{
			if (strpos($tmpParam, '=') !== false)
			{
				$pTmp  = explode('=', $tmpParam);
				$pvTmp = explode(';', trim($pTmp['1']));
				if ($pvTmp['1'] == 'list' && $pvTmp['3'] != '')
				{
					$parameter[trim($pTmp['0'])]= $pvTmp['3']; //list default
				}
				elseif ($pvTmp['1'] != 'list' && $pvTmp['2'] != '')
				{
					$parameter[trim($pTmp['0'])]= $pvTmp['2'];
				}
			}
		}
		while(list($k, $v) = each($parameter))
		{
			$v = str_replace('%3D','=',$v);
			$v = str_replace('%26','&',$v);
			$parameter[$k] = $v;
		}
		return $parameter;
	}

    function sendmail($params=array(), $msg='')
    	{$this->loadExtension('SubParser');return $this->sub->sendmail($params, $msg);}
    function rotate_log($target='event_log',$limit=2000, $trim=100)
    	{$this->loadExtension('SubParser');$this->sub->rotate_log($target,$limit,$trim);}
    function logEvent($evtid, $type, $msg, $title= 'Parser')
    	{$this->loadExtension('SubParser');$this->sub->logEvent($evtid,$type,$msg,$title);}
    function clearCache($params=array())
    	{$this->loadExtension('SubParser');return $this->sub->clearCache($params);}
    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='')
    	{$this->loadExtension('SubParser');$this->sub->messageQuit($msg,$query,$is_error,$nr,$file,$source,$text,$line,$output);}
    function get_backtrace($backtrace)
    	{$this->loadExtension('SubParser');return $this->sub->get_backtrace($backtrace);}
    function _IIS_furl_fix()
    	{$this->loadExtension('SubParser');$this->sub->_IIS_furl_fix();}
    function sendRedirect($url, $count_attempts= 0, $type= '', $responseCode= '')
    	{$this->loadExtension('SubParser');$this->sub->sendRedirect($url,$count_attempts,$type,$responseCode);}
    function sendForward($id='', $responseCode= '')
    	{$this->loadExtension('SubParser');$this->sub->sendForward($id, $responseCode);}
    function sendErrorPage()
    	{$this->loadExtension('SubParser');$this->sub->sendErrorPage();}
    function sendUnauthorizedPage()
    	{$this->loadExtension('SubParser');$this->sub->sendUnauthorizedPage();}
    function setCacheRefreshTime($unixtime)
    	{$this->loadExtension('SubParser');$this->sub->setCacheRefreshTime($unixtime);}
    function webAlert($msg, $url= '')
    	{$this->loadExtension('SubParser');$this->sub->webAlert($msg, $url);}
    function getSnippetId()
    	{$this->loadExtension('SubParser');return $this->sub->getSnippetId();}
    function getSnippetName()
    	{$this->loadExtension('SubParser');return $this->sub->getSnippetName();}
    function runSnippet($snippetName, $params= array ())
    	{$this->loadExtension('SubParser');return $this->sub->runSnippet($snippetName, $params);}
    function changeWebUserPassword($oldPwd, $newPwd)
    	{$this->loadExtension('SubParser');return $this->sub->changeWebUserPassword($oldPwd, $newPwd);}
    function addEventListener($evtName, $pluginName)
    	{$this->loadExtension('SubParser');return $this->sub->addEventListener($evtName, $pluginName);}
    function removeEventListener($evtName, $pluginName='')
    	{$this->loadExtension('SubParser');return $this->sub->removeEventListener($evtName, $pluginName);}
	function regClientCSS($src, $media='')
    	{$this->loadExtension('SubParser');return $this->sub->regClientCSS($src, $media);}
	function regClientScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false), $startup= false)
    	{$this->loadExtension('SubParser');return $this->sub->regClientScript($src, $options, $startup);}
	function regClientStartupHTMLBlock($html)
    	{$this->loadExtension('SubParser');return $this->sub->regClientStartupHTMLBlock($html);}
	function regClientHTMLBlock($html)
    	{$this->loadExtension('SubParser');return $this->sub->regClientHTMLBlock($html);}
	function regClientStartupScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false))
    	{$this->loadExtension('SubParser');return $this->sub->regClientStartupScript($src, $options);}
    	
    /***************************************************************************************/
    /* End of API functions								       */
    /***************************************************************************************/

	function phpError($nr, $text, $file, $line)
	{
		if (error_reporting() == 0 || $nr == 0)
		{
			return true;
		}
		if($this->stopOnNotice == false)
		{
			switch($nr)
			{
				case E_NOTICE:
					if($this->error_reporting <= 2) return true;
					break;
				case E_STRICT:
				case E_DEPRECATED:
					if($this->error_reporting <= 1) return true;
					break;
				default:
					if($this->error_reporting === 0) return true;
			}
		}
		
		if (is_readable($file))
		{
			$source= file($file);
			$source= htmlspecialchars($source[$line -1]);
		}
		else
		{
			$source= '';
		} //Error $nr in $file at $line: <div><code>$source</code></div>
		
		$result = $this->messageQuit('PHP Parse Error', '', true, $nr, $file, $source, $text, $line);
		if($result===false) exit();
		return $result;
	}

    function getRegisteredClientScripts() {
        return implode("\n", $this->jscripts);
    }

    function getRegisteredClientStartupScripts() {
        return implode("\n", $this->sjscripts);
    }
    
	/**
	 * Format alias to be URL-safe. Strip invalid characters.
	 *
	 * @param string Alias to be formatted
	 * @return string Safe alias
	 */
    function stripAlias($alias, $browserID='') {
        // let add-ons overwrite the default behavior
        $results = $this->invokeEvent('OnStripAlias', array ('alias'=>$alias,'browserID'=>$browserID));
        
        if (!empty($results)) return end($results);//if multiple plugins are registered, only the last one is used
        else                  return urlencode(strip_tags($alias));
    }
    
	function nicesize($size) {
		$a = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
		$pos = 0;
		while ($size >= 1024) {
			   $size /= 1024;
			   $pos++;
		}
		return round($size,2).' '.$a[$pos];
	}
	
	function initProcessCache()
	{
		$cache_path = $this->config['base_path'] . 'assets/cache/process.pageCache.php';
		if(is_file($cache_path))
		{
			$src = file_get_contents($cache_path);
			$this->processCache = unserialize($src);
		}
		else $this->processCache = array();
	}
	
	function setProcessCache($key, $value, $mode='mem')
	{
		$this->processCache[$key] = $value;
		
		if($mode==='file')
		{
			$cache_path = $this->config['base_path'] . 'assets/cache/process.pageCache.php';
			file_put_contents($cache_path,serialize($this->processCache), LOCK_EX);
		}
		
	}
	
	function getProcessCache($key)
	{
		if(isset($this->processCache[$key])) return $this->processCache[$key];
		else                                 return false;
	}
	
	function getDocumentListing($str)
	{
		return $this->getIdFromAlias($str);
	}
	
	function getIdFromAlias($alias)
	{
		$cacheKey = md5(__FUNCTION__ . $alias);
		$result = $this->getProcessCache($cacheKey);
		if($result!==false) return $result;
		
		$children = array();
		
		if($this->config['use_alias_path']==1)
		{
			if(strpos($alias,'/')!==false) $_a = explode('/', $alias);
			else                           $_a[] = $alias;
			$id= 0;
			
			foreach($_a as $alias)
			{
				if($id===false) break;
				$rs  = $this->db->select('id', '[+prefix+]site_content', "deleted=0 and parent='{$id}' and alias='{$alias}'");
				if($this->db->getRecordCount($rs)==0) $rs  = $this->db->select('id', '[+prefix+]site_content', "deleted=0 and parent='{$id}' and id='{$alias}'");
				$row = $this->db->getRow($rs);
				
				if($row) $id = $row['id'];
				else     $id = false;
			}
		}
		else
		{
			$rs = $this->db->select('id', '[+prefix+]site_content', "deleted=0 and alias='{$alias}'", 'parent, menuindex');
			$row = $this->db->getRow($rs);
			
			if($row) $id = $row['id'];
			else     $id = false;
		}
		$this->setProcessCache($cacheKey,$id,'file');
		return $id;
	}
	
    // End of class.
}

// SystemEvent Class
class SystemEvent {
    var $name;
    var $_propagate;
    var $_output;
    var $_globalVariables;
    var $activated;
    var $activePlugin;

    function SystemEvent($name= '') {
        $this->_resetEventObject();
        $this->name= $name;
    }

    // used for displaying a message to the user
    function alert($msg) {
        global $SystemAlertMsgQueque;
        if ($msg == '')
            return;
        if (is_array($SystemAlertMsgQueque)) {
            if ($this->name && $this->activePlugin)
                $title= "<div><b>" . $this->activePlugin . "</b> - <span style='color:maroon;'>" . $this->name . "</span></div>";
            $SystemAlertMsgQueque[]= "$title<div style='margin-left:10px;margin-top:3px;'>$msg</div>";
        }
    }

    // used for rendering an out on the screen
    function output($msg) {
        $this->_output .= $msg;
    }

    // get global variables
    function getGlobalVariable($key) {
        if( isset( $GLOBALS[$key] ) )
        {
            return $GLOBALS[$key];
        }
        return false;
    }

    // set global variables
    function setGlobalVariable($key,$val,$now=0) {
        if (! isset( $GLOBALS[$key] ) ) { return false; }
        if ( $now === 1 || $now === 'now' )
        {
            $GLOBALS[$key] = $val;
        }
        else
        {
            $this->_globalVariables[$key]=$val;
        }
        return true;
    }

    // set all global variables
    function setAllGlobalVariables() {
        if ( empty( $this->_globalVariables ) ) { return false; }
        foreach ( $this->_globalVariables as $key => $val )
        {
            $GLOBALS[$key] = $val;
        }
        return true;
    }

    function stopPropagation() {
        $this->_propagate= false;
    }

    function _resetEventObject() {
        unset ($this->returnedValues);
        $this->name= '';
        $this->_output= '';
        $this->_globalVariables=array();
        $this->_propagate= true;
        $this->activated= false;
    }
}
