<?php
/*####
#
#	Name: PHx (Placeholders Xtended)
#	Version: 2.1.5
#	Modified by Nick to include external files
#	Author: Armand "bS" Pondman (apondman@zerobarrier.nl)
#	Date: July 13, 2007
#
####*/

class PHx {
	
	function PHx()
	{
	}
	
	// Parser: modifier detection and eXtended processing if needed
	function Filter($value, $cmd, $opt='')
	{
		global $modx;
		switch ($cmd)
		{
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
				$value = preg_replace("~([\n\r\t\s]+)~",' ',$value); break;
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
			
			
			#####  Special functions 
			case 'math':
				$filter = preg_replace("~([a-zA-Z\n\r\t\s])~",'',$opt);
				$filter = str_replace('?',$value,$filter);
				$value = eval('return '.$filter.';');
				break;
			case 'ifempty':
				if (empty($value)) $value = $opt; break;
			case 'date':
				if(empty($opt)) $opt = $modx->toDateFormat(null, 'formatOnly') . ' %H:%M';
				$value = $modx->mb_strftime($opt,0+$value);
				break;
			case 'userinfo':
				if ($value == '&_PHX_INTERNAL_&') $value = $this->user['id'];
				$value = $this->ModUser($value,$opt);
				break;
			case 'inrole':
				// deprecated
				if ($value == '&_PHX_INTERNAL_&') $value = $this->user['id'];
				$grps = (strlen($opt) > 0 ) ? explode(',', $opt) :array();
				$value = intval($this->isMemberOfWebGroupByUserId($value,$grps));
				break;
				
			// If we haven't yet found the modifier, let's look elsewhere	
			default:
				$html = $modx->getChunk('phx:' . $cmd);
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
						$php = '';
					}
				}
				if($php !== '')
				{
					ob_start();
					$options = $opt;
					$output = $value;
					$custom = eval($php);
					$msg = ob_get_contents();
					$value = $msg . $custom;
					ob_end_clean();
				}
				elseif($html !== '')
				{
					$html = str_replace(array('[+output+]','[+value+]'), $value, $html);
					$value = str_replace(array('[+options+]','[+param+]'), $opt, $html);
				}
				break;
		}
		return $value;
	}
	
	// Returns the specified field from the user record
	// positive userid = manager, negative integer = webuser
	function ModUser($userid,$field) {
		global $modx;
		if (!array_key_exists($userid, $this->cache['ui'])) {
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
	function phxFilter($value,$modifiers)
	{
		$modifiers = $this->splitModifiers($modifiers);
		$value = $this->parsePhx($value,$modifiers);
		return $value;
	}
	
	function parsePhx($input,$modifiers)
	{
		//if(isset($phx) && is_object($phx))
		
		foreach($modifiers as $k=>$v)
		{
			$input = $this->Filter($input, $k, $v);
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
						if($r[0]==='"'||$r[0]==="'"||$r[0]==='`')
						{
							$delim = $r[0];
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
}
