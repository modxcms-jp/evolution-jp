<?php
function sendmail($params=array(), $msg='')
{
	global $modx;

	if(isset($params) && is_string($params))
	{
		if(strpos($params,'=')===false)
		{
			if(strpos($params,'@')!==false) $p['sendto']  = $params;
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
	if($msg==='') $msg = $_SERVER['REQUEST_URI'] . "\n" . $_SERVER['HTTP_USER_AGENT'] . "\n" . $_SERVER['HTTP_REFERER'];
	include_once $modx->config['base_path'] . 'manager/includes/controls/modxmailer.inc.php';
	$mail = new MODxMailer();
	$mail->From     = (!isset($p['from']))     ? $modx->config['emailsender']  : $p['from'];
	$mail->FromName = (!isset($p['fromname'])) ? $modx->config['site_name']    : $p['fromname'];
	$mail->Subject  = (!isset($p['subject']))  ? $modx->config['emailsubject'] : $p['subject'];
	$sendto         = (!isset($p['sendto']))   ? $modx->config['emailsender']  : $p['sendto'];
	$mail->Body     = $msg;
	$sendto = explode(',',$sendto);
	foreach($sendto as $to)
	{
		$to = trim($to);
		$mail->AddAddress($to);
	}
	$rs = $mail->Send();
	return $rs;
}

function rotate_log($target='event_log',$limit=2000, $trim=100)
{
	global $modx, $dbase;
	
	$dbase = trim($dbase,'`');
	
	if($limit < $trim) $trim = $limit;
	
	$count = $modx->db->getValue($modx->db->select('COUNT(id)',"[+prefix+]{$target}"));
	$over = $count - $limit;
	if(0 < $over)
	{
		$trim = ($over + $trim);
		$modx->db->delete("[+prefix+]{$target}",'','',$trim);
	}
	$result = $modx->db->query("SHOW TABLE STATUS FROM `{$dbase}`");
	while ($row = $modx->db->getRow($result))
	{
		$modx->db->query('OPTIMIZE TABLE ' . $row['Name']);
	}
}

function logEvent($evtid, $type, $msg, $title= 'Parser')
{
	global $modx;
	
	$type=(int)$type; 
	if ($type < 1) $type= 1; // Types: 1 = information, 2 = warning, 3 = error
	if (3 < $type) $type= 3;
	
	$mailbody = $msg;
	$pos = strpos($msg,'<h3 style="color:red">&laquo; MODX Parse Error &raquo;</h3>');
	if($pos!==false) $mailbody = substr($mailbody, 0, $pos);
	$mailbody = strip_tags($mailbody);
	if(1000 < strlen($mailbody)) $mailbody = substr($mailbody,0,1000);
	$request_uri = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, $modx->config['modx_charset']);
	$ua       = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, $modx->config['modx_charset']);
	$mailbody .= "\n{$request_uri}\n{$ua}";
	
	$msg= $modx->db->escape($msg . "\n" . $modx->config['site_url']);
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
	if ($LoginUserID == '' || $LoginUserID===false) $LoginUserID = '-';
	
	$fields['eventid']     = intval($evtid);
	$fields['type']        = $type;
	$fields['createdon']   = time();
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
			$mailbody = urldecode($mailbody);
			$modx->sendmail($subject,$mailbody);
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

function clearCache($params=array())
{
	global $modx;
	
	if(opendir(MODX_BASE_PATH . 'assets/cache')!==false)
	{
		$showReport = ($params['showReport']) ? $params['showReport'] : false;
		$target = ($params['target']) ? $params['target'] : 'pagecache,sitecache';
		
		include_once MODX_MANAGER_PATH . 'processors/cache_sync.class.processor.php';
		$sync = new synccache();
		$sync->setCachepath(MODX_BASE_PATH . 'assets/cache/');
		$sync->setReport($showReport);
		$sync->setTarget($target);
		if(isset($params['cacheRefreshTime'])) $sync->cacheRefreshTime = $params['cacheRefreshTime'];
		$sync->emptyCache(); // first empty the cache
		return true;
	}
	else return false;
}

function messageQuit($msg= 'unspecified error', $query= '', $is_error= true, $nr= '', $file= '', $source= '', $text= '', $line= '', $output='')
{
	global $modx;
	
    $version= isset ($GLOBALS['modx_version']) ? $GLOBALS['modx_version'] : '';
	$release_date= isset ($GLOBALS['release_date']) ? $GLOBALS['release_date'] : '';
    $request_uri = $_SERVER['REQUEST_URI'];
    $request_uri = htmlspecialchars($request_uri, ENT_QUOTES, $modx->config['modx_charset']);
    $ua          = htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, $modx->config['modx_charset']);
    $referer     = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, $modx->config['modx_charset']);
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
    if($error_level === 2) return true;

    // Set 500 response header
    header('HTTP/1.1 500 Internal Server Error');

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
	global $modx;
	
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
