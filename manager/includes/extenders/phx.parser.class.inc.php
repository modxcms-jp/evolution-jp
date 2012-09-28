<?php
/*####
#
#	Name: PHx class(Placeholders Xtended)
#	Version: 2.2
#	Author: Armand "bS" Pondman (apondman@zerobarrier.nl)
#	Modified by Nick to include external files
#	Modified by yama yamamoto@kyms.ne.jp
#	Date: 2012/07/28
#
####*/

class PHx {
	
	function PHx()
	{
	}
	
	// Parser: modifier detection and eXtended processing if needed
	function Filter($key, $value, $cmd, $opt='')
	{
		global $modx;
		$cmd=strtolower($cmd);
		
		switch ($cmd)
		{
			#####  String Modifiers
			case 'lcase':
			case 'strtolower':
				$value = strtolower($value); break;
			case 'ucase':
			case 'strtoupper':
				$value = strtoupper($value); break;
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
				$value = strlen($value); break;
			case 'reverse':
			case 'strrev':
				$value = strrev($value); break;
			case 'wordwrap':
				// default: 70
			  	$wrapat = intval($opt) ? intval($opt) : 70;
				$value = preg_replace("~(\b\w+\b)~e","wordwrap('\\1',\$wrapat,' ',1)",$value);
				break;
			case 'limit':
				// default: 100
			  	$limit = intval($opt) ? intval($opt) : 100;
				$value = mb_substr($value,0,$limit,$modx->config['modx_charset']);
				break;
			case 'str_shuffle':
			case 'shuffle':
				$value = str_shuffle($value); break;
			case 'str_word_count':
			case 'word_count':
			case 'wordcount':
				$value = str_word_count($value); break;
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
				$value = $cmd($value);
				break;
			
			#####  Resource fields
			case 'id':
				if($opt) $value = $this->getDocumentObject($opt,$key);
				else     $value = $this->getDocumentObject($value,$cmd);
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
			
			#####  Special functions 
			case 'math':
				$filter = preg_replace("~([a-zA-Z\n\r\t\s])~",'',$opt);
				$filter = str_replace('?',$value,$filter);
				$value = eval('return '.$filter.';');
				break;
			case 'ifempty':
				if (empty($value)) $value = $opt; break;
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
				$grps = (strlen($opt) > 0 ) ? explode(',', $opt) :array();
				$value = intval($this->isMemberOfWebGroupByUserId($value,$grps));
				break;
				
			// If we haven't yet found the modifier, let's look elsewhere	
			default:
				$tbl_site_snippets = $modx->getFullTableName('site_snippets');
				$result = $modx->db->select('snippet',$tbl_site_snippets,"name='phx:{$cmd}'");
				if($modx->db->getRecordCount($result) == 1)
				{
					$php = $modx->db->getValue($result);
				}
				elseif($modx->db->getRecordCount($result) == 0)
				{
					$filename = "{$modx->config['base_dir']}assets/plugins/phx/modifiers/{$cmd}.phx.php";
					if(file_exists($filename))
					{
						$php = @file_get_contents($filename);
						$php = trim($php);
						$php = preg_replace('@^<\?php@', '', $php);
						$php = preg_replace('@?>$@', '', $php);
						$php = preg_replace('@^<\?@', '', $php);
					}
					else
					{
						$php = false;
					}
				}
				if($php==='') $php=false;
				
				if($php===false) $html = $modx->getChunk('phx:' . $cmd);
				else             $html = false;
				
				if($php !== false)
				{
					ob_start();
					$options = $opt;
					$output = $value;
					$custom = eval($php);
					$msg = ob_get_contents();
					$value = $msg . $custom;
					ob_end_clean();
				}
				elseif($html!==false)
				{
					$html = str_replace(array('[+output+]','[+value+]'), $value, $html);
					$value = str_replace(array('[+options+]','[+param+]'), $opt, $html);
				}
				if($php===false && $html===false && $value!==''
				   && (strpos($cmd,'[+value+]')!==false || strpos($cmd,'[+output+]')!==false))
				{
					$value = str_replace(array('[+value+]','[+output+]'),$value,$cmd);
				}
				break;
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
		//if(isset($phx) && is_object($phx))
		
		foreach($modifiers as $cmd=>$opt)
		{
			$input = $this->Filter($key,$value, $cmd, $opt);
		}
		
		return $input;
	}
	
	function splitModifiers($modifiers)
	{
		$reslut = array();
		$in_opt = false;
		$cmd = '';
		$opt = '';
		$delim = '';
		$r = $modifiers;
		$c=0;
		while(!empty($r) && $c < 3000)
		{
			$v = substr($r,0,1);
			$r = substr($r,1);
			switch($v)
			{
				case ':':
					if($in_opt===false && !empty($cmd))
					{
						$reslut[$cmd] = '';
						$opt = '';
					}
					elseif($in_opt===true && !empty($cmd))
					{
						$in_opt = false;
					$reslut[$cmd] = $opt;
						$delim = '';
					}
					$cmd = '';
					$opt = '';
					break;
				case '=':
					if($in_opt===true) $opt .= '=';
					elseif($in_opt===false && $cmd!=='')
					{
						if($r['0']==='"'||$r['0']==="'"||$r['0']==='`')
						{
							$delim = $r['0'];
							$r = substr($r,1);
							list($opt, $r) = explode($delim, $r, 2);
							$reslut[$cmd] = $opt;
							$cmd = '';
						}
					else                $in_opt = true;
					}
					break;
				default:
					if(strpos($r,':')===false && strpos($r,'=')===false)
					{
						$reslut[$v.$r] = '';
						$r = '';
			}
					elseif(strpos($r,':')===false && strpos($r,'=')!==false)
					{
						list($cmd,$opt) = explode('=',$v.$r);
						if($opt['0']==='"'||$opt['0']==="'"||$opt['0']==='`')
						{
							$delim = $opt['0'];
							$opt = trim($opt,$delim);
						}
						$reslut[$cmd] = $opt;
						$r = '';
		}
					else
					{
						if($in_opt===true) $opt .= $v;
						else               $cmd .= $v;
					}
			}
			$c++;
		}
		
		if(count($reslut) < 1) $reslut[$cmd] = '';
		return $reslut;
	}
	
	function getDocumentObject($target,$field='pagetitle')
	{
		global $modx;
		
		$target = trim($target);
		if(preg_match('@^[0-9]+$@',$target)) $mode='id';
		else $mode = 'alias';
		
		if(!isset($this->documentObject[$target])) 
		{
			$this->documentObject[$target] = $modx->getDocumentObject($mode,$target);
		}
		if(is_array($this->documentObject[$target][$field]))
		{
			$a = $modx->getTemplateVarOutput($field,$target);
			$this->documentObject[$target][$field] = $a[$field];
		}
		
		return $this->documentObject[$target][$field];
	}
}
