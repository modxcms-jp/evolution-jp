<?php
/**
 * MODX Document Parser
 * Function: This class contains the main document parsing functions
 *
 */

require_once('initialize.inc.php');
$conf_path = MODX_CORE_PATH . 'config.inc.php';
if (is_file($conf_path)) include_once($conf_path);

if (!isset($lastInstallTime) || empty($lastInstallTime)) {
    if(strpos($_SERVER['SCRIPT_NAME'],'install/index.php')===false) {
        if(is_file(MODX_BASE_PATH . 'install/index.php')) {
            header('Location: install/index.php?action=mode');
            exit();
        }else exit('Not installed.');
    }
}

if(!defined('MODX_API_MODE')) set_parser_mode();
if (session_id() === '') startCMSSession();
$modx = new DocumentParser;
return $modx;

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
    var $documentOutput;
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
    var $ph;
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
    var $functionCache = array();
    var $uaType;
    var $functionLog = array();
    var $currentSnippetCall;
    var $aliasCache = array();
    var $previewObject = ''; //プレビュー用のPOSTデータを保存
    var $snipLapCount;
    var $chunkieCache;

    private $baseTime = ''; //タイムマシン(基本は現在時間)

    function __get($property_name)
    {
        if($property_name==='documentMap')
            $this->setdocumentMap();
        elseif($property_name==='documentListing')
            return $this->makeDocumentListing();
        elseif(isset($this->config[$property_name]))
            return $this->config[$property_name];
        else
            $this->logEvent(0, 1, "\$modx-&gt;{$property_name} is undefined property", 'Call undefined property');
    }
    
    function __call($method_name, $arguments)
    {
        switch($method_name) {
            case 'ParseCommand':
            case 'ParseInputOptions':
            case 'ProcessTVCommand':
            case '_IIS_furl_fix':
            case 'addEventListener':
            case 'addLog':
            case 'atBindFile':
            case 'atBindUrl':
            case 'changeWebUserPassword':
            case 'checkPermissions':
            case 'clearCache':
            case 'decodeParamValue':
            case 'genToken':
            case 'getActiveChildren':
            case 'getAllChildren':
            case 'getDocumentChildren':
            case 'getDocumentChildrenTVarOutput':
            case 'getDocumentChildrenTVars':
            case 'getExtention':
            case 'getLoginUserName':
            case 'getLoginUserType':
            case 'getMimeType':
            case 'getOption':
            case 'getPreviewObject':
            case 'getSnippetId':
            case 'getSnippetName':
            case 'getUnixtimeFromDateString':
            case 'getUserInfo':
            case 'getVersionData':
            case 'getWebUserInfo':
            case 'get_backtrace':
            case 'isMemberOfWebGroup':
            case 'isSelected':
            case 'loadLexicon':
            case 'logEvent':
            case 'mergeInlineFilter':
            case 'messageQuit':
            case 'parseInput':
            case 'recDebugInfo':
            case 'regClientCSS':
            case 'regClientHTMLBlock':
            case 'regClientScript':
            case 'regClientStartupHTMLBlock':
            case 'regClientStartupScript':
            case 'regOption':
            case 'removeEventListener':
            case 'renderFormElement':
            case 'rotate_log':
            case 'runSnippet':
            case 'sendErrorPage':
            case 'sendForward':
            case 'sendRedirect':
            case 'sendUnauthorizedPage':
            case 'sendUnavailablePage':
            case 'sendmail':
            case 'setCacheRefreshTime':
            case 'setOption':
            case 'snapshot':
            case 'splitOption':
            case 'updateDraft':
            case 'webAlertAndQuit':
                $this->loadExtension('SubParser');
                if(method_exists($this->sub,$method_name))
                    return call_user_func_array(array($this->sub,$method_name),$arguments);
        }
        
        $this->loadExtension('DeprecatedAPI');
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
    function __construct()
    {
        $this->loadExtension('DBAPI') or die('Could not load DBAPI class.'); // load DBAPI class
        $this->loadExtension('DocumentAPI');
        
        if($this->isBackend()) $this->loadExtension('ManagerAPI');
        
        // events
        $this->event= new SystemEvent();
        $this->Event= & $this->event; //alias for backward compatibility
        $this->ph = & $this->placeholders;
        
        $this->minParserPasses = 1; // min number of parser recursive loops or passes
        $this->maxParserPasses = 10; // max number of parser recursive loops or passes
        $this->dumpSQL      = false;
        $this->dumpSnippets = false; // feed the parser the execution start time
        $this->snipLapCount = 0;
        $this->stopOnNotice = false;
        $this->safeMode     = false;
        // set track_errors ini variable
        @ ini_set('track_errors', '1'); // enable error tracking in $php_errormsg
        $this->error_reporting = 1;
        // Don't show PHP errors to the public
        if($this->checkSession()===false && !defined('MODX_API_MODE')) @ini_set('display_errors','0');
        
        if(!isset($this->tstart)) {
            $this->tstart = $_SERVER['REQUEST_TIME_FLOAT'];
        }
        if(!isset($this->mstart))
            $this->mstart = memory_get_usage();
        
        $alias_cache_path = MODX_BASE_PATH . 'assets/cache/alias.siteCache.idx.php';
        if(is_file($alias_cache_path)) {
            $aliasCache = file_get_contents($alias_cache_path);
            $this->aliasCache=unserialize($aliasCache);
        }
        $this->setAliasListing();
    }

    /*
     * loads an extension from the extenders folder
     *
     * @param $extname Extension name
     * @return bool or Object
     * 
     */
    function loadExtension($extname)
    {
        global $database_type;
        
        $low_extname = strtolower($extname);
        
        switch ($low_extname)
        {
            case 'dbapi'       : // Database API
            case 'managerapi'  : // Manager API
            case 'docapi'      : // Resource API
            case 'export_site' :
            case 'subparser'   :
            case 'revision'    :
                return include_once(MODX_CORE_PATH . "extenders/ex_{$low_extname}.php");
            case 'documentapi' : // Document API
                include_once(MODX_CORE_PATH . "extenders/ex_{$low_extname}.php");
                Document::$modx=$this;
                return;
            case 'modifiers' : //Modfires
            case 'phx' :
                return include_once(MODX_CORE_PATH . 'extenders/ex_modifiers.php');
            case 'deprecatedapi':
                return include_once(MODX_CORE_PATH . 'extenders/ex_deprecated.php');
            case 'modxmailer' : // PHPMailer
                include_once(MODX_CORE_PATH . 'extenders/ex_modxmailer.php');
                $this->mail= new MODxMailer;
                return;
            case 'maketable' :
                include_once(MODX_CORE_PATH . 'extenders/ex_maketable.php');
                $this->table= new MakeTable;
                return;
            case 'configmediation':
                include_once(MODX_CORE_PATH . 'extenders/ex_configmediation.php');
                return new CONFIG_MEDIATION($this);
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

        $this->setBaseTime();
        $this->sanitizeVars();
        $this->uaType  = $this->setUaType();
        $this->qs_hash = $this->genQsHash();
        
        if($this->checkSiteStatus()===false) $this->sendUnavailablePage();
        
        $this->decoded_request_uri = $this->setRequestUri($id);
        $_REQUEST['q'] = $this->setRequestQ($this->decoded_request_uri);
        
        if($this->directParse==0)
        {
            if(0 < count($_POST)) $this->config['cache_type'] = 0;
            
            $this->documentOutput = $this->get_static_pages($this->decoded_request_uri);
            if(!empty($this->documentOutput))
            {
                $this->documentOutput = $this->parseDocumentSource($this->documentOutput);
                $this->invokeEvent('OnWebPagePrerender');
                echo $this->documentOutput;
                $this->invokeEvent('OnWebPageComplete');
                exit;
            }
            // make sure the cache doesn't need updating
            $this->updatePublishStatus();
            
            // find out which document we need to display
            $this->documentMethod= isset($_REQUEST['id']) ? 'id' : 'alias';
            $this->documentIdentifier= $this->getDocumentIdentifier($this->documentMethod);
        }
        else
        {
            $this->documentMethod     = 'id';
            $this->documentIdentifier = $id;
        }
        
        $path = $this->decoded_request_uri;
        $pos = strpos($path,'?');
        if($pos!==false) $path = substr($path,0,$pos);
        if ($path===$this->config['base_url'])
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
                if(strlen($this->virtualDir) > 0) $alias = $this->virtualDir . '/' . $alias;
                
                $this->documentIdentifier= $this->getIdFromAlias($alias);
                if($this->documentIdentifier===false)
                {
                    $this->documentIdentifier = $this->getIdFromAlias($alias . $this->config['friendly_url_suffix']);
                    if ($this->documentIdentifier===false)
                        $this->sendErrorPage();
                }
            }
            else
            {
                $this->documentIdentifier= $this->getIdFromAlias($this->documentIdentifier);
            }
            $this->documentMethod= 'id';
        }
        // invoke OnWebPageInit event
        $this->invokeEvent('OnWebPageInit');
        
        $result = $this->prepareResponse();
        return $result;
    }
    
    function setRequestQ($decoded_request_uri) {
        if(isset($_REQUEST['id'])) $q = null;
        else {
            $q = substr($decoded_request_uri,strlen($this->config['base_url']));
            if(strpos($q,'?')!==false) $q = substr($q,0,strpos($q,'?'));
            if($q=='index.php')        $q = '';
        }
        // IIS friendly url fix
        if (strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false)
            $q = $this->_IIS_furl_fix();
        
        return $q;
    }
    
    function sanitizeVars() {
        if (isset($_SERVER['QUERY_STRING']) && strpos(urldecode($_SERVER['QUERY_STRING']), chr(0)) !== false)
            exit();
        
        foreach (array ('PHP_SELF', 'HTTP_USER_AGENT', 'HTTP_REFERER', 'QUERY_STRING') as $key) {
            $_SERVER[$key] = isset ($_SERVER[$key]) ? htmlspecialchars($_SERVER[$key], ENT_QUOTES) : null;
        }
        $this->sanitize_gpc($_GET);
        if($this->isBackend()) {
            if(session_id()==='' || $_SESSION['mgrPermissions']['save_document']!=1) $this->sanitize_gpc($_POST);
        }
        $this->sanitize_gpc($_COOKIE);
        $this->sanitize_gpc($_REQUEST);
    }
    
    function setUaType() {
        if($this->config['individual_cache']==1&&$this->config['cache_type']!=2)
            $uaType = $this->getUaType();
        else $uaType = 'pages';
        return $uaType;
    }
    
    function genQsHash() {
        if($this->directParse==0 && !empty($_SERVER['QUERY_STRING']))
        {
            $qs = $_GET;
            if(isset($qs['id'])) unset($qs['id']);
            if(0 < count($qs)) $qs_hash = '_' . md5(join('&',$qs));
            else $qs_hash = '';
            $userID = $this->getLoginUserID('web');
            if($userID) $qs_hash = md5($qs_hash."^{$userID}^");
        }
        else $qs_hash = '';
        return $qs_hash;
    }
    
    function setRequestUri($id=0) {
        if($this->directParse==1)
        {
            $_REQUEST['id'] = $id;
            $_GET['id']     = $id;
            $rs = $this->config['base_url'] . "index.php?id={$id}";
        }
        else $rs = urldecode($_SERVER['REQUEST_URI']);
        return $rs;
    }
    
    function prepareResponse()
    {
        // we now know the method and identifier, let's check the cache
        $this->documentContent= $this->getCache($this->documentIdentifier);
        if ($this->documentContent != '')
        {
          $params = array('useCache' => true);
          $this->invokeEvent('OnLoadWebPageCache',$params); // invoke OnLoadWebPageCache  event
          if( $params['useCache'] != true ) //no use cache
          {
            $this->config['cache_type'] = 0;
            $this->documentContent = '';
          }
        }

        if ($this->documentContent == '')
        {
            $this->setChunkCache();
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
                if( !empty($this->previewObject) ){
                    $this->directParse = 0;
                }
                $rs = $this->sendRedirect($this->documentObject['content'], 0, '', 'HTTP/1.0 301 Moved Permanently');
                if($this->directParse==1) return $rs;
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
                elseif(substr($template->content,0,4)==='@URL')
                    $template->content = $this->atBindUrl($template->content);
                
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
                                elseif(substr($parent->content,0,4)==='@URL')
                                    $parent->content = $this->atBindUrl($parent->content);
                                if(strpos($parent->content,'[*content*]')!==false)
                                    $template->content = str_replace('[*content*]', $template->content, $parent->content);
                                elseif(strpos($parent->content,'[*#content*]')!==false)
                                    $template->content = str_replace('[*#content*]', $template->content, $parent->content);
                                elseif(strpos($parent->content,'[*content:')!==false)
                                {
                                    $matches = $this->getTagsFromContent($parent->content,'[*content:','*]');
                                    if($matches[0])
                                    {
                                        $modifier = $matches[1][0];
                                        $template->content = str_replace($matches[0][0], $template->content, $parent->content);
                                        $template->content = str_replace('[*content*]',"[*content:{$modifier}*]",$template->content);
                                    }
                                }
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
        if ($this->sjscripts && $js= $this->getRegisteredClientStartupScripts())
        {
            $this->documentOutput= str_ireplace('</head>', "{$js}\n</head>", $this->documentOutput);
        }
        
        // Insert jscripts & html block into template - template must have a </body> tag
        if ($this->jscripts && $js= $this->getRegisteredClientScripts())
        {
            $this->documentOutput= str_ireplace('</body>', "{$js}\n</body>", $this->documentOutput);
        }
        // End fix by sirlancelot
        
        // remove all unused placeholders
        if (strpos($this->documentOutput, '[+') !==false)
        {
            $matches= array ();
            $matches = $this->getTagsFromContent($this->documentOutput,'[+','+]');
            if ($matches[0])
            $this->documentOutput= str_replace($matches[0], '', $this->documentOutput);
        }
        
        if(strpos($this->documentOutput,'[~')!==false) $this->documentOutput = $this->rewriteUrls($this->documentOutput);
        if(strpos($this->documentOutput,'<!---->')!==false)
            $this->documentOutput = str_replace('<!---->','',$this->documentOutput);
        
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
            $this->documentOutput = preg_replace("@(</body>)@i", $this->queryCode . "\n\\1", $this->documentOutput);
        }
        if ($this->dumpSnippets)
        {
            $this->documentOutput = preg_replace("@(</body>)@i", $this->snipCode . "\n\\1", $this->documentOutput);
        }
        
        // invoke OnLogPageView event
        if ($this->config['track_visitors'] == 1)
        {
            $this->invokeEvent('OnLogPageHit');
        }
        
        // invoke OnWebPagePrerender event
        if (!$noEvent)
            $this->invokeEvent('OnWebPagePrerender');
        
        if(strpos($this->documentOutput,'^]')!==false)
            echo $this->mergeBenchmarkContent($this->documentOutput);
        else
            echo $this->documentOutput;
        
        $echo = ob_get_clean();
        if($this->debug) $this->recDebugInfo();
        return $echo;
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
            $param = array('makeCache' => true);
            // invoke OnBeforeSaveWebPageCache event
            $this->invokeEvent('OnBeforeSaveWebPageCache',$param);
            if( $param['makeCache'] != true )
              return;

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
                    $cacheContent  = '<?php header("HTTP/1.0 404 Not Found");exit; ?>';
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
            if(!is_dir(MODX_BASE_PATH . 'assets/cache')) mkdir(MODX_BASE_PATH . 'assets/cache');
            if(!is_dir("{$base_path}assets/cache/{$this->uaType}"))
                mkdir("{$base_path}assets/cache/{$this->uaType}",0777);
            $page_cache_path = "{$base_path}assets/cache/{$this->uaType}/{$filename}.pageCache.php";
            file_put_contents($page_cache_path, $cacheContent, LOCK_EX);
            $alias_cache_path = "{$base_path}assets/cache/alias.siteCache.idx.php";
            if(count($this->aliasCache)<7000) {
                $aliasCache = serialize($this->aliasCache);
                file_put_contents($alias_cache_path, $aliasCache, LOCK_EX);
            }
        }
        // Useful for example to external page counters/stats packages
        $this->invokeEvent('OnWebPageComplete');
        // end post processing
    }
    
    function sanitize_gpc(& $target, $count=0) {
        if(empty($target)) return;
        $_ = join('',$target);
        if(strpos($_,'[')===false&&strpos($_,'<')===false&&strpos($_,'#')===false) return;
        
        $s = array('[[',']]','[!','!]','[*','*]','[(',')]','{{','}}','[+','+]','[~','~]','[^','^]');
        foreach($s as $_)
        {
            $r[] = " {$_['0']} {$_['1']} ";
        }
        foreach ($target as $key => $value)
        {
            if (is_array($value))
            {
                $count++;
                if(10 < $count)
                {
                    echo 'too many nested array';
                    exit;
                }
                $this->sanitize_gpc($value, $count);
            }
            else
            {
                $value = str_replace($s,$r,$value);
                $value = str_ireplace('<script', 'sanitized_by_modx<s cript', $value);
                $value = preg_replace('/&#(\d+);/', 'sanitized_by_modx& #$1', $value);
                $target[$key] = $value;
            }
            $count=0;
        }
        return $target;
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
    
    function getMicroTime()
    {
        list ($usec, $sec)= explode(' ', microtime());
        return ((float) $usec + (float) $sec);
    }
    
    function get_static_pages($filepath)
    {
        if(strpos($filepath,'?')!==false) $filepath = substr($filepath,0,strpos($filepath,'?'));
        $filepath = substr($filepath,strlen($this->config['base_url']));
        if(substr($filepath,-1)==='/' || empty($filepath)) $filepath .= 'index.html';
        $filepath = $this->config['base_path'] . "temp/public_html/{$filepath}";
        if(is_file($filepath)===false) return false;
        $ext = strtolower(substr($filepath,strrpos($filepath,'.')));
        switch($ext)
        {
            case '.html': case '.htm':
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
            case '.ico': case '.jpg': case '.jpeg': case '.png': case '.gif':
                if($ext==='.ico') $mime_type = 'image/x-icon';
                else              $mime_type = $this->getMimeType($filepath);
            default:
                exit;
        }
        if(!$mime_type) $this->sendErrorPage();
        header("Content-type: {$mime_type}");
        return file_get_contents($filepath);
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
        $this->config = $this->getSiteCache();
        $cache_path = MODX_BASE_PATH . 'assets/cache/';
        if(is_file($cache_path.'siteCache.idx.php')) include_once($cache_path.'siteCache.idx.php');
        
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
        if(!isset($this->config['modx_charset'])||empty($this->config['modx_charset']))
            $this->config['modx_charset'] = 'utf-8';
        
        if(!defined('IN_PARSER_MODE')) $this->setChunkCache();
        
        $this->invokeEvent('OnGetConfig');
        return $this->config;
    }
    
    function getDocumentIdentifier($method)
    {
        // function to test the query and find the retrieval method
        switch ($method)
        {
            case 'alias' :
                return $this->db->escape($_REQUEST['q']);
            case 'id' :
                if (!preg_match('@^[0-9]+$@', $_REQUEST['id']))
                    $this->sendErrorPage();
                else
                    return intval($_REQUEST['id']);
                break;
            default:
                return $this->config['site_start'];
        }
    }
    
    // check for manager login session
    function isLoggedin()
    {
        if(isset($_SESSION['mgrValidated']) && !empty($_SESSION['mgrValidated']))
            return true;
        else return false;
    }

    function checkSession() {
        return $this->isLoggedin();
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
        if($this->config['site_status'] == 1) return true; // site online
        elseif($this->checkSession())         return true; // site offline but launched via the manager
        else                                  return false; // site is offline
    }
    
    function cleanDocumentIdentifier($qOrig)
    {
        if(isset($this->aliasCache[__FUNCTION__]['vdir'][$qOrig]))
            $this->virtualDir = $this->aliasCache[__FUNCTION__]['vdir'][$qOrig];
        if(isset($this->aliasCache[__FUNCTION__]['id'][$qOrig])) {
            $this->documentMethod = 'id';
            return $this->aliasCache[__FUNCTION__]['id'][$qOrig];
        }
        elseif(isset($this->aliasCache[__FUNCTION__]['alias'][$qOrig])) {
            return $this->aliasCache[__FUNCTION__]['alias'][$qOrig];
        }
        
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
        if (!preg_match('@^[1-9][0-9]*$@',$q))
        {
            if ($this->config['friendly_alias_urls'] == 0) $rs = $qOrig;
            else                                           $rs = $q;
        }
        else
        {
            if ($this->config['use_alias_path'] == 1)
            {
                $vdir = $this->virtualDir;
                
                $aliasPath = $vdir!='' ? "{$vdir}/{$q}" : $q;
                $docid = $this->getIdFromAlias($aliasPath);
                
                $pid = $vdir!='' ? $this->getIdFromAlias($vdir) : 0;
                $children = $this->getChildIds($pid, 1);
                $wChild =in_array($q, $children);
                
                if (!$docid && $wChild)
                {
                    $this->documentMethod = 'id';
                    $rs = $q;
                }
                else $rs = $q;
            }
            else
            {
                $id = $this->getIdFromAlias($q);
                if($id!==false) {
                    $this->documentMethod = 'id';
                    $rs = $id;
                }
                else $rs = $q;
            }
        }
        $this->aliasCache[__FUNCTION__]['vdir'][$qOrig] = $this->virtualDir;
        $this->aliasCache[__FUNCTION__][$this->documentMethod][$qOrig] = $rs;
        return $rs;
    }

    function checkCache($id)
    {
        return $this->getCache($id);
    }
    
    function getCache($id)
    {
        if($this->config['cache_type'] == 0) return ''; // jp-edition only
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
        
        if($this->config['cache_type'] == 2 && $this->http_status_code != 404)
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
        
        if(substr($flContent,0,5)==='<?php') list($null,$flContent) = explode('?>', $flContent, 2); // remove php header
        $a = explode('<!--__MODxCacheSpliter__-->', $flContent, 2);
        if(count($a) == 1) return $a[0];
        elseif($this->config['cache_type']!=0 && $this->http_status_code==404) {
            return $a[1];
        }
        
        $docObj = unserialize(trim($a['0'])); // rebuild document object
        // add so - check page security(admin(mgrRole=1) is pass)
        if(!(isset($_SESSION['mgrRole']) && $_SESSION['mgrRole'] == 1) 
            && $docObj['privateweb'] && isset ($docObj['__MODxDocGroups__'])) {
            
            $pass = false;
            $usrGrps = $this->getUserDocGroups();
            $docGrps = explode(',',$docObj['__MODxDocGroups__']);
            // check is user has access to doc groups
            if(is_array($usrGrps)&&!empty($usrGrps)) {
                foreach ($usrGrps as $k => $v) {
                    $v = trim($v);
                    if(in_array($v, $docGrps)) {
                        $pass = true;
                        break;
                    }
                }
            }
            // diplay error pages if user has no access to cached doc
            if(!$pass) {
                if($this->config['unauthorized_page']) {
                    // check if file is not public
                    $rs = $this->db->select('id', '[+prefix+]document_groups', "document='{$id}'",'',1);
                    $total = $this->db->getRecordCount($rs);
                }
                
                if(0 < $total) $this->sendUnauthorizedPage();
                else           $this->sendErrorPage();
            }
        }
        
        // Grab the Scripts
        if(isset($docObj['__MODxSJScripts__'])) $this->sjscripts = $docObj['__MODxSJScripts__'];
        if(isset($docObj['__MODxJScripts__']))  $this->jscripts  = $docObj['__MODxJScripts__'];
        
        $this->documentObject = $docObj;
        return $a['1']; // return document content
    }
    
    function updatePublishStatus()
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

        //下書き採用(今のところリソースのみ)
        $draft_ids = array();
        $rs = $this->db->select('element,elmid','[+prefix+]site_revision', "pub_date<{$timeNow} AND status = 'standby'");
        while( $row = $this->db->getRow($rs) ){
            if( $row['element'] == 'resource' ){
                $draft_ids[] = $row['elmid'];
            }
        }
        if( !empty($draft_ids) ){
            $this->updateDraft();
        }
        
        // now, check for documents that need publishing
        $pub_ids = array();
        $fields = "published='1', publishedon=pub_date";
        $where = "pub_date <= {$timeNow} AND pub_date!=0 AND published=0";
        $rs = $this->db->select('id','[+prefix+]site_content',$where);
        while( $row = $this->db->getRow($rs) ){ $pub_ids[] = $row['id']; }
        if( !empty($pub_ids) ){
            $rs = $this->db->update($fields,'[+prefix+]site_content',$where);
        }
        
        // now, check for documents that need un-publishing
        $unpub_ids = array();
        $fields = "published='0', publishedon='0'";
        $where = "unpub_date <= {$timeNow} AND unpub_date!=0 AND published=1";
        $rs = $this->db->select('id','[+prefix+]site_content',$where);
        while( $row = $this->db->getRow($rs) ){ $unpub_ids[] = $row['id']; }
        if( !empty($unpub_ids) ){
            $rs = $this->db->update($fields,'[+prefix+]site_content',$where);
        }
    
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
        $this->setAliasListing();

        //invoke events
        if( !empty($pub_ids) ){
            $tmp = array('docid'=>$pub_ids,'type'=>'scheduled');
            $this->invokeEvent('OnDocPublished',$tmp);
        }
        if( !empty($draft_ids) ){
            $tmp = array('docid'=>$draft_ids,'type'=>'draftScheduled');
            $this->invokeEvent('OnDocPublished',$tmp);
        }
        if( !empty($unpub_ids) ){
            $tmp = array('docid'=>$unpub_ids,'type'=>'scheduled');
            $this->invokeEvent('OnDocUnPublished',$tmp);
        }
    }
    
    function getTagsFromContent($content,$left='[+',$right='+]') {
        $key = md5("{$content}{$left}{$right}");
        if(isset($this->functionCache['gettagsfromcontent'][$key])) return $this->functionCache['gettagsfromcontent'][$key];
        $_ = $this->_getTagsFromContent($content,$left,$right);
        if(empty($_)) return array();
        foreach($_ as $v)
        {
            $tags[0][] = "{$left}{$v}{$right}";
            $tags[1][] = $v;
        }
        $this->functionCache['gettagsfromcontent'][$key] = $tags;
        return $tags;
    }
    
    function _getTagsFromContent($content, $left='[+',$right='+]') {
        if(strpos($content,$left)===false) return array();
        $spacer = md5('<<<MODX>>>');
        if(strpos($content,';}}')!==false)  $content = str_replace(';}}', ";}{$spacer}}",$content);
        if(strpos($content,'{{}}')!==false) $content = str_replace('{{}}',"{{$spacer}{}{$spacer}}",$content);
        if(strpos($content,']]]]')!==false) $content = str_replace(']]]]',"]]{$spacer}]]",$content);
        if(strpos($content,']]]')!==false)  $content = str_replace(']]]',"]{$spacer}]]",$content);
        
        $pos['<![CDATA[']                 = strpos($content,'<![CDATA[');
        if($pos['<![CDATA[']) $pos[']]>'] = strpos($content,']]>');
        if($pos['<![CDATA[']!==false && $pos[']]>']!==false)
            $content = substr($content,0,$pos['<![CDATA[']) . substr($content,$pos['<![CDATA[']+9,$pos[']]>']) . substr($content,$pos[']]>']+3);
        
        $lp = explode($left,$content);
        $piece = array();
        foreach($lp as $lc=>$lv) {
            if($lc!==0) $piece[] = $left;
            if(strpos($lv,$right)===false) $piece[] = $lv;
            else {
                $rp = explode($right,$lv);
                foreach($rp as $rc=>$rv) {
                    if($rc!==0) $piece[] = $right;
                    $piece[] = $rv;
                }
            }
        }
        $lc=0;
        $rc=0;
        $fetch = '';
        foreach($piece as $v) {
            if($v===$left) {
                if(0<$lc) $fetch .= $left;
                $lc++;
            }
            elseif($v===$right) {
                if($lc===0) continue;
                $rc++;
                if($lc===$rc) {
                    $tags[] = $fetch; // Fetch and reset
                    $fetch = '';
                    $lc=0;
                    $rc=0;
                }
                else $fetch .= $right;
            } else {
                if(0<$lc) $fetch .= $v;
                else continue;
            }
        }
        if(!$tags) return array();
        
        foreach($tags as $tag) {
            if(strpos($tag,$left)!==false) {
//                http://forum.modx.jp/viewtopic.php?f=32&t=1604
//                $pos = strpos($tag,':');
//                if($pos) $tag = substr($tag,0,$pos);
                $innerTags = $this->_getTagsFromContent($tag,$left,$right);
                $tags = array_merge($innerTags,$tags);
            }
        }
        
        foreach($tags as $i=>$tag) {
            if(strpos($tag,"$spacer")!==false) $tags[$i] = str_replace("$spacer", '', $tag);
        }
        return $tags;
    }
    
    function getUltimateParentId($id,$top=0) {
        $i=0;
        while ($id &&$i<20) {
            if($top==$this->aliasListing[$id]['parent']) break;
            $id = $this->aliasListing[$id]['parent'];
            $i++;
        }
        return $id;
    }
    // mod by Raymond
    function mergeDocumentContent($content,$convertValue=true)
    {
        if(!isset($this->documentIdentifier)) return $content;
        if(strpos($content,'[*')===false) return $content;
        if(!isset($this->documentObject) || empty($this->documentObject)) return $content;
        
        if ($this->debug) $fstart = $this->getMicroTime();
        
        $matches = $this->getTagsFromContent($content,'[*','*]');
        if(!$matches) return $content;
        
        foreach($matches[1] as $i=>$key) {
            if(substr($key, 0, 1) == '#') $key = substr($key, 1); // remove # for QuickEdit format
            
            if(strpos($key,':')!==false)
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if(strpos($key,'@')!==false)
            {
                //if(strpos($key,'/u')!==false)
                if(preg_match('/@\d+\/u/',$key))
                    $key = str_replace(array('@','/u'),array('@u(',')'),$key);
                list($key,$str) = explode('@',$key,2);
                $context = strtolower($str);
                if(substr($str,0,5)==='alias' && strpos($str,'(')!==false)
                    $context = 'alias';
                elseif(substr($str,0,1)==='u' && strpos($str,'(')!==false)
                    $context = 'uparent';
                switch($context)
                {
                    case 'site_start':
                        $docid = $this->config['site_start'];
                        break;
                    case 'parent':
                    case 'p':
                        $docid = $this->documentObject['parent'];
                        if($docid==0) $docid = $this->config['site_start'];
                        break;
                    case 'ultimateparent':
                    case 'uparent':
                    case 'up':
                    case 'u':
                        if(strpos($str,'(')!==false) {
                            $top = substr($str,strpos($str,'('));
                            $top = trim($top,'()"\'');
                        }
                        else $top = 0;
                        $docid = $this->getUltimateParentId($this->documentIdentifier,$top);
                        break;
                    case 'alias':
                        $str = substr($str,strpos($str,'('));
                        $str = trim($str,'()"\'');
                        $docid = $this->getIdFromAlias($str);
                        break;
                    default:
                        $docid = $str;
                }
                if(preg_match('@^[1-9][0-9]*$@',$docid))
                    $value = $this->getField($key,$docid);
                else $value = '';
            }
            elseif(!isset($this->documentObject[$key])) $value = '';
            else $value= $this->documentObject[$key];
            
            if (is_array($value)) $value= $this->tvProcessor($value);
            
            if($modifiers!==false)
            {
                $this->loadExtension('MODIFIERS');
                $value = $this->filter->phxFilter($key,$value,$modifiers);
            }
            elseif($convertValue)
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
            $content= str_replace($matches[0][$i], $value, $content);
        }
        
        if ($this->debug)
        {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->'.__FUNCTION__ . "[{$_}]",$fstart);
        }
        return $content;
    }
    
    function addLogEntry($fname,$fstart)
    {
        $tend = $this->getMicroTime();
        $totaltime = $tend - $fstart;
        $msg = sprintf('%s %2.4f s',$fname,$totaltime);
        $this->functionLog[] = $msg;
    }
    
    function mergeSettingsContent($content)
    {
        if(strpos($content,'[(')===false) return $content;
        
        if ($this->debug) $fstart = $this->getMicroTime();
        
        $matches = $this->getTagsFromContent($content,'[(',')]');
        if(!$matches) return $content;
        
        $replace= array ();
        foreach($matches[1] as $i=>$key) {
            if(strpos($key,':')!==false)
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if(isset($this->config[$key]))
            {
                $value = $this->config[$key];
                if($modifiers!==false)
                {
                    $this->loadExtension('MODIFIERS');
                    $value = $this->filter->phxFilter($key,$value,$modifiers);
                }
                
                $replace[$i]= $value;
            }
            else $replace[$i]= $key;
        }
        
        $content= str_replace($matches[0], $replace, $content);
        if ($this->debug)
        {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->'.__FUNCTION__ . "[{$_}]",$fstart);
        }
        return $content;
    }
    
    function mergeChunkContent($content)
    {
        if(strpos($content,'{{')===false) return $content;
        
        if ($this->debug) $fstart = $this->getMicroTime();
        
        $matches = $this->getTagsFromContent($content,'{{','}}');
        if(!$matches) return $content;
        
        $replace= array ();
        foreach($matches[1] as $i=>$key) {
            $snip_call = $this->_split_snip_call($key);
            $key = $snip_call['name'];
            $ph = $this->_snipParamsToArray($snip_call['params']);
            if(strpos($key,':')!==false)
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            $value = $this->getChunk($key);
            $value = $this->parseText($ph,$value,'[+','+]','hasModifier');
            
            if($modifiers!==false)
            {
                $this->loadExtension('MODIFIERS');
                $value = $this->filter->phxFilter($key,$value,$modifiers);
            }
            $replace[$i] = $value;
        }
        
        $content= str_replace($matches[0], $replace, $content);
        if ($this->debug)
        {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->'.__FUNCTION__ . "[{$_}]",$fstart);
        }
        return $content;
    }
    
    // Added by Raymond
    function mergePlaceholderContent($content)
    {
        if(strpos($content,'[+')===false) return $content;
        if(!is_array($this->placeholders)) return $content;
        
        if ($this->debug) $fstart = $this->getMicroTime();
        $content=$this->mergeSettingsContent($content);
        $matches = $this->getTagsFromContent($content,'[+','+]');
        if(!$matches) return $content;
        foreach($matches[1] as $i=>$key) {
            if(strpos($key,':')!==false)
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            
            if (isset($this->placeholders[$key])) $value = $this->placeholders[$key];
            elseif($key==='phx') $value = '';
            else continue;
            
            if($modifiers!==false)
            {
                $this->loadExtension('MODIFIERS');
                $modifiers = $this->mergePlaceholderContent($modifiers);
                $value = $this->filter->phxFilter($key,$value,$modifiers);
            }
            $content= str_replace($matches[0][$i], $value, $content);
        }
        if ($this->debug)
        {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->'.__FUNCTION__ . "[{$_}]",$fstart);
        }
        return $content;
    }
    
    function mergeCommentedTagsContent($content, $left='<!--@MODX:', $right='-->')
    {
        if(strpos($content,$left)===false) return $content;
        
        if ($this->debug) $fstart = $this->getMicroTime();
        
        $matches = $this->getTagsFromContent($content,$left,$right);
        if(!empty($matches))
        {
            foreach($matches[1] as $i=>$v)
            {
                $matches[1][$i] = $this->parseDocumentSource($v);
            }
            $content = str_replace($matches[0],$matches[1],$content);
        }
        if ($this->debug) $this->addLogEntry('$modx->'.__FUNCTION__,$fstart);
        return $content;
    }
    
    function ignoreCommentedTagsContent($content, $left='<!--@IGNORE:BEGIN-->', $right='<!--@IGNORE:END-->')
    {
        if(strpos($content,$left)===false) return $content;
        $matches = $this->getTagsFromContent($content,$left,$right);
        if(!empty($matches))
        {
            foreach($matches[0] as $i=>$v)
            {
                $addBreakMatches[$i] = $v."\n";
            }
            $content = str_replace($addBreakMatches,'',$content);
            if(strpos($content,$left)!==false)
                $content = str_replace($matches[0],'',$content);
        }
        return $content;
    }
    
    function mergeConditionalTagsContent($content, $left='<!--@IF:', $right='<@ENDIF-->')
    {
        if ($this->debug) $fstart = $this->getMicroTime();
        
        if(strpos($content,'<!--@IF ')!==false) $content = str_replace('<!--@IF ',$left,$content);
        if(strpos($content,$left)===false) return $content;
        if(strpos($content,'<!--@ELSE-->')!==false)  $content = str_replace('<!--@ELSE-->','<@ELSE>',$content);
        if(strpos($content,'<!--@ENDIF-->')!==false) $content = str_replace('<!--@ENDIF-->','<@ENDIF-->',$content);
        $matches = $this->getTagsFromContent($content,$left,$right);
        if(!empty($matches))
        {
            foreach($matches[0] as $i=>$v)
            {
                $cmd = substr($v,8,strpos($v,'>')-8);
                $cmd = trim($cmd);
                if(substr($cmd,-2)==='--') $cmd = substr($cmd,0,-2);
                $cond = substr($cmd,0,1)!=='!' ? true : false;
                if($cond===false) $cmd = ltrim($cmd,'!');
                if(strpos($cmd,'[!')!==false)
                    $cmd = str_replace(array('[!','!]'),array('[[',']]'),$cmd);
                $cmd = $this->parseDocumentSource($cmd);
                $cmd = trim($cmd);
                if(strpos($matches[1][$i],'<@ELSE>')!==false) {
                    list($if_content,$else_content) = explode('<@ELSE>',$matches[1][$i]);
                } else {
                    $if_content = $matches[1][$i];
                    $else_content = '';
                }
                if(preg_match('@^[0-9<= \-\+\*/\(\)%!]*$@', $cmd)) $cmd = (int) eval("return {$cmd};");
                if( ($cond===true && empty($cmd)) || ($cond===false && !empty($cmd)) )
                    $matches[1][$i] = trim($else_content);
                else
                    $matches[1][$i] = substr($if_content,strpos($if_content,'>')+1);
            }
            foreach($matches[0] as $i=>$v)
            {
                $addBreakMatches[$i] = $v."\n";
            }
            $content = str_replace($addBreakMatches,$matches[1],$content);
            if(strpos($content,$left)!==false)
                $content = str_replace($matches[0],$matches[1],$content);
        }
        if ($this->debug) $this->addLogEntry('$modx->'.__FUNCTION__,$fstart);
        return $content;
    }
    
    function mergeBenchmarkContent($content)
    {
        if(strpos($content,'^]')===false) return $content;
        
        if ($this->debug) $fstart = $this->getMicroTime();
        
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
        
        if ($this->debug) $this->addLogEntry('$modx->'.__FUNCTION__,$fstart);
        
        return $content;
    }
    
    // evalPlugin
    function evalPlugin($pluginCode, $params)
    {
        $modx= & $this;
        $modx->event->params = $params; // store params inside event object
        if (is_array($params))
        {
            extract($params, EXTR_SKIP);
            $modx->event->cm->setParams($params);
        }
        ob_start();
        $return = eval($pluginCode);
        unset ($modx->event->params);
        $echo = ob_get_contents();
        ob_end_clean();
        if ($echo && isset ($php_errormsg))
        {
            $error_info = error_get_last();
            if($error_info['type']===2048 || $error_info['type']===8192) $error_type = 2;
            else                                                         $error_type = 3;
            if(1<$this->config['error_reporting'] || 2<$error_type)
            {
                extract($error_info);
                if($echo===false) $echo = 'ob_get_contents() error';
                $result = $this->messageQuit('PHP Parse Error', '', true, $type, $file, 'Plugin', $text, $line, $echo);
                if ($this->isBackend())
                {
                    $this->event->alert("An error occurred while loading. Please see the event log for more information.<p>{$echo}</p>");
                }
            }
        }
        else
        {
            return $echo . $return;
        }
    }
    
    function evalSnippet($phpcode, $params)
    {
        $modx= & $this;
        if ($this->debug) $fstart = $this->getMicroTime();
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
        $return= eval($phpcode);
        $echo= ob_get_contents();
        ob_end_clean();
        
        if ((0<$this->config['error_reporting']) && $echo && isset($php_errormsg))
        {
            $error_info = error_get_last();
            if($error_info['type']===2048 || $error_info['type']===8192) $error_type = 2;
            else                                                         $error_type = 3;
            if(1<$this->config['error_reporting'] || 2<$error_type)
            {
                extract($error_info);
                $result = $this->messageQuit('PHP Parse Error', '', true, $type, $file, 'Snippet', $text, $line, $echo);
                if ($this->isBackend())
                {
                    $this->event->alert("An error occurred while loading. Please see the event log for more information<p>{$echo}</p>");
                }
            }
        }
        unset ($modx->event->params);
        if ($this->debug) $this->addLogEntry($this->currentSnippetCall,$fstart);
        $this->currentSnippet = '';
        return $echo . $return;
    }
    
    function evalSnippets($content,$nest=0)
    {
        if(strpos($content,'[[')===false) return $content;
        
        if(!$this->snippetCache) $this->setSnippetCache();
        $matches = $this->getTagsFromContent($content,'[[',']]');
        if(!$matches) return $content;
        $this->snipLapCount++;
        if ($this->dumpSnippets)
            $this->snipCode .= '<fieldset style="margin-bottom:1em;"><legend><b style="color: #821517;">PARSE LAP ' . ($this->snipLapCount) . '</b></legend><div style="width:100%;text-align:left;">';
        
        $replace= array ();
        foreach($matches[1] as $i=>$value) {
            if(substr($value,0,2)==='$_') {
                $replace[$i] = $this->_getSGVar($value);
                continue;
            }
            foreach($matches[0] as $find=>$tag) {
                if(isset($replace[$find]) && strpos($value,$tag)!==false) {
                    $value = str_replace($tag,$replace[$find],$value);
                    break;
                }
            }
            $replace[$i] = $this->_get_snip_result($value);
        }
        
        if($this->dumpSnippets) $this->snipCode .= '</div></fieldset>';
        
        $content = str_replace($matches[0], $replace, $content);
        return $content;
    }
    
    function _getSGVar($value) { // Get super globals
        $key = $value;
        if(strpos($key,':')!==false)
            list($key,$modifiers) = explode(':', $key, 2);
        else $modifiers = false;
        $key = str_replace(array('(',')'),array("['","']"),$key);
        if(strpos($key,'$_SESSION')!==false)
        {
            $_ = $_SESSION;
            $key = str_replace('$_SESSION','$_',$key);
            if(isset($_['mgrFormValues'])) unset($_['mgrFormValues']);
            if(isset($_['token'])) unset($_['token']);
        }
        if(strpos($key,'[')!==false)
            $value = eval("return {$key};");
        elseif(0<eval("return count({$key});"))
            $value = eval("return print_r({$key},true);");
        else $value = '';
        if($modifiers!==false)
        {
            $this->loadExtension('MODIFIERS');
            $value = $this->filter->phxFilter($key,$value,$modifiers);
        }
        return $value;
    }
    private function _get_snip_result($piece)
    {
        $snip_call = $this->_split_snip_call($piece);
        $snip_name = $snip_call['name'];
        
        if(strpos($snip_name,':')!==false)
        {
            list($snip_name,$modifiers) = explode(':', $snip_name, 2);
            $snip_call['name'] = $snip_name;
        }
        else $modifiers = false;
        
        $snippetObject = $this->_getSnippetObject($snip_call['name']);
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
            $this->loadExtension('MODIFIERS');
            $value = $this->filter->phxFilter($snip_name,$value,$modifiers);
        }
        
        if($this->dumpSnippets)
        {
            if($value) {
                if(150<strlen($value)) $height = 200;
                else                   $height = 50;
                $code = sprintf('<textarea style="width:90%%;height:%spx">%s</textarea>', $height, htmlspecialchars($value,ENT_NOQUOTES,$this->config['modx_charset']));
            }
            else $code = 'Empty';
            $this->snipCode .= sprintf('<fieldset style="margin-bottom:1em;"><legend><b>Output of %s</b></legend>%s</fieldset>', $snippetObject['name'], $code);
        }
        return $value;
    }
    
    function _snipParamsToArray($string='')
    {
        if(empty($string)) return array();
        
        $_tmp = $string;
        $_tmp = ltrim($_tmp, '?&');
        $temp_params = array();
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
                if(trim($key)!=='') $value = '';
                else continue;
            }
            else
                if($key!==''||trim($char)!=='') $key .= $char;
            
            if(!is_null($value))
            {
                if(strpos($key,'amp;')!==false) $key = str_replace('amp;', '', $key);
                $key=trim($key);
                if(substr($value,0,6)!=='@CODE:')
                {
                    if(strpos($value,'[*')!==false) $value = $this->mergeDocumentContent($value);
                    if(strpos($value,'[(')!==false) $value = $this->mergeSettingsContent($value);
                    if(strpos($value,'{{')!==false) $value = $this->mergeChunkContent($value);
                    if(strpos($value,'[[')!==false) $value = $this->evalSnippets($value);
                    if(strpos($value,'[+')!==false) $value = $this->mergePlaceholderContent($value);
                }
                $temp_params[][$key]=$value;
                
                $key   = '';
                $value = null;

                $_tmp = ltrim($_tmp, " ,\t");
                if(substr($_tmp, 0, 2)==='//') $_tmp = strstr($_tmp, "\n");
            }
            
            if($_tmp===$bt)
            {
                $key = trim($key);
                if($key!=='') $temp_params[][$key] = '';
                break;
            }
        endwhile;
        // スニペットコールのパラメータを配列にも対応
        foreach($temp_params as $p)
        {
            $k = key($p);
            if(substr($k,-2)==='[]')
            {
                $k = substr($k,0,-2);
                $params[$k][] = current($p);
            }
            elseif(strpos($k,'[')!==false && substr($k,-1)===']')
            {
                list($k, $subk) = explode('[', $k, 2);
                $subk = substr($subk,0,-1);
                $params[$k][$subk] = current($p);
            }
            else
                $params[$k] = current($p);
        }
        return $params;
    }
    
    function _findSplitter($str) {
        $str = str_split($str);
        $pass = false;
        $i = -1;
        $pos = false;
        $inFilter = false;
        foreach($str as $c) {
            $i++;
            if($inFilter)    {
                if($c===')')     { $pass = false; continue; }
                elseif($pass)    { continue; }
                elseif($c==='(') { $pass=true; continue; }
                elseif($c==='?') { $pos=$i; break; }
            }
            elseif($c===':')     { $inFilter=true; }
            elseif($c==='?')     { $pos = $i; break; }
        }
        return $pos;
    }
    
    private function _split_snip_call($call)
    {
        $spacer = md5('dummy');
        if(strpos($call,']]>')!==false)
            $call = str_replace(']]>', "]{$spacer}]>",$call);
        
        $pos['?']  = $this->_findSplitter($call);
        $pos['&']  = strpos($call, '&');
        $pos['=']  = strpos($call, '=');
        $pos['lf'] = strpos($call, "\n");
        
        if($pos['?'] !== false)
        {
            if($pos['lf']!==false && $pos['?'] < $pos['lf'])
                list($name,$params) = explode('?',$call,2);
            elseif($pos['lf']!==false && $pos['lf'] < $pos['?'])
                list($name,$params) = explode("\n",$call,2);
            else {
                $name   = substr($call, 0, $pos['?']);
                $params = substr($call, $pos['?']+1);
            }
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
    
    private function _getSnippetObject($snip_name)
    {
        if(isset($this->snippetCache[$snip_name]))
        {
            $snippetObject['name']    = $snip_name;
            $snippetObject['content'] = $this->snippetCache[$snip_name];
            if(isset($this->snippetCache["{$snip_name}Props"]))
            {
                $snippetObject['properties'] = $this->snippetCache["{$snip_name}Props"];
            }
        }
        else
        {
            $where = sprintf("name='%s'",$this->db->escape($snip_name));
            $rs= $this->db->select('name,snippet,properties','[+prefix+]site_snippets',$where);
            $count = $this->db->getRecordCount($rs);
            if(1<$count) exit('Error $modx->_getSnippetObject()'.$snip_name);
            if($count)
            {
                $row = $this->db->getRow($rs);
                $snip_content = $row['snippet'];
                $snip_prop    = $row['properties'];
            }
            else
            {
                $snip_content = 'return false;';
                $snip_prop    = '';
            }
            $snippetObject['name']       = $snip_name;
            $snippetObject['content']    = $snip_content;
            $snippetObject['properties'] = $snip_prop;
            $this->snippetCache[$snip_name]          = $snip_content;
            $this->snippetCache["{$snip_name}Props"] = $snip_prop;
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
    
    function getPluginCache()
    {
        $plugins = @include_once(MODX_BASE_PATH . 'assets/cache/plugin.siteCache.idx.php');
        if($plugins) $this->pluginCache = $plugins;
        else return false;
    }
    
    function setdocumentMap()
    {
        $fields = 'id, parent';
        $rs = $this->db->select($fields,'[+prefix+]site_content','deleted=0','parent, menuindex');
        $this->documentMap = array();
        while ($row = $this->db->getRow($rs))
        {
            $docid  = $row['id'];
            $parent = $row['parent'];
            $this->documentMap[] = array($row['parent'] => $row['id']);
        }
    }
    
    function setAliasListing()
    {
        if($this->aliasListing) return;
        $aliases = @include(MODX_BASE_PATH . 'assets/cache/aliasListing.siteCache.idx.php');
        if($aliases) $this->aliasListing = $aliases;
        else return false;
    }
    
    /**
    * name: getDocumentObject  - used by parser
    * desc: returns a document object - $method: alias, id
    */
    function getDocumentObject($method='id', $identifier='', $mode='direct')
    {
        if($method === 'alias')
        {
            $identifier = $this->getIdFromAlias($identifier);
            if($identifier===false) return false;
            $method = 'id';
        }
        
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
            $identifier = $previewObject['id'];
            $this->documentIdentifier = $identifier;
        }
        elseif(isset($_GET['revision']))
        {
            if(!isset($_SESSION['mgrValidated']))
            {
                $_SESSION['save_uri'] = $_SERVER['REQUEST_URI'];
                header('location:'.MODX_MANAGER_URL);
                exit;
            }

            $this->loadExtension('REVISION');
            if( empty($this->previewObject) ){
                $previewObject = $this->revision->getDraft($identifier);
                //tvのkeyをtv名に変更
                $tmp=array();
                foreach( $previewObject as $k => $v ){
                    if( preg_match('/^tv([0-9]+)$/',$k,$mt) ){
                        $row = $this->db->getRow($this->db->select('name', '[+prefix+]site_tmplvars', "id='{$mt[1]}'"));
                        $k = $row['name'];
                    }
                    $tmp[$k] = $v;
                }
                $previewObject = $tmp;
                $this->previewObject = $previewObject;
            }else{
                $previewObject = $this->previewObject;
            }
            $this->config['cache_type'] = 0;
        }
        else $previewObject = false;
        
        // get document groups for current user
        if ($docgrp= $this->getUserDocGroups()) $docgrp= implode(',', $docgrp);
        
        // get document (add so)
        $_ = array();
        if($this->isFrontend())         $_[] = 'sc.privateweb=0';
        else                            $_[] = 'sc.privatemgr=0';
        if($docgrp)                     $_[] = sprintf('dg.document_group IN (%s)', $docgrp);
        if(isset($_SESSION['mgrRole'])) $_[] = sprintf("1='%s'", $_SESSION['mgrRole']);
        $access = join(' OR ', $_);
        
        $from = "[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id";
        $where ="sc.id='{$identifier}' AND ($access)";
        $result= $this->db->select('sc.*',$from,$where,'',1);
        if ($this->db->getRecordCount($result) < 1)
        {
            if ($this->isBackend()||$mode==='direct') return false;
            
            // check if file is not public
            $rs = $this->db->select('id','[+prefix+]document_groups',"document='{$identifier}'",'',1);
            $total= $this->db->getRecordCount($rs);
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
        if( isset($previewObject['template']) )
            $tmp = $previewObject['template'];
        else
            $tmp = $documentObject['template'];
        $where = "tvtpl.templateid = '{$tmp}'";

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
                if(!is_array($documentObject[$k]))
                {
                    if( $snapObject[$k] !=  $documentObject[$k] ) continue; // Priority is higher changing on OnLoadDocumentObject event.
                    $documentObject[$k] = $previewObject[$k];
                }
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
            if ($i == ($passes -1)) $bt= md5($source);
            
            // invoke OnParseDocument event
            $this->documentOutput= $source; // store source code so plugins can
            $this->invokeEvent('OnParseDocument'); // work on it via $modx->documentOutput
            $source= $this->documentOutput;
            
            if(strpos($source,'<!--@IF')!==false)              $source= $this->mergeConditionalTagsContent($source);
            if(strpos($source,'<!--@IGNORE:BEGIN-->')!==false) $source= $this->ignoreCommentedTagsContent($source);
            if(strpos($source,'<!--@IGNORE-->')!==false)       $source= $this->ignoreCommentedTagsContent($source,'<!--@IGNORE-->','<!--@ENDIGNORE-->');
            if(strpos($source,'<!--@MODX:')!==false)           $source= $this->mergeCommentedTagsContent($source);
            
            if(strpos($source,'[+@')!==false)                  $source= $this->mergeInlineFilter($source);
            // combine template and document variables
            if(strpos($source,'[*')!==false)                   $source= $this->mergeDocumentContent($source);
            // replace settings referenced in document
            if(strpos($source,'[(')!==false)                   $source= $this->mergeSettingsContent($source);
            // replace HTMLSnippets in document
            if(strpos($source,'{{')!==false)                   $source= $this->mergeChunkContent($source);
            // find and merge snippets
            if(strpos($source,'[[')!==false)                   $source= $this->evalSnippets($source);
            // find and replace Placeholders (must be parsed last) - Added by Raymond
            if(strpos($source,'[+')!==false
             &&strpos($source,'[[')===false)                   $source= $this->mergePlaceholderContent($source);
            
            if ($i == ($passes -1) && $i < ($this->maxParserPasses - 1))
            {
                // check if source length was changed
                if ($bt != md5($source))
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
        $args = print_r(func_get_args(),true);
        $cacheKey = md5($args);
        if(isset($this->functionCache['getchildids'][$cacheKey])) return $this->functionCache['getchildids'][$cacheKey];
        
        // Initialise a static array to index parents->children
        if(empty($this->childrenList))
            $childrenList = $this->set_childrenList();
        else
            $childrenList = $this->childrenList;
        
        // Get all the children for this parent node
        if (isset($childrenList[$id]))
        {
            $depth--;
            
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
        $this->functionCache['getchildids'][$cacheKey] = $children;
        return $children;
    }

    # Returns true if user has the currect permission
    function hasPermission($pm) {
        
        if (isset($_SESSION['mgrPermissions']) && !empty($_SESSION['mgrPermissions']))
            $state= ($_SESSION['mgrPermissions'][$pm] == 1);
        else $state= false;
        
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
        if (count($ids) == 0 || empty($ids)) return false;
        
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

        $tmp = isset($_SESSION['mgrRole']) ? $_SESSION['mgrRole'] : '';
        $where = "(sc.id IN ({$ids_str}) {$where_published} AND sc.deleted={$deleted} {$where}) AND (sc.private{$context}=0 {$cond} OR 1='{$tmp}') GROUP BY sc.id";
        $orderby = ($sort) ? "{$sort} {$dir}" : '';
        $result= $this->db->select($fields,$from,$where,$orderby,$limit);
        $resourceArray= array ();
        while ($row = $this->db->getRow($result))
        {
            $resourceArray[] = $row;
        }
        return $resourceArray;
    }

    function getDocument($id= 0, $fields= '*', $published= 1, $deleted= 0)
    {
        if ($id == 0) return false;
        
        $tmpArr[]= $id;
        $docs= $this->getDocuments($tmpArr, $published, $deleted, $fields, '', '', '', 1);
        
        if ($docs != false) return $docs['0'];
        else                return false;
    }

    function getField($field='content', $docid='')
    {
        if(empty($docid) && isset($this->documentIdentifier))
            $docid = $this->documentIdentifier;
        elseif(!preg_match('@^[0-9]+$@',$docid))
            $docid = $this->getIdFromAlias($docid);
        
        if(empty($docid)) return false;
        $webInternalKey = isset($_SESSION['webInternalKey']) ? $_SESSION['webInternalKey'] : '0';
        $cacheKey = "[{$field}-{$docid}-{$webInternalKey}]";
        if(isset($this->functionCache['getfield'][$cacheKey]))
            return $this->functionCache['getfield'][$cacheKey];
        
        $doc = $this->getDocumentObject('id', $docid);
        if(is_array($doc[$field]))
        {
            $tvs= $this->getTemplateVarOutput($field, $docid,null);
            $this->functionCache['getfield'][$cacheKey] = $tvs[$field];
            return $tvs[$field];
        }
        $this->functionCache['getfield'][$cacheKey] = $doc[$field];
        return $doc[$field];
    }
    
    function getPageInfo($docid= 0, $activeOnly= 1, $fields= 'id, pagetitle, description, alias')
    {
        if($docid === 0 || !preg_match('/^[0-9]+$/',$docid)) return false;
        
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

    function getParent($pid= -1, $activeOnly= 1, $fields= 'id, pagetitle, description, alias, parent')
    {
        if ($pid == -1)
        {
            $pid= $this->documentObject['parent'];
            return ($pid == 0) ? false : $this->getPageInfo($pid, $activeOnly, $fields);
        }
        elseif ($pid == 0) return false;
        
        // first get the child document
        $child= $this->getPageInfo($pid, $activeOnly, "parent");
        
        // now return the child's parent
        $pid= ($child['parent']) ? $child['parent'] : 0;
        
        return ($pid == 0) ? false : $this->getPageInfo($pid, $activeOnly, $fields);
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
    
    function makeUrl($id='', $alias= '', $args= '', $scheme= 'full', $ignoreReference=false)
    {
        if($id==0) $id = $this->config['site_start'];
        elseif($id=='') $id = $this->documentIdentifier;
        $cacheKey = "[{$id}-{$alias}-{$args}-{$scheme}]";
        if(isset($this->functionCache['makeurl'][$cacheKey])) return $this->functionCache['makeurl'][$cacheKey];
        $makeurl= '';
        $f_url_prefix = $this->config['friendly_url_prefix'];
        $f_url_suffix = $this->config['friendly_url_suffix'];
        if (!preg_match('@^[0-9]+$@',$id))
        {
            $this->messageQuit("'{$id}' is not numeric and may not be passed to makeUrl()");
        }
        
        if(!isset($this->referenceListing)) $this->_getReferenceListing();

        $type='document';
        $orgId=0;
        if(isset($this->referenceListing[$id]) && !$ignoreReference)
        {
            $type='reference';
            if(preg_match('/^[0-9]+$/',$this->referenceListing[$id]))
            {
                $orgId=$id;
                $id = $this->referenceListing[$id];
            }
            else {
                $this->functionCache['makeurl'][$cacheKey] = $this->referenceListing[$id];
                return $this->referenceListing[$id];
            }
        }

        if ($this->config['friendly_urls'] == 1)
        {
            $alPath = '';
            if(empty($alias))
            {
                $al= $this->aliasListing[$id];
                $alias = $id;
                if ($this->config['friendly_alias_urls'] == 1)
                {
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
        $params = array();
        $params['id']          = $id;
        $params['alias']       = $alias;
        $params['args']        = $args;
        $params['scheme']      = $scheme;
        $params['url']         = & $url;
        $params['type']        = $type; // document or reference
        $params['orgId']       = $orgId;
        $this->event->vars = array();
        $this->event->vars = $params;
        $rs = $this->invokeEvent('OnMakeUrl',$params);
        $this->event->vars = array();
        if (!empty($rs))
        {
            $url = end($rs);
        }
        if( $url != $params['url'] )
          $url = $params['url'];
        
        $this->functionCache['makeurl'][$cacheKey] = $url;
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
        
        foreach($matches[1] as $i=>$key)
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
        }
        $content = str_replace($matches[0], $replace, $content);
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
        if($key==='') return false;
        $onCache = true;

        if( isset($this->chunkCache[$key]) ){
            $isCache = true;
            $value = $this->chunkCache[$key];
        }else{
            $isCache = false;
            $where = "`name`='%s' AND (`published`='1' OR 
                                       (`pub_date` <> 0 AND `pub_date` < %d AND ( `unpub_date` = 0 OR `unpub_date` > %d) )
                                      )";
            $where = sprintf($where,  $this->db->escape($key), $this->baseTime,$this->baseTime);
            $rs    = $this->db->select('snippet,published','[+prefix+]site_htmlsnippets',$where);
            if ($this->db->getRecordCount($rs)==1){
                $row= $this->db->getRow($rs);
                $value = $row['snippet'];
                if( $row['published'] == 0 ){ //publishedが0じゃないものはキャッシュしない
                    $onCache = false;
                }
            }
            else $value = '';

            if( $onCache )
                $this->chunkCache[$key] = $value;

        }

        $params = array('name' => $key ,'value' => $value , 'isCache' => $isCache);
        $this->invokeEvent('OnCallChunk',$params);

        return $params['value'];

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
        if(is_array($content)&&is_string($ph)) {list($ph,$content) = array($content,$ph);}
        
        if(!$ph) return $content;
        if ($this->debug) $fstart = $this->getMicroTime();
        if(strpos($content,$left)===false) return $content;
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
        $c=0;
        $bt='';
        while($bt!==md5($content)) {
            $matches = $this->getTagsFromContent($content,$left,$right);
            if(!$matches) break;
            $bt = md5($content);
            $replace= array ();
            foreach($matches[1] as $i=>$key) {
                if(strpos($key,':')!==false) list($key,$modifiers) = explode(':', $key, 2);
                else                         $modifiers = false;
                
                if($cleanup=='hasModifier' && !isset($ph[$key])) $ph[$key] = '';
                
                if(isset($ph[$key])) {
                    $value = $ph[$key];
                    if($modifiers!==false) {
                        $this->loadExtension('MODIFIERS');
                        $value = $this->filter->phxFilter($key,$value,$modifiers);
                    }
                    $replace[$i]= $value;
                }
                elseif($cleanup) $replace[$i] = '';
                else             $replace[$i] = $matches[0][$i];
            }
            $content= str_replace($matches[0], $replace, $content);
            if($bt===md5($content)) break;
            $c++;
            $cleanup = false;
            if(1000<$c) $this->messageQuit('parseText parse over');
        }
        if ($this->debug)
        {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->'.__FUNCTION__ . "[{$_}]",$fstart);
        }
        return $content;
    }
    
    function toDateFormat($timestamp = 0, $mode = '')
    {
        if($timestamp==0&&$mode==='') return '';
        
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
        if ($docid == ''){
            $docid = $this->documentIdentifier;
            $resource= $this->documentObject;
        }else{
            if( $docid == $this->documentIdentifier && !empty($this->previewObject['template']) ) //Ignore published when the preview.
                $resource= $this->getDocument($docid, '*',null);
            else
                $resource= $this->getDocument($docid, '*', $published);
            if (!$resource) return false;
        };

            $template = $resource['template'];
            if( $docid == $this->documentIdentifier && !empty($this->previewObject['template']) ) //Load preview
                $template = $this->previewObject['template'];

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
            $where  = "{$where} AND tvtpl.templateid={$template}";
            
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
                    if( !empty($this->previewObject[$row['name']]) && $docid == $this->documentIdentifier ) //Load preview
                        $row['value'] = $this->previewObject[$row['name']];

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
        return $this->db->getFullTableName($tbl);
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
        if ($context && isset($_SESSION["{$context}Validated"]))
            return $_SESSION["{$context}InternalKey"];
        
        if ($this->isFrontend() && isset ($_SESSION['webValidated']))
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
    function invokeEvent($evtName, &$extParams= array ())
    {
        if (!empty($this->safeMode))               return false;
        if (!$evtName)                             return false;
        if (!isset ($this->pluginEvent[$evtName])) return false;
        
        if(count($this->pluginEvent[$evtName])==0) return array();
        
        if(!$this->pluginCache) $this->getPluginCache();

        $preEventName = $this->event->name;
        $this->event->name= $evtName;
        $results= array ();
        foreach($this->pluginEvent[$evtName] as $pluginName)
        {
            $pluginName = stripslashes($pluginName);
            
            // reset event object
            $this->event->_resetEventObject();
            $preCm = $this->event->cm;
            $this->event->cm = $this->loadExtension('ConfigMediation');
            
            // get plugin code and properties
            $pluginCode       = $this->getPluginCode($pluginName);
            $pluginProperties = $this->getPluginProperties($pluginName);
            
            // load default params/properties
            $parameter = $this->parseProperties($pluginProperties);
            if (!empty($extParams)) $parameter= array_merge($parameter, $extParams);

            // eval plugin
            $this->event->activePlugin= $pluginName;
            $output = $this->evalPlugin($pluginCode, $parameter);
            if($output) $this->event->cm->addOutput($output);
            $this->event->activePlugin= '';
            
            $this->event->setAllGlobalVariables();
            if ($this->event->_output != '') $results[]=$this->event->_output; /* deprecation */
            if ($this->event->cm->hasOutput) $results[]=$this->event->cm->showOutput();
            foreach ( $extParams as $key => $val)
            {
              $tmp = $this->event->cm->getParam($key);
              if( $val != $tmp )
                $extParams[$key] = $tmp;
            }
            $cm = $this->event->cm;
            unset($cm);
            $this->event->cm = $preCm;
            if ($this->event->_propagate != true) break;
        }
        $this->event->name = $preEventName;
        return $results;
    }
    
    function getPluginCode($pluginName)
    {
        if(!isset ($this->pluginCache[$pluginName]))
            $this->setPluginCache($pluginName);
        return $this->pluginCache[$pluginName];
    }
    
    function getPluginProperties($pluginName)
    {
        if(!isset ($this->pluginCache["{$pluginName}Props"]))
            $this->setPluginCache($pluginName);
        return $this->pluginCache["{$pluginName}Props"];
    }
    
    function setPluginCache($pluginName)
    {
        if(isset($this->pluginCache[$pluginName])) {
            $this->pluginCache["{$pluginName}Props"] = '';
            return;
        }
        $result= $this->db->select('*','[+prefix+]site_plugins', "`name`='{$pluginName}' AND disabled=0");
        if ($this->db->getRecordCount($result) == 1)
        {
            $row = $this->db->getRow($result);
            $code       = $row['plugincode'];
            $properties = $row['properties'];
        }
        else
        {
            $code       = 'return false;';
            $properties = '';
        }
        $this->pluginCache[$pluginName]          = $code;
        $this->pluginCache["{$pluginName}Props"] = $properties;
    }
    
    # parses a resource property string and returns the result as an array
    function parseProperties($propertyString)
    {
        if (empty($propertyString)) return array();
        
        $parameter= array ();
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
                    if(substr($v, 0, 5) === '<?php') $v = "@@EVAL\n".substr($v,6);
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
                if($this->db->isResult($value)) $value = $this->parseInput($value);
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
    function changePassword($o, $n) {return $this->changeWebUserPassword($o, $n);}
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
                case E_USER_NOTICE :
                    if($this->error_reporting <= 2) return true;
                    break;
                case E_STRICT:
                case E_DEPRECATED:
                case E_USER_DEPRECATED:
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
        $params = array ('alias'=>&$alias,'browserID'=>$browserID);
        $this->event->vars = $params;
        $_ = $alias;
        $results = $this->invokeEvent('OnStripAlias', $params);
        $this->event->vars = array();
        if($alias!==$_) $this->event->output($alias);
        
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
    
    function getIdFromAlias($aliasPath)
    {
        if(isset($this->aliasCache[__FUNCTION__][$aliasPath]))
            return $this->aliasCache[__FUNCTION__][$aliasPath];
        
        $children = array();
        
        if($this->config['use_alias_path']==1)
        {
            if(strpos($aliasPath,'/')!==false) $_a = explode('/', $aliasPath);
            else                               $_a[] = $aliasPath;
            $id= 0;
            
            foreach($_a as $alias)
            {
                if($id===false) break;
                if( empty($alias) ){ continue; }
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
            $alias = $this->db->escape($aliasPath);
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
        $this->aliasCache[__FUNCTION__][$aliasPath] = $id;
        return $id;
    }

    /*
     * 基準時間の設定
     *
     * 引数がない場合は現在の時間を設定。
     * 次の条件を満たす場合 $_REQUEST['baseTime'] が利用される。
     *
     * ・引数がない
     * ・ログイン状態
     * ・$_REQUEST['baseTime'] が存在する
     *
     * @param $t 時間(Unixtime or 日付フォーマット)
     * @return bool
     *
     */
    function setBaseTime($t=''){
        if( empty($t) ){
            if( !empty($_REQUEST['baseTime']) && $this->isLoggedin() ){
                $t=$_REQUEST['baseTime'];
            }else{
                $this->baseTime = time();
                return true;
            }
        }
        if( $this->isInt($t,1) ){
            $this->baseTime = $t;
        }else{
            $tmp = $this->toTimeStamp($t);
            if( empty($tmp) )
                return false;
            $this->baseTime = $tmp;
        }
        return true;
    }
    /*
     * 基準時間の取得
     *
     * @param none
     * @return int
     *
     */
    function getBaseTime(){
        return $this->baseTime;
    }

    //内部サポート用Class
    //※APIとしては提供しない
    //※量が増えたり使い勝手が悪かったら別Class等にするかも
    /*
     * 数値確認
     *
     * @param $param 入力値
     * @param $min   最小値(default:null)
     * @param $max   最大値(default:null)
     * @return bool
     *
     */
    private static function isInt($param,$min=null,$max=null){
        if( !preg_match('/\A[0-9]+\z/', $param) ){
            return false;
        }
        if( !is_null($min) && preg_match('/\A[0-9]+\z/', $min) && $param < $min ){
            return false;
        }
        if( !is_null($max) && preg_match('/\A[0-9]+\z/', $max) && $param > $max ){
            return false;
        }
        return true;
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
    var $params = array();
    var $vars = array();
    var $cm = null;

    function __construct($name= '') {
        $this->_resetEventObject();
        $this->name= $name;
        $this->activePlugin = '';
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
      if( is_object($this->cm) )
      {
        $this->cm->addOutput($msg);
      }
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
        $this->_output= '';
        $this->_globalVariables=array();
        $this->_propagate= true;
        $this->activated= false;
    }
}
