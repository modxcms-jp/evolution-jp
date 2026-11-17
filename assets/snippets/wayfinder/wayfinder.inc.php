<?php

class Wayfinder
{
    public $_config;
    public $_templates;
    public $_css;
    public $docs = [];
    public $parentTree = [];
    public $hasChildren = [];
    public $tvList = [];

    public function run()
    {
        $this->parentTree = evo()->getParentIds($this->_config['hereId']);
        $this->parentTree[] = $this->_config['hereId'];
        $this->checkTemplates();
        if ($this->_config['cssTpl'] || $this->_config['jsTpl']) {
            $this->regJsCss();
        }
        $this->docs = $this->getData();

        if (empty($this->docs)) {
            return '';
        }

        // Sort documents by level for proper wrapper substitution
        ksort($this->docs);
        return $this->buildMenu();
    }

    function buildMenu()
    {
        $output = '';
        foreach ($this->docs as $level => $parents) {
            foreach ($parents as $parentId => $children) {
                if ($this->_config['hideSubMenus'] && !$this->isHere($parentId) && 1 < $level) {
                    continue;
                }

                $subMenu = $this->buildSubMenu($children, $level);
                if ($level == 1 && (!$this->_config['displayStart'] || $this->_config['id'] == 0)) {
                    $output = $subMenu;
                    continue;
                }

                if ($level == 0 && $this->_config['displayStart']) {
                    $output = $subMenu;
                    continue;
                }

                $wrapper_text = sprintf('[+wf.wrapper.%s+]', $parentId);
                if (strpos($output, $wrapper_text) !== false) {
                    $output = str_replace(
                        $wrapper_text,
                        $subMenu,
                        $output
                    );
                }
            }
        }

        return $output;
    }

    function buildSubMenu($subDocs, $level)
    {
        $subMenuOutput = '';
        $counter = 1;
        $total = count($subDocs);

        foreach ($subDocs as $docId => $docInfo) {
            $docInfo['level'] = $level;
            $docInfo['first'] = $counter == 1 ? 1 : 0;
            $docInfo['last'] = ($counter == $total && 0 < $total) ? 1 : 0;

            if (in_array($docInfo['id'], $this->hasChildren)) {
                $docInfo['hasChildren'] = 1;
                $numChildren = count($this->docs[$level + 1][$docInfo['id']]);
            } else {
                $docInfo['hasChildren'] = 0;
                $numChildren = 0;
            }

            $subMenuOutput .= $this->renderRow($docInfo, $numChildren, $counter);
            $counter++;
        }

        if ($level < 1) {
            return $subMenuOutput;
        }

        $wrapperClass = $level == 1 ? 'outercls' : 'innercls';
        $classNames = $this->setItemClass($wrapperClass, 0, 0, 0, $level);

        $ph = [];
        $ph['wf.wrapper'] = $subMenuOutput;
        $ph['wf.classes'] = $classNames ? sprintf(' class="%s"', $classNames) : '';
        $ph['wf.classnames'] = $classNames;
        $ph['wf.level'] = $level;
        if ($this->_templates['innerTpl'] && $wrapperClass === 'innercls') {
            $tpl = $this->_templates['innerTpl'];
        } else {
            $tpl = $this->_templates['outerTpl'];
        }

        return evo()->parseText($tpl, $ph);
    }

