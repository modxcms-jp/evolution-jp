<?php

$this->sub = new SubParser();

class SubParser {
    public function __construct()
    {
    }

    function sendmail($params=array(), $msg='') {
        global $modx;
        $p = array();
        if(isset($params) && is_string($params)) {
            if(strpos($params,'=')===false) {
                if(strpos($params,'@')!==false) {
                    $p['to'] = $params;
                } else {
                    $p['subject'] = $params;
                }
            } else {
                $params_array = explode(',',$params);
                foreach($params_array as $k=>$v) {
                    $k = trim($k);
                    $p[$k] = trim($v);
                }
            }
        } else {
            $p = $params;
        }
        if(isset($p['sendto'])) {
            $p['to'] = $p['sendto'];
        }

        if(isset($p['to']) && preg_match('@^[0-9]+$@',$p['to'])) {
            $userinfo = $modx->getUserInfo($p['to']);
            $p['to'] = $userinfo['email'];
        }
        if(isset($p['from']) && preg_match('@^[0-9]+$@',$p['from'])) {
            $userinfo = $modx->getUserInfo($p['from']);
            $p['from']     = $userinfo['email'];
            $p['fromname'] = $userinfo['username'];
        }
        if($msg==='' && !isset($p['body'])) {
            $p['body'] = $_SERVER['REQUEST_URI'] . "\n" . $_SERVER['HTTP_USER_AGENT'] . "\n" . $_SERVER['HTTP_REFERER'];
        } elseif(is_string($msg) && 0<strlen($msg)) {
            $p['body'] = $msg;
        }

        $modx->loadExtension('MODxMailer');
        $sendto = !isset($p['to']) ? $modx->config['emailsender']  : $p['to'];
        $sendto = explode(',',$sendto);
        foreach($sendto as $address) {
            list($name, $address) = $modx->mail->address_split($address);
            $modx->mail->AddAddress($address,$name);
        }
        if(isset($p['cc'])) {
            $p['cc'] = explode(',',$p['cc']);
            foreach($p['cc'] as $address) {
                list($name, $address) = $modx->mail->address_split($address);
                $modx->mail->AddCC($address,$name);
            }
        }
        if(isset($p['bcc'])) {
            $p['bcc'] = explode(',',$p['bcc']);
            foreach($p['bcc'] as $address) {
                list($name, $address) = $modx->mail->address_split($address);
                $modx->mail->AddBCC($address,$name);
            }
        }
        if(isset($p['replyto'])) {
            list($name, $address) = $modx->mail->address_split($p['replyto']);
            $modx->mail->addReplyTo($address,$name);
        }

        if(isset($p['from']) && strpos($p['from'],'<')!==false && substr($p['from'],-1)==='>') {
            list($p['fromname'], $p['from']) = $modx->mail->address_split($p['from']);
        }
        $modx->mail->From     = !isset($p['from']) ? $modx->config['emailsender']  : $p['from'];
        $modx->mail->FromName = !isset($p['fromname']) ? $modx->config['site_name'] : $p['fromname'];
        $modx->mail->Subject  = !isset($p['subject']) ? $modx->config['emailsubject'] : $p['subject'];
        $modx->mail->Body     = $p['body'];
        if (isset($p['type']) && $p['type'] === 'text') {
            $this->mail->IsHTML(false);
        }
        return $modx->mail->send();
    }

    function rotate_log($target='event_log',$limit=2000, $trim=100) {
        global $modx;

        if($limit < $trim) {
            $trim = $limit;
        }

        $count = $modx->db->getValue($modx->db->select('COUNT(id)',"[+prefix+]{$target}"));
        $over = $count - $limit;
        if(0 < $over) {
            $trim = ($over + $trim);
            $modx->db->delete("[+prefix+]{$target}",'','',$trim);
        }
        if( config('automatic_optimize') == 1 ){
            $rs = $modx->db->query(
                sprintf('SHOW TABLE STATUS FROM `%s`',trim($modx->db->dbname,'`'))
            );
            while ($row = $modx->db->getRow($rs)) {
                $modx->db->query('OPTIMIZE TABLE ' . $row['Name']);
            }
        }
    }

    function addLog($title='no title',$msg='',$type=1){
        if($title==='') {
            $title = 'no title';
        }
        if(is_array($msg)) {
            $msg = sprintf('<pre>%s</pre>', print_r($msg, true));
        }
        $this->logEvent(
            0
            , $type
            , $msg ? $msg : $_SERVER['REQUEST_URI']
            , $title
        );
    }

