<?php
class TopicPath
{
    private $stopIds;
    private $pathThruUnPub;

    public function __construct()
	{
		global $modx;
		if(event()->params) extract($modx->event->params);
		$this->set('showTopicsAtHome', '0');
		$this->set('showAtLeastOneTopic', '0');
		$this->array_set('ignoreIDs', '');
		$this->array_set('disabledOn', '');
		$this->set('disabledUnder', '');
		$this->set('menuItemOnly', '1');
		$this->set('limit', 100);
		$this->set('topicGap', '...');
		$this->array_set('titleField', 'menutitle,pagetitle');
		$this->array_set('descField', 'description,longtitle,pagetitle');
		$this->set('homeId', $modx->config['site_start']);
		$this->array_set('stopIDs', '');
		$this->set('order','');
		
		if(isset($homeTopicTitle)) $this->homeTopicTitle = $homeTopicTitle;
		if(isset($homeTopicDesc))  $this->homeTopicDesc  = $homeTopicDesc;
		
		$this->theme = $theme;
		$this->tpl = array();
		if(isset($tplOuter))          $this->tpl['Outer']          = $tplOuter;
		if(isset($tplHomeTopic))      $this->tpl['HomeTopic']      = $tplHomeTopic;
		if(isset($tplCurrentTopic))   $this->tpl['CurrentTopic']   = $tplCurrentTopic;
		if(isset($tplOtherTopic))     $this->tpl['OtherTopic']     = $tplOtherTopic;
		if(isset($tplReferenceTopic)) $this->tpl['ReferenceTopic'] = $tplReferenceTopic;
		if(isset($tplSeparator)) {
			$this->tpl['Separator'] = $tplSeparator;
		} elseif(isset($tplOtherTopic) && strpos(trim($tplOtherTopic),'<li')===0) {
			$this->tpl['Separator'] = "\n";
		}
	}

	private function set($key, $default=null) {
		$this->$key = array_get(event()->params, $key, $default);
	}

	private function array_set($key, $default=null) {
		$this->$key = explode(',', array_get(event()->params, $key, $default));
	}

	public function getTopicPath()
	{
		global $modx;
		$id = $modx->documentIdentifier;
		
		$this->disabledOn = $this->getDisableDocs();
		
		if(!$this->showTopicsAtHome && $id === $modx->config['site_start']) return;
		elseif(in_array($id,$this->disabledOn))                             return;
		$default = include(__DIR__ . '/config/default.php');
		switch(strtolower($this->theme))
		{
			case 'list':
			case 'li':
				$tpl = $default['list'];
				break;
			case 'bootstrap':
				$tpl = $default['bootstrap'];
				break;
			case 'microdata':
				$tpl = $default['microdata'];
				break;
			default:
				$tpl = $default['simple'];
		}
		$tpl = array_merge($tpl, $this->tpl);
		foreach($tpl as $i=>$v)
		{
			if(substr($v,0,5)==='@CODE') $tpl[$i] = substr($v,6);
		}
		
		$docs   = $this->getDocs($id);
		$topics = $this->setTopics($docs, $tpl);
		
		if($this->limit < count($topics)) $topics = $this->trimTopics($topics);
		
		if(count($topics) > 1 || (count($topics) == 1 && $this->showAtLeastOneTopic))
		{
			if(strpos($this->order, 'r') === 0) $topics = array_reverse($topics);
			$rs = join($tpl['Separator'],$topics);
			$rs = $modx->parseText($tpl['Outer'],array('topics'=>$rs));
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
	
	private function getDocs($docid)
	{
		global $modx;
		
		$docs = array();
		$c = 0;
		$doc = array();
		while ($docid !== $this->homeId  && $c < 1000 )
		{
			$doc = $modx->getPageInfo($docid,0,'*');
			if($doc['id'] == $modx->documentIdentifier) {
                $doc['hidemenu'] = false;
            }
			if($this->isEnable($doc)) {
                $docs[] = $doc;
            }
			$docid = $doc['parent'];
			if($docid==='0') $docid = $this->homeId ;
			$c++;
		}
		$docs[] = $modx->getPageInfo($this->homeId ,0,'*');
		return $docs;
	}
	
	private function setTopics($docs, $tpl)
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
			
			if($i===$c-1&&$doc['id']==$modx->documentIdentifier) {
                $topics[$i] = $modx->parseText($tpl['CurrentTopic'], $ph);
            }
			elseif($i===0) {
                $topics[$i] = $modx->parseText($tpl['HomeTopic'], $ph);
            }
			elseif($isRf) {
                $topics[$i] = $modx->parseText($tpl['ReferenceTopic'], $ph);
            }
			else {
                $topics[$i] = $modx->parseText($tpl['OtherTopic'], $ph);
            }
			
			$i++;
		}
		return $topics;
	}
	
	private function getDisableDocs()
	{
		global $modx;
		$tbl_site_content = evo()->getFullTableName('site_content');
		
		if(empty($this->disabledUnder)) return $this->disabledOn;
		
		$rs = db()->select('id', $tbl_site_content, "parent IN ({$this->disabledUnder})");
		while ($id = db()->getValue($rs))
		{
			$hidden[] = $id;
		}
		return array_merge($this->disabledOn,$hidden);
	}
}
