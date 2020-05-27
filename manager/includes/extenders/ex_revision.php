<?php
$this->revision = new REVISION;

class REVISION {
    public $hasDraft;
    
    function __construct() {
        if(!defined('MODX_BASE_PATH')) {
            exit('undefined MODX_BASE_PATH');
        }
    }
    
    function getRevision($elmid) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s'", $elmid)
        );
        while($row = db()->getRow($rs)) {
            if($row['version']==='inherit') {
                $rev[$row['version']] = unserialize($row['content']);
            } else {
                $rev[$row['status']] = unserialize($row['content']);
            }
        }
        return $rev;
    }
    
    function getRevisionObject($elmid,$elm='resource',$addContent='') {
        $rs = $this->_setStatus($elmid,$elm);
        if(!$rs) {
            return false;
        }
        if( $addContent && !is_array($addContent) ){
            $addContent = explode(',',$addContent);
        }
        if( is_array($addContent) ){
            $tmp = array();
            foreach( $addContent as $val ){
                $tmp[] = trim($val);
            }
            $addContent = $tmp;
        }
        
        $rs = db()->select(
            '*'
            ,'[+prefix+]site_revision'
            , sprintf("elmid='%s' AND element='%s'", $elmid, $elm)
        );
        $obj = array();
        while($row = db()->getRow($rs)) {
            foreach($row as $k=>$v) {
                if($k !== 'content') {
                    $obj[$k] = $v;
                    continue;
                }
                if (!$addContent) {
                    continue;
                }
                $tmp = unserialize($v);
                foreach ($addContent as $k2) {
                    if (!isset($tmp[$k2])) {
                        continue;
                    }
                    $obj[$k2] = $tmp[$k2];
                }
            }
        }
        return $obj;
    }
    
    function _setStatus($elmid, $elm='resource') {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s' AND element='%s'", $elmid, $elm));
        if(!$rs) {
            return false;
        }
        
        $this->hasDraft     = 0;
        $this->hasInherit   = 0;
        $this->hasPending   = 0;
        $this->hasAutoDraft = 0;
        $this->hasStandby   = 0;
        $this->hasPrivate   = 0;
        while($row = db()->getRow($rs)) {
            if ($row['status'] === 'draft') {
                $this->hasDraft = 1;
            } elseif ($row['status'] === 'inherit') {
                $this->hasInherit = 1;
            } elseif ($row['status'] === 'pending') {
                $this->hasPending = 1;
            } elseif ($row['status'] === 'auto-draft') {
                $this->hasAutoDraft = 1;
            } elseif ($row['status'] === 'standby') {
                $this->hasStandby = 1;
            } elseif ($row['status'] === 'private') {
                $this->hasPrivate = 1;
            }
        }
        return true;
    }
    
    function getDraft($elmid) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s' AND version='0'", $elmid)
        );
        $row = db()->getRow($rs);
        $data = array();
        if(isset($row['content'])) {
            $data = unserialize($row['content']);
        }
        if(!$data) {
            return array();
        }

        $data = $data + $this->getCurrentResource($elmid);
        return $data;
    }
    
    function getRevisionStatus($elmid) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf(
                "elmid='%s' AND version='0'"
                , $elmid
            )
        );
        $row = db()->getRow($rs);
        if(array_get($row, 'status')) {
            return $row['status'];
        }
        return 'nodraft';
    }
    
    function getFormFromDraft($id) {
        $data = $this->getDraft($id);
        $resource  = $this->getCurrentResource($id);
        $data = $data + $resource;
        $form = array();
        foreach($data as $k=>$v) {
            $form[] = evo()->parseText(
                '<input type="hidden" name="[+name+]" value="[+value+]" />'
                , array(
                    'name'  => $k,
                    'value' => hsc($v)
                )
            );
        }
        return implode("\n", $form);
    }
    
    function getCurrentResource($docid) {
        $rs = evo()->getTemplateVars('*', '*', $docid);
        if(empty($rs)) {
            return array();
        }
        foreach($rs as $i=>$v) {
            if(isset($v['id'])) $name = 'tv' . $v['id'];
            else                $name = $v['name'];
            $doc[$name] = $v['value'];
        }
        
        $doc = $this->convertData($doc);
        if(!$doc) {
            return false;
        }
        return $doc;
    }

    function convertData($resource=array()) {
        $input = array(
            'content' => array_get(
                $resource
                , 'content'
                , array_get($resource, 'ta', '')
            ),
            'pagetitle'       => array_get($resource, 'pagetitle', ''),
            'longtitle'       => array_get($resource, 'longtitle', ''),
            'menutitle'       => array_get($resource, 'menutitle', ''),
            'description'     => array_get($resource, 'description', ''),
            'introtext'       => array_get($resource, 'introtext', ''),
            'type'            => array_get($resource, 'type', 'document'),
            'alias'           => array_get($resource, 'alias', ''),
            'link_attributes' => array_get($resource, 'link_attributes', ''),
            'isfolder'        => array_get($resource, 'isfolder', 0),
            'richtext'        => array_get($resource, 'richtext', 1),
            'parent'          => array_get($resource, 'parent', 0),
            'template'        => array_get($resource, 'template', 0),
            'menuindex'       => array_get($resource, 'menuindex', 0),
            'searchable'      => array_get($resource, 'searchable', 1),
            'cacheable'       => array_get($resource, 'cacheable', 1),
            'contentType'     => array_get($resource, 'contentType', 'text/html'),
            'content_dispo'   => array_get($resource, 'content_dispo', ''),
            'hidemenu'        => array_get($resource, 'hidemenu', ''),
            'pub_date'        => array_get($resource, 'pub_date', 0),
            'unpub_date'      => array_get($resource, 'unpub_date', 0),
            'published' => array_get(
                $resource
                , 'published'
                , evo()->config(
                    'publish_default'
                    ,0
                )
            ),
        );
        foreach($resource as $k=>$v) {
            if(strpos($k, 'tv') !== 0) {
                continue;
            }
            if (!isset($resource[$k . '_prefix'])) {
                $input[$k] = $v;
                continue;
            }
            if ($resource[$k . '_prefix'] === 'DocID') {
                //tvがリンクの時の例外処理
                $input[$k] = '[~' . $v . '~]';
                continue;
            }
            $input[$k] = $resource[$k . '_prefix'] . $v;
        }
        return $input;
    }
    
    function save($elmid='',$resource=array(), $status='inherit') {
        if(!$elmid) {
            return '';
        }
        
        $input = $this->convertData($resource);
        $input['status'] = $status;
        
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s'", $elmid)
            , 'version DESC'
        );
        $exists_data = db()->getRow($rs);
        $total = db()->getRecordCount($rs);
        
        $revision_content = serialize($input);
        $revision_content = db()->escape($revision_content);
        $checksum = hash('crc32b', $revision_content);
        if($total && $exists_data['checksum'] === $checksum && $exists_data['status'] == $status) {
            return 'nochange';
        }
        $f = array(
            'elmid'    => $elmid,
            'status'   => $status,
            'content'  => $revision_content,
            'element'  => 'resource',
            'editedon' => serverv('REQUEST_TIME'),
            'editedby' => evo()->getLoginUserID(),
            'checksum' => $checksum,
            'version'  => ($status === 'inherit') ? $total + 1 : 0
        );

        if ($total) {
            db()->update($f, '[+prefix+]site_revision', "elmid='{$elmid}'");
        } else {
            db()->insert($f, '[+prefix+]site_revision');
        }
        return $total ? 'upd' : 'new';
    }
    
    function delete($elmid='', $status='*') {
        if(!$elmid) {
            return 0;
        }
        return db()->delete(
            '[+prefix+]site_revision'
            , $status === '*'
                ?
                sprintf("elmid='%s'", $elmid)
                :
                sprintf("elmid='%s' AND status='%s'", $elmid, $status)
        );
    }

    /*
    *  公開予定から下書きに変更
    *  (複数公開/複数下書きの仕様は未考慮)
    */
    function chStandbytoDraft($elmid, $type='resource') {
        if(!$elmid) {
            return false;
        }
        return db()->update(
            array('status' => 'draft')
            , '[+prefix+]site_revision'
            , sprintf(
                "element='%s' AND elmid='%s'"
                , db()->escape($type)
                , $elmid
            )
        );
    }
    
    function convertTvid2Tvname($input) {
        $rs = db()->select('id,name','[+prefix+]site_tmplvars');
        while($row = db()->getRow($rs)) {
            $tvid = 'tv' . $row['id'];
            $tvname[$tvid] = $row['name'];
        }
        
        foreach($input as $k=>$v) {
            if(isset($tvname[$k])) {
                unset($input[$k]);
                $k = $tvname[$k];
                $input[$k] = $v;
            } elseif($k==='ta') {
                $input['content'] = $v;
                unset($input['ta']);
            }
        }
        return $input;
    }

    function publishDraft($fields) {
        evo()->loadExtension('DocAPI');
        
        $fields = evo()->doc->fixTvNest($fields);
        $fields = evo()->doc->fixPubStatus($fields);
        
        if(severv('REQUEST_TIME') < array_get($fields, 'pub_date', 0)) {
            $this->save($fields['id'], $fields, 'standby');
            db()->update(
                array('pub_date' => array_get($fields, 'pub_date', 0))
                , '[+prefix+]site_revision'
                , sprintf("elmid='%s'", $fields['id'])
            );
            evo()->setCacheRefreshTime(array_get($fields, 'pub_date', 0));
            return 'standby';
        }
        evo()->doc->update(
            db()->escape($fields)
            , $fields['id']
        );
        $this->delete($fields['id'], 'draft');
        return 'published';
    }
}
