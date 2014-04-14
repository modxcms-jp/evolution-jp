<?php
/*####
#
#	Name: PHx class(Placeholders Xtended)
#	Version: 2.2
#	Author: Armand "bS" Pondman (apondman@zerobarrier.nl)
#	Modified by Nick to include external files
#	Modified by yama yamamoto@kyms.jp
#	Date: 2012/07/28
#
####*/

class PHx {
	function PHx()
	{
		global $modx;
		if (function_exists('mb_internal_encoding')) mb_internal_encoding($modx->config['modx_charset']);
	}
	
	// Parser: modifier detection and eXtended processing if needed
	function Filter($phxkey, $value, $cmd, $opt='')
	{
		global $modx;
		
		$cmd=strtolower($cmd);
		
		if($modx->config['output_filter']==='1')
			$this->elmName = "phx:{$cmd}";
		else
			$this->elmName = $cmd;
		
		if($phxkey==='documentObject') $value = $modx->documentIdentifier;
		if(!$modx->snippetCache) $modx->setSnippetCache();
		if( isset($modx->snippetCache[$this->elmName]) )
			$value = $this->getValueFromElement($phxkey, $value, $cmd, $opt);
		elseif(isset($this->chunkCache[$phxkey]))
			$value = $this->getValueFromElement($phxkey, $value, $cmd, $opt);
		else
			$value = $this->getValueFromPreset($phxkey, $value, $cmd, $opt);
		if($modx->config['output_filter']==='1') $value = str_replace('[+key+]', $phxkey, $value);
		else                                     $value = str_replace('[+name+]', $phxkey, $value);
		return $value;
	}
	
