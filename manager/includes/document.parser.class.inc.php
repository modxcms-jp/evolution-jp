<?php
/**
 * MODX Document Parser
 * Function: This class contains the main document parsing functions
 *
 */

require_once(__DIR__ . '/initialize.inc.php');
if (is_file(MODX_BASE_PATH . 'assets/helpers.php')) {
    require_once(MODX_BASE_PATH . 'assets/helpers.php');
}
require_once(__DIR__ . '/system.event.class.inc.php');

class DocumentParser
{
    public $version;
    public $db; // db object
    public $event, $Event; // event object
    public $pluginEvent = [];
    public $config = [];
    public $userConfig = [];
    public $rs;
    public $result;
    public $sql;
    public $table_prefix;
    public $debug;
    public $q;
    public $documentIdentifier;
    public $documentGenerated;
    public $documentContent;
    public $documentOutput;
    public $tstart;
    public $mstart;
    public $maxParserPasses;
    public $documentObject = [];
    public $templateObject = [];
    public $snippetObjects = [];
    public $moduleObject = [];
    public $stopOnNotice;
    public $executedQueries;
    public $queryTime;
    public $currentSnippet;
    public $aliases;
    public $entrypage;
    public $dumpSQL;
    public $dumpSnippets;
    public $dumpPlugins;
    public $dumpSnippetsCode = [];
    public $chunkCache;
    public $snippetCache;
    public $contentTypes;
    public $dumpSQLCode = [];
    public $ph;
    public $placeholders = [];
    public $sjscripts = [];
    public $jscripts = [];
    public $loadedjscripts = [];
    public $documentMap = [];
    public $forwards = 3;
    public $referenceListing;
    public $childrenList = [];
    public $safeMode;
    public $qs_hash;
    public $cacheRefreshTime;
    public $error_reporting;
    public $http_status_code;
    public $directParse;
    public $decoded_request_uri;
    public $dbConfig;
    public $pluginCache;
    public $aliasListing = [];
    public $SystemAlertMsgQueque;
    public $uaType;
    public $functionLog = [];
    public $currentSnippetCall;
    public $previewObject = ''; //プレビュー用のPOSTデータを保存
    public $snipLapCount;
    public $chunkieCache;
    public $template_path;
    public $lastInstallTime;
    public $aliaslist = [];
    public $parentlist = [];
    public $aliasPath = [];
    public $tmpCache = [];
    public $docid;
    public $doc = [];
    public $uri_parent_dir;
    public $manager;
    public $user_allowed_docs;

    private $baseTime = ''; //タイムマシン(基本は現在時間)

    function __get($property_name)
    {
        if (isset($this->config[$property_name])) {
            return $this->config[$property_name];
        }

        $this->logEvent(
            0
            , 1
            , '$modx-&gt;{$property_name} is undefined property', 'Call undefined property'
        );
        return '';
    }

    public function __call($method_name, $arguments)
    {
        $_ = explode(',',
            'splitTVCommand,ParseInputOptions,ProcessTVCommand,addEventListener,addLog,atBind,atBindFile,atBindUrl,atBindInclude,changeWebUserPassword,checkPermissions,clearCache,decodeParamValue,genTokenString,getActiveChildren,getAllChildren,getDocumentChildren,getDocumentChildrenTVarOutput,getDocumentChildrenTVars,getExtention,getLoginUserName,getLoginUserType,getMimeType,getOption,getPreviewObject,getSnippetId,getSnippetName,getUnixtimeFromDateString,getUserInfo,getVersionData,getWebUserInfo,get_backtrace,isMemberOfWebGroup,isSelected,loadLexicon,logEvent,mergeInlineFilter,messageQuit,parseInput,recDebugInfo,regClientCSS,regClientHTMLBlock,regClientScript,regClientStartupHTMLBlock,regClientStartupScript,regOption,removeEventListener,renderFormElement,rotate_log,runSnippet,sendErrorPage,sendForward,sendRedirect,sendUnauthorizedPage,sendUnavailablePage,sendmail,setCacheRefreshTime,setOption,snapshot,splitOption,updateDraft,webAlertAndQuit,setdocumentMap,setAliasListing');
        if (in_array($method_name, $_, true)) {
            $this->loadExtension('SubParser');
            if (method_exists($this->sub, $method_name)) {
                return call_user_func_array([$this->sub, $method_name], $arguments);
            }
        }

        $this->loadExtension('DeprecatedAPI');
        if (method_exists($this->old, $method_name)) {
            $error_type = 1;
        } else {
            $error_type = 3;
        }

        if (!isset($this->config) || !$this->config) {
            $this->config = $this->getSettings();
        }

        if (!isset($this->config['error_reporting']) || 1 < $this->config['error_reporting']) {
            if ($error_type == 1) {
                $title = 'Call deprecated method';
                $msg = $this->htmlspecialchars("\$modx->{$method_name}() is deprecated function");
            } else {
                $title = 'Call undefined method';
                $msg = $this->htmlspecialchars("\$modx->{$method_name}() is undefined function");
            }
            $info = debug_backtrace();
            $m[] = $msg;
            if ($this->currentSnippet) {
                $m[] = 'Snippet - ' . $this->currentSnippet;
            } elseif (!empty($this->event->activePlugin)) {
                $m[] = 'Plugin - ' . $this->event->activePlugin;
            }
            $m[] = $this->decoded_request_uri;
            $m[] = str_replace('\\', '/', $info[0]['file']) . '(line:' . $info[0]['line'] . ')';
            $msg = implode('<br />', $m);
            $this->logEvent(0, $error_type, $msg, $title);
        }

        if (method_exists($this->old, $method_name)) {
            return call_user_func_array([$this->old, $method_name], $arguments);
        }
        return '';
    }

    // constructor
    function __construct()
    {
        if ($this->isLoggedIn()) {
            ini_set('display_errors', 1);
        }
        if (!defined('MODX_SETUP_PATH')) {
            set_error_handler([& $this, 'phpError'], E_ALL); //error_reporting(0);
        }
        mb_internal_encoding('utf-8');
        $this->loadExtension('DBAPI'); // load DBAPI class
        $this->loadExtension('DocumentAPI');
        if ($this->isBackend()) {
            $this->loadExtension('ManagerAPI');
        }

        // events
        $this->event = new SystemEvent();
        $this->Event = &$this->event; //alias for backward compatibility
        $this->ph = &$this->placeholders;
        $this->docid = &$this->documentIdentifier;
        $this->doc = &$this->documentObject;

        $this->maxParserPasses = 10; // max number of parser recursive loops or passes
        $this->debug = false;
        $this->dumpSQL = false;
        $this->dumpSnippets = false; // feed the parser the execution start time
        $this->dumpPlugins = false;
        $this->snipLapCount = 0;
        $this->stopOnNotice = false;
        $this->safeMode = false;
        // set track_errors ini variable
        @ ini_set('track_errors', '1');
        $this->error_reporting = 1;
        // Don't show PHP errors to the public
        if ($this->isLoggedIn()) {
            ini_set('display_errors', '1');
        } elseif (!defined('MODX_API_MODE')) {
            ini_set('display_errors', '0');
        }

        if (!isset($this->tstart)) {
            $this->tstart = serverv('REQUEST_TIME_FLOAT');
        }
        if (!isset($this->mstart)) {
            $this->mstart = memory_get_usage();
        }
    }

    /*
     * loads an extension from the extenders folder
     *
     * @param $extname Extension name
     * @return bool or Object
     *
     */
    public function loadExtension($extname)
    {
        $extname = strtolower($extname);

        switch ($extname) {
            case 'dbapi'       : // Database API
            case 'managerapi'  : // Manager API
            case 'docapi'      : // Resource API
            case 'export_site' :
            case 'subparser'   :
            case 'revision'    :
            case 'phpass'      :
                require_once(MODX_CORE_PATH . "extenders/ex_{$extname}.php");
                return true;
            case 'documentapi' : // Document API
                include_once(MODX_CORE_PATH . "extenders/ex_{$extname}.php");
                Document::$modx = $this;
                return true;
            case 'modifiers' : //Modfires
            case 'phx' :
            case 'filter' :
                include_once(MODX_CORE_PATH . 'extenders/ex_modifiers.php');
                return true;
            case 'deprecatedapi':
                include_once(MODX_CORE_PATH . 'extenders/ex_deprecated.php');
                return '';
            case 'modxmailer' : // PHPMailer
                include_once(MODX_CORE_PATH . 'extenders/ex_modxmailer.php');
                $this->mail = new MODxMailer;
                return true;
            case 'maketable' :
                include_once(MODX_CORE_PATH . 'extenders/ex_maketable.php');
                $this->table = new MakeTable;
                return true;
            case 'configmediation':
                include_once(MODX_CORE_PATH . 'extenders/ex_configmediation.php');
                return new CONFIG_MEDIATION($this);
            default :
                return false;
        }
    }

    public function executeParser()
    {
        ob_start();

        $this->http_status_code = '200';

        $this->directParse = 0;

        // get the settings
        if (!isset($this->config) || !$this->config) {
            $this->config = $this->getSettings();
        }

        $this->setBaseTime();
        $this->sanitizeVars();
        $this->uaType = $this->setUaType();
        $this->qs_hash = $this->genQsHash();

        if ($this->checkSiteStatus() === false) {
            $this->sendUnavailablePage();
        }

        $this->updatePublishStatus();

        $this->decoded_request_uri = urldecode($this->treatRequestUri(request_uri()));
        $_ = ltrim(
            substr(request_uri(), 0, strrpos(request_uri(), '/')) . '/',
            '/'
        );
        if (strpos($_, '?') !== false) {
            $_ = substr($_, 0, strpos($_, '?'));
        }
        $this->uri_parent_dir = $_;

        if (serverv('REQUEST_METHOD') === 'POST') {
            $this->config['cache_type'] = 0;
        }

        $rs = $this->get_static_pages($this->decoded_request_uri);
        if ($rs === 'complete') {
            exit;
        }
        $this->documentIdentifier = $this->getDBCache(
            'docid_by_uri',
            md5($this->decoded_request_uri)
        );

        if ($this->documentIdentifier === false) {
            $this->sendErrorPage();
            return;
        }

        if (!$this->documentIdentifier) {
            $this->documentIdentifier = $this->getDocumentIdentifier(
                $this->decoded_request_uri
            );
            $this->setDBCache(
                'docid_by_uri',
                md5($this->decoded_request_uri),
                $this->documentIdentifier
            );
        }

        if (!$this->documentIdentifier) {
            $this->sendErrorPage();
        }

        // invoke OnWebPageInit event
        $this->invokeEvent('OnWebPageInit');

        return $this->prepareResponse();
    }

    private function treatRequestUri($uri)
    {
        if(strpos($uri,'?')===false) {
            return $uri;
        }
        $qs = $_GET;
        ksort($qs);
        return strstr($uri,'?',true) . '?' . http_build_query($qs);
    }

    function executeParserDirect($id = '')
    {
        ob_start();

        $this->http_status_code = '200';

        $this->directParse = 1;

        // get the settings
        if (!isset($this->config) || !$this->config) {
            $this->config = $this->getSettings();
        }

        $this->setBaseTime();
        $this->sanitizeVars();
        $this->uaType = $this->setUaType();
        $this->qs_hash = '';

        if ($this->checkSiteStatus() === false) {
            $this->sendUnavailablePage();
        }

        $this->decoded_request_uri = MODX_BASE_URL . "index.php?id={$id}";
        $this->uri_parent_dir = '';

        $_REQUEST['id'] = $id;
        $_GET['id'] = $id;

        $this->documentIdentifier = $id;

        // invoke OnWebPageInit event
        $this->invokeEvent('OnWebPageInit');

        return $this->prepareResponse();
    }

    private function getDocumentIdentifier($uri)
    {
        if (getv('id') && preg_match('@^[1-9][0-9]*$@', getv('id'))) {
            return getv('id');
        }

        if ($uri === MODX_BASE_URL) {
            return $this->config['site_start'];
        }

        $getQ = $this->getRequestQ($this->decoded_request_uri);
        if (!getv('id') && $getQ !== false) {
            return $this->getIdFromAlias($this->_treatAliasPath($getQ));
        }

        return 0;
    }

    function setDBCache($category, $key, $value)
    {
        db()->delete(
            '[+prefix+]system_cache',
            [
                where('cache_section', $category),
                and_where('cache_key', $key)
            ]
        );
        return db()->insert(
            db()->escape(
                [
                    'cache_section' => $category,
                    'cache_key' => $key,
                    'cache_value' => $value,
                    'cache_timestamp' => request_time()
                ]
            ),
            '[+prefix+]system_cache'
        );
    }

    function getDBCache($category, $key)
    {
        $rs = db()->select(
            'cache_value',
            '[+prefix+]system_cache',
            [
                where('cache_section', '=', $category),
                'and',
                where('cache_key', '=', $key)
            ]
        );
        if (!$rs) {
            return false;
        }

        return db()->getValue($rs);
    }

    function purgeDBCache()
    {
        return db()->truncate('[+prefix+]system_cache');
    }

    function _treatAliasPath($q)
    {
        $pos = strrpos($q, '/');
        if ($pos) {
            $path = substr($q, 0, $pos);
            $alias = substr($q, $pos + 1);
        } else {
            $path = '';
            $alias = $q;
        }

        $prefix = $this->config['friendly_url_prefix'];
        $suffix = $this->config['friendly_url_suffix'];
        if ($prefix && strpos($q, $prefix) !== false) {
            $alias = preg_replace("@^{$prefix}@", '', $alias);
        }
        if ($suffix && strpos($q, $suffix) !== false) {
            $alias = preg_replace("@{$suffix}" . '$@', '', $alias);
        }

        if ($pos) {
            return "{$path}/{$alias}";
        }

        return $alias;
    }

    function getRequestQ($uri)
    {
        if (strpos(serverv('SERVER_SOFTWARE'), 'Microsoft-IIS') !== false) // IIS friendly url fix
        {
            return $this->_IIS_furl_fix();
        }

        if (strpos($uri, '?') !== false) {
            $uri = strstr($uri, '?', true);
        }

        if ($uri === MODX_BASE_URL . 'index.php') {
            return '/';
        }

        return substr($uri, strlen(MODX_BASE_URL));
    }

    function sanitizeVars()
    {
        if (strpos(urldecode(serverv('QUERY_STRING')), chr(0)) !== false) {
            exit();
        }

        foreach (['PHP_SELF', 'HTTP_USER_AGENT', 'HTTP_REFERER', 'QUERY_STRING'] as $key) {
            if (isset ($_SERVER[$key])) {
                $_SERVER[$key] = $this->hsc($_SERVER[$key]);
            } else {
                $_SERVER[$key] = null;
            }
        }
        $this->sanitize_gpc($_GET);
        if ($this->isBackend()) {
            if (session_id() === '' || $this->session('mgrPermissions.save_document') != 1) {
                $this->sanitize_gpc($_POST);
            }
        }
        $this->sanitize_gpc($_COOKIE);
        $this->sanitize_gpc($_REQUEST);
    }

    function setUaType()
    {
        if (!$this->config('individual_cache') || $this->config('cache_type') == 2) {
            return 'pages';
        }
        return device();

    }

    function genQsHash()
    {
        if (!$this->server('QUERY_STRING')) {
            return '';
        }

        $qs = $_GET;
        if (isset($qs['id'])) {
            unset($qs['id']);
        }
        if (0 < count($qs)) {
            ksort($qs);
            $qs_hash = '_' . hash('crc32b', http_build_query($qs));
        } else {
            $qs_hash = '';
        }
        $userID = $this->getLoginUserID('web');
        if ($userID) {
            return hash('crc32b', sprintf('%s^%s^', $qs_hash, $userID));
        }

        return $qs_hash;
    }

    public function prepareResponse()
    {
        // we now know the method and identifier, let's check the cache
        $this->documentContent = $this->getCache($this->documentIdentifier);
        if ($this->documentContent != '') {
            $params = ['useCache' => true];
            $this->invokeEvent('OnLoadWebPageCache', $params); // invoke OnLoadWebPageCache  event
            if ($params['useCache'] != true) {  //no use cache
                $this->config['cache_type'] = 0;
                $this->documentContent = '';
            }
        }

        if ($this->documentContent == '') {
            // get document object
            if ($this->documentObject) {
                $_ = $this->documentObject;
            }
            $this->documentObject = $this->getDocumentObject('id', $this->documentIdentifier, 'prepareResponse');
            if (isset($_)) {
                $this->documentObject = array_merge($_, $this->documentObject);
            }

            // validation routines
            if ($this->checkSiteStatus() === false) {
                if (!$this->config['site_unavailable_page']) {
                    header("Content-Type: text/html; charset={$this->config['modx_charset']}");
                    $tpl = '<!DOCTYPE html><head><title>[+site_unavailable_message+]</title><body>[+site_unavailable_message+]';
                    $content = $this->parseText($tpl, $this->config);
                    header('Content-Length: ' . strlen($content));
                    exit($content);
                }
            }

            if ($this->http_status_code == '200') {
                if ($this->documentObject['published'] == 0) {
                    if (!$this->hasPermission('view_unpublished') || !$this->checkPermissions($this->documentIdentifier)) {
                        $this->sendErrorPage();
                    }
                } elseif ($this->documentObject['deleted'] == 1) {
                    $this->sendErrorPage();
                }
            }
            // check whether it's a reference
            if ($this->documentObject['type'] === 'reference') {
                if (preg_match('@^[0-9]+$@', $this->documentObject['content'])) {
                    // if it's a bare document id
                    $this->documentObject['content'] = $this->makeUrl($this->documentObject['content']);
                }
                $this->documentObject['content'] = $this->parseDocumentSource($this->documentObject['content']);
                if ($this->previewObject) {
                    $this->directParse = 0;
                }
                $rs = $this->sendRedirect(
                    $this->documentObject['content']
                    , 0
                    , ''
                    , 'HTTP/1.0 301 Moved Permanently'
                );
                if ($this->directParse == 1) {
                    return $rs;
                }
            }
            // check if we should not hit this document
            if ($this->documentObject['donthit'] == 1) {
                $this->config['track_visitors'] = 0;
            }

            if (is_file(MODX_BASE_PATH . 'assets/templates/autoload.php')) {
                $modx =& $this;
                include_once(MODX_BASE_PATH . 'assets/templates/autoload.php');
            }

            // get the template and start parsing!
            $this->documentContent = $this->_getTemplateCode($this->documentObject);

            // invoke OnLoadWebDocument event
            $this->invokeEvent('OnLoadWebDocument');

            // Parse document source
            $this->documentContent = $this->parseDocumentSource($this->documentContent);
        }
        if ($this->directParse == 0) {
            register_shutdown_function([
                & $this,
                'postProcess'
            ]); // tell PHP to call postProcess when it shuts down
        }
        return $this->outputContent();
    }

