<?php
class TopicPath
{
	function TopicPath()
	{
		global $modx;
		if($modx->event->params) extract($modx->event->params);
		$this->menuitemOnly = 1;
		$this->ignoreIDs = array();
		$this->showTopicsAtHome    = (!isset($showTopicsAtHome))    ? '0' : $showTopicsAtHome;
		$this->showAtLeastOneTopic = (!isset($showAtLeastOneTopic)) ? '0' : $showAtLeastOneTopic;
		$this->ignoreIDs           = (!isset($ignoreIDs))           ? array() :explode(',',$ignoreIDs);
		$this->disabledOn          = (!isset($disabledOn))          ? array() :explode(',',$disabledOn);
		$this->disabledUnder       = (!isset($disabledUnder))       ? '' :$disabledUnder;
		$this->menuItemOnly        = (!isset($menuItemOnly))        ? '1' : $menuItemOnly;
		$this->limit               = (!isset($limit))               ? 100 :$limit;
		$this->topicGap            = (!isset($topicGap))            ? '...' :$topicGap;
		$this->titleField          = (!isset($titleField))          ? array('menutitle','pagetitle')              :explode(',',$titleField);
		$this->descField           = (!isset($descField))           ? array('description','longtitle','pagetitle'):explode(',',$descField);
		$this->homeId              = (!isset($homeId))              ? $modx->config['site_start'] :$homeId;
		$this->stopIDs             = (!isset($stopIDs))             ? array() :explode(',', $stopIDs);
		$this->order               = (!isset($order))               ? '' : $order;
		
		if(isset($homeTopicTitle)) $this->homeTopicTitle = $homeTopicTitle;
		if(isset($homeTopicDesc))  $this->homeTopicDesc  = $homeTopicDesc;
		
		$this->theme            = $theme;
		$this->tpl = array();
		if(isset($tplOuter))        $this->tpl['outer']         = $tplOuter;
		if(isset($tplHomeTopic))    $this->tpl['home_topic']    = $tplHomeTopic;
		if(isset($tplCurrentTopic)) $this->tpl['current_topic'] = $tplCurrentTopic;
		if(isset($tplOtherTopic))   $this->tpl['other_topic']   = $tplOtherTopic;
		if(isset($tplReferenceTopic)) $this->tpl['reference_topic'] = $tplReferenceTopic;
		if(isset($tplSeparator))    $this->tpl['separator']     = $tplSeparator;
	}
	
	function getTopicPath()
	{
		global $modx;
		$id = $modx->documentIdentifier;
		
		$this->disabledOn = $this->getDisableDocs();
		
		if(!$this->showTopicsAtHome && $id === $modx->config['site_start']) return;
		elseif(in_array($id,$this->disabledOn))                             return;
		
		switch(strtolower($this->theme))
		{
			case 'list':
			case 'li':
				$tpl['outer']            = '<ul class="topicpath">[+topics+]</ul>';
				$tpl['home_topic']       = '<li class="home"><a href="[+href+]" title="[+title+]">[+title+]</a></li>';
				$tpl['current_topic']    = '<li class="current">[+title+]</li>';
				$tpl['reference_topic']  = '<li>[+title+]</li>';
				$tpl['other_topic']      = '<li><a href="[+href+]" title="[+title+]">[+title+]</a></li>';
				$tpl['separator']        = "\n";
				break;
			default:
				$tpl['outer']             = '[+topics+]';
				$tpl['home_topic']        = '<a href="[+href+]" class="home" title="[+title+]">[+title+]</a>';
				$tpl['current_topic']     = '[+title+]';
				$tpl['reference_topic']   = '[+title+]';
				$tpl['other_topic']       = '<a href="[+href+]" title="[+title+]">[+title+]</a>';
				$tpl['separator']         = ' &raquo; ';
		}
		$tpl = array_merge($tpl, $this->tpl);
		
		$docs   = $this->getDocs($modx->documentIdentifier);
		$topics = $this->setTopics($docs,$tpl);
		
		if($this->limit < count($topics)) $topics = $this->trimTopics($topics);
		
		if(count($topics) > 1 || count($topics) == 1 && $this->showAtLeastOneTopic)
		{
			if(substr($this->order,0,1)==='r') $topics = array_reverse($topics);
			$rs = join($tpl['separator'],$topics);
			$rs = $this->parseText($tpl['outer'],array('topics'=>$rs));
		}
		else $rs = '';
		
		return $rs;
	}
	
