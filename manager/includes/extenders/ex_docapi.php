<?php

$this->doc = new DocAPI;

class DocAPI
{

    public $mode;

    public function __construct()
    {
    }

    public function create($f = [], $groups = [])
    {
        $f = $this->correctResourceFields($f, 'create');

        $f['editedon'] = $f['createdon'];
        $f['editedby'] = $f['createdby'];
        if ($groups) {
            $f['privatemgr'] = 1;
        }

//		$f = $this->setPubStatus($f);
        $newdocid = $this->getNewDocID();
        if ($newdocid) {
            $f['id'] = $newdocid;
        }

        $fields = [];
        foreach ($f as $k => $v) {
            if ($this->isTv($k)) {
                $fields['tv'][$k] = $v;
                continue;
            }
            $fields['doc'][$k] = $v;
        }
        $id = db()->insert(db()->escape($fields['doc']), '[+prefix+]site_content');
        if (!$id) {
            return false;
        }
        if (preg_match('@^[1-9][0-9]*$@', array_get($fields, 'doc.parent', 0))) {
            db()->update(
                ['isfolder' => 1],
                '[+prefix+]site_content',
                sprintf("id='%s'", array_get($fields, 'doc.parent')));
        }

        if (!empty($fields['tv'])) {
            foreach ($fields['tv'] as $k => $v) {
                $this->saveTV($id, $k, $v);
            }
        }

        if ($groups) {
            foreach ($groups as $group) {
                db()->insert(
                    ['document_group' => $group, 'document' => $id],
                    '[+prefix+]document_groups'
                );
            }
        }
        evo()->clearCache();
        return $id;
    }

    public function update($f = [], $docid = 0, $where = '')
    {
        if (!preg_match('@^[1-9][0-9]*$@', $docid)) {
            return false;
        }
        if (is_string($f) && str_contains($f, '=')) {
            [$k, $v] = explode('=', $f, 2);
            $f = [
                trim($k) => trim($v)
            ];
        }
        $f['id'] = $docid;
        $f = $this->correctResourceFields($f, 'update');

        $fields = [];
        foreach ($f as $k => $v) {
            if ($this->isTv($k)) {
                $fields['tv'][$k] = $v;
                continue;
            }
            $fields['doc'][$k] = $v;
        }
        // $f = $this->setPubStatus($f);

        if (!empty($fields['doc'])) {
            $rs = db()->update(
                db()->escape(
                    $fields['doc']
                ),
                '[+prefix+]site_content',
                sprintf("%s `id`='%d'", $where, $docid)
            );
        }
        if (!empty($fields['tv'])) {
            foreach ($fields['tv'] as $k => $v) {
                $this->saveTV($docid, $k, $v);
            }
        }
        evo()->clearCache();
        return $rs;
    }

    public function delete($id = 0, $where = '')
    {
        if (!preg_match('@^[0-9]+$@', $id)) {
            return;
        }
        if (empty($id)) {
            if (evo()->documentIdentifier) {
                $id = evo()->documentIdentifier;
            } else {
                return;
            }
        }

        $f['deleted'] = '1';
        $f['published'] = '0';
        $f['publishedon'] = '';
        $f['pub_date'] = '';
        $f['unpub_date'] = '';

        db()->update($f, '[+prefix+]site_content', "id='" . $id . "'");
    }

    public function setPubStatus($f)
    {
        if (!isset($f['pub_date']) || empty($f['pub_date'])) {
            $f['pub_date'] = 0;
        } else {
            $f['pub_date'] = evo()->toTimeStamp($f['pub_date']);
            if ($f['pub_date'] < request_time()) {
                $f['published'] = 1;
            } elseif ($f['pub_date'] > request_time()) {
                $f['published'] = 0;
            }
        }

        if (empty($f['unpub_date'])) {
            $f['unpub_date'] = 0;
            return $f;
        }

        $f['unpub_date'] = evo()->toTimeStamp($f['unpub_date']);
        if ($f['unpub_date'] < request_time()) {
            $f['published'] = 0;
        }
        return $f;
    }

    // type : 'create' or 'update'
    private function correctResourceFields($fields,$type='create')
    {
        $keys = array_keys($fields);
        foreach ($keys as $k) {
            if (!evo()->get_docfield_type($k)) {
                unset($fields[$k]);
            }
        }

        $fields['editedon'] = request_time();

        if (empty($fields['editedby']) && $editedBy = evo()->getLoginUserID()) {
            $fields['editedby'] = $editedBy;
        }

        if ($type == 'create') {
            return $this->_fieldsForCreate($fields);
        }

        return $this->fieldsForUpdate($fields);
    }

