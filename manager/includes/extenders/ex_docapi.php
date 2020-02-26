<?php

$this->doc= new DocAPI;

class DocAPI {

    var $mode;

    function __construct()
    {
    }

    function create($f = array(), $groups = array())
    {
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
        $f['editedon']  = $f['createdon'];
        $f['editedby']  = $f['createdby'];
        if(isset($f['published']) && $f['published']==1 && !isset($f['publishedon'])) {
            $f['publishedon'] = $f['createdon'];
        }
        if(!$f['template']) {
            $f['template'] = $modx->config['default_template'];
        }
        if ($groups) {
            $f['privatemgr'] = 1;
        }

//		$f = $this->setPubStatus($f);

        $newdocid = $this->getNewDocID();
        if($newdocid) {
            $f['id'] = $newdocid;
        }

        $id = $modx->db->insert($f, '[+prefix+]site_content');
        $this->saveTVs($f,$id);
        if(isset($f['parent']) && preg_match('@^[1-9][0-9]*$@',$f['parent'])) {
            $parent = $f['parent'];
            $modx->db->update(
                array('isfolder'=>'1')
                , '[+prefix+]site_content'
                , sprintf("id='%s'", $parent));
        }

        if ($groups && $id) {
            foreach ($groups as $group) {
                $modx->db->insert(
                    array('document_group' => $group, 'document' => $id)
                    , '[+prefix+]document_groups'
                );
            }
        }
        if($id!==false) {
            $modx->clearCache();
        }
        return $id;
    }

    function update($f = array(), $id = 0, $where = '')
    {
        global $modx;

        if(!$id) {
            if(!isset($modx->documentIdentifier)) {
                return false;
            }
            $id = $modx->documentIdentifier;
        }
        if(!preg_match('@^[1-9][0-9]*$@', $id)) {
            return false;
        }

        if(is_string($f) && strpos($f,'=')!==false) {
            list($k,$v) = explode('=',$f,2);
            $k = trim($k);
            $v = trim($v);
            $f = array();
            $f[$k] = $v;
        }

        if(!$f['template']) {
            $f['template'] = $modx->getField('template', $id);
        }

        $this->saveTVs($f,$id);

//		$f = $this->setPubStatus($f);

        $f['editedon'] = (!$f['editedon']) ? time() : $f['editedon'];
        if(!isset($f['editedby']) && isset($_SESSION['mgrInternalKey'])) {
            $f['editedby'] = $_SESSION['mgrInternalKey'];
        }

        $f = $this->correctResourceFields($f);
        $f = $modx->db->escape($f);
        $rs = $modx->db->update($f, '[+prefix+]site_content', $where . " `id`='{$id}'");
        if($rs!==false) {
            $modx->clearCache();
        }
        return $rs;
    }

    function delete($id = 0, $where = '')
    {
        global $modx;

        if(!preg_match('@^[0-9]+$@', $id)) return;
        if(empty($id))
        {
            if(isset($modx->documentIdentifier)) $id = $modx->documentIdentifier;
            else return;
        }

        $f['deleted']     = '1';
        $f['published']   = '0';
        $f['publishedon'] = '';
        $f['pub_date']    = '';
        $f['unpub_date']  = '';

        $modx->db->update($f, '[+prefix+]site_content', "id='{$id}'");
    }

    function setPubStatus($f)
    {
        global $modx;

        $currentdate = time();

        if(!isset($f['pub_date']) || empty($f['pub_date'])) $f['pub_date'] = 0;
        else
        {
            $f['pub_date'] = $modx->toTimeStamp($f['pub_date']);
            if($f['pub_date'] < $currentdate) $f['published'] = 1;
            elseif($f['pub_date'] > $currentdate) $f['published'] = 0;
        }

        if(empty($f['unpub_date'])) $f['unpub_date'] = 0;
        else
        {
            $f['unpub_date'] = $modx->toTimeStamp($f['unpub_date']);
            if($f['unpub_date'] < $currentdate) $f['published'] = 0;
        }
        return $f;
    }

    function correctResourceFields($fields)
    {
        global $modx;
        foreach($fields as $k=>$v) {
            if(!$modx->get_docfield_type($k)) {
                unset($fields[$k]);
            }
        }
        return $fields;
    }

    function saveTVs($inputFields=array(), $doc_id)
    {
        global $modx;
        $tmplvars = $this->tmplVars($inputFields['template']);
        foreach($tmplvars as $name=>$tmplvarid) {
            $fields = array('value' => $modx->db->escape($inputFields[$name]));
            if ($this->hasTmplvar($tmplvarid, $doc_id)) {
                $modx->db->update(
                    $fields
                    , '[+prefix+]site_tmplvar_contentvalues'
                    , sprintf(
                        "tmplvarid='%s' AND contentid='%s'"
                        , $tmplvarid
                        , $doc_id
                    )
                );
            } else {
                $fields['tmplvarid'] = $tmplvarid;
                $fields['contentid'] = $doc_id;
                $modx->db->insert($fields, '[+prefix+]site_tmplvar_contentvalues');
            }
        }
    }

