<?php
class TopicPath
{
	function __construct()
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
		if(isset($tplOuter))          $this->tpl['Outer']          = $tplOuter;
		if(isset($tplHomeTopic))      $this->tpl['HomeTopic']      = $tplHomeTopic;
		if(isset($tplCurrentTopic))   $this->tpl['CurrentTopic']   = $tplCurrentTopic;
		if(isset($tplOtherTopic))     $this->tpl['OtherTopic']     = $tplOtherTopic;
		if(isset($tplReferenceTopic)) $this->tpl['ReferenceTopic'] = $tplReferenceTopic;
		if(isset($tplSeparator))      $this->tpl['Separator']      = $tplSeparator;
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
				$tpl['Outer']           = '<ul class="topicpath">[+topics+]</ul>';
				$tpl['HomeTopic']       = '<li class="home"><a href="[+url+]">[+title+]</a></li>';
				$tpl['CurrentTopic']    = '<li class="current">[+title+]</li>';
				$tpl['ReferenceTopic']  = '<li>[+title+]</li>';
				$tpl['OtherTopic']      = '<li><a href="[+url+]">[+title+]</a></li>';
				$tpl['Separator']       = "\n";
				break;
			default:
				$tpl['Outer']            = '[+topics+]';
				$tpl['HomeTopic']        = '<a href="[+url+]" class="home">[+title+]</a>';
				$tpl['CurrentTopic']     = '[+title+]';
				$tpl['ReferenceTopic']   = '[+title+]';
				$tpl['OtherTopic']       = '<a href="[+url+]">[+title+]</a>';
				$tpl['Separator']        = ' &gt; ';
		}
		$tpl = array_merge($tpl, $this->tpl);
		foreach($tpl as $i=>$v)
		{
			if(substr($v,0,5)==='@CODE') $tpl[$i] = substr($v,6);
		}
		
		$docs   = $this->getDocs($id);
		$topics = $this->setTopics($docs,$tpl);
		
		if($this->limit < count($topics)) $topics = $this->trimTopics($topics);
		
		if(count($topics) > 1 || count($topics) == 1 && $this->showAtLeastOneTopic)
		{
			if(substr($this->order,0,1)==='r') $topics = array_reverse($topics);
			$rs = join($tpl['Separator'],$topics);
			$rs = $this->parseText($tpl['Outer'],array('topics'=>$rs));
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
			if($doc['id'] == $modx->documentIdentifier) $doc['hidemenu'] = false;
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
			$ph['url']  = ($doc['id'] == $modx->config['site_start']) ? $modx->config['site_url'] : $modx->makeUrl($doc['id'],'','','full');
			$ph['href'] = & $ph['url'];
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
			if(isset($tpl['ReferenceTopic']) && $doc['type'] === "reference") {
				$isRf = true;
			}
			
			$ph['title'] = htmlspecialchars($ph['title'], ENT_QUOTES, $modx->config['modx_charset']);
			$ph['desc']  = htmlspecialchars($ph['desc'], ENT_QUOTES, $modx->config['modx_charset']);
			
			if($i===$c-1&&$doc['id']==$modx->documentIdentifier)
				           $topics[$i] = $this->parseText($tpl['CurrentTopic'],$ph);
			elseif($i===0) $topics[$i] = $this->parseText($tpl['HomeTopic'],$ph);
			elseif($isRf)  $topics[$i] = $this->parseText($tpl['ReferenceTopic'],$ph);
			else           $topics[$i] = $this->parseText($tpl['OtherTopic'],$ph);
			
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
		global $modx;
		
		foreach($ph as $k=>$v)
		{
			$k = "[+{$k}+]";
			$tpl = str_replace($k,$v,$tpl);
		}
		
		$modx->loadExtension('MODIFIERS');
		$modx->filter->setPlaceholders($ph);
        $i=0;
        $bt = '';
        while($bt !== $tpl)
        {
            $bt = $tpl;
            $tpl = $modx->parseText($tpl,$modx->filter->placeholders,'[+','+]',false);
            if($bt===$tpl) break;
            $i++;
            if(1000<$i) $modx->messageQuit('TopicPath parse over');
        }
		
		return $tpl;
	}
}