    function _getTemplateCode($documentObject)
    {
        if (!$documentObject['template']) {
            return '[*content*]';
        } // use blank template

        $rs = db()->select('id,parent,content', '[+prefix+]site_templates');
        $_ = [];
        while ($row = db()->getRow($rs)) {
            $_[$row['id']] = $row;
        }

        $parentIds = [];
        $template_id = $documentObject['template'];
        $i = 0;
        while ($i < 10) {
            $parentIds[] = $template_id;
            $template_id = $_[$template_id]['parent'];
            $i++;
            if (!$template_id) {
                break;
            }
        }
        $parentIds = array_reverse($parentIds);
        $parents = [];
        foreach ($parentIds as $template_id) {
            $content = $_[$template_id]['content'];
            if (strpos($content, '@') === 0) {
                $content = $this->atBind($content);
            }
            $parents[] = $content;
        }
        $content = array_shift($parents);
        if (strpos($content, '<@IF:') !== false) {
            $content = $this->mergeConditionalTagsContent($content);
        }
        if (!$parents) {
            return $content;
        }

        while ($child_content = array_shift($parents)) {
            if (strpos($content, '[*content*]') !== false) {
                $content = str_replace('[*content*]', $child_content, $content);
            }
            if (strpos($content, '[*#content*]') !== false) {
                $content = str_replace('[*#content*]', $child_content, $content);
            }
            if (strpos($content, '[*content:') !== false) {
                $matches = $this->getTagsFromContent($content, '[*content:', '*]');
                if ($matches[0]) {
                    $modifiers = $matches[1][0];
                    $child_content = $this->applyFilter($child_content, $modifiers);
                    $content = str_replace($matches[0][0], $child_content, $content);
                }
            }
        }

        return $content;
    }

    private function mergeScripts($content) {
        if ($this->documentGenerated != 1) {
            return $content;
        }
        if ($this->documentObject['cacheable'] != 1) {
            return $content;
        }
        if ($this->documentObject['type'] !== 'document') {
            return $content;
        }
        if ($this->documentObject['published'] != 1) {
            return $content;
        }

        if ($this->sjscripts) {
            $this->documentObject['__MODxSJScripts__'] = $this->sjscripts;
        }
        if ($this->jscripts) {
            $this->documentObject['__MODxJScripts__'] = $this->jscripts;
        }
        return $content;
    }

    function outputContent($noEvent = false)
    {
        $content = $this->documentContent;
        $content = $this->mergeScripts($content);
        $content = $this->parseNonCachedSnippets($content);
        $content = $this->mergeRegisteredClientStartupScripts($content);
        $content = $this->mergeRegisteredClientScripts($content);
        $content = $this->cleanUpMODXTags($content);
        $content = $this->rewriteUrls($content);

        if (strpos($content, '\{') !== false) {
            $content = $this->RecoveryEscapedTags($content);
        } elseif (strpos($content, '\[') !== false) {
            $content = $this->RecoveryEscapedTags($content);
        }

        if ($this->dumpSQLCode) {
            $content = preg_replace(
                '@(</body>)@i'
                , implode("\n", $this->dumpSQLCode) . "\n\\1", $content);
        }

        if ($this->dumpSnippetsCode) {
            $content = preg_replace(
                '@(</body>)@i'
                , implode("\n", $this->dumpSnippetsCode) . "\n\\1"
                , $content
            );
        }
        $unstrict_url = MODX_SITE_URL . $this->makeUrl($this->config('site_start'), '', '', 'rel');
        if (strpos($content, $unstrict_url) !== false) {
            $content = str_replace($unstrict_url, MODX_SITE_URL, $content);
        }

        $this->documentOutput = $content;

        // invoke OnLogPageView event
        if ($this->config['track_visitors'] == 1) {
            $this->invokeEvent('OnLogPageHit');
        }

        // invoke OnWebPagePrerender event
        if (!$noEvent) {
            $this->invokeEvent('OnWebPagePrerender');
        }

        echo $this->mergeBenchmarkContent($this->documentOutput);

        if ($this->debug) {
            $this->recDebugInfo();
        }

        $ob_get = ob_get_clean();
        if (defined('IN_PARSER_MODE') && constant('IN_PARSER_MODE') == 'true') {
            header(
                sprintf(
                    'Content-Type: %s; charset=%s',
                    array_get($this->documentObject, 'contentType', 'text/html'),
                    $this->config('modx_charset')
                )
            );
            header('Content-Length: ' . strlen($ob_get));
            if ($this->documentObject['content_dispo'] == 1) {
                if ($this->documentObject['alias']) {
                    $name = $this->documentObject['alias'];
                } else {
                    // strip title of special characters
                    $name = $this->documentObject['pagetitle'];
                    $name = strip_tags($name);
                    $name = preg_replace('/&.+?;/', '', $name); // kill entities
                    $name = preg_replace('/\s+/', '-', $name);
                    $name = preg_replace('|-+|', '-', $name);
                    $name = trim($name, '-');
                }
                header('Content-Disposition: attachment; filename=' . $name);
            }
        }
        return $ob_get;
    }

    function RecoveryEscapedTags($contents)
    {
        $tags = explode(',', '{{,}},[[,]],[!,!],[*,*],[(,)],[+,+],[~,~],[^,^]');
        $rTags = $this->_getEscapedTags($tags);
        $contents = str_replace($rTags, $tags, $contents);
        return $contents;
    }

    function _getEscapedTags($tags)
    {
        $rTags = [];
        foreach ($tags as $tag) {
            $rTags[] = '\\' . $tag[0] . '\\' . $tag[1];
        }
        return $rTags;
    }

    function parseNonCachedSnippets($contents)
    {
        if (strpos($contents, '[!') === false) {
            return $contents;
        }
        if ($this->config['cache_type'] == 2) {
            $this->config['cache_type'] = 1;
        }

        $i = 0;
        while ($i < $this->maxParserPasses) {
            if (strpos($contents, '[!') === false) {
                break;
            }
            $bt = $contents;
            $contents = str_replace(['[!', '!]'], ['[[', ']]'], $contents);
            $contents = $this->parseDocumentSource($contents);
            if ($bt == $contents) {
                break;
            }
            $i++;
        }
        return $contents;
    }

    function postProcess()
    {
        // if the current document was generated, cache it!
        if ($this->documentGenerated == 1
            && $this->documentObject['cacheable'] == 1
            && $this->documentObject['type'] === 'document'
            && $this->documentObject['published'] == 1) {
            $docid = $this->documentIdentifier;
            $param = ['makeCache' => true];
            // invoke OnBeforeSaveWebPageCache event
            $this->invokeEvent('OnBeforeSaveWebPageCache', $param);

            if ($param['makeCache'] != true) {
                return;
            }

            // get and store document groups inside document object. Document groups will be used to check security on cache pages
            $dsq = db()->select(
                'document_group',
                '[+prefix+]document_groups',
                where('document', '=', $docid)
            );
            $docGroups = db()->getColumn('document_group', $dsq);

            // Attach Document Groups and Scripts
            if (is_array($docGroups)) {
                $this->documentObject['__MODxDocGroups__'] = join(',', $docGroups);
            }

            switch ($this->config['cache_type']) {
                case '1':
                    $cacheContent = '<?php header("HTTP/1.0 404 Not Found");exit; ?>';
                    $cacheContent .= serialize($this->documentObject);
                    $cacheContent .= "<!--__MODxCacheSpliter__-->{$this->documentContent}";
                    $filename = "{$this->uri_parent_dir}docid_{$docid}{$this->qs_hash}";
                    break;
                case '2':
                    $cacheContent = serialize($this->documentObject['contentType']);
                    $cacheContent .= "<!--__MODxCacheSpliter__-->{$this->documentOutput}";
                    $filename = hash('crc32b', request_uri());
                    break;
            }

            switch ($this->http_status_code) {
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

            if (!is_dir(MODX_BASE_PATH . 'assets/cache')) {
                mkdir(MODX_BASE_PATH . 'assets/cache');
            }
            if (!is_dir(MODX_BASE_PATH . 'assets/cache/' . $this->uaType)) {
                mkdir(MODX_BASE_PATH . 'assets/cache/' . $this->uaType, 0777);
            }

            if ($this->config['cache_type'] == 1) {
                $path = sprintf("%sassets/cache/%s/%s", MODX_BASE_PATH, $this->uaType, $this->uri_parent_dir);
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
            }
            $this->saveToFile(
                sprintf(
                    '%sassets/cache/%s/%s.pageCache.php'
                    , MODX_BASE_PATH
                    , $this->uaType
                    , $filename
                )
                , $cacheContent
            );
        }
        // Useful for example to external page counters/stats packages
        $this->invokeEvent('OnWebPageComplete');
        // end post processing
    }

    function sanitize_gpc(&$target, $count = 0)
    {
        if (!$target) {
            return [];
        }
        $_ = implode('', $target);
        if (strpos($_, '[') === false && strpos($_, '<') === false && strpos($_, '#') === false) {
            return '';
        }

        $s = ['[[', ']]', '[!', '!]', '[*', '*]', '[(', ')]', '{{', '}}', '[+', '+]', '[~', '~]', '[^', '^]'];
        foreach ($s as $_) {
            $r[] = " {$_['0']} {$_['1']} ";
        }
        foreach ($target as $key => $value) {
            if (is_array($value)) {
                $count++;
                if (10 < $count) {
                    echo 'too many nested array';
                    exit;
                }
                $this->sanitize_gpc($value, $count);
            } else {
                $value = str_replace($s, $r, $value);
                $value = str_ireplace('<script', 'sanitized_by_modx<s cript', $value);
                $value = preg_replace('/&#(\d+);/', 'sanitized_by_modx& #$1', $value);
                $target[$key] = $value;
            }
            $count = 0;
        }
        return $target;
    }

    function getUaType()
    {
        return device();
    }

    public function join($delim = ',', $array, $prefix = '')
    {
        foreach ($array as $i => $v) {
            $array[$i] = $prefix . trim($v);
        }
        return implode($delim, $array);
    }

    function getMicroTime()
    {
        list ($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }

    function get_static_pages($filepath)
    {
        if (strpos($filepath, '?') !== false) {
            $filepath = strstr($filepath, '?', true);
        }
        $filepath = substr($filepath, strlen(MODX_BASE_URL));
        if (substr($filepath, -1) === '/' || $filepath === '') {
            $filepath .= 'index.html';
        }
        $filepath = sprintf("%stemp/public_html/%s", MODX_BASE_PATH, $filepath);
        if (is_file($filepath) === false) {
            return false;
        }

        $ext = strtolower(substr($filepath, strrpos($filepath, '.')));
        $get_mime_type = function ($ext) use ($filepath) {
            if (in_array($ext, ['.html', '.htm'])) {
                return 'text/html';
            }
            if (in_array($ext, ['.xml', '.rdf'])) {
                return 'text/xml';
            }
            if ($ext === '.css') {
                return 'text/css';
            }
            if ($ext === '.js') {
                return 'text/javascript';
            }
            if ($ext === '.txt') {
                return 'text/plain';
            }
            if (in_array($ext, ['.jpg', '.jpeg', '.png', '.gif'])) {
                return $this->getMimeType($filepath);
            }
            if ($ext === '.ico') {
                return 'image/x-icon';
            }

            return 'text/html';
        };

        $content = file_get_contents($filepath);
        if ($content) {
            $this->documentOutput = $this->parseDocumentSource($content);
            $this->invokeEvent('OnWebPagePrerender');
            header("Content-type: " . $get_mime_type($ext));
            header('Content-Length: ' . strlen($this->documentOutput));
            echo $this->documentOutput;
            $this->invokeEvent('OnWebPageComplete');
            return 'complete';
        }

        return false;
    }

    private function getSiteCache()
    {
        $cache_path = MODX_BASE_PATH . 'assets/cache/config.siteCache.idx.php';
        if (is_file($cache_path)) {
            $config = include($cache_path);
        }

        if (isset($config) && $config) {
            return $config;
        }
        return false;
    }

    private function setSiteCache($config)
    {
        if (!db()->isConnected() || !db()->table_exists('[+prefix+]system_settings')) {
            return;
        }
        include_once MODX_CORE_PATH . 'cache_sync.class.php';
        $cache = new synccache();
        $cache->setCachepath(MODX_BASE_PATH . 'assets/cache/');
        $cache->setReport(false);
        $cache->setConfig($config);
        $cache->buildCache($this);
    }

    private function token_auth()
    {
        if (!$this->input_get('auth_token')) {
            return null;
        }
        if (!$this->db) {
            $this->loadExtension('DBAPI');
        }
        $rs = db()->select(
            'user'
            , '[+prefix+]user_settings'
            , [
                where('setting_name', '=', 'auth_token'),
                'AND',
                where('setting_value', '=', $this->input_get('auth_token'))
            ]
        );
        if (!$rs) {
            return false;
        }
        $userid = db()->getValue($rs);
        $user = $this->getUserINfo($userid);

        session_regenerate_id(true);

        $_SESSION['usertype'] = 'manager'; // user is a backend user

        // get permissions
        $_SESSION['mgrShortname'] = $user['username'];
        $_SESSION['mgrFullname'] = $user['fullname'];
        $_SESSION['mgrEmail'] = $user['email'];
        $_SESSION['mgrValidated'] = 1;
        $_SESSION['mgrInternalKey'] = $userid;
        $_SESSION['mgrFailedlogins'] = $user['failedlogincount'];
        $_SESSION['mgrLogincount'] = $user['logincount']; // login count
        $_SESSION['mgrRole'] = $user['role'];
        $rs = db()->select(
            '*'
            , '[+prefix+]user_roles'
            , where('id', '=', $user['role'])
        );
        $row = db()->getRow($rs);

        $_SESSION['mgrPermissions'] = $row;

        if ($this->session('mgrPermissions.messages') == 1) {
            $rs = db()->select('*', '[+prefix+]manager_users');
            if (db()->count($rs) == 1) {
                $_SESSION['mgrPermissions']['messages'] = '0';
            }
        }
        // successful login so reset fail count and update key values
        db()->update(
            [
                'failedlogincount' => 0,
                'logincount' => $user['logincount'] + 1,
                'lastlogin' => $user['thislogin'],
                'thislogin' => request_time(),
                'sessionid' => session_id()
            ]
            , $this->getFullTableName('user_attributes')
            , 'internalKey=' . $userid
        );

        $_SESSION['mgrLastlogin'] = request_time();
        if (!$this->manager) {
            $this->loadExtension('ManagerAPI');
        }
        $_SESSION['mgrDocgroups'] = $this->manager->getMgrDocgroups($userid);

        if ($this->input_any('rememberme')) {
            $_SESSION['modx.mgr.session.cookie.lifetime'] = (int)$this->config['session.cookie.lifetime'];
            setcookie(
                'modx_remember_manager'
                , $user['username']
                , strtotime('+1 month')
                , MODX_BASE_URL
                , null
                , init::is_ssl() ? true : false
                , true
            );
        } else {
            $_SESSION['modx.mgr.session.cookie.lifetime'] = 0;
            setcookie(
                'modx_remember_manager'
                , ''
                , (request_time() - 3600)
                , MODX_BASE_URL
            );
        }

        if ($this->hasPermission('remove_locks')) {
            $this->manager->remove_locks();
        }
    }

    function getSettings()
    {
        $this->token_auth();

        $config = $this->getSiteCache();
        if (!$config) {
            $rs = db()->select('setting_name,setting_value', '[+prefix+]system_settings');
            $config = [];
            while ($row = db()->getRow($rs)) {
                $config[$row['setting_name']] = $row['setting_value'];
            }
            $this->setSiteCache($config);
        }
        $this->config = $config;

        $cache_path = MODX_BASE_PATH . 'assets/cache/';
        if (is_file($cache_path . 'siteCache.idx.php')) {
            include_once($cache_path . 'siteCache.idx.php');
        }

        $this->config['base_path'] = MODX_BASE_PATH;
        $this->config['core_path'] = MODX_CORE_PATH;
        $this->config['base_url'] = MODX_BASE_URL;
        $this->config['site_url'] = MODX_SITE_URL;
        if (!isset($this->config['error_page'])) {
            $this->config['error_page'] = $this->config['start_page'];
        }
        if (!isset($this->config['unauthorized_page'])) {
            $this->config['unauthorized_page'] = $this->config['error_page'];
        }

        $this->config = $this->getWebUserSettings($this->config);
        $this->userConfig = $this->getUserConfig();
        foreach ($this->userConfig as $k => $v) {
            $this->config[$k] = $v;
        }

        if (strpos($this->config['filemanager_path'], '[(') !== false) {
            $this->config['filemanager_path'] = str_replace(
                '[(base_path)]'
                , MODX_BASE_PATH
                , $this->config['filemanager_path']
            );
        }
        if (strpos($this->config['rb_base_dir'], '[(') !== false) {
            $this->config['rb_base_dir'] = str_replace(
                '[(base_path)]'
                , MODX_BASE_PATH
                , $this->config['rb_base_dir']
            );
        }
        if (!isset($this->config['modx_charset'])) {
            $this->config['modx_charset'] = 'utf-8';
        }

        if ($this->lastInstallTime) {
            $this->config['lastInstallTime'] = $this->lastInstallTime;
        }
        if ($this->config['legacy_cache']) {
            $this->setAliasListing();
        }
        $this->setSnippetCache();

        if ($this->config['disable_cache_at_login'] && $this->isFrontEnd() && $this->isLoggedIn('mgr')) {
            $this->config['cache_type'] = 0;
        }

        $this->invokeEvent('OnGetConfig');
        return $this->config;
    }

    private function getWebUserSettings($config)
    {
        $uid = $this->getLoginUserID('web');
        if (!$uid) {
            return $config;
        }
        $result = db()->select(
            'setting_name, setting_value'
            , '[+prefix+]web_user_settings'
            , where('webuser', '=', $uid)
        );

        if (!$result) {
            return $config;
        }

        while ($row = db()->getRow($result)) {
            $config[$row['setting_name']] = $row['setting_value'];
        }
        return $config;
    }

    private function getUserConfig($uid = null)
    {
        if (!$uid) {
            $uid = $this->getLoginUserID('mgr');
            if (!$uid) {
                return [];
            }
        }

        static $cache = [];
        if (isset($cache[$uid])) {
            return $cache[$uid];
        }
        $cache[$uid] = false;

        if ($this->isBackend()) {
            $this->invokeEvent('OnBeforeManagerPageInit');
        }

        $result = db()->select(
            'setting_name, setting_value'
            , '[+prefix+]user_settings'
            , where('user', '=', $uid)
        );

        if (!$result) {
            return [];
        }

        $config = [];
        while ($row = db()->getRow($result)) {
            $config[$row['setting_name']] = $row['setting_value'];
        }
        $cache[$uid] = $config;
        return $config;
    }

    // check for manager login session
    function isLoggedIn($context = 'mgr')
    {
        if (stripos($context, 'm') === 0) {
            return $this->session('mgrValidated');
        }
        if (stripos($context, 'w') === 0) {
            return $this->session('webValidated');
        }
        return false;
    }

    public function getUserFromName($username)
    {
        $field = 'mu.*, ua.*';
        $from = [
            '[+prefix+]manager_users mu',
            'LEFT JOIN [+prefix+]user_attributes ua ON ua.internalKey=mu.id'
        ];
        $rs = db()->select(
            $field
            , $from
            , sprintf("BINARY mu.username='%s'", db()->escape($username))
        );

        $total = db()->count($rs);

        if (!$total && config('login_by') !== 'username' && strpos($username, '@') !== false) {
            $rs = db()->select(
                $field
                , $from
                , sprintf("BINARY ua.email='%s'", db()->escape($username))
            );
            $total = db()->count($rs);
        }

        if ($total != 1) {
            return false;
        }

        return db()->getRow($rs);
    }

    function checkSession()
    {
        return $this->isLoggedin();
    }

    function checkPreview()
    {
        if ($this->isLoggedin() != true) {
            return false;
        }

        if ($this->input_any('z') === 'manprev') {
            return true;
        }

        return false;
    }

    // check if site is offline
    function checkSiteStatus()
    {
        // site online
        if ($this->config('site_status')) {
            return true;
        }

        // site offline but launched via the manager
        if ($this->isLoggedin()) {
            return true;
        }

        // site is offline
        return false;
    }

    function checkCache($id)
    {
        return $this->getCache($id);
    }

    function getCache($id)
    {
        if (!$this->config('cache_type')) { // jp-edition only
            return '';
        }

        switch ($this->http_status_code) {
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
                $filename = "{$this->uri_parent_dir}docid_{$id}{$this->qs_hash}";
        }

        $cacheFile = sprintf(
            '%sassets/cache/%s/%s.pageCache.php'
            , MODX_BASE_PATH
            , $this->uaType
            , $filename
        );

        if ($this->session('mgrValidated') || $this->input_post()) {
            $this->config['cache_type'] = '1';
        }

        if ($this->config('cache_ttl') && is_file($cacheFile)) {
            $timestamp = filemtime($cacheFile);
            $timestamp += $this->config['cache_ttl'];
            if ($timestamp < request_time()) {
                @unlink($cacheFile);
                $this->documentGenerated = 1;
                return '';
            }
        }

        if ($this->config('cache_type') == 2 && $this->http_status_code != 404) {
            $this->documentGenerated = 1;
            return '';
        }

        if (is_file($cacheFile)) {
            $flContent = file_get_contents($cacheFile, false);
        }

        if (!is_file($cacheFile) || !$flContent) {
            $this->documentGenerated = 1;
            return '';
        }

        $this->documentGenerated = 0;

        if (strpos($flContent, '<?php') === 0) {
            $flContent = substr($flContent, strpos($flContent, '?>') + 2);
        }
        $a = explode('<!--__MODxCacheSpliter__-->', $flContent, 2);
        if (count($a) == 1) {
            return $a[0];
        }

        if ($this->config('cache_type') && $this->http_status_code == 404) {
            return $a[1];
        }

        $docObj = unserialize(trim($a['0'])); // rebuild document object
        // add so - check page security(admin(mgrRole=1) is pass)
        if ($this->session('mgrRole') != 1 && $docObj['privateweb'] && isset ($docObj['__MODxDocGroups__'])) {
            $pass = false;
            $usrGrps = $this->getUserDocGroups();
            $docGrps = explode(',', $docObj['__MODxDocGroups__']);
            // check is user has access to doc groups
            if (is_array($usrGrps) && $usrGrps) {
                foreach ($usrGrps as $k => $v) {
                    $v = trim($v);
                    if (in_array($v, $docGrps)) {
                        $pass = true;
                        break;
                    }
                }
            }
            // diplay error pages if user has no access to cached doc
            if (!$pass) {
                $total = 0;
                if ($this->config('unauthorized_page')) {
                    // check if file is not public
                    $rs = db()->select(
                        'id',
                        '[+prefix+]document_groups',
                        where('document', '=', $id),
                        '',
                        1
                    );
                    $total = db()->count($rs);
                }

                if ($total) {
                    $this->sendUnauthorizedPage();
                } else {
                    $this->sendErrorPage();
                }
                exit;
            }
        }

        // Grab the Scripts
        if (isset($docObj['__MODxSJScripts__'])) {
            $this->sjscripts = $docObj['__MODxSJScripts__'];
        }
        if (isset($docObj['__MODxJScripts__'])) {
            $this->jscripts = $docObj['__MODxJScripts__'];
        }

        $this->documentObject = $docObj;
        return $a[1]; // return document content
    }

    function updatePublishStatus()
    {
        $cache_path = MODX_BASE_PATH . 'assets/cache/basicConfig.php';
        if ($this->cacheRefreshTime == '') {
            if (is_file($cache_path)) {
                global $cacheRefreshTime;
                include_once($cache_path);
                $this->cacheRefreshTime = $cacheRefreshTime;
            } else {
                $this->cacheRefreshTime = 0;
            }
        }
        $timeNow = $this->server('REQUEST_TIME', 0) + $this->config('server_offset_time', 0);

        if ($timeNow < $this->cacheRefreshTime || $this->cacheRefreshTime == 0) {
            return;
        }

        $rs = db()->select(
            'element,elmid'
            , '[+prefix+]site_revision'
            , sprintf(
                "pub_date<=%s AND status='standby'"
                , $timeNow
            )
        );
        $draft_ids = [];
        while ($row = db()->getRow($rs)) {
            if ($row['element'] === 'resource') {
                $draft_ids[] = $row['elmid'];
            }
        }
        if ($draft_ids) {
            $this->updateDraft();
        }

        // now, check for documents that need publishing
        $pub_ids = [];
        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , sprintf(
                'published=0 AND pub_date!=0 AND pub_date<=%s AND (unpub_date=0 OR pub_date<=unpub_date)'
                , $timeNow
            )
        );
        while ($row = db()->getRow($rs)) {
            $pub_ids[] = $row['id'];
        }
        if ($pub_ids) {
            $rs = db()->update(
                'published=1, publishedon=pub_date'
                , '[+prefix+]site_content'
                , sprintf('id in (%s)', implode(',', $pub_ids))
            );
        }

        // now, check for documents that need un-publishing
        $unpub_ids = [];
        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , sprintf(
                'published=1 AND unpub_date!=0 AND unpub_date<=%s AND (pub_date=0 OR pub_date<=unpub_date)'
                , $timeNow
            )
        );
        while ($row = db()->getRow($rs)) {
            $unpub_ids[] = $row['id'];
        }
        if ($unpub_ids) {
            $rs = db()->update(
                'published=0, publishedon=0'
                , '[+prefix+]site_content'
                , sprintf('id in (%s)', join(',', $unpub_ids))
            );
        }

        // now, check for chunks that need publishing
        db()->update(
            'published=1'
            , '[+prefix+]site_htmlsnippets'
            , sprintf(
                'published=0 AND pub_date!=0 AND pub_date<=%s AND (unpub_date=0 OR pub_date<=unpub_date)'
                , $timeNow
            )
        );

        // now, check for chunks that need un-publishing
        db()->update(
            'published=0'
            , '[+prefix+]site_htmlsnippets'
            , sprintf(
                'published=1 AND unpub_date!=0 AND unpub_date<=%s AND (pub_date=0 OR pub_date<=unpub_date)'
                , $timeNow
            )
        );

        $this->clearCache();

        if ($this->config['legacy_cache']) {
            $this->setAliasListing();
        }

        if ($pub_ids) {
            $tmp = ['docid' => $pub_ids, 'type' => 'scheduled'];
            $this->invokeEvent('OnDocPublished', $tmp);
        }
        if ($draft_ids) {
            $tmp = ['docid' => $draft_ids, 'type' => 'draftScheduled'];
            $this->invokeEvent('OnDocPublished', $tmp);
        }
        if ($unpub_ids) {
            $tmp = ['docid' => $unpub_ids, 'type' => 'scheduled'];
            $this->invokeEvent('OnDocUnPublished', $tmp);
        }
    }

