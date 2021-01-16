<?php

$this->doc = new DocAPI;

class DocAPI {

    public $mode;

    function __construct() {
    }

    function create($f = array(), $groups = array()) {
        global $modx, $_lang;
        $f = $this->correctResourceFields($f);

        if ((!$f['pagetitle'])) {
            $f['pagetitle'] = $_lang['untitled_resource'];
        }
        if ((!$f['createdon'])) {
            $f['createdon'] = time();
        }
        if ((!$f['createdby'])) {
            $f['createdby'] = $modx->getLoginUserID();
        }
        $f['editedon'] = $f['createdon'];
        $f['editedby'] = $f['createdby'];
        if (isset($f['published']) && $f['published'] == 1 && !isset($f['publishedon'])) {
            $f['publishedon'] = $f['createdon'];
        }
        if (!$f['template']) {
            $f['template'] = $modx->config['default_template'];
        }
        if ($groups) {
            $f['privatemgr'] = 1;
        }

//		$f = $this->setPubStatus($f);

        $newdocid = $this->getNewDocID();
        if ($newdocid) {
            $f['id'] = $newdocid;
        }

        $tvs = array();
        $doc_fields = array();
        foreach ($f as $k=>$v) {
            if($this->isTv($k)) {
                $tvs[$k] = $v;
                continue;
            }
            $doc_fields[$k] = $v;
        }
        $id = db()->insert(db()->escape($doc_fields), '[+prefix+]site_content');
        if(!$id) {
            return;
        }
        if (isset($doc_fields['parent']) && preg_match('@^[1-9][0-9]*$@', $doc_fields['parent'])) {
            $parent = $doc_fields['parent'];
            db()->update(
                array('isfolder' => '1')
                , '[+prefix+]site_content'
                , sprintf("id='%s'", $parent));
        }

        if($tvs) {
            foreach($tvs as $k=>$v) {
                $this->saveTVs($id, $k, $v);
            }
        }

        if ($groups && $id) {
            foreach ($groups as $group) {
                db()->insert(
                    array('document_group' => $group, 'document' => $id)
                    , '[+prefix+]document_groups'
                );
            }
        }
        if ($id !== false) {
            $modx->clearCache();
        }
        return $id;
    }

    function update($f = array(), $docid = 0, $where = '') {
        global $modx;

        if (!$docid) {
            if (!isset($modx->documentIdentifier)) {
                return false;
            }
            $docid = $modx->documentIdentifier;
        }
        if (!preg_match('@^[1-9][0-9]*$@', $docid)) {
            return false;
        }
        if (is_string($f) && strpos($f, '=') !== false) {
            list($k, $v) = explode('=', $f, 2);
            $f = array(
                trim($k) => trim($v)
            );
        }
        $docfields = [];
        foreach ($f as $k=>$v) {
            if ($this->isTv($k)) {
                $this->saveTV($docid, $k, $v);
                continue;
            }
            $docfields[$k] = $v;
        }
        if(!$docfields) {
            return;
        }
//		$f = $this->setPubStatus($f);

        $f['editedon'] = !$f['editedon'] ? time() : $f['editedon'];
        if (!isset($f['editedby']) && sessionv('mgrInternalKey')) {
            $f['editedby'] = $_SESSION['mgrInternalKey'];
        }

        $rs = db()->update(
            db()->escape(
                $this->correctResourceFields($f)
            )
            , '[+prefix+]site_content'
            , sprintf("%s `id`='%d'", $where, $docid)
        );
        if ($rs !== false) {
            $modx->clearCache();
        }
        return $rs;
    }

    function delete($id = 0, $where = '') {
        global $modx;

        if (!preg_match('@^[0-9]+$@', $id)) {
            return;
        }
        if (empty($id)) {
            if (isset($modx->documentIdentifier)) {
                $id = $modx->documentIdentifier;
            } else {
                return;
            }
        }

        $f['deleted'] = '1';
        $f['published'] = '0';
        $f['publishedon'] = '';
        $f['pub_date'] = '';
        $f['unpub_date'] = '';

        db()->update($f, '[+prefix+]site_content', "id='{$id}'");
    }

