<?php
/**
 * QuickManager+
 *
 * @author      Mikko Lammi, www.maagit.fi
 * @license     GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @version     1.5.6 updated 12/01/2011
 */

class Qm {
    public $modx;
    public $jqpath = '';

    public function __construct() {
    }

    private function tv_buttons() {
        global $modx;
        if(!evo()->isFrontend()) {
            return;
        }
        // Replace [*#tv*] with QM+ edit TV button placeholders
        if (event()->param('tvbuttons') != 'true') {
            return;
        }
        if (event()->name !== 'OnParseDocument') {
            return;
        }
        $output = &$modx->documentOutput;
        if(strpos($output,'[*#')===false) {
            return;
        }
        
        $m = evo()->getTagsFromContent(
            $output
            , '[*#', '*]'
        );
        if(!$m) {
            return;
        }
        foreach($m[1] as $i=>$v) {
            $output = str_replace(
                $m[0][$i]
                , sprintf(
                    '<!-- %s %s -->%s'
                    , event()->param('tvbclass')
                    , (strpos($v,':')!==false) ? substr($v, 0, strpos($v, ':')) : $v
                    , $m[0][$i]
                )
                , $output
            );
        }
    }

    private function init() {
        global $modx;
        if(getv('a')==83) {
            return;
        }

        $params = event()->params;
        if(!$params) {
            $modx->documentOutput = 'QuickManagerをインストールし直してください。';
            return;
        }
        extract($params);
        if ($this->config('disabled')) {
            $arr = explode(',', $this->config('disabled') );
            if (in_array(evo()->documentIdentifier, $arr)) {
                return false;
            }
        }

        // Get plugin parameters
        $this->jqpath = 'manager/media/script/jquery/jquery.min.js';
        $this->loadfrontendjq = $loadfrontendjq;
        $this->noconflictjq = $noconflictjq;
        $this->loadtb = $loadtb;
        $this->tbwidth = $tbwidth;
        $this->tbheight = $tbheight;
        $this->hidefields = $hidefields;
        $this->hidetabs = isset($hidetabs) ? $hidetabs : '';;
        $this->hidesections = isset($hidesections) ? $hidesections : '';
        $this->addbutton = $addbutton;
        $this->tpltype = $tpltype;
        $this->tplid = isset($tplid) ? $tplid : '';
        $this->custombutton = isset($custombutton)? $custombutton : '';
        $this->managerbutton = $managerbutton;
        $this->logout = $logout;
        $this->autohide = $autohide;
        $this->editbuttons = $editbuttons;
        $this->editbclass = $editbclass;
        $this->newbuttons = $newbuttons;
        $this->newbclass = $newbclass;
        $this->tvbuttons = $tvbuttons;
        $this->tvbclass = $tvbclass;

        if(!isset($version) || version_compare($version,'1.5.5r5','<')) {
            $modx->documentOutput = 'QuickManagerをアップデートしてください。';
            return false;
        }

        // Includes
        include_once(MODX_BASE_PATH.'assets/plugins/qm/mcc.class.php');
        return true;
    }

    function Run() {

        $this->tv_buttons();

        $rs = $this->init();
        if(!$rs) {
            return;
        }
        // Include MODx manager language file
        global $_lang;

        // Get manager language
        $manager_language = evo()->config['manager_language'];

        // Get event
        $e = &evo()->event;

        // Run plugin based on event
        switch ($e->name) {
            case 'OnDocFormSave':
                include 'inc/on_doc_form_save.php';
                break;
            case 'OnWebPagePrerender':
                include 'inc/on_web_page_prerender.php';
                break;
            case 'OnDocFormPrerender':
                include 'inc/on_doc_form_prerender.php';
                break;
            case 'OnManagerLogout': // Where to logout
                include 'inc/on_manager_logout.php';
                break;
        }
    }

    public function conf($key, $default=null) {
        $conf = evo()->event->params;
        if(!isset($conf[$key])) {
            $keys = array('hidetabs', 'hidesections', 'tplid', 'custombutton');
            if(in_array($key, $keys)) {
                return '';
            }
            if ($key === 'jqpath') {
                if ($this->jqpath) {
                    return $this->jqpath;
                }
                return 'manager/media/script/jquery/jquery.min.js';
            }
            return $default;
        }
        return $conf[$key];

    }