	function getValueFromPreset($phxkey, $value, $cmd, $opt)
	{
		global $modx, $condition;
		
		switch ($cmd)
		{
			#####  Conditional Modifiers 
			case 'input':
			case 'if':
				$value = $opt;
				break;
			case 'equals':
			case 'is':
			case 'eq':
				$condition[] = intval($value == $opt); break;
			case 'notequals':
			case 'isnot':
			case 'isnt':
			case 'ne':
				$condition[] = intval($value != $opt);break;
			case 'isgreaterthan':
			case 'isgt':
			case 'eg':
				$condition[] = intval($value >= $opt);break;
			case 'islowerthan':
			case 'islt':
			case 'el':
				$condition[] = intval($value <= $opt);break;
			case 'greaterthan':
			case 'gt':
				$condition[] = intval($value > $opt);break;
			case 'lowerthan':
			case 'lt':
				$condition[] = intval($value < $opt);break;
			case 'find':
				$condition[] = intval(strpos($value, $opt)!==false);break;
			case 'preg':
				$condition[] = intval(preg_match($opt,$value));break;
			case 'isinrole':
			case 'ir':
			case 'memberof':
			case 'mo':
				// Is Member Of  (same as inrole but this one can be stringed as a conditional)
				if ($value == '&_PHX_INTERNAL_&') $value = $this->user['id'];
				$grps = ($this->strlen($modifier_value) > 0 ) ? explode(',',$opt) :array();
				$condition[] = intval($this->isMemberOfWebGroupByUserId($value,$grps));
				break;
			case 'or':
				$condition[] = '||';break;
			case 'and':
				$condition[] = '&&';break;
			case 'show':
			case 'this':
				$conditional = implode(' ',$condition);
				$isvalid = intval(eval('return ('. $conditional. ');'));
				if (!$isvalid) { $value = NULL;}
			case 'then':
				$conditional = implode(' ',$condition);
				$isvalid = intval(eval('return ('. $conditional. ');'));
				if ($isvalid) { $value = $opt; }
				else { $value = NULL; }
				break;
			case 'else':
				$conditional = implode(' ',$condition);
				$isvalid = intval(eval('return ('. $conditional. ');'));
				if (!$isvalid) { $value = $opt; }
				break;
			case 'select':
				$raw = explode('&',$opt);
				$map = array();
				$c = count($raw);
				for($m=0; $m<$c; $m++) {
					$mi = explode('=',$raw[$m]);
					$map[$mi[0]] = $mi[1];
				}
				$value = $map[$value];
				break;
			##### End of Conditional Modifiers
			
			#####  String Modifiers
			case 'lcase':
			case 'strtolower':
				$value = $this->strtolower($value); break;
			case 'ucase':
			case 'strtoupper':
				$value = $this->strtoupper($value); break;
			case 'htmlent':
			case 'htmlentities':
				$value = htmlentities($value,ENT_QUOTES,$modx->config['modx_charset']); break;
			case 'html_entity_decode':
				$value = html_entity_decode($value,ENT_QUOTES,$modx->config['modx_charset']); break;
			case 'esc':
				$value = preg_replace('/&amp;(#[0-9]+|[a-z]+);/i', '&$1;', htmlspecialchars($value));
			$value = str_replace(array('[', ']', '`'),array('&#91;', '&#93;', '&#96;'),$value);
				break;
			case 'strip':
				$value = str_replace(array("\n","\r","\t","\s"), ' ', $value); break;
			case 'notags':
			case 'strip_tags':
				if($opt!=='')
				{
					foreach(explode(',',$opt) as $v)
					{
						$param[] = "<{$v}>";
					}
					$params = join(',',$param);
				}
				else $params = '';
				$value = strip_tags($value,$params);
				break;
			case 'length':
			case 'len':
			case 'strlen':
				$value = $this->strlen($value); break;
			case 'reverse':
			case 'strrev':
				$value = $this->strrev($value); break;
			case 'wordwrap':
				// default: 70
			  	$wrapat = intval($opt) ? intval($opt) : 70;
				$value = preg_replace("~(\b\w+\b)~e","wordwrap('\\1',\$wrapat,' ',1)",$value);
				break;
			case 'limit':
				// default: 100
			  	$limit = intval($opt) ? intval($opt) : 100;
				$value = $this->substr($value,0,$limit);
				break;
			case 'str_shuffle':
			case 'shuffle':
				$value = $this->str_shuffle($value); break;
			case 'str_word_count':
			case 'word_count':
			case 'wordcount':
				$value = $this->str_word_count($value); break;
			case 'zenhan':
				if(empty($opt)) $opt='Krns';
				$value = mb_convert_kana($value,$opt,$modx->config['modx_charset']); break;
			case 'hanzen':
				if(empty($opt)) $opt='KAS';
				$value = mb_convert_kana($value,$opt,$modx->config['modx_charset']); break;
			case 'str_replace':
				if(empty($opt) || strpos($opt,',')===false) break;
				list($s,$r) = explode(',',$opt,2);
				if($value!=='') $value = str_replace($s,$r,$value);
				break;
			case 'replace_to':
				if($value!=='') $value = str_replace(array('[+value+]','[+output+]'),$value,$opt);
				break;
			case '.':
				if($value!=='') $value = $value . $opt;
				break;
			case 'nl2lf':
				if($value!=='') $value = str_replace(array("\r\n","\n", "\r"), '\n', $value);
				break;
			case 'toint':
				$value = intval($value);
				break;
			case 'tofloat':
				$value = floatval($value);
				break;
			case 'tobool':
				$value = boolval($value);
				break;
			
			// These are all straight wrappers for PHP functions
			case 'ucfirst':
			case 'lcfirst':
			case 'ucwords':
			case 'addslashes':
			case 'ltrim':
			case 'rtrim':
			case 'trim':
			case 'nl2br':
			case 'md5':
			case 'urlencode':
			case 'urldecode':
			case 'rawurlencode':
			case 'rawurldecode':
			case 'base64_encode':
			case 'base64_ decode':
				$value = $cmd($value);
				break;
			
			#####  Resource fields
			case 'id':
				if($opt) $value = $this->getDocumentObject($opt,$phxkey,'direct');
				break;
			case 'type':
			case 'contentType':
			case 'pagetitle':
			case 'longtitle':
			case 'description':
			case 'alias':
			case 'link_attributes':
			case 'published':
			case 'pub_date':
			case 'unpub_date':
			case 'parent':
			case 'isfolder':
			case 'content':
			case 'richtext':
			case 'template':
			case 'menuindex':
			case 'searchable':
			case 'cacheable':
			case 'createdby':
			case 'createdon':
			case 'editedby':
			case 'editedon':
			case 'deleted':
			case 'deletedon':
			case 'deletedby':
			case 'publishedon':
			case 'publishedby':
			case 'menutitle':
			case 'donthit':
			case 'haskeywords':
			case 'hasmetatags':
			case 'privateweb':
			case 'privatemgr':
			case 'content_dispo':
			case 'hidemenu':
				$value = $this->getDocumentObject($value,$cmd);
				break;
			case 'title':
				$pagetitle = $this->getDocumentObject($value,'pagetitle');
				$longtitle = $this->getDocumentObject($value,'longtitle');
				$value = $longtitle ? $longtitle : $pagetitle;
				break;
			case 'shorttitle':
				$pagetitle = $this->getDocumentObject($value,'pagetitle');
				$menutitle = $this->getDocumentObject($value,'menutitle');
				$value = $menutitle ? $menutitle : $pagetitle;
				break;
			
			#####  Special functions 
			case 'math':
				$filter = preg_replace("~([a-zA-Z\n\r\t\s])~",'',$opt);
				$filter = str_replace('?',$value,$filter);
				$value = eval('return '.$filter.';');
				break;
			case 'ifempty':
				if (empty($value)) $value = $opt; break;
			case 'ifnotempty':
				if (!empty($value)) $value = $opt; break;
			case 'strftime':
			case 'date':
				if(empty($opt)) $opt = $modx->toDateFormat(null, 'formatOnly');
				if(!preg_match('@^[0-9]+$@',$value)) $value = strtotime($value);
				$value = $modx->mb_strftime($opt,0+$value);
				break;
			case 'time':
				if(empty($opt)) $opt = '%H:%M';
				if(!preg_match('@^[0-9]+$@',$value)) $value = strtotime($value);
				$value = $modx->mb_strftime($opt,0+$value);
				break;
			case 'userinfo':
				if(empty($opt)) $opt = 'username';
				$value = $this->ModUser($value,$opt);
				break;
			case 'webuserinfo':
				if(empty($opt)) $opt = 'username';
				$value = $this->ModUser(-$value,$opt);
				break;
			case 'inrole':
				// deprecated
				$grps = ($this->strlen($opt) > 0 ) ? explode(',', $opt) :array();
				$value = intval($this->isMemberOfWebGroupByUserId($value,$grps));
				break;
			case 'googlemap':
			case 'googlemaps':
				if(empty($opt)) $opt = 'border:none;width:500px;height:350px;';
				$tpl = '<iframe style="[+style+]" src="https://maps.google.co.jp/maps?ll=[+value+]&output=embed&z=15"></iframe>';
				$ph['style'] = $opt;
				$ph['value'] = $value;
				$value = $modx->parseText($tpl,$ph);
				break;
			// If we haven't yet found the modifier, let's look elsewhere
			default:
				$value = $this->getValueFromElement($phxkey, $value, $cmd, $opt);
				break;
		}
		return $value;
	}