    function setPubStatus($f) {
        global $modx;

        $currentdate = time();

        if (!isset($f['pub_date']) || empty($f['pub_date'])) {
            $f['pub_date'] = 0;
        } else {
            $f['pub_date'] = $modx->toTimeStamp($f['pub_date']);
            if ($f['pub_date'] < $currentdate) {
                $f['published'] = 1;
            } elseif ($f['pub_date'] > $currentdate) {
                $f['published'] = 0;
            }
        }

        if (empty($f['unpub_date'])) {
            $f['unpub_date'] = 0;
        } else {
            $f['unpub_date'] = $modx->toTimeStamp($f['unpub_date']);
            if ($f['unpub_date'] < $currentdate) {
                $f['published'] = 0;
            }
        }
        return $f;
    }

    function correctResourceFields($fields) {
        global $modx;
        foreach ($fields as $k => $v) {
            if (!$modx->get_docfield_type($k)) {
                unset($fields[$k]);
            }
        }
        return $fields;
    }

    function saveTV($doc_id, $name, $value) {
        static $tv = array();
        if(!isset($tv[$doc_id][$name])) {
            $rs = db()->select(
                array(
                    'doc_id' => 'doc.id',
                    'tv_name' => 'var.name',
                    'tv_id' => 'tt.tmplvarid',
                    'template_id' => 'doc.template'
                )
                , array(
                    '[+prefix+]site_content doc',
                    'left join [+prefix+]site_tmplvar_templates tt on tt.templateid=doc.id',
                    'left join [+prefix+]site_tmplvars var on var.id=tt.tmplvarid'
                )
                , sprintf("doc.id='%s' and tt.tmplvarid is not null", $doc_id)
            );
            while($row = db()->getRow($rs)) {
                $tv[$row['doc_id']][$row['tv_name']] = $row;
            }
        }
        if(!isset($tv[$doc_id][$name])) {
            return;
        }
        if ($this->hasTmplvar($tv[$doc_id][$name]['tv_id'], $doc_id)) {
            db()->update(
                array(
                    'value' => db()->escape($name)
                )
                , '[+prefix+]site_tmplvar_contentvalues'
                , sprintf(
                    "tmplvarid='%s' AND contentid='%s'"
                    , $tv[$doc_id][$name]['tv_id']
                    , $doc_id
                )
            );
        } else {
            db()->insert(
                array(
                    'tmplvarid' => $tv[$doc_id][$name]['tv_id'],
                    'contentid' => $doc_id,
                    'value' => db()->escape($name)
                )
                , '[+prefix+]site_tmplvar_contentvalues'
            );
        }
}

    function saveTVs($inputFields = array(), $doc_id) {
        $tmplvars = $this->tmplVars($inputFields['template']);
        foreach ($tmplvars as $name => $tmplvarid) {
            if ($this->hasTmplvar($tmplvarid, $doc_id)) {
                db()->update(
                    array(
                        'value' => db()->escape($inputFields[$name])
                    )
                    , '[+prefix+]site_tmplvar_contentvalues'
                    , sprintf(
                        "tmplvarid='%s' AND contentid='%s'"
                        , $tmplvarid
                        , $doc_id
                    )
                );
            } else {
                db()->insert(
                    array(
                        'tmplvarid' => $tmplvarid,
                        'contentid' => $doc_id,
                        'value' => db()->escape($inputFields[$name])
                    )
                    , '[+prefix+]site_tmplvar_contentvalues'
                );
            }
        }
    }

    private function tmplVars($template_id) {
        $rs = db()->select('id,name', '[+prefix+]site_tmplvars');
        $tmplvars = array();
        while ($row = db()->getRow($rs)) {
            if (!$this->hasTmplvarRelation($row['id'], $template_id)) {
                continue;
            }
            $tmplvars[$row['name']] = $row['id'];
            $tmplvars['tv' . $row['id']] = $row['id'];
        }
        return $tmplvars;
    }

