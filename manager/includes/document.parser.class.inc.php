<?php
/**
 * MODX Document Parser
 * Function: This class contains the main document parsing functions
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
    var $childrenList = array();
    var $safeMode;
    var $qs_hash;
    var $cacheRefreshTime;
    var $error_reporting;
    var $http_status_code;
    var $directParse;
    var $decoded_request_uri;
    var $dbConfig;
    var $pluginCache;
    var $aliasListing = array();
    var $SystemAlertMsgQueque;
    var $functionCache;
    var $functionCacheBeginCount;
    var $uaType;

    function __get($property_name)
    {
        if($property_name==='documentMap')
            $this->setdocumentMap();
        elseif($property_name==='documentListing')
            return $this->makeDocumentListing();
        elseif($property_name==='chunkCache')
            $this->setChunkCache();
        else
            $this->logEvent(0, 1, "\$modx-&gt;{$property_name} is undefined property", 'Call undefined property');
    }
    
    function __call($method_name, $arguments)
    {
        include_once(MODX_MANAGER_PATH . 'includes/extenders/deprecated.functions.inc.php');
        if(method_exists($this->old,$method_name)) $error_type=1;
        else                                       $error_type=3;
        
        if(!isset($this->config['error_reporting'])||1<$this->config['error_reporting'])
        {
            if($error_type==1)
            {
                $title = 'Call deprecated method';
                $msg = htmlspecialchars("\$modx->{$method_name}() is deprecated function");
            }
            else
            {
                $title = 'Call undefined method';
                $msg = htmlspecialchars("\$modx->{$method_name}() is undefined function");
            }
            $info = debug_backtrace();
            $m[] = $msg;
            if(!empty($this->currentSnippet))          $m[] = 'Snippet - ' . $this->currentSnippet;
            elseif(!empty($this->event->activePlugin)) $m[] = 'Plugin - '  . $this->event->activePlugin;
            $m[] = $this->decoded_request_uri;
            $m[] = str_replace('\\','/',$info[0]['file']) . '(line:' . $info[0]['line'] . ')';
            $msg = implode('<br />', $m);
            $this->logEvent(0, $error_type, $msg, $title);
        }
        if(method_exists($this->old,$method_name))
            return call_user_func_array(array($this->old,$method_name),$arguments);
    }
    // constructor
    function DocumentParser()
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
        $this->dumpSQL      = false;
        $this->dumpSnippets = false; // feed the parser the execution start time
        $this->stopOnNotice = false;
        $this->safeMode     = false;
        $this->decoded_request_uri = urldecode($_SERVER['REQUEST_URI']);
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
        if(!is_dir(MODX_BASE_PATH . 'assets/cache')) mkdir(MODX_BASE_PATH . 'assets/cache');
    }

    // loads an extension from the extenders folder
    function loadExtension($extname)
    {
        global $database_type;
        
        switch ($extname)
        {
            // Database API
            case 'DBAPI' :
                if(!isset($database_type)||empty($database_type)) $database_type = 'mysql';
                if(include_once(MODX_CORE_PATH . "extenders/dbapi.{$database_type}.class.inc.php"))
                {
                    $this->db= new DBAPI;
                    $this->dbConfig= & $this->db->config; // alias for backward compatibility
                    return true;
                }
                else return false;
                break;
            // Manager API
            case 'ManagerAPI' :
                if(include_once(MODX_CORE_PATH . 'extenders/manager.api.class.inc.php'))
                {
                    $this->manager= new ManagerAPI;
                    return true;
                }
                else return false;
                break;
            // PHPMailer
            case 'MODxMailer' :
                include_once(MODX_CORE_PATH . 'extenders/modxmailer.class.inc.php');
                $this->mail= new MODxMailer;
                if($this->mail) return true;
                else            return false;
                break;
            // Resource API
            case 'DocAPI' :
                if(include_once(MODX_CORE_PATH . 'extenders/doc.api.class.inc.php'))
                {
                    $this->doc= new DocAPI;
                    return true;
                }
                else return false;
                break;
            // PHx
            case 'PHx' :
                if(!class_exists('PHx') || !is_object($this->phx))
                {
                    $rs = include_once(MODX_CORE_PATH . 'extenders/phx.parser.class.inc.php');
                    if($rs)
                    {
                        $this->phx= new PHx;
                        return true;
                    }
                    else return false;
                }
                else return true;
                break;
            case 'MakeTable' :
                if(include_once(MODX_CORE_PATH . 'extenders/maketable.class.php'))
                {
                    $this->table= new MakeTable;
                    return true;
                }
                else return false;
                break;
            case 'EXPORT_SITE' :
                if(include_once(MODX_CORE_PATH . 'extenders/export.class.inc.php'))
                {
                    $this->export= new EXPORT_SITE;
                    return true;
                }
                else return false;
                break;
            case 'SubParser':
                include_once(MODX_CORE_PATH . 'extenders/sub.document.parser.class.inc.php');
                $this->sub = new SubParser();
                break;
            case 'REVISION' :
                if(include_once(MODX_CORE_PATH . 'extenders/revision.class.inc.php'))
                {
                    $this->revision = new REVISION;
                    return true;
                }
                else return false;
                break;
            case 'DeprecatedAPI':
                if(include_once(MODX_CORE_PATH . 'extenders/deprecated.functions.inc.php'))
                {
                    return true;
                }
                else return false;
                break;
            default :
                return false;
        }
    }
    
    function executeParser($id='')
    {
        ob_start();
        set_error_handler(array(& $this,'phpError'), E_ALL); //error_reporting(0);
        
        $this->http_status_code = '200';

        if(preg_match('@^[0-9]+$@',$id)) $this->directParse = 1;
        else                             $this->directParse = 0;
        
        // get the settings
        if(!$this->db->conn)      $this->db->connect();
        if(!isset($this->config)) $this->config = $this->getSettings();
        
        if($this->config['individual_cache']==1)
            $this->uaType = $this->getUaType();
        else $this->uaType = 'pages';
        
        $this->functionCache = array();
        $this->functionCacheBeginCount = 0;
        if(is_file(MODX_BASE_PATH . 'assets/cache/function.pageCache.php'))
        {
        	$this->functionCache = include_once(MODX_BASE_PATH . 'assets/cache/function.pageCache.php');
        	$this->functionCacheBeginCount = count($this->functionCache);
        }
        if($this->directParse==0 && !empty($_SERVER['QUERY_STRING']))
        {
            $qs = $_GET;
            if(isset($qs['id'])) unset($qs['id']);
            if(0 < count($qs)) $this->qs_hash = '_' . md5(join('&',$qs));
            else $this->qs_hash = '';
        }
        
        if($this->checkSiteStatus()===false) $this->sendUnavailablePage();
        
        if($this->directParse==1)
        {
            $_REQUEST['id'] = $id;
            $_GET['id']     = $id;
            $this->decoded_request_uri = $this->config['base_url'] . "index.php?id={$id}";
        }
        
        if(!isset($_REQUEST['id']))
        {
            $_REQUEST['q'] = substr($this->decoded_request_uri,strlen($this->config['base_url']));
            if(strpos($_REQUEST['q'],'?')) $_REQUEST['q'] = substr($_REQUEST['q'],0,strpos($_REQUEST['q'],'?'));
        }
        
        if(strpos($_REQUEST['q'],'?')!==false && !isset($_GET['id'])) $_REQUEST['q'] = '';
        elseif($_REQUEST['q']=='index.php') $_REQUEST['q'] = '';
        
        if($this->directParse==0 && 0 < count($_POST)) $this->config['cache_type'] = 0;
        
        if($this->directParse==0)
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
        
        if($this->directParse==1)
        {
            $this->documentMethod     = 'id';
            $this->documentIdentifier = $id;
        }
        else
        {
            // make sure the cache doesn't need updating
            $this->checkPublishStatus();
            
            // find out which document we need to display
            $this->documentMethod= $this->getDocumentMethod();
            $this->documentIdentifier= $this->getDocumentIdentifier($this->documentMethod);
        }
        
        $path = $this->decoded_request_uri;
        $pos = strpos($path,'?');
        if($pos!==false) $path = substr($path,0,$pos);
        if ($this->documentMethod == 'none' || ($path===$this->config['base_url']))
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
                
                $this->documentIdentifier= $this->getIdFromAlias($alias);
                if($this->documentIdentifier===false)
                {
                    $alias .= $this->config['friendly_url_suffix'];
                    $this->documentIdentifier = $this->getIdFromAlias($alias);
                    if ($this->documentIdentifier===false)
                    {
                        $this->sendErrorPage();
                    }
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
            $this->documentObject= $this->getDocumentObject($this->documentMethod, $this->documentIdentifier, 'prepareResponse');
            
            // validation routines
            if($this->checkSiteStatus()===false)
            {
                if (!$this->config['site_unavailable_page'])
                    $this->documentObject['content'] = $this->config['site_unavailable_message'];
            }
            
            if($this->http_status_code == '200')
            {
                if ($this->documentObject['published'] == 0)
                {
                    if (!$this->hasPermission('view_unpublished') || !$this->checkPermissions($this->documentIdentifier))
                        $this->sendErrorPage();
                }
                elseif ($this->documentObject['deleted'] == 1)
                    $this->sendErrorPage();
            }
            // check whether it's a reference
            if($this->documentObject['type'] === 'reference')
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
                if(substr($template->content,0,5)==='@FILE')
                    $template->content = $this->atBindFile($template->content);
                
                if($template->id)
                {
                    if(!empty($template->parent))
                    {
                        $parent = $this->db->getObject('site_templates',"id='{$template->parent}'");
                        $loopcount = 0;
                        $check = array();
                        while($loopcount<20)
                        {
                            $loopcount++;
                            if(array_search($parent->id,$check)===false) $check[] = $parent->id;
                            else $this->messageQuit('Template recursive reference parent error.');
                            
                            if($template->id !== $parent->id)
                            {
                                if(substr($parent->content,0,5)==='@FILE')
                                    $parent->content = $this->atBindFile($parent->content);
                                $template->content = str_replace('[*content*]', $template->content, $parent->content);
                                if(!empty($parent->parent)) $parent = $this->db->getObject('site_templates',"id='{$parent->parent}'");
                                else break;
                            }
                            else break;
                        }
                    }
                    
                    $this->documentContent = $template->content;
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
                if($i == ($passes -1)) $st= crc32($this->documentOutput);
                
                $this->documentOutput = str_replace(array('[!','!]'), array('[[',']]'), $this->documentOutput);
                $this->documentOutput = $this->parseDocumentSource($this->documentOutput);
                
                if($i == ($passes -1) && $i < ($this->maxParserPasses - 1))
                {
                    $et = crc32($this->documentOutput);
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
            $matches = $this->getTagsFromContent($this->documentOutput,'[+','+]');
            if ($matches['0'])
            $this->documentOutput= str_replace($matches['0'], '', $this->documentOutput);
        }
        
        if(strpos($this->documentOutput,'[~')!==false) $this->documentOutput = $this->rewriteUrls($this->documentOutput);
        
        // send out content-type and content-disposition headers
        if (IN_PARSER_MODE == 'true')
        {
            $type = $this->documentObject['contentType'];
            if(empty($type)) $type = 'text/html';
            
            header("Content-Type: {$type}; charset={$this->config['modx_charset']}");
            //            if (($this->documentIdentifier == $this->config['error_page']) || $redirect_error)
            //                header('HTTP/1.0 404 Not Found');
            if ($this->documentObject['content_dispo'] == 1)
            {
                if ($this->documentObject['alias'])
                {
                    $name= $this->documentObject['alias'];
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
        if($this->config['cache_type'] !=2&&strpos($this->documentOutput,'^]')!==false)
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
        
        if(strpos($this->documentOutput,'^]')!==false)
            echo $this->mergeBenchmarkContent($this->documentOutput);
        else
            echo $this->documentOutput;
        
        $result = ob_get_clean();
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
                    $cacheContent  = serialize($this->documentObject['contentType']);
                    $cacheContent .= "<!--__MODxCacheSpliter__-->{$this->documentOutput}";
                    $filename = md5($this->decoded_request_uri);
                    break;
            }
            
            switch($this->http_status_code)
            {
                case '404':
                    $filename = 'error404';
                    break;
                case '403':
                    $filename = 'error403';
                    break;
                case '503':
                    $filename = 'error503';
                    break;
            }
            
            if(mt_rand(0,99) < 1)
            {
                $file_count = count(glob($this->config['base_path'].'assets/cache/*.php'));
                if(1000 < $file_count) $this->clearCache();
            }
            if(!is_dir("{$base_path}assets/cache/{$this->uaType}"))
            	mkdir("{$base_path}assets/cache/{$this->uaType}",0777);
            $page_cache_path = "{$base_path}assets/cache/{$this->uaType}/{$filename}.pageCache.php";
            file_put_contents($page_cache_path, $cacheContent, LOCK_EX);
            
            if($this->functionCache && count($this->functionCache)!=$this->functionCacheBeginCount)
            {
            	$str = '<?php return ' . var_export($this->functionCache, true) . ';';
            	file_put_contents("{$base_path}assets/cache/function.pageCache.php", $str, LOCK_EX);
            }
        }
        
        // Useful for example to external page counters/stats packages
        $this->invokeEvent('OnWebPageComplete');
        
        // end post processing
    }
    
    function getUaType()
    {
		$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
		
		if(strpos($ua, 'ipad')!==false)          $type = 'tablet';
		elseif(strpos($ua, 'iphone')!==false)    $type = 'smartphone';
		elseif(strpos($ua, 'ipod')!==false)      $type = 'smartphone';
		elseif(strpos($ua, 'android')!==false)
		{
			if(strpos($ua, 'mobile')!==false)    $type = 'smartphone';
			else                                 $type = 'tablet';
		}
		elseif(strpos($ua, 'windows phone')!==false)
		                                         $type = 'smartphone';
		elseif(strpos($ua, 'docomo')!==false)    $type = 'mobile';
		elseif(strpos($ua, 'softbank')!==false)  $type = 'mobile';
		elseif(strpos($ua, 'up.browser')!==false)
			                                     $type = 'mobile';
		else                                     $type = 'pc';
		
    	return $type;
    }
    
    function join($delim=',', $array, $prefix='')
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
        $filepath = $this->decoded_request_uri;
        if(strpos($filepath,'?')!==false) $filepath = substr($filepath,0,strpos($filepath,'?'));
        $filepath = substr($filepath,strlen($this->config['base_url']));
        if(substr($filepath,-1)==='/' || empty($filepath)) $filepath .= 'index.html';
        $filepath = $this->config['base_path'] . "temp/public_html/{$filepath}";
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
                    else              $mime_type = $this->getMimeType($filepath);
                    if(!$mime_type) $this->sendErrorPage();
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
        $cache_path = MODX_BASE_PATH . 'assets/cache/config.siteCache.idx.php';
        
        if(is_file($cache_path)) $config= include($cache_path);
        
        if(!isset($config)||!$config)
        {
            include_once MODX_CORE_PATH . 'cache_sync.class.php';
            $cache = new synccache();
            $cache->setCachepath(MODX_BASE_PATH . 'assets/cache/');
            $cache->setReport(false);
            $rebuilt = $cache->buildCache($this);
            
            if($rebuilt && is_file($cache_path)) $config = include($cache_path);
            else $config = false;
        }
        
        return $config;
    }
    
    function getSettings()
    {
        $cache_path = MODX_BASE_PATH . 'assets/cache/siteCache.idx.php';
        if(is_file($cache_path))
            include_once($cache_path);
        if(!isset($this->config) || !is_array($this->config) || empty ($this->config))
        {
            $this->config = $this->getSiteCache();
        }
        
        if($this->config['cache_type']!=='1') $this->setChunkCache();
        
        // added for backwards compatibility - garry FS#104
        $this->config['etomite_charset'] = & $this->config['modx_charset'];
        
        // store base_url and base_path inside config array
        $this->config['base_path']= MODX_BASE_PATH;
        $this->config['core_path']= MODX_CORE_PATH;
        if(empty($this->config['base_url']))
            $this->config['base_url']= MODX_BASE_URL;
        if(empty($this->config['site_url']))
            $this->config['site_url']= MODX_SITE_URL;
        if(empty($this->config['error_page']))
            $this->config['error_page'] = $this->config['start_page'];
        if(empty($this->config['unauthorized_page']))
            $this->config['unauthorized_page'] = $this->config['error_page'];
        
        // load user setting if user is logged in
        $usrSettings= array();
        $uid= $this->getLoginUserID('web');
        if (!empty($uid))
        {
            if (isset ($_SESSION['webUsrConfigSet']) && 0 < count($_SESSION['webUsrConfigSet']))
                $usrSettings= & $_SESSION['webUsrConfigSet'];
            else
            {
                $result= $this->db->select('setting_name, setting_value', '[+prefix+]web_user_settings', "webuser='{$uid}'");
                if($result) {
                    while ($row= $this->db->getRow($result))
                    {
                        $usrSettings[$row['setting_name']]= $row['setting_value'];
                    }
                    $_SESSION['webUsrConfigSet']= $usrSettings;
                }
            }
        }
        $uid= $this->getLoginUserID('mgr');
        if(!empty($uid))
        {
            if($this->isBackend()) $this->invokeEvent('OnBeforeManagerPageInit');
            $musrSettings= array ();
            if(isset ($_SESSION['mgrUsrConfigSet']) && is_array($_SESSION['mgrUsrConfigSet']))
                $musrSettings= & $_SESSION['mgrUsrConfigSet'];
            else
            {
                $result= $this->db->select('setting_name, setting_value','[+prefix+]user_settings',"user='{$uid}'");
                if($result) {
                    while ($row= $this->db->getRow($result))
                    {
                        $musrSettings[$row['setting_name']]= $row['setting_value'];
                    }
                    $_SESSION['mgrUsrConfigSet']= $musrSettings;
                }
            }
            $usrSettings= array_merge($musrSettings, $usrSettings);
        }
        if(!empty($usrSettings)) $this->config= array_merge($this->config, $usrSettings);
        if(strpos($this->config['filemanager_path'],'[(')!==false)
            $this->config['filemanager_path'] = str_replace('[(base_path)]',MODX_BASE_PATH,$this->config['filemanager_path']);
        if(strpos($this->config['rb_base_dir'],'[(')!==false)
            $this->config['rb_base_dir']      = str_replace('[(base_path)]',MODX_BASE_PATH,$this->config['rb_base_dir']);
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
                    $this->sendErrorPage();
                else
                    $docIdentifier= intval($_REQUEST['id']);
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
        if ($this->config['use_alias_path'] == 1) {
            $this->virtualDir = dirname($q);
            $this->virtualDir = ($this->virtualDir === '.') ? '' : $this->virtualDir;
            $q = explode('/', $q);
            $q = end($q);
        } else {
            $this->virtualDir= '';
        }
        $prefix = $this->config['friendly_url_prefix'];
        $suffix = $this->config['friendly_url_suffix'];
        if(!empty($prefix) && strpos($q,$prefix)!==false) $q = preg_replace('@^' . $prefix . '@',  '', $q);
        if(!empty($suffix) && strpos($q,$suffix)!==false) $q = preg_replace('@'  . $suffix . '$@', '', $q);
        if (preg_match('@^[1-9][0-9]*$@',$q)) :
            /* we got an ID returned, check to make sure it's not an alias */
            /* FS#476 and FS#308: check that id is valid in terms of virtualDir structure */
            if ($this->config['use_alias_path'] == 1) {
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
                    )) {
                    $this->documentMethod = 'id';
                    $result = $q;
                } else {
                    /* not a valid id in terms of virtualDir, treat as alias */
                    $this->documentMethod = 'alias';
                    $result = $q;
                }
            } else {
                $id = $this->getIdFromAlias($q);
                if($id!==false) {
                    $this->documentMethod = 'id';
                    $result = $id;
                } else {
                    $this->documentMethod = 'alias';
                    $result = $q;
                }
            }
        else:
            /* we didn't get an ID back, so instead we assume it's an alias */
            if ($this->config['friendly_alias_urls'] != 1) {
                $q = $qOrig;
            }
            $this->documentMethod= 'alias';
            $result = $q;
        endif;
        
        return $result;
    }

    function checkCache($id)
    {
        if(isset($this->config['cache_type']) && $this->config['cache_type'] == 0) return ''; // jp-edition only
        switch($this->http_status_code)
        {
            case '404':
                $filename = 'error404';
                break;
            case '403':
                $filename = 'error403';
                break;
            case '503':
                $filename = 'error503';
                break;
            default:
                $filename = "docid_{$id}{$this->qs_hash}";
        }
        
        $cacheFile = "{$this->config['base_path']}assets/cache/{$this->uaType}/{$filename}.pageCache.php";
        
        if(isset($_SESSION['mgrValidated']) || 0 < count($_POST)) $this->config['cache_type'] = '1';
        
        if(isset($this->config['cache_expire']) && !empty($this->config['cache_expire']) && is_file($cacheFile))
        {
            $timestamp = filemtime($cacheFile);
            $timestamp += $this->config['cache_expire'];
            if($timestamp < $_SERVER['REQUEST_TIME'])
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
            && $docObj['privateweb'] && isset ($docObj['__MODxDocGroups__'])):
            
            $pass = false;
            $usrGrps = $this->getUserDocGroups();
            $docGrps = explode(',',$docObj['__MODxDocGroups__']);
            // check is user has access to doc groups
            if(is_array($usrGrps)&&!empty($usrGrps))
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
        endif;
        
        // Grab the Scripts
        if(isset($docObj['__MODxSJScripts__'])) $this->sjscripts = $docObj['__MODxSJScripts__'];
        if(isset($docObj['__MODxJScripts__']))  $this->jscripts  = $docObj['__MODxJScripts__'];
        
        $this->documentObject = $docObj;
        return $a['1']; // return document content
    }
    
    function checkPublishStatus()
    {
        $cache_path= "{$this->config['base_path']}assets/cache/basicConfig.php";
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
        $timeNow= $_SERVER['REQUEST_TIME'] + $this->config['server_offset_time'];
        
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
    
        // clear the cache
        $this->clearCache();
        
        unset($this->chunkCache);
        $this->setChunkCache();
    }
    
    function getTagsFromContent($content,$left='[+',$right='+]') {
        $_ = $this->_getTagsFromContent($content,$left,$right);
        if(empty($_)) return array();
        foreach($_ as $v)
        {
            $tags[0][] = "{$left}{$v}{$right}";
            $tags[1][] = $v;
        }
        return $tags;
    }
    
    function _getTagsFromContent($content, $left='[+',$right='+]') {
        $_tmp = $content;
        $spacer = md5('dummy');
        if(strpos($_tmp,']]>')!==false)  $_tmp = str_replace(']]>', "]{$spacer}]>",$_tmp);
        if(strpos($_tmp,';}}')!==false)  $_tmp = str_replace(';}}', ";}{$spacer}}",$_tmp);
        if(strpos($_tmp,'{{}}')!==false) $_tmp = str_replace('{{}}',"{{$spacer}{}{$spacer}}",$_tmp);
        $count_left  = 0;
        $count_right = 0;
        $strlen_left  = strlen($left);
        $strlen_right = strlen($right);
        $key = '';
        $c = 0;
        while($_tmp!=='')
        {
            $bt = $_tmp;
            $key .= substr($_tmp,0,1);
            $_tmp = substr($_tmp,1);
            $strpos_left = strpos($key,$left);
            if($strpos_left!==false && substr($key,-$strlen_right)===$right)
            {
                $key = substr($key,$strpos_left);
                if(substr_count($key,$left)===substr_count($key,$right))
                {
                    $key = substr($key, (strpos($key,$left) + $strlen_left) );
                    $tags[] = substr($key,0,strlen($key)-$strlen_right);
                    $key = '';
                }
            }
            if($bt === $_tmp) break;
            if(1000000<$c) exit('Fetch tags error');
            $c++;
        }
        if(!$tags) return array();
        
        foreach($tags as $tag)
        {
            if(strpos($tag,$left)!==false)
            {
                $fetch = $this->_getTagsFromContent($tag,$left,$right);
                foreach($fetch as $v)
                {
                    $tags[] = $v;
                }
            }
        }
        $i = 0;
        foreach($tags as $tag)
        {
            if(strpos($tag,"]{$spacer}]>")!==false)           $tags[$i] = str_replace("]{$spacer}]>", ']]>', $tag);
            if(strpos($tag,";}{$spacer}}")!==false)           $tags[$i] = str_replace(";}{$spacer}}", ';}}', $tag);
            if(strpos($tag,"{{$spacer}{}{$spacer}}")!==false) $tags[$i] = str_replace("{{$spacer}{}{$spacer}}", '{{}}',$tag);
            $i++;
        }
        return $tags;
    }
    
    // mod by Raymond
    function mergeDocumentContent($content)
    {
        if(!isset($this->documentIdentifier)) return $content;
        if(strpos($content,'[*')===false) return $content;
        if(!isset($this->documentObject) || empty($this->documentObject)) return $content;
        
        $matches = $this->getTagsFromContent($content,'[*','*]');
        if(!$matches) return $content;
        
        $i= 0;
        $replace= array ();
        foreach($matches['1'] as $key):
            $key= substr($key, 0, 1) == '#' ? substr($key, 1) : $key; // remove # for QuickEdit format
            
            if(strpos($key,':')!==false && $this->config['output_filter']!=='0')
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if(!isset($this->documentObject[$key])) $value = '';
            else $value= $this->documentObject[$key];
            
            if (is_array($value)) $value= $this->tvProcessor($value);
            
            if($modifiers!==false)
            {
                $this->loadExtension('PHx') or die('Could not load PHx class.');
                $value = $this->phx->phxFilter($key,$value,$modifiers);
            }
            else
            {
                switch($key)
                {
                    case 'createdon':
                    case 'editedon':
                    case 'publishedon':
                    case 'pub_date':
                    case 'unpub_date':
                        $value = $this->toDateFormat($value);
                        break;
                    case 'createdby':
                    case 'editedby':
                    case 'publishedby':
                        $_ = $this->getUserInfo($value);
                        $value = $_['username'];
                        break;
                }
            }
            $replace[$i]= $value;
            $i++;
        endforeach;
        $content= str_replace($matches['0'], $replace, $content);
        return $content;
    }
    
    function mergeSettingsContent($content)
    {
        if(strpos($content,'[(')===false) return $content;
        
        $matches = $this->getTagsFromContent($content,'[(',')]');
        if(!$matches) return $content;
        
        $i= 0;
        $replace= array ();
        foreach($matches['1'] as $key):
            if(strpos($key,':')!==false && $this->config['output_filter']!=='0')
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if(isset($this->config[$key]))
            {
                
                $value = $this->config[$key];
                if($modifiers!==false)
                {
                    $this->loadExtension('PHx') or die('Could not load PHx class.');
                    $value = $this->phx->phxFilter($key,$value,$modifiers);
                }
                
                $replace[$i]= $value;
            }
            else $replace[$i]= $key;
            $i++;
        endforeach;
        
        $content= str_replace($matches['0'], $replace, $content);
        return $content;
    }
    
    function mergeChunkContent($content)
    {
        if(strpos($content,'{{')===false) return $content;
        
        $matches = $this->getTagsFromContent($content,'{{','}}');
        if(!$matches) return $content;
        
        $i= 0;
        $replace= array ();
        foreach($matches['1'] as $key):
            if(strpos($key,':')!==false && $this->config['output_filter']!=='0')
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if ($this->getChunk($key)!==false):
                $value= $this->getChunk($key);
            else:
                if(!isset($this->chunkCache)) $this->setChunkCache();
                $escaped_name = $this->db->escape($key);
                $where = "`name`='{$escaped_name}' AND `published`='1'";
                $rs    = $this->db->select('snippet','[+prefix+]site_htmlsnippets',$where);
                if ($this->db->getRecordCount($rs)==1)
                {
                    $row= $this->db->getRow($rs);
                    $this->chunkCache[$key]= $row['snippet'];
                    $value= $row['snippet'];
                }
                else
                {
                    $this->chunkCache[$key]= '';
                    $value= '';
                }
            endif;
            
            if($modifiers!==false)
            {
                $this->loadExtension('PHx') or die('Could not load PHx class.');
                $value = $this->phx->phxFilter($key,$value,$modifiers);
            }
            $replace[$i] = $value;
            $i++;
        endforeach;
        
        $content= str_replace($matches['0'], $replace, $content);
        return $content;
    }
    
    // Added by Raymond
    function mergePlaceholderContent($content)
    {
        if(strpos($content,'[+')===false) return $content;
        
        $replace= array ();
        $content=$this->mergeSettingsContent($content);
        $matches = $this->getTagsFromContent($content,'[+','+]');
        if(!$matches) return $content;
        $i= 0;
        $replace = array();
        foreach($matches['1'] as $key):
            if(strpos($key,':')!==false && $this->config['output_filter']!=='0')
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if (is_array($this->placeholders) && isset($this->placeholders[$key]))
                $value = $this->placeholders[$key];
            else $value = '';
            
            if ($value !== ''):
                if($modifiers!==false)
                {
                    $modifiers = $this->mergePlaceholderContent($modifiers);
                    $this->loadExtension('PHx') or die('Could not load PHx class.');
                    $value = $this->phx->phxFilter($key,$value,$modifiers);
                }
                $replace[$i]= $value;
            else:
                unset ($matches['0'][$i]); // here we'll leave empty placeholders for last.
            endif;
            $i++;
        endforeach;
        $content= str_replace($matches['0'], $replace, $content);
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
    
    function mergeConditionalTagsContent($content, $left='<!--@IF:', $right='<!--@ENDIF-->')
    {
        if(strpos($content,'<!--@IF ')!==false) $content = str_replace('<!--@IF ',$left,$content);
        if(strpos($content,$left)===false) return $content;
        $matches = $this->getTagsFromContent($content,$left,$right);
        if(!empty($matches))
        {
            foreach($matches['0'] as $i=>$v)
            {
                $cmd = substr($v,8,strpos($v,'-->')-8);
                $cmd = trim($cmd);
                $cond = substr($cmd,0,1)!=='!' ? true : false;
                if($cond===false) $cmd = ltrim($cmd,'!');
                switch(substr($cmd,0,2)) {
                    case '[*':
                    case '[[':
                    case '[!':
                        if(strpos($cmd,'[!')!==false)
                            $cmd = str_replace(array('[!','!]'),array('[[',']]'),$cmd);
                        $cmd = $this->parseDocumentSource($cmd);
                        break;
                }
                $cmd = trim($cmd);
                if(strpos($matches['1'][$i],'<!--@ELSE-->')) {
                    list($if_content,$else_content) = explode('<!--@ELSE-->',$matches['1'][$i]);
                } else {
                    $if_content = $matches['1'][$i];
                    $else_content = '';
                }
                    
                if( ($cond===true && empty($cmd)) || ($cond===false && !empty($cmd)) )
                    $matches['1'][$i] = $else_content;
                else
                    $matches['1'][$i] = substr($if_content,strpos($if_content,'-->')+3);
            }
            $content = str_replace($matches['0'],$matches['1'],$content);
        }
        return $content;
    }
    
    function mergeBenchmarkContent($content)
    {
        if(strpos($content,'^]')===false) return $content;
        
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
        if(!isset($this->chunkCache)) $this->setChunkCache();
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
    
    function evalSnippets($content)
    {
        if(strpos($content,'[[')===false) return $content;
        
        $etomite= & $this;
        
        if(!$this->snippetCache) $this->setSnippetCache();
        $matches = $this->getTagsFromContent($content,'[[',']]');
        
        if(!$matches) return $content;
        $i= 0;
        $replace= array ();
        foreach($matches['1'] as $value)
        {
            if(strpos($value,'[[')!==false) $value = $this->evalSnippets($value);
            $replace[$i] = $this->_get_snip_result($value);
            $i++;
        }
        $content = str_replace($matches['0'], $replace, $content);
        return $content;
    }
    
    private function _get_snip_result($piece)
    {
        $snip_call = $this->_split_snip_call($piece);
        $snip_name = $snip_call['name'];
        
        if(strpos($snip_name,':')!==false && $this->config['output_filter']==='1')
        {
            list($snip_name,$modifiers) = explode(':', $snip_name, 2);
            $snip_call['name'] = $snip_name;
        }
        else $modifiers = false;
        
        $snippetObject = $this->_get_snip_properties($snip_call);
        $this->currentSnippet = $snippetObject['name'];
        
        // current params
        $params = $this->_snipParamsToArray($snip_call['params']);
        
        if(isset($snippetObject['properties']))
        {
            $default_params = $this->parseProperties($snippetObject['properties']);
            $params = array_merge($default_params,$params);
        }
        
        $value = $this->evalSnippet($snippetObject['content'], $params);
        if($modifiers!==false)
        {
            $this->loadExtension('PHx') or die('Could not load PHx class.');
            $value = $this->phx->phxFilter($snip_name,$value,$modifiers);
        }
        
        if($this->dumpSnippets == 1)
        {
            $this->snipCode .= sprintf('<fieldset><legend><b>%s</b></legend><textarea style="width:60%%;height:200px">%s</textarea></fieldset>', $snippetObject['name'], htmlentities($value,ENT_NOQUOTES,$this->config['modx_charset']));
        }
        return $value;
    }
    
    function _snipParamsToArray($string='')
    {
        if(empty($string)) return array();
        
        $_tmp = $string;
        $_tmp = ltrim($_tmp, '?&');
        $params = array();
        while($_tmp!==''):
            $bt = $_tmp;
            $char = substr($_tmp,0,1);
            $_tmp = substr($_tmp,1);
            
            if($char==='=')
            {
                $_tmp = trim($_tmp);
                $nextchar = substr($_tmp,0,1);
                if(in_array($nextchar, array('"', "'", '`')))
                {
                    list($null, $value, $_tmp) = explode($nextchar, $_tmp, 3);
                    if($nextchar !== "'")
                    {
                        if(strpos($value,'[*')!==false) $value = $this->mergeDocumentContent($value);
                        if(strpos($value,'[(')!==false) $value = $this->mergeSettingsContent($value);
                        if(strpos($value,'{{')!==false) $value = $this->mergeChunkContent($value);
                        if(strpos($value,'[+')!==false) $value = $this->mergePlaceholderContent($value);
                    }
                }
                elseif(strpos($_tmp,'&')!==false)
                {
                    list($value, $_tmp) = explode('&', $_tmp, 2);
                    $value = trim($value);
                }
                else
                {
                    $value = $_tmp;
                    $_tmp = '';
                }
            }
            elseif($char==='&')
            {
                $value = '';
            }
            else $key .= $char;
            
            if(!is_null($value))
            {
                if(strpos($key,'amp;')!==false) $key = str_replace('amp;', '', $key);
                $key=trim($key);
                $params[$key]=$value;
                
                $key   = '';
                $value = null;

                $_tmp = ltrim($_tmp, " ,\t");
                if(substr($_tmp, 0, 2)==='//') $_tmp = strstr($_tmp, "\n");
            }
            
            if($_tmp===$bt)
            {
                $key = trim($key);
                if($key!=='') $params[$key] = '';
                break;
            }
        endwhile;
        
        return $params;
    }
    
    private function _split_snip_call($src)
    {
        $spacer = md5('dummy');
        if(strpos($src,']]>')!==false)
            $src = str_replace(']]>', "]{$spacer}]>",$src);
        
        list($call,$snip['except_snip_call']) = explode(']]', $src, 2);

        $pos['?']  = strpos($call, '?');
        $pos['&']  = strpos($call, '&');
        $pos['=']  = strpos($call, '=');
        $pos['lf'] = strpos($call, "\n");
        
        if($pos['?'] !== false)
        {
            if($pos['lf']!==false && $pos['?'] < $pos['lf'])
                list($name,$params) = explode('?',$call,2);
            elseif($pos['lf']!==false && $pos['lf'] < $pos['?'])
                list($name,$params) = explode("\n",$call,2);
            else
                list($name,$params) = explode('?',$call,2);
        }
        elseif($pos['&'] !== false && $pos['='] !== false && $pos['?'] === false)
            list($name,$params) = explode('&',$call,2);
        elseif($pos['lf'] !== false)
            list($name,$params) = explode("\n",$call,2);
        else
        {
            $name   = $call;
            $params = '';
        }
        
        $snip['name']   = trim($name);
        if(strpos($params,$spacer)!==false)
            $params = str_replace("]{$spacer}]>",']]>',$params);
        $snip['params'] = $params = ltrim($params,"?& \t\n");
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
                //$this->logEvent(0,'1','Not found snippet name [['.$snippetObject['name'].']] {$this->decoded_request_uri}',"Parser (ResourceID:{$this->documentIdentifier})");
            }
        }
        return $snippetObject;
    }
    
    function setChunkCache()
    {
        if(isset($this->chunkCache)) return;
        $chunk = @include_once(MODX_BASE_PATH . 'assets/cache/chunk.siteCache.idx.php');
        if(is_array($chunk)) $this->chunkCache = $chunk;
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
        $d = @include_once(MODX_BASE_PATH . 'assets/cache/documentMap.siteCache.idx.php');
        if($d) $this->documentMap = $d;
        else return false;
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
        $aliases = array();
        if(is_file($path_aliases))
        {
            $aliases = @include_once($path_aliases);
            $this->aliases = $aliases;
        }
        if(empty($aliases))
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
    function getDocumentObject($method='id', $identifier='', $mode='direct')
    {
        if(isset($_SESSION['mgrValidated'])
             && $mode==='prepareResponse'
             && isset($_POST['id']) && preg_match('@^[0-9]+$@',$_POST['id'])
            )
        {
            if(!isset($_POST['token']) || !isset($_SESSION['token']) || $_POST['token']!==$_SESSION['token']) {
                exit('Can not preview');
            }
            $previewObject = $this->getPreviewObject($_POST);
            $this->directParse = 1;
            $method = 'id';
            $identifier = $previewObject['id'];
            $this->documentIdentifier = $identifier;
        }
        else $previewObject = false;
        
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
        if ($docgrp= $this->getUserDocGroups()) $docgrp= implode(',', $docgrp);
        
        // get document (add so)
        if($this->isFrontend()) $access= 'sc.privateweb=0';
        else                    $access= 'sc.privatemgr=0';
        
        if($docgrp) $access .= " OR dg.document_group IN ({$docgrp})";
        $access .= " OR 1='{$_SESSION['mgrRole']}'";
        
        $from = "[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id";
        $where ="sc.{$method}='{$identifier}' AND ($access)";
        $result= $this->db->select('sc.*',$from,$where,'',1);
        if ($this->db->getRecordCount($result) < 1)
        {
            if ($this->isBackend()||$mode==='direct') return false;
            
            // method may still be alias, while identifier is not full path alias, e.g. id not found above
            if ($method === 'alias') :
                $field = 'dg.id';
                $from = "[+prefix+]document_groups dg, [+prefix+]site_content sc";
                $where =  "dg.document = sc.id AND sc.alias = '{$identifier}'";
            else:
                $field = 'id';
                $from = '[+prefix+]document_groups';
                $where =  "document = '{$identifier}'";
            endif;
            
            // check if file is not public
            $total= $this->db->getRecordCount($this->db->select($field,$from,$where,'',1));
            if ($total > 0) $this->sendUnauthorizedPage();
            else            $this->sendErrorPage();
        }
        
        # this is now the document :) #
        $documentObject= $this->db->getRow($result);
        if( $previewObject )
        {
            $snapObject = $documentObject;
        }
        if($mode==='prepareResponse') $this->documentObject = & $documentObject;
        $this->invokeEvent('OnLoadDocumentObject');
        $docid = $documentObject['id'];
        
        // load TVs and merge with document - Orig by Apodigm - Docvars
        $f[] = 'tv.name';
        $f[] = "IF(tvc.value!='',tvc.value,tv.default_text) as value";
        $f[] = 'tv.display';
        $f[] = 'tv.display_params';
        $f[] = 'tv.type';
        $field = implode(',',$f);
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
                $name = $row['name'];
                if(isset($documentObject[$name])) continue;
                $tmplvars[$name][]       = $row['name'];
                $tmplvars[$name][]       = $row['value'];
                $tmplvars[$name][]       = $row['display'];
                $tmplvars[$name][]       = $row['display_params'];
                $tmplvars[$name][]       = $row['type'];
                $tmplvars[$name]['name']           = $row['name'];
                $tmplvars[$name]['value']          = $row['value'];
                $tmplvars[$name]['display']        = $row['display'];
                $tmplvars[$name]['display_params'] = $row['display_params'];
                $tmplvars[$name]['type']           = $row['type'];
            }
            $documentObject= array_merge($documentObject, $tmplvars);
        }
        if($previewObject)
        {
            foreach($documentObject as $k=>$v)
            {
                if(!isset($previewObject[$k])) continue;
                if( $snapObject[$k] !=  $documentObject[$k] ) continue; // Priority is higher changing on OnLoadDocumentObject event.
                if(!is_array($documentObject[$k]))
                    $documentObject[$k] = $previewObject[$k];
                else $documentObject[$k]['value'] = $previewObject[$k];
            }
        }
        return $documentObject;
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
            if ($i == ($passes -1)) $bt= crc32($source);
            if ($this->dumpSnippets == 1)
            {
                $this->snipCode .= '<fieldset><legend><b style="color: #821517;">PARSE PASS ' . ($i +1) . '</b></legend>The following snippets (if any) were parsed during this pass.<div style="width:100%;text-align:left;">';
            }
            
            // invoke OnParseDocument event
            $this->documentOutput= $source; // store source code so plugins can
            $this->invokeEvent('OnParseDocument'); // work on it via $modx->documentOutput
            $source= $this->documentOutput;
            
            if(strpos($source,'<!--@IF')!==false)             $source= $this->mergeConditionalTagsContent($source);
            if(strpos($source,'<!--@IGNORE:BEGIN-->')!==false) $source= $this->ignoreCommentedTagsContent($source);
            if(strpos($source,'<!--@IGNORE-->')!==false)       $source= $this->ignoreCommentedTagsContent($source,'<!--@IGNORE-->','<!--@ENDIGNORE-->');
            if(strpos($source,'<!--@MODX:')!==false)           $source= $this->mergeCommentedTagsContent($source);
            // combine template and document variables
            if(strpos($source,'[*')!==false)                   $source= $this->mergeDocumentContent($source);
            // replace settings referenced in document
            if(strpos($source,'[(')!==false)                   $source= $this->mergeSettingsContent($source);
            // replace HTMLSnippets in document
            if(strpos($source,'{{')!==false)                   $source= $this->mergeChunkContent($source);
            // insert META tags & keywords
            if(isset($this->config['show_meta']) && $this->config['show_meta']==1)
                                                               $source= $this->mergeDocumentMETATags($source);
            // find and merge snippets
            if(strpos($source,'[[')!==false)                   $source= $this->evalSnippets($source);
            // find and replace Placeholders (must be parsed last) - Added by Raymond
            if(strpos($source,'[+')!==false
             &&strpos($source,'[[')===false)                   $source= $this->mergePlaceholderContent($source);
            
            if ($this->dumpSnippets == 1)
            {
                $this->snipCode .= '</div></fieldset>';
            }
            if ($i == ($passes -1) && $i < ($this->maxParserPasses - 1))
            {
                // check if source length was changed
                if ($bt != crc32($source))
                {
                    $passes++; // if content change then increase passes because
                }
            } // we have not yet reached maxParserPasses
            
            if(strpos($source,'[~')!==false && strpos($source,'[~[+')===false)
                                                               $source = $this->rewriteUrls($source);
        }
        return $source;
    }
    
    /***************************************************************************************/
    /* API functions                                                                /
    /***************************************************************************************/

    function getParentIds($id='', $height= 10)
    {
        if($id==='') $id = $this->documentIdentifier;
        $parents= array ();
        
        if(empty($this->aliasListing)) $this->setAliasListing();
        
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
        if($this->childrenList) return $this->childrenList;
        $path_childrenListCache = MODX_BASE_PATH . 'assets/cache/childrenList.siteCache.idx.php';
        if(is_file($path_childrenListCache))
        {
            $src = file_get_contents($path_childrenListCache);
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
            file_put_contents($path_childrenListCache,serialize($childrenList), LOCK_EX);
            $this->childrenList = $childrenList;
        }
        return $this->childrenList;
    }

    function getChildIds($id, $depth= 10, $children= array ())
    {
        // Initialise a static array to index parents->children
        if(empty($this->childrenList))
            $childrenList = $this->set_childrenList();
        else
            $childrenList = $this->childrenList;
        
        // Get all the children for this parent node
        if (isset($childrenList[$id]))
        {
            $depth--;
            
            if(empty($this->aliasListing)) $this->setAliasListing();
            
            foreach ($childrenList[$id] as $childId)
            {
                $pkey = $this->aliasListing[$childId]['alias'];
                if(strlen($this->aliasListing[$childId]['path']))
                {
                    $pkey = "{$this->aliasListing[$childId]['path']}/{$pkey}";
                }
                
                if (!strlen($pkey)) $pkey = $childId;
                $children[$pkey] = $childId;
                
                if ($depth && isset($childrenList[$childId])) {
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
            while ($row = $this->db->getRow($result))
            {
                $resourceArray[] = $row;
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
        if(empty($rows)) {
            $this->referenceListing = array();
            return array();
        }
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
    
    function makeUrl($id, $alias= '', $args= '', $scheme= 'full')
    {
        if($id==0) return $this->config['site_url'];
        $makeurl= '';
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
            else return $this->referenceListing[$id];
        }
        
        if ($this->config['friendly_urls'] == 1)
        {
            $alPath = '';
            if(empty($alias))
            {
                $alias = $id;
                if ($this->config['friendly_alias_urls'] == 1)
                {
                    if(!$this->aliasListing) $this->setAliasListing();
                    
                    $al= $this->aliasListing[$id];
                    $alPath = ($al && !empty($al['path'])) ? $al['path'] . '/' : '';
                    if(!empty($alPath))
                    {
                        $_ = explode('/', $alPath);
                        foreach($_ as $i=>$v)
                        {
                            $_[$i] = urlencode($v);
                        }
                        $alPath = join('/', $_);
                    }
                    if ($al && $al['alias'])
                    {
                        if($this->config['xhtml_urls']==='1') $alias = urlencode($al['alias']);
                        else                                  $alias = $al['alias'];
                    }
                    else return false;
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
            $makeurl = $alPath . $f_url_prefix . $alias . $f_url_suffix;
        }
        else {
            if(!$this->aliasListing)  $this->setAliasListing();
            if(isset($this->aliasListing[$id])) $makeurl= "index.php?id={$id}";
            else return false;
        }
        
        $site_url = $this->config['site_url'];
        $base_url = $this->config['base_url'];
        switch($scheme)
        {
            case 'full':
            case 'f':
                $site_url = $this->config['site_url'];
                $base_url = '';
                if($id==$this->config['site_start'])
                    $makeurl = '';
                break;
            case 'http':
            case '0':
                if(strpos($site_url,'http://')!==0)
                    $site_url = 'http' . substr($site_url,strpos($site_url,':'));
                $base_url = '';
                break;
            case 'https':
            case 'ssl':
            case '1':
                if(strpos($site_url,'https://')!==0)
                    $site_url = 'https' . substr($site_url,strpos($site_url,':'));
                $base_url = '';
                break;
            case 'absolute':
            case 'abs':
            case 'a':
                $site_url = '';
                $base_url = $this->config['base_url'];
                if($id==$this->config['site_start'])
                    $makeurl = '';
                break;
            case 'relative':
            case 'rel':
            case 'r':
            case '-1':
            default:
                $site_url = '';
                $base_url = '';
        }
        
        $url = "{$site_url}{$base_url}{$makeurl}";
        if($args!=='')
        {
            $args = ltrim($args,'?&');
            if(strpos($url,'?')===false) $url .= "?{$args}";
            else                         $url .= "&{$args}";
        }
        
        if($this->config['xhtml_urls']) $url = preg_replace("/&(?!amp;)/",'&amp;', $url);
        
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
    
    function rewriteUrls($content)
    {
        if(strpos($content,'[~')===false) return $content;
        
        if(!isset($this->referenceListing))
        {
            $this->referenceListing = $this->_getReferenceListing();
        }
        
        $replace= array ();
        $matches = $this->getTagsFromContent($content,'[~','~]');
        if(!$matches) return $content;
        
        $i= 0;
        foreach($matches['1'] as $key)
        {
            $key_org = $key;
            $key = trim($key);
            $key = $this->mergeDocumentContent($key);
            $key = $this->mergeSettingsContent($key);
            $key = $this->mergeChunkContent($key);
            $key = $this->evalSnippets($key);
            
            if(preg_match('/^[0-9]+$/',$key))
            {
                $id = $key;
                if(isset($this->referenceListing[$id]) && preg_match('/^[0-9]+$/',$this->referenceListing[$id] ))
                {
                    $id = $this->referenceListing[$id];
                }
                $replace[$i] = $this->makeUrl($id,'','','rel');
                if(!$replace[$i])
                {
                    $ph['linktag']     = "[~{$key_org}~]";
                    $ph['request_uri'] = $this->decoded_request_uri;
                    $ph['docid']       = $this->documentIdentifier;
                    $tpl = 'Can not parse linktag [+linktag+] <a href="index.php?a=27&id=[+docid+]">[+request_uri+]</a>';
                    $tpl = $this->parseText($tpl,$ph);
                    $this->logEvent(0,'1',$tpl, "Missing parse link tag(ResourceID:{$this->documentIdentifier})");
                }
            }
            else
            {
                $replace[$i] = $key;
            }
            $i++;
        }
        $content = str_replace($matches['0'], $replace, $content);
        return $content;
    }
    
    function getConfig($name= '', $default='')
    {
        if(!isset($this->config[$name]))
        {
            if($default==='') return false;
            else              return $default;
        }
        else                  return $this->config[$name];
    }
    
    function getChunk($key)
    {
        if(!$this->chunkCache) $this->setChunkCache();
        if($key==='') return false;
        
        if(isset($this->chunkCache[$key]))
        {
            return $this->chunkCache[$key];
        }
        else {
            return false;
        }
    }
    
    function parseChunk($chunkName, $chunkArr, $prefix= '{', $suffix= '}',$mode='chunk')
    {
        if (!is_array($chunkArr)) return false;
        
        if($mode==='chunk') $src= $this->getChunk($chunkName);
        else                $src = $chunkName;
        
        while(list($key, $value) = each($chunkArr))
        {
            $src= str_replace("{$prefix}{$key}{$suffix}", $value, $src);
        }
        return $src;
    }

    function parseText($content='', $ph=array(), $left= '[+', $right= '+]',$cleanup=true)
    {
        if(!$ph) return $content;
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
        $matches = $this->getTagsFromContent($content,$left,$right);
        if(!$matches) return $content;
        $i= 0;
        $replace= array ();
        foreach($matches['1'] as $key):
            if(strpos($key,':')!==false && $this->config['output_filter']!=='0')
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if(isset($ph[$key]))
            {
                $value = $ph[$key];
                if($modifiers!==false)
                {
                    $this->loadExtension('PHx') or die('Could not load PHx class.');
                    $value = $this->phx->phxFilter($key,$value,$modifiers);
                }
                $replace[$i]= $value;
            }
            elseif($cleanup) $replace[$i] = '';
            else             $replace[$i] = $matches['0'][$i];
            $i++;
        endforeach;
        $content= str_replace($matches['0'], $replace, $content);
        return $content;
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
    function getTemplateVars($idnames='*',$fields='*',$docid='',$published= 1,$sort='rank',$dir='ASC')
    {
        if($idnames!='*' && !is_array($idnames)) $idnames = array($idnames);
        
        if (is_array($idnames) && empty($idnames)):
            return false;
        else:
            $result= array ();
            
            // get document record
            if ($docid == ''):
                $docid = $this->documentIdentifier;
                $resource= $this->documentObject;
            else:
                $resource= $this->getDocument($docid, '*', $published);
                if (!$resource) return false;
            endif;
            // get user defined template variables
            $fields= ($fields == '') ? 'tv.*' : $this->join(',',explode(',',$fields),'tv.');
            $sort= ($sort == '')     ? ''     : $this->join(',',explode(',',$sort),'tv.');
            
            if ($idnames === '*') $where= 'tv.id<>0';
            elseif (preg_match('@^[0-9]+$@',$idnames['0']))
                $where= "tv.id='{$idnames['0']}'";
            else
            {
                $i = 0;
                foreach($idnames as $idname)
                {
                    $idnames[$i] = $this->db->escape(trim($idname));
                    $i++;
                }
                $tvnames = "'" . join("','", $idnames) . "'";
                $where = (preg_match('@^[1-9][0-9]*$@',$idnames['0'])) ? 'tv.id' : "tv.name IN ({$tvnames})";
            }
            if ($docgrp= $this->getUserDocGroups())
                $docgrp= implode(',', $docgrp);
            
            $fields  = "{$fields}, IF(tvc.value!='',tvc.value,tv.default_text) as value";
            $from    = '[+prefix+]site_tmplvars tv';
            $from   .= ' INNER JOIN [+prefix+]site_tmplvar_templates tvtpl  ON tvtpl.tmplvarid = tv.id';
            $from   .= " LEFT JOIN [+prefix+]site_tmplvar_contentvalues tvc ON tvc.tmplvarid=tv.id AND tvc.contentid='{$docid}'";
            $where  = "{$where} AND tvtpl.templateid={$resource['template']}";
            
            if ($sort)
                 $orderby = "{$sort} {$dir}";
            else $orderby = '';
            
            $rs= $this->db->select($fields,$from,$where,$orderby);
            while($row = $this->db->getRow($rs))
            {
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
        endif;
    }

    # returns an associative array containing TV rendered output values. $idnames - can be an id or name that belongs the template that the current document is using
    function getTemplateVarOutput($idnames= '*', $docid= '', $published= 1, $sep='')
    {
        if (is_array($idnames) && empty($idnames))
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
                foreach($result as $row)
                {
                    if (!$row['id'])
                    {
                        $output[$row['name']] = $row['value'];
                    }
                    else
                    {
                        $row['docid'] = $docid;
                        $row['sep']   = $sep;
                        $output[$row['name']] = $this->tvProcessor($row);
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
    function setPlaceholder($name, $value) {
        $this->placeholders[$name]= $value;
    }

    # set arrays or object vars as placeholders
    function toPlaceholders($subject, $prefix= '') {
        if (is_object($subject)) {
            $subject= get_object_vars($subject);
        }
        if (is_array($subject)) {
            foreach($subject as $key=>$value)
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
            if(!$this->pluginCache) $this->setPluginCache();
            
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
    
    /*
    * Template Variable Display Format
    * Created by Raymond Irving Feb, 2005
    */
    // Added by Raymond 20-Jan-2005
    function tvProcessor($value,$format='',$paramstring='',$name='',$tvtype='',$docid='', $sep='')
    {
        $modx = & $this;
        
        if(is_array($value))
        {
            if(isset($value['docid'])) $docid = $value['docid'];
            if(isset($value['sep']))   $sep   = $value['sep'];
            $format      = $value['display'];
            $paramstring = $value['display_params'];
            $name        = $value['name'];
            $tvtype      = $value['type'];
            $value       = $value['value'];
        }
        // process any TV commands in value
        $docid= intval($docid) ? intval($docid) : $this->documentIdentifier;
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
                    if(substr($v,0,1)==='@')
                        $values[$i] = $this->ProcessTVCommand($v, $name, $docid, $src);
                    $i++;
                }
                $value = join('||', $values);
                break;
            default:
                $src = 'docform';
                if(substr($value,0,1)==='@')
                    $value = $this->ProcessTVCommand($value, $name, $docid, $src);
        }
        
        if(empty($value))
        {
            if($format!=='custom_widget' && $format!=='richtext' && $format!=='datagrid')
                return $value;
            elseif($format==='datagrid' && $params['egmsg']==='')
                return '';
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
                    if(strpos($ar[1],'%')!==false)
                        $params[$ar[0]] = $this->decodeParamValue($ar[1]);
                    else
                        $params[$ar[0]] = $ar[1];
                }
            }
        }

        $id = "tv{$name}";
        $o = '';
        switch($format)
        {
            case 'image':
            case 'delim': // display as delimitted list
            case 'string':
            case 'date':
            case 'dateonly':
            case 'hyperlink':
            case 'htmltag':
            case 'richtext':
            case 'unixtime':
            case 'datagrid':
            case 'htmlentities':
            case 'custom_widget':
                $o = include(MODX_CORE_PATH . "docvars/outputfilter/{$format}.inc.php");
                break;
            default:
                if(is_resource($value)) $value = $this->parseInput($value);
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
    
    function sendmail($params=array(), $msg='')
        {$this->loadExtension('SubParser');return $this->sub->sendmail($params, $msg);}
    function rotate_log($target='event_log',$limit=2000, $trim=100)
        {$this->loadExtension('SubParser');$this->sub->rotate_log($target,$limit,$trim);}
    function addLog($title='no title',$msg='',$type=1)
        {$this->loadExtension('SubParser');$this->sub->addLog($title,$msg,$type);}
    function logEvent($evtid, $type, $msg, $title= 'Parser')
        {$this->loadExtension('SubParser');$this->sub->logEvent($evtid,$type,$msg,$title);}
    function clearCache($params=array())
        {$this->loadExtension('SubParser');return $this->sub->clearCache($params);}
    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='')
        {$this->loadExtension('SubParser');$this->sub->messageQuit($msg,$query,$is_error,$nr,$file,$source,$text,$line,$output);}
    function get_backtrace($backtrace)
        {$this->loadExtension('SubParser');return $this->sub->get_backtrace($backtrace);}
    function sendRedirect($url, $count_attempts= 0, $type= 'REDIRECT_HEADER',$responseCode='')
        {$this->loadExtension('SubParser');$this->sub->sendRedirect($url,$count_attempts,$type,$responseCode);}
    function sendForward($id='', $responseCode= '')
        {$this->loadExtension('SubParser');$this->sub->sendForward($id, $responseCode);}
    function sendErrorPage()
        {$this->loadExtension('SubParser');$this->sub->sendErrorPage();}
    function sendUnauthorizedPage()
        {$this->loadExtension('SubParser');$this->sub->sendUnauthorizedPage();}
    function sendUnavailablePage()
        {$this->loadExtension('SubParser');$this->sub->sendUnavailablePage();}
    function setCacheRefreshTime($unixtime)
        {$this->loadExtension('SubParser');$this->sub->setCacheRefreshTime($unixtime);}
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
    function updateDraft($now)
        {$this->loadExtension('SubParser');$this->sub->updateDraft($now);}
    function regClientCSS($src, $media='')
        {$this->loadExtension('SubParser');$this->sub->regClientCSS($src, $media);}
    function regClientScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false), $startup= false)
        {$this->loadExtension('SubParser');$this->sub->regClientScript($src, $options, $startup);}
    function regClientStartupHTMLBlock($html)
        {$this->loadExtension('SubParser');$this->sub->regClientStartupHTMLBlock($html);}
    function regClientHTMLBlock($html)
        {$this->loadExtension('SubParser');$this->sub->regClientHTMLBlock($html);}
    function regClientStartupScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false))
        {$this->loadExtension('SubParser');$this->sub->regClientStartupScript($src, $options);}
    function checkPermissions($docid=false,$duplicateDoc = false)
        {$this->loadExtension('SubParser');return $this->sub->checkPermissions($docid,$duplicateDoc);}
        
    function ProcessTVCommand($value, $name = '', $docid = '', $src='docform')
        {$this->loadExtension('SubParser');return $this->sub->ProcessTVCommand($value, $name, $docid, $src);}
    function ParseCommand($binding_string)
        {$this->loadExtension('SubParser');return $this->sub->ParseCommand($binding_string);}
    function getExtention($str)
        {$this->loadExtension('SubParser');return $this->sub->getExtention($str);}
    function decodeParamValue($s)
        {$this->loadExtension('SubParser');return $this->sub->decodeParamValue($s);}
    function parseInput($src, $delim='||', $type='string', $columns=true)
        {$this->loadExtension('SubParser');return $this->sub->parseInput($src, $delim, $type, $columns);}
    function getUnixtimeFromDateString($value)
        {$this->loadExtension('SubParser');return $this->sub->getUnixtimeFromDateString($value);}

    function renderFormElement($f_type, $f_id, $default_text, $f_elements, $f_value, $f_style='', $row = array())
        {$this->loadExtension('SubParser');
        return $this->sub->renderFormElement($f_type,$f_id,$default_text,$f_elements,$f_value, $f_style,$row);}
    function ParseIntputOptions($v)
        {$this->loadExtension('SubParser');return $this->sub->ParseIntputOptions($v);}
    function splitOption($value)
        {$this->loadExtension('SubParser');return $this->sub->splitOption($value);}
    function isSelected($label,$value,$item,$field_value)
        {$this->loadExtension('SubParser');return $this->sub->isSelected($label,$value,$item,$field_value);}
    function webAlertAndQuit($msg, $url= '')
        {$this->loadExtension('SubParser');return $this->sub->webAlertAndQuit($msg, $url);}
    function getMimeType($file_path='')
        {$this->loadExtension('SubParser');return $this->sub->getMimeType($file_path);}
    function getUserInfo($uid)
        {$this->loadExtension('SubParser');return $this->sub->getUserInfo($uid);}
    function getWebUserInfo($uid)
        {$this->loadExtension('SubParser');return $this->sub->getWebUserInfo($uid);}
    function isMemberOfWebGroup($groupNames= array ())
        {$this->loadExtension('SubParser');return $this->sub->isMemberOfWebGroup($groupNames);}
    function getLoginUserType()
        {$this->loadExtension('SubParser');return $this->sub->getLoginUserType();}
    function getLoginUserName($context= '')
        {$this->loadExtension('SubParser');return $this->sub->getLoginUserName($context);}

    function getDocumentChildrenTVars($parentid= 0, $tvidnames= '*', $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC', $tvfields= '*', $tvsort= 'rank', $tvsortdir= 'ASC')
        {$this->loadExtension('SubParser');return $this->sub->getDocumentChildrenTVars($parentid, $tvidnames, $published, $docsort, $docsortdir, $tvfields, $tvsort, $tvsortdir);}
    function getDocumentChildrenTVarOutput($parentid= 0, $tvidnames= '*', $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC')
        {$this->loadExtension('SubParser');return $this->sub->getDocumentChildrenTVarOutput($parentid, $tvidnames, $published, $docsort, $docsortdir);}
    function getPreviewObject($input)
        {$this->loadExtension('SubParser');return $this->sub->getPreviewObject($input);}

    function getAllChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle',$where=false)
        {$this->loadExtension('SubParser');return $this->sub->getAllChildren($id, $sort, $dir, $fields,$where);}
    function getActiveChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle')
        {$this->loadExtension('SubParser');return $this->sub->getActiveChildren($id, $sort, $dir, $fields);}
    function getDocumentChildren($parentid= 0, $published= 1, $deleted= 0, $fields= '*', $where= '', $sort= 'menuindex', $dir= 'ASC', $limit= '')
        {$this->loadExtension('SubParser');return $this->sub->getDocumentChildren($parentid, $published, $deleted, $fields, $where, $sort, $dir, $limit);}
    function loadLexicon($target='manager')
        {$this->loadExtension('SubParser');return $this->sub->loadLexicon($target);}
    function snapshot($filename='',$target='')
        {$this->loadExtension('SubParser');return $this->sub->snapshot($filename,$target);}
    function getVersionData($data=null)
        {$this->loadExtension('SubParser');return $this->sub->getVersionData($data);}
    function _IIS_furl_fix()
        {$this->loadExtension('SubParser');return $this->sub->_IIS_furl_fix();}
    function genToken()
        {$this->loadExtension('SubParser');return $this->sub->genToken();}
    function atBindFile($content='')
        {$this->loadExtension('SubParser');return $this->sub->atBindFile($content);}
    
    // - deprecated db functions
    function dbConnect()                 {$this->db->connect();$this->rs= $this->db->conn;}
    function dbQuery($sql)               {return $this->db->query($sql);}
    function recordCount($rs)            {return $this->db->getRecordCount($rs);}
    function fetchRow($rs,$mode='assoc') {return $this->db->getRow($rs, $mode);}
    function affectedRows($rs)           {return $this->db->getAffectedRows($rs);}
    function insertId($rs)               {return $this->db->getInsertId($rs);}
    function dbClose()                   {$this->db->disconnect();}
    
    function putChunk($chunkName)   {return $this->getChunk($chunkName);}
    function getDocGroups()         {return $this->getUserDocGroups();}
    function changePassword($o, $n) {return changeWebUserPassword($o, $n);}
    function parsePlaceholder($src='', $ph=array(), $left= '[+', $right= '+]',$mode='ph')
                                    {return $this->parseText($src, $ph, $left, $right, $mode);}
    

    /***************************************************************************************/
    /* End of API functions                                       */
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
            $source= $source[$line -1];
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
        else                  return strip_tags($alias);
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
    
    function getDocumentListing($str)
    {
        return $this->getIdFromAlias($str);
    }
    function setFunctionCache($cacheKey,$value)
    {
    	$this->functionCache[$cacheKey] = $value;
    }
    
    function getIdFromAlias($alias)
    {
        $cacheKey = md5(__FUNCTION__ . $alias);
        if(isset($this->functionCache[$cacheKey]))
        	return $this->functionCache[$cacheKey];
        
        $children = array();
        
        if($this->config['use_alias_path']==1)
        {
            if(strpos($alias,'/')!==false) $_a = explode('/', $alias);
            else                           $_a[] = $alias;
            $id= 0;
            
            foreach($_a as $alias)
            {
                if($id===false) break;
                $alias = $this->db->escape($alias);
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
            
            if(!$row && preg_match('@^[1-9][0-9]*$@',$alias))
            {
                $rs = $this->db->select('id', '[+prefix+]site_content', "deleted=0 and id='{$alias}'");
                $row = $this->db->getRow($rs);
            }
            if($row) $id = $row['id'];
            else     $id = false;
        }
        $this->functionCache[$cacheKey] = $id;
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
        if ($msg == '')
            return;
        if (is_array($this->SystemAlertMsgQueque)) {
            if ($this->name && $this->activePlugin)
                $title= "<div><b>" . $this->activePlugin . "</b> - <span style='color:maroon;'>" . $this->name . "</span></div>";
            $this->SystemAlertMsgQueque[]= "$title<div style='margin-left:10px;margin-top:3px;'>$msg</div>";
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