    private function tmplVars($template_id) {
        global $modx;
        $rs = $modx->db->select('id,name', '[+prefix+]site_tmplvars');
        $tmplvars = array();
        while($row = $modx->db->getRow($rs)) {
            if(!$this->hasTmplvarRelation($row['id'],$template_id)) {
                continue;
            }
            $tmplvars[$row['name']]    = $row['id'];
            $tmplvars['tv'.$row['id']] = $row['id'];
        }
        return $tmplvars;
    }

    private function hasTmplvar($tmplvarid,$doc_id)
    {
        global $modx;
        $rs = $modx->db->select(
            '*'
            , '[+prefix+]site_tmplvar_contentvalues'
            , sprintf(
                "tmplvarid='%s' AND contentid='%s'"
                , $tmplvarid
                , $doc_id
            )
        );
        return $modx->db->getRecordCount($rs);
    }

    private function hasTmplvarRelation($tmplvarid,$template_id) {
        global $modx;
        $rs = $modx->db->select(
            '*'
            , '[+prefix+]site_tmplvar_templates'
            , sprintf(
                "tmplvarid='%s' AND templateid='%s'"
                , $modx->db->escape($tmplvarid)
                , $modx->db->escape($template_id)
            )
        );
        return $modx->db->getRecordCount($rs) == 1;
    }
    function initValue($form_v)
    {
        global $modx;

        $fields = explode(
            ','
            , 'id,ta,alias,type,contentType,pagetitle,longtitle,description,link_attributes,isfolder,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,richtext,content_dispo,donthit,menutitle,hidemenu,introtext'
        );
        if(isset($form_v['ta'])) {
            $form_v['content'] = $form_v['ta'];
            unset($form_v['ta']);
        }
        foreach($fields as $key) {
            if(!isset($form_v[$key])) {
                $form_v[$key] = '';
            }
            $value = trim($form_v[$key]);
            switch($key) {
                case 'id': // auto_increment
                case 'parent':
                case 'template':
                case 'menuindex':
                case 'publishedon':
                case 'publishedby':
                case 'content_dispo':
                    if(!preg_match('@^[0-9]+$@',$value))
                        $value = 0;
                    break;
                case 'published':
                case 'isfolder':
                case 'donthit':
                case 'hidemenu':
                case 'richtext':
                    if(!preg_match('@^[01]$@',$value))
                        $value = 0;
                    break;
                case 'searchable':
                case 'cacheable':
                    if(!preg_match('@^[01]$@',$value))
                        $value = 1;
                    break;
                case 'pub_date':
                case 'unpub_date':
                    if($value==='') $value = 0;
                    else $value = $modx->toTimeStamp($value);
                    break;
                case 'editedon':
                    $value = $_SERVER['REQUEST_TIME'];
                    break;
                case 'editedby':
                    if(empty($value)) $value = $modx->getLoginUserID('mgr');
                    break;
                case 'type':
                    if($value==='') $value = 'document';
                    break;
                case 'contentType':
                    if($value==='') $value = 'text/html';
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
        global $modx,$_lang;
        $mode = $_POST['mode'];

        $form_v['alias'] = get_alias(
            $modx->array_get($form_v, 'id')
            ,$modx->array_get($form_v, 'alias')
            ,$modx->array_get($form_v, 'parent')
            ,$modx->array_get($form_v, 'pagetitle')
            );
        if($form_v['type']!=='reference' && $form_v['contentType'] !== 'text/html')
            $form_v['richtext'] = 0;

        $pos = strrpos($form_v['alias'],'.');
        if($pos!==false && $form_v['contentType'] === 'text/html')
        {
            $ext = substr($form_v['alias'],$pos);
            if    ($ext==='.xml') $form_v['contentType'] = 'text/xml';
            elseif($ext==='.rss') $form_v['contentType'] = 'application/rss+xml';
            elseif($ext==='.css') $form_v['contentType'] = 'text/css';
            elseif($ext==='.js')  $form_v['contentType'] = 'text/javascript';
            elseif($ext==='.txt') $form_v['contentType'] = 'text/plain';
        }

        if($form_v['type']==='reference') {
            if(strpos($form_v['content'],"\n")!==false||strpos($form_v['content'],'<')!==false)
                $form_v['content'] = '';
        }

        if($form_v['pagetitle']==='') {
            $form_v['pagetitle'] =  ($form_v['type'] === 'reference') ? $_lang['untitled_weblink'] :  $_lang['untitled_resource'];
        }

        if(substr($form_v['alias'],-1)==='/') {
            $form_v['alias'] = trim($form_v['alias'],'/');
            $form_v['isfolder'] = 1;
            $form_v['alias'] = $modx->stripAlias($form_v['alias']);
        }

        if(!empty($form_v['pub_date'])) {
            $form_v['pub_date'] = $modx->toTimeStamp($form_v['pub_date']);
            if(empty($form_v['pub_date']))
            {
                $modx->manager->saveFormValues($mode);
                $url = "index.php?a={$mode}";
                if($modx->array_get($form_v, 'id')) {
                    $url .= "&id={$modx->array_get($form_v, 'id')}";
                }
                $modx->webAlertAndQuit($_lang['mgrlog_dateinvalid'],$url);
            }
            elseif($form_v['pub_date'] < $_SERVER['REQUEST_TIME']) $form_v['published'] = 1;
            elseif($form_v['pub_date'] > $_SERVER['REQUEST_TIME']) $form_v['published'] = 0;
        }

        if(!empty($form_v['unpub_date'])) {
            $form_v['unpub_date'] = $modx->toTimeStamp($form_v['unpub_date']);
            if(empty($form_v['unpub_date']))
            {
                $modx->manager->saveFormValues($mode);
                $url = "index.php?a={$mode}";
                if($modx->array_get($form_v, 'id')) {
                    $url .= "&id={$modx->array_get($form_v, 'id')}";
                }
                $modx->webAlertAndQuit($_lang['mgrlog_dateinvalid'],$url);
            }
            elseif($form_v['unpub_date'] < $_SERVER['REQUEST_TIME']) $form_v['published'] = 0;
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

    function getNewDocID()
    {
        global $modx;

        if($modx->config['docid_incrmnt_method']==1) {
            $rs = $modx->db->select(
                'MIN(T0.id)+1'
                , '[+prefix+]site_content AS T0 LEFT JOIN [+prefix+]site_content AS T1 ON T0.id + 1 = T1.id'
                , 'T1.id IS NULL'
            );
            return $modx->db->getValue($rs);
        }

        if($modx->config['docid_incrmnt_method']==2) {
            $rs = $modx->db->select(
                'MAX(id)+1'
                , '[+prefix+]site_content'
            );
            return $modx->db->getValue($rs);
        }
        return false;
    }

    function fixPubStatus($f) // published, pub_date, unpub_date
    {
        global $modx;

        $currentdate = time();

        if(isset($f['pub_date']) && !empty($f['pub_date']))
        {
            $f['pub_date'] = $modx->toTimeStamp($f['pub_date']);

            if($f['pub_date'] < $currentdate) $f['published'] = 1;
            else                              $f['published'] = 0;
        }
        else $f['pub_date'] = 0;

        if(isset($f['unpub_date']) && !empty($f['unpub_date']))
        {
            $f['unpub_date'] = $modx->toTimeStamp($f['unpub_date']);

            if($f['unpub_date'] < $currentdate) $f['published'] = 0;
            else                                $f['published'] = 1;
        }
        else $f['unpub_date'] = 0;

        return $f;
    }

    function fixTvNest($form_v)
    {
        if(isset($form_v['ta'])) {
            $form_v['content'] = $form_v['ta'];
            unset($form_v['ta']);
        }
        $target = explode(
            ','
            , 'ta,introtext,pagetitle,longtitle,menutitle,description,alias,link_attributes'
        );
        foreach($target as $key) {
            if(strpos($form_v[$key], '[*' . $key . '*]')===false) {
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

    function canSaveDoc()
    {
        global $modx;

        return $modx->hasPermission('save_document');
    }

    function canPublishDoc()
    {
        global $modx;
        if($modx->hasPermission('new_document')) return 1;
        elseif(!$modx->documentObject['published']) return 1;
        else return 0;
    }

    function canSaveDraft()
    {
        global $modx;
        return 1;
    }

    function canMoveDoc()
    {
        global $modx;
        return $modx->hasPermission('save_document');
    }

    function canCopyDoc()
    {
        global $modx;
        return ($modx->hasPermission('new_document')&&$modx->hasPermission('save_document'));
    }

    function canDeleteDoc()
    {
        global $modx;
        return ($modx->hasPermission('save_document')&&$modx->hasPermission('delete_document'));
    }

    function canCreateDoc()
    {
        global $modx;
        return $modx->hasPermission('new_document');
    }

    function canEditDoc()
    {
        global $modx;
        return $modx->hasPermission('edit_document');
    }

    function existsDoc($id = 0) {
        global $modx;
        $rs = $modx->db->select('id','[+prefix+]site_content', "id='{$id}'");
        if($modx->db->getRecordCount($rs)==0) {
            return false;
        }

        return true;
    }
}