    private function hasTmplvar($tmplvarid, $doc_id) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_tmplvar_contentvalues'
            , sprintf(
                "tmplvarid='%s' AND contentid='%s'"
                , $tmplvarid
                , $doc_id
            )
        );
        return db()->getRecordCount($rs);
    }

    private function hasTmplvarRelation($tmplvarid, $template_id) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_tmplvar_templates'
            , sprintf(
                "tmplvarid='%s' AND templateid='%s'"
                , db()->escape($tmplvarid)
                , db()->escape($template_id)
            )
        );
        return db()->getRecordCount($rs) == 1;
    }

    private function isTv($key){
        static $tv=array();
        if(isset($tv[$key])) {
            return $tv[$key];
        }
        $doc = explode(
            ','
            ,
            'id,ta,alias,type,contentType,pagetitle,longtitle,description,link_attributes,isfolder,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,richtext,content_dispo,donthit,menutitle,hidemenu,introtext'
        );
        if(isset($doc[$key])) {
            $tv[$key] = false;
            return false;
        }
        $rs = db()->select('id,name', '[+prefix+]site_tmplvars');
        while($row = db()->getRow($rs)) {
            $tv[$row['name']] = $row['id'];
        }
        if(!isset($tv[$key])) {
            $tv[$key] = false;
        }
        return $tv[$key];
    }
    
    function initValue($form_v) {
        global $modx;

        $fields = explode(
            ','
            ,
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
                        $value = $modx->toTimeStamp($value);
                    }
                    break;
                case 'editedon':
                    $value = $_SERVER['REQUEST_TIME'];
                    break;
                case 'editedby':
                    if (empty($value)) {
                        $value = $modx->getLoginUserID('mgr');
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

    function setValue($form_v) {
        global $modx, $_lang;
        $mode = $_POST['mode'];

        $form_v['alias'] = get_alias(
            $modx->array_get($form_v, 'id')
            , $modx->array_get($form_v, 'alias')
            , $modx->array_get($form_v, 'parent')
            , $modx->array_get($form_v, 'pagetitle')
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
            $form_v['pagetitle'] = ($form_v['type'] === 'reference') ? $_lang['untitled_weblink'] : $_lang['untitled_resource'];
        }

        if (substr($form_v['alias'], -1) === '/') {
            $form_v['alias'] = trim($form_v['alias'], '/');
            $form_v['isfolder'] = 1;
            $form_v['alias'] = $modx->stripAlias($form_v['alias']);
        }

        if (!empty($form_v['pub_date'])) {
            $form_v['pub_date'] = $modx->toTimeStamp($form_v['pub_date']);
            if (empty($form_v['pub_date'])) {
                $modx->manager->saveFormValues($mode);
                $url = "index.php?a={$mode}";
                if ($modx->array_get($form_v, 'id')) {
                    $url .= "&id={$modx->array_get($form_v, 'id')}";
                }
                $modx->webAlertAndQuit($_lang['mgrlog_dateinvalid'], $url);
            } elseif ($form_v['pub_date'] < $_SERVER['REQUEST_TIME']) {
                $form_v['published'] = 1;
            } elseif ($form_v['pub_date'] > $_SERVER['REQUEST_TIME']) {
                $form_v['published'] = 0;
            }
        }

        if (!empty($form_v['unpub_date'])) {
            $form_v['unpub_date'] = $modx->toTimeStamp($form_v['unpub_date']);
            if (empty($form_v['unpub_date'])) {
                $modx->manager->saveFormValues($mode);
                $url = "index.php?a={$mode}";
                if ($modx->array_get($form_v, 'id')) {
                    $url .= "&id={$modx->array_get($form_v, 'id')}";
                }
                $modx->webAlertAndQuit($_lang['mgrlog_dateinvalid'], $url);
            } elseif ($form_v['unpub_date'] < $_SERVER['REQUEST_TIME']) {
                $form_v['published'] = 0;
            }
        }

        // deny publishing if not permitted
        if ($modx->array_get('mode') != 27) {
            return $form_v;
        }

        if (!$modx->hasPermission('publish_document')) {
            $form_v['pub_date'] = 0;
            $form_v['unpub_date'] = 0;
            $form_v['published'] = 0;
        }
        $form_v['publishedon'] = $form_v['published'] ? $_SERVER['REQUEST_TIME'] : 0;
        $form_v['publishedby'] = $form_v['published'] ? $modx->getLoginUserID() : 0;

        $form_v['createdby'] = $modx->getLoginUserID();
        $form_v['createdon'] = $_SERVER['REQUEST_TIME'];
        return $form_v;
    }

    function getNewDocID() {
        global $modx;

        if ($modx->config['docid_incrmnt_method'] == 1) {
            $rs = db()->select(
                'MIN(T0.id)+1'
                , '[+prefix+]site_content AS T0 LEFT JOIN [+prefix+]site_content AS T1 ON T0.id + 1 = T1.id'
                , 'T1.id IS NULL'
            );
            return db()->getValue($rs);
        }

        if ($modx->config['docid_incrmnt_method'] == 2) {
            $rs = db()->select(
                'MAX(id)+1'
                , '[+prefix+]site_content'
            );
            return db()->getValue($rs);
        }
        return false;
    }

    function fixPubStatus($f) // published, pub_date, unpub_date
    {
        global $modx;

        $currentdate = time();

        if (isset($f['pub_date']) && !empty($f['pub_date'])) {
            $f['pub_date'] = $modx->toTimeStamp($f['pub_date']);

            if ($f['pub_date'] < $currentdate) {
                $f['published'] = 1;
            } else {
                $f['published'] = 0;
            }
        } else {
            $f['pub_date'] = 0;
        }

        if (isset($f['unpub_date']) && !empty($f['unpub_date'])) {
            $f['unpub_date'] = $modx->toTimeStamp($f['unpub_date']);

            if ($f['unpub_date'] < $currentdate) {
                $f['published'] = 0;
            } else {
                $f['published'] = 1;
            }
        } else {
            $f['unpub_date'] = 0;
        }

        return $f;
    }

    function fixTvNest($form_v) {
        if (isset($form_v['ta'])) {
            $form_v['content'] = $form_v['ta'];
            unset($form_v['ta']);
        }
        $target = explode(
            ','
            , 'ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes'
        );
        foreach ($target as $key) {
            if (strpos($form_v[$key], '[*' . $key . '*]') === false) {
                continue;
            }
            $form_v[$key] = str_replace(
                '[*' . $key . '*]'
                , '[ *' . $key . '* ]'
                , $form_v[$key]
            );
        }
        return $form_v;
    }

    function canSaveDoc() {
        global $modx;

        return $modx->hasPermission('save_document');
    }

    function canPublishDoc() {
        global $modx;
        if ($modx->hasPermission('new_document')) {
            return 1;
        } elseif (!$modx->documentObject['published']) {
            return 1;
        } else {
            return 0;
        }
    }

    function canSaveDraft() {
        return 1;
    }

    function canMoveDoc() {
        global $modx;
        return $modx->hasPermission('save_document');
    }

    function canCopyDoc() {
        global $modx;
        return ($modx->hasPermission('new_document') && $modx->hasPermission('save_document'));
    }

    function canDeleteDoc() {
        global $modx;
        return ($modx->hasPermission('save_document') && $modx->hasPermission('delete_document'));
    }

    function canCreateDoc() {
        global $modx;
        return $modx->hasPermission('new_document');
    }

    function canEditDoc() {
        global $modx;
        return $modx->hasPermission('edit_document');
    }

    function existsDoc($id = 0) {
        $rs = db()->select('id', '[+prefix+]site_content', "id='{$id}'");
        if (db()->getRecordCount($rs) == 0) {
            return false;
        }

        return true;
    }
}

