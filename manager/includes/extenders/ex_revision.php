<?php
$this->revision = new REVISION;

class REVISION {
    public $hasDraft;
    public $hasInherit;
    public $hasPending;
    public $hasAutoDraft;
    public $hasStandby;
    public $hasPrivate;

    public function __construct() {
    }

    public function getRevision($elmid) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s'", $elmid)
        );
        while ($row = db()->getRow($rs)) {
            if ($row['version'] === 'inherit') {
                $rev['inherit'] = unserialize($row['content']);
            } else {
                $rev[$row['status']] = unserialize($row['content']);
            }
        }
        return $rev;
    }

    public function getRevisionObject($elmid, $elm = 'resource', $addContent = '') {
        $rs = $this->_setStatus($elmid, $elm);
        if (!$rs) {
            return false;
        }
        if ($addContent && !is_array($addContent)) {
            $addContent = explode(',', $addContent);
        }

        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s' AND element='%s'", $elmid, $elm)
        );
        $obj = array();
        while ($row = db()->getRow($rs)) {
            foreach ($row as $k => $v) {
                if ($k !== 'content') {
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

    public function getDraft($elmid) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s' AND version='0'", $elmid)
        );
        $row = db()->getRow($rs);

        $draft = array_get($row, 'content') ? unserialize($row['content']) : array();
        if (!$draft) {
            return array();
        }
        return $this->convertData($draft);
    }

    public function save($elmid = '', $resource = array(), $status = 'inherit') {
        if (!$elmid) {
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
        $checksum = hash('crc32b', $revision_content);
        if ($total
            &&
            $exists_data['checksum'] === $checksum
            &&
            $exists_data['status'] == $status
        ) {
            return 'nochange';
        }
        $f = array(
            'elmid' => $elmid,
            'status' => $status,
            'content' => db()->escape($revision_content),
            'element' => 'resource',
            'editedon' => serverv('REQUEST_TIME'),
            'editedby' => evo()->getLoginUserID(),
            'checksum' => $checksum,
            'version' => ($status === 'inherit') ? $total + 1 : 0
        );

        if ($total) {
            db()->update($f, '[+prefix+]site_revision', sprintf("elmid='%s'", $elmid));
        } else {
            db()->insert($f, '[+prefix+]site_revision');
        }
        return $total ? 'upd' : 'new';
    }

    public function delete($elmid = '', $status = '*') {
        if (!$elmid) {
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

    private function getCurrentResource($docid) {
        $vars = evo()->getTemplateVars('*', '*', $docid, null);
        if (!$vars) {
            return array();
        }
        foreach ($vars as $i => $v) {
            if (isset($v['id'])) {
                $doc[sprintf('tv%s', $v['id'])] = $v['value'];
            } else {
                $doc[$v['name']] = $v['value'];
            }
        }
//        $doc = $this->convertData($doc);
        if (!$doc) {
            return false;
        }
        return $doc;
    }

    private function convertData($doc = array()) {
        $input = array(
            'content' => array_get(
                $doc
                , 'content'
                , array_get($doc, 'ta', '')
            ),
            'pagetitle'       => array_get($doc, 'pagetitle', ''),
            'longtitle'       => array_get($doc, 'longtitle', ''),
            'menutitle'       => array_get($doc, 'menutitle', ''),
            'description'     => array_get($doc, 'description', ''),
            'introtext'       => array_get($doc, 'introtext', ''),
            'type'            => array_get($doc, 'type', 'document'),
            'alias'           => array_get($doc, 'alias', ''),
            'link_attributes' => array_get($doc, 'link_attributes', ''),
            'isfolder'        => array_get($doc, 'isfolder', 0),
            'richtext'        => array_get($doc, 'richtext', 1),
            'parent'          => array_get($doc, 'parent', 0),
            'template'        => array_get($doc, 'template', 0),
            'menuindex'       => array_get($doc, 'menuindex', 0),
            'searchable'      => array_get($doc, 'searchable', 1),
            'cacheable'       => array_get($doc, 'cacheable', 1),
            'contentType'     => array_get($doc, 'contentType', 'text/html'),
            'content_dispo'   => array_get($doc, 'content_dispo', ''),
            'hidemenu'        => array_get($doc, 'hidemenu', ''),
            'pub_date'        => array_get($doc, 'pub_date', 0),
            'unpub_date'      => array_get($doc, 'unpub_date', 0),
            'published'       => array_get(
                $doc
                , 'published'
                , evo()->config(
                'publish_default'
                , 0
            )
            ),
        );
        foreach ($doc as $k => $v) {
            if (strpos($k, 'tv') !== 0) {
                continue;
            }
            if (array_get($doc, $k . '_prefix') === null) {
                $input[$k] = is_array($v) ? implode('||',$v) : $v;
                continue;
            }
            if ($doc[$k . '_prefix'] === 'DocID') {
                //tvがリンクの時の例外処理
                $input[$k] = '[~' . $v . '~]';
                continue;
            }
            $input[$k] = $doc[$k . '_prefix'] . $v;
        }
        return $input;
    }

    private function _setStatus($elmid, $elm = 'resource') {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf("elmid='%s' AND element='%s'", $elmid, $elm));
        if (!$rs) {
            return false;
        }

        $this->hasDraft = 0;
        $this->hasInherit = 0;
        $this->hasPending = 0;
        $this->hasAutoDraft = 0;
        $this->hasStandby = 0;
        $this->hasPrivate = 0;
        while ($row = db()->getRow($rs)) {
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

    public function getRevisionStatus($elmid) {
        $rs = db()->select(
            '*'
            , '[+prefix+]site_revision'
            , sprintf(
                "elmid='%s' AND version='0'"
                , $elmid
            )
        );
        $row = db()->getRow($rs);
        if (array_get($row, 'status')) {
            return $row['status'];
        }
        return 'nodraft';
    }

    public function getFormFromDraft($id) {
        $data = $this->getDraft($id);
        $form = array();
        foreach ($data as $k => $v) {
            $form[] = evo()->parseText(
                '<input type="hidden" name="[+name+]" value="[+value+]" />'
                , array(
                    'name' => $k,
                    'value' => hsc($v)
                )
            );
        }
        return implode("\n", $form);
    }

    /*
    *  公開予定から下書きに変更
    *  (複数公開/複数下書きの仕様は未考慮)
    */
    public function chStandbytoDraft($elmid, $type = 'resource') {
        if (!$elmid) {
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

    public function convertTvid2Tvname($input) {
        $rs = db()->select('id,name', '[+prefix+]site_tmplvars');
        while ($row = db()->getRow($rs)) {
            $tvname['tv' . $row['id']] = $row['name'];
        }

        foreach ($input as $k => $v) {
            if (!isset($tvname[$k])) {
                continue;
            }
            unset($input[$k]);
            $input[$tvname[$k]] = $v;
        }
        if (isset($input['ta'])) {
            $input['content'] = $input['ta'];
            unset($input['ta']);
        }
        return $input;
    }

    public function publishDraft($fields) {
        evo()->loadExtension('DocAPI');
        $fields = evo()->doc->fixPubStatus(
            evo()->doc->fixTvNest($fields)
        );

        if (severv('REQUEST_TIME') < array_get($fields, 'pub_date', 0)) {
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
