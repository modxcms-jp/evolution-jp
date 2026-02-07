<?php

class TopicPath
{
    private $stopIds;
    private $showTopicsAtHome;
    private $showAtLeastOneTopic;
    private $ignoreIDs;
    private $disabledOn;
    private $disabledUnder;
    private $menuItemOnly;
    private $limit;
    private $topicGap;
    private $titleField;
    private $descField;
    private $homeId;
    private $homeTopicTitle;
    private $homeTopicDesc;
    private $order;
    private $theme;
    private $tpl;

    public function __construct()
    {
        global $modx;
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
        $this->array_set('stopIds', '');
        $this->set('order', '');

        if (event()->params) {
            extract($modx->event->params);
        }
        if (isset($homeTopicTitle)) $this->homeTopicTitle = $homeTopicTitle;
        if (isset($homeTopicDesc)) $this->homeTopicDesc = $homeTopicDesc;

        if ($theme === 'li') {
            $theme = 'list';
        }
        if (!$theme) {
            $theme = 'simple';
        }
        $this->theme = $theme;
        $this->tpl = [];
        if (isset($tplOuter)) $this->tpl['Outer'] = $tplOuter;
        if (isset($tplHomeTopic)) $this->tpl['HomeTopic'] = $tplHomeTopic;
        if (isset($tplCurrentTopic)) $this->tpl['CurrentTopic'] = $tplCurrentTopic;
        if (isset($tplOtherTopic)) $this->tpl['OtherTopic'] = $tplOtherTopic;
        if (isset($tplReferenceTopic)) $this->tpl['ReferenceTopic'] = $tplReferenceTopic;
        if (isset($tplSeparator)) {
            $this->tpl['Separator'] = $tplSeparator;
        } elseif (isset($tplOtherTopic) && strpos(trim($tplOtherTopic), '<li') === 0) {
            $this->tpl['Separator'] = "\n";
        }
    }

    public function getTopicPath()
    {
        $this->disabledOn = $this->getDisableDocs();

        if (!$this->showTopicsAtHome && docid() === $this->homeId) return;
        elseif (in_array(docid(), $this->disabledOn)) return;
        $default = include(__DIR__ . '/config/default.php');
        if (!isset($default[$this->theme])) {
            $available = implode(', ', array_keys($default));
            throw new Exception("設定に存在しないテーマ '{$this->theme}' が指定されています。利用可能なテーマ: {$available}");
        }
        $tpl = $default[$this->theme];
        $tpl = array_merge($tpl, $this->tpl);
        foreach ($tpl as $i => $v) {
            if (substr($v, 0, 5) === '@CODE') $tpl[$i] = substr($v, 6);
        }

        $docs = $this->getDocs(docid());
        $topics = $this->setTopics($docs, $tpl);

        if ($this->limit < count($topics)) {
            $topics = $this->trimTopics($topics);
        }

        if (1 < count($topics) || (count($topics) == 1 && $this->showAtLeastOneTopic)) {
            if (strpos($this->order, 'r') === 0) {
                $topics = array_reverse($topics);
            }
            return evo()->parseText(
                $tpl['Outer'],
                ['topics' => implode($tpl['Separator'], $topics)]
            );
        }

        return '';
    }

    private function set($key, $default = null)
    {
        $this->$key = array_get(event()->params, $key, $default);
    }

    private function array_set($key, $default = null)
    {
        $this->$key = explode(',', array_get(event()->params, $key, $default));
    }

    private function trimTopics($topics)
    {
        $last_topic = array_pop($topics);
        array_splice($topics, $this->limit - 1);
        $topics[] = $this->topicGap;
        $topics[] = $last_topic;

        return $topics;
    }

    private function isEnable($doc)
    {
        if (in_array($doc['id'], $this->ignoreIDs)) $rs = false;
        elseif ($this->menuItemOnly && $doc['hidemenu']) $rs = false;
        elseif (!$doc['published']) $rs = false;
        else                                            $rs = true;

        return $rs;
    }

    private function getDocs($docid)
    {
        global $modx;

        $docs = [];
        $c = 0;
        while ($docid !== $this->homeId && $c < 1000) {
            $doc = $modx->getPageInfo($docid, 0, '*');
            if ($doc['id'] == $modx->documentIdentifier) {
                $doc['hidemenu'] = false;
            }
            if ($this->isEnable($doc)) {
                $docs[] = $doc;
            }
            $docid = $doc['parent'];
            if (!$docid) {
                $docid = $this->homeId;
            }
            $c++;
        }
        $docs[] = $modx->getPageInfo($this->homeId, 0, '*');
        return $docs;
    }

    private function setTopics($docs, $tpl)
    {
        global $modx;
        $topics = [];
        $docs = array_reverse($docs);
        $i = 0;
        $total = count($docs);
        foreach ($docs as $doc) {
            $ph = $doc;
            if (in_array($doc['id'], $this->stopIds)) {
                break;
            }
            $ph['url'] = $this->url($doc);
            $ph['href'] = &$ph['url'];

            $ph['title'] = hsc($this->title($doc));
            $ph['desc'] = hsc($this->desc($doc));

            if ($i === $total - 1 && $doc['id'] == $modx->documentIdentifier) {
                $topics[$i] = $modx->parseText($tpl['CurrentTopic'], $ph);
            } elseif ($i === 0) {
                $topics[$i] = $modx->parseText($tpl['HomeTopic'], $ph);
            } elseif ($this->isReferenceTopic($doc, $tpl)) {
                $topics[$i] = $modx->parseText($tpl['ReferenceTopic'], $ph);
            } else {
                $topics[$i] = $modx->parseText($tpl['OtherTopic'], $ph);
            }

            $i++;
        }
        return $topics;
    }

    private function url($doc)
    {
        return ($doc['id'] == $this->homeId)
            ? MODX_SITE_URL
            : evo()->makeUrl($doc['id'], '', '', 'full')
        ;
    }
    private function title($doc)
    {
        if ($this->homeTopicTitle !== null && $doc['id'] == $this->homeId) {
            return $this->homeTopicTitle;
        }

        foreach ($this->titleField as $f) {
            if ($doc[$f] !== '') {
                return $doc[$f];
            }
        }

        return $doc['pagetitle'];
    }

    private function desc($doc)
    {
        if ($this->homeTopicDesc !== null && $doc['id'] == $this->homeId) {
            return $this->homeTopicDesc;
        }

        foreach ($this->descField as $f) {
            if ($doc[$f] !== '') {
                return $doc[$f];
            }
        }

        return $doc['pagetitle'];
    }

    private function isReferenceTopic($doc, $tpl)
    {
        return isset($tpl['ReferenceTopic']) && $doc['type'] === "reference";
    }

    private function getDisableDocs()
    {
        $tbl_site_content = evo()->getFullTableName('site_content');

        if (empty($this->disabledUnder)) return $this->disabledOn;

        $rs = db()->select('id', $tbl_site_content, "parent IN ({$this->disabledUnder})");
        while ($id = db()->getValue($rs)) {
            $hidden[] = $id;
        }
        return array_merge($this->disabledOn, $hidden);
    }
}
