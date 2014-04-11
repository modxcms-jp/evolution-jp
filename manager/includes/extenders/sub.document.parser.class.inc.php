<?php
class SubParser {
	function SubParser()
	{
	}
	function sendmail($params=array(), $msg='')
	{
		global $modx;
		if(isset($params) && is_string($params))
		{
			if(strpos($params,'=')===false)
			{
				if(strpos($params,'@')!==false) $p['to']	  = $params;
				else                            $p['subject'] = $params;
			}
			else
			{
				$params_array = explode(',',$params);
				foreach($params_array as $k=>$v)
				{
					$k = trim($k);
					$v = trim($v);
					$p[$k] = $v;
				}
			}
		}
		else
		{
			$p = $params;
			unset($params);
		}
		if(isset($p['sendto'])) $p['to'] = $p['sendto'];
		
		if(isset($p['to']) && preg_match('@^[0-9]+$@',$p['to']))
		{
			$userinfo = $modx->getUserInfo($p['to']);
			$p['to'] = $userinfo['email'];
		}
		if(isset($p['from']) && preg_match('@^[0-9]+$@',$p['from']))
		{
			$userinfo = $modx->getUserInfo($p['from']);
			$p['from']	 = $userinfo['email'];
			$p['fromname'] = $userinfo['username'];
		}
		if($msg==='' && !isset($p['body']))
		{
			$p['body'] = $_SERVER['REQUEST_URI'] . "\n" . $_SERVER['HTTP_USER_AGENT'] . "\n" . $_SERVER['HTTP_REFERER'];
		}
		elseif(is_string($msg) && 0<strlen($msg)) $p['body'] = $msg;
		
		$modx->loadExtension('MODxMailer');
		$sendto = (!isset($p['to']))   ? $modx->config['emailsender']  : $p['to'];
		$sendto = explode(',',$sendto);
		foreach($sendto as $address)
		{
			list($name, $address) = $modx->mail->address_split($address);
			$modx->mail->AddAddress($address,$name);
		}
		if(isset($p['cc']))
		{
			$p['cc'] = explode(',',$p['cc']);
			foreach($p['cc'] as $address)
			{
				list($name, $address) = $modx->mail->address_split($address);
				$modx->mail->AddCC($address,$name);
			}
		}
		if(isset($p['bcc']))
		{
			$p['bcc'] = explode(',',$p['bcc']);
			foreach($p['bcc'] as $address)
			{
				list($name, $address) = $modx->mail->address_split($address);
				$modx->mail->AddBCC($address,$name);
			}
		}
		if(isset($p['from']) && strpos($p['from'],'<')!==false && substr($p['from'],-1)==='>')
			list($p['fromname'],$p['from']) = $modx->mail->address_split($p['from']);
		$modx->mail->From	 = (!isset($p['from']))  ? $modx->config['emailsender']  : $p['from'];
		$modx->mail->FromName = (!isset($p['fromname'])) ? $modx->config['site_name'] : $p['fromname'];
		$modx->mail->Subject  = (!isset($p['subject']))  ? $modx->config['emailsubject'] : $p['subject'];
		$modx->mail->Body	 = $p['body'];
		$rs = $modx->mail->send();
		return $rs;
	}
	
	function rotate_log($target='event_log',$limit=2000, $trim=100)
	{
		global $modx, $dbase;
		
		if($limit < $trim) $trim = $limit;
		
		$count = $modx->db->getValue($modx->db->select('COUNT(id)',"[+prefix+]{$target}"));
		$over = $count - $limit;
		if(0 < $over)
		{
			$trim = ($over + $trim);
			$modx->db->delete("[+prefix+]{$target}",'','',$trim);
		}
		$result = $modx->db->query("SHOW TABLE STATUS FROM {$dbase}");
		while ($row = $modx->db->getRow($result))
		{
			$modx->db->query('OPTIMIZE TABLE ' . $row['Name']);
		}
	}
	
	function logEvent($evtid, $type, $msg, $title= 'Parser')
	{
		global $modx;
		
		$evtid= intval($evtid);
		$type = intval($type);
		if ($type < 1) $type= 1; // Types: 1 = information, 2 = warning, 3 = error
		if (3 < $type) $type= 3;
		$msg= $modx->db->escape($msg);
		$title = htmlentities($title);
		$title= $modx->db->escape($title);
		if (function_exists('mb_substr'))
		{
			$title = mb_substr($title, 0, 50 , $modx->config['modx_charset']);
		}
		else
		{
			$title = substr($title, 0, 50);
		}
		$LoginUserID = $modx->getLoginUserID();
		if (empty($LoginUserID)) $LoginUserID = '-';
		
		$fields['eventid']     = $evtid;
		$fields['type']        = $type;
		$fields['createdon']   = $_SERVER['REQUEST_TIME'];
		$fields['source']      = $title;
		$fields['description'] = $msg;
		$fields['user']        = $LoginUserID;
		$insert_id = $modx->db->insert($fields,'[+prefix+]event_log');
		if(!$modx->db->conn) $title = 'DB connect error';
		if(isset($modx->config['send_errormail']) && $modx->config['send_errormail'] !== '0')
		{
			if($modx->config['send_errormail'] <= $type)
			{
				$subject = 'Error mail from ' . $modx->config['site_name'];
				$modx->sendmail($subject,"{$source}\n{$modx->decoded_request_uri}");
			}
		}
		if (!$insert_id)
		{
			echo 'Error while inserting event log into database.';
			exit();
		}
		else
		{
			$trim  = (isset($modx->config['event_log_trim']))  ? intval($modx->config['event_log_trim']) : 100;
			if(($insert_id % $trim) == 0)
			{
				$limit = (isset($modx->config['event_log_limit'])) ? intval($modx->config['event_log_limit']) : 2000;
				$modx->rotate_log('event_log',$limit,$trim);
			}
		}
	}
	
