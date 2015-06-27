<?php
/*####
#
#	Name: PHx class(Placeholders Xtended)
#	Author: Armand "bS" Pondman (apondman@zerobarrier.nl)
#	Modified by Nick to include external files
#	Modified by yama yamamoto@kyms.jp
#	Date: 2015/03/24
#
####*/

class PHx {
	
	var $placeholders = array();
	var $vars = array();
	var $cache = array();
	var $bt;
	
	function PHx()
	{
		global $modx;
		
		if (function_exists('mb_internal_encoding')) mb_internal_encoding($modx->config['modx_charset']);
		$this->placeholders['phx'] = '';
	}
	
	function phxFilter($key,$value,$modifiers)
	{
		global $modx;
		$modifiers = str_replace(array("\r\n","\r"), "\n", $modifiers);
		$modifiers = $this->splitModifiers($modifiers);
		$this->vars = array();
		$this->vars['name']    = & $key;
		$value = $this->parsePhx($key,$value,$modifiers);
		$this->vars = array();
		return $value;
	}
	
	function splitModifiers($modifiers)
	{
		global $modx;
		
		if(strpos($modifiers,':')===false && strpos($modifiers,'=')===false && strpos($modifiers,'(')===false)
			return array(array('cmd'=>$modifiers,'opt'=>''));
		
		$result = array();
		$key   = '';
		$value = null;
		while($modifiers!=='')
		{
			$bt = $modifiers;
			$char = $this->substr($modifiers,0,1);
			$modifiers = $this->substr($modifiers,1);
			
			if($key===''&&$char==='=') exit('PHx parse error');
			
			if    ($char==='=')
			{
		    	$nextchar = $this->substr($modifiers,0,1);
				if(in_array($nextchar, array('"', "'", '`'))) list($value,$modifiers) = $this->_delimRoop($modifiers,$nextchar);
		    	elseif(strpos($modifiers,':')!==false)        list($value,$modifiers) = explode(':', $modifiers, 2);
		    	else                                          list($value,$modifiers) = array($modifiers,'');
			}
			elseif($char==='(' && strpos($modifiers,')')!==false)
			{
				$delim = $this->substr($modifiers,0,1);
				switch($delim)
				{
					case '"':
					case "'":
					case '`':
						if(strpos($modifiers,"{$delim})")!==false)
					    {
							list($value,$modifiers) = explode("{$delim})", $modifiers, 2);
							$value = substr($value,1);
						}
						break;
					default:
						list($value,$modifiers) = explode(')', $modifiers, 2);
				}
			}
			elseif($char===':') $value = '';
			else                $key .= $char;
			
			if(!is_null($value))
			{
    	    	$key=trim($key);
    	    	if($key!=='') $result[]=array('cmd'=>$key,'opt'=>$value);
    	    	
    	    	$key   = '';
    	    	$value = null;
			}
			elseif($key!==''&&$modifiers==='')
				$result[]=array('cmd'=>$key,'opt'=>'');
			
			if($modifiers===$bt)
			{
				$key = trim($key);
				if($key!=='') $result[] = array('cmd'=>$key,'opt'=>'');
				break;
			}
		}
		
		if(empty($result)) return array();
		
		foreach($result as $i=>$a)
		{
			$result[$i]['opt'] = $this->parseDocumentSource($a['opt']);
			$result[$i]['opt'] = $modx->parseText($a['opt'],$this->placeholders);
		}
		
		return $result;
	}
	
	function parsePhx($key,$value,$modifiers)
	{
		global $modx,$condition;
		if(empty($modifiers)) return;
		
		$condition = array();

		foreach($modifiers as $m)
		{
			$lastKey = $m['cmd'];
		}
		$_ = explode(',','equals,is,eq,notequals,isnot,isnt,ne,isgreaterthan,isgt,eg,islowerthan,islt,el,greaterthan,gt,lowerthan,lt,find,preg');
		if(in_array($lastKey,$_))
		{
			$modifiers[] = array('cmd'=>'then','opt'=>'1');
			$modifiers[] = array('cmd'=>'else','opt'=>'0');
		}
		
		foreach($modifiers as $i=>$a)
		{
			if ($modx->debug) $fstart = $modx->getMicroTime();
			$value = $this->Filter($key,$value, $a['cmd'], $a['opt']);
			if ($modx->debug) $modx->addLogEntry('$modx->filter->'.__FUNCTION__."(:{$a['cmd']})",$fstart);
		}
		return $value;
	}
	