    private function _fieldsForCreate($fields)
    {
        if (!isset($fields['published'])) {
            $fields['published'] = config('publish_default', 0);
        }
        if (array_get($fields, 'pagetitle', '') === '') {
            $fields['pagetitle'] = lang('untitled_resource');
        }
        if (!isset($fields['createdon'])) {
            $fields['createdon'] = request_time();
        }
        if (!isset($fields['createdby'])) {
            $fields['createdby'] = evo()->getLoginUserID();
            if( $fields['createdby'] === false ) {
                $fields['createdby'] = 0;
            }
        }

        if (empty($fields['publishedon']) && !empty($fields['published'])) {
            $fields['publishedon'] = $fields['editedon'];
        }
        if (empty($fields['template'])) {
            $fields['template'] = config('default_template', 0);
        }

        return $fields;
    }

    private function fieldsForUpdate($fields)
    {
        if (isset($fields['pagetitle']) && $fields['pagetitle'] === '') {
            $fields['pagetitle'] = lang('untitled_resource');
        }

        if (!empty($fields['published'])) {
            $fields['publishedon'] = request_time();
            if (empty($fields['publishedby']) && $fields['editedby']) {
                $fields['publishedby'] = $fields['editedby'];
            }
        }

        return $fields;
    }

    public function saveTV($doc_id, $name, $value)
    {
        static $tv = [];
        if (!isset($tv[$doc_id][$name])) {
            $rs = db()->select(
                [
                    'doc_id' => 'doc.id',
                    'tv_name' => 'var.name',
                    'tv_id' => 'tt.tmplvarid',
                    'template_id' => 'doc.template'
                ],
                [
                    '[+prefix+]site_content doc',
                    'left join [+prefix+]site_tmplvar_templates tt on tt.templateid=doc.template',
                    'left join [+prefix+]site_tmplvars var on var.id=tt.tmplvarid'
                ],
                sprintf("doc.id='%s' and tt.tmplvarid is not null", $doc_id)
            );
            while ($row = db()->getRow($rs)) {
                $tv[$row['doc_id']][$row['tv_name']] = $row;
            }
        }
        if (!isset($tv[$doc_id][$name])) {
            return;
        }
        if ($this->hasTmplvar($tv[$doc_id][$name]['tv_id'], $doc_id)) {
            db()->update(
                [
                    'value' => db()->escape($value)
                ],
                '[+prefix+]site_tmplvar_contentvalues',
                sprintf(
                    "tmplvarid='%s' AND contentid='%s'",
                    $tv[$doc_id][$name]['tv_id'],
                    $doc_id
                )
            );
            return;
        }
        db()->insert(
            [
                'tmplvarid' => $tv[$doc_id][$name]['tv_id'],
                'contentid' => $doc_id,
                'value' => db()->escape($value)
            ],
            '[+prefix+]site_tmplvar_contentvalues'
        );
    }

    private function tmplVars($template_id)
    {
        static $tmplvars = null;
        if ($tmplvars !== null) {
            return $tmplvars;
        }
        $rs = db()->select('id,name', '[+prefix+]site_tmplvars');
        $tmplvars = [];
        while ($row = db()->getRow($rs)) {
            if (!$this->hasTmplvarRelation($row['id'], $template_id)) {
                continue;
            }
            $tmplvars[$row['name']] = $row['id'];
            $tmplvars['tv' . $row['id']] = $row['id'];
        }
        return $tmplvars;
    }

    private function hasTmplvar($tmplvarid, $doc_id)
    {
        $rs = db()->select(
            '*',
            '[+prefix+]site_tmplvar_contentvalues',
            sprintf(
                "tmplvarid='%s' AND contentid='%s'",
                $tmplvarid,
                $doc_id
            )
        );
        return db()->count($rs);
    }

    private function hasTmplvarRelation($tmplvarid, $template_id)
    {
        $rs = db()->select(
            '*',
            '[+prefix+]site_tmplvar_templates',
            sprintf(
                "tmplvarid='%s' AND templateid='%s'",
                db()->escape($tmplvarid),
                db()->escape($template_id)
            )
        );
        return db()->count($rs) == 1;
    }

    private function isTv($key)
    {
        static $tv = null;
        if (isset($tv[$key])) {
            return $tv[$key];
        }
        if ($tv !== null) {
            return false;
        }
        $tv = [];
        $rs = db()->select('id,name', '[+prefix+]site_tmplvars');
        while ($row = db()->getRow($rs)) {
            $tv[$row['name']] = $row['id'];
        }
        if (!isset($tv[$key])) {
            return false;
        }
        return $tv[$key];
    }