    function checkAccess() {
        // If user is admin (role = 1)
        if (sessionv('mgrRole') == 1) {
            return true;
        }

        if (empty(evo()->documentIdentifier)) {
            return false;
        }

        $result= db()->select(
            'id'
            , '[+prefix+]document_groups'
            , where('document', '=', evo()->documentIdentifier)
        );
        if (!db()->count($result)) {
            return true;
        }

        if(!sessionv('mgrDocgroups')) {
            return false;
        }
        $result = db()->select(
            'id'
            , '[+prefix+]document_groups'
            , array(
                where('document', '=', evo()->documentIdentifier),
                'AND',
                where_in('document_group', sessionv('mgrDocgroups'))
            )
        );
        if (db()->count($result)) {
            return true;
        }
        return false;
    }

    // Function from: manager/processors/cache_sync.class.processor.php
    //_____________________________________________________
    function getParents($id, $path = ''){
        if(!$this->aliases) {
            $qh = db()->select(
                "id, IF(alias='', id, alias) AS alias, parent"
                , '[+prefix+]site_content'
            );
            if ($qh && db()->count($qh) > 0) {
                while ($row = db()->getRow($qh)) {
                    $this->aliases[$row['id']] = $row['alias'];
                    $this->parents[$row['id']] = $row['parent'];
                }
            }
        }
        if (isset($this->aliases[$id])) {
            $path = $this->aliases[$id] . ($path != '' ? '/' : '') . $path;
            return $this->getParents($this->parents[$id], $path);
        }
        return $path;
    }

    // Create TV buttons if user has permissions to TV
    //_____________________________________________________
    function createTvButtons($matches) {
        $docID = evo()->documentIdentifier;

        // Get TV caption for button title
        $tv = evo()->getTemplateVar($matches[1]);
        $caption = $tv['caption'];

        // If caption is empty this must be a "build-in-tv-field" like pagetitle etc.
        if ($caption == '') {
            $access = true;
            $caption = $this->getDefaultTvCaption($matches[1]);
        } else {
            $access = $this->checkTvAccess($tv['id']);
        }

        // Return TV button link if access
        if ($access && $caption != '') {
            return sprintf(
                '<span class="%s"><a class="colorbox" href="%sindex.php?id=%s&amp;quickmanagertv=1&amp;tvname=%s"><span>%s</span></a></span>'
                , $this->tvbclass
                , evo()->config['site_url']
                , $docID
                , urlencode($matches[1])
                , $caption
            );
        }
    }

    // Check user access to TV
    //_____________________________________________________
    function checkTvAccess($tvId){
        if ($_SESSION['mgrRole'] == 1) {
            return true;
        }

        $result = db()->select('id','[+prefix+]site_tmplvar_access', 'tmplvarid = ' . $tvId);
        if (!db()->count($result)) {
            return true;
        }

        if ($this->docGroup != '') {
            $result = db()->select(
                'id'
                , '[+prefi+]site_tmplvar_access'
                , sprintf(
                    'tmplvarid = %s AND documentgroup IN (%s)'
                    , $tvId
                    , $this->docGroup
                )
            );
            if (db()->count($result)) {
                return true;
            }
        }
        return false;
    }

    // Get default TV ("build-in" TVs) captions
    //_____________________________________________________
    public function getDefaultTvCaption($name){
        global $_lang;
        switch ($name) {
            case 'pagetitle'   : return $_lang['resource_title'];
            case 'longtitle'   : return $_lang['long_title'];
            case 'description' : return $_lang['resource_description'];
            case 'content'     : return $_lang['resource_content'];
            case 'menutitle'   : return $_lang['resource_opt_menu_title'];
            case 'introtext'   : return $_lang['resource_summary'];
        }
        return '';
    }

    public function getField($field_name, $docid) {
        $doc = evo()->getDocumentObject('id', $docid);
        if(!$doc) {
            return false;
        }
        if(is_array(array_get($doc, $field_name))) {
            return array_get($doc, $field_name);
        }
        return array(
            'type'         => $this->formType($doc, $field_name),
            'default_text' => '',
            'elements'     => '',
            'value'        => array_get($doc, $field_name),
            'access'       => true,
            'caption'      => $this->caption($field_name)
        );
    }
    
    private function formType($doc,$field_name) {
        switch ($field_name)
        {
            case 'pagetitle'   :
            case 'longtitle'   :
            case 'menutitle'   : return 'text';
            case 'description' :
            case 'introtext'   : return 'textarea';
            case 'content'     : return (config('use_editor') && doc('richtext')) ? 'richtext' : 'textarea';
        }
        return null;
    }