    function clearCache($params=array()) {
    	global $modx;
    	
    	if($modx->isBackend() && !$modx->hasPermission('empty_cache')) return;
    	if(!is_array($params) && preg_match('@^[1-9][0-9]*$@',$params))
    	{
    		$docid = $params;
    		if($modx->config['cache_type']==='2')
    		{
    			$url = $modx->config['base_url'] . $modx->makeUrl($docid,'','','root_rel');
    			$filename = md5($url);
    		}
    		else
    			$filename = "docid_{$docid}";
    		$page_cache_path = "{$base_path}assets/cache/{$filename}.pageCache.php";
    		if(is_file($page_cache_path))
    		{
    			unlink($page_cache_path);
    			$modx->config['cache_type'] = '0';
    		}
    		return;
    	}
    	elseif(is_string($params) && $params==='full')
    	{
    		$params = array();
    		$params['showReport'] = false;
    		$params['target'] = 'pagecache,sitecache';
    	}
    	
    	if(opendir(MODX_BASE_PATH . 'assets/cache')!==false)
    	{
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
		else return false;
	}
	
    function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='') {
    	global $modx;

        $version= isset ($GLOBALS['version']) ? $GLOBALS['version'] : '';
		$release_date= isset ($GLOBALS['release_date']) ? $GLOBALS['release_date'] : '';
        $request_uri = $modx->decoded_request_uri;
        $request_uri = htmlspecialchars($request_uri, ENT_QUOTES);
        $ua          = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES);
        $referer     = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES);
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

        if (!empty ($query)) {
	        $str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">SQL &gt; <span id="sqlHolder">' . $query . '</span></div>
                    </td></tr>';
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

		if(!empty($nr) || !empty($file))
		{
			$str .= '<tr><td colspan="2"><b>PHP error debug</b></td></tr>';
			if ($text != '')
			{
				$str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">Error : ' . $text . '</div></td></tr>';
			}
			if($output!='')
			{
				$str .= '<tr><td colspan="2"><div style="font-weight:bold;border:1px solid #ccc;padding:8px;color:#333;background-color:#ffffcd;">' . $output . '</div></td></tr>';
			}
			$str .= '<tr><td valign="top">ErrorType[num] : </td>';
			$str .= '<td>' . $errortype [$nr] . "[{$nr}]</td>";
			$str .= '</tr>';
			$str .= "<tr><td>File : </td><td>{$file}</td></tr>";
			$str .= "<tr><td>Line : </td><td>{$line}</td></tr>";
		}
        
        if ($source != '')
        {
            $str .= "<tr><td>Source : </td><td>{$source}</td></tr>";
        }

        $str .= '<tr><td colspan="2"><b>Basic info</b></td></tr>';

        $str .= '<tr><td valign="top" style="white-space:nowrap;">REQUEST_URI : </td>';
        $str .= "<td>{$request_uri}</td>";
        $str .= '</tr>';
        
	    if(isset($_GET['a']))      $action = $_GET['a'];
	    elseif(isset($_POST['a'])) $action = $_POST['a'];
        if(isset($action) && !empty($action))
        {
        	include_once($modx->config['core_path'] . 'actionlist.inc.php');
        	global $action_list;
        	if(isset($action_list[$action])) $actionName = " - {$action_list[$action]}";
        	else $actionName = '';
			$str .= '<tr><td valign="top">Manager action : </td>';
			$str .= "<td>{$action}{$actionName}</td>";
			$str .= '</tr>';
        }
        
        if(preg_match('@^[0-9]+@',$modx->documentIdentifier))
        {
        	$resource  = $modx->getDocumentObject('id',$modx->documentIdentifier);
        	$url = $modx->makeUrl($modx->documentIdentifier,'','','full');
        	$link = '<a href="' . $url . '" target="_blank">' . $resource['pagetitle'] . '</a>';
			$str .= '<tr><td valign="top">Resource : </td>';
			$str .= '<td>[' . $modx->documentIdentifier . ']' . $link . '</td></tr>';
        }

        if(!empty($modx->currentSnippet))
        {
            $str .= "<tr><td>Current Snippet : </td>";
            $str .= '<td>' . $modx->currentSnippet . '</td></tr>';
        }

        if(!empty($modx->event->activePlugin))
        {
            $str .= "<tr><td>Current Plugin : </td>";
            $str .= '<td>' . $modx->event->activePlugin . '(' . $modx->event->name . ')' . '</td></tr>';
        }

        $str .= "<tr><td>Referer : </td><td>{$referer}</td></tr>";
        $str .= "<tr><td>User Agent : </td><td>{$ua}</td></tr>";

        $str .= "<tr><td>IP : </td>";
        $str .= '<td>' . $_SERVER['REMOTE_ADDR'] . '</td>';
        $str .= '</tr>';

        $str .= '<tr><td colspan="2"><b>Benchmarks</b></td></tr>';

        $str .= "<tr><td>MySQL : </td>";
	    $str .= '<td>[^qt^] ([^q^] Requests)</td>';
        $str .= '</tr>';

        $str .= "<tr><td>PHP : </td>";
	    $str .= '<td>[^p^]</td>';
        $str .= '</tr>';

        $str .= "<tr><td>Total : </td>";
	    $str .= '<td>[^t^]</td>';
        $str .= '</tr>';

	    $str .= "<tr><td>Memory : </td>";
	    $str .= '<td>[^m^]</td>';
	    $str .= '</tr>';
	    
        $str .= "</table>\n";

        $totalTime= ($modx->getMicroTime() - $modx->tstart);

		$mem = (function_exists('memory_get_peak_usage')) ? memory_get_peak_usage()  : memory_get_usage() ;
		$total_mem = $modx->nicesize($mem - $modx->mstart);
		
        $queryTime= $modx->queryTime;
        $phpTime= $totalTime - $queryTime;
        $queries= isset ($modx->executedQueries) ? $modx->executedQueries : 0;
        $queryTime= sprintf("%2.4f s", $queryTime);
        $totalTime= sprintf("%2.4f s", $totalTime);
        $phpTime= sprintf("%2.4f s", $phpTime);

        $str= str_replace('[^q^]', $queries, $str);
        $str= str_replace('[^qt^]',$queryTime, $str);
        $str= str_replace('[^p^]', $phpTime, $str);
        $str= str_replace('[^t^]', $totalTime, $str);
        $str= str_replace('[^m^]', $total_mem, $str);

        if(isset($php_errormsg) && !empty($php_errormsg)) $str = "<b>{$php_errormsg}</b><br />\n{$str}";
		$str .= '<br />' . $modx->get_backtrace(debug_backtrace()) . "\n";
		

        // Log error
        if(!empty($modx->currentSnippet)) $source = 'Snippet - ' . $modx->currentSnippet;
        elseif(!empty($modx->event->activePlugin)) $source = 'Plugin - ' . $modx->event->activePlugin;
        elseif($source!=='') $source = 'Parser - ' . $source;
        elseif($query!=='')  $source = 'SQL Query';
        else             $source = 'Parser';
        if(isset($actionName) && !empty($actionName)) $source .= $actionName;
        switch($nr)
        {
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
        if($modx->error_reporting==='99' && !isset($_SESSION['mgrValidated'])) return true;

        // Set 500 response header
	    if($error_level !== 2) header('HTTP/1.1 500 Internal Server Error');

        // Display error
	    if (isset($_SESSION['mgrValidated']))
	    {
	        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html><head><title>MODX Content Manager ' . $version . ' &raquo; ' . $release_date . '</title>
	             <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	             <link rel="stylesheet" type="text/css" href="' . $modx->config['site_url'] . 'manager/media/style/' . $modx->config['manager_theme'] . '/style.css" />
	             <style type="text/css">body { padding:10px; } td {font:inherit;}</style>
	             </head><body>
	             ' . $str . '</body></html>';
	    
	    }
        else  echo 'Error';
        ob_end_flush();

        exit;
    }

	function get_backtrace($backtrace)
	{
		$str = "<p><b>Backtrace</b></p>\n";
		$str  .= '<table>';
		$backtrace = array_reverse($backtrace);
		foreach ($backtrace as $key => $val)
		{
			$key++;
			if(substr($val['function'],0,11)==='messageQuit') break;
			elseif(substr($val['function'],0,8)==='phpError') break;
			$path = str_replace('\\','/',$val['file']);
			if(strpos($path,MODX_BASE_PATH)===0) $path = substr($path,strlen(MODX_BASE_PATH));
    		switch($val['type'])
			{
    			case '->':
    			case '::':
    				$functionName = $val['function'] = $val['class'] . $val['type'] . $val['function'];
    				break;
    			default:
    				$functionName = $val['function'];
				}
			$str .= "<tr><td valign=\"top\">{$key}</td>";
        	$str .= "<td>{$functionName}()<br />{$path} on line {$val['line']}</td>";
		}
		$str .= '</table>';
		return $str;
	}

    function sendRedirect($url, $count_attempts= 0, $type= 'REDIRECT_HEADER',$responseCode='')
    {
    	global $modx;
    	
    	if (empty($url)) return false;
    	elseif(preg_match('@^[1-9][0-9]*$@',$url)) {
    		$url = $modx->makeUrl($url,'','','full');
    	}
    	
    	if ($count_attempts == 1) {
    		// append the redirect count string to the url
    		$currentNumberOfRedirects= isset ($_REQUEST['err']) ? $_REQUEST['err'] : 0;
    		if ($currentNumberOfRedirects > 3) {
    			$modx->messageQuit("Redirection attempt failed - please ensure the document you're trying to redirect to exists. <p>Redirection URL: <i>{$url}</i></p>");
    		} else {
    			$currentNumberOfRedirects += 1;
    			if (strpos($url, '?') > 0) $url .= '&';
    			else                       $url .= '?';
    			$url .= "err={$currentNumberOfRedirects}";
    		}
    	}
    	if    ($type === 'REDIRECT_REFRESH') $header= "Refresh: 0;URL={$url}";
    	elseif($type === 'REDIRECT_META') {
    		$header= '<META HTTP-EQUIV="Refresh" CONTENT="0; URL=' . $url . '" />';
    		echo $header;
    		exit;
    	}
    	else {
    		// check if url has /$base_url
    		global $base_url, $site_url;
    		if (substr($url, 0, strlen($base_url)) == $base_url) {
    			// append $site_url to make it work with Location:
    			$url= $site_url . substr($url, strlen($base_url));
    		}
    		if (strpos($url, "\n") === false) $header= 'Location: ' . $url;
    		else $modx->messageQuit('No newline allowed in redirect url.');
    	}
    	if (!empty($responseCode)) {
    		if    (strpos($responseCode, '301') !== false) $responseCode = 301;
    		elseif(strpos($responseCode, '302') !== false) $responseCode = 302;
    		elseif(strpos($responseCode, '303') !== false) $responseCode = 303;
    		elseif(strpos($responseCode, '307') !== false) $responseCode = 307;
    		else $responseCode = '';
    		if(!empty($responseCode))
    		{
        		header($header, true, $responseCode);
        		exit;
    		}
    	}
    	header($header);
    	exit();
    }
    
	function sendForward($id, $responseCode= '')
	{
		global $modx;
		
		if ($modx->forwards > 0)
		{
			$modx->forwards= $modx->forwards - 1;
			$modx->documentIdentifier= $id;
			$modx->documentMethod= 'id';
			$modx->documentObject= $modx->getDocumentObject('id', $id);
			if ($responseCode)
			{
				header($responseCode);
			}
			echo $modx->prepareResponse();
		}
		else
		{
			$modx->messageQuit("Internal Server Error id={$id}");
			header('HTTP/1.0 500 Internal Server Error');
			die('<h1>ERROR: Too many forward attempts!</h1><p>The request could not be completed due to too many unsuccessful forward attempts.</p>');
		}
		exit();
	}
	
	function sendErrorPage()
	{
		global $modx;
		
		// invoke OnPageNotFound event
		$modx->invokeEvent('OnPageNotFound');
		
		if($modx->config['error_page']) $dist = $modx->config['error_page'];
		else                            $dist = $modx->config['site_start'];
		
		$modx->http_status_code = '404';
		$modx->sendForward($dist, 'HTTP/1.0 404 Not Found');
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
			$snippet= $modx->snippetCache[$snippetName];
			$properties= $modx->snippetCache["{$snippetName}Props"];
		}
		else
		{ // not in cache so let's check the db
			$esc_name = $modx->db->escape($snippetName);
			$result= $modx->db->select('name,snippet,properties','[+prefix+]site_snippets',"name='{$esc_name}'");
			if ($modx->db->getRecordCount($result) == 1)
			{
				$row = $modx->db->getRow($result);
				$snippet= $modx->snippetCache[$snippetName]= $row['snippet'];
				$properties= $modx->snippetCache["{$snippetName}Props"]= $row['properties'];
			}
			else
			{
				$snippet= $modx->snippetCache[$snippetName]= "return false;";
				$properties= '';
			}
		}
		// load default params/properties
		$parameters= $modx->parseProperties($properties);
		$parameters= array_merge($parameters, $params);
		// run snippet
		return $modx->evalSnippet($snippet, $parameters);
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
			elseif ($newPwd == '')   return "You didn't specify a password for this user!";
			else
			{
				$newPwd = $modx->db->escape($newPwd);
				$modx->db->update("password = md5('{$newPwd}')", '[+prefix+]web_users', "id='{$uid}'");
				// invoke OnWebChangePassword event
				$modx->invokeEvent('OnWebChangePassword',
				array
				(
					'userid' => $row['id'],
					'username' => $row['username'],
					'userpassword' => $newPwd
				));
				return true;
			}
		}
		else return 'Incorrect password.';
	}
	
	# add an event listner to a plugin - only for use within the current execution cycle
	function addEventListener($evtName, $pluginName)
	{
		global $modx;
		
		if(!$evtName || !$pluginName) return false;
		
		if (!isset($modx->pluginEvent[$evtName]))
		{
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
        if ( $pluginName == '' ){
            unset ($modx->pluginEvent[$evtName]);
            return true;
        }else{
            foreach($modx->pluginEvent[$evtName] as $key => $val){
                if ($modx->pluginEvent[$evtName][$key] == $pluginName){
                    unset ($modx->pluginEvent[$evtName][$key]);
                    return true;
                }
            }
        }
        return false;
    }

	function regClientCSS($src, $media)
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

     # Registers Client-side JavaScript 	- these scripts are loaded at the end of the page unless $startup is true
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
    	$this->regClientScript($html, true, true);
    }
    
    function regClientHTMLBlock($html) // Registers Client-side HTML block
    {
    	$this->regClientScript($html, true);
    }
    
	# Registers Startup Client-side JavaScript - these scripts are loaded at inside the <head> tag
	function regClientStartupScript($src, $options)
	{
        $this->regClientScript($src, $options, true);
	}
	
	function parsePlaceholder($src='', $ph=array(), $left= '[+', $right= '+]',$mode='ph')
	{
		global $modx;
		return $modx->parseText($src, $ph, $left, $right, $mode);
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
		else             return false;
    }
    
	/*
	 * Template Variable Data Source @Bindings
	 * Created by Raymond Irving Feb, 2005
	 */

	function ProcessTVCommand($value, $name = '', $docid = '', $src='docform') {
	    global $modx;
	    $docid = intval($docid) ? intval($docid) : $modx->documentIdentifier;
	    $nvalue = trim($value);
	    if (substr($nvalue, 0, 1) !== '@')
	        return $value;
	    elseif($modx->config['enable_bindings']!=1 && $src==='docform')
	    {
	        return '@Bindings is disabled.';
	    }
	    else {
	        list ($cmd, $param) = $modx->ParseCommand($nvalue);
	        $cmd = trim($cmd);
	        $param = trim($param);
	        switch ($cmd) {
	            case "FILE" :
	            	if($modx->getExtention($param)==='.php') $output = 'Could not retrieve PHP file.';
	            	else $output = @file_get_contents($param);
	                if($output===false) $output = " Could not retrieve document '{$file}'.";
	                break;

	            case "CHUNK" : // retrieve a chunk and process it's content
	                $chunk = $modx->getChunk(trim($param));
	                $output = $chunk;
	                break;

	            case "DOCUMENT" : // retrieve a document and process it's content
	                $rs = $modx->getDocument($param);
	                if (is_array($rs))
	                    $output = $rs['content'];
	                else
	                    $output = "Unable to locate document {$param}";
	                break;

	            case "SELECT" : // selects a record from the cms database
	                $rt = array ();
	                $replacementVars = array (
	                    'dbase' => $modx->db->config['dbase'],
	                    'DBASE' => $modx->db->config['dbase'],
	                    'prefix' => $modx->db->config['table_prefix'],
	                    'PREFIX' => $modx->db->config['table_prefix']
	                );
	                foreach ($replacementVars as $rvKey => $rvValue) {
	                    $modx->setPlaceholder($rvKey, $rvValue);
	                }
	                $param = $modx->mergePlaceholderContent($param);
	                $rs = $modx->db->query("SELECT {$param}");
	                $output = $rs;
	                break;

	            case "EVAL" : // evaluates text as php codes return the results
	                $output = eval ($param);
	                break;

	            case "INHERIT" :
	                $output = $param; // Default to param value if no content from parents
	                if(empty($docid) && isset($_REQUEST['pid'])) $doc['parent'] = $_REQUEST['pid'];
	                else                                         $doc = $modx->getPageInfo($docid, 0, 'id,parent');

	                while ($doc['parent'] != 0) {
	                    $parent_id = $doc['parent'];

	                    // Grab document regardless of publish status
	                    $doc = $modx->getPageInfo($parent_id, 0, 'id,parent');

	                    $tv = $modx->getTemplateVar($name, '*', $doc['id'], null);

	                    // inheritance allows other @ bindings to be inherited
	                    // if no value is inherited and there is content following the @INHERIT binding,
	                    // that content will be used as the output
	                    // @todo consider reimplementing *appending* the output the follows an @INHERIT as opposed
	                    //       to using it as a default/fallback value; perhaps allow choice in behavior with
	                    //       system setting
	                    if ((string) $tv['value'] !== '' && !preg_match('%^@INHERIT[\s\n\r]*$%im', $tv['value'])) {
	                        $output = (string) $tv['value'];
	                        //$output = str_replace('@INHERIT', $output, $nvalue);
	                        break 2;
	                    }
	                }
	                break;

	            case 'DIRECTORY' :
	                $files = array ();
	                $path = $modx->config['base_path'] . $param;
	                if (substr($path, -1, 1) != '/') {
	                    $path .= '/';
	                }
	                if (!is_dir($path)) {
	                    die($path);
	                    break;
	                }
	                $dir = dir($path);
	                while (($file = $dir->read()) !== false) {
	                    if (substr($file, 0, 1) != '.') {
	                        $files[] = "{$file}=={$param}{$file}";
	                    }
	                }
	                asort($files);
	                $output = implode('||', $files);
	                break;

	            case 'NULL' :
	            case 'NONE' :
	                $output = '';
	                break;

	            default :
	                $output = $value;
	                break;

	        }
	        // support for nested bindings
	        return is_string($output) && ($output != $value) ? $modx->ProcessTVCommand($output, $name, $docid, $src) : $output;
	    }
	}

	// separate @ cmd from params
	function ParseCommand($binding_string)
	{
	    // Array of supported bindings. must be upper case
	    $BINDINGS = array (
	        'FILE',
	        'CHUNK',
	        'DOCUMENT',
	        'SELECT',
	        'EVAL',
	        'INHERIT',
	        'DIRECTORY',
	        'NONE'
	    );
		$binding_array = array();
		foreach($BINDINGS as $cmd)
		{
			if(strpos($binding_string,'@'.$cmd)===0)
			{
				$code = substr($binding_string,strlen($cmd)+2);
				$binding_array = array($cmd,trim($code));
				break;
			}
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
	
	// DISPLAY FORM ELEMENTS
	function renderFormElement($field_type, $field_id, $default_text, $field_elements, $field_value, $field_style='', $row = array()) {
		global $modx;
		global $base_url;
		global $rb_base_url;
		global $manager_theme, $_style;
		global $_lang;
		global $content;
		
		$field_html ='';
		$field_value = ($field_value!="" ? $field_value : $default_text);

		switch (strtolower($field_type)) {

			case "text":    // handler for regular text boxes
			case "rawtext"; // non-htmlentity converted text boxes
			case "email":   // handles email input fields
			case "number":  // handles the input of numbers
				$tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/text.inc.php');
				if($field_type=='text')       $class = 'text';
				elseif($field_type=='number') $class = 'text imeoff';
				else                          $class = "text {$field_type}";
				$ph['class']  = $class;
				$ph['id']     = "tv{$field_id}";
				$ph['name']   = "tv{$field_id}";
				$ph['value']  = htmlspecialchars($field_value);
				$ph['style']  = $field_style;
				$ph['tvtype'] = $field_type;
				$field_html =  $modx->parseText($tpl,$ph);
				break;
			case "textarea":     // handler for textarea boxes
			case "rawtextarea":  // non-htmlentity convertex textarea boxes
			case "htmlarea":     // handler for textarea boxes (deprecated)
			case "richtext":     // handler for textarea boxes
			case "textareamini": // handler for textarea mini boxes
				$tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/textarea.inc.php');
				$ph['class']  = "{$field_type} phptextarea";
				$ph['id']     = "tv{$field_id}";
				$ph['name']   = "tv{$field_id}";
				$ph['value']  = htmlspecialchars($field_value);
				$ph['style']  = $field_style;
				$ph['tvtype'] = $field_type;
				$ph['rows']   = $field_type==='textareamini' ? '5' : '15';
				$field_html =  $modx->parseText($tpl,$ph);
				break;
			case "date":
			case "dateonly":
				$tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/date.inc.php');
				$ph['class']           = 'DatePicker';
				$ph['id']              = 'tv' . str_replace(array('-', '.'),'_', urldecode($field_id));	;
				$ph['name']            = "tv{$field_id}";
				$ph['value']           = $field_value==0 || !isset($field_value) ? '' : htmlspecialchars($field_value);
				$ph['style']           = $field_style;
				$ph['tvtype']          = $field_type;
				$ph['cal_nodate']      = $_style['icons_cal_nodate'];
				$ph['yearOffset']      = $modx->config['datepicker_offset'];
				$ph['datetime_format'] = $modx->config['datetime_format'] . ($field_type==='date' ? ' hh:mm:00' : '');
				$field_html =  $modx->parseText($tpl,$ph);
				break;
			case "dropdown": // handler for select boxes
			case "listbox":  // handler for select boxes
			case "listbox-multiple": // handler for select boxes where you can choose multiple items
				$tpl = file_get_contents(MODX_CORE_PATH . 'docvars/inputform/list.inc.php');
				$rs = $this->ProcessTVCommand($field_elements, $field_id,'','tvform');
				$index_list = $this->ParseIntputOptions($rs);
				$tpl2 = '<option value="[+value+]" [+selected+]>[+label+]</option>';
				foreach ($index_list as $label=>$item)
				{
					list($label,$value) = $this->splitOption($item);
					$ph2['label']    = $label;
					$ph2['value']    = $value;
					$ph2['selected'] = ($value==$field_value) ? 'selected="selected"':'';
					$options[] = $modx->parseText($tpl2, $ph2);
				}
				$ph['options'] = join("\n",$options);
				$ph['id']      = "tv{$field_id}";
				$ph['name']    = "tv{$field_id}";
				$ph['size']   = (count($index_list)<8) ? count($index_list) : 8;
				$ph['extra'] = '';
				if($field_type==='listbox-multiple') $ph['extra'] = 'multiple';
				elseif($field_type==='dropdown')     $ph['size']   = '1';
				$field_html =  $modx->parseText($tpl,$ph);
				break;
			case "url": // handles url input fields
				$urls= array(''=>'--', 'http://'=>'http://', 'https://'=>'https://', 'ftp://'=>'ftp://', 'mailto:'=>'mailto:');
				$field_html ='<table border="0" cellspacing="0" cellpadding="0"><tr><td><select id="tv'.$field_id.'_prefix" name="tv'.$field_id.'_prefix">';
				foreach($urls as $k => $v)
				{
					if(strpos($field_value,$v)===false) $field_html.='<option value="'.$v.'">'.$k.'</option>';
					else
					{
						$field_value = str_replace($v,'',$field_value);
						$field_html.='<option value="'.$v.'" selected="selected">'.$k.'</option>';
					}
				}
				$field_html .='</select></td><td>';
				$field_html .=  '<input type="text" id="tv'.$field_id.'" name="tv'.$field_id.'" value="'.htmlspecialchars($field_value).'" width="100" '.$field_style.' /></td></tr></table>';
				break;
			case "checkbox": // handles check boxes
				if(!is_array($field_value)) $field_value = explode('||',$field_value);
				$rs = $this->ProcessTVCommand($field_elements, $field_id,'','tvform');
				$index_list = $this->ParseIntputOptions($rs);
				static $i=0;
				foreach ($index_list as $item)
				{
					list($label,$value) = $this->splitOption($item);
					$checked = ($this->isSelected($label,$value,$item,$field_value)) ? ' checked="checked"':'';
					$value = htmlspecialchars($value);
					$field_html .=  '<label for="tv_'.$i.'"><input type="checkbox" value="'.$value.'" id="tv_'.$i.'" name="tv'.$field_id.'[]" '. $checked.' />'.$label.'</label>';
					$i++;
				}
				break;
			case "option": // handles radio buttons
				$rs = $this->ProcessTVCommand($field_elements, $field_id,'','tvform');
				$index_list = $this->ParseIntputOptions($rs);
				static $i=0;
				while (list($label, $item) = each ($index_list))
				{
					list($label,$value) = $this->splitOption($item);
					$checked = ($this->isSelected($label,$value,$item,$field_value)) ?'checked="checked"':'';
					$value = htmlspecialchars($value);
					$field_html .=  '<label for="tv_'.$i.'"><input type="radio" value="'.$value.'" id="tv_'.$i.'" name="tv'.$field_id.'" '. $checked .' />'.$label.'</label>';
					$i++;
				}
				break;
			case "image":	// handles image fields using htmlarea image manager
				global $_lang;
				global $content,$use_editor,$which_editor;
				$field_html .='<input type="text" id="tv'.$field_id.'" name="tv'.$field_id.'"  value="'.$field_value .'" '.$field_style.' />&nbsp;<input type="button" value="'.$_lang['insert'].'" onclick="BrowseServer(\'tv'.$field_id.'\')" />';
				break;
			case "file": // handles the input of file uploads
			/* Modified by Timon for use with resource browser */
                global $_lang;
				global $content,$use_editor,$which_editor;
				$field_html .='<input type="text" id="tv'.$field_id.'" name="tv'.$field_id.'"  value="'.$field_value .'" '.$field_style.' />&nbsp;<input type="button" value="'.$_lang['insert'].'" onclick="BrowseFileServer(\'tv'.$field_id.'\')" />';
                
				break;
			case "hidden":
				$field_type = 'hidden';
				$field_html .=  '<input type="hidden" id="tv'.$field_id.'" name="tv'.$field_id.'" value="'.htmlspecialchars($field_value). '" tvtype="' . $field_type.'" />';
				break;

            case 'custom_tv':
                $custom_output = '';
                /* If we are loading a file */
                if(substr($field_elements, 0, 5) == "@FILE") {
                    $file_name = MODX_BASE_PATH . trim(substr($field_elements, 6));
                    if( !is_file($file_name) ) {
                        $custom_output = $file_name . ' does not exist';
                    } else {
                        $custom_output = file_get_contents($file_name);
                    }
                } elseif(substr($field_elements, 0, 8) == '@INCLUDE') {
                    $file_name = MODX_BASE_PATH . trim(substr($field_elements, 9));
                    if( !is_file($file_name) ) {
                        $custom_output = $file_name . ' does not exist';
                    } else {
                        ob_start();
                        include($file_name);
                        $custom_output = ob_get_contents();
                        ob_end_clean();
                    }
                } elseif(substr($field_elements, 0, 6) == "@CHUNK") {
                    $chunk_name = trim(substr($field_elements, 7));
                    $chunk_body = $modx->getChunk($chunk_name);
                    if($chunk_body == false) {
                        $custom_output = $_lang['chunk_no_exist']
                            . '(' . $_lang['htmlsnippet_name']
                            . ':' . $chunk_name . ')';
                } else {
                        $custom_output = $chunk_body;
                    }
                } elseif(substr($field_elements, 0, 5) == "@EVAL") {
                    $eval_str = trim(substr($field_elements, 6));
                    $custom_output = eval($eval_str);
                } else {
                    $custom_output = $field_elements;
                }
                    $replacements = array(
                        '[+field_type+]'   => $field_type,
                        '[+field_id+]'     => $field_id,
                        '[+field_name+]'   => "tv{$field_id}",
                        '[+default_text+]' => $default_text,
                        '[+field_value+]'  => htmlspecialchars($field_value),
                        '[+field_style+]'  => $field_style,
                        );
                $custom_output = str_replace(array_keys($replacements), $replacements, $custom_output);
                $modx->documentObject = $content;
                $custom_output = $modx->parseDocumentSource($custom_output);
                $field_html .= $custom_output;
                break;
            
			default: // the default handler -- for errors, mostly
				$sname = strtolower($field_type);
				$result = $modx->db->select('snippet','[+prefix+]site_snippets',"name='input:{$field_type}'");
				if($modx->db->getRecordCount($result)==1)
				{
					$field_html .= eval($modx->db->getValue($result));
				}
				else
					$field_html .=  '<input type="text" id="tv'.$field_id.'" name="tv'.$field_id.'" value="'.htmlspecialchars($field_value).'" '.$field_style.' />';
		} // end switch statement
		return $field_html;
	}

	function ParseIntputOptions($v)
	{
		global $modx;
		$a = array();
		if(is_array($v)) $a = $v;
		elseif(is_resource($v))
		{
			while ($cols = $modx->db->getRow($v,'num'))
			{
				$a[] = $cols;
			}
		}
		else
		{
			$a = explode('||', $v);
		}
		return $a;
	}
	
	function splitOption($value)
	{
		if(is_array($value))
		{
			$label=$value[0];
			$value=$value[1];
		}
		else
		{
			if(strpos($value,'==')===false)
				$label = $value;
			else
				list($label,$value) = explode('==',$value,2);
		}
		return array($label,$value);
	}
	
	function isSelected($label,$value,$item,$field_value)
	{
		if(is_array($item)) $item = $item['0'];
		if(strpos($item,'==')!==false && strlen($value)==0)
		{
			if(is_array($field_value))
			{
				$rs = in_array($label,$field_value);
			}
			else $rs = ($label===$field_value);
		}
		else
		{
			if(is_array($field_value))
			{
				$rs = in_array($value,$field_value);
			}
			else $rs = ($value===$field_value);
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
        global $modx_manager_charset;
        if (substr(strtolower($url), 0, 11) == "javascript:") {
            $fnc = substr($url, 11);
        } elseif ($url) {
            $fnc = "window.location.href='" . addslashes($url) . "';";
        } else {
            $fnc = "history.back(-1);";
        }
        echo "<html><head>
            <title>MODX :: Alert</title>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset={$modx_manager_charset};\">
            <script>
                function __alertQuit() {
                    alert('" . addslashes($msg) . "');
                    {$fnc}
                }
                window.setTimeout('__alertQuit();',100);
            </script>
            </head><body>
            <p>{$msg}</p>
            </body></html>";
            exit;
    }
    
	function getMimeType($filepath='')
	{
		$fp = fopen($filepath, 'rb');
		$head= fread($fp, 2); fclose($fp);
		$head = mb_convert_encoding($head, '8BIT');
		if($head==='BM')                    $mime_type = 'image/bmp';
		elseif($head==='GI')                $mime_type = 'image/gif';
		elseif($head===chr(0xFF).chr(0xd8)) $mime_type = 'image/jpeg';
		elseif($head===chr(0x89).'P')       $mime_type = 'image/png';
		else $mime_type = false;
		return $mime_type;
	}
	
	# returns true if the current web user is a member the specified groups
	function isMemberOfWebGroup($groupNames= array ())
	{
		global $modx;
		
		if (!is_array($groupNames)) return false;
		
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
			if (in_array(trim($v), $grpNames)) return true;
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
		global $modx;
		
		$field = 'mu.username, mu.password, mua.*';
		$from  = '[+prefix+]manager_users mu INNER JOIN [+prefix+]user_attributes mua ON mua.internalkey=mu.id';
		$rs= $modx->db->select($field,$from,"mu.id = '$uid'");
		$limit= $modx->db->getRecordCount($rs);
		if ($limit == 1)
		{
			$row= $modx->db->getRow($rs);
			if (!$row['usertype']) $row['usertype']= 'manager';
			return $row;
		}
		else return false;
	}
	
    # Returns current user name
    function getLoginUserName($context= '') {
    	global $modx;
        if (!empty($context) && isset ($_SESSION[$context . 'Validated'])) {
            return $_SESSION[$context . 'Shortname'];
        }
        elseif ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            return $_SESSION['webShortname'];
        }
        elseif ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return $_SESSION['mgrShortname'];
        }
        else return false;
    }

    # Returns current login user type - web or manager
    function getLoginUserType() {
    	global $modx;
        if ($modx->isFrontend() && isset ($_SESSION['webValidated'])) {
            return 'web';
        }
        elseif ($modx->isBackend() && isset ($_SESSION['mgrValidated'])) {
            return 'manager';
        } else {
            return '';
        }
    }
    
	function getDocumentChildrenTVars($parentid= 0, $tvidnames= '*', $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC', $tvfields= '*', $tvsort= 'rank', $tvsortdir= 'ASC')
	{
		global $modx;
		
		$docs= $modx->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
		if (!$docs) return false;
		else
		{
			foreach($docs as $doc)
			{
				$result[] = $modx->getTemplateVars($tvidnames, $tvfields, $doc['id'],$published);
			}
			return $result;
		}
	}
		
	function getDocumentChildrenTVarOutput($parentid= 0, $tvidnames= '*', $published= 1, $docsort= 'menuindex', $docsortdir= 'ASC')
	{
		global $modx;
		
		$docs= $modx->getDocumentChildren($parentid, $published, 0, '*', '', $docsort, $docsortdir);
		if (!$docs) return false;
		else
		{
			$result= array ();
			foreach($docs as $doc)
			{
				$tvs= $modx->getTemplateVarOutput($tvidnames, $doc['id'], $published, '', '');
				if ($tvs) $result[$doc['id']]= $tvs; // Use docid as key - netnoise 2006/08/14
			}
			return $result;
		}
	}
	
	function getAllChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle',$where=false)
	{
		global $modx;
		
		// modify field names to use sc. table reference
		$fields= $modx->join(',', explode(',',$fields),'sc.');
		$sort  = $modx->join(',', explode(',',$sort),'sc.');
		
		// build query
		$from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
		if($where===false)
		{
			// get document groups for current user
			if ($docgrp= $modx->getUserDocGroups())
			{
				$docgrp= implode(',', $docgrp);
				$cond = "OR dg.document_group IN ({$docgrp}) OR 1='{$_SESSION['mgrRole']}'";
			}
			else $cond = '';
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
		return $resourceArray;
	}
	
	function getActiveChildren($id= 0, $sort= 'menuindex', $dir= 'ASC', $fields= 'id, pagetitle, description, parent, alias, menutitle')
	{
		global $modx;
		
		// get document groups for current user
		if ($docgrp= $modx->getUserDocGroups())
		{
			$docgrp= implode(',', $docgrp);
			$cond = " OR dg.document_group IN ({$docgrp})";
		}
		else $cond = '';
		if($modx->isFrontend()) $context = 'sc.privateweb=0';
		else                    $context = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0";
		$where = "sc.parent = '{$id}' AND sc.published=1 AND sc.deleted=0 AND ({$context} {$cond}) GROUP BY sc.id";
		
		$resourceArray = $modx->getAllChildren($id, $sort, $dir, $fields,$where);
		
		return $resourceArray;
	}
	
	function getDocumentChildren($parentid= 0, $published= 1, $deleted= 0, $fields= '*', $where= '', $sort= 'menuindex', $dir= 'ASC', $limit= '')
	{
		global $modx;
		
		// modify field names to use sc. table reference
		$fields = $modx->join(',', explode(',',$fields),'sc.');
		if($where != '') $where= "AND {$where}";
		// get document groups for current user
		if ($docgrp= $modx->getUserDocGroups()) $docgrp= implode(',', $docgrp);
		// build query
		$access  = $modx->isFrontend() ? 'sc.privateweb=0' : "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0";
		$access .= !$docgrp ? '' : " OR dg.document_group IN ({$docgrp})";
		$from = '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
		$where = "sc.parent = '{$parentid}' AND sc.published={$published} AND sc.deleted={$deleted} {$where} AND ({$access}) GROUP BY sc.id";
		$sort = ($sort != '') ? $modx->join(',', explode(',',$sort),'sc.') : '';
		$orderby = $sort ? "{$sort} {$dir}" : '';
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
        if($input['pub_date']==='')    $input['pub_date']    = '0';
        if($input['unpub_date']==='')  $input['unpub_date']  = '0';
        if($input['publishedon']==='') $input['publishedon'] = '0';
        
        return $input;
	}
	
	function loadLexicon($target='manager') {
		global $modx, $_lang;
		
		if (!isset($modx->config['manager_language'])) {
		    $lang = 'japanese-utf8';
		}
		else $langname = $modx->config['manager_language'];
		if($target==='manager') {
			global $modx_manager_charset, $modx_lang_attribute, $modx_textdir;
			$target = MODX_CORE_PATH . 'lang/';
			$modx_manager_charset = 'utf-8';
			$modx_lang_attribute = 'ja';
			$modx_textdir = 'ltr';
		}
		$target = rtrim($target, '/') . '/';
		
		$_lang = array();
		include_once("{$target}{$langname}.inc.php");
	}
	
	function snapshot($filename='', $target='') {
		global $modx, $modx_version;
		
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
			$today = $modx->toDateFormat(time());
			$today = str_replace(array('/',' '), '-', $today);
			$today = str_replace(':', '', $today);
			$today = strtolower($today);
			$filename = "{$today}-{$modx_version}.sql";
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
        $out=array();
        if(empty($modx->version) || !is_array($modx->version)){
            //include for compatibility modx version < 1.0.10
            include MODX_MANAGER_PATH . "includes/version.inc.php";
            $modx->version=array();
            $modx->version['version']= isset($modx_version) ? $modx_version : '';
            $modx->version['branch']= isset($modx_branch) ? $modx_branch : '';
            $modx->version['release_date']= isset($modx_release_date) ? $modx_release_date : '';
            $modx->version['full_appname']= isset($modx_full_appname) ? $modx_full_appname : '';
            $modx->version['new_version'] = isset($modx->config['newversiontext']) ? $modx->config['newversiontext'] : '';
        }
        return (!is_null($data) && is_array($modx->version) && isset($modx->version[$data])) ? $modx->version[$data] : $modx->version;
    }
}