    function renderRow(&$resource, $numChildren, $curNum)
    {
        $refid = $resource['id'];

        // Determine fields for use from referenced resource
        if ($this->_config['useReferenced'] && $resource['type'] === 'reference' && preg_match('@^[1-9][0-9]*$@', $resource['content'])) {
            if ($this->_config['useReferenced'] === 'id') {
                // if id only, do not need get referenced data
                $resource['id'] = $resource['content'];
            } elseif ($referenced = evo()->getDocument($resource['content'])) {
                if (in_array($this->_config['useReferenced'], explode(',', '1,*'))) {
                    $this->_config['useReferenced'] = array_keys($resource);
                }
                if (!is_array($this->_config['useReferenced'])) {
                    $this->_config['useReferenced'] = preg_split("/[\s,]+/", $this->_config['useReferenced']);
                }
                $this->_config['useReferenced'] = array_diff($this->_config['useReferenced'], explode(',', 'content,parent,isfolder'));

                // choose the fields with the referenced document's values
                $linkTextField = $this->linkTextField($referenced);
                $titleField = $this->titleField($referenced);

                foreach ($this->_config['useReferenced'] as $field) {
                    if (isset($referenced[$field])) {
                        $resource[$field] = $referenced[$field];
                    }
                    if (in_array($field, ['linktext', $linkTextField], true)) {
                        $resource['linktext'] = $referenced[$linkTextField] ?? '';
                    }
                    if (in_array($field, ['title', $titleField], true)) {
                        $resource['title'] = $referenced[$titleField] ?? '';
                    }
                }
            }
        }

        $usedTemplate = $this->rowTemplateName($resource, $numChildren);
        $useSub = $resource['hasChildren'] ? "[+wf.wrapper.{$refid}+]" : '';
        $classNames = $this->setItemClass('rowcls', $resource['id'], $resource['first'], $resource['last'], $resource['level'], $resource['hasChildren'], $resource['type']);
        $useClass = $classNames ? sprintf(' class="%s"', $classNames) : '';

        if ($this->_config['rowIdPrefix']) {
            $useId = sprintf(' id="%s%s"', $this->_config['rowIdPrefix'], $resource['id']);
        } else {
            $useId = '';
        }

        $ph = [];
        $ph['wf.wrapper'] = $useSub;
        $ph['wf.classes'] = $useClass;
        $ph['wf.classnames'] = $classNames;
        $ph['wf.link'] = $resource['link'];
        $ph['url'] = $resource['link'];
        $ph['wf.title'] = !$this->_config['entityEncode'] ? $resource['title'] : evo()->hsc($resource['title']);
        $ph['wf.linktext'] = !$this->_config['entityEncode'] ? $resource['linktext'] : evo()->hsc($resource['linktext']);
        $ph['wf.id'] = $useId;
        $ph['wf.alias'] = $resource['alias'];
        $ph['wf.attributes'] = $resource['link_attributes'];
        $ph['wf.docid'] = $resource['id'];
        $ph['wf.introtext'] = !$this->_config['entityEncode'] ? $resource['introtext'] : evo()->hsc($resource['introtext']);
        $ph['wf.description'] = !$this->_config['entityEncode'] ? $resource['description'] : evo()->hsc($resource['description']);
        $ph['wf.subitemcount'] = $numChildren;
        $ph['wf.refid'] = $refid;
        $ph['wf.menuindex'] = $resource['menuindex'];
        $ph['wf.iterator'] = $curNum;

        foreach ($resource as $dvName => $dvVal) {
            $ph[$dvName] = $dvVal;
        }

        foreach ($this->tvList as $tvName) {
            $ph[$tvName] = $resource[$tvName];
        }

        return evo()->parseText($this->_templates[$usedTemplate], $ph) . $this->_config['nl'];
    }

    private function rowTemplateName($resource, $numChildren)
    {
        $isCurrent = $resource['id'] == evo()->documentIdentifier;
        $showsChildren = $this->showsChildrenAt($resource['level']);

        if ($this->_config['displayStart'] && $resource['level'] == 0) {
            return 'startItemTpl';
        }
        if ($isCurrent && $resource['isfolder'] && $this->_templates['parentRowHereTpl'] && $showsChildren && $numChildren) {
            return 'parentRowHereTpl';
        }
        if ($isCurrent && $this->_templates['innerHereTpl'] && $resource['level'] > 1) {
            return 'innerHereTpl';
        }
        if ($isCurrent && $this->_templates['hereTpl']) {
            return 'hereTpl';
        }
        if ($resource['isfolder'] && $this->_templates['activeParentRowTpl'] && $showsChildren && $this->isHere($resource['id']) && $numChildren) {
            return 'activeParentRowTpl';
        }
        if ($resource['isfolder']
            && ($resource['template'] == '0' || is_numeric(strpos($resource['link_attributes'], 'rel="category"')))
            && $this->_templates['categoryFoldersTpl']
            && $showsChildren) {
            return 'categoryFoldersTpl';
        }
        if ($resource['isfolder'] && $this->_templates['parentRowTpl'] && $showsChildren && $numChildren) {
            return 'parentRowTpl';
        }
        if ($resource['level'] > 1 && $this->_templates['innerRowTpl']) {
            return 'innerRowTpl';
        }
        if ($resource['last'] && $this->_templates['lastRowTpl']) {
            return 'lastRowTpl';
        }
        return 'rowTpl';
    }

    // &level: 0 means no limit on depth
    private function showsChildrenAt($level)
    {
        return $level < $this->_config['level'] || $this->_config['level'] == 0;
    }