    function getTagsFromContent($content, $left = '[+', $right = '+]')
    {
        static $cached = [];

        $key = hash('crc32b', $content . '|' . $left . '|' . $right);
        if (isset($cached[$key])) {
            return $cached[$key];
        }
        $_ = $this->_getTagsFromContent($content, $left, $right);
        if (!$_) {
            return [];
        }
        foreach ($_ as $v) {
            $tags[0][] = $left . $v . $right;
            $tags[1][] = $v;
        }
        $cached[$key] = $tags;
        return $tags;
    }

    private function _getTagsFromContent($content, $left = '[+', $right = '+]')
    {
        if (strpos($content, $left) === false) {
            return [];
        }

        $spacer = hash('crc32b', '<<<MODX>>>');
        $content = $this->escaped_content($content, $left, $spacer);

        $lp = explode($left, $content);
        $piece = [];
        foreach ($lp as $lc => $lv) {
            if ($lc !== 0) {
                $piece[] = $left;
            }
            if (strpos($lv, $right) === false) {
                $piece[] = $lv;
                continue;
            }
            $rp = explode($right, $lv);
            foreach ($rp as $rc => $rv) {
                if ($rc !== 0) {
                    $piece[] = $right;
                }
                $piece[] = $rv;
            }
        }
        $lc = 0;
        $rc = 0;
        $fetch = '';
        $tags = [];
        foreach ($piece as $v) {
            if ($v === $left) {
                if ($lc) {
                    $fetch .= $left;
                }
                $lc++;
                continue;
            }

            if ($v === $right) {
                if ($lc === 0) {
                    continue;
                }
                $rc++;
                if ($lc !== $rc) {
                    $fetch .= $right;
                    continue;
                }
                $tags[] = $fetch; // Fetch and reset
                $fetch = '';
                $lc = 0;
                $rc = 0;
                continue;
            }
            if (0 < $lc) {
                $fetch .= $v;
                continue;
            }
        }

        if (!$tags) {
            return [];
        }

        foreach ($tags as $i => $tag) {
            if (strpos($tag, $spacer) === false) {
                continue;
            }
            $tags[$i] = str_replace($spacer, '', $tag);
        }
        return $tags;
    }

    private function escaped_content($content, $left, $spacer)
    {
        if ($left === '{{') {
            if (strpos($content, ';}}') !== false) {
                $content = str_replace(';}}', sprintf(';}%s}', $spacer), $content);
            }
            if (strpos($content, '{{}}') !== false) {
                $content = str_replace('{{}}', sprintf('{%s{}%s}', $spacer, $spacer), $content);
            }
        }
        if ($left === '[[') {
            if (strpos($content, ']]]]') !== false) {
                $content = str_replace(']]]]', sprintf(']]%s]]', $spacer), $content);
            }
            if (strpos($content, ']]]') !== false) {
                $content = str_replace(']]]', sprintf(']%s]]', $spacer), $content);
            }
        }

        $pos['<![CDATA['] = strpos($content, '<![CDATA[');
        if ($pos['<![CDATA[']) {
            $pos[']]>'] = strpos($content, ']]>');
        }
        if ($pos['<![CDATA['] !== false && $pos[']]>'] !== false) {
            $content = substr($content, 0, $pos['<![CDATA['])
                . substr($content, $pos['<![CDATA['] + 9, $pos[']]>'] - ($pos['<![CDATA['] + 9))
                . substr($content, $pos[']]>'] + 3);
        }
        return $content;
    }

    public function getAliasListing($id, $key = false)
    {

        if (isset($this->aliasListing[$id])) {
            if ($key) {
                return $this->aliasListing[$id][$key];
            }
            return $this->aliasListing[$id];
        }

        $rs = db()->select(
            'id,alias,isfolder,parent'
            , '[+prefix+]site_content'
            , where('id', '=', $id)
        );

        if (!db()->count($rs)) {
            return false;
        }

        $row = db()->getRow($rs);
        $pathInfo = [
            'id' => (int)$row['id'],
            'alias' => $row['alias'] == '' ? $row['id'] : $row['alias'],
            'parent' => (int)$row['parent'],
            'isfolder' => (int)$row['isfolder'],
        ];
        $pathInfo['path'] = '';
        if (0 < $pathInfo['parent'] && $this->config['use_alias_path'] == '1') {
            $_ = $this->getAliasListing((int)$row['parent']);
            if (0 < $_['parent'] && $_['path'] != '') {
                $pathInfo['path'] = sprintf('%s/%s', $_['path'], $_['alias']);
            } else {
                $pathInfo['path'] = $_['alias'];
            }
        }
        if (!isset($this->tmpCache['aliasListingByParent'][$row['parent']])) {
            $this->setAliasListingByParent($row['parent']);
        }
        $this->aliasListing[$id] = $pathInfo;

        if ($key) {
            return $pathInfo[$key];
        }
        return $pathInfo;
    }

    function setAliasListingByParent($parent_id)
    {

        if (isset($this->tmpCache['aliasListingByParent'][$parent_id])) {
            return true;
        }

        $rs = db()->select(
            'id,alias,isfolder,parent'
            , '[+prefix+]site_content'
            , where('parent', '=', $parent_id)
        );

        if (!db()->count($rs)) {
            return false;
        }

        while ($row = db()->getRow($rs)) {
            $docid = (int)$row['id'];
            if (isset($this->aliasListing[$docid])) {
                continue;
            }

            if ((int)$row['parent'] && $this->config('use_alias_path')) {
                $_ = $this->getAliasListing($row['parent']);
                if ($_['parent'] && $_['path'] != '') {
                    $path = $_['path'] . '/' . $_['alias'];
                } else {
                    $path = $_['alias'];
                }
            } else {
                $path = '';
            }

            $this->aliasListing[$docid] = [
                'id' => $docid,
                'alias' => $row['alias'] == '' ? $docid : $row['alias'],
                'parent' => (int)$row['parent'],
                'isfolder' => (int)$row['isfolder'],
                'path' => $path
            ];
        }
        $this->tmpCache['aliasListingByParent'][$parent_id] = true;
        return true;
    }

    function getAliasFromID($docid)
    {

        if (isset($this->aliaslist[$docid])) {
            return $this->aliaslist[$docid];
        }

        $rs = db()->select(
            "id, IF(alias='', id, alias) AS alias"
            , '[+prefix+]site_content'
            , where('parent', '=', $this->getParentID($docid))
        );

        if (!$rs) {
            return false;
        }

        while ($row = db()->getRow($rs)) {
            $this->aliaslist[$row['id']] = $row['alias'];
        }

        return $this->aliaslist[$docid];
    }

    public function getParentID($docid)
    {

        if (!$docid) {
            return 0;
        }

        if (isset($this->parentlist[$docid])) {
            return $this->parentlist[$docid];
        }

        $rs = db()->select(
            'parent'
            , '[+prefix+]site_content'
            , [
                where('id', '=', $docid),
                'AND deleted=0'
            ]
        );

        if (!$rs) {
            $this->parentlist[$docid] = false;
            return false;
        }

        $parent = db()->getValue($rs);

        $this->parentlist[$docid] = $parent;
        $this->setParentIDByParent($parent);

        return $parent;
    }

    private function setParentIDByParent($parent)
    {
        static $cached = [];
        if (isset($cached[$parent])) {
            return;
        }
        $cached[$parent] = false;

        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , [
                where('parent', '=', $parent),
                'AND deleted=0'
            ]
        );

        if (!$rs) {
            return;
        }

        while ($row = db()->getRow($rs)) {
            $this->parentlist[$row['id']] = $parent;
        }

        $cached[$parent] = true;

