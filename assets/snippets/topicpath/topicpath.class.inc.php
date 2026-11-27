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
    private $showUnpublished;
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
        $this->set('showTopicsAtHome', '0');
        $this->set('showAtLeastOneTopic', '0');
        $this->array_set('ignoreIDs', '');
        $this->array_set('disabledOn', '');
        $this->set('disabledUnder', '');
        $this->set('menuItemOnly', '1');
        $this->set('showUnpublished', '1');
        $this->set('limit', 100);
        $this->set('topicGap', '...');
        $this->array_set('titleField', 'menutitle,pagetitle');
        $this->array_set('descField', 'description,longtitle,pagetitle');
        $this->set('homeId', config('site_start'));
        $this->homeId = (int)$this->homeId;
        $this->array_set('stopIds', '');
        $this->set('order', '');
        $this->set('homeTopicTitle');
        $this->set('homeTopicDesc');

        // 古い版のテーマ名を今の名前に読み替える（古い版は大文字・小文字を区別しなかった）。
        // string は古い版のスニペットのプロパティの既定値で、アップデーターはプロパティを残すため、
        // 上げたサイトに残っている。書式は今の simple と同じ
        $legacyThemes = ['li' => 'list', 'string' => 'simple'];
        $theme = (string)array_get(event()->params, 'theme', '');
        $legacyKey = strtolower(trim($theme));
        if (isset($legacyThemes[$legacyKey])) {
            $theme = $legacyThemes[$legacyKey];
        }
        $this->theme = $theme ?: 'simple';

        $this->tpl = [];
        foreach (['Outer', 'HomeTopic', 'CurrentTopic', 'OtherTopic', 'ReferenceTopic', 'Separator'] as $name) {
            $value = array_get(event()->params, "tpl{$name}");
            if ($value !== null) {
                $this->tpl[$name] = $value;
            }
        }
        if (!isset($this->tpl['Separator']) && isset($this->tpl['OtherTopic'])
            && strpos(trim($this->tpl['OtherTopic']), '<li') === 0) {
            $this->tpl['Separator'] = "\n";
        }
    }

    public function getTopicPath()
    {
        $this->disabledOn = $this->getDisableDocs();

        if (!$this->showTopicsAtHome && (int)docid() === $this->homeId) return;
        elseif (in_array(docid(), $this->disabledOn)) return;
        $default = include(__DIR__ . '/config/default.php');
        $theme = $this->theme;
        if (!isset($default[$theme])) {
            evo()->logEvent(
                0,
                2,
                sprintf('TopicPath: 設定にないテーマ名 %s が指定されたため simple で表示しました', hsc($theme)),
                'TopicPath'
            );
            $theme = 'simple';
        }
        $tpl = array_merge($default[$theme], $this->tpl);
        foreach ($tpl as $i => $v) {
            $tpl[$i] = preg_replace('/^@CODE[:\s]?/', '', $v);
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
        elseif (!$doc['published'] && !$this->isUnpublishedVisible()) $rs = false;
        else                                            $rs = true;

        return $rs;
    }

    // 非公開の文書を表示できるのは、閲覧中の文書自体が非公開のとき（view_unpublished 権限での閲覧）に限る。
    // 公開中の文書はページキャッシュされて一般の訪問者にも配信されるため、非公開の親を含めない
    private function isUnpublishedVisible()
    {
        return $this->showUnpublished && !evo()->doc('published');
    }

    private function getDocs($docid)
    {
        $docs = [];
        $c = 0;
        while ((int)$docid !== $this->homeId && $c < 1000) {
            $doc = evo()->getPageInfo($docid, 0, '*');
            // false when the visitor cannot see it (web private) or it does not exist
            if (!$doc) {
                break;
            }
            if ($doc['id'] == docid()) {
                $doc['hidemenu'] = false;
            }
            if ($this->isEnable($doc)) {
                $docs[] = $doc;
            }
            $docid = (int)$doc['parent'];
            if (!$docid) {
                $docid = $this->homeId;
            }
            $c++;
        }
        $home = evo()->getPageInfo($this->homeId, 0, '*');
        if ($home) {
            $docs[] = $home;
        }
        return $docs;
    }

    private function setTopics($docs, $tpl)
    {
        $topics = [];
        $docs = array_reverse($docs);
        $i = 0;
        $total = count($docs);
        foreach ($docs as $doc) {
            $ph = $doc;
            if (in_array($doc['id'], $this->stopIds)) {
                break;
            }
            $ph['url'] = hsc($this->url($doc), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, null, false);
            $ph['href'] = $ph['url'];

            $ph['title'] = hsc($this->title($doc));
            $ph['desc'] = hsc($this->desc($doc));

            if ($i === $total - 1 && $doc['id'] == docid()) {
                $topics[$i] = evo()->parseText($tpl['CurrentTopic'], $ph);
            } elseif ($i === 0) {
                $topics[$i] = evo()->parseText($tpl['HomeTopic'], $ph);
            } elseif ($this->isReferenceTopic($doc, $tpl)) {
                $topics[$i] = evo()->parseText($tpl['ReferenceTopic'], $ph);
            } else {
                $topics[$i] = evo()->parseText($tpl['OtherTopic'], $ph);
            }

            $i++;
        }
        return $topics;
    }

    private function url($doc)
    {
        if ((int)$doc['id'] === $this->homeId && $this->homeId === (int)config('site_start')) {
            return MODX_SITE_URL;
        }
        return evo()->makeUrl($doc['id'], '', '', 'full');
    }
    private function title($doc)
    {
        if ($this->homeTopicTitle !== null && (int)$doc['id'] === $this->homeId) {
            return $this->homeTopicTitle;
        }

        foreach ($this->titleField as $f) {
            if (($doc[trim($f)] ?? '') !== '') {
                $f = trim($f);
                return $doc[$f];
            }
        }

        return $doc['pagetitle'];
    }

    private function desc($doc)
    {
        if ($this->homeTopicDesc !== null && (int)$doc['id'] === $this->homeId) {
            return $this->homeTopicDesc;
        }

        foreach ($this->descField as $f) {
            if (($doc[trim($f)] ?? '') !== '') {
                $f = trim($f);
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

        $parents = array_filter(array_map('intval', explode(',', (string)$this->disabledUnder)));
        if (!$parents) return $this->disabledOn;

        $hidden = [];
        $rs = db()->select('id', $tbl_site_content, sprintf('parent IN (%s)', implode(',', $parents)));
        while ($id = db()->getValue($rs)) {
            $hidden[] = $id;
        }
        return array_merge($this->disabledOn, $hidden);
    }
}