	// Parser: modifier detection and eXtended processing if needed
	function Filter($phxkey, $value, $cmd, $opt='')
	{
		global $modx;
		
		if($phxkey==='documentObject') $value = $modx->documentIdentifier;
		$cmd = $this->parseDocumentSource($cmd);
		if(preg_match('@^[1-9][/0-9]*$@',$cmd))
		{
			if(strpos($cmd,'/')!==false)
				$cmd = $this->substr($cmd,strrpos($cmd,'/')+1);
			$opt = $cmd;
			$cmd = 'id';
		}
		
		$this->elmName = '';
		if(!$modx->snippetCache) $modx->setSnippetCache();
		if(isset($modx->snippetCache["phx:{$cmd}"])) {
			$this->elmName = "phx:{$cmd}";
		}
		elseif(isset($modx->snippetCache[$cmd])) {
			$this->elmName = $cmd;
		}
		elseif(isset($modx->chunkCache["phx:{$cmd}"])) {
			$this->elmName = "phx:{$cmd}";
		}
		elseif(isset($modx->chunkCache[$cmd])) {
			$this->elmName = $cmd;
		}
		$cmd = strtolower($cmd);
		if($this->elmName!=='')
			$value = $this->getValueFromElement($phxkey, $value, $cmd, $opt);
		else
			$value = $this->getValueFromPreset($phxkey, $value, $cmd, $opt);
		
		if($modx->config['output_filter']==='1') $value = str_replace('[+key+]', $phxkey, $value);
		else                                     $value = str_replace('[+name+]', $phxkey, $value);
		return $value;
	}
	
	function isEmpty($cmd,$value)
	{
		if($value!=='') return false;
		
		$_ = explode(',', 'id,ifempty,input,if,equals,is,eq,notequals,isnot,isnt,ne,find,preg,or,and,show,this,then,else,select,switch,summary,smart_description,smart_desc,isinrole,ir,memberof,mo');
		if(in_array($cmd,$_)) return false;
		else                  return true;
	}
	