    function setItemClass($classType, $docId = 0, $first = 0, $last = 0, $level = 0, $isFolder = 0, $type = 'document')
    {
        $classNames = [];
        $class = &$this->_css;
        $config = &$this->_config;

        switch ($classType) {
            case 'outercls':
                if ($class['outer']) {
                    $classNames[] = $class['outer'];
                }
                break;
            case 'innercls':
                if ($class['inner']) {
                    $classNames[] = $class['inner'];
                }
                if ($class['outerLevel']) {
                    $classNames[] = $class['outerLevel'] . $level;
                }
                break;
            case 'rowcls':
                if ($class['row']) {
                    $classNames[] = $class['row'];
                }
                if ($first && $class['first']) {
                    $classNames[] = $class['first'];
                }
                if ($last && $class['last']) {
                    $classNames[] = $class['last'];
                }
                if ($class['level']) {
                    $classNames[] = $class['level'] . $level;
                }

                if ($class['here'] && $this->isHere($docId)) {
                    $classNames[] = $class['here'];
                }
                if ($class['self'] && $docId == $config['hereId']) {
                    $classNames[] = $class['self'];
                }
                if ($class['weblink'] && $type === 'reference') {
                    $classNames[] = $class['weblink'];
                }

                if ($isFolder && $class['parent'] && $this->showsChildrenAt($level)) {
                    if ($this->isHere($docId) || !$config['hideSubMenus']) {
                        $classNames[] = $class['parent'];
                    }
                }
                break;
        }

        return implode(' ', $classNames);
    }

    // "you are here": the current document or one of its ancestors
    function isHere($did)
    {
        return in_array($did, $this->parentTree);
    }

    // Add the specified css & javascript chunks to the page
    function regJsCss()
    {
        if ($this->_config['cssTpl']) {
            $cssChunk = $this->fetch($this->_config['cssTpl']);
            if ($cssChunk) {
                evo()->regClientCSS($cssChunk);
            }
        }
        if ($this->_config['jsTpl']) {
            $jsChunk = $this->fetch($this->_config['jsTpl']);
            if ($jsChunk) {
                evo()->regClientStartupScript($jsChunk);
            }
        }
    }