    public function initValue($form_v)
    {
        $fields = explode(
            ',',
            'id,ta,alias,type,contentType,pagetitle,longtitle,description,link_attributes,isfolder,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,richtext,content_dispo,donthit,menutitle,hidemenu,introtext'
        );
        if (isset($form_v['ta'])) {
            $form_v['content'] = $form_v['ta'];
            unset($form_v['ta']);
        }
        foreach ($fields as $key) {
            if (!isset($form_v[$key])) {
                $form_v[$key] = '';
            }
            $value = trim($form_v[$key]);
            switch ($key) {
                case 'id': // auto_increment
                case 'parent':
                case 'template':
                case 'menuindex':
                case 'publishedon':
                case 'publishedby':
                case 'content_dispo':
                    if (!preg_match('@^[0-9]+$@', $value)) {
                        $value = 0;
                    }
                    break;
                case 'published':
                case 'isfolder':
                case 'donthit':
                case 'hidemenu':
                case 'richtext':
                    if (!preg_match('@^[01]$@', $value)) {
                        $value = 0;
                    }
                    break;
                case 'searchable':
                case 'cacheable':
                    if (!preg_match('@^[01]$@', $value)) {
                        $value = 1;
                    }
                    break;
                case 'pub_date':
                case 'unpub_date':
                    if ($value === '') {
                        $value = 0;
                    } else {
                        $value = evo()->toTimeStamp($value);
                    }
                    break;
                case 'editedon':
                    $value = request_time();
                    break;
                case 'editedby':
                    if (empty($value)) {
                        $value = evo()->getLoginUserID('mgr');
                    }
                    break;
                case 'type':
                    if ($value === '') {
                        $value = 'document';
                    }
                    break;
                case 'contentType':
                    if ($value === '') {
                        $value = 'text/html';
                    }
                    break;
                case 'longtitle':
                case 'description':
                case 'link_attributes':
                case 'introtext':
                case 'menutitle':
                case 'pagetitle':
                case 'content':
                case 'alias':
                    break;
            }
            $form_v[$key] = $value;
        }
        return $form_v;
    }

    public function setValue($form_v)
    {
        global $_lang;

        $form_v['alias'] = get_alias(
            array_get($form_v, 'id'),
            array_get($form_v, 'alias'),
            array_get($form_v, 'parent'),
            array_get($form_v, 'pagetitle')
        );
        if ($form_v['type'] !== 'reference' && $form_v['contentType'] !== 'text/html') {
            $form_v['richtext'] = 0;
        }

        $pos = strrpos($form_v['alias'], '.');
        if ($pos !== false && $form_v['contentType'] === 'text/html') {
            $ext = substr($form_v['alias'], $pos);
            if ($ext === '.xml') {
                $form_v['contentType'] = 'text/xml';
            } elseif ($ext === '.rss') {
                $form_v['contentType'] = 'application/rss+xml';
            } elseif ($ext === '.css') {
                $form_v['contentType'] = 'text/css';
            } elseif ($ext === '.js') {
                $form_v['contentType'] = 'text/javascript';
            } elseif ($ext === '.txt') {
                $form_v['contentType'] = 'text/plain';
            }
        }

        if ($form_v['type'] === 'reference') {
            if (strpos($form_v['content'], "\n") !== false || strpos($form_v['content'], '<') !== false) {
                $form_v['content'] = '';
            }
        }

        if ($form_v['pagetitle'] === '') {
            if ($form_v['type'] === 'reference') {
                $form_v['pagetitle'] = $_lang['untitled_weblink'];
            } else {
                $form_v['pagetitle'] = $_lang['untitled_resource'];
            }
        }

        if (substr($form_v['alias'], -1) === '/') {
            $form_v['alias'] = trim($form_v['alias'], '/');
            $form_v['isfolder'] = 1;
            $form_v['alias'] = evo()->stripAlias($form_v['alias']);
        }

        if (!empty($form_v['pub_date'])) {
            $form_v['pub_date'] = evo()->toTimeStamp($form_v['pub_date']);
            if (!$form_v['pub_date']) {
                evo()->manager->saveFormValues(postv('mode'));
                if (array_get($form_v, 'id')) {
                    evo()->webAlertAndQuit(
                        $_lang['mgrlog_dateinvalid'],
                        "index.php?a=" . postv('mode') . "&id=" . array_get($form_v, 'id')
                    );
                    exit;
                }
                evo()->webAlertAndQuit(
                    $_lang['mgrlog_dateinvalid'],
                    "index.php?a=" . postv('mode')
                );
                exit;
            }
            if ($form_v['pub_date'] < request_time()) {
                $form_v['published'] = 1;
            } elseif ($form_v['pub_date'] > request_time()) {
                $form_v['published'] = 0;
            }
        }

        if (!empty($form_v['unpub_date'])) {
            $form_v['unpub_date'] = evo()->toTimeStamp($form_v['unpub_date']);
            if (!$form_v['unpub_date']) {
                evo()->manager->saveFormValues(postv('mode'));
                $url = "index.php?a=" . postv('mode');
                if (array_get($form_v, 'id')) {
                    $url .= "&id=" . array_get($form_v, 'id');
                }
                evo()->webAlertAndQuit($_lang['mgrlog_dateinvalid'], $url);
            } elseif ($form_v['unpub_date'] < request_time()) {
                $form_v['published'] = 0;
            }
        }

        // deny publishing if not permitted
        if (array_get('mode') != 27) {
            return $form_v;
        }

        if (!evo()->hasPermission('publish_document')) {
            $form_v['pub_date'] = 0;
            $form_v['unpub_date'] = 0;
            $form_v['published'] = 0;
        }
        $form_v['publishedon'] = $form_v['published'] ? request_time() : 0;
        $form_v['publishedby'] = $form_v['published'] ? evo()->getLoginUserID() : 0;

        $form_v['createdby'] = evo()->getLoginUserID();
        $form_v['createdon'] = request_time();
        return $form_v;
    }