    private function caption($field_name) {
        switch ($field_name)
        {
            case 'pagetitle'   :
            case 'longtitle'   :
            case 'description' :
            case 'content'     :
            case 'menutitle'   :
            case 'introtext'   :
                return $this->getDefaultTvCaption($field_name);
        }
        return null;
    }
    // Check that a document isn't locked for editing
    //_____________________________________________________
    function checkLocked(){
        $result = db()->select(
            'internalKey',
            '[+prefix+]active_users',
            array(
                where('action', 27),
                and_where('internalKey', '!=', $_SESSION['mgrInternalKey']),
                and_where('id', evo()->documentIdentifier)
            )
        );

        if (!db()->count($result)) {
            return false;
        }

        return true;
    }

    function setLocked($locked) {
        if ($locked == 1) {
            $fields['id']     = evo()->documentIdentifier;
            $fields['action'] = 27;
        } else {
            $fields['id'] = 'NULL';
            $fields['action'] = 2;
        }
        db()->update(
            $fields,
            '[+prefix+]active_users',
            where('internalKey', '=', $_SESSION['mgrInternalKey'])
        );
    }

    // Save TV
    //_____________________________________________________
    function saveTv($tvName){
        $tvId = preg_match('@^[1-9][0-9]*$@',postv('tvid')) ? postv('tvid') : 0;
        $tvContent = postv('tv' . $tvId, postv('tv'.$tvName), '');

        $tmp = array('mode'=>'upd', 'id'=>evo()->documentIdentifier);
        evo()->invokeEvent('OnBeforeDocFormSave', $tmp);

        if (is_array($tvContent)) {
            $tvContent = implode('||', $tvContent);
        }

        // Save TV
        if ($tvId) {
            db()->select(
                'id',
                '[+prefix+]site_tmplvar_contentvalues',
                array(
                    where('tmplvarid', '=', $tvId),
                    and_where('contentid', '=', evo()->documentIdentifier)
                )
            );
            if(db()->count()) {
                $result = db()->update(
                    array('value' => db()->escape($tvContent)),
                    '[+prefix+]site_tmplvar_contentvalues',
                    array(
                        where('tmplvarid', $tvId),
                        and_where('contentid', evo()->documentIdentifier)
                    )
                );
            } else {
                $result = db()->insert(
                    array(
                        'tmplvarid' => $tvId,
                        'contentid' => evo()->documentIdentifier,
                        'value'     => db()->escape($tvContent)
                    ),
                    '[+prefix+]site_tmplvar_contentvalues'
                );
            }
        } else {
            $result = db()->update(
                array(
                    db()->escape($tvName) => db()->escape($tvContent)
                ),
                '[+prefix+]site_content',
                where('id', '=', evo()->documentIdentifier)
            );
        }
        if(!$result) {
            evo()->logEvent(
                0
                , 0
                , sprintf(
                    '<p>Save failed!</p><strong>SQL:</strong><pre>%s</pre>'
                    , db()->lastQuery()
                )
                , 'QuickManager+'
            );
            return;
        }
        db()->update(
            array('editedon'=>request_time(), 'editedby'=>$_SESSION['mgrInternalKey'])
            , '[+prefix+]site_content'
            , where('id', evo()->documentIdentifier)
        );
        $tmp = array('mode'=>'upd', 'id'=>evo()->documentIdentifier);
        evo()->invokeEvent('OnDocFormSave', $tmp);
        evo()->clearCache();
    }

    public function jq() {
        if ($this->noconflictjq === 'true') {
            return '$j';
        }
        return '$';
    }

    public function get_img_prev_src(){
        return parseText(
            file_get_contents(__DIR__ . '/js/preview_img.tpl'),
            array(
                'jq' => '$',
                'base_url' => MODX_BASE_URL
            )
        );
    }

    private function config($key, $default=null) {
        $conf = evo()->event->params;
        if(!isset($conf[$key])) {
            if ($key === 'jqpath') {
                if ($this->jqpath) {
                    return $this->jqpath;
                }
                return 'manager/media/script/jquery/jquery.min.js';
            }
            return $default;
        }
        if($conf[$key]==='true') {
            $conf[$key] = true;
        }
        if($conf[$key]==='false') {
            $conf[$key] = false;
        }
        return $conf[$key];

    }
}