    // level => parent id => documents
    private function getData()
    {
        $ids = [];

        if (!$this->_config['hideSubMenus']) {
            $ids = evo()->getChildIds(
                $this->_config['id'],
                $this->_config['level'] ?: 10
            );
        } else {
            $ids = evo()->getChildIds($this->_config['id'], 1, $ids);
            // if startId not in parents, only show children of startId
            $parents = [$this->_config['hereId']] + evo()->getParentIds($this->_config['hereId']);
            if ($this->_config['id'] == 0 || in_array($this->_config['id'], $parents)) {
                //remove parents higher than startId(including startId)
                $startId_parents = [$this->_config['id']] + evo()->getParentIds($this->_config['id']);
                $parents = array_diff($parents, $startId_parents);

                //remove parents lower than level of startId + level depth
                $parents = array_slice(
                    array_reverse($parents),
                    0,
                    ($this->_config['level'] ?: 10) - 1);

                foreach ($parents as $p) {
                    $ids += evo()->getChildIds($p, 1, $ids);
                }
            }
        }
        if ($this->_config['displayStart'] && $this->_config['id'] !== 0) {
            $ids[] = $this->_config['id'];
        }

        if (!$ids) {
            return [];
        }

        $from = [];
        $from[] = '[+prefix+]site_content sc';
        $from[] = 'LEFT JOIN [+prefix+]document_groups dg ON dg.document=sc.id';

        $where = [];
        $where[] = 'sc.published=1';
        $where[] = 'AND sc.deleted=0';
        if (evo()->isFrontend()) {
            if (!$this->_config['showPrivate']) {
                $where[] = 'AND sc.privateweb=0';
            }
        } elseif (sessionv('mgrRole') != 1) {
            if (evo()->getUserDocGroups()) {
                $where[] = sprintf(
                    'AND (sc.privatemgr=0 OR dg.document_group IN (%s))',
                    implode(',', evo()->getUserDocGroups()
                    )
                );
            } else {
                $where[] = 'AND sc.privatemgr=0';
            }
        }

        if (!$this->_config['ignoreHidden']) {
            $where[] = 'AND sc.hidemenu=0';
        }

        if ($this->_config['includeDocs']) {
            // no valid id matches nothing (0 is never a document id)
            $includeDocs = $this->idList($this->_config['includeDocs']) ?: [0];
            $where[] = sprintf(
                'AND sc.id IN (%s)',
                implode(',', $includeDocs)
            );
        }

        $excludeDocs = $this->idList($this->_config['excludeDocs']);
        if ($excludeDocs) {
            $where[] = sprintf(
                'AND (sc.id NOT IN (%s))',
                implode(',', $excludeDocs)
            );
        }

        if (!empty($this->_config['where'])) {
            $where[] = sprintf(
                'AND (%s)',
                $this->_config['where']
            );
        }

        $where[] = sprintf(
            'AND sc.id IN (%s)',
            implode(',', $ids)
        );
        $where[] = 'GROUP BY sc.id';

        if (strtolower($this->_config['sortBy']) === 'random') {
            $sort = 'rand()';
        } else {
            // each item is "field" or "field ASC|DESC" (site_content columns only);
            // &sortOrder is the direction of the items without one
            $sortOrder = strtoupper(trim((string)$this->_config['sortOrder'])) === 'DESC' ? 'DESC' : 'ASC';
            $_ = [];
            $hasId = false;
            foreach (explode(',', $this->_config['sortBy']) as $v) {
                $parts = preg_split('/\s+/', trim($v));
                $field = $parts[0];
                $dir = isset($parts[1]) ? strtoupper($parts[1]) : $sortOrder;
                if (count($parts) > 2 || !in_array($dir, ['ASC', 'DESC'], true) || !in_array($field, $this->contentColumns(), true)) {
                    continue;
                }
                $_[] = "sc.{$field} {$dir}";
                $hasId = $hasId || $field === 'id';
            }
            if (!$_) {
                $_[] = "sc.menuindex {$sortOrder}";
            }
            // ties keep the id order (GROUP BY leaves it to the query plan otherwise)
            if (!$hasId) {
                $_[] = 'sc.id ASC';
            }
            $sort = implode(', ', $_);
        }

        $fields = explode(',', 'id,menutitle,pagetitle,introtext,menuindex,published,hidemenu,parent,isfolder,description,alias,longtitle,type,content,template,link_attributes');
        foreach ($fields as $i => $v) {
            if ($v === 'alias') $fields[$i] = "IF(sc.alias='', sc.id, sc.alias) AS alias";
            elseif ($v === 'content') $fields[$i] = "IF(sc.type='reference',sc.content,'') AS content";
            else                     $fields[$i] = 'sc.' . $v;
        }

        $result = db()->select(
            'DISTINCT ' . implode(',', $fields),
            $from,
            $where,
            $sort,
            $this->_config['limit'] ?: ''
        );

        $level = 1;
        $prevParent = -1;
        //Setup startlevel for determining each items level
        if ($this->_config['id'] == 0) {
            $startLevel = 0;
        } else {
            $startLevel = count(evo()->getParentIds($this->_config['id']));
            $startLevel = $startLevel ? $startLevel + 1 : 1;
        }
        $rows = [];
        while ($row = db()->getRow($result)) {
            $linkScheme = $this->_config['fullLink'] ? 'full' : 'abs';
            if ($this->_config['useWeblinkUrl'] && $row['type'] === 'reference') {
                if (preg_match('@^[1-9][0-9]*$@', $row['content'])) {
                    $row['link'] = evo()->makeUrl((int)$row['content'], '', '', $linkScheme);
                } else {
                    $row['link'] = $row['content'];
                }
            } elseif ($row['id'] == config('site_start')) {
                $row['link'] = $this->_config['fullLink'] ? MODX_SITE_URL : MODX_BASE_URL;
            } else {
                $row['link'] = evo()->makeUrl($row['id'], '', '', $linkScheme);
            }

            //determine the level, if parent has changed
            if ($prevParent !== $row['parent']) {
                $level = count(evo()->getParentIds($row['id'])) + 1 - $startLevel;
            }
            //add parent to hasChildren array for later processing
            if (($level > 1 || $this->_config['displayStart']) && !in_array($row['parent'], $this->hasChildren)) {
                $this->hasChildren[] = $row['parent'];
            }
            $row['level'] = $level;
            $prevParent = $row['parent'];
            $row['linktext'] = $row[$this->linkTextField($row)] ?? '';
            $row['title'] = $row[$this->titleField($row)] ?? '';

            $rows[] = $row;
        }

        $resourceArray = [];
        foreach ($rows as $row) {
            foreach ($this->tvList as $tvName) {
                $row[$tvName] = $this->tvValue($tvName, $row['id']);
            }
            $resourceArray[$row['level']][$row['parent']][] = $row;
        }
        return $resourceArray;
    }

    private function tvValue($tvName, $docId)
    {
        // false when the document's template has no such TV (or a weblink with template 0)
        $tv = evo()->getTemplateVarOutput($tvName, $docId);
        return is_array($tv) ? ($tv[$tvName] ?? '') : '';
    }