    public function getNewDocID()
    {
        if (evo()->config['docid_incrmnt_method'] == 1) {
            $rs = db()->select(
                'MIN(T0.id)+1',
                '[+prefix+]site_content AS T0 LEFT JOIN [+prefix+]site_content AS T1 ON T0.id + 1 = T1.id',
                'T1.id IS NULL'
            );
            return db()->getValue($rs);
        }

        if (evo()->config['docid_incrmnt_method'] == 2) {
            $rs = db()->select(
                'MAX(id)+1',
                '[+prefix+]site_content'
            );
            return db()->getValue($rs);
        }
        return false;
    }

    public function fixPubStatus($f) // published, pub_date, unpub_date
    {
        if (isset($f['pub_date']) && !empty($f['pub_date'])) {
            $f['pub_date'] = evo()->toTimeStamp($f['pub_date']);

            if ($f['pub_date'] < request_time()) {
                $f['published'] = 1;
            } else {
                $f['published'] = 0;
            }
        } else {
            $f['pub_date'] = 0;
        }

        if (!isset($f['unpub_date']) || empty($f['unpub_date'])) {
            $f['unpub_date'] = 0;
            return $f;
        }

        $f['unpub_date'] = evo()->toTimeStamp($f['unpub_date']);
        if ($f['unpub_date'] < request_time()) {
            $f['published'] = 0;
            return $f;
        }

        $f['published'] = 1;
        return $f;
    }

    public function fixTvNest($form_v)
    {
        if (isset($form_v['ta'])) {
            $form_v['content'] = $form_v['ta'];
            unset($form_v['ta']);
        }
        foreach ($form_v as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $form_v[$k] = str_replace(
                '[*' . $k . '*]',
                '[ *' . $k . '* ]',
                $v
            );
        }
        return $form_v;
    }

    public function canSaveDoc()
    {
        return evo()->hasPermission('save_document');
    }

    public function canPublishDoc()
    {
        if (evo()->hasPermission('new_document')) {
            return 1;
        }

        if (!evo()->documentObject['published']) {
            return 1;
        }

        return 0;
    }

    public function canSaveDraft()
    {
        return 1;
    }

    public function canMoveDoc()
    {
        return evo()->hasPermission('save_document');
    }

    public function canCopyDoc()
    {
        return (evo()->hasPermission('new_document') && evo()->hasPermission('save_document'));
    }

    public function canDeleteDoc()
    {
        return (evo()->hasPermission('save_document') && evo()->hasPermission('delete_document'));
    }

    public function canCreateDoc()
    {
        return evo()->hasPermission('new_document');
    }

    public function canEditDoc()
    {
        return evo()->hasPermission('edit_document');
    }

    public function existsDoc($id = 0)
    {
        $rs = db()->select('id', '[+prefix+]site_content', where('id', $id));
        return db()->count($rs) != 0;
    }
}