	function getValueFromElement($phxkey, $value, $cmd, $opt)
	{
		global $modx;
		if( isset($modx->snippetCache[$this->elmName]) )
		{
			$php = $modx->snippetCache[$this->elmName];
		}
		else
		{
			$esc_elmName = $modx->db->escape($this->elmName);
			$result = $modx->db->select('snippet','[+prefix+]site_snippets',"name='{$esc_elmName}'");
			$total = $modx->db->getRecordCount($result);
			if($total == 1)
			{
				$row = $modx->db->getRow($result);
				$php = $row['snippet'];
			}
			elseif($total == 0)
			{
				$modifiers_path = "{$modx->config['base_dir']}assets/plugins/phx/modifiers/{$cmd}.phx.php";
				if(is_file($modifiers_path))
				{
					$php = @file_get_contents($modifiers_path);
					$php = trim($php);
					$php = preg_replace('@^\s*<\?php@', '', $php);
					$php = preg_replace('@?>\s*$@', '', $php);
					$php = preg_replace('@^<\?@', '', $php);
					$modx->snippetCache[$this->elmName.'Props'] = '';
				}
				else
				{
					$php = false;
				}
			}
			else $php = false;
			$modx->snippetCache[$this->elmName]= $php;
		}
		if($php==='') $php=false;
		
		if($php===false) $html = $modx->getChunk($this->elmName);
		else             $html = false;

		if($modx->config['output_filter']==='1') $self = '[+output+]';
		else                                     $self = '[+input+]';
		
		if($php !== false)
		{
			ob_start();
			$options = $opt;
			if($modx->config['output_filter']==='1') $output = $value;
			else                                     $input = $value;
			if($modx->config['output_filter']==='1') $name = $phxkey;
			else                                     $key  = $phxkey;
			
			$custom = eval($php);
			$msg = ob_get_contents();
			$value = $msg . $custom;
			ob_end_clean();
		}
		elseif($html!==false && $value!=='')
		{
			$html = str_replace(array($self,'[+value+]'), $value, $html);
			$value = str_replace(array('[+options+]','[+param+]'), $opt, $html);
		}
		if($php===false && $html===false && $value!==''
		   && (strpos($cmd,'[+value+]')!==false || strpos($cmd,$self)!==false))
		{
			$value = str_replace(array('[+value+]',$self),$value,$cmd);
		}
		return $value;
	}
	// Returns the specified field from the user record
	// positive userid = manager, negative integer = webuser
	function ModUser($userid,$field) {
		global $modx;
		if (!isset($this->cache['ui']) || !array_key_exists($userid, $this->cache['ui'])) {
			if (intval($userid) < 0) {
				$user = $modx->getWebUserInfo(-($userid));
			} else {
				$user = $modx->getUserInfo($userid);
			}
			$this->cache['ui'][$userid] = $user;
		} else {
			$user = $this->cache['ui'][$userid];
		}
		$user['name'] = !empty($user['fullname']) ? $user['fullname'] : $user['fullname'];
		return $user[$field];
	}
	 