    // Names of all TVs
    function getTVList()
    {
        return db()->getColumn('name', db()->select('name', '[+prefix+]site_tmplvars'));
    }

    // Load each template (falling back to the default) and collect the TVs they use
    function checkTemplates()
    {
        $default['outerTpl'] = '<ul[+wf.classes+]>[+wf.wrapper+]</ul>';
        $default['rowTpl'] = '<li[+wf.id+][+wf.classes+]><a href="[+wf.link+]" title="[+wf.title+]" [+wf.attributes+]>[+wf.linktext+]</a>[+wf.wrapper+]</li>';
        $default['startItemTpl'] = '<h2[+wf.id+][+wf.classes+]>[+wf.linktext+]</h2>[+wf.wrapper+]';

        $find_fields = [];

        foreach ($this->_templates as $name => $string) {
            $fetched_tpl = $string ? $this->fetch($string) : false;
            if (!$string || !$fetched_tpl) {
                $this->_templates[$name] = $default[$name] ?? false;
                continue;
            }

            $this->_templates[$name] = $fetched_tpl;
            $tv = $this->findTemplateVars($fetched_tpl);
            foreach ($tv as $k) {
                $find_fields[] = $k;
            }
        }

        if ($find_fields) {
            $find_fields = array_unique($find_fields);
            $allTvars = $this->getTVList();

            foreach ($find_fields as $field) {
                if (in_array($field, $allTvars, true)) {
                    $this->tvList[] = $field;
                }
            }
        }
    }

    function fetch($tpl)
    {
        if (strpos($tpl, '@FILE') === 0) {
            $file_path = evo()->findBindFile(substr($tpl, 6));
            if (!$file_path) {
                return false;
            }
            if (evo()->getExtention($file_path) === '.php') {
                return 'Could not retrieve PHP file.';
            }
            return file_get_contents($file_path);
        }
        if (strpos($tpl, '@CODE') === 0) {
            return substr($tpl, 6);
        }
        if (evo()->getChunk($tpl) != '') {
            return evo()->getChunk($tpl);
        }
        return $tpl;
    }

    function findTemplateVars($tpl)
    {
        preg_match_all('~\[\+([^:]*?)(:|\+\])~', $tpl, $matches);
        $tvnames = [];
        foreach ($matches[1] as $tv) {
            if (strpos(strtolower($tv), 'phx') === 0) continue;
            if (strpos(strtolower($tv), 'wf.') === 0) continue;
            $tvnames[] = $tv;
        }

        return array_unique($tvnames);
    }

    // "1, 2,,3" => [1, 2, 3]; drops anything that is not a document id
    private function idList($ids)
    {
        $result = [];
        foreach (explode(',', (string)$ids) as $id) {
            $id = trim($id);
            if (preg_match('/^[1-9][0-9]*$/', $id)) {
                $result[] = (int)$id;
            }
        }
        return $result;
    }

    // column names of site_content (for &sortBy)
    private function contentColumns()
    {
        static $columns = null;
        if ($columns === null) {
            $meta = db()->getTableMetaData(evo()->getFullTableName('site_content'));
            $columns = $meta ? array_keys($meta) : [];
        }
        return $columns;
    }

    // First field in the comma separated list that has a value in $row, or $fallback
    private function pickField($row, $fields, $fallback)
    {
        foreach (explode(',', (string)$fields) as $field) {
            $field = trim($field);
            if ($field !== '' && !empty($row[$field])) {
                return $field;
            }
        }
        return $fallback;
    }

    // &textOfLinks: the first field with a value, or pagetitle
    private function linkTextField($row)
    {
        return $this->pickField($row, $this->_config['textOfLinks'], 'pagetitle');
    }

    // &titleOfLinks: a list falls back to pagetitle; a single field is used even when empty
    private function titleField($row)
    {
        $fields = (string)$this->_config['titleOfLinks'];
        if (strpos($fields, ',') === false) {
            return trim($fields);
        }
        return $this->pickField($row, $fields, 'pagetitle');
    }

    // &startId=i: the document itself when it has menu items below it, otherwise its parent
    function getIndexID($id)
    {
        $parent = evo()->getParentID($id);
        if (!$parent) {
            return $id;
        }

        $children = (int)db()->getValue(
            db()->select(
                'COUNT(*)',
                '[+prefix+]site_content',
                [
                    where('parent', '=', (int)$id),
                    'AND hidemenu=0'
                ]
            )
        );

        return $children ? $id : $parent;
    }

    // &startId=p: the parent, or the document itself at the top level
    function getParentID($id)
    {
        return evo()->getParentID($id) ?: $id;
    }
}