	function getValueFromPreset($phxkey, $value, $cmd, $opt)
	{
		global $modx, $condition;
		
		if($this->isEmpty($cmd,$value)) return;
		
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
			case 'preg_match':
				$condition[] = intval(preg_match($opt,$value));break;
			case 'isinrole':
			case 'ir':
			case 'memberof':
			case 'mo':
				// Is Member Of  (same as inrole but this one can be stringed as a conditional)
        		$userID = $modx->getLoginUserID();
				$grps = ($this->strlen($opt) > 0 ) ? explode(',',$opt) :array();
				$condition[] = intval($this->isMemberOfWebGroupByUserId($userID,$grps));
				break;
			case 'or':
				$condition[] = '||';break;
			case 'and':
				$condition[] = '&&';break;
			case 'show':
			case 'this':
				$conditional = implode(' ',$condition);
				$isvalid = intval(eval("return ({$conditional});"));
				if (!$isvalid) { $value = NULL;}
				break;
			case 'then':
				$conditional = implode(' ',$condition);
				$isvalid = intval(eval("return ({$conditional});"));
				if ($isvalid) { $value = $opt; }
				else { $value = NULL; }
				break;
			case 'else':
				$conditional = implode(' ',$condition);
				$isvalid = intval(eval("return ({$conditional});"));
				if (!$isvalid) { $value = $opt; }
				break;
			case 'select':
			case 'switch':
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
			case 'lower_case':
				$value = $this->strtolower($value); break;
			case 'ucase':
			case 'strtoupper':
			case 'upper_case':
				$value = $this->strtoupper($value); break;
			case 'htmlent':
			case 'htmlentities':
				$value = htmlentities($value,ENT_QUOTES,$modx->config['modx_charset']); break;
			case 'html_entity_decode':
			case 'decode_html':
				$value = html_entity_decode($value,ENT_QUOTES,$modx->config['modx_charset']);
				break;
			case 'esc':
			case 'escape':
				$value = preg_replace('/&amp;(#[0-9]+|[a-z]+);/i', '&$1;', htmlspecialchars($value, ENT_QUOTES, $modx->config['modx_charset']));
		    	$value = str_replace(array('[', ']', '`'),array('&#91;', '&#93;', '&#96;'),$value);
				break;
			case 'sql_escape':
			case 'encode_js':
				$value = $modx->db->escape($value);
				break;
			case 'htmlspecialchars':
			case 'hsc':
			case 'encode_html':
				$value = preg_replace('/&amp;(#[0-9]+|[a-z]+);/i', '&$1;', htmlspecialchars($value, ENT_QUOTES, $modx->config['modx_charset']));
				break;
			case 'spam_protect':
				$value = str_replace(array('@','.'),array('&#64;','&#46;'),$value);
				$value = sprintf("<script>document.write('%s');</script>",$value);
				break;
			case 'strip':
				if($opt==='') $opt = ' ';
				$value = preg_replace('/[\n\r\t\s]+/', $opt, $value); break;
			case 'strip_linefeeds':
				$value = str_replace(array("\n","\r"), '', $value); break;
			case 'notags':
			case 'strip_tags':
			case 'remove_html':
				if($opt!=='')
				{
					foreach(explode(',',$opt) as $v)
					{
						$v = trim($v,'</> ');
						$param[] = "<{$v}>";
					}
					$params = join(',',$param);
				}
				else $params = '';
				if(!strpos($params,'<br>')===false) {
					$value = preg_replace('@(<br[ /]*>)\n@','$1',$value);
					$value = preg_replace('@<br[ /]*>@',"\n",$value);
				}
				$value = strip_tags($value,$params);
				break;
			case 'length':
			case 'len':
			case 'strlen':
			case 'count_characters':
				$value = $this->strlen($value); break;
			case 'reverse':
			case 'strrev':
				$value = $this->strrev($value); break;
			case 'wordwrap':
				// default: 70
			  	$wrapat = intval($opt) ? intval($opt) : 70;
				$value = preg_replace("~(\b\w+\b)~e","wordwrap('\\1',\$wrapat,' ',1)",$value);
				break;
			case 'wrap_text':
			  	$width = preg_match('/^[1-9][0-9]*$/',$opt) ? $opt : 70;
			  	if($modx->config['manager_language']==='japanese-utf8')
				{
					$chunk = array();
					$c=0;
					while($c<10000)
			  		{
			  			$c++;
			  			if($this->strlen($value)<$width)
						{
							$chunk[] = $value;
							break;
			  			}
			  			$chunk[] = $this->substr($value,0,$width);
			  			$value = $this->substr($value,$width);
					}
					$value = join("\n",$chunk);
			  	}
			  	else
			  		$value = wordwrap($value,$width,"\n",true);
				break;
			case 'limit':
				// default: 100
			  	$limit = intval($opt) ? intval($opt) : 100;
				$value = $this->substr($value,0,$limit);
				break;
			case 'summary':
			case 'smart_description':
			case 'smart_desc':
			  	if(strpos($opt,',')) list($limit,$delim) = explode(',', $opt);
				elseif(preg_match('/^[1-9][0-9]*$/',$opt)) {$limit=$opt;$delim='';}
				else {$limit=100;$delim='';}
				$value = $this->getSummary($value, $limit, $delim);
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
			case 'replace':
			case 'str_replace':
				if(empty($opt) || strpos($opt,',')===false) break;
				if    (substr_count($opt, ',') ==1) $delim = ',';
				elseif(substr_count($opt, '|') ==1) $delim = '|';
				elseif(substr_count($opt, '=>')==1) $delim = '=>';
				elseif(substr_count($opt, '/') ==1) $delim = '/';
				else break;
				list($s,$r) = explode($delim,$opt);
				if($value!=='') $value = str_replace($s,$r,$value);
				break;
			case 'replace_to':
				if($value!=='') $value = str_replace(array('[+value+]','[+output+]','{value}'),$value,$opt);
				break;
			case 'preg_replace':
			case 'regex_replace':
				if(empty($opt) || strpos($opt,',')===false) break;
				list($s,$r) = explode(',',$opt,2);
				if($value!=='') $value = preg_replace($s,$r,$value);
				break;
			case 'sprintf':
			case 'string_format':
				if($value!=='') $value = sprintf($opt,$value);
				break;
                        case 'money_format':
                                setlocale(LC_MONETARY,setlocale(LC_TIME,0));
                                if($value!=='') $value = money_format($opt,$value);
                                break;
			case 'cat':
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
			case 'addbreak':
				$value = $this->addbreak($value);
				break;
			case 'capitalize':
				$_ = explode(' ',$value);
				foreach($_ as $i=>$v)
				{
					$_[$i] = ucfirst($v);
				}
				$value = join(' ',$_);
				break;
		    case 'count_paragraphs':
		    	$value = trim($value);
		    	$value = preg_replace('/\r/', '', $value);
		    	$value = count(preg_split('/\n+/',$value));
		    	break;
		    case 'count_words':
		    	$value = trim($value);
		    	$value = count(preg_split('/\s+/',$value));
		    	break;
			case 'urlencode':
			case 'encode_url':
				$value = urlencode($value);
				break;
			case 'sha1':
			case 'encode_sha1':
				$value = sha1($value);
				break;
			case 'trim_to':
				if(strpos($opt,'+'))
					list($len,$str) = explode('+',$opt,2);
				else {
					$len = $opt;
					$str = '';
				}
				if(preg_match('/^[1-9][0-9]*$/',$len)) {
					$value = $this->substr($value,0,$len) . $str;
				}
				elseif(preg_match('/^\-[1-9][0-9]*$/',$len)) {
					$value = $this->substr($value,$len) . $str;
				}
				break;
			case 'br2nl':
				$value = preg_replace('@<br[\s/]*>@i', "\n", $value);
				break;
			case 'ltrim':
			case 'rtrim':
			case 'trim': // ref http://mblo.info/modifiers/custom-modifiers/rtrim_opt.html
				if($opt==='')
					$value = $cmd($value);
				else $value = $cmd($value,$opt);
				break;
        	case 'nl2br':
				if($opt!=='')
				{
					$opt = trim($opt);
					$opt = strtolower($opt);
					if($opt==='false') $opt = false;
					elseif($opt==='0') $opt = false;
					else               $opt = true;
				}
            	elseif(isset($modx->config['mce_element_format'])&&$modx->config['mce_element_format']==='html')
            	                       $opt = false;
				else                   $opt = true;
            	$value = nl2br($value,$opt);
            	break;
			case 'base64_decode':
				if($opt!=='false') $opt = true;
				else               $opt = false;
				$value = base64_decode($value,$opt);
				break;
			// These are all straight wrappers for PHP functions
			case 'ucfirst':
			case 'lcfirst':
			case 'ucwords':
			case 'addslashes':
			case 'md5':
			case 'urldecode':
			case 'rawurlencode':
			case 'rawurldecode':
			case 'base64_encode':
				$value = $cmd($value);
				break;
			
			#####  Resource fields
			case 'id':
				if($opt) $value = $this->getDocumentObject($opt,$phxkey);
				break;
			case 'type':
			case 'contenttype':
			case 'pagetitle':
			case 'longtitle':
			case 'description':
			case 'alias':
			case 'introtext':
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
			case 'privateweb':
			case 'privatemgr':
			case 'content_dispo':
			case 'hidemenu':
				if($cmd==='contenttype') $cmd = 'contentType';
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
			case 'templatename':
				$template = $this->getDocumentObject($value,'template');
				$templateObject = $modx->db->getObject('site_templates',"id='{$template}'");
				$value = $templateObject !== false ? $templateObject->templatename : '(blank)';
				break;
				
			#####  User info
			case 'username':
			case 'fullname':
			case 'role':
			case 'email':
			case 'phone': 
			case 'mobilephone': 
			case 'blocked':
			case 'blockeduntil':
			case 'blockedafter':
			case 'logincount':
			case 'lastlogin':
			case 'thislogin':
			case 'failedlogincount':
			case 'dob':
			case 'gender':
			case 'country':
			case 'street':
			case 'city':
			case 'state':
			case 'zip':
			case 'fax':
			case 'photo':
			case 'comment':
				$value = $this->ModUser($value,$cmd);
				break;
			#####  Special functions 
			case 'math':
				$filter = preg_replace('@([a-rt-zA-Z\n\r\t\s])@','',$opt);
				$filter = str_replace(array('?','%s'),$value,$filter);
				$value = eval("return {$filter};");
				break;
			case 'ifempty':
			case '_default':
				if (empty($value)) $value = $opt; break;
			case 'ifnotempty':
				if (!empty($value)) $value = $opt; break;
			case 'strftime':
			case 'date':
			case 'dateformat':
				if(empty($opt)) $opt = $modx->toDateFormat(null, 'formatOnly');
				if(!preg_match('@^[0-9]+$@',$value)) $value = strtotime($value);
				if(strpos($opt,'%')!==false)
					$value = $modx->mb_strftime($opt,0+$value);
				else
					$value = date($opt,0+$value);
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
			case 'youtube':
			case 'youtube16x9':
				if(empty($opt)) $opt = 560;
				$h = round($opt*0.5625);
				$tpl = '<iframe width="%s" height="%s" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>';
				$value = sprintf($tpl,$opt,$h,$value);
				break;
			//case 'youtube4x3':%s*0.75＋25
			case 'datagrid':
                include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
                $grd = new DataGrid();
                $grd->ds = trim($value);
                $grd->itemStyle = '';
                $grd->altItemStyle = '';
                $pos = strpos($value,"\n");
                if($pos) $_ = substr($value,0,$pos);
                else $_ = $pos;
                $grd->cdelim = strpos($_,"\t")!==false ? 'tab' : ',';
                $value = $grd->render();
                break;
            case 'rotate':
            case 'evenodd':
                if(strpos($opt,',')===false) $opt = 'odd,even';
                $_ = explode(',', $opt);
                $c = count($_);
                $i = $value + $c;
                $i = $i % $c;
                $value = $_[$i];
                break;
            case 'getimage':
                $pattern = '/<img[\s\n]+src=[\s\n]*"([^"]+\.(jpg|jpeg|png|gif))"[^>]+>/i';
                preg_match_all($pattern , $value , $images);
                if($opt==='')
                {
                    if($images[1][0]) $value = $images[1][0];
                    else               $value = '';
                }
                else
                {
                    $value = '';
                    foreach($images[0] as $i=>$image)
                    {
                        if(strpos($image,$opt)!==false)
                        {
                            $value = $images[1][$i];
                            break;
                        }
                    }
                }
                break;

            case 'filesize':
                if($value == '') return ;
                $filename = $value;
                
                $site_url = $modx->config['site_url'];
                if(strpos($filename,$site_url) === 0)
                    $filename = substr($filename,0,strlen($site_url));
                $filename = trim($filename,'/');
                
                $opt = trim($opt,'/');
                if($opt!=='') $opt .= '/';
                
                $filename = MODX_BASE_PATH.$opt.$filename;
                
                if(is_file($filename)){
                    $size = filesize($filename);
                    clearstatcache();
                    return $size;
                }
                else return;
                break;
            case 'nicesize':
                    return $modx->nicesize($value);
                break;
                
            case 'setvar':
            	$modx->placeholders[$opt] = $value;
            	return;
            	break;
            
            case 'children':
            case 'childids':
                if($value=='') $value = 0; // 値がない場合はルートと見なす
                $published = 1;
                $_ = explode(',',$opt);
                $where = array();
                foreach($_ as $opt) {
                    switch(trim($opt)) {
                        case 'page'; case '!folder'; case '!isfolder': $where[] = 'sc.isfolder=0'; break;
                        case 'folder'; case 'isfolder':                $where[] = 'sc.isfolder=1'; break;
                        case 'menu'; case 'show_menu':                 $where[] = 'sc.hidemenu=0'; break;
                        case '!menu'; case '!show_menu':               $where[] = 'sc.hidemenu=1'; break;
                        case 'published':                              $published = 1; break;
                        case '!published':                             $published = 0; break;
                    }
                }
                $where = join(' AND ', $where);
                
                $IDs = $modx->getDocumentChildren($value, $published, '0', 'id', $where);
                foreach((array)$IDs as $id){ // $IDs が null だった時にエラーになるため型キャスト
                    $result[] = $id[id];
                }
                return join(',', $result);
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
			else                                     $input  = $value;
			if($modx->config['output_filter']==='1') $name   = $phxkey;
			else                                     $key    = $phxkey;
			$this->bt = $value;
			$this->vars['value']   = & $value;
			$this->vars['input']   = & $value;
			$this->vars['option']  = & $opt;
			$this->vars['options'] = & $opt;
			$custom = eval($php);
			$msg = ob_get_contents();
			if($value===$this->bt) $value = $msg . $custom;
			ob_end_clean();
		}
		elseif($html!==false && isset($value) && $value!=='')
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
		if (isset($this->cache['mo'][$userid])) $grpNames = $this->cache['mo'][$userid];
		else
		{
			$from = sprintf("[+prefix+]webgroup_names wgn INNER JOIN [+prefix+]web_groups wg ON wg.webgroup=wgn.id AND wg.webuser='%s'",$userid);
			$rs = $modx->db->select('wgn.name',$from);
			$this->cache['mo'][$userid] = $grpNames = $modx->db->getColumn('name',$rs);
		}
		