    function logEvent($evtid, $type, $msg, $title= 'Parser'){
        global $modx;
        if(!$modx->db->isConnected()) {
            return;
        }
        if(!$modx->config) {
            $modx->getSettings();
        }
        $evtid= (int)$evtid;
        $type = (int)$type;
        if ($type < 1) {
            $type = 1;
        } // Types: 1 = information, 2 = warning, 3 = error
        if (3 < $type) {
            $type = 3;
        }
        if($modx->db->isConnected()) {
            $msg = $modx->db->escape($msg);
        }
        $title = htmlspecialchars($title, ENT_QUOTES, $modx->config['modx_charset']);
        if($modx->db->isConnected()) {
            $title = $modx->db->escape($title);
        }
        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 50 , $modx->config['modx_charset']);
        } else {
            $title = substr($title, 0, 50);
        }
        $LoginUserID = $modx->getLoginUserID();
        if (!$LoginUserID) {
            $LoginUserID = '0';
        }

        $fields['eventid']     = $evtid;
        $fields['type']        = $type;
        $fields['createdon']   = $_SERVER['REQUEST_TIME'];
        $fields['source']      = $title;
        $fields['description'] = $msg;
        $fields['user']        = $LoginUserID;
        $_ = $modx->db->lastQuery;
        if($modx->db->isConnected()) {
            $insert_id = $modx->db->insert($fields, '[+prefix+]event_log');
        } else {
            $title = 'DB connect error';
        }
        $modx->db->lastQuery = $_;
        if(isset($modx->config['send_errormail']) && $modx->config['send_errormail'] !== '0') {
            if($modx->config['send_errormail'] <= $type) {
                $body['URL'] = $modx->config['site_url'] . ltrim($_SERVER['REQUEST_URI'],'/');
                $body['Source'] = $fields['source'];
                $body['IP'] = $_SERVER['REMOTE_ADDR'];
                if(!empty($_SERVER['REMOTE_ADDR'])) $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
                if(!empty($hostname)) {
                    $body['Host name'] = $hostname;
                }
                if(!empty($modx->event->activePlugin)) {
                    $body['Plugin'] = $modx->event->activePlugin;
                }
                if(!empty($modx->currentSnippet)) {
                    $body['Snippet'] = $modx->currentSnippet;
                }
                $subject = 'Error mail from ' . $modx->config['site_name'];
                foreach($body as $k=>$v) {
                    $mailbody[] = sprintf('[%s] %s', $k, $v);
                }
                $mailbody = implode("\n",$mailbody);
                $modx->sendmail($subject,$mailbody);
            }
        }
        if (!isset($insert_id) || !$insert_id) {
            exit('Error while inserting event log into database.');
        }

        $trim = (isset($modx->config['event_log_trim']))  ? (int)$modx->config['event_log_trim'] : 100;
        if(($insert_id % $trim) == 0) {
            $limit = (isset($modx->config['event_log_limit'])) ? (int)$modx->config['event_log_limit'] : 2000;
            $modx->rotate_log('event_log',$limit,$trim);
        }
    }

    function clearCache($params=array()) {
        global $modx;

        if(!is_array($params) && preg_match('@^[1-9][0-9]*$@',$params)) {
            $docid = $params;
            if($modx->config['cache_type']==='2') {
                $url = $modx->config['base_url'] . $modx->makeUrl($docid,'','','root_rel');
                $filename = hash('crc32b', $url);
            } else {
                $filename = "docid_{$docid}";
            }

            $_ = array('pages','pc','smartphone','tablet','mobile');
            foreach($_ as $uaType) {
                $page_cache_path = MODX_BASE_PATH . "assets/cache/{$uaType}/{$filename}.pageCache.php";
                if(is_file($page_cache_path)) unlink($page_cache_path);
            }
        }

        if(is_string($params) && $params==='full') {
            $params = array();
            $params['showReport'] = false;
            $params['target'] = 'pagecache,sitecache';
        }

        if(opendir(MODX_BASE_PATH . 'assets/cache')!==false) {
            $showReport = ($params['showReport']) ? $params['showReport'] : false;
            $target = ($params['target']) ? $params['target'] : 'pagecache,sitecache';

            include_once MODX_CORE_PATH . 'cache_sync.class.php';
            $sync = new synccache();
            $sync->setCachepath(MODX_BASE_PATH . 'assets/cache/');
            $sync->setReport($showReport);
            $sync->setTarget($target);
            $sync->emptyCache(); // first empty the cache
            return true;
        }
        return false;
    }

    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='') {
        global $modx;

        $version= isset ($GLOBALS['version']) ? $GLOBALS['version'] : '';
        $release_date= isset ($GLOBALS['release_date']) ? $GLOBALS['release_date'] : '';
        $ua          = $modx->hsc($_SERVER['HTTP_USER_AGENT']);
        $referer     = $modx->hsc($_SERVER['HTTP_REFERER']);
        if ($is_error) {
            $str = '<h3 style="color:red">&laquo; MODX Parse Error &raquo;</h3>
                    <table border="0" cellpadding="1" cellspacing="0">
                    <tr><td colspan="2">MODX encountered the following error while attempting to parse the requested resource:</td></tr>
                    <tr><td colspan="2"><b style="color:red;">&laquo; ' . $msg . ' &raquo;</b></td></tr>';
        } else {
            $str = '<h3 style="color:#003399">&laquo; MODX Debug/ stop message &raquo;</h3>
                    <table border="0" cellpadding="1" cellspacing="0">
                    <tr><td colspan="2">The MODX parser recieved the following debug/ stop message:</td></tr>
                    <tr><td colspan="2"><b style="color:#003399;">&laquo; ' . $msg . ' &raquo;</b></td></tr>';
        }

        $codetpl = '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">[+code+]</div></td></tr>';

        if (!empty ($query)) {
            $str .= $modx->parseText($codetpl, array('code' => $query));
        }

        $errortype= array (
            E_ERROR             => "ERROR",
            E_WARNING           => "WARNING",
            E_PARSE             => "PARSING ERROR",
            E_NOTICE            => "NOTICE",
            E_CORE_ERROR        => "CORE ERROR",
            E_CORE_WARNING      => "CORE WARNING",
            E_COMPILE_ERROR     => "COMPILE ERROR",
            E_COMPILE_WARNING   => "COMPILE WARNING",
            E_USER_ERROR        => "USER ERROR",
            E_USER_WARNING      => "USER WARNING",
            E_USER_NOTICE       => "USER NOTICE",
            E_STRICT            => "STRICT NOTICE",
            E_RECOVERABLE_ERROR => "RECOVERABLE ERROR",
            E_DEPRECATED        => "DEPRECATED",
            E_USER_DEPRECATED   => "USER DEPRECATED"
        );

        $tpl = '<tr><td valign="top">[+left+]</td><td>[+right+]</td></tr>';
        if($nr || $file) {
            $str .= '<tr><td colspan="2"><b>PHP error debug</b></td></tr>';
            if ($text != '') {
                $str .= $modx->parseText($codetpl, array('code' => "Error : {$text}"));
            }
            if($output!='') {
                $str .= $modx->parseText($codetpl, array('code' => $output));
            }
            if(!isset($errortype[$nr])) {
                $errortype[$nr] = '';
            }
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left'=>'ErrorType[num] : ',
                    'right'=> sprintf('%s[%s]', $errortype[$nr], $nr)
                )
            );
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left'=>'File : ',
                    'right'=>$file
                )
            );
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left'=>'Line : ',
                    'right'=>$line
                )
            );
        }

        if ($source != '') {
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left' => 'Source : ',
                    'right' => $source)
            );
        }

        if ($modx->db->lastQuery) {
            $str .= $modx->parseText(
                $tpl, array(
                    'left' => 'LastQuery : ',
                    'right' => $modx->hsc($modx->db->lastQuery)
                )
            );
        }

        $str .= '<tr><td colspan="2"><b>Basic info</b></td></tr>';

        $str .= '<tr><td valign="top" style="white-space:nowrap;">REQUEST_URI : </td>';
        $str .= sprintf('<td>%s</td>', htmlspecialchars(urldecode($_SERVER['REQUEST_URI']), ENT_QUOTES, $modx->config['modx_charset']));
        $str .= '</tr>';

        if(isset($_GET['a'])) {
            $action = $_GET['a'];
        } elseif(isset($_POST['a'])) {
            $action = $_POST['a'];
        }
        if(isset($action) && !empty($action)) {
            include_once(MODX_CORE_PATH . 'actionlist.inc.php');
            global $action_list;
            if(isset($action_list[$action])) {
                $actionName = " - {$action_list[$action]}";
            } else {
                $actionName = '';
            }
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left'=>'Manager action : ',
                    'right'=> $action . $actionName
                )
            );
        }

        if(preg_match('@^[0-9]+@',$modx->documentIdentifier)) {
            $resource  = $modx->getDocumentObject('id',$modx->documentIdentifier);
            $url = $modx->makeUrl($modx->documentIdentifier);
            $link = '<a href="' . $url . '" target="_blank">' . $resource['pagetitle'] . '</a>';
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left'=>'Resource : ',
                    'right'=> sprintf('[%s]%s', $modx->documentIdentifier, $link)
                )
            );
        }

        if($modx->currentSnippet) {
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left' => 'Current Snippet : ',
                    'right' => $modx->currentSnippet
                )
            );
        }

        if($modx->event->activePlugin) {
            $str .= $modx->parseText(
                $tpl
                , array(
                    'left' => 'Current Plugin : ',
                    'right' => sprintf('%s(%s)', $modx->event->activePlugin, $modx->event->name)
                )
            );
        }

        $str .= $modx->parseText($tpl,array('left'=>'Referer : '    , 'right'=>$referer));
        $str .= $modx->parseText($tpl,array('left'=>'User Agent : ' , 'right'=>$ua));
        $str .= $modx->parseText($tpl,array('left'=>'IP : '         , 'right'=>$_SERVER['REMOTE_ADDR']));

        $str .= '<tr><td colspan="2"><b>Benchmarks</b></td></tr>';

        $str .= $modx->parseText(
            $tpl
            , array(
                'left'=>'MySQL : ',
                'right'=>'[^qt^] ([^q^] Requests)'
            )
        );
        $str .= $modx->parseText(
            $tpl
            , array(
                'left'=>'PHP : ',
                'right'=>'[^p^]'
            )
        );
        $str .= $modx->parseText(
            $tpl
            , array(
                'left'=>'Total : ',
                'right'=>'[^t^]'
            )
        );
        $str .= $modx->parseText(
            $tpl
            , array(
                'left'=>'Memory : ',
                'right'=>'[^m^]'
            )
        );

        $str .= "</table>\n";

        $str = $modx->mergeBenchmarkContent($str);

        if(isset($php_errormsg) && !empty($php_errormsg)) {
            $str = "<b>{$php_errormsg}</b><br />\n{$str}";
        }
        $str .= '<br />' . $modx->get_backtrace() . "\n";


        // Log error
        if($modx->currentSnippet) {
            $source = 'Snippet - ' . $modx->currentSnippet;
        } elseif($modx->event->activePlugin) {
            $source = 'Plugin - ' . $modx->event->activePlugin;
        } elseif($source!=='') {
            $source = 'Parser - ' . $source;
        } elseif($query!=='') {
            $source = 'SQL Query';
        } else {
            $source = 'Parser';
        }

        if(isset($actionName) && !empty($actionName)) {
            $source .= $actionName;
        }
        switch($nr) {
            case E_DEPRECATED :
            case E_USER_DEPRECATED :
            case E_STRICT :
            case E_NOTICE :
            case E_USER_NOTICE :
                $error_level = 2;
                break;
            default:
                $error_level = 3;
        }
        $modx->logEvent(0, $error_level, $str,$source);
        if($modx->error_reporting==='99' && !isset($_SESSION['mgrValidated'])) {
            return true;
        }

        // Set 500 response header
        if(2 < $error_level && $modx->event->name!=='OnWebPageComplete') {
            header('HTTP/1.1 500 Internal Server Error');
        }

        // Display error
        if ($modx->isLoggedin()) {
            if($modx->event->name!=='OnWebPageComplete') {
                echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
                echo sprintf('<html><head><title>MODX Content Manager %s &raquo; %s</title>', $version, $release_date);
                echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
                echo sprintf('<link rel="stylesheet" type="text/css" href="%smanager/media/style/%s/style.css" />', $modx->config['site_url'], $modx->config['manager_theme']);
                echo '<style type="text/css">body { padding:10px; } td {font:inherit;}</style>';
                echo '</head><body>';
            }
            echo '<div style="text-align:left;">'.$str.'</div>';
            if($modx->event->name!=='OnWebPageComplete')
                echo '</body></html>';
        } else {
            echo 'Error';
        }
        ob_end_flush();

        exit;
    }

    function recDebugInfo(){
        global $modx;

        $incs = get_included_files();
        $backtrace = array_reverse(debug_backtrace());
        $i=0;
        foreach($incs as $v) {
            $incs[$i] = str_replace('\\','/',$v);
            $i++;
        }
        $i=0;
        foreach($backtrace as $v) {
            if(isset($v['object'])) {
                unset($backtrace[$i]['object']);
            }
            if(isset($v['file'])) {
                $backtrace[$i]['file'] = str_replace('\\', '/', $v['file']);
            }
            if(isset($v['args'])&&empty($v['args'])) {
                unset($backtrace[$i]['args']);
            }
            if($v['class']==='DocumentParser'&&$v['type']==='->') {
                unset($backtrace[$i]['file']);
                unset($backtrace[$i]['class']);
                unset($backtrace[$i]['type']);
                $backtrace[$i]['function'] = '$modx->'.$v['function'] . '()';
            } elseif(isset($v['class'])) {
                if(strpos($v['file'],'document.parser.class.inc.php')!==false) {
                    unset($backtrace[$i]['file']);
                }
                unset($backtrace[$i]['class']);
                unset($backtrace[$i]['type']);
                $backtrace[$i]['function'] = $v['class'] . $v['type'] .$v['function'] . '()';
            }
            $i++;
        }

        $tend = $modx->getMicroTime();
        $totaltime = $tend - $modx->tstart;
        $totaltimemsg = sprintf('Total time %2.4f s',$totaltime);
        $info['request_uri']    = $modx->decoded_request_uri;
        if(isset($modx->documentIdentifier)) {
            $info['docid'] = $modx->documentIdentifier;
        }
        $info['Total time']     = $totaltimemsg;
        $info['included_files'] = print_r($incs,true);
        $info['backtrace']      = print_r($backtrace,true);
        $info['functions']      = print_r($modx->functionLog,true);
        $msg = '<pre>' . print_r($info,true) .'</pre>';
        $this->addLog('Debug log',$msg,1);
    }

    function get_backtrace(){
        global $modx;
        $str = "<p><b>Backtrace</b></p>\n";
        $str  .= '<table>';
        $backtrace = array_reverse(debug_backtrace());
        foreach ($backtrace as $key => $val) {
            $key++;
            if (substr($val['function'],0,11)==='messageQuit') {
                break;
            }
            if(substr($val['function'],0,8)==='phpError') {
                break;
            }
            $path = str_replace('\\','/',$val['file']);
            if(strpos($path,MODX_BASE_PATH)===0) {
                $path = substr($path, strlen(MODX_BASE_PATH));
            }
            switch($val['type']) {
                case '->':
                case '::':
                    if($val['class']==='DocumentParser'&&$val['type']==='->')
                        $val['class'] = '$modx';
                    $functionName = $val['function'] = $val['class'] . $val['type'] . $val['function'];
                    break;
                default:
                    $functionName = $val['function'];
            }
            if($functionName==='evalSnippet' && $modx->currentSnippet) {
                $functionName .= sprintf('(%s)', $modx->currentSnippet);
            }
            $str .= '<tr><td valign="top">' . $key . "</td>";
            $str .= sprintf(
                '<td>%s()<br />%s on line %s</td>'
                , $functionName
                , $path
                , $val['line']
            );
        }
        $str .= '</table>';
        return $str;
    }

    function sendRedirect($url='', $count_attempts= 0, $type= 'REDIRECT_HEADER',$responseCode='') {
        global $modx;

        if($modx->debug) {
            register_shutdown_function(array(& $modx, 'recDebugInfo'));
        }

        if($type==='REDIRECT_HEADER') {
            $modx->config['xhtml_urls'] = 0;
        }

        if(empty($url)) {
            $url = $modx->makeUrl($modx->documentIdentifier, '', '', 'full');
        } elseif(preg_match('@^[1-9][0-9]*$@',$url)) {
            $url = $modx->makeUrl($url,'','','full');
        } else {
            if(strpos($url,'[')!==false || strpos($url,'{{')!==false) {
                $url = $modx->parseDocumentSource($url);
            }

            if(strpos($url, '?') === 0) {
                $url = $modx->makeUrl($modx->documentIdentifier, '', $url, 'full');
            } elseif(preg_match('@^[1-9][0-9]*$@',$url)) {
                $url = $modx->makeUrl($url);
            } elseif(preg_match('@^[1-9][0-9]*\?@',$url)) {
                list($url,$args) = explode('?',$url,2);
                $url = $modx->makeUrl($url,'',$args,'full');
            }

            if(strpos($url,'[~')!==false) {
                $url = $modx->rewriteUrls($url);
            }

        }

        if ($count_attempts == 1) {
            // append the redirect count string to the url
            $currentNumberOfRedirects= isset ($_REQUEST['err']) ? $_REQUEST['err'] : 0;
            if ($currentNumberOfRedirects > 3) {
                $modx->messageQuit(
                    "Redirection attempt failed - please ensure the document you're trying to redirect to exists. <p>Redirection URL: <i>{$url}</i></p>"
                );
                exit;
            }

            $currentNumberOfRedirects += 1;
            if (strpos($url, '?') > 0) {
                $url .= '&';
            } else {
                $url .= '?';
            }
            $url .= "err={$currentNumberOfRedirects}";
        }
        if ($type === 'REDIRECT_REFRESH') {
            $header = "Refresh: 0;URL={$url}";
        } elseif ($type !== 'REDIRECT_META') {
            // check if url has /$base_url
            global $base_url, $site_url;
            if (substr($url, 0, 2) !== '//' && substr($url, 0, strlen($base_url)) == $base_url) {
                // append $site_url to make it work with Location:
                $url = $site_url . substr($url, strlen($base_url));
            }
            if (strpos($url, "\n") === false) {
                $header = 'Location: ' . $url;
            } else {
                $modx->messageQuit('No newline allowed in redirect url.');
            }
        } else {
            echo '<META HTTP-EQUIV="Refresh" CONTENT="0; URL=' . $url . '" />';
            exit;
        }

        if($modx->directParse==1) {
            if($_SERVER['HTTP_USER_AGENT']) {
                ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']);
            }
            return file_get_contents($url);
        }

        if (strpos($responseCode, '301') !== false) {
            header($header, true, 301);
            exit;
        }
        if (strpos($responseCode, '302') !== false) {
            header($header, true, 302);
            exit;
        }
        if (strpos($responseCode, '303') !== false) {
            header($header, true, 303);
            exit;
        }
        if (strpos($responseCode, '307') !== false) {
            header($header, true, 307);
            exit;
        }
        if($responseCode) {
            header($header, true, $responseCode);
            exit;
        }
        header($header);
        exit;
    }

    function sendForward($id, $responseCode= ''){
        global $modx;

        if ($modx->forwards) {
            $modx->forwards--;
            $modx->documentIdentifier= $id;
            if($responseCode) header($responseCode);
            echo $modx->prepareResponse();
            exit;
        }
        $modx->messageQuit("Internal Server Error id={$id}");
        header('HTTP/1.0 500 Internal Server Error');
        echo '<h1>ERROR: Too many forward attempts!</h1><p>The request could not be completed due to too many unsuccessful forward attempts.</p>';
        exit;
    }

    function sendUnavailablePage(){
        global $modx;
        if($modx->config['site_unavailable_page']) {
            $dist = $modx->config['site_unavailable_page'];
        } else {
            $dist = $modx->config['site_start'];
        }
        $modx->http_status_code = '503';
        $modx->sendForward($dist, 'HTTP/1.0 503 Service Unavailable');
    }

    function sendErrorPage()
    {
        global $modx;
        
        // invoke OnPageNotFound event
        $modx->invokeEvent('OnPageNotFound');
        
        if($modx->config['error_page']) $dist = $modx->config['error_page'];
        else                            $dist = $modx->config['site_start'];
        
        $modx->http_status_code = '404';
        $modx->sendForward($dist, $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
    }
    
    function sendUnauthorizedPage()
    {
        global $modx;
        
        // invoke OnPageUnauthorized event
        $_REQUEST['refurl'] = $modx->documentIdentifier;
        $modx->invokeEvent('OnPageUnauthorized');
        
        if($modx->config['unauthorized_page']) $dist = $modx->config['unauthorized_page'];
        elseif($modx->config['error_page'])    $dist = $modx->config['error_page'];
        else                                   $dist = $modx->config['site_start'];
        
        $modx->http_status_code = '403';
        $modx->sendForward($dist , 'HTTP/1.1 403 Forbidden');
    }

    function getSnippetId()
    {
        global $modx;
        
        if ($modx->currentSnippet)
        {
            $snip = $modx->db->escape($modx->currentSnippet);
            $rs= $modx->db->select('id', '[+prefix+]site_snippets', "name='{$snip}'",'',1);
            $row= @ $modx->db->getRow($rs);
            if ($row['id']) return $row['id'];
        }
        return 0;
    }
    
    function getSnippetName()
    {
        global $modx;
        
        return $modx->currentSnippet;
    }
    
    function runSnippet($snippetName, $params= array ())
    {
        global $modx;
        
        if (isset ($modx->snippetCache[$snippetName]))
        {
            $phpCode= $modx->snippetCache[$snippetName];
            $properties= $modx->snippetCache["{$snippetName}Props"];
        }
        else
        { // not in cache so let's check the db
            $esc_name = $modx->db->escape($snippetName);
            $result= $modx->db->select('name,snippet,properties','[+prefix+]site_snippets',"name='{$esc_name}'");
            if ($modx->db->getRecordCount($result) == 1)
            {
                $row = $modx->db->getRow($result);
                $phpCode= $modx->snippetCache[$snippetName]= $row['snippet'];
                $properties= $modx->snippetCache["{$snippetName}Props"]= $row['properties'];
            }
            else
            {
                $phpCode= $modx->snippetCache[$snippetName]= "return false;";
                $properties= '';
            }
        }
        // load default params/properties
        $parameters= $modx->parseProperties($properties);
        $parameters= array_merge($parameters, $params);
        // run snippet
        return $modx->evalSnippet($phpCode, $parameters);
    }
    
    # Change current web user's password - returns true if successful, oterhwise return error message
    function changeWebUserPassword($oldPwd, $newPwd)
    {
        global $modx;
        
        if ($_SESSION['webValidated'] != 1) return false;
        
        $uid = $modx->getLoginUserID();
        $ds = $modx->db->select('id,username,password', '[+prefix+]web_users', "`id`='{$uid}'");
        $total = $modx->db->getRecordCount($ds);
        if ($total != 1) return false;
        
        $row= $modx->db->getRow($ds);
        if ($row['password'] == md5($oldPwd))
        {
            if (strlen($newPwd) < 6) return 'Password is too short!';
            if ($newPwd == '')       return "You didn't specify a password for this user!";

            $f = array();
            $f['password'] = md5($newPwd);
            $f['cachepwd'] = '';
            $f = $modx->db->escape($f);
            $modx->db->update($f, '[+prefix+]web_users', "id='{$uid}'");
            $modx->db->update("blockeduntil='0'", '[+prefix+]web_user_attributes', "internalKey='{$uid}'");
            // invoke OnWebChangePassword event
            $tmp = array(
                'userid' => $row['id'],
                'username' => $row['username'],
                'userpassword' => $newPwd
            );
            $modx->invokeEvent('OnWebChangePassword',$tmp);
            return true;
        }

        return 'Incorrect password.';
    }
    
    # add an event listner to a plugin - only for use within the current execution cycle
    function addEventListener($evtName, $pluginName)
    {
        global $modx;
        
        if(!$evtName || !$pluginName) return false;
        
        if (!isset($modx->pluginEvent[$evtName])) {
            $modx->pluginEvent[$evtName] = array();
        }
        
        $result = array_push($modx->pluginEvent[$evtName], $pluginName);
        
        return $result; // return array count
    }
    
    # remove event listner - only for use within the current execution cycle
    function removeEventListener($evtName, $pluginName='') {
        global $modx;
        
        if (!$evtName)
            return false;
        
        if ( $pluginName == '' ) {
            unset ($modx->pluginEvent[$evtName]);
            return true;
        }

        foreach($modx->pluginEvent[$evtName] as $key => $val){
            if ($modx->pluginEvent[$evtName][$key] == $pluginName){
                unset ($modx->pluginEvent[$evtName][$key]);
                return true;
            }
        }

        return false;
    }

    function regClientCSS($src, $media='')
    {
        global $modx;
        
        if (empty($src) || isset ($modx->loadedjscripts[$src])) return '';
        
        $nextpos = max(array_merge(array(0),array_keys($modx->sjscripts)))+1;
        
        $modx->loadedjscripts[$src]['startup'] = true;
        $modx->loadedjscripts[$src]['version'] = '0';
        $modx->loadedjscripts[$src]['pos']     = $nextpos;
        
        if (strpos(strtolower($src), '<style') !== false || strpos(strtolower($src), '<link') !== false)
        {
            $modx->sjscripts[$nextpos]= $src;
        }
        else
        {
            $media = $media ? 'media="' . $media . '" ' : '';
            $modx->sjscripts[$nextpos] = "\t" . '<link rel="stylesheet" type="text/css" href="'.$src.'" '.$media.'/>';
        }
    }

    # Registers Client-side JavaScript     - these scripts are loaded at the end of the page unless $startup is true
    function regClientScript($src, $options= array('name'=>'', 'version'=>'0', 'plaintext'=>false), $startup= false)
    {
        global $modx;
        
        if (empty($src)) return ''; // nothing to register
        
        if (!is_array($options))
        {
            if(is_bool($options))       $options = array('plaintext'=>$options);
            elseif(is_string($options)) $options = array('name'=>$options);
            else                        $options = array();
        }
        $name      = isset($options['name'])      ? strtolower($options['name']) : '';
        $version   = isset($options['version'])   ? $options['version'] : '0';
        $plaintext = isset($options['plaintext']) ? $options['plaintext'] : false;
        $key       = !empty($name)                ? $name : $src;
        
        $useThisVer= true;
        if (isset($modx->loadedjscripts[$key]))
        { // a matching script was found
            // if existing script is a startup script, make sure the candidate is also a startup script
            if ($modx->loadedjscripts[$key]['startup']) $startup= true;
            
            if (empty($name))
            {
                $useThisVer= false; // if the match was based on identical source code, no need to replace the old one
            }
            else
            {
                $useThisVer = version_compare($modx->loadedjscripts[$key]['version'], $version, '<');
            }
            
            if ($useThisVer)
            {
                if ($startup==true && $modx->loadedjscripts[$key]['startup']==false)
                { // remove old script from the bottom of the page (new one will be at the top)
                    unset($modx->jscripts[$modx->loadedjscripts[$key]['pos']]);
                }
                else
                { // overwrite the old script (the position may be important for dependent scripts)
                    $overwritepos= $modx->loadedjscripts[$key]['pos'];
                }
            }
            else
            { // Use the original version
                if ($startup==true && $modx->loadedjscripts[$key]['startup']==false)
                { // need to move the exisiting script to the head
                    $version= $modx->loadedjscripts[$key][$version];
                    $src= $modx->jscripts[$modx->loadedjscripts[$key]['pos']];
                    unset($modx->jscripts[$modx->loadedjscripts[$key]['pos']]);
                }
                else return ''; // the script is already in the right place
            }
        }
        
        if ($useThisVer && $plaintext!=true && (strpos(strtolower($src), "<script") === false))
        {
            $src= "\t" . '<script type="text/javascript" src="' . $src . '"></script>';
        }
        
        if ($startup)
        {
            $pos = isset($overwritepos) ? $overwritepos : max(array_merge(array(0),array_keys($modx->sjscripts)))+1;
            $modx->sjscripts[$pos]= $src;
        }
        else
        {
            $pos = isset($overwritepos) ? $overwritepos : max(array_merge(array(0),array_keys($modx->jscripts)))+1;
            $modx->jscripts[$pos]= $src;
        }
        $modx->loadedjscripts[$key]['version']= $version;
        $modx->loadedjscripts[$key]['startup']= $startup;
        $modx->loadedjscripts[$key]['pos']= $pos;
    }
    
    function regClientStartupHTMLBlock($html) // Registers Client-side Startup HTML block
    {
        $options = array('plaintext'=>true);
        $startup = true;
        $this->regClientScript($html, $options, $startup);
    }
    
    function regClientHTMLBlock($html) // Registers Client-side HTML block
    {
        $options = array('plaintext'=>true);
        $startup = false;
        $this->regClientScript($html, $options, $startup);
    }
    
    # Registers Startup Client-side JavaScript - these scripts are loaded at inside the <head> tag
    function regClientStartupScript($src, $options=array('name'=>'', 'version'=>'0', 'plaintext'=>false))
    {
        $startup = true;
        $this->regClientScript($src, $options, $startup);
    }
    
    function checkPermissions($docid=false,$duplicateDoc = false) {
        global $modx;
        
        if(strpos($docid, ',') !== false)
            $docid = substr($docid, 0, strpos($docid, ','));
        
        $allowroot = $modx->config['udperms_allowroot'];

        if($modx->hasPermission('save_role'))       return true; // administrator - grant all document permissions
        elseif($docid == 0 && $allowroot == 1)      return true;
        elseif(empty($modx->config['use_udperms'])) return true; // permissions aren't in use
        elseif($docid===false)                      return false;
        
        $rs = $modx->db->select('parent', '[+prefix+]site_content', "id='{$docid}'");
        $parent = $modx->db->getValue($rs);
        if ($this->duplicateDoc == true && $parent == 0 && $allowroot == 0) {
            return false; // deny duplicate document at root if Allow Root is No
        }

        // get document groups for current user
        if (isset($_SESSION['mgrDocgroups']) && !empty($_SESSION['mgrDocgroups'])) {
            foreach($_SESSION['mgrDocgroups'] as $v)
            {
                $docgrp[] = "dg.document_group='{$v}'";
            }
            $docgrps = join(' || ', $docgrp);
            $where_docgrp = "({$docgrps} || sc.privatemgr = 0)";
        }
        else $where_docgrp = 'sc.privatemgr = 0';
        
        $field = 'COUNT(DISTINCT sc.id)';
        $from   = '[+prefix+]site_content sc';
        $from  .= ' LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
        $from  .= ' LEFT JOIN [+prefix+]documentgroup_names dgn ON dgn.id = dg.document_group';
        $where = "sc.id='{$docid}' AND {$where_docgrp}";

        $rs = $modx->db->select($field, $from, $where);
        $total = $modx->db->getRecordCount($rs);
        
        if ($total == 1) return true;
        return false;
    }
    
    /*
     * Template Variable Data Source @Bindings
     * Created by Raymond Irving Feb, 2005
     */

    function ProcessTVCommand($input, $name = '', $docid = '', $src='docform') {
        global $modx;
        $docid = (int)$docid ? (int)$docid : $modx->documentIdentifier;
        $input = trim($input);
        
        if(substr($input,0,1)==='@' && $modx->config['enable_bindings']!=1 && $src==='docform')
            return '@Bindings is disabled.';

        list ($CMD, $param) = $this->splitTVCommand($input);
        $CMD = '@'.trim($CMD);
        $param = trim($param);
        switch ($CMD) {
            case '@PARSE' :
            case '@MODX' :
                if(strpos($param,'[!')!==false)
                    $param = str_replace(array('[!','!]'),array('[[',']]'),$param);
                if(strpos($param,'[*')!==false) $param = $modx->mergeDocumentContent($param);
                if(strpos($param,'[(')!==false) $param = $modx->mergeSettingsContent($param);
                if(strpos($param,'{{')!==false) $param = $modx->mergeChunkContent($param);
                if(strpos($param,'[[')!==false) $param = $modx->evalSnippets($param);
                $output = trim($param);
                break;
            case '@FILE' :
                if($modx->getExtention($param)==='.php') {
                    $output = 'Could not retrieve PHP file.';
                } else {
                    $output = @file_get_contents($param);
                }
                if($output===false) {
                    $output = " Could not retrieve document '{$param}'.";
                }
                break;
            case '@CHUNK' : // retrieve a chunk and process it's content
                $output = $modx->getChunk(trim($param));
                break;
            case '@DOCUMENT' : // retrieve a document and process it's content
            case '@DOC' :
                $rs = $modx->getDocument($param);
                if (is_array($rs)) $output = $rs['content'];
                else               $output = "Unable to locate document {$param}";
                break;
            case '@SELECT' : // selects a record from the cms database
                $ph = array (
                    'dbase' => $modx->db->config['dbase'],
                    'DBASE' => $modx->db->config['dbase'],
                    'prefix' => $modx->db->config['table_prefix'],
                    'PREFIX' => $modx->db->config['table_prefix']
                );
                $param = $modx->parseText($param,$ph);
                $rs = $modx->db->query("SELECT {$param}");
                if($modx->db->getRecordCount($rs)==0) return;
                $output = $rs;
                break;
            case '@EVAL' : // evaluates text as php codes return the results
                $output = eval ($param);
                break;
            case '@INHERIT' :
                $output = $param;
                if(empty($docid) && isset($_REQUEST['pid'])) $doc['parent'] = $_REQUEST['pid'];
                else                                         $doc = $modx->getPageInfo($docid, 0, 'id,parent');

                while ($doc['parent'] != 0) {
                    $doc = $modx->getPageInfo($doc['parent'], 0, 'id,parent');
                    $tv = $modx->getTemplateVar($name, '*', $doc['id'], null);
                    $value = (string) $tv['value'];
                    if ($value !== '' && strpos($value, '@INHERIT') !== 0) {
                        $output = $value;
                        break 2;
                    }
                }
                break;
            case '@DIRECTORY' :
            case '@DIR' :
                $files = array ();
                $param = trim($param,'/');
                $path = MODX_BASE_PATH . $param;
                if (!is_dir($path)) exit($path);
                
                $dir = dir($path);
                while (($file = $dir->read()) !== false) {
                    if (strpos($file, '.') !== 0) {
                        $files[] = "{$file}=={$param}{$file}";
                    }
                }
                asort($files);
                $output = implode('||', $files);
                break;
            case '@NULL' :
            case '@NONE' :
                $output = '';
                break;
            default :
                $output = $input;
                break;
        }
        // support for nested bindings
        if(is_string($output) && strpos($output, '@') === 0 && $output != $input)
            $output = $this->ProcessTVCommand($output, $name, $docid, $src);
        
        return $output;
    }

    // separate @ cmd from params
    function splitTVCommand($binding_string)
    {
        if(strpos($binding_string, '@') !== 0)      return array();
        if(strpos($binding_string,'@INHERIT')===0) return array('INHERIT','');
        
        if(strpos($binding_string,'@@EVAL')===0) {
            $binding_string = substr($binding_string, 1);
        }
        
        $BINDINGS = explode(',', 'PARSE,MODX,FILE,CHUNK,DOCUMENT,DOC,SELECT,EVAL,INHERIT,DIRECTORY,DIR,NULL,NONE');
        $binding_array = array();
        foreach($BINDINGS as $CMD) {
            if(strpos($binding_string,"@{$CMD}")!==0)
                continue;
            $code = substr($binding_string,strlen($CMD)+2);
            $binding_array = array($CMD,trim($code));
            break;
        }
        return $binding_array;
    }

    function getExtention($str)
    {
        $str = trim($str);
        $str = strtolower($str);
        $pos = strrpos($str,'.');
        if($pos===false) return false;
        return substr($str,$pos);
    }
    
    function decodeParamValue($s)
    {
        $s = str_replace(
            array('%3B', '%3D', '%26', '%2C', '%5C')
            , array(';', '=', '&', ',', '\\')
            , $s
        );

        return $s;
    }

    // returns an array if a delimiter is present. returns array is a recordset is present
    function parseInput($src, $delim='||', $type='string', $columns=true)
    { // type can be: string, array
        global $modx;
        
        if ($modx->db->isResult($src))
        {
            // must be a recordset
            $rows = array();
            $nc = $modx->db->numFields($src);
            while ($cols = $modx->db->getRow($src,'num'))
            {
                $rows[] = ($columns)? $cols : implode(' ',$cols);
            }
            return ($type=='array')? $rows : implode($delim,$rows);
        }

// must be a text
        if($type === 'array') {
            return explode($delim, $src);
        }
        return $src;
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
    
    // DISPLAY FORM ELEMENTS
    function renderFormElement($field_type, $field_id, $default_text='', $field_elements, $field_value, $field_style='', $row = array()) {
        global $modx,$_style,$_lang,$content;
        
        if(isset($content['id'])) {
            global $docObject;
            if($docObject) {
                $modx->documentObject = $docObject;
            } elseif(!isset($modx->documentObject)) {
                $modx->documentObject = $modx->getDocumentObject('id', $content['id']);
            }
            
            if(!isset($modx->documentIdentifier)) {
                $modx->documentIdentifier = $content['id'];
            }
        }
        
        if(strpos($field_elements, '<?php') === 0) {
            $field_elements = "@EVAL:\n" . substr($field_elements, 6);
        }
        if(strpos($field_elements, '@@EVAL') === 0) {
            $field_elements = "@EVAL:\n" . substr($field_elements, 7);
        }
        if(strpos($default_text, '<?php') === 0) {
            $default_text = "@@EVAL:\n" . substr($default_text, 6);
        }
        if(strpos($field_value, '<?php') === 0) {
            $field_value = "@@EVAL:\n" . substr($field_value, 6);
        }
        
        if(strpos($default_text, '@@EVAL') === 0 && $field_value===$default_text) {
            $eval_str = trim(substr($default_text, 7));
            $default_text = eval($eval_str);
            $field_value = $default_text;
        }

        if (in_array(strtolower($field_type),array('text','rawtext','email','number','zipcode','tel','url'))) {
            if($field_type === 'text') {
                $class = 'text';
            } elseif($field_type === 'number') {
                $class = 'text imeoff';
            } else {
                $class = "text {$field_type}";
            }
            return $modx->parseText(
                file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_text.tpl')
                , array(
                    'class'  => $class,
                    'id'     => 'tv' . $field_id,
                    'name'   => 'tv' . $field_id,
                    'value'  => $modx->hsc($field_value),
                    'style'  => $field_style,
                    'tvtype' => $field_type
                )
            );
        }

        if(in_array(strtolower($field_type),array('textarea','rawtextarea','htmlarea','richtext','textareamini'))) {
            return $modx->parseText(
                file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_textarea.tpl')
                , array(
                    'id'     => 'tv' . $field_id,
                    'name'   => 'tv' . $field_id,
                    'value'  => $modx->hsc($field_value),
                    'style'  => $field_style,
                    'tvtype' => $field_type,
                    'rows'   => $field_type==='textareamini' ? '5' : '15'
                )
            );
        }
        if(in_array(strtolower($field_type),array('date','dateonly'))) {
            $format = $modx->config['datetime_format'];
            if($field_type === 'date') {
                $format .= ' hh:mm:00';
            }
            return $modx->parseText(
                file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_date.tpl')
                , array(
                'id' => sprintf(
                    'tv%s'
                    , str_replace(array('-', '.'), '_', urldecode($field_id))
                ),
                'name'            => 'tv' . $field_id,
                'value'           => $field_value ? $modx->hsc($field_value) : '',
                'style'           => $field_style,
                'tvtype'          => $field_type,
                'cal_nodate'      => $_style['icons_cal_nodate'],
                'yearOffset'      => $modx->config['datepicker_offset'],
                'datetime_format' => $format,
                'timepicker'      => strtolower($field_type)==='date' ? 'true' : 'false'
            ));
        }
        if(in_array(strtolower($field_type),array('dropdown','listbox','listbox-multiple'))) {
            $tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_list.tpl');
            if($field_type==='listbox-multiple') {
                $tpl = str_replace('[+name+]', '[+name+][]', $tpl);
            }
            $rs = $this->ProcessTVCommand($field_elements, $field_id,'','tvform');
            $index_list = $this->ParseInputOptions($rs);
            $tpl2 = '<option value="[+value+]" [+selected+]>[+label+]</option>';
            $field_values = explode('||',$field_value);
            foreach ($index_list as $label=>$item)
            {
                list($label,$value) = $this->splitOption($item);
                $ph2['label']    = $label;
                $ph2['value']    =  $modx->hsc($value);
                $ph2['selected'] = in_array($value,$field_values) ? 'selected="selected"':'';
                $options[] = $modx->parseText($tpl2, $ph2);
            }
            $ph['options'] = join("\n",$options);
            $ph['id']      = 'tv' . $field_id;
            $ph['name']    = 'tv' . $field_id;
            $ph['size']   = count($index_list)<8 ? count($index_list) : 8;
            $ph['extra'] = '';
            if($field_type==='listbox-multiple') $ph['extra'] = 'multiple';
            elseif($field_type==='dropdown')     $ph['size']   = '1';
            return $modx->parseText($tpl,$ph);
        }
        if(strtolower($field_type)==='checkbox') {
            if(!is_array($field_value)) {
                $field_value = explode('||', $field_value);
            }
            $rs = $this->ProcessTVCommand($field_elements, $field_id,'','tvform');
            $index_list = $this->ParseInputOptions($rs);
            $tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_checkbox.tpl');
            $field_html = '';
            $i=0;
            foreach ($index_list as $item) {
                list($label,$value) = $this->splitOption($item);
                $field_html .=  $modx->parseText($tpl,array(
                    'id'      => 'tv' . $field_id . '_' . $i,
                    'name'    => 'tv' . $field_id . '[]',
                    'value'   => $modx->hsc($value),
                    'tvtype'  => $field_type,
                    'label'   => $label,
                    'checked' => $this->isSelected($label,$value,$item,$field_value) ? ' checked':''
                ));
                $i++;
            }
            return trim($field_html);
        }
        if(strtolower($field_type)==='option') {
            $rs = $this->ProcessTVCommand($field_elements, $field_id,'','tvform');
            $index_list = $this->ParseInputOptions($rs);
            $i=0;
            $field_html = '';
            $tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_radio.tpl');
            foreach ($index_list as $item)
            {
                list($label,$value) = $this->splitOption($item);
                $checked = $this->isSelected($label,$value,$item,$field_value) ?'checked="checked"':'';
                $value = $modx->hsc($value);
                $field_html .= sprintf(
                    $tpl
                    , $i
                    , $value
                    , $i
                    , $field_id
                    , $checked
                    , $label
                );
                $i++;
            }
            return $field_html;
        }
        if(strtolower($field_type)==='image') {
            return sprintf(
                file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_image.tpl')
                , $field_id
                , $field_id
                , $field_value
                , $field_style
                , $_lang['insert']
                , $field_id
            );
        }
        if(strtolower($field_type)==='file') {
            return sprintf(
                file_get_contents(MODX_CORE_PATH . 'docvars/inputform/form_file.tpl')
                , $field_id
                , $field_id
                , $field_value
                , $field_style
                , $_lang['insert']
                , $field_id
            );
        }
        if(strtolower($field_type)==='hidden') {
            $field_type = 'hidden';
            return sprintf(
                '<input type="hidden" id="tv%s" name="tv%s" value="%s" tvtype="%s" />'
                , $field_id
                , $field_id
                , $modx->hsc($field_value)
                , $field_type
            );
        }
        if(strtolower($field_type)==='custom_tv') {
            $tpl = $this->custom_tv_tpl($field_id,$field_elements);
            $ph['field_type']   = $field_type;
            $ph['field_id']     = $field_id;
            $ph['field_name']   = "tv{$field_id}";
            $ph['name']         = "tv{$field_id}";
            $ph['default_text'] = $default_text;
            $ph['field_value']  = $modx->hsc($field_value);
            $ph['value']        = $modx->hsc($field_value);
            $ph['field_style']  = $field_style;
            $tpl =$modx->evalSnippets(
                $modx->mergeChunkContent(
                    $modx->mergeSettingsContent(
                        $modx->mergeDocumentContent(
                            $modx->parseText(
                                $tpl
                                , $ph
                            )
                        )
                    )
                )
            );
            return $tpl;
        }

        // the default handler -- for errors, mostly
        if(strpos($field_elements, '@EVAL') === 0) {
            $eval_str = trim(substr($field_elements, 6));
        } else {
            $result = $modx->db->select(
                'snippet'
                , '[+prefix+]site_snippets'
                , sprintf("name='input:%s'", $field_type)
            );
            if($modx->db->getRecordCount($result)==1) {
                $eval_str = $modx->db->getValue($result);
            } else {
                $eval_str = false;
            }
        }
        if($eval_str) {
            return eval($eval_str);
        }

        return sprintf(
            '<input type="text" id="tv%s" name="tv%s" value="%s" %s />'
            , $field_id
            , $field_id
            , $modx->hsc($field_value)
            , $field_style
        );
    }

    private function custom_tv_tpl($field_id,$field_elements) {
        global $modx, $_lang;
        if (strpos($field_elements, '@FILE') === 0) {
            $path_str = trim(substr($field_elements, 6));
            $lfpos = strpos($path_str,"\n");
            if($lfpos!==false) {
                $path_str = substr($path_str, 0, $lfpos);
            }
            $path_str = MODX_BASE_PATH . trim($path_str);

            if(!is_file($path_str)) {
                return $path_str . ' does not exist';
            }

            return file_get_contents($path_str);
        }

        if (strpos($field_elements, '@INCLUDE') === 0) {
            $path_str = substr($field_elements, 9);
            $lfpos = strpos($path_str,"\n");
            if($lfpos!==false) {
                $path_str = substr($path_str, 0, $lfpos);
            }
            $path_str = trim($path_str);
            if(is_file(MODX_BASE_PATH.'assets/tvs/'.$path_str)) {
                $path = MODX_BASE_PATH . 'assets/tvs/' . $path_str;
            } elseif(is_file( MODX_BASE_PATH.$path_str)) {
                $path = MODX_BASE_PATH . $path_str;
            } else {
                $path = false;
            }

            if(!$path) {
                return $path_str . ' does not exist';
            }

            ob_start();
            include($path);
            return ob_get_clean();
        }

        if (strpos($field_elements, '@CHUNK') === 0) {
            $chunk_name = trim(substr($field_elements, 7));
            $tpl = $modx->getChunk($chunk_name);
            if($tpl !== false) {
                return $tpl;
            }
            return sprintf(
                '%s(%s:%s)'
                , $_lang['chunk_no_exist']
                , $_lang['htmlsnippet_name']
                , $chunk_name
            );
        }

        if(strpos($field_elements, '@EVAL') === 0) {
            return eval(
            trim(substr($field_elements, 6))
            );
        }

        if(strpos($field_elements, '@') === 0) {
            return $this->ProcessTVCommand(
                $field_elements
                , $field_id
                , ''
                , 'tvform'
            );
        }
        return $field_elements;
    }
    function ParseInputOptions($v)
    {
        global $modx;
        if (is_array($v)) {
            return $v;
        }

        if($modx->db->isResult($v)) {
            $a = array();
            while ($cols = $modx->db->getRow($v,'num'))
            {
                $a[] = $cols;
            }
            return $a;
        }

        $v = trim($v);
        if (strpos($v,'||')!==false) {
            return explode('||', $v);
        }

        if(strpos($v,"\n")!==false) {
            $v = str_replace("\n", '||', $v);
        } elseif(strpos($v,',')!==false) {
            $v = str_replace(',', '||', $v);
        }

        return explode('||', $v);
    }
    
    function splitOption($value)
    {
        if(is_array($value))
        {
            $label=$value[0];
            $value= isset($value[1]) ? $value[1] : $value[0];
        }
        else
        {
            if(strpos($value,'==')===false) {
                $label = $value;
            } else {
                list($label, $value) = explode('==', $value, 2);
            }
        }
        return array(trim($label),trim($value));
    }
    
    function isSelected($label,$value,$item,$field_value)
    {
        if(is_array($item)) {
            $item = $item['0'];
        }

        if(strpos($item,'==')!==false && strlen($value)==0)
        {
            if(is_array($field_value)) {
                $rs = in_array($label,$field_value);
            } else {
                $rs = ($label === $field_value);
            }
        }
        else
        {
            if(is_array($field_value)) {
                $rs = in_array($value,$field_value);
            } else {
                $rs = ($value === $field_value);
            }
        }
        
        return $rs;
    }
    /**
     * Displays a javascript alert message in the web browser and quit
     *
     * @param string $msg Message to show
     * @param string $url URL to redirect to
     */
    function webAlertAndQuit($msg, $url= "") {
        global $modx,$modx_manager_charset;
        if (strpos(strtolower($url), 'javascript:') === 0) {
            $fnc = substr($url, 11);
        } elseif ($url) {
            $fnc = "window.location.href='" . addslashes($url) . "';";
        } elseif(isset($_SESSION['previous_request_uri'])) {
            $fnc = sprintf("window.location.href='%s';", $_SESSION['previous_request_uri']);
        } else {
            $fnc = "history.back(-1);";
        }
        $msg = addslashes($msg);
        $msg = str_replace("\n",'\n',$msg);
        echo "<html><head>
            <title>MODX :: Alert</title>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset={$modx_manager_charset};\">
            <link rel=\"stylesheet\" type=\"text/css\" href=\"media/style/{$modx->config['manager_theme']}/style.css\" />
            <script>
                function __alertQuit() {
                    alert('" . $msg . "');
                    {$fnc}
                }
                window.setTimeout('__alertQuit();',100);
            </script>
            </head><body>
            </body></html>";
            exit;
    }
    
    function getMimeType($filepath='')
    {
        $fp = fopen($filepath, 'rb');
        $head= fread($fp, 2); fclose($fp);
        $head = mb_convert_encoding($head, '8BIT');

        if ($head==='BM') {
            return 'image/bmp';
        }

        if ($head==='GI') {
            return 'image/gif';
        }

        if ($head===chr(0xFF).chr(0xd8)) {
            return 'image/jpeg';
        }

        if($head===chr(0x89).'P') {
            return 'image/png';
        }

        return false;
    }
    
    # returns true if the current web user is a member the specified groups
    function isMemberOfWebGroup($groupNames= array ())
    {
        global $modx;
        
        if (!is_array($groupNames)) {
            return false;
        }
        
        // check cache
        $grpNames= isset ($_SESSION['webUserGroupNames']) ? $_SESSION['webUserGroupNames'] : false;
        if (!is_array($grpNames))
        {
            $uid = $modx->getLoginUserID();
            $from  = '[+prefix+]webgroup_names wgn' .
                    " INNER JOIN [+prefix+]web_groups wg ON wg.webgroup=wgn.id AND wg.webuser='{$uid}'";
            $rs = $modx->db->select('wgn.name', $from);
            $grpNames= $modx->db->getColumn('name', $rs);
            
            // save to cache
            $_SESSION['webUserGroupNames']= $grpNames;
        }
        foreach ($groupNames as $k => $v)
        {
            if (in_array(trim($v), $grpNames, true)) {
                return true;
            }
        }
        return false;
    }
    
    # Returns a record for the web user
    function getWebUserInfo($uid)
    {
        global $modx;
        
        $field = 'wu.username, wu.password, wua.*';
        $from = '[+prefix+]web_users wu INNER JOIN [+prefix+]web_user_attributes wua ON wua.internalkey=wu.id';
        $rs= $modx->db->select($field,$from,"wu.id='$uid'");
        $limit= $modx->db->getRecordCount($rs);
        if ($limit == 1)
        {
            $row= $modx->db->getRow($rs);
            if (!$row['usertype']) $row['usertype']= 'web';
            return $row;
        }
        else return false;
    }
    
    # Returns a record for the manager user
    function getUserInfo($uid)
    {
        $rs = db()->select(
            'user.username, user.password, attrib.*'
            , array(
                '[+prefix+]manager_users user',
                'INNER JOIN [+prefix+]user_attributes attrib ON ua.internalKey=user.id'
            )
            , sprintf("user.id='%s'", db()->escape($uid))
        );
        if (db()->getRecordCount($rs) == 1) {
            $row= db()->getRow($rs);
            if (!isset($row['usertype'])) {
                $row['usertype'] = 'manager';
            }
            if(!isset($user['failedlogins']) ) {
                $user['failedlogins'] = 0;
            }
            return $row;
        }
        return false;
    }
    
    # Returns current user name
    function getLoginUserName($context= '') {
        global $modx;

        if ($context && isset ($_SESSION[$context . 'Validated'])) {
            return $_SESSION[$context . 'Shortname'];
        }

        if ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webShortname'];
        }

        if ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return $_SESSION['mgrShortname'];
        }
        return false;
    }

    # Returns current login user type - web or manager
    function getLoginUserType() {
        global $modx;

        if ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            return 'web';
        }

        if ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return 'manager';
        }
        return '';
    }
    
    function getDocumentChildrenTVars($parentid= 0, $tvidnames= '*', $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC', $tvfields= '*', $tvsort= 'rank', $tvsortdir= 'ASC')
    {
        global $modx;
        
        $docs= $modx->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
        if (!$docs) {
            return false;
        }

        foreach($docs as $doc) {
            $result[] = $modx->getTemplateVars($tvidnames, $tvfields, $doc['id'],$published);
        }
        return $result;
    }
        
    function getDocumentChildrenTVarOutput($parentid= 0, $tvidnames= '*', $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC')
    {
        global $modx;
        
        $docs= $modx->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
        if (!$docs) {
            return false;
        }
        
        $result= array ();
        foreach($docs as $doc) {
            $tvs= $modx->getTemplateVarOutput($tvidnames, $doc['id'], $published, '', '');
            if ($tvs) {
                $result[$doc['id']] = $tvs;
            } // Use docid as key - netnoise 2006/08/14
        }
        return $result;
    }
    
    function getAllChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle',$where=false)
    {
        global $modx;
        
        $cacheKey = hash('crc32b', print_r(func_get_args(),true));
        if(isset($modx->tmpCache[__FUNCTION__][$cacheKey])) return $modx->tmpCache[__FUNCTION__][$cacheKey];
        
        // modify field names to use sc. table reference
        $fields= $modx->join(',', explode(',',$fields),'sc.');
        $sort  = $modx->join(',', explode(',',$sort),'sc.');
        
        // build query
        $from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
        if($where===false)
        {
            // get document groups for current user
            if ($modx->getUserDocGroups()) {
                $docgrp= implode(',', $modx->getUserDocGroups());
                $cond = "OR dg.document_group IN ({$docgrp}) OR 1='{$_SESSION['mgrRole']}'";
            } else {
                $cond = '';
            }
            $context = ($modx->isFrontend() ? 'web' : 'mgr');
            $where = "sc.parent = '{$id}' AND (sc.private{$context}=0 {$cond}) GROUP BY sc.id";
        }
        $orderby = "{$sort} {$dir}";
        $result= $modx->db->select("DISTINCT {$fields}",$from,$where,$orderby);
        $resourceArray= array ();
        while ($row = $modx->db->getRow($result))
        {
            $resourceArray[] = $row;
        }
        
        $modx->tmpCache[__FUNCTION__][$cacheKey] = $resourceArray;
        
        return $resourceArray;
    }
    
    function getActiveChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle') {
        global $modx;
        
        $cacheKey = hash('crc32b', print_r(func_get_args(),true));
        if(isset($modx->tmpCache[__FUNCTION__][$cacheKey])) {
            return $modx->tmpCache[__FUNCTION__][$cacheKey];
        }
        $where = array();
        $where[] = "sc.parent = '{$id}'";
        $where[] = "AND sc.published=1";
        $where[] = "AND sc.deleted=0";
        if($modx->isFrontend()) {
            if ($modx->getUserDocGroups()) {
                $where[] = sprintf(
                    "AND (sc.privateweb=0 OR dg.document_group IN (%s))"
                    , implode(',', $modx->getUserDocGroups())
                );
            } else {
                $where[] = 'AND sc.privateweb=0';
            }
        } elseif($_SESSION['mgrRole']!=1) {
            if ($modx->getUserDocGroups()) {
                $where[] = sprintf(
                    "AND (sc.privatemgr=0 OR dg.document_group IN (%s))"
                    , implode(',', $modx->getUserDocGroups())
                );
            } else {
                $where[] = 'AND sc.privatemgr=0';
            }
        }
        $where[] = "GROUP BY sc.id";
        
        $resourceArray = $modx->getAllChildren($id, $sort, $dir, $fields,$where);
        
        $modx->tmpCache[__FUNCTION__][$cacheKey] = $resourceArray;
        
        return $resourceArray;
    }
    
    function getDocumentChildren($parentid=0, $published=1, $deleted=0, $fields='*', $customWhere='', $sort='menuindex', $dir='ASC', $limit='')
    {
        global $modx;
        
        // modify field names to use sc. table reference
        $fields = $modx->join(',', explode(',',$fields),'sc.');
        
        $from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
        
        $access = '';
        if($modx->isFrontend())         $access = 'sc.privateweb=0';
        elseif($_SESSION['mgrRole']!=1) $access = 'sc.privatemgr=0';
        if($docgrp = $modx->getUserDocGroups()) {
            if($access!=='') $access .= ' OR';
            $access .= sprintf(' dg.document_group IN (%s)', join(',', $docgrp));
        }
        
        $_ = array();
        $_[] = "sc.parent='{$parentid}'";
        $_[] = "sc.published={$published}";
        $_[] = "sc.deleted={$deleted}";
        if($customWhere != '') $_[] = $customWhere;
        if($access!='')        $_[] = "({$access})";
        $where = join(' AND ', $_) . ' GROUP BY sc.id';
        
        if(strpos($sort,',')!==false)
            $orderby = $modx->join(',', explode(',',$sort),'sc.');
        else
            $orderby = "{$sort} {$dir}";
        
        $result= $modx->db->select("DISTINCT {$fields}",$from,$where,$orderby,$limit);
        $resourceArray= array ();
        while ($row = $modx->db->getRow($result))
        {
            $resourceArray[] = $row;
        }
        return $resourceArray;
    }
    
    function getPreviewObject($input=array()) {
        global $modx;

        if( !empty($modx->previewObject) ){
            return $modx->previewObject;
        }

        if(!isset($input['id'])||empty($input['id']))
            $input['id'] = $modx->config['site_start'];

        $modx->documentIdentifier = $input['id'];
        
        $rs = $modx->db->select('id,name,type,display,display_params','[+prefix+]site_tmplvars');
        while($row = $modx->db->getRow($rs))
        {
            $tvid = 'tv' . $row['id'];
            $tvname[$tvid] = $row['name'];
        }
        foreach($input as $k=>$v)
        {
            if(isset($tvname[$k]))
            {
                if(is_array($v)) {
                    $v = implode('||', $v);
                }
                unset($input[$k]);
                $name = $tvname[$k];
                if( isset($input["{$k}_prefix"]) )
                {
                    if( $input["{$k}_prefix"] != 'DocID' )
                        $v = $input["{$k}_prefix"] . $v;
                    elseif( preg_match('/\A[0-9]+\z/',$v) )
                        $v = '[~' . $v . '~]';
                }
                $input[$name] = $v;
            }
            elseif($k==='ta')
            {
                $input['content'] = $v;
                unset($input['ta']);
            }
        }
        if($input['pub_date']==='')    $input['pub_date']    = '0';
        if($input['unpub_date']==='')  $input['unpub_date']  = '0';
        if($input['publishedon']==='') $input['publishedon'] = '0';

        $modx->previewObject = $input;
        
        return $modx->previewObject;
    }
    
    function loadLexicon($target='manager') {
        global $modx;
        
        if (!isset($modx->config['manager_language'])) {
            $langname = 'english';
        }
        else $langname = $modx->config['manager_language'];
        
        if($target==='manager') {
            global $_lang, $modx_manager_charset, $modx_lang_attribute, $modx_textdir;
            $path = MODX_CORE_PATH . 'lang/';
            $modx_manager_charset = 'utf-8';
            $modx_lang_attribute = 'en';
            $modx_textdir = 'ltr';
            $_lang = array();
        }
        elseif($target==='locale') {
            global $_lc;
            $path = MODX_CORE_PATH . 'lang/locale/';
        }
        else $path = $target;
        
        $path = rtrim($path, '/') . '/';
        
        $file_path = "{$path}{$langname}.inc.php";
        if(is_file($file_path)) include_once($file_path);
    }
    
    function snapshot($filename='', $target='') {
        global $modx, $settings_version;
        
        if(is_array($filename)) {
            if(!isset($filename['filename'])) $filename = '';
            else                              $filename = $filename['filename'];
            if(!isset($filename['target'])) $target = '';
            else                              $target = $filename['target'];
        }
        
        if(strpos($filename,'/')!==false) return;
        if(strpos($filename,'\\')!==false) return;
        if($target!=='') $target = substr(strtolower($target),0,1);
        
        if(!isset($modx->config['snapshot_path'])||empty($modx->config['snapshot_path'])) {
            if(is_dir(MODX_BASE_PATH . 'temp/backup')) $snapshot_path = MODX_BASE_PATH . 'temp/backup/';
            elseif(is_dir(MODX_BASE_PATH . 'assets/backup')) $snapshot_path = MODX_BASE_PATH . 'assets/backup/';
        }
        else $snapshot_path = $modx->config['snapshot_path'];
        
        if($filename==='') {
            $today = $modx->toDateFormat($_SERVER['REQUEST_TIME']);
            $today = str_replace(array('/',' '), '-', $today);
            $today = str_replace(':', '', $today);
            $today = strtolower($today);
            $filename = "{$today}-{$settings_version}.sql";
        }
        
        include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');
        $dumper = new Mysqldumper();
        $dumper->mode = 'snapshot';
        if($target==='c') $dumper->contentsOnly = true;
        $output = $dumper->createDump();
        return $dumper->snapshot($snapshot_path.$filename,$output);
    }
    
    /**
     * Returns the MODX version information as version, branch, release date and full application name.
     *
     * @return array
     */
    
    function getVersionData($data=null) {
        global $modx;
        if(!$modx->version || !is_array($modx->version)){
            //include for compatibility modx version < 1.0.10
            include MODX_CORE_PATH . 'version.inc.php';
            $modx->version=array();
            $modx->version['version']= isset($modx_version) ? $modx_version : '';
            $modx->version['branch']= isset($modx_branch) ? $modx_branch : '';
            $modx->version['release_date']= isset($modx_release_date) ? $modx_release_date : '';
            $modx->version['full_appname']= isset($modx_full_appname) ? $modx_full_appname : '';
            $modx->version['new_version'] = isset($modx->config['newversiontext']) ? $modx->config['newversiontext'] : '';
        }
        return ($data !== null && is_array($modx->version) && isset($modx->version[$data])) ? $modx->version[$data] : $modx->version;
    }
    
    function _IIS_furl_fix()
    {
        global $modx;
        
        if($modx->config['friendly_urls'] != 1) return;
        
        $url= $_SERVER['QUERY_STRING'];
        $err= substr($url, 0, 3);
        if ($err !== '404' && $err !== '405') return;
        
        $k= array_keys($_GET);
        unset ($_GET[$k['0']]);
        unset ($_REQUEST[$k['0']]); // remove 404,405 entry
        $qp= parse_url(str_replace($modx->config['site_url'], '', substr($url, 4)));
        $_SERVER['QUERY_STRING']= $qp['query'];
        if (!empty ($qp['query']))
        {
            parse_str($qp['query'], $qv);
            foreach ($qv as $n => $v)
            {
                $_REQUEST[$n]= $_GET[$n]= $v;
            }
        }
        $_SERVER['PHP_SELF']= $modx->config['base_url'] . $qp['path'];
        return $qp['path'];
    }
    
    function genTokenString($seed='') {
        global $modx;
        if(isset($modx->tmpCache['tokenString'])) return $modx->tmpCache['tokenString'];
        if(!$seed) $seed = md5(mt_rand());
        $_ = str_split($seed,5);
        $p = array();
        foreach($_ as $v) {
            $p[] = base_convert($v,16,36);
        }
        $key = join('',$p);
        $key = substr($key,0,12);
        $modx->tmpCache['tokenString'] = $key;
        return $key;
    }

    function setCacheRefreshTime($unixtime=0)
    {
        if($unixtime==0) {
            return;
        }
        if(!$this->db->isConnected() || !db()->table_exists('[+prefix+]system_settings')) {
            return;
        }
        include_once MODX_CORE_PATH . 'cache_sync.class.php';
        $cache = new synccache();
        $cache->setCacheRefreshTime($unixtime);
        $cache->publishBasicConfig();
    }
    
    function atBind($str='') {
        if(strpos($str, '@') !== 0) return $str;
        
        if(strpos($str, '@FILE') === 0)    return $this->atBindFile($str);
        if(strpos($str, '@URL') === 0)     return $this->atBindUrl($str);
        if(strpos($str, '@INCLUDE') === 0) return $this->atBindInclude($str);
        
        return $str;
    }
    
    function atBindFile($str='')
    {
        global $modx;
        
        if(strpos($str,'@FILE')!==0) return $str;
        $str = trim($str);
        if(strpos($str,"\n")!==false)
            $str = substr($str,0,strpos("\n",$str));
        
        $str = substr($str,6);
        $str = trim($str);
        $str = str_replace('\\','/',$str);
        $template_path = 'assets/templates/';
        
        if(substr($str,0,1)==='/')
        {
            if(is_file($str) && strpos($str, MODX_MANAGER_PATH) === 0)
                $file_path = false;
            elseif(is_file($str) && strpos($str, MODX_BASE_PATH) === 0)
                $file_path = $str;
            elseif(is_file(MODX_BASE_PATH . trim($str,'/')))
                $file_path = MODX_BASE_PATH . trim($str,'/');
            else $file_path = false;
        }
        elseif(is_file(MODX_BASE_PATH . $str))
            $file_path = MODX_BASE_PATH . $str;
        elseif(is_file(MODX_BASE_PATH . $template_path . $str))
            $file_path = MODX_BASE_PATH . $template_path . $str;
        else
            $file_path = false;
        
        if(!$file_path) return false;
        
        if($modx->getExtention($file_path)==='.php') {
            return 'Could not retrieve PHP file.';
        }
        
        $content = file_get_contents($file_path);
        if(! $content)return '';
        
        global $recent_update;
        if($recent_update < filemtime($file_path))
            $modx->clearCache();
        if(!$modx->template_path && strpos($file_path,MODX_BASE_PATH.'assets/templates/')===0)
            $modx->template_path = $file_path . '/';
        
        return $content;
    }
    
    function atBindUrl($str='')
    {
        if(strpos($str,'@URL')!==0) {
            return $str;
        }

        $str = trim($str);
        $pos = strpos($str, "\n");
        if($pos) {
            $str = substr($str, 0, $pos);
        }
        
        $str = substr($str,5);
        $str = trim($str);
        if(strpos($str,'http')!==0) {
            return 'Error @URL';
        }

        return file_get_contents($str);
    }
    
    function atBindInclude($str='')
    {
        if(strpos($str,'@INCLUDE')!==0) return $str;
        $str = trim($str);
        if(strpos($str,"\n")!==false)
            $str = substr($str,0,strpos("\n",$str));
        
        $str = substr($str,9);
        $str = trim($str);
        $str = str_replace('\\','/',$str);
        
        $tpl_dir = 'assets/templates/';
        
        if(substr($str,0,1)==='/')
        {
            $vpath = MODX_BASE_PATH . ltrim($str,'/');
            if(is_file($str) && strpos($str,MODX_MANAGER_PATH)===0)         $file_path = false;
            elseif(is_file($vpath) && strpos($vpath,MODX_MANAGER_PATH)===0) $file_path = false;
            elseif(is_file($str) && strpos($str,MODX_BASE_PATH)===0)        $file_path = $str;
            elseif(is_file($vpath))                                         $file_path = $vpath;
            else                                                            $file_path = false;
        }
        elseif(is_file(MODX_BASE_PATH . $str))                              $file_path = MODX_BASE_PATH.$str;
        elseif(is_file(MODX_BASE_PATH . "{$tpl_dir}{$str}"))                $file_path = MODX_BASE_PATH.$tpl_dir.$str;
        else                                                                $file_path = false;
        
        if(!$file_path || !is_file($file_path)) return false;
        
        ob_start();
        global $modx;
        $result = include($file_path);
        if($result===1) $result = '';
        $content = ob_get_clean();
        if(!$content && $result) $content = $result;
        return $content;
    }
    
    function setOption($key, $value='')
    {
        $this->config[$key] = $value;
    }
    
    function getOption($key, $default = null, $options = null, $skipEmpty = false)
    {
        global $modx;
        
        $option= $default;
        
        if(strpos($key,',')!==false) {
            $key = explode(',', $key);
        }
        if (is_array($key)) {
            if (!is_array($option)) {
                $default= $option;
                $option= array();
            }
            foreach ($key as $k) {
                $k = trim($k);
                $option[$k]= $this->getOption($k, $default, $options);
            }
            return $option;
        }

        if (is_string($key) && $key) {
            if (is_array($options) && array_key_exists($key, $options) && (!$skipEmpty || ($skipEmpty && $options[$key] !== ''))) {
                return $options[$key];
            }
            if (is_array($modx->config) && array_key_exists($key, $modx->config) && (!$skipEmpty || ($skipEmpty && $modx->config[$key] !== ''))) {
                return $modx->config[$key];
            }
        }
        return $option;
    }
    
    function regOption($key, $value='')
    {
        global $modx;
        
        $modx->config[$key] = $value;
        $f['setting_name']  = $key;
        $f['setting_value'] = $modx->db->escape($value);
        $key = $modx->db->escape($key);
        $rs = $modx->db->select('*','[+prefix+]system_settings', "setting_name='{$key}'");
        
        if($modx->db->getRecordCount($rs)==0)
        {
            $modx->db->insert($f,'[+prefix+]system_settings');
            $diff = $modx->db->getAffectedRows();
            if(!$diff)
            {
                $modx->messageQuit('Error while inserting new option into database.', $modx->db->lastQuery);
                exit();
            }
        }
        else
        {
            $modx->db->update($f,'[+prefix+]system_settings', "setting_name='{$key}'");
        }
        
        $modx->getSettings();
    }
    
    function mergeInlineFilter($content)
    {
        global $modx;
        
        if(strpos($content,'[+@')===false) return $content;
        
        if ($modx->debug) $fstart = $modx->getMicroTime();
        
        $matches = $modx->getTagsFromContent($content,'[+@','+]');
        if(!$matches) return $content;
        
        $replace= array ();
        foreach($matches['1'] as $i=>$key) {
            $delim = substr($key,0,1);
            switch($delim)
            {
                case '"':
                case '`':
                case "'":
                    if(substr_count($key,$delim)==1) break;
                    $key = substr($key,1);
                    list($body,$remain) = explode($delim,$key,2);
                    $key = str_replace(':', hash('crc32b', ':'),$body) . $remain;
            }
            if(strpos($key,':')!==false)
                list($key,$modifiers) = explode(':', $key, 2);
            else $modifiers = false;
            if(strpos($key,hash('crc32b', ':'))!==false) $key = str_replace(hash('crc32b', ':'),':',$key);
            $value = $key;
            if($modifiers!==false)
            {
                $modx->loadExtension('MODIFIERS');
                $value = $modx->filter->phxFilter($key,$value,$modifiers);
            }
            $replace[$i] = $value;
        }
        
        $content= str_replace($matches['0'], $replace, $content);
        if ($modx->debug)
        {
            $_ = join(', ', $matches['0']);
            $modx->addLogEntry('$modx->'.__FUNCTION__ . "[{$_}]",$fstart);
        }
        return $content;
    }
    function updateDraft()
    {
        global $modx;
        
        $now = $_SERVER['REQUEST_TIME'] + $modx->config['server_offset_time'];
        
        $rs = $modx->db->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf('pub_date!=0 AND pub_date<%s', $now)
        );

        if(!$modx->db->getRecordCount($rs)) {
            return;
        }
        
        $modx->loadExtension('REVISION');
        $modx->loadExtension('DocAPI');
        while($row = $modx->db->getRow($rs))
        {
            $draft = $modx->revision->getDraft($row['elmid']);
            $draft['editedon'] = $row['editedon'];
            $draft['editedby'] = $row['editedby'];
            
            $modx->doc->update($draft,$row['elmid']);
        }
        $modx->db->delete(
            '[+prefix+]site_revision'
            , sprintf("pub_date!=0 AND pub_date<%s", $now)
        );
    }
    
    function setdocumentMap()
    {
        global $modx;
        
        $fields = 'id, parent';
        $rs = $modx->db->select($fields,'[+prefix+]site_content','deleted=0','parent, menuindex');
        $modx->documentMap = array();
        while ($row = $modx->db->getRow($rs))
        {
            $modx->documentMap[] = array($row['parent'] => $row['id']);
        }
    }
    
    function setAliasListing()
    {
        global $modx;
        if(!$modx->aliasListing) {
            $aliases = @include(MODX_BASE_PATH . 'assets/cache/aliasListing.siteCache.idx.php');
            if($aliases) $modx->aliasListing = $aliases;
        }
        if(!$modx->documentMap) {
            $documentMap = @include(MODX_BASE_PATH . 'assets/cache/documentMap.siteCache.idx.php');
            if($documentMap) $modx->documentMap = $documentMap;
        }
        return false;
    }
}