	 // Returns true if the user id is in one the specified webgroups
	 function isMemberOfWebGroupByUserId($userid=0,$groupNames=array()) {
		global $modx;
		
		// if $groupNames is not an array return false
		if(!is_array($groupNames)) return false;
		
		// if the user id is a negative number make it positive
		if (intval($userid) < 0) { $userid = -($userid); }
		
		// Creates an array with all webgroups the user id is in
		if (!array_key_exists($userid, $this->cache['mo'])) {
			$tbl = $modx->getFullTableName('webgroup_names');
			$tbl2 = $modx->getFullTableName('web_groups');
			$sql = "SELECT wgn.name FROM $tbl wgn INNER JOIN $tbl2 wg ON wg.webgroup=wgn.id AND wg.webuser='{$userid}'";
			$this->cache['mo'][$userid] = $grpNames = $modx->db->getColumn('name',$sql);
		} else {
			$grpNames = $this->cache['mo'][$userid];
		}
		// Check if a supplied group matches a webgroup from the array we just created
		foreach($groupNames as $k=>$v)
			if(in_array(trim($v),$grpNames)) return true;
		
		// If we get here the above logic did not find a match, so return false
		return false;
	 }
	function phxFilter($key,$value,$modifiers)
	{
		$modifiers = $this->splitModifiers($modifiers);
		$value = $this->parsePhx($key,$value,$modifiers);
		return $value;
	}
	
	function parsePhx($key,$value,$modifiers)
	{
		global $condition;
		if(empty($modifiers)) return;
		//if(isset($phx) && is_object($phx))
		$condition = array();
		foreach($modifiers as $cmd=>$opt)
		{
			$value = $this->Filter($key,$value, $cmd, $opt);
		}
		return $value;
	}
	