	function trimTopics($topics)
	{
		$last_topic = array_pop($topics);
		array_splice($topics,$this->limit-1);
		$topics[] = $this->topicGap;
		$topics[] = $last_topic;
		
		return $topics;
	}
	
	function isEnable($doc)
	{
		if(in_array($doc['id'],$this->ignoreIDs))       $rs = false;
		elseif($this->menuItemOnly && $doc['hidemenu']) $rs = false;
		elseif(!$doc['published'])                      $rs = false;
		else                                            $rs = true;
		
		return $rs;
	}
	
	function isEnd($doc)
	{
		if(in_array($doc['id'],$this->stopIds) || !$doc['parent'] || ( !$doc['published'] && !$this->pathThruUnPub ))
		{
			$rs = true;
		}
		else $rs = false;
		
		return $rs;
	}
	
	function getDocs($docid)
	{
		global $modx;
		
		$docs = array();
		$c = 0;
		$doc = array();
		while ($docid !== $this->homeId  && $c < 1000 )
		{
			$doc = $modx->getPageInfo($docid,0,'*');
			if($this->isEnable($doc)) $docs[] = $doc;
			$docid = $doc['parent'];
			if($docid==='0') $docid = $this->homeId ;
			$c++;
		}
		$docs[] = $modx->getPageInfo($this->homeId ,0,'*');
		return $docs;
	}
	
	function setTopics($docs,$tpl)
	{
		global $modx;
		$topics = array();
		$docs = array_reverse($docs);
		$i = 0;
		$c = count($docs);
		foreach($docs as $doc)
		{
			$ph = $doc;
			if(in_array($doc['id'],$this->stopIDs)) break;
			$ph['href']  = ($doc['id'] == $modx->config['site_start']) ? $modx->config['site_url'] : $modx->makeUrl($doc['id'],'','','full');
			foreach($this->titleField as $f)
			{
				if($doc[$f]!=='')
				{
					$ph['title'] = $doc[$f];
					break;
				}
			}
			if(!isset($ph['title'])) $ph['title'] = $doc['pagetitle'];
			
			foreach($this->descField as $f)
			{
				if($doc[$f]!=='')
				{
					$ph['desc'] = $doc[$f];
					break;
				}
			}
			if(!isset($ph['desc'])) $ph['desc'] = $doc['pagetitle'];
			
			if(isset($this->homeTopicTitle) && $doc['id'] == $this->homeId)
			{
				$ph['title'] = $this->homeTopicTitle;
			}
			if(isset($this->homeTopicDesc) && $doc['id'] == $this->homeId)
			{
				$ph['desc'] = $this->homeTopicDesc;
			}
			$isRf = false;
			if(isset($tpl['reference_topic']) && $doc['type'] === "reference") {
				$isRf = true;
			}
			
			$ph['title'] = htmlspecialchars($ph['title'], ENT_QUOTES, $modx->config['modx_charset']);
			$ph['desc']  = htmlspecialchars($ph['desc'], ENT_QUOTES, $modx->config['modx_charset']);
			
			if($i===$c-1&&$doc['id']==$modx->documentIdentifier)
				           $topics[$i] = $this->parseText($tpl['current_topic'],$ph);
			elseif($i===0) $topics[$i] = $this->parseText($tpl['home_topic'],$ph);
			elseif($isRf)  $topics[$i] = $this->parseText($tpl['reference_topic'],$ph);
			else           $topics[$i] = $this->parseText($tpl['other_topic'],$ph);
			
			$i++;
		}
		return $topics;
	}
	
	function getDisableDocs()
	{
		global $modx;
		$tbl_site_content = $modx->getFullTableName('site_content');
		
		if(empty($this->disabledUnder)) return $this->disabledOn;
		
		$rs = $modx->db->select('id', $tbl_site_content, "parent IN ({$this->disabledUnder})");
		while ($id = $modx->db->getValue($rs))
		{
			$hidden[] = $id;
		}
		return array_merge($this->disabledOn,$hidden);
	}
	
	function parseText($tpl='',$ph=array())
	{
		foreach($ph as $k=>$v)
		{
			$k = "[+{$k}+]";
			$tpl = str_replace($k,$v,$tpl);
		}
		return $tpl;
	}
}