        return;
    }

    private function getAliasPath($docid)
    {

        if (isset($this->aliasPath[$docid])) {
            return $this->aliasPath[$docid];
        }

        $parent = $docid;
        $i = 0;
        $_ = [];
        while ($parent != 0) {
            $_[] = $this->getAliasFromID($parent);
            $parent = $this->getParentID($parent);
            $i++;
            if (20 < $i) {
                break;
            }
        }

        if ($_) {
            $this->aliasPath[$docid] = implode('/', array_reverse($_));
        } else {
            $this->aliasPath[$docid] = '';
        }
        return $this->aliasPath[$docid];
    }

    public function getUltimateParentId($docid = null, $top = 0)
    {
        if ($docid === null) {
            $docid = $this->documentIdentifier;
        }
        $i = 0;
        while ($docid && $i < 20) {
            if ($top == $this->getParentID($docid)) {
                break;
            }
            $docid = $this->getParentID($docid);
            $i++;
        }
        return $docid;
    }

    // mod by Raymond
    public function mergeDocumentContent($content, $ph = false, $convertValue = true)
    {

        if (strpos($content, '<@LITERAL>') !== false) {
            $content = $this->escapeLiteralTagsContent($content);
        }

        if (strpos($content, '[*') === false) {
            return $content;
        }

        if (!isset($this->documentIdentifier)) {
            return $content;
        }

        if (!isset($this->documentObject) || empty($this->documentObject)) {
            return $content;
        }

        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }

        if (!$ph) {
            $ph = $this->documentObject;
            // dummy phx
            $ph['phx'] = '';
            $ph['dummy'] = '';
        }

        $matches = $this->getTagsFromContent($content, '[*', '*]');
        if (!$matches) {
            return $content;
        }

        foreach ($matches[1] as $i => $key) {
            if (strpos($key, '#') === 0) {
                $key = substr($key, 1);
            }
            list($key, $modifiers) = $this->splitKeyAndFilter($key);
            if (strpos($key, '@') !== false) {
                list($key, $context) = explode('@', $key, 2);
            } else {
                $context = false;
            }

            if (strpos($key, '|') !== false) {
                $keys = explode('|', $key);
                foreach ($keys as $k) {
                    if (!empty($ph[$k])) {
                        $key = $k;
                        break;
                    }
                }
            }

            if (!isset($ph[$key]) && $modifiers) {
                $ph[$key] = '';
            }
            if (!isset($ph[$key]) && !$context) {
                continue;
            }

            if ($context) {
                $value = $this->_contextValue(
                    sprintf('%s@%s', $key, $context),
                    $this->documentObject['parent']
                );
            } else {
                $value = $ph[$key];
            }

            if (is_array($value)) {
                if ($modifiers === 'raw') {
                    $value = $value['value'];
                } else {
                    $value = $this->tvProcessor($value);
                }
            }

            if (strpos($value, '@') === 0) {
                $value = $this->atBind($value);
            }

            if ($modifiers !== false) {
                $value = $this->applyFilter($value, $modifiers, $key);
            } elseif ($convertValue) {
                $value = $this->getReadableValue($key, $value);
            }

            $content = str_replace($matches[0][$i], $value, $content);
        }

        if ($this->debug) {
            $_ = implode(', ', $matches[0]);
            $this->addLogEntry('$modx->' . __FUNCTION__ . "[{$_}]", $fstart);
        }
        return $content;
    }

    function splitKeyAndFilter($key)
    {

        if (strpos($key, ':') === false) {
            return [trim($key), false];
        }

        list($key, $modifiers) = explode(':', $key, 2);

        return [trim($key), trim($modifiers)];
    }

    function getReadableValue($key, $value)
    {
        if ($this->get_docfield_type($key) === 'datetime') {
            return $this->toDateFormat($value);
        }
        if ($this->get_docfield_type($key) === 'user') {
            $user = $this->getUserInfo($value);
            return $user['username'];
        }
        return $value;
    }

    function _contextValue($key, $parent = false)
    {
        if (preg_match('/@\d+\/u/', $key)) {
            $key = str_replace(['@', '/u'], ['@u(', ')'], $key);
        }
        list($key, $str) = explode('@', $key, 2);

        if (strpos($str, '(')) {
            list($context, $option) = explode('(', $str, 2);
        } else {
            list($context, $option) = [$str, false];
        }

        if ($option) {
            $option = trim($option, ')(\'"`');
        }

        switch (strtolower($context)) {
            case 'site_start':
                $docid = $this->config['site_start'];
                break;
            case 'parent':
            case 'p':
                $docid = $parent;
                if ($docid == 0) {
                    $docid = $this->config['site_start'];
                }
                break;
            case 'ultimateparent':
            case 'uparent':
            case 'up':
            case 'u':
                if (strpos($str, '(') !== false) {
                    $top = trim(
                        substr(
                            $str
                            , strpos($str, '(')
                        )
                        , '()"\''
                    );
                } else {
                    $top = 0;
                }
                $docid = $this->getUltimateParentId($this->documentIdentifier, $top);
                break;
            case 'alias':
                $str = substr($str, strpos($str, '('));
                $str = trim($str, '()"\'');
                $docid = $this->getIdFromAlias($str);
                break;
            case 'prev':
                if (!$option) {
                    $option = 'menuindex,ASC';
                } elseif (strpos($option, ',') === false) {
                    $option .= ',ASC';
                }
                list($by, $dir) = explode(',', $option, 2);
                $children = $this->getActiveChildren($parent, $by, $dir);
                $find = false;
                $prev = false;
                foreach ($children as $row) {
                    if ($row['id'] == $this->documentIdentifier) {
                        $find = true;
                        break;
                    }
                    $prev = $row;
                }
                if ($find) {
                    if (isset($prev[$key])) {
                        return $prev[$key];
                    }
                    $docid = $prev['id'];
                } else {
                    $docid = '';
                }
                break;
            case 'next':
                if (!$option) {
                    $option = 'menuindex,ASC';
                } elseif (strpos($option, ',') === false) {
                    $option .= ',ASC';
                }
                list($by, $dir) = explode(',', $option, 2);
                $children = $this->getActiveChildren($parent, $by, $dir);
                $find = false;
                $next = false;
                foreach ($children as $row) {
                    if ($find) {
                        $next = $row;
                        break;
                    }
                    if ($row['id'] == $this->documentIdentifier) {
                        $find = true;
                    }
                }
                if ($find) {
                    if (isset($next[$key])) {
                        return $next[$key];
                    }
                    $docid = $next['id'];
                } else {
                    $docid = '';
                }
                break;
            default:
                $docid = $str;
        }

        if (preg_match('@^[1-9][0-9]*$@', $docid)) {
            return $this->getField($key, $docid);
        }
        return '';
    }

    function addLogEntry($fname, $fstart)
    {
        $tend = $this->getMicroTime();
        $totaltime = $tend - $fstart;
        $fname = $this->htmlspecialchars($fname);
        $msg = sprintf('%2.4fs, %s', $totaltime, $fname);
        $this->functionLog[] = $msg;
    }

    function mergeSettingsContent($content, $ph = false)
    {
        if (strpos($content, '<@LITERAL>') !== false) {
            $content = $this->escapeLiteralTagsContent($content);
        }
        if (strpos($content, '[(') === false) {
            return $content;
        }

        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }

        if (!$ph) {
            $ph = $this->config;
        }

        $matches = $this->getTagsFromContent($content, '[(', ')]');
        if (!$matches) {
            return $content;
        }

        foreach ($matches[1] as $i => $key) {
            list($key, $modifiers) = $this->splitKeyAndFilter($key);

            if (isset($ph[$key])) {
                $value = $ph[$key];
            } else {
                continue;
            }

            if ($modifiers !== false) {
                $value = $this->applyFilter($value, $modifiers, $key);
            }
            $content = str_replace($matches[0][$i], $value, $content);
        }

        if ($this->debug) {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->' . __FUNCTION__ . "[{$_}]", $fstart);
        }
        return $content;
    }

    function mergeChunkContent($content, $ph = false)
    {
        if (strpos($content, '{{ ') !== false) {
            $content = str_replace(['{{ ', ' }}'], ['\{\{ ', ' \}\}'], $content);
        }
        if (strpos($content, '<@LITERAL>') !== false) {
            $content = $this->escapeLiteralTagsContent($content);
        }
        if (strpos($content, '{{') === false) {
            return $content;
        }

        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }

        if (!$ph) {
            $ph = $this->chunkCache;
        }

        $matches = $this->getTagsFromContent($content, '{{', '}}');
        if (!$matches) {
            return $content;
        }

        foreach ($matches[1] as $i => $key) {
            $snip_call = $this->_split_snip_call($key);
            $key = $snip_call['name'];
            $params = $this->getParamsFromString($snip_call['params']);

            list($key, $modifiers) = $this->splitKeyAndFilter($key);

            if (!isset($ph[$key])) {
                $ph[$key] = $this->getChunk($key);
            }
            $value = $ph[$key];
            if ($value === null) {
                continue;
            }

            $value = $this->mergePlaceholderContent($value, $params);
            $value = $this->mergeConditionalTagsContent($value);
            $value = $this->mergeDocumentContent($value);
            $value = $this->mergeSettingsContent($value);
            $value = $this->mergeChunkContent($value);

            if ($modifiers !== false) {
                $value = $this->applyFilter($value, $modifiers, $key);
            }

            $content = str_replace($matches[0][$i], $value, $content);
        }

        if ($this->debug) {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->' . __FUNCTION__ . "[{$_}]", $fstart);
        }
        return $content;
    }

    // Added by Raymond
    function mergePlaceholderContent($content, $ph = false)
    {

        if (strpos($content, '<@LITERAL>') !== false) {
            $content = $this->escapeLiteralTagsContent($content);
        }

        if (strpos($content, '[+') === false) {
            return $content;
        }

        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }

        if (!$ph) {
            $ph = $this->placeholders;
        }

        $content = $this->mergeConditionalTagsContent($content);
        $content = $this->mergeDocumentContent($content);
        $content = $this->mergeSettingsContent($content);
        $matches = $this->getTagsFromContent($content, '[+', '+]');
        if (!$matches) {
            return $content;
        }
        foreach ($matches[1] as $i => $key) {

            list($key, $modifiers) = $this->splitKeyAndFilter($key);

            if (isset($ph[$key])) {
                $value = $ph[$key];
            } elseif ($key === 'phx') {
                $value = '';
            } else {
                continue;
            }

            if ($modifiers !== false) {
                $modifiers = $this->mergePlaceholderContent($modifiers);
                $value = $this->applyFilter($value, $modifiers, $key);
            }
            $content = str_replace($matches[0][$i], $value, $content);
        }
        if ($this->debug) {
            $_ = join(', ', $matches[0]);
            $this->addLogEntry('$modx->' . __FUNCTION__ . "[{$_}]", $fstart);
        }
        return $content;
    }

    function mergeCommentedTagsContent($content, $left = '<!--@MODX:', $right = '-->')
    {
        if (strpos($content, $left) === false) {
            return $content;
        }

        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }

        $matches = $this->getTagsFromContent($content, $left, $right);
        if (empty($matches)) {
            return $content;
        }

        foreach ($matches[1] as $i => $v) {
            $matches[1][$i] = trim($v);
        }
        $content = str_replace($matches[0], $matches[1], $content);

        if ($this->debug) {
            $this->addLogEntry('$modx->' . __FUNCTION__, $fstart);
        }
        return $content;
    }

    function ignoreCommentedTagsContent($content, $left = '<!--@IGNORE:BEGIN-->', $right = '<!--@IGNORE:END-->')
    {
        if (strpos($content, $left) === false) {
            return $content;
        }

        $matches = $this->getTagsFromContent($content, $left, $right);
        if ($matches) {
            foreach ($matches[0] as $i => $v) {
                $addBreakMatches[$i] = $v . "\n";
            }
            $content = str_replace($addBreakMatches, '', $content);
            if (strpos($content, $left) !== false) {
                $content = str_replace($matches[0], '', $content);
            }
        }
        return $content;
    }

    function escapeLiteralTagsContent($content, $left = '<@LITERAL>', $right = '<@ENDLITERAL>')
    {
        if (strpos($content, $left) === false) {
            return $content;
        }

        $matches = $this->getTagsFromContent($content, $left, $right);
        $tags = explode(',', '{{,}},[[,]],[!,!],[*,*],[(,)],[+,+],[~,~],[^,^]');
        $rTags = $this->_getEscapedTags($tags);
        if (!empty($matches)) {
            foreach ($matches[1] as $i => $v) {
                $v = str_replace($tags, $rTags, $v);
                $content = str_replace($matches[0][$i], $v, $content);
            }
        }
        return $content;
    }

    function mergeConditionalTagsContent(
        $content,
        $iftag = '<@IF:',
        $elseiftag = '<@ELSEIF:',
        $elsetag = '<@ELSE>',
        $endiftag = '<@ENDIF>'
    )
    {
        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }

        $content = $this->_prepareCTag($content, $iftag, $elseiftag, $elsetag, $endiftag);
        if (strpos($content, $iftag) === false) {
            return $content;
        }

        $sp = '#' . hash('crc32b', 'ConditionalTags' . request_time()) . '#';
        $content = str_replace(['<?php', '?>'], ["{$sp}b", "{$sp}e"], $content);

        $pieces = explode('<@IF:', $content);
        foreach ($pieces as $i => $split) {
            if ($i === 0) {
                $content = $split;
                continue;
            }
            list($cmd, $text) = explode('>', $split, 2);
            $cmd = str_replace("'", "\'", $cmd);
            $content .= "<?php if(\$this->_parseCTagCMD('" . $cmd . "')): ?>";
            $content .= $text;
        }
        $pieces = explode('<@ELSEIF:', $content);
        foreach ($pieces as $i => $split) {
            if ($i === 0) {
                $content = $split;
                continue;
            }
            list($cmd, $text) = explode('>', $split, 2);
            $cmd = str_replace("'", "\'", $cmd);
            $content .= "<?php elseif(\$this->_parseCTagCMD('" . $cmd . "')): ?>";
            $content .= $text;
        }

        $content = str_replace(['<@ELSE>', '<@ENDIF>'], ['<?php else:?>', '<?php endif;?>'], $content);
        if (strpos($content, '<?xml') !== false) {
            $content = str_replace('<?xml', '<?php echo "<?xml";?>', $content);
        }
        ob_start();
        eval('?>' . $content);
        $content = ob_get_clean();
        $content = str_replace(["{$sp}b", "{$sp}e"], ['<?php', '?>'], $content);
        if ($this->debug) {
            $this->addLogEntry('$modx->' . __FUNCTION__, $fstart);
        }
        return $content;
    }

    private function _prepareCTag(
        $content,
        $iftag = '<@IF:',
        $elseiftag = '<@ELSEIF:',
        $elsetag = '<@ELSE>',
        $endiftag = '<@ENDIF>'
    )
    {
        if (strpos($content, '<!--@IF ') !== false) {
            $content = str_replace('<!--@IF ', $iftag, $content);
        } // for jp
        if (strpos($content, '<!--@IF:') !== false) {
            $content = str_replace('<!--@IF:', $iftag, $content);
        }
        if (strpos($content, $iftag) === false) {
            return $content;
        }
        if (strpos($content, '<!--@ELSEIF:') !== false) {
            $content = str_replace('<!--@ELSEIF:', $elseiftag, $content);
        } // for jp
        if (strpos($content, '<!--@ELSE-->') !== false) {
            $content = str_replace('<!--@ELSE-->', $elsetag, $content);
        }  // for jp
        if (strpos($content, '<!--@ENDIF-->') !== false) {
            $content = str_replace('<!--@ENDIF-->', $endiftag, $content);
        }    // for jp
        if (strpos($content, '<@ENDIF-->') !== false) {
            $content = str_replace('<@ENDIF-->', $endiftag, $content);
        }
        $tags = [$iftag, $elseiftag, $elsetag, $endiftag];
        $content = str_ireplace($tags, $tags, $content); // Change to capital letters
        return $content;
    }

    private function _parseCTagCMD($cmd)
    {
        if (strpos($cmd, '[!') !== false) {
            $cmd = str_replace(['[!', '!]'], ['[[', ']]'], $cmd);
        }
        $safe = 0;
        while ($safe < 20) {
            if (strpos($cmd, '[') === false && strpos($cmd, '{') === false) {
                break;
            }
            $bt = $cmd;
            if (strpos($cmd, '[*') !== false) {
                $cmd = $this->mergeDocumentContent($cmd);
            }
            if (strpos($cmd, '[(') !== false) {
                $cmd = $this->mergeSettingsContent($cmd);
            }
            if (strpos($cmd, '{{') !== false) {
                $cmd = $this->mergeChunkContent($cmd);
            }
            if (strpos($cmd, '[[') !== false) {
                $cmd = $this->evalSnippets($cmd);
            }
            if (strpos($cmd, '[+') !== false && strpos($cmd, '[[') === false) {
                $cmd = $this->mergePlaceholderContent($cmd);
            }
            if ($bt === $cmd) {
                break;
            }
            $safe++;
        }
        $cmd = $this->cleanUpMODXTags($cmd);
        $cmd = trim($cmd);
        $cmd = rtrim($cmd, '-');
        $cmd = str_replace([' and ', ' or '], ['&&', '||'], strtolower($cmd));
        $token = preg_split('@(&&|\|\|)@', $cmd, null, PREG_SPLIT_DELIM_CAPTURE);
        $cmd = [];
        foreach ($token as $i => $v) {
            $v = trim($v);
            if ($i % 2 == 0) {
                $reverse = (strpos($v, '!') === 0);
                if ($reverse) {
                    $v = ltrim($v, '!');
                }

                if (empty($v)) {
                    $v = 0;
                } elseif (preg_match('@^-?[0-9]+$@', $v)) {
                    $v = (int)$v;
                } elseif (preg_match('@^[0-9<>=/ \-+*()%]*$@', $v)) {
                    $v = eval("return {$v};");
                } elseif (trim($v, "' ") == '') {
                    $v = 0;
                } elseif (trim($v, '" ') == '') {
                    $v = 0;
                } else {
                    $v = 1;
                }

                if ($reverse) {
                    $v = (int)!$v;
                }
                $v = 0 < $v ? '1' : '0';
            }
            $cmd[] = $v;
        }
        $cmd = join('', $cmd);
        $cmd = (int)eval("return {$cmd};");

        return $cmd;
    }

    function mergeBenchmarkContent($content)
    {
        if (strpos($content, '^]') === false) {
            return $content;
        }

        if ($this->debug) {
            $this->addLogEntry('$modx->' . __FUNCTION__, $this->getMicroTime());
        }

        $totalTime = ($this->getMicroTime() - $this->tstart);

        return str_replace(
            ['[^q^]', '[^qt^]', '[^p^]', '[^t^]', '[^s^]', '[^m^]', '[^f^]']
            , [
                isset($this->executedQueries) ? $this->executedQueries : 0,
                sprintf('%2.4f s', $this->queryTime),
                sprintf('%2.4f s', ($totalTime - $this->queryTime)),
                sprintf('%2.4f s', $totalTime),
                ($this->documentGenerated || !$this->config['cache_type']) ? 'database' : 'full_cache',
                $this->nicesize(memory_get_peak_usage() - $this->mstart),
                count(get_included_files())
            ]
            , $content
        );
    }

    public function evalPlugin($pluginCode, $params)
    {
        $modx = &$this;
        $modx->event->params = $params; // store params inside event object
        if (is_array($params)) {
            extract($params, EXTR_SKIP);
            $modx->event->cm->setParams($params);
        }
        ob_start();
        $pluginCode = preg_replace('{^<\?php}u', '', trim($pluginCode));
        $return = eval($pluginCode);
        unset ($modx->event->params);
        $echo = ob_get_clean();
        if (!$echo || !error_get_last()) {
            return $echo . $return;
        }

        $error_info = error_get_last();
        if ($error_info['type'] === 2048 || $error_info['type'] === 8192) {
            $error_type = 2;
        } else {
            $error_type = 3;
        }
        if (1 < $this->config['error_reporting'] || 2 < $error_type) {
            if ($echo === false) {
                $echo = 'ob_get_contents() error';
            }
            $this->messageQuit(
                'PHP Parse Error'
                , ''
                , true
                , $error_info['type']
                , $error_info['file']
                , 'Plugin'
                , $error_info['text']
                , $error_info['line']
                , $echo
            );
            if ($this->isBackend()) {
                $this->event->alert(
                    sprintf(
                        'An error occurred while loading. Please see the event log for more information.<p>%s</p>'
                        , $echo
                    )
                );
            }
        }
        return '';
    }

    public function evalSnippet($phpcode, $params)
    {
        $phpcode = trim($phpcode);
        if (empty($phpcode)) {
            return '';
        }

        $modx = &$this;
        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }

        if (isset($params) && is_array($params)) {
            foreach ($params as $k => $v) {
                if (is_string($v)) {
                    $v = strtolower($v);
                    if ($v === 'false') {
                        $params[$k] = false;
                    } elseif ($v === 'true') {
                        $params[$k] = true;
                    }
                }
            }
        }

        $modx->event->params = $params; // store params inside event object
        if (is_array($params)) {
            extract($params, EXTR_SKIP);
        }
        ob_start();
        if (strpos($phpcode, ';') !== false || strpos(trim($phpcode), "\n") !== false) {
            $return = eval($phpcode);
        } else {
            $return = $phpcode($params);
        }
        $echo = ob_get_clean();

        if ((0 < $this->config['error_reporting']) && $echo && error_get_last()) {
            $error_info = error_get_last();
            if ($error_info['type'] === 2048 || $error_info['type'] === 8192) {
                $error_type = 2;
            } else {
                $error_type = 3;
            }

            if (1 < $this->config['error_reporting'] || 2 < $error_type) {
                $this->messageQuit(
                    'PHP Parse Error'
                    , ''
                    , true
                    , $error_info['type']
                    , $error_info['file']
                    , 'Snippet'
                    , $error_info['text']
                    , $error_info['line']
                    , $echo
                );
                if ($this->isBackend()) {
                    $this->event->alert(
                        'An error occurred while loading. Please see the event log for more information<p>' . $echo . '</p>'
                    );
                }
            }
        }

        unset($modx->event->params);

        if ($this->debug) {
            $this->addLogEntry($this->currentSnippetCall, $fstart);
        }

        $this->currentSnippetCall = '';
        $this->currentSnippet = '';
        if (is_array($return) || is_object($return)) {
            return $return;
        }

        return $echo . $return;
    }

    function evalSnippets($content)
    {
        if (strpos($content, '[[') === false) {
            return $content;
        }

        $matches = $this->getTagsFromContent($content, '[[', ']]');
        if (!$matches) {
            return $content;
        }

        $this->snipLapCount++;
        if ($this->dumpSnippets) {
            $tpl = '<legend><b style="color: #821517;">PARSE LAP %s</b></legend>';
            $tpl = '<fieldset style="margin:1em;">' . $tpl . '<div style="width:100%;text-align:left;">';
            $this->dumpSnippetsCode[] = sprintf($tpl, $this->snipLapCount);
        }

        foreach ($matches[1] as $i => $call) {
            if (strpos($call, '$_') === 0) {
                if (strpos($content, '_PHX_INTERNAL_') === false) {
                    $value = $this->_getSGVar($call);
                } else {
                    $value = $matches[0][$i];
                }
                $content = str_replace($matches[0][$i], $value, $content);
                continue;
            }
            if (stripos($call, '@include:') === 0) {
                $path = trim(str_ireplace('@include:', '', $call));
                if (is_file($path)) {
                    ob_start();
                    $return = include $path;
                }
                $content = str_replace(
                    $matches[0][$i]
                    , ob_get_clean() ?: $return
                    , $content
                );
                continue;
            }
            $value = $this->_get_snip_result($call);
            if ($value === false) {
                continue;
            }
            $content = str_replace($matches[0][$i], $value, $content);
        }

        if ($this->dumpSnippets) {
            $this->dumpSnippetsCode[] = '</div></fieldset>';
        }

        return $content;
    }

    private function getAbsolutePath($path)
    {
        if (substr($path, 0, 1) === '/') {
            if (!is_file($path)) {
                $path = MODX_BASE_PATH . ltrim($path, '/');
            }
        }
        if (strpos($path, MODX_MANAGER_PATH) === 0) {
            return false;
        }
        if (!is_file($path)) {
            return false;
        }
        return $path;

    }

    private function _getSGVar($value)
    { // Get super globals
        $key = $value;
        list($key, $modifiers) = $this->splitKeyAndFilter($key);

        $key = str_replace(['(', ')'], ["['", "']"], $key);
        if (strpos($key, '$_SESSION') !== false) {
            $_ = $_SESSION;
            $key = str_replace('$_SESSION', '$_', $key);
            if (isset($_['mgrFormValues'])) {
                unset($_['mgrFormValues']);
            }
            if (isset($_['token'])) {
                unset($_['token']);
            }
        }
        if (strpos($key, '[') !== false) {
            $value = $key ? eval("return {$key};") : '';
        } elseif (0 < eval("return is_array($key) ? count({$key}) : 0;")) {
            $value = eval("return print_r({$key},true)");
        } else {
            $value = '';
        }

        if ($modifiers !== false) {
            $value = $this->applyFilter($value, $modifiers, $key);
        }

        return $value;
    }

    private function _get_snip_result($piece)
    {
        if (ltrim($piece) !== $piece) {
            return '';
        }

        $snip_call = $this->_split_snip_call($piece);

        list($key, $modifiers) = $this->splitKeyAndFilter($snip_call['name']);

        $snippetObject = $this->_getSnippetObject($key);
        if (!$snippetObject) {
            return false;
        }

        $snip_call['name'] = $key;
        $this->currentSnippet = $key;

        // current params
        $params = $this->getParamsFromString($snip_call['params']);

        if (isset($snippetObject['properties'])) {
            if (is_array($snippetObject['properties'])) {
                $default_params = $snippetObject['properties'];
            } else {
                $default_params = $this->parseProperties($snippetObject['properties']);
            }
            $params = array_merge($default_params, $params);
        }
        $value = $this->evalSnippet($snippetObject['content'], $params);

        if ($modifiers !== false) {
            $value = $this->applyFilter($value, $modifiers, $key);
        }

        if ($this->dumpSnippets) {
            $tpl = '<div style="background-color:#fff;padding:1em;border:1px solid #ccc;border-radius:8px;margin-bottom:1em;">%s</div>';
            $piece = sprintf($tpl, nl2br(str_replace(' ', '&nbsp;', $this->htmlspecialchars('[[' . $piece . ']]'))));
            $params = sprintf($tpl, nl2br(str_replace(' ', '&nbsp;', $this->htmlspecialchars(print_r($params, true)))));
            $code = sprintf($tpl, nl2br(str_replace(' ', '&nbsp;', $this->htmlspecialchars($value))));
            $this->dumpSnippetsCode[] = sprintf(
                '<fieldset style="margin-bottom:1em;"><legend><b>Output of %s</b></legend>%s%s%s</fieldset>'
                , $key
                , $piece
                , $params
                , $code
            );
        }
        return $value;
    }

    function getParamsFromString($string = '') // _snipParamsToArray()
    {
        if (empty($string)) {
            return [];
        }

        if (strpos($string, '&_PHX_INTERNAL_') !== false) {
            $string = str_replace(['&_PHX_INTERNAL_091_&', '&_PHX_INTERNAL_093_&'], ['[', ']'], $string);
        }

        $_ = $this->documentOutput;
        $this->documentOutput = $string;
        $this->invokeEvent('OnParseDocument');
        $string = $this->documentOutput;
        $this->documentOutput = $_;

        $_tmp = $string;
        $_tmp = ltrim($_tmp, '?&');
        $temp_params = [];
        $key = '';
        $value = null;
        while ($_tmp !== '') {
            $bt = $_tmp;
            $char = substr($_tmp, 0, 1);
            $_tmp = substr($_tmp, 1);

            if ($char === '=') {
                $_tmp = trim($_tmp);
                $delim = substr($_tmp, 0, 1);
                if (in_array($delim, ['"', "'", '`'])) {
                    list($null, $value, $_tmp) = explode($delim, $_tmp, 3);
                    while (strpos(trim($_tmp), '//') === 0) {
                        $_ = $_tmp;
                        $_tmp = strstr(trim($_tmp), "\n");
                        if ($_tmp === $_) {
                            break;
                        }
                    }
                    $i = 0;
                    while ($delim === '`' && substr(trim($_tmp), 0, 1) !== '&' && 1 < substr_count($_tmp, '`')) {
                        list($inner, $outer, $_tmp) = explode('`', $_tmp, 3);
                        $value .= "`{$inner}`{$outer}";
                        $i++;
                        if (20 < $i) {
                            exit('The nest of values are hard to read. Please use three different quotes.');
                        }
                    }
                    if ($i && $delim === '`') {
                        $value = rtrim($value, '`');
                    }
                } elseif (strpos($_tmp, '&') !== false) {
                    list($value, $_tmp) = explode('&', $_tmp, 2);
                    $value = trim($value);
                } else {
                    $value = $_tmp;
                    $_tmp = '';
                }
            } elseif ($char === '&') {
                if (trim($key) !== '') {
                    $value = '1';
                } else {
                    continue;
                }
            } elseif ($_tmp === '') {
                $key .= $char;
                $value = '1';
            } elseif ($key !== '' || trim($char) !== '') {
                $key .= $char;
            }

            if (isset($value) && $value !== null) {
                if (strpos($key, 'amp;') !== false) {
                    $key = str_replace('amp;', '', $key);
                }
                $key = trim($key);
                if (strpos($value, '[!') !== false) {
                    $value = str_replace(['[!', '!]'], ['[[', ']]'], $value);
                }
                $value = $this->mergeDocumentContent($value);
                $value = $this->mergeSettingsContent($value);
                $value = $this->mergeChunkContent($value);
                $value = $this->evalSnippets($value);
                if (strpos($value, '@CODE:') === false) {
                    $value = $this->mergePlaceholderContent($value);
                }

                $temp_params[][$key] = $value;
                $key = '';
                $value = null;

                $_tmp = ltrim($_tmp, " ,\t");
                if (strpos($_tmp, '//') === 0) {
                    $_tmp = strstr($_tmp, "\n");
                }
            }

            if ($_tmp === $bt) {
                $key = trim($key);
                if ($key !== '') {
                    $temp_params[][$key] = '';
                }
                break;
            }
        }
        // スニペットコールのパラメータを配列にも対応
        foreach ($temp_params as $p) {
            $k = key($p);
            if (substr($k, -2) === '[]') {
                $k = substr($k, 0, -2);
                $params[$k][] = current($p);
            } elseif (strpos($k, '[') !== false && substr($k, -1) === ']') {
                list($k, $subk) = explode('[', $k, 2);
                $params[$k][substr($subk, 0, -1)] = current($p);
            } else {
                $params[$k] = current($p);
            }
        }

        return $params;
    }

    function _getSplitPosition($str)
    {
        $closeOpt = false;
        $maybePos = false;
        $inFilter = false;
        $qpos = strpos($str, '?');
        $strlen = strlen($str);
        for ($i = 0; $i < $strlen; $i++) {
            $c = substr($str, $i, 1);
            $cc = substr($str, $i, 2);
            if (!$inFilter) {
                if ($c === ':') {
                    $inFilter = true;
                } elseif ($c === '?') {
                    $pos = $i;
                } elseif ($c === ' ') {
                    $maybePos = $i;
                } elseif ($c === '&' && $maybePos) {
                    $pos = $maybePos;
                } elseif ($c === '&' && !$qpos) {
                    $pos = $i;
                } elseif ($c === "\n") {
                    $pos = $i;
                } else {
                    $pos = false;
                }
            } else {
                if ($cc == $closeOpt) {
                    $closeOpt = false;
                } elseif ($c == $closeOpt) {
                    $closeOpt = false;
                } elseif ($closeOpt) {
                    continue;
                } elseif ($cc === "('") {
                    $closeOpt = "')";
                } elseif ($cc === '("') {
                    $closeOpt = '")';
                } elseif ($cc === '(`') {
                    $closeOpt = '`)';
                } elseif ($c === '(') {
                    $closeOpt = ')';
                } elseif ($c === '?') {
                    $pos = $i;
                } elseif ($c === ' '
                    && $qpos === false) {
                    $pos = $i;
                } else {
                    $pos = false;
                }
            }
            if ($pos) {
                break;
            }
        }

        return $pos;
    }

    private function _split_snip_call($call)
    {
        $spacer = hash('crc32b', 'dummy');
        if (strpos($call, ']]>') !== false) {
            $call = str_replace(']]>', "]{$spacer}]>", $call);
        }

        $splitPosition = $this->_getSplitPosition($call);

        if ($splitPosition !== false) {
            $name = substr($call, 0, $splitPosition);
            $params = substr($call, $splitPosition + 1);
        } else {
            $name = $call;
            $params = '';
        }

        $snip['name'] = trim($name);
        if (strpos($params, $spacer) !== false) {
            $params = str_replace("]{$spacer}]>", ']]>', $params);
        }
        $snip['params'] = ltrim($params, "?& \t\n");

        return $snip;
    }

    private function _getSnippetObject($snip_name)
    {
        if (!isset($this->snippetCache[$snip_name])) {
            return false;
        }
        $snippetObject['name'] = $snip_name;
        $snippetObject['content'] = $this->snippetCache[$snip_name];

        if (isset($this->snippetCache[$snip_name . 'Props'])) {
            $snippetObject['properties'] = $this->snippetCache[$snip_name . 'Props'];
        }

        return $snippetObject;
    }

    function setSnippetCache()
    {
        $rs = db()->select('name,snippet,properties', '[+prefix+]site_snippets');
        while ($row = db()->getRow($rs)) {
            $name = $row['name'];
            $this->snippetCache[$name] = $row['snippet'];
            $this->snippetCache["{$name}Props"] = $row['properties'];
        }
    }

    function getPluginCache()
    {
        $plugins = @include(MODX_BASE_PATH . 'assets/cache/plugin.siteCache.idx.php');

        if ($plugins) {
            $this->pluginCache = $plugins;
        }

        return false;
    }

    private function is_int($string)
    {
        return preg_match('@^[1-9][0-9]*$@', $string);
    }

    /**
     * name: getDocumentObject  - used by parser
     * desc: returns a document object - $method: alias, id
     */
    public function getDocumentObject($method = 'id', $identifier = '', $mode = 'direct')
    {
        if ($method === 'alias') {
            $identifier = $this->getIdFromAlias($identifier);
            if ($identifier === false) {
                return false;
            }
        }

        if ($this->isLoggedIn() && $mode === 'prepareResponse' && $this->is_int($this->input_post('id'))) {
            if (!$this->input_post('token') || !$this->session('token') || $this->input_post('token') !== $this->session('token')) {
                exit('Can not preview');
            }

            $previewObject = $this->getPreviewObject($_POST);
            $this->directParse = 1;
            $identifier = $previewObject['id'];
            $this->documentIdentifier = $identifier;
        } elseif ($this->input_get('revision')) {
            if (!$this->isLoggedIn()) {
                $_SESSION['save_uri'] = request_uri();
                header('location:' . MODX_MANAGER_URL);
                exit;
            }

            $this->loadExtension('REVISION');
            if (!$this->previewObject) {
                $previewObject = $this->revision->getDraft($identifier);
                //tvのkeyをtv名に変更
                $tmp = [];
                foreach ($previewObject as $k => $v) {
                    $mt = [];
                    if (preg_match('/^tv([0-9]+)$/', $k, $mt)) {
                        $row = db()->getRow(
                            db()->select(
                                'name'
                                , '[+prefix+]site_tmplvars'
                                , where('id', '=', $mt[1])
                            )
                        );
                        $k = $row['name'];
                    }
                    $tmp[$k] = $v;
                }
                $previewObject = $tmp;
                $this->previewObject = $previewObject;
            } else {
                $previewObject = $this->previewObject;
            }
            $this->config['cache_type'] = 0;
        } else {
            $previewObject = false;
        }

        // get document (add so)
        $_ = [];
        if ($this->isFrontend()) {
            $_[] = 'sc.privateweb=0';
        } else {
            $_[] = 'sc.privatemgr=0';
        }
        $docgrp = $this->getUserDocGroups();
        if ($docgrp) {
            $_[] = where_in('dg.`document_group`', $docgrp);
        }
        if ($this->session('mgrRole')) {
            $_[] = sprintf('1=%d', (int)$this->session('mgrRole'));
        }
        $access = join(' OR ', $_);

        $result = db()->select(
            'sc.*'
            , [
                '[+prefix+]site_content sc',
                'LEFT JOIN [+prefix+]document_groups dg ON dg.document=sc.id'
            ]
            , [
                where('`sc`.`id`', '=', $identifier),
                'AND',
                '(' . $access . ')'
            ]
            , ''
            , 1
        );
        if (!db()->count($result)) {
            if ($this->isBackend() || $mode === 'direct') {
                return false;
            }

            // check if file is not public
            $rs = db()->select(
                'id'
                , '[+prefix+]document_groups'
                , where('document', '=', $identifier)
                , ''
                , 1
            );
            if (db()->count($rs)) {
                $this->sendUnauthorizedPage();
            } else {
                $this->sendErrorPage();
            }
        }

        # this is now the document :) #
        $documentObject = db()->getRow($result);
        if ($previewObject) {
            $snapObject = $documentObject;
        }
        if ($mode === 'prepareResponse') {
            $this->documentObject = &$documentObject;
        }
        $this->invokeEvent('OnLoadDocumentObject');
        $docid = $documentObject['id'];

        // load TVs and merge with document - Orig by Apodigm - Docvars
        $field = [];
        $field['tvid'] = 'tv.id';
        $field['tv.name'] = 'tv.name';
        $field['value'] = "IF(tvc.value!='',tvc.value,tv.default_text)";
        $field['tv.display'] = 'tv.display';
        $field['tv.display_params'] = 'tv.display_params';
        $field['tv.type'] = 'tv.type';
        $field['tv.caption'] = 'tv.caption';
        $from = [];
        $from[] = '[+prefix+]site_tmplvars tv';
        $from[] = 'INNER JOIN [+prefix+]site_tmplvar_templates tvtpl ON tvtpl.tmplvarid=tv.id';
        $from[] = sprintf(
            'LEFT JOIN [+prefix+]site_tmplvar_contentvalues tvc ON tvc.tmplvarid=tv.id AND tvc.contentid=%d'
            , (int)$docid
        );

        if (isset($previewObject['template'])) {
            $tmp = $previewObject['template'];
        } else {
            $tmp = $documentObject['template'];
        }
        $where = sprintf("tvtpl.templateid='%s'", $tmp);

        $rs = db()->select($field, $from, $where);
        $rowCount = db()->count($rs);
        if ($rowCount > 0) {
            while ($row = db()->getRow($rs)) {
                $name = $row['name'];
                if (isset($documentObject[$name])) {
                    continue;
                }
                $tmplvars[$name][] = $row['tvid'];
                $tmplvars[$name][] = $row['name'];
                $tmplvars[$name][] = $row['value'];
                $tmplvars[$name][] = $row['display'];
                $tmplvars[$name][] = $row['display_params'];
                $tmplvars[$name][] = $row['type'];
                $tmplvars[$name][] = $row['caption'];
                $tmplvars[$name]['tvid'] = $row['tvid'];
                $tmplvars[$name]['name'] = $row['name'];
                $tmplvars[$name]['value'] = $row['value'];
                $tmplvars[$name]['display'] = $row['display'];
                $tmplvars[$name]['display_params'] = $row['display_params'];
                $tmplvars[$name]['type'] = $row['type'];
                $tmplvars[$name]['caption'] = $row['caption'];
            }
            $documentObject = array_merge($documentObject, $tmplvars);
        }
        if ($previewObject) {
            foreach ($documentObject as $k => $v) {
                if (!isset($previewObject[$k])) {
                    continue;
                }
                if (!is_array($documentObject[$k])) {
                    // Priority is higher changing on OnLoadDocumentObject event.
                    if ($snapObject[$k] != $documentObject[$k]) {
                        continue;
                    }
                    $documentObject[$k] = $previewObject[$k];
                } else {
                    $documentObject[$k]['value'] = $previewObject[$k];
                }
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
        $orgDocumentOutput = $this->documentOutput;
        $i = 0;
        while ($i < $this->maxParserPasses) {
            $bt = md5($source);
            // invoke OnParseDocument event
            $this->documentOutput = $source; // store source code so plugins can
            $this->invokeEvent('OnParseDocument'); // work on it via $modx->documentOutput
            $source = $this->documentOutput;

            if (strpos($source, '<@IF') !== false) {
                $source = $this->mergeConditionalTagsContent($source);
            }
            if (strpos($source, '<!--@IF') !== false) {
                $source = $this->mergeConditionalTagsContent($source);
            }
            if (strpos($source, '<!--@IGNORE:BEGIN-->') !== false) {
                $source = $this->ignoreCommentedTagsContent($source);
            }
            if (strpos($source, '<!--@IGNORE-->') !== false) {
                $source = $this->ignoreCommentedTagsContent($source, '<!--@IGNORE-->', '<!--@ENDIGNORE-->');
            }
            if (strpos($source, '<!--@MODX:') !== false) {
                $source = $this->mergeCommentedTagsContent($source);
            }

            if (strpos($source, '[+@') !== false) {
                $source = $this->mergeInlineFilter($source);
            }
            if (strpos($source, '[*') !== false) {
                $source = $this->mergeDocumentContent($source);
            }
            if (strpos($source, '[(') !== false) {
                $source = $this->mergeSettingsContent($source);
            }
            if (strpos($source, '{{') !== false) {
                $source = $this->mergeChunkContent($source);
            }
            if (strpos($source, '[[') !== false) {
                $source = $this->evalSnippets($source);
            }
            if (strpos($source, '[+') !== false
                && strpos($source, '[[') === false) {
                $source = $this->mergePlaceholderContent($source);
            }

            if (strpos($source, '[~') !== false && strpos($source, '[~[+') === false) {
                $source = $this->rewriteUrls($source);
            }

            if ($bt === md5($source)) {
                break;
            }

            $i++;
        }
        $this->documentOutput = $orgDocumentOutput; //Return to original output
        return $source;
    }

    /***************************************************************************************/
    /* API functions                                                                /
    /***************************************************************************************/

    public function getParentIds($id = null, $height = 10)
    {
        if ($id === null) {
            $id = $this->documentIdentifier;
        }
        $parents = [];

        while ($id && $height) {
            $parent_id = $this->getParentID($id);
            if (!$parent_id) {
                break;
            }
            $parents[$id] = $parent_id;
            $id = $parent_id;
            $height--;
        }
        return $parents;
    }

    public function hasChildren($docid, $extraWhere = 'and deleted=0')
    {
        static $cache = [];
        if (isset($cache[$docid])) {
            return $cache[$docid];
        }
        $rs = db()->getValue(
            db()->select(
                'id, count(id) as count'
                , '[+prefix+]site_content'
                , sprintf(
                    "parent in (%s) %s GROUP BY id"
                    , implode(',', $this->getSiblings($docid))
                    , $extraWhere
                )
            )
        );
        while ($row = db()->getRow($rs)) {
            $cache[$row['id']] = $row['count'];
        }
        return isset($cache[$docid]) ? $cache[$docid] : null;
    }

    public function getSiblingIds($docid)
    {
        static $cache = [];
        if (isset($cache[$docid])) {
            return $cache[$docid];
        }
        $parent_id = $this->getParentID($docid);
        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , sprintf("parent='%s' and deleted=0", $parent_id)
        );
        $siblings = [];
        while ($row = db()->getRow($rs)) {
            $siblings[] = $row['id'];
        }
        $cache[$docid] = $siblings;
        return $siblings;
    }

    public function getSiblings($docid)
    {
        return $this->getSiblingIds($docid);
    }

    public function getChildIds($id, $depth = 10, $children = [])
    {
        static $cached = [];
        $cacheKey = hash('crc32b', print_r(func_get_args(), true));
        if (isset($cached[$cacheKey])) {
            return $cached[$cacheKey];
        }
        $cached[$cacheKey] = [];

        static $hasChildren = [];

        if (!$hasChildren) {
            $rs = db()->select('DISTINCT(parent)', '[+prefix+]site_content', 'deleted=0');
            while ($row = db()->getRow($rs)) {
                $hasChildren[$row['parent']] = true;
            }
        }

        if (!isset($hasChildren[$id])) {
            return [];
        }

        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , sprintf('deleted=0 AND parent=%s', $id)
            , 'parent, menuindex'
        );
        $depth--;
        while ($row = db()->getRow($rs)) {
            $key = trim(
                sprintf(
                    '%s/%s'
                    , $this->getAliasListing($row['id'], 'path')
                    , $this->getAliasListing($row['id'], 'alias')
                )
                , '/'
            );
            $children[$key] = $row['id'];

            if ($depth) {
                $subChildId = $this->getChildIds($row['id'], $depth);
                if ($subChildId) {
                    $children += $subChildId;
                }
            }
        }

        $cached[$cacheKey] = $children;
        return $children;
    }

    # Returns true if user has the currect permission
    function hasPermission($key = null)
    {

        if ($this->session('mgrPermissions')) {
            if (!$key) {
                return print_r($_SESSION['mgrPermissions'], true);
            }
            return ($this->session('mgrPermissions.' . $key) == 1);
        }
        return false;
    }

    # Returns true if parser is executed in backend (manager) mode
    function isBackend()
    {
        return defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true';
    }

    # Returns true if parser is executed in frontend mode
    function isFrontend()
    {
        return !(defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true');
    }

    public function getDocuments(
        $ids = [],
        $published = 1,
        $deleted = 0,
        $fields = '*',
        $extra_where = '',
        $sort = 'menuindex',
        $dir = 'ASC',
        $limit = ''
    )
    {
        if (!$ids) {
            return false;
        }

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        foreach ($ids as $i => $id) {
            $ids[$i] = trim($id);
        }

        $where = [];
        if ($this->getUserDocGroups()) {
            $where[] = sprintf('sc.id IN (%s)', implode(',', $ids));
            if ($published !== null) {
                $where[] = sprintf('AND sc.published=%d', $published);
            }
            $where[] = sprintf('AND sc.deleted=%d', $deleted);

            if ($this->session('mgrRole') != 1) {
                if ($this->isFrontend()) {
                    $where[] = sprintf(
                        'AND (sc.privateweb=0 OR dg.document_group IN (%s))'
                        , implode(',', $this->getUserDocGroups())
                    );
                } else {
                    $where[] = sprintf(
                        'AND (sc.privatemgr=0 OR dg.document_group IN (%s))'
                        , implode(',', $this->getUserDocGroups())
                    );
                }
            }
            if ($extra_where) {
                $where[] = sprintf('AND %s', $extra_where);
            }
            $where[] = 'GROUP BY sc.id';

            $result = db()->select(
                'DISTINCT ' . $this->join(',', explode(',', $fields), 'sc.')
                , [
                    '[+prefix+]site_content sc',
                    'LEFT JOIN [+prefix+]document_groups dg on dg.document=sc.id'
                ]
                , $where
                , $sort ? sprintf('sc.%s %s', $sort, $dir) : ''
                , $limit
            );
        } else {
            $where[] = sprintf('id IN (%s)', join(',', $ids));
            if ($published !== null) {
                $where[] = sprintf('AND published=%d', $published);
            }
            $where[] = sprintf('AND deleted=%d', $deleted);

            if ($this->session('mgrRole') != 1) {
                if ($this->isFrontend()) {
                    $where[] = 'AND privateweb=0';
                } else {
                    $where[] = 'AND privatemgr=0';
                }
            }
            if ($extra_where) {
                $where[] = sprintf('AND %s', $extra_where);
            }
            $where[] = 'GROUP BY id';

            $result = db()->select(
                'DISTINCT ' . $fields
                , '[+prefix+]site_content'
                , $where
                , $sort ? sprintf('%s %s', $sort, $dir) : ''
                , $limit
            );
        }

        $docs = [];
        while ($row = db()->getRow($result)) {
            $docs[] = $row;
        }
        return $docs;
    }

    public function getDocument($id = 0, $fields = '*', $published = 1, $deleted = 0)
    {
        if (!$id) {
            return false;
        }

        $docs = $this->getDocuments([$id], $published, $deleted, $fields, '', '', '', 1);

        if ($docs != false) {
            return $docs['0'];
        }
        return false;
    }

    public function getField($field = 'content', $docid = '')
    {
        static $doc = [], $cached = [];

        if ($docid === '' && isset($this->documentIdentifier)) {
            $docid = $this->documentIdentifier;
        } elseif (!preg_match('@^[0-9]+$@', $docid)) {
            $docid = $this->getIdFromAlias($docid);
        }

        if (!$docid) {
            return false;
        }

        if (isset($cached[$docid][$field])) {
            return $cached[$docid][$field];
        }

        if (!isset($doc[$docid])) {
            $doc[$docid] = $this->getDocumentObject('id', $docid);
        }

        if (isset($doc[$docid][$field]) && is_array($doc[$docid][$field])) {
            $cached[$docid][$field] = $this->tvProcessor($doc[$docid][$field]);
            return $cached[$docid][$field];
        }
        $cached[$docid][$field] = array_get(
            $doc,
            sprintf(
                '%s.%s',
                $docid,
                $field
            )
        );
        return $cached[$docid][$field];
    }

    public function getPageInfo($docid = 0, $activeOnly = 1, $fields = 'id, pagetitle, description, alias')
    {
        if (!$docid || !preg_match('/^[1-9][0-9]*$/', $docid)) {
            return false;
        }

        // get document groups for current user
        $docgrp = $this->getUserDocGroups();

        $result = db()->select(
            $this->join(
                ','
                , explode(
                    ','
                    , preg_replace("/\s/", '', $fields))
                , 'sc.'
            )
            , [
                '[+prefix+]site_content sc',
                'LEFT JOIN [+prefix+]document_groups dg on dg.document=sc.id'
            ]
            , sprintf(
                "(sc.id='%s' %s) AND (%s %s)"
                , $docid
                , ($activeOnly == 1) ? "AND sc.published=1 AND sc.deleted='0'" : ''
                , $this->isFrontend() ?
                "sc.privateweb='0'"
                :
                sprintf(
                    "1='%s' OR sc.privatemgr='0'"
                    , $this->session('mgrRole')
                )
                , ($docgrp) ? sprintf("OR dg.document_group IN (%s)", implode(',', $docgrp)) : ''
            )
            , ''
            , 1
        );
        return db()->getRow($result);
    }

    function getParent($pid = -1, $activeOnly = 1, $fields = 'id, pagetitle, description, alias, parent')
    {
        if (!$pid) {
            return false;
        }
        if ($pid == -1) {
            if ($this->documentObject['parent'] == 0) {
                return false;
            }
            return $this->getPageInfo(
                $this->documentObject['parent']
                , $activeOnly
                , $fields
            );
        }

        $child = $this->getPageInfo($pid, $activeOnly, 'parent');
        if (!$child['parent']) {
            return false;
        }
        return $this->getPageInfo($child['parent'], $activeOnly, $fields);
    }

    private function _getReferenceListing()
    {
        $rs = db()->select(
            'id,content'
            , '[+prefix+]site_content'
            , "type='reference'"
        );
        if (!db()->count($rs)) {
            $this->referenceListing = [];
            return [];
        }
        $rows = db()->makeArray($rs);
        $referenceListing = [];
        foreach ($rows as $row) {
            $content = trim($row['content']);
            if (
                (strpos($content, '[') !== false || strpos($content, '{') !== false)
                &&
                strpos($content, '[~') === false
            ) {
                $content = $this->parseDocumentSource($content);
            } elseif (strpos($content, '[~') === 0) {
                $content = substr($content, 2, -2);
                if (strpos($content, '[') !== false || strpos($content, '{') !== false) {
                    $content = $this->parseDocumentSource($content);
                }
            }
            $referenceListing[$row['id']] = $content;
        }
        $this->referenceListing = $referenceListing;
        return $referenceListing;
    }

    function makeUrl($id = '', $alias = '', $args = '', $scheme = 'full', $ignoreReference = false)
    {
        static $cached = [];

        if ($id == 0) {
            $id = $this->config('site_start');
        } elseif ($id == '') {
            $id = $this->documentIdentifier;
        }

        $cacheKey = hash(
            'crc32b'
            , print_r(
                [$id, $alias, $args, $scheme, $ignoreReference]
                , true
            )
        );
        if (isset($cached[$cacheKey])) {
            return $cached[$cacheKey];
        }

        $cached[$cacheKey] = false;

        if (!preg_match('@^[0-9]+$@', $id)) {
            $this->messageQuit(
                sprintf(
                    "'%s' is not numeric and may not be passed to makeUrl()"
                    , $id
                )
            );
        }

        if (!isset($this->referenceListing)) {
            $this->_getReferenceListing();
        }

        if (!isset($this->referenceListing[$id]) || $ignoreReference) {
            $orgId = null;
            $type = 'document';
        } else {
            $type = 'reference';
            if (!preg_match('/^[0-9]+$/', $this->referenceListing[$id])) {
                $cached[$cacheKey] = $this->referenceListing[$id];
                return $this->referenceListing[$id];
            }
            $orgId = $id;
            $id = $this->referenceListing[$id];
        }

        if (
            $id == $this->config('site_start')
            &&
            (strpos($scheme, 'f') === 0 || strpos($scheme, 'a') === 0)
        ) {
            $makeurl = '';
        } elseif (!$this->config('friendly_urls')) {
            $makeurl = "index.php?id={$id}";
        } else {
            $alPath = '';
            if (!$alias) {
                $al = $this->getAliasListing($orgId ? $orgId : $id);
                $alias = $orgId ? $orgId : $id;
                if ($this->config('friendly_alias_urls')) {
                    if (!$al || !$al['alias']) {
                        return false;
                    }
                    if ($al['path']) {
                        $_ = explode('/', $al['path'] . '/');
                        foreach ($_ as $i => $v) {
                            $_[$i] = urlencode($v);
                        }
                        $alPath = join('/', $_);
                    } else {
                        $alPath = '';
                    }

                    if ($this->config('xhtml_urls')) {
                        $alias = urlencode($al['alias']);
                    } else {
                        $alias = $al['alias'];
                    }
                }
            }

            if (strpos($alias, '.') !== false && $this->config('suffix_mode')) {
                $f_url_suffix = '';
            } elseif ($al['isfolder'] == 1 && $this->config('make_folders') && $id != $this->config('site_start')) {
                $f_url_suffix = '/';
            } else {
                $f_url_suffix = $this->config('friendly_url_suffix');
            }
            $makeurl = $alPath . $this->config('friendly_url_prefix') . $alias . $f_url_suffix;
        }

        if (strpos($scheme, 'f') === 0) {
            $url = $this->config('site_url') . $makeurl;
        } elseif (in_array($scheme, ['http', '0'])) {
            $site_url = $this->config('site_url');
            if (strpos($site_url, 'http://') !== 0) {
                $url = 'http' . substr($site_url, strpos($site_url, ':')) . $makeurl;
            } else {
                $url = $site_url . $makeurl;
            }
        } elseif (in_array($scheme, ['https', 'ssl', '1'])) {
            $site_url = $this->config('site_url');
            if (strpos($site_url, 'https://') !== 0) {
                $site_url = 'https' . substr($site_url, strpos($site_url, ':'));
            }
            $url = "{$site_url}{$makeurl}";
        } elseif (strpos($scheme, 'a') === 0) {
            $url = MODX_BASE_URL . $makeurl;
        } else {
            $url = $makeurl;
        }

        if ($args) {
            if (is_array($args)) {
                $args = http_build_query($args);
            }
            $url .= sprintf(
                '%s%s'
                , (strpos($url, '?') === false) ? '?' : '&'
                , ltrim($args, '?&')
            );
        }

        if ($this->config('xhtml_urls')) {
            $url = preg_replace('/&(?!amp;)/', '&amp;', $url);
        }
        $params = [
            'id' => $id,
            'alias' => $alias,
            'args' => $args,
            'scheme' => $scheme,
            'url' => & $url,
            'type' => $type,
            'orgId' => $orgId
        ];
        $this->event->vars = $params;
        $rs = $this->invokeEvent('OnMakeUrl', $params);
        $this->event->vars = [];
        if ($rs) {
            $url = end($rs);
        }
        if ($url != $params['url']) {
            $url = $params['url'];
        }

        $cached[$cacheKey] = $url;

        return $url;
    }

    function rewriteUrls($content)
    {
        if (strpos($content, '[~') === false) {
            return $content;
        }

        if (!isset($this->referenceListing)) {
            $this->referenceListing = $this->_getReferenceListing();
        }

        $matches = $this->getTagsFromContent($content, '[~', '~]');
        if (!$matches) {
            return $content;
        }

        $replace = [];
        foreach ($matches[1] as $i => $key) {
            $key_org = $key;
            $key = $this->evalSnippets(
                $this->mergeChunkContent(
                    $this->mergeSettingsContent(
                        $this->mergeDocumentContent(
                            trim($key)
                        )
                    )
                )
            );

            if (strpos($key, '?') === false) {
                $args = '';
            } else {
                list($key, $args) = explode('?', $key, 2);
            }

            if (strpos($key, ':') !== false) {
                list($key, $modifiers) = $this->splitKeyAndFilter($key);
            } else {
                $modifiers = false;
            }

            if ($key === '') {
                $value = '';
            } elseif (preg_match('/^[0-9]+$/', $key)) {
                if (isset($this->referenceListing[$key]) && preg_match('/^[0-9]+$/', $this->referenceListing[$key])) {
                    $docid = $this->referenceListing[$key];
                } else {
                    $docid = $key;
                }

                $value = $this->makeUrl($docid, '', $args, 'rel');
                if (!$value) {
                    $this->logEvent(
                        0
                        , '1'
                        , $this->parseText(
                        [
                            'Can not parse linktag [+linktag+]',
                            '<a href="index.php?a=27&id=[+docid+]">[+request_uri+]</a>',
                            MODX_SITE_URL
                        ]
                        , [
                            'linktag' => sprintf('[~%s~]', $key_org),
                            'request_uri' => $this->decoded_request_uri,
                            'docid' => $this->documentIdentifier
                        ]
                    )
                        , "Missing parse link tag(ResourceID:{$this->documentIdentifier})");
                }
            } else {
                $value = $this->getIdFromAlias($key);
                if (!$value) {
                    $value = '';
                }
            }

            if ($modifiers !== false) {
                $value = $this->applyFilter($value, $modifiers, $key);
            }
            $replace[$i] = $value;
        }
        $content = str_replace($matches[0], $replace, $content);
        return $content;
    }

    function getConfig($name = '', $default = '')
    {
        if (!isset($this->config[$name])) {
            if ($default === '') {
                return false;
            }
            return $default;
        }
        return $this->config[$name];
    }

    public function getChunk($chunk_name)
    {
        $chunk_name = trim($chunk_name);
        if ($chunk_name === '') {
            return false;
        }

        if (isset($this->chunkCache[$chunk_name])) {
            return $this->_return_chunk_value(
                $chunk_name
                , $this->chunkCache[$chunk_name]
                , true
            );
        }

        if (strpos($chunk_name, '@FILE') === 0) {
            return $this->_return_chunk_value(
                $chunk_name
                , $this->atBindFile($chunk_name)
                , false
            );
        }

        return $this->_return_chunk_value(
            $chunk_name
            , !$this->hasChunk($chunk_name) ? '' : $this->chunkCache[$chunk_name]
            , false
        );
    }

    public function hasChunk($chunk_name)
    {
        static $db = null;
        if ($db === null) {
            $db = [];
            $rs = db()->select(
                'name,snippet,published'
                , '[+prefix+]site_htmlsnippets'
                , 'published=1'
            );
            while ($row = db()->getRow($rs)) {
                $db[$row['name']] = $row;
                $this->chunkCache[$row['name']] = $row['snippet'];
            }
        }
        return isset($db[$chunk_name]);
    }

    private function _return_chunk_value($chunk_name, $value, $isCache)
    {
        $params = [
            'name' => $chunk_name,
            'value' => $value,
            'isCache' => $isCache
        ];
        $this->invokeEvent('OnCallChunk', $params);
        return $value;

    }

    function parseChunk($chunkName, $ph, $left = '{', $right = '}', $mode = 'chunk')
    {
        if (!is_array($ph)) {
            return false;
        }

        $tpl = ($mode === 'chunk') ? $this->getChunk($chunkName) : $chunkName;

        if (strpos($tpl, '{{') !== false) {
            return $this->parseText($tpl, $ph, '{{', '}}');
        }
        return $this->parseText($tpl, $ph, $left, $right);
    }

    function parseText($tpl = '', $ph = [], $left = '[+', $right = '+]', $execModifier = true)
    {
        if (is_array($tpl) && !is_array($ph)) {
            list($tpl, $ph) = [$ph, $tpl];
        } // ditto->paginate()

        if (is_array($tpl)) {
            $tpl = join('', $tpl);
        }

        if (strpos($tpl, '@') === 0) {
            $tpl = $this->atBind($tpl);
        }

        if (!$ph || !$tpl) {
            return $tpl;
        }

        if (strpos($tpl, '<@LITERAL>') !== false) {
            $tpl = $this->escapeLiteralTagsContent($tpl);
        }
        $matches = $this->getTagsFromContent($tpl, $left, $right);
        if (!$matches) {
            return $tpl;
        }

        foreach ($matches[1] as $i => $key) {
            if (strpos($key, ':') !== false && $execModifier) {
                list($key, $modifiers) = $this->splitKeyAndFilter($key);
            } else {
                $modifiers = false;
            }

            if (strpos($key, '@') !== false) {
                list($key, $context) = explode('@', $key, 2);
            } else {
                list($key, $context) = [$key, ''];
            }

            if (!isset($ph['parent'])) {
                $ph['parent'] = false;
            }

            if ($key === '') {
                $key = 'value';
            }

            if (!isset($ph[$key]) && !$context) {
                continue;
            } elseif ($context) {
                $value = $this->_contextValue("{$key}@{$context}", $ph['parent']);
            } else {
                $value = $ph[$key];
            }

            if ($modifiers !== false) {
                if (strpos($modifiers, $left) !== false) {
                    $modifiers = $this->parseText($modifiers, $ph, $left, $right);
                }
                $value = $this->applyFilter($value, $modifiers, $key);
            }
            $tpl = str_replace($matches[0][$i], $value, $tpl);
        }

        return $tpl;
    }

    function parseList($tpl = '', $multiPH = [])
    {

        if (empty($multiPH) || empty($tpl)) {
            return $tpl;
        }
        if (strpos($tpl, '@') === 0) {
            $tpl = $this->atBind($tpl);
        }

        foreach ($multiPH as $ph) {
            $_[] = $this->parseText($tpl, $ph);
        }
        return implode("\n", $_);
    }

    function toDateFormat($timestamp = 0, $mode = '')
    {
        if ($timestamp == 0 && $mode === '') {
            return '';
        }

        $timestamp = trim($timestamp);
        $timestamp = (int)$timestamp + $this->config['server_offset_time'];

        switch ($this->config['datetime_format']) {
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

        if (empty($mode)) {
            $strTime = $this->mb_strftime($dateFormat . " %H:%M:%S", $timestamp);
        } elseif ($mode == 'dateOnly') {
            $strTime = $this->mb_strftime($dateFormat, $timestamp);
        } elseif ($mode == 'formatOnly') {
            $strTime = $dateFormat;
        }
        return $strTime;
    }

    function toTimeStamp($str)
    {
        $str = trim($str);
        if (empty($str)) {
            return '';
        }
        if (preg_match('@^[0-9]+$@', $str)) {
            return $str;
        }

        switch ($this->config['datetime_format']) {
            case 'YYYY/mm/dd':
                if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}[0-9 :]*$/', $str)) {
                    return '';
                }
                list ($Y, $m, $d, $H, $M, $S) = sscanf($str, '%4d/%2d/%2d %2d:%2d:%2d');
                break;
            case 'dd-mm-YYYY':
                if (!preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{4}[0-9 :]*$/', $str)) {
                    return '';
                }
                list ($d, $m, $Y, $H, $M, $S) = sscanf($str, '%2d-%2d-%4d %2d:%2d:%2d');
                break;
            case 'mm/dd/YYYY':
                if (!preg_match('/^[0-9]{2}\/[0-9]{2}\/[0-9]{4}[0-9 :]*$/', $str)) {
                    return '';
                }
                list ($m, $d, $Y, $H, $M, $S) = sscanf($str, '%2d/%2d/%4d %2d:%2d:%2d');
                break;
        }
        if (!$H && !$M && !$S) {
            $H = 0;
            $M = 0;
            $S = 0;
        }
        $timeStamp = mktime($H, $M, $S, $m, $d, $Y);
        $timeStamp = (int)$timeStamp;
        return $timeStamp;
    }

    function mb_strftime($format = '%Y/%m/%d', $timestamp = '')
    {
        global $modx, $_lc;

        if (stripos($format, '%a') !== false) {
            $modx->loadLexicon('locale');
        }

        if (isset($_lc['days.short'])) {
            $a = explode(',', $_lc['days.short']);
        } else {
            $a = explode(',', 'Sun, Mon, Tue, Wed, Thu, Fri, Sat');
        }
        if (isset($_lc['days.wide'])) {
            $A = explode(',', $_lc['days.short']);
        } else {
            $A = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
        }
        $w = strftime('%w', $timestamp);
        $p = ['am' => 'AM', 'pm' => 'PM'];
        $P = ['am' => 'am', 'pm' => 'pm'];
        $ampm = (strftime('%H', $timestamp) < 12) ? 'am' : 'pm';
        if ($timestamp === '') {
            return '';
        }
        if (strpos(PHP_OS, 'WIN') === 0) {
            $format = str_replace('%-', '%#', $format);
        }
        $pieces = preg_split('@(%[\-#]?[a-zA-Z%])@', $format, null, PREG_SPLIT_DELIM_CAPTURE);

        $str = '';
        foreach ($pieces as $v) {
            if ($v === '%a') {
                $str .= $a[$w];
            } elseif ($v === '%A') {
                $str .= $A[$w];
            } elseif ($v === '%p') {
                $str .= $p[$ampm];
            } elseif ($v === '%P') {
                $str .= $P[$ampm];
            } elseif (strpos($v, '%') !== false) {
                $str .= strftime($v, $timestamp);
            } else {
                $str .= $v;
            }
        }
        return $str;
    }

    #::::::::::::::::::::::::::::::::::::::::
    # Added By: Raymond Irving - MODx
    #

    // Modified by Raymond for TV - Orig Modified by Apodigm - DocVars
    # returns a single TV record. $idnames - can be an id or name that belongs the template that the current document is using
    public function getTemplateVar($idname = '', $fields = '*', $docid = '', $published = 1)
    {
        if ($idname == '') {
            return false;
        }

        $result = $this->getTemplateVars([$idname], $fields, $docid, $published, '', '');
        return ($result != false) ? $result['0'] : false;
    }

    # returns an array of TV records. $idnames - can be an id or name that belongs the template that the current document is using
    public function getTemplateVars($idnames = '*', $fields = '*', $docid = '', $published = 1, $sort = 'rank', $dir = 'ASC')
    {
        // get document record
        if ($docid === '' && $this->documentIdentifier) {
            $docid = $this->documentIdentifier;
        }

        if ($this->array_get($this->previewObject, 'template')) {
            $resource = $this->getDocument($docid, '*', null); //Ignore published when the preview.
            $resource['template'] = $this->previewObject['template'];
        } elseif ($docid == $this->documentIdentifier) {
            $resource = $this->documentObject;
        } else {
            $resource = $this->getDocument($docid, '*', $published);
        }

        if (!$resource) {
            return false;
        }
        if (!$resource['template']) {
            foreach ($resource as $key => $value) {
                if ($idnames === '*' || (is_string($idnames) && in_array($key, explode(',', $idnames)))) {
                    $result[] = ['name' => $key, 'value' => $value];
                }
            }
            return $result;
        }

        if ($fields === '*' || $fields === '') {
            $fields = "tv.*, IF(tvc.value!='',tvc.value,tv.default_text) as value";
        } else {
            $fields = sprintf(
                "%s, IF(tvc.value!='',tvc.value,tv.default_text) as value"
                , array_map(
                    function ($v) {
                        return 'tv.' . $v;
                    }
                    , explode(',', $fields)
                )
            );
        }

        if (is_array($idnames) && !empty($idnames)) {
            $idnames = sprintf(
                "'%s'",
                implode("','", db()->escape($idnames))
            );
        }

        if ($idnames === '*') {
            $where = 'tv.id<>0';
        } elseif (preg_match('@^[1-9][0-9]*$@', $idnames)) {
            $where = sprintf('tv.id=%s', $idnames);
        } elseif (strpos($idnames, "'") === 0) {
            $where = sprintf("tv.name IN (%s)", $idnames);
        } else {
            $where = sprintf("tv.name='%s'", db()->escape($idnames));
        }

        $result = [];
        $rs = db()->select(
            $fields
            , [
                '[+prefix+]site_tmplvars tv',
                'INNER JOIN [+prefix+]site_tmplvar_templates tvtpl  ON tvtpl.tmplvarid = tv.id',
                sprintf(
                    "LEFT JOIN [+prefix+]site_tmplvar_contentvalues tvc ON tvc.tmplvarid=tv.id AND tvc.contentid='%s'"
                    , $docid
                )
            ]
            , sprintf('%s AND tvtpl.templateid=%s', $where, $resource['template'])
            , $sort ?
            sprintf('%s %s', $this->join(',', explode(',', $sort), 'tv.'), $dir)
            :
            ''
        );
        while ($row = db()->getRow($rs)) {
            $result[] = $row;
        }

        // get default/built-in template variables
        ksort($resource);
        foreach ($resource as $key => $value) {
            if ($idnames === '*' || in_array($key, explode(',', $idnames))) {
                $result[] = ['name' => $key, 'value' => $value];
            }
        }
        return $result;
    }

    # returns an associative array containing TV rendered output values. $idnames - can be an id or name that belongs the template that the current document is using
    function getTemplateVarOutput($idnames = '*', $docid = '', $published = 1, $sep = '')
    {
        if (is_array($idnames) && empty($idnames)) {
            return false;
        }

        if (is_string($idnames) && strpos($idnames, ',') !== false) {
            $idnames = explode(',', $idnames);
        }
        $vars = ($idnames == '*' || is_array($idnames)) ? $idnames : [$idnames];
        if (!preg_match('@^[1-9][0-9]*$@', $docid)) {
            $docid = $this->documentIdentifier;
        }
        $result = $this->getTemplateVars($vars, '*', $docid, $published, '', ''); // remove sort for speed
        if ($result == false) {
            return false;
        }

        $output = [];
        foreach ($result as $row) {
            if (!empty($this->previewObject[$row['name']]) && $docid == $this->documentIdentifier) {
                //Load preview
                $row['value'] = $this->previewObject[$row['name']];
            }

            if (!is_array($row['value'])) {
                $row['docid'] = $docid;
                if (isset($row['sep'])) {
                    $row['sep'] = $row['sep'];
                }
                $output[$row['name']] = $this->tvProcessor($row);
            } else {
                $row['value']['docid'] = $docid;
                $row['value']['sep'] = $sep;
                $output[$row['name']] = $this->tvProcessor($row['value']);
            }
        }
        return $output;
    }

    # returns the full table name based on db settings
    function getFullTableName($tbl)
    {
        return db()->getFullTableName($tbl);
    }

    # return placeholder value
    function getPlaceholder($name)
    {
        return $this->placeholders[$name];
    }

    # sets a value for a placeholder
    function setPlaceholder($name, $value)
    {
        $this->placeholders[$name] = $value;
    }

    # set arrays or object vars as placeholders
    function toPlaceholders($ph, $prefix = '')
    {
        if (is_object($ph)) {
            $ph = get_object_vars($ph);
        }
        if (is_array($ph)) {
            foreach ($ph as $key => $value) {
                $this->toPlaceholder($key, $value, $prefix);
            }
        }
    }

    function toPlaceholder($key, $value, $prefix = '')
    {
        if (is_array($value) || is_object($value)) {
            $this->toPlaceholders(
                $value
                , sprintf('%s%s.', $prefix, $key)
            );
        } else {
            $this->setPlaceholder(
                sprintf('%s%s', $prefix, $key)
                , $value
            );
        }
    }

    # returns the virtual relative path to the manager folder
    function getManagerPath()
    {
        return MODX_BASE_URL . 'manager/';
    }

    # returns the virtual relative path to the cache folder
    function getCachePath()
    {
        return MODX_BASE_URL . 'assets/cache/';
    }

    # Returns current user id
    function getLoginUserID($context = '')
    {
        if ($context && isset($_SESSION["{$context}Validated"])) {
            return $_SESSION["{$context}InternalKey"];
        }

        if ($this->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webInternalKey'];
        }

        if ($this->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return sessionv('mgrInternalKey', 0);
        }

        return false;
    }

    # Returns an array of document groups that current user is assigned to.
    # This function will first return the web user doc groups when running from frontend otherwise it will return manager user's docgroup
    # Set $resolveIds to true to return the document group names
    function getUserDocGroups($resolveIds = false)
    {
        $dg = []; // add so
        $dgn = [];

        if (sessionv('webDocgroups') && sessionv('webValidated') && $this->isFrontend()) {
            $dg = sessionv('webDocgroups');
            if (sessionv('webDocgrpNames')) {
                $dgn = sessionv('webDocgrpNames'); //add so
            }
        }

        if (sessionv('mgrDocgroups') && sessionv('mgrValidated')
            && ($this->isBackend() || config('allow_mgr2web'))) {
            $dg = array_merge($dg, sessionv('mgrDocgroups'));
            if (sessionv('mgrDocgrpNames')) {
                $dgn = array_merge($dgn, sessionv('mgrDocgrpNames'));
            }
        }

        if (!$resolveIds) {
            return $dg;
        }

        if (!$dg || $dgn) {
            return $dgn; // add so
        }

        if (!$dg || !is_array($dg)) {
            return false;
        }

        $ds = db()->select(
            'name'
            , '[+prefix+]documentgroup_names'
            , where_in('id', $dg)
        );

        $dgn = [];
        $i = 1;
        while ($row = db()->getRow($ds)) {
            $dgn[$i] = $row['name'];
            $i++;
        }
        // cache docgroup names to session
        if ($this->isFrontend()) {
            $_SESSION['webDocgrpNames'] = $dgn;
        } else {
            $_SESSION['mgrDocgrpNames'] = $dgn;
        }
        return $dgn;
    }

    # Remove unwanted html tags and snippet, settings and tags
    function stripTags($html, $allowed = '')
    {
        $t = strip_tags($html, $allowed);
        $t = preg_replace('~\[\*(.*?)\*]~', '', $t); //tv
        $t = preg_replace('~\[\[(.*?)]]~', '', $t); //snippet
        $t = preg_replace('~\[!(.*?)!]~', '', $t); //snippet
        $t = preg_replace('~\[\((.*?)\)]~', '', $t); //settings
        $t = preg_replace('~\[\+(.*?)\+]~', '', $t); //placeholders
        $t = preg_replace('~{{(.*?)}}~', '', $t); //chunks
        return $t;
    }

    # remove all event listners - only for use within the current execution cycle
    function removeAllEventListener()
    {
        unset ($this->pluginEvent);
        $this->pluginEvent = [];
    }

    # invoke an event. $extParams - hash array: name=>value
    public function invokeEvent($evtName, &$extParams = [])
    {
        if ($this->debug) {
            $fstart = $this->getMicroTime();
        }
        $return = true;
        if ($this->safeMode) {
            $return = false;
        }
        if (!$evtName) {
            $return = false;
        }
        if (!isset($this->pluginEvent[$evtName])) {
            $return = false;
        }
        if (
            isset($this->pluginEvent[$evtName])
            &&
            count($this->pluginEvent[$evtName]) == 0
        ) {
            $return = [];
        }
        if (empty($return)) {
            if ($this->debug) {
                $this->addLogEntry('$modx->' . __FUNCTION__ . "({$evtName})", $fstart);
            }
            return $return;
        }

        if (!$this->pluginCache) {
            $this->getPluginCache();
        }

        $preEventName = $this->event->name;
        $this->event->name = $evtName;
        $results = [];
        foreach ($this->pluginEvent[$evtName] as $pluginName) {
            if ($this->debug) {
                $fstart = $this->getMicroTime();
            }
            $pluginName = stripslashes($pluginName);

            // reset event object
            $this->event->_resetEventObject();
            $preCm = $this->event->cm;
            $this->event->cm = $this->loadExtension('ConfigMediation');

            // get plugin code and properties
            $pluginCode = $this->getPluginCode($pluginName);
            $pluginProperties = $this->getPluginProperties($pluginName);

            // load default params/properties
            $parameter = $this->parseProperties($pluginProperties);
            if (!empty($extParams)) {
                foreach ($extParams as $k => $v) {
                    $parameter[$k] = $v;
                }
            }

            // eval plugin
            $this->event->activePlugin = $pluginName;
            $output = $this->evalPlugin($pluginCode, $parameter);
            if ($output) {
                $this->event->cm->addOutput($output);
            }
            $this->event->activePlugin = '';
            if ($this->debug) {
                $this->addLogEntry('$modx->' . __FUNCTION__ . "({$evtName},{$pluginName})", $fstart);
            }

            $this->event->setAllGlobalVariables();
            if ($this->event->_output != '') {
                $results[] = $this->event->_output;
            } /* deprecation */
            if ($this->event->cm->hasOutput) {
                $results[] = $this->event->cm->showOutput();
            }
            foreach ($extParams as $key => $val) {
                $tmp = $this->event->cm->getParam($key);
                if ($val != $tmp) {
                    $extParams[$key] = $tmp;
                }
            }
            $cm = $this->event->cm;
            unset($cm);
            $this->event->cm = $preCm;
            if ($this->event->_propagate != true) {
                break;
            }
        }
        $this->event->name = $preEventName;
        return $results;
    }

    function getPluginCode($pluginName)
    {
        if (!isset ($this->pluginCache[$pluginName])) {
            $this->setPluginCache($pluginName);
        }
        return $this->pluginCache[$pluginName];
    }

    function getPluginProperties($pluginName)
    {
        if (!isset ($this->pluginCache["{$pluginName}Props"])) {
            $this->setPluginCache($pluginName);
        }
        return $this->pluginCache["{$pluginName}Props"];
    }

    function setPluginCache($pluginName)
    {
        if (isset($this->pluginCache[$pluginName])) {
            $this->pluginCache["{$pluginName}Props"] = '';
            return;
        }
        $result = db()->select(
            '*',
            '[+prefix+]site_plugins',
            [
                where('name', '=', $pluginName),
                'AND disabled=0'
            ]
        );
        if (db()->count($result) == 1) {
            $row = db()->getRow($result);
            $code = $row['plugincode'];
            $properties = $row['properties'];
        } else {
            $code = 'return false;';
            $properties = '';
        }
        $this->pluginCache[$pluginName] = $code;
        $this->pluginCache["{$pluginName}Props"] = $properties;
    }

    # parses a resource property string and returns the result as an array
    function parseProperties($propertyString)
    {
        if (!$propertyString) {
            return [];
        }

        $parameter = [];
        $tmpParams = explode('&', $propertyString);
        foreach ($tmpParams as $tmpParam) {
            if (strpos($tmpParam, '=') === false) {
                continue;
            }
            $pTmp = explode('=', $tmpParam);
            $pvTmp = explode(';', trim($pTmp['1']));
            if ($pvTmp['1'] === 'list' && $pvTmp['3'] != '') {
                $parameter[trim($pTmp['0'])] = $pvTmp['3']; //list default
            } elseif ($pvTmp['1'] !== 'list' && $pvTmp['2'] != '') {
                $parameter[trim($pTmp['0'])] = $pvTmp['2'];
            }
        }
        foreach ($parameter as $k => $v) {
            $parameter[$k] = str_replace(['%3D', '%26'], ['=', '&'], $v);
        }
        return $parameter;
    }

    /*
    * Template Variable Display Format
    * Created by Raymond Irving Feb, 2005
    */
    // Added by Raymond 20-Jan-2005
    function tvProcessor($value, $format = '', $paramstring = '', $name = '', $tvtype = '', $docid = '', $sep = '')
    {
        $modx = &$this;

        if (is_array($value)) {
            if (isset($value['docid'])) {
                $docid = $value['docid'];
            }
            if (isset($value['sep'])) {
                $sep = $value['sep'];
            }
            $format = $value['display'];
            $paramstring = $value['display_params'];
            $name = $value['name'];
            $tvtype = $value['type'];
            $value = $value['value'];
        }
        // process any TV commands in value
        $docid = (int)$docid ? (int)$docid : $this->documentIdentifier;
        switch ($tvtype) {
            case 'dropdown':
            case 'listbox':
            case 'listbox-multiple':
            case 'checkbox':
            case 'option':
                $src = $tvtype;
                $values = explode('||', $value);
                foreach ($values as $i => $v) {
                    if (strpos($v, '<?php') === 0) {
                        $v = "@@EVAL\n" . substr($v, 6);
                    }
                    if (strpos($v, '@') === 0) {
                        $values[$i] = $this->ProcessTVCommand($v, $name, $docid, $src);
                    }
                }
                $value = implode('||', $values);
                break;
            default:
                $src = 'docform';
                if (strpos($value, '@') === 0) {
                    $value = $this->ProcessTVCommand($value, $name, $docid, $src);
                }
        }

        $params = [];
        if ($paramstring) {
            $cp = explode('&', $paramstring);
            foreach ($cp as $v) {
                $ar = explode('=', trim($v));
                if (!is_array($ar) || count($ar) != 2) {
                    continue;
                }
                if (strpos($ar[1], '%') !== false) {
                    $params[$ar[0]] = $this->decodeParamValue($ar[1]);
                } else {
                    $params[$ar[0]] = $ar[1];
                }
            }
        }

        if (!$value) {
            if ($format !== 'custom_widget' && $format !== 'richtext' && $format !== 'datagrid') {
                return $value;
            }

            if ($format === 'datagrid' && $params['egmsg'] === '') {
                return '';
            }
        }

        $id = "tv{$name}";
        $o = '';
        switch ($format) {
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
                $o = include MODX_CORE_PATH . 'docvars/outputfilter/' . $format . '.inc.php';
                break;
            default:
                if (db()->isResult($value)) {
                    $value = $this->parseInput($value);
                }
                if ($tvtype === 'checkbox' || $tvtype === 'listbox-multiple') {
                    // add separator
                    $value = explode('||', $value);
                    $value = join($sep, $value);
                }
                $o = $value;
                break;
        }
        return $o;
    }

    function applyFilter($value = '', $modifiers = false, $key = '')
    {
        if ($modifiers === false || $modifiers === 'raw') {
            return $value;
        }
        $this->loadExtension('MODIFIERS');
        return $this->filter->phxFilter($key, $value, trim($modifiers));
    }

    function addSnippet($name, $phpCode, $params = [])
    {
        if (strpos($phpCode, '@') === 0) {
            $phpCode = $this->atBind($phpCode);
        }
        $this->snippetCache["#{$name}"] = $phpCode;
        $this->snippetCache["#{$name}Props"] = $params;
    }

    function addChunk($name, $text)
    {
        if (strpos($text, '@') === 0) {
            $text = $this->atBind($text);
        }
        $this->chunkCache['#' . $name] = $text;
    }

    function addFilter($name, $phpCode)
    {
        $this->snippetCache['phx:' . $name] = $phpCode;
    }

    function cleanUpMODXTags($content = '')
    {
        $_ = ['[* *]', '[( )]', '{{ }}', '[[ ]]', '[+ +]'];
        foreach ($_ as $brackets) {
            list($left, $right) = explode(' ', $brackets);
            if (strpos($content, $left) === false) {
                continue;
            }
            $matches = $this->getTagsFromContent($content, $left, $right);
            $content = str_replace($matches[0], '', $content);
        }
        if (strpos($content, '<!---->') !== false) {
            $content = str_replace('<!---->', '', $content);
        }
        return $content;
    }

    // - deprecated db functions
    function dbConnect()
    {
        db()->connect();
        $this->rs = $this->db->conn;
    }

    function dbQuery($sql)
    {
        return db()->query($sql);
    }

    function recordCount($rs)
    {
        return db()->count($rs);
    }

    function fetchRow($rs, $mode = 'assoc')
    {
        return db()->getRow($rs, $mode);
    }

    function affectedRows($rs)
    {
        return db()->getAffectedRows($rs);
    }

    function insertId($rs)
    {
        return db()->getInsertId($rs);
    }

    function dbClose()
    {
        db()->disconnect();
    }

    function putChunk($chunkName)
    {
        return $this->getChunk($chunkName);
    }

    function getDocGroups()
    {
        return $this->getUserDocGroups();
    }

    function changePassword($o, $n)
    {
        return $this->changeWebUserPassword($o, $n);
    }

    function parsePlaceholder($src = '', $ph = [], $left = '[+', $right = '+]', $mode = 'ph')
    {
        return $this->parseText($src, $ph, $left, $right, $mode);
    }


    /***************************************************************************************/
    /* End of API functions                                       */
    /***************************************************************************************/

    function phpError($nr, $text, $file, $line)
    {
        if (error_reporting() == 0 || $nr == 0) {
            return true;
        }
        if ($this->stopOnNotice == false) {
            switch ($nr) {
                case E_NOTICE:
                case E_USER_NOTICE :
                    if ($this->error_reporting <= 2) {
                        return true;
                    }
                    break;
                case E_STRICT:
                case E_DEPRECATED:
                case E_USER_DEPRECATED:
                    if ($this->error_reporting <= 1) {
                        return true;
                    }
                    break;
                default:
                    if ($this->error_reporting === 0) {
                        return true;
                    }
            }
        }

        if (is_readable($file)) {
            $source = file($file);
            $source = $source[$line - 1];
        } else {
            $source = '';
        } //Error $nr in $file at $line: <div><code>$source</code></div>
        $result = $this->messageQuit('PHP Parse Error', '', true, $nr, $file, $source, $text, $line);
        if ($result === false) {
            exit();
        }
        return $result;
    }

    function mergeRegisteredClientScripts($content)
    {
        if(!$this->jscripts) {
            return $content;
        }
        return str_ireplace(
            '</body>',
            join("\n", $this->jscripts) . "\n</body>",
            $content
        );
    }

    function mergeRegisteredClientStartupScripts($content)
    {
        if(!$this->sjscripts) {
            return $content;
        }
        return str_ireplace(
            '</head>',
            join("\n", $this->sjscripts) . "\n</head>",
            $content
        );
    }

    /**
     * Format alias to be URL-safe. Strip invalid characters.
     *
     * @param string Alias to be formatted
     * @return string Safe alias
     */
    function stripAlias($alias, $browserID = '')
    {
        // let add-ons overwrite the default behavior
        $params = ['alias' => &$alias, 'browserID' => $browserID];
        $this->event->vars = $params;
        $_ = $alias;
        $results = $this->invokeEvent('OnStripAlias', $params);
        $this->event->vars = [];
        if ($alias !== $_) {
            $this->event->output($alias);
        }

        //if multiple plugins are registered, only the last one is used
        if ($results) {
            return end($results);
        }

        return strip_tags($alias);
    }

    function nicesize($size)
    {
        $a = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pos = 0;
        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, 2) . ' ' . $a[$pos];
    }

    public function getIdFromAlias($aliasPath = '')
    {
        static $cache = [];

        $aliasPath = trim($aliasPath, '/');

        if (isset($cache[$aliasPath])) {
            return $cache[$aliasPath];
        }

        $cache[$aliasPath] = false;

        if (empty($aliasPath)) {
            return $this->config['site_start'];
        }

        if ($this->config['use_alias_path']) {
            if (strpos($aliasPath, '/') !== false) {
                $_a = explode('/', $aliasPath);
            } else {
                $_a = [$aliasPath];
            }

            $parent = 0;
            foreach ($_a as $alias) {
                $rs = db()->select(
                    'id'
                    , '[+prefix+]site_content'
                    , sprintf(
                        "deleted=0 AND parent='%s' AND alias=BINARY '%s'"
                        , $parent
                        , db()->escape($alias)
                    )
                );
                if (!db()->count($rs)) {
                    if (!preg_match('@^[1-9][0-9]*$@', db()->escape($alias))) {
                        return false;
                    }
                    $rs = db()->select(
                        'id'
                        , '[+prefix+]site_content'
                        , sprintf(
                            "deleted=0 AND parent='%s' AND id='%s'"
                            , $parent
                            , db()->escape($alias)
                        )
                    );
                }
                $row = db()->getRow($rs);
                if (!$row) {
                    return false;
                }
                $parent = $row['id'];
            }
            $cache[$aliasPath] = $parent;
            return $parent;
        }

        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , [
                where('alias', '=', $aliasPath),
                'AND deleted=0'
            ]
            , 'parent, menuindex'
        );
        $row = db()->getRow($rs);
        if (!$row) {
            if (!preg_match('@^[1-9][0-9]*$@', db()->escape($aliasPath))) {
                return false;
            }
            $rs = db()->select(
                'id'
                , '[+prefix+]site_content'
                , [
                    where('id', '=', $aliasPath),
                    'AND deleted=0'
                ]
            );
            $row = db()->getRow($rs);
        }
        if (!$row) {
            return false;
        }

        $cache[$aliasPath] = $row['id'];
        return $row['id'];
    }

    public function getIdFromUrl($url = '')
    {
        $url = preg_replace(
            '@' . $this->config('friendly_url_suffix') . '$@'
            , ''
            , trim($url)
        );
        if (strpos($url, '/') === 0) {
            $url = preg_replace('@^' . MODX_BASE_URL . '@', '', $url);
        }
        if (substr($url, 0, 4) === 'http') {
            $url = preg_replace('@^' . MODX_SITE_URL . '@', '', $url);
        }
        return $this->getIdFromAlias(trim($url, '/'));
    }

    /*
     * ファイル作成
     *
     * 一時ファイルを作成後、リネームしてファイルを作成する。
     * file_put_contentでファイル作成中に max_execution_time が経過するとファイルを破壊することがあるため。
     * 入力はチェックしないため注意。APIとしての利用は非推奨。
     *
     * @param $filename 保存先のパスとファイル名
     * @param $data     保存内容
     * @return bool
     *
     */
    function saveToFile($filename, $data)
    {
        if (!$filename) {
            return false;
        }

        $tmp = MODX_BASE_PATH . 'assets/cache/.tmp_' . md5(uniqid(getmypid() . '_', true));
        if (is_file($tmp) && !is_writable($tmp)) {
            chmod($tmp, 0666);
        }

        if (@file_put_contents($tmp, $data, LOCK_EX)) {
            return rename($tmp, $filename);
        }
        return false;
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
    function setBaseTime($t = '')
    {
        if (!$t) {
            $baseTime = isset($_REQUEST['baseTime']) ? $_REQUEST['baseTime'] : '';
            if (!empty($baseTime) && $this->isLoggedin()) {
                $t = $baseTime;
            } else {
                $this->baseTime = request_time();
                return true;
            }
        }
        if (self::isInt($t, 1)) {
            $this->baseTime = $t;
        } else {
            $tmp = $this->toTimeStamp($t);
            if (empty($tmp)) {
                return false;
            }
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
    function getBaseTime()
    {
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
    private static function isInt($param, $min = null, $max = null)
    {
        if (!preg_match('/\A[0-9]+\z/', $param)) {
            return false;
        }
        if ($min !== null && preg_match('/\A[0-9]+\z/', $min) && $param < $min) {
            return false;
        }
        if ($max !== null && preg_match('/\A[0-9]+\z/', $max) && $param > $max) {
            return false;
        }
        return true;
    }

    public function gotoSetup()
    {
        if (strpos(serverv('SCRIPT_NAME'), 'install/index.php') !== false) {
            return false;
        }

        if (strpos(serverv('SCRIPT_NAME'), 'install/connection.') !== false) {
            return false;
        }

        if (is_file(MODX_BASE_PATH . 'install/index.php')) {
            header(
                sprintf('Location: %sinstall/index.php?action=mode', MODX_SITE_URL)
            );
            exit();
        }

        exit('Not installed.');
    }

    function htmlspecialchars($str, $flags = ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401, $encode = null, $double_encode = true)
    {
        return $this->hsc($str, $flags, $encode, $double_encode);
    }

    function hsc($str, $flags = ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401, $encode = null, $double_encode = true)
    {
        if (!$str) {
            return $str;
        }

        if (is_array($str)) {
            foreach ($str as $k => $v) {
                $str[$k] = $this->hsc($v, $flags, $encode, $double_encode);
            }
            return $str;
        }

        if (!$encode) {
            $encode = $this->config('modx_charset', 'utf-8');
        }

        $ent_str = htmlspecialchars($str, $flags, $encode, $double_encode);

        if ($str && $ent_str == '') {
            $ent_str = $this->hsc(
                mb_convert_encoding($str, $encode, implode(',', mb_detect_order())),
                $flags, $encode, $double_encode
            );
        }

        return $ent_str;
    }

    function reload()
    {
        $url = $this->makeUrl($this->docid);
        $this->sendRedirect($url);
        exit;
    }

    function move_uploaded_file($tmp_path, $target_path)
    {
        $target_path = str_replace('\\', '/', $target_path);
        $new_file_permissions = octdec(ltrim($this->config['new_file_permissions'], '0'));

        if (strpos($target_path, $this->config['filemanager_path']) !== 0) {
            $msg = "Can't upload to '{$target_path}'.";
            $this->logEvent(1, 3, $msg, 'move_uploaded_file');
        }

        $img = getimagesize($tmp_path);
        switch ($img[2]) {
            case IMAGETYPE_JPEG:
                if (stripos($target_path, '.jpeg') !== false) {
                    $ext = '.jpeg';
                } else {
                    $ext = '.jpg';
                }
                break;
            case IMAGETYPE_PNG:
                $ext = '.png';
                break;
            case IMAGETYPE_GIF:
                $ext = '.gif';
                break;
            case IMAGETYPE_BMP:
                $ext = '.bmp';
                break;
            default:
                $ext = '';
        }
        if ($ext) {
            $target_path = substr($target_path, 0, strrpos($target_path, '.')) . $ext;
        }

        $limit_width = $this->config('image_limit_width');
        if (!$limit_width || $img[0] <= $limit_width || !$ext) {
            if (move_uploaded_file($tmp_path, $target_path)) {
                @chmod($target_path, octdec($this->config['new_file_permissions']));
                return true;
            }
            $this->logEvent(
                1
                , 3
                , implode("<br>\n", [
                    '$tmp_path = ' . $tmp_path,
                    '$target_path = ' . $target_path,
                    sprintf(
                        '$image_limit_width = %s'
                        , $this->config('image_limit_width', '- not set -')
                    ),
                    sprintf(
                        '$target_is_writable = %s'
                        , is_writable(dirname($target_path)) ? 'true' : 'false'
                    ),
                    'getimagesize = ' . print_r($img, true)
                ])
                , 'move_uploaded_file'
            );
            return false;
        }

        $limit_height = (int)(($img[1] / $img[0]) * $limit_width);
        $new_image = imagecreatetruecolor($limit_width, $limit_height);
        switch ($img[2]) {
            case IMAGETYPE_JPEG:
                $rs = imagecopyresampled(
                    $new_image
                    , imagecreatefromjpeg($tmp_path)
                    , 0
                    , 0
                    , 0
                    , 0
                    , $limit_width
                    , $limit_height
                    , $img[0]
                    , $img[1]
                );
                if (!$rs) {
                    return false;
                }
                $rs = imagejpeg($new_image, $target_path, 83);
                break;
            case IMAGETYPE_PNG:
                $rs = imagecopyresampled(
                    $new_image
                    , imagecreatefrompng($tmp_path)
                    , 0
                    , 0
                    , 0
                    , 0
                    , $limit_width
                    , $limit_height
                    , $img[0]
                    , $img[1]
                );
                if (!$rs) {
                    return false;
                }
                $rs = imagepng($new_image, $target_path);
                break;
            case IMAGETYPE_GIF:
                $rs = imagecopyresampled(
                    $new_image
                    , imagecreatefromgif($tmp_path)
                    , 0
                    , 0
                    , 0
                    , 0
                    , $limit_width
                    , $limit_height
                    , $img[0]
                    , $img[1]
                );
                if (!$rs) {
                    return false;
                }
                $rs = imagepng($new_image, $target_path);
                break;
            case IMAGETYPE_BMP:
                $rs = imagecopyresampled(
                    $new_image
                    , imagecreatefromwbmp($tmp_path)
                    , 0
                    , 0
                    , 0
                    , 0
                    , $limit_width
                    , $limit_height
                    , $img[0]
                    , $img[1]
                );
                if (!$rs) {
                    return false;
                }
                $rs = imagepng($new_image, $target_path);
                break;
            default:
        }
        imagedestroy($new_image);
        if ($rs) {
            @chmod($target_path, $new_file_permissions);
            return $rs;
        }
        return false;
    }

    public function input_get($key = null, $default = null)
    {
        return $this->array_get($_GET, $key, $default);
    }

    public function input_post($key = null, $default = null)
    {
        return $this->array_get($_POST, $key, $default);
    }

    public function input_cookie($key = null, $default = null)
    {
        return $this->array_get($_COOKIE, $key, $default);
    }

    public function input_any($key = null, $default = null)
    {
        return anyv($key, $default);
    }

    public function server($key = null, $default = null)
    {
        if (!isset($_SERVER)) {
            return $default;
        }
        return $this->array_get($_SERVER, strtoupper($key), $default);
    }

    public function server_var($key = null, $default = null)
    {
        return $this->server($key, $default);
    }

    public function session($key = null, $default = null)
    {
        if (strpos($key, '*') === 0) {
            $_SESSION[ltrim($key, '*')] = $default;
            return $default;
        }
        if (!isset($_SESSION)) {
            return $default;
        }
        return $this->array_get($_SESSION, $key, $default);
    }

    public function session_var($key = null, $default = null)
    {
        return $this->session($key, $default);
    }

    public function global_var($key = null, $default = null)
    {
        if (strpos($key, '*') === 0 || strpos($key, '.*') !== false) {
            $value = $default;
            $this->array_set($GLOBALS, $key, $value);
            return $value;
        }
        if (!isset($GLOBALS)) {
            return $default;
        }
        return $this->array_get($GLOBALS, $key, $default);
    }

    public function config($key = null, $default = null)
    {
        if (!defined('MODX_SETUP_PATH')) {
            if (!isset($this->config['site_url']) || !$this->config['site_url']) {
                $this->getSettings();
            }
        }
        if (strpos($key, '*') === 0 || strpos($key, '.*') !== false) {
            $value = $default;
            $this->array_set($this->config, $key, $value);
            return $value;
        }
        return $this->array_get($this->config, $key, $default);
    }

    public function doc($key = null, $default = null)
    {
        if (!$this->documentObject) {
            return $default;
        }
        if (strpos($key, '*') === 0 || strpos($key, '.*') !== false) {
            $value = $default;
            $this->array_set($this->documentObject, $key, $value);
            return $value;
        }
        return $this->array_get($this->documentObject, $key, $default);
    }

    public function output($string = null)
    {
        if ($string !== null) {
            $this->documentOutput = $string;
        }
        return $this->documentOutput;
    }

    public function conf_var($key = null, $default = null)
    {
        return $this->config($key, $default);
    }

    function array_get($array, $key = null, $default = null)
    {
        if ($key === null || trim($key) == '') {
            return $array;
        }

        static $cache = [];
        $cachekey = md5(print_r(func_get_args(), true));
        if (isset($cache[$cachekey]) && $cache[$cachekey] !== null) {
            return $cache[$cachekey];
        }

        if (isset($array[$key])) {
            $cache[$cachekey] = $array[$key];
            return $array[$key];
        }
        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if (!is_array($array) || !isset($array[$segment])) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    // from Laravel Arr::set()
    public function array_set(&$array, $key, $value)
    {
        if ($key === null) {
            return $array = $value;
        }
        $key = ltrim('*', $key);
        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $key = array_shift($keys);
            $key = ltrim('*', $key);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    public function get_docfield_type($field_name = '')
    {
        $type = [];
        $type['datetime'] = 'published,createdon,editedon,publishedon,deletedon,pub_date,unpub_date';
        $type['title'] = 'pagetitle,longtitle,menutitle';
        $type['content'] = 'content,description,introtext';
        $type['user'] = 'createdby,editedby,publishedby,deletedby';
        $type['permission'] = 'privateweb,privatemgr';
        $type['navi'] = 'alias,menuindex,hidemenu,link_attributes,alias_visible';
        $type['document'] = 'id,parent,isfolder,template,type,contentType,content_dispo';
        $type['status'] = 'richtext,searchable,cacheable,deleted,donthit';
        $type['deprecated'] = 'haskeywords,hasmetatags';

        if (in_array($field_name, explode(',', $type['datetime']), true)) {
            return 'datetime';
        }

        if (in_array($field_name, explode(',', $type['title']), true)) {
            return 'title';
        }

        if (in_array($field_name, explode(',', $type['content']), true)) {
            return 'content';
        }

        if (in_array($field_name, explode(',', $type['user']), true)) {
            return 'user';
        }

        if (in_array($field_name, explode(',', $type['permission']), true)) {
            return 'permission';
        }

        if (in_array($field_name, explode(',', $type['navi']), true)) {
            return 'navi';
        }

        if (in_array($field_name, explode(',', $type['document']), true)) {
            return 'document';
        }

        if (in_array($field_name, explode(',', $type['status']), true)) {
            return 'status';
        }

        if (in_array($field_name, explode(',', $type['deprecated']), true)) {
            return 'deprecated';
        }

        return false;
    }

    // End of class.
    public function html_tag($tag_name, $attrib = [], $content = null)
    {
        $tag_name = trim($tag_name, '<>');
        if (!$attrib && !$content) {
            return sprintf('<%s>', $tag_name);
        }
        if (is_array($attrib)) {
            foreach ($attrib as $k => $v) {
                if ($v === null) {
                    $attrib[$k] = sprintf('%s', $k);
                } elseif (is_bool($v)) {
                    $attrib[$k] = sprintf('%s="%s"', $k, (int)$v);
                } elseif ($v === '') {
                    unset($attrib[$k]);
                } else {
                    $attrib[$k] = sprintf('%s="%s"', $k, $v);
                }
            }
        }
        if ($content === null) {
            return sprintf('<%s %s>', $tag_name, implode(' ', $attrib));
        }
        if (is_array($content)) {
            $content = implode("\n", $content);
        }
        return sprintf(
            '<%s%s>%s</%s>'
            , $tag_name
            , $attrib ? ' ' . implode(' ', $attrib) : ''
            , $content
            , $tag_name
        );
    }

    public function real_ip()
    {
        return real_ip();
    }
}