	function splitModifiers($modifiers)
	{
		global $modx;
		
		$reslut = array();
		$delim = '';
		$remain = $modifiers;
		$scope = 'key';
		$key   = '';
		$value = '';
		$safecount=0;
		while($remain!=='' && $safecount < 3000)
		{
			$safecount++;
			$s = substr($remain,0,1);
			$remain = substr($remain,1);
			
			if($scope==='key')
			{
				switch($s)
				{
				    case ':':
				    	$key=trim($key); $reslut[$key]=$value; $key=''; $value='';
				    	break;
				    case '=':
				    	switch($remain['0'])
					    {
				    	    case '"': case "'": case '`':
				    	    	$delim = $remain['0'];
				    	    	$remain = substr($remain,1);
    							list($value, $remain) = explode($delim, $remain, 2);
    							$key=trim($key); $reslut[$key]=$value; $key=''; $value='';
				    	    default:
				    	    	$scope = 'value';
				    	    	$value = '';
				    	}
				    	break;
				    default:
				    	$key .= $s;
				}
			}
			else
			{
				switch($s)
				{
				    case ':':
				    	$key=trim($key); $reslut[$key]=$value; $key=''; $value='';
				    	$scope = 'key';
				    	$key   = '';
				    	break;
				    default:
				    	$value .= $s;
				}
			}
			if($this->strlen($remain)===0 && $key!=='') $reslut[$key]=$value;
		}
		
		if(count($reslut) < 1) $reslut = false;
		else
		{
			foreach($reslut as $k=>$v)
			{
				$safecount=20;
				while($safecount!==0)
				{
					$safecount--;
					$bt = md5($v);
    				if(strpos($v,'[*')!==false) $v = $modx->mergeDocumentContent($v);
    				if(strpos($v,'[(')!==false) $v = $modx->mergeSettingsContent($v);
    				if(strpos($v,'{{')!==false) $v = $modx->mergeChunkContent($v);
    				if(strpos($v,'[[')!==false) $v = $modx->evalSnippets($v);
    				if(md5($v)===$bt)
					{
						$reslut[$k] = $v;
    					break;
    				}
				}
			}
		}
		return $reslut;
	}
	
	function getDocumentObject($target='',$field='pagetitle',$mode='prepareResponse')
	{
		global $modx;
		
		$target = trim($target);
		if(empty($target)) $target = $modx->documentIdentifier;
		if(preg_match('@^[1-9][0-9]*$@',$target)) $method='id';
		else $method = 'alias';

		if(!isset($this->documentObject[$target])) 
		{
			$this->documentObject[$target] = $modx->getDocumentObject($method,$target,$mode);
		}
		
		if(isset($this->documentObject[$target][$field])&&is_array($this->documentObject[$target][$field]))
		{
			$a = $modx->getTemplateVarOutput($field,$target);
			$this->documentObject[$target][$field] = $a[$field];
		}
		
		return $this->documentObject[$target][$field];
	}
	
	//mbstring
	function substr($str, $s, $l = null) {
		if (function_exists('mb_substr')) return mb_substr($str, $s, $l);
		return substr($str, $s, $l);
	}
	function strlen($str) {
		if (function_exists('mb_strlen')) return mb_strlen($str);
		return strlen($str);
	}
	function strtolower($str) {
		if (function_exists('mb_strtolower')) return mb_strtolower($str);
		return strtolower($str);
	}
	function strtoupper($str) {
		if (function_exists('mb_strtoupper')) return mb_strtoupper($str);
		return strtoupper($str);
	}
	function ucfirst($str) {
		if (function_exists('mb_strtoupper') && function_exists('mb_substr') && function_exists('mb_strlen')) 
			return mb_strtoupper(mb_substr($str, 0, 1)).mb_substr($str, 1, mb_strlen($str));
		return ucfirst($str);
	}
	function lcfirst($str) {
		if (function_exists('mb_strtolower') && function_exists('mb_substr') && function_exists('mb_strlen')) 
			return mb_strtolower(mb_substr($str, 0, 1)).mb_substr($str, 1, mb_strlen($str));
		return lcfirst($str);
	}
	function ucwords($str) {
		if (function_exists('mb_convert_case'))
			return mb_convert_case($str, MB_CASE_TITLE);
		return ucwords($str);
	}
	function strrev($str) {
		preg_match_all('/./us', $str, $ar);
		return implode(array_reverse($ar[0]));
	}
	function str_shuffle($str) {
		preg_match_all('/./us', $str, $ar);
		shuffle($ar[0]);
		return implode($ar[0]);
	}
	function str_word_count($str) {
		return count(preg_split('~[^\p{L}\p{N}\']+~u',$str));
	}
}