		// Check if a supplied group matches a webgroup from the array we just created
		foreach($groupNames as $k=>$v)
		{
			if(in_array(trim($v),$grpNames)) return true;
		}
		
		// If we get here the above logic did not find a match, so return false
		return false;
	}
	
	function _delimRoop($_tmp,$delim)
	{
		$debugbt = $_tmp;
		$_tmp = $this->substr($_tmp,1);
		$value = '';
		$c = 0;
		while($c < 65000)
		{
			$bt = $_tmp;
			$char = $this->substr($_tmp,0,1);
			$_tmp = $this->substr($_tmp,1);
			$c++;
			if($c===65000)
			{
				global $modx;
				$modx->addLog('PHx _delimRoop debug',$debugbt);
				exit('phx parse over');
			}
			if($char===$delim && ($this->substr($_tmp,0,1)===':'))
				break;
			else
				$value .= $char;
			
			if($delim===$_tmp)    {$_tmp='';break;}
			elseif($bt === $_tmp) break;
			elseif($_tmp==='')    break;
		}
		if($value===$delim) $value = '';
		if(!empty($value))
			$value = $this->parseDocumentSource($value);
		
		return array($value,$_tmp);
	}
	
	function parseDocumentSource($content='')
	{
		global $modx;
		
		$c=0;
		while($c < 20)
		{
			$bt = $content;
			if(strpos($content,'[*')!==false && $modx->documentIdentifier)
				                              $content = $modx->mergeDocumentContent($content);
			if(strpos($content,'[(')!==false) $content = $modx->mergeSettingsContent($content);
			if(strpos($content,'{{')!==false) $content = $modx->mergeChunkContent($content);
			if(strpos($content,'[[')!==false) $content = $modx->evalSnippets($content);
			if($content===$bt) break;
			$c++;
			if($c===20) exit('Parse over');
		}
		return $content;
	}
	
	function getDocumentObject($target='',$field='pagetitle')
	{
		global $modx;
		
		$target = trim($target);
		if(empty($target)) $target = $modx->config['site_start'];
		if(preg_match('@^[1-9][0-9]*$@',$target)) $method='id';
		else $method = 'alias';

		if(!isset($this->documentObject[$target]))
		{
			$this->documentObject[$target] = $modx->getDocumentObject($method,$target,'direct');
		}
		
		if($this->documentObject[$target]['publishedon']==='0')
			return '';
		elseif(isset($this->documentObject[$target][$field]))
		{
			if(is_array($this->documentObject[$target][$field]))
			{
				$a = $modx->getTemplateVarOutput($field,$target);
				$this->documentObject[$target][$field] = $a[$field];
			}
		}
		else $this->documentObject[$target][$field] = false;
		
		return $this->documentObject[$target][$field];
	}
	
	function setPlaceholders($value = '', $key = '', $path = '') {
		$keypath = !empty($path) ? $path . "." . $key : $key;
	    if (is_array($value)) {
			foreach ($value as $subkey => $subval) {
				$this->setPlaceholders($subval, $subkey, $keypath);
			}
		}
		else $this->setPHxVariable($keypath, $value);
	}
	
	// Sets a placeholder variable which can only be access by PHx
	function setPHxVariable($name, $value) {
		if ($name != 'phx') $this->placeholders[$name] = $value;
	}
	
	//mbstring
	function substr($str, $s, $l = null) {
		global $modx;
		if(is_null($l)) $l = $this->strlen($str);
		if (function_exists('mb_substr'))
		{
			if(strpos($str,"\r")!==false)
				$str = str_replace(array("\r\n","\r"), "\n", $str);
			return mb_substr($str, $s, $l, $modx->config['modx_charset']);
		}
		return substr($str, $s, $l);
	}
	function strpos($haystack,$needle,$offset=0) {
		global $modx;
		if (function_exists('mb_strpos')) return mb_strpos($haystack,$needle,$offset,$modx->config['modx_charset']);
		return $this->strlen($haystack,$needle,$offset);
	}
	function strlen($str) {
		global $modx;
		if (function_exists('mb_strlen')) return mb_strlen(str_replace("\r\n", "\n", $str),$modx->config['modx_charset']);
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
		if (function_exists('mb_strtoupper')) 
			return mb_strtoupper($this->substr($str, 0, 1)).$this->substr($str, 1, $this->strlen($str));
		return ucfirst($str);
	}
	function lcfirst($str) {
		if (function_exists('mb_strtolower')) 
			return mb_strtolower($this->substr($str, 0, 1)).$this->substr($str, 1, $this->strlen($str));
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
	
    function addbreak($text)
    {
        global $modx;
        
        $text = $this->parseDocumentSource($text);
        $text = str_replace(array("\r\n","\r"),"\n",$text);
        
        $blockElms  = 'br,table,tbody,tr,td,th,thead,tfoot,caption,colgroup,div';
        $blockElms .= ',dl,dd,dt,ul,ol,li,pre,select,option,form,map,area,blockquote';
        $blockElms .= ',address,math,style,input,p,h1,h2,h3,h4,h5,h6,hr,object,param,embed';
        $blockElms .= ',noframes,noscript,section,article,aside,hgroup,footer,address,code';
        $blockElms = explode(',', $blockElms);
        $lines = explode("\n",$text);
        $c = count($lines);
        foreach($lines as $i=>$line)
        {
            $line = rtrim($line);
            if($i===$c-1) break;
            foreach($blockElms as $block)
            {
                if(preg_match("@</?{$block}" . '[^>]*>$@',$line))
                    continue 2;
            }
            $lines[$i] = "{$line}<br />";
        }
        return join("\n", $lines);
    }
    
    function getSummary($content='', $limit=100, $delim='')
    {
    	global $modx;
    	if($delim==='') $delim = $modx->config['manager_language']==='japanese-utf8' ? '。' : '.';
    	$limit = intval($limit);
    	
    	if($content==='' && isset($modx->documentObject['content']))
    		$content = $modx->documentObject['content'];
    	
    	$content = $this->parseDocumentSource($content);
    	$content = strip_tags($content);
    	$content = str_replace(array("\r\n","\r","\n","\t",'&nbsp;'),' ',$content);
    	if(preg_match('/\s+/',$content))
    		$content = preg_replace('/\s+/',' ',$content);
    	$content = trim($content);
    	
    	$pos = $this->strpos($content, $delim);
    	
    	if($pos!==false && $pos<$limit)
    	{
    		$_ = explode($delim, $content);
    		$text = '';
    		foreach($_ as $value)
    		{
    			if($limit <= $this->strlen($text.$value.$delim)) break;
    			$text .= $value.$delim;
    		}
    	}
    	else $text = $content;
    	
    	if($limit<$this->strlen($text) && strpos($text,' ')!==false)
    	{
    		$_ = explode(' ', $text);
    		$text = '';
    		foreach($_ as $value)
    		{
    			if($limit <= $this->strlen($text.$value.' ')) break;
    			$text .= $value . ' ';
    		}
    		if($text==='') $text = $content;
    	}
    	
    	if($limit < $this->strlen($text)) $text = $this->substr($text, 0, $limit);
    	
    	return $text;
    }
}
