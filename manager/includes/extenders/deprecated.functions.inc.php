<?php
$this->old = new OldFunctions();
class OldFunctions {
function makeList($array,$ulroot='root',$ulprefix='sub_',$type='',$ordered= false,$tablevel= 0)
{
	global $modx;
	// first find out whether the value passed is an array
	if (!is_array($array)) return "<ul><li>Bad list</li></ul>";
	
	$tabs= '';
	for ($i= 0; $i < $tablevel; $i++)
	{
		$tabs .= "\t";
	}
	
	$tag = ($ordered == true) ? 'ol' : 'ul';
	
	if(!empty($type)) $typestr= " style='list-style-type: {$type}'";
	else              $typestr= '';
	
	$listhtml= "{$tabs}<{$tag} class='{$ulroot}'{$typestr}>\n";
	foreach ($array as $key => $value)
	{
		if (is_array($value))
		{
			$line = $modx->makeList($value, "{$ulprefix}{$ulroot}", $ulprefix, $type, $ordered, $tablevel +2);
			$listhtml .= "{$tabs}\t<li>{$key}\n{$line}{$tabs}\t</li>\n";
		}
		else
		{
			$listhtml .= "{$tabs}\t<li>{$value}</li>\n";
		}
	}
	$listhtml = "{$tabs}</{$tag}>\n";
	return $listhtml;
}

function getUserData()
{
	$client['host'] = $_SERVER['REMOTE_ADDR'];
	$client['ip']   = $_SERVER['REMOTE_ADDR'];
	$client['ua']   = $_SERVER['HTTP_USER_AGENT'];
	return $client;
}

function insideManager()
{
	$m= false;
	if (defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == 'true')
	{
		$m= true;
		if(defined('SNIPPET_INTERACTIVE_MODE') && SNIPPET_INTERACTIVE_MODE == 'true')
		{
			$m= "interact";
		}
		elseif(defined('SNIPPET_INSTALL_MODE') && SNIPPET_INSTALL_MODE == 'true')
		{
			$m= "install";
		}
	}
	return $m;
}

function putChunk($chunkName)   {global $modx;return $modx->getChunk($chunkName);}// deprecated alias name >.<
function getDocGroups()         {global $modx;return $modx->getUserDocGroups();} // deprecated
function changePassword($o, $n) {return changeWebUserPassword($o, $n);} // deprecated

function mergeDocumentMETATags($template) {
	global $modx;
    if ($modx->documentObject['haskeywords'] == 1) {
        // insert keywords
        $keywords = $modx->getKeywords();
        if (is_array($keywords) && count($keywords) > 0) {
            $keywords = implode(", ", $keywords);
            $metas= "\t<meta name=\"keywords\" content=\"{$keywords}\" />\n";
        }

    // Don't process when cached
    $modx->documentObject['haskeywords'] = '0';
    }
    if ($modx->documentObject['hasmetatags'] == 1) {
        // insert meta tags
        $tags= $modx->getMETATags();
        foreach ($tags as $n => $col) {
            $tag= strtolower($col['tag']);
            $tagvalue= $col['tagvalue'];
            $tagstyle= $col['http_equiv'] ? 'http-equiv' : 'name';
            $metas .= "\t<meta {$tagstyle}=\"{$tag}\" content=\"{$tagvalue}\" />\n";
        }

    // Don't process when cached
    $modx->documentObject['hasmetatags'] = '0';
    }
if (isset($metas) && $metas) $template = preg_replace("/(<head>)/i", "\\1\n\t" . trim($metas), $template);
    return $template;
}

function getMETATags($id= 0) {
	global $modx;
    if ($id == 0) {
        $id= $modx->documentObject['id'];
    }
    $sql= "SELECT smt.* " .
    "FROM " . $modx->getFullTableName("site_metatags") . " smt " .
    "INNER JOIN " . $modx->getFullTableName("site_content_metatags") . " cmt ON cmt.metatag_id=smt.id " .
    "WHERE cmt.content_id = '$id'";
    $ds= $modx->db->query($sql);
    $limit= $modx->db->getRecordCount($ds);
    $metatags= array ();
    if ($limit > 0) {
        for ($i= 0; $i < $limit; $i++) {
            $row= $modx->db->getRow($ds);
            $metatags[$row['name']]= array (
                "tag" => $row['tag'],
                "tagvalue" => $row['tagvalue'],
                "http_equiv" => $row['http_equiv']
            );
        }
    }
    return $metatags;
}

function userLoggedIn()
{
	global $modx;
	$userdetails= array ();
	if ($modx->isFrontend() && isset ($_SESSION['webValidated']))
	{
		// web user
		$userdetails['loggedIn']= true;
		$userdetails['id']= $_SESSION['webInternalKey'];
		$userdetails['username']= $_SESSION['webShortname'];
		$userdetails['usertype']= 'web'; // added by Raymond
		return $userdetails;
	}
	elseif($modx->isBackend() && isset ($_SESSION['mgrValidated']))
	{
		// manager user
		$userdetails['loggedIn']= true;
		$userdetails['id']= $_SESSION['mgrInternalKey'];
		$userdetails['username']= $_SESSION['mgrShortname'];
		$userdetails['usertype']= 'manager'; // added by Raymond
		return $userdetails;
	}
	else
	{
		return false;
	}
}

function getKeywords($id= 0) {
	global $modx;
    if ($id == 0) {
        $id= $modx->documentObject['id'];
    }
    $tblKeywords= $modx->getFullTableName('site_keywords');
    $tblKeywordXref= $modx->getFullTableName('keyword_xref');
    $from = "{$tblKeywords} AS keywords INNER JOIN {$tblKeywordXref} AS xref ON keywords.id=xref.keyword_id";
    $result= $modx->db->select('keywords.keyword',$from,"xref.content_id = '{$id}'");
    $limit= $modx->db->getRecordCount($result);
    $keywords= array ();
    if ($limit > 0) {
        while($row= $modx->db->getRow($result))
        {
            $keywords[]= $row['keyword'];
        }
    }
    return $keywords;
}

function makeFriendlyURL($pre, $suff, $path) {
	global $modx;
	$elements = explode('/',$path);
	$alias    = array_pop($elements);
	$dir      = implode('/', $elements);
	unset($elements);
	if((strpos($alias, '.') !== false))
	{
		if(isset($modx->config['suffix_mode']) && $modx->config['suffix_mode']==1) $suff = ''; // jp-edition only
	}
	//container_suffix
	if(substr($alias,0,1) === '[' && substr($alias,-1) === ']') return '[~' . $alias . '~]';
	return ($dir !== '' ? $dir . '/' : '') . $pre . $alias . $suff;
}

function _IIS_furl_fix()
{
	global $modx;
	
	if($modx->config['friendly_urls'] != 1) return;
	
	$url= $_SERVER['QUERY_STRING'];
	$err= substr($url, 0, 3);
	if ($err == '404' || $err == '405')
	{
		$k= array_keys($_GET);
		unset ($_GET[$k['0']]);
		unset ($_REQUEST[$k['0']]); // remove 404,405 entry
		$_SERVER['QUERY_STRING']= $qp['query'];
		$qp= parse_url(str_replace($modx->config['site_url'], '', substr($url, 4)));
		if (!empty ($qp['query']))
		{
			parse_str($qp['query'], $qv);
			foreach ($qv as $n => $v)
			{
				$_REQUEST[$n]= $_GET[$n]= $v;
			}
		}
		$_SERVER['PHP_SELF']= $modx->config['base_url'] . $qp['path'];
		$_REQUEST['q']= $_GET['q']= $qp['path'];
	}
}

# Displays a javascript alert message in the web browser
function webAlert($msg, $url= '')
{
	global $modx;
	
	$msg= addslashes($modx->db->escape($msg));
	if (substr(strtolower($url), 0, 11) == 'javascript:')
	{
		$act= '__WebAlert();';
		$fnc= 'function __WebAlert(){' . substr($url, 11) . '};';
	}
	else
	{
		$act= $url ? "window.location.href='" . addslashes($url) . "';" : '';
	}
	$html= "<script>{$fnc} window.setTimeout(\"alert('{$msg}');{$act}\",100);</script>";
	if ($modx->isFrontend())
	{
		$modx->regClientScript($html);
	}
	else
	{
		echo $html;
	}
}
}