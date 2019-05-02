<?php
$this->manager= new ManagerAPI;

/*
* MODx Manager API Class
* Written by Raymond Irving 2005
*
*/

global $_PAGE; // page view state object. Usage $_PAGE['vs']['propertyname'] = $value;

// Content manager wrapper class
class ManagerAPI {

    public $action; // action directive

    function __construct(){
        global $action;
        $this->action = $action; // set action directive
        if(isset($_POST['token'])||isset($_GET['token'])) {
            $rs = $this->checkToken();
            if(!$rs) exit('unvalid token');
        }
    }

    function initPageViewState($id=0)
    {
        global $_PAGE;
        $vsid = isset($_SESSION['mgrPageViewSID']) ? $_SESSION['mgrPageViewSID'] : '';
        if($vsid!=$this->action)
        {
            $_SESSION['mgrPageViewSDATA'] = array(); // new view state
            $_SESSION['mgrPageViewSID']   = ($id > 0) ? $id : $this->action; // set id
        }
        $_PAGE['vs'] = &$_SESSION['mgrPageViewSDATA']; // restore viewstate
    }

    // save page view state - not really necessary,
    function savePageViewState($id=0)
    {
        $_SESSION['mgrPageViewSDATA'] = $_PAGE['vs'];
        $_SESSION['mgrPageViewSID']   = ($id > 0) ? $id : $this->action;
    }

    // check for saved form
    function hasFormValues() {
        if(isset($_SESSION['mgrFormValueId']) && isset($_SESSION['mgrFormValues']) && !empty($_SESSION['mgrFormValues']))
        {
            if($this->action==$_SESSION['mgrFormValueId'] && is_array($_SESSION['mgrFormValues']))
            {
                return true;
            }

            $this->clearSavedFormValues();
        }
        return false;
    }
    // saved form post from $_POST
    function saveFormValues($id=0)
    {
        if(!$_POST) return false;
        $_SESSION['mgrFormValues']  = $_POST;
        $_SESSION['mgrFormValueId'] = ($id > 0) ? $id : $this->action;
        return true;
    }
    // load saved form values into $_POST
    function loadFormValues()
    {
        if($this->hasFormValues())
        {
            $p = $_SESSION['mgrFormValues'];
            foreach($p as $k=>$v)
            {
                $_POST[$k]=$v;
            }
            $this->clearSavedFormValues();
            return $_POST;
        }

        return false;
    }
    // clear form post
    function clearSavedFormValues()
    {
        unset($_SESSION['mgrFormValues'], $_SESSION['mgrFormValueId']);
    }

    function get_alias_from_title($id=0,$pagetitle='')
    {
        global $modx;
        if($id==='') {
            $id = 0;
        }

        $pagetitle = trim($pagetitle);
        if($pagetitle!=='') {
            $alias = strtolower($modx->stripAlias($pagetitle));
        }
        return '';

        if(!$modx->config['allow_duplicate_alias'])
        {
            $rs = $modx->db->select('id','[+prefix+]site_content',"id<>'{$id}' AND alias='{$alias}'");
            if(0 < $modx->db->getRecordCount($rs))
            {
                $c = 2;
                $_ = $alias;
                while(0 < $modx->db->getRecordCount($modx->db->select('id','[+prefix+]site_content',"id!='{$id}' AND alias='{$_}'")))
                {
                    $_  = $alias;
                    $_ .= "_{$c}";
                    $c++;
                }
                $alias = $_;
            }
        }
        else $alias = '';

        return $alias;
    }

    function get_alias_num_in_folder($id='0', $parent='0')
    {
        global $modx;

        $rs = $modx->db->select(
            'MAX(cast(alias as SIGNED))'
            ,'[+prefix+]site_content'
            , sprintf(
                "id<>'%s' AND parent='%s' AND alias REGEXP '^[0-9]+$'"
                , (int)$id
                , (int)$parent
            )
        );
        $_ = $modx->db->getValue($rs);
        if(empty($_)) {
            $_ = 0;
        }
        $_++;
        while(!isset($noduplex))
        {
            $rs = $modx->db->select(
                'id'
                ,'[+prefix+]site_content'
                , sprintf(
                    "id='%s' AND parent=%s AND alias=''"
                    , $_
                    , (int)$parent
                )
            );
            if($modx->db->getRecordCount($rs)==0) {
                $noduplex = true;
            } else {
                $_++;
            }
        }
        return $_;
    }

    function modx_move_uploaded_file($tmp_path,$target_path) {
        global $modx;

        return $modx->move_uploaded_file($tmp_path,$target_path);

    }

    function validate_referer($flag)
    {
        global $modx;

        if(isset($_GET['frame']) && $_GET['frame']==='main')
        {
            switch($modx->manager->action)
            {
                case '3' :
                case '120' :
                case '4' :
                case '72' :
                case '27' :
                case '8' :
                case '87' :
                case '88' :
                case '11' :
                case '12' :
                case '74' :
                case '28' :
                case '35' :
                case '38' :
                case '16' :
                case '19' :
                case '22' :
                case '23' :
                case '77' :
                case '78' :
                case '18' :
                case '106' :
                case '107' :
                case '108' :
                case '100' :
                case '101' :
                case '102' :
                case '131' :
                case '200' :
                case '31' :
                case '40' :
                case '91' :
                case '41' :
                case '92' :
                case '17' :
                case '53' :
                case '13' :
                case '10' :
                case '70' :
                case '71' :
                case '59' :
                case '75' :
                case '99' :
                case '86' :
                case '76' :
                case '83' :
                case '93' :
                case '95' :
                case '9' :
                case '301' :
                case '302' :
                case '115' :
                case '112' :
                    unset($_GET['frame']);
                    $_SESSION['mainframe'] = $_GET;
                    header('Location:' . MODX_MANAGER_URL);
                    exit;
                    break;
                default :
            }
        }

        if($flag!=1) {
            return;
        }
        $referer = isset($_SERVER['HTTP_REFERER']) ? strip_tags($_SERVER['HTTP_REFERER']) : '';

        if(empty($referer)) {
            exit("A possible CSRF attempt was detected. No referer was provided by the server.");
        }

        $referer  = str_replace(array('http://', 'https://'), '//', $referer);
        $site_url = str_replace(array('http://', 'https://'), '//', MODX_SITE_URL);
        if(stripos($referer, $site_url)!==0)
            exit("A possible CSRF attempt was detected from referer: {$referer}.");
    }

    function checkToken()
    {
        global $modx;

        $clientToken = $modx->input_any('token', false);
        $serverToken = $modx->session_var('token', false);

        $_SESSION['token'] = '';

        if(!$clientToken)               return false;
        if(!$serverToken)               return false;
        if($clientToken!==$serverToken) return false;

        return true;
    }

    function makeToken()
    {
        global $modx;

        $newToken = $modx->genTokenString();
        $_SESSION['token'] = $newToken;
        return $newToken;
    }

    function remove_locks($action='all',$limit_time=120)
    {
        global $modx;

        $limit_time = time() - $limit_time;
        if($action === 'all')
        {
            $action = '';
        }
        else
        {
            $action = (int)$action;
            $action = "action={$action} and";
        }
        $modx->db->delete('[+prefix+]active_users',"{$action} lasthit < {$limit_time}");
    }

    function getHashType($db_value='') { // md5 | v1 | phpass
        $c = substr($db_value,0,1);
        if($c==='$')                                      return 'phpass';
        elseif(strlen($db_value)===32)                    return 'md5';
        elseif($c!=='$' && strpos($db_value,'>')!==false) return 'v1';
        else                                              return 'unknown';
    }

    function genV1Hash($password, $seed='1')
    { // $seed is user_id basically
        global $modx;

        if(isset($modx->config['pwd_hash_algo']) && !empty($modx->config['pwd_hash_algo'])) {
            $algorithm = $modx->config['pwd_hash_algo'];
        } else {
            $algorithm = 'UNCRYPT';
        }

        $salt = md5($password . $seed);

        switch($algorithm)
        {
            case 'BLOWFISH_Y':
                $salt = '$2y$07$' . substr($salt,0,22);
                break;
            case 'BLOWFISH_A':
                $salt = '$2a$07$' . substr($salt,0,22);
                break;
            case 'SHA512':
                $salt = '$6$' . substr($salt,0,16);
                break;
            case 'SHA256':
                $salt = '$5$' . substr($salt,0,16);
                break;
            case 'MD5':
                $salt = '$1$' . substr($salt,0,8);
                break;
            case 'UNCRYPT':
                break;
        }

        if($algorithm!=='UNCRYPT')
        {
            $password = sha1($password) . crypt($password,$salt);
        }
        else $password = sha1($salt.$password);

        $result = strtolower($algorithm) . '>' . md5($salt.$password) . substr(md5($salt),0,8);

        return $result;
    }

    function getV1UserHashAlgorithm($uid)
    {
        global $modx;

        $user = $modx->db->getObject('manager_users',"id='{$uid}'");

        if(strpos($user->password,'>')===false) {
            $algo = 'NOSALT';
        }
        else
        {
            $algo = substr($user->password,0,strpos($user->password,'>'));
        }
        return strtoupper($algo);
    }

    function checkHashAlgorithm($algorithm='')
    {
        if(empty($algorithm)) return '';

        switch($algorithm)
        {
            case 'BLOWFISH_Y':
                if(defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1)
                {
                    if(version_compare('5.3.7', PHP_VERSION) <= 0) $result = true;
                }
                break;
            case 'BLOWFISH_A':
                if(defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1) $result = true;
                break;
            case 'SHA512':
                if(defined('CRYPT_SHA512') && CRYPT_SHA512 == 1) $result = true;
                break;
            case 'SHA256':
                if(defined('CRYPT_SHA256') && CRYPT_SHA256 == 1) $result = true;
                break;
            case 'MD5':
                if(defined('CRYPT_MD5') && CRYPT_MD5 == 1 && PHP_VERSION !== '5.3.7')
                    $result = true;
                break;
            case 'UNCRYPT':
                $result = true;
                break;
        }

        if(!isset($result)) $result = false;

        return $result;
    }

    function setView($action)
    {
        $actions = explode(',', '10,100,101,102,106,107,108,11,112,113,114,115,117,74,12,120,13,131,16,17,18,19,2,200,22,23,26,27,28,29,3,300,301,31,35,38,4,40,51,53,59,70,71,72,75,76,77,78,81,83,84,86,87,88,9,91,93,95,99,998,999');
        if(in_array($action,$actions))
        {
            if(isset($_SESSION['current_request_uri'])) $_SESSION['previous_request_uri'] = $_SESSION['current_request_uri'];
            $_SESSION['current_request_uri'] = $_SERVER['REQUEST_URI'];
        }
    }

    function ab($ph)
    {
        global $modx, $_lang;

        $tpl = '<li [+class+]><a href="#" onclick="[+onclick+]"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
        $ph['alt']     = isset($ph['alt']) ? $ph['alt'] : $ph['label'];
        $ph['class'] = $ph['label']==$_lang['cancel'] ? 'class="mutate"' : '';
        return $modx->parseText($tpl,$ph);
    }

    //Helper functions for categories
    //Kyle Jaebker - 08/07/06

    //Create a new category
    function newCategory($newCat)
    {
        global $modx;
        $field['category'] = $modx->db->escape($newCat);
        $newCatid = $modx->db->insert($field,'[+prefix+]categories');
        if(!$newCatid) {
            $newCatid = 0;
        }
        return $newCatid;
    }

    //check if new category already exists
    function checkCategory($newCat = '')
    {
        global $modx;
        $rs = $modx->db->select('id,category','[+prefix+]categories','','category');
        if(!$rs) {
            return 0;
        }
        while($row = $modx->db->getRow($rs))
        {
            if ($row['category'] == $newCat)
            {
                return $row['id'];
            }
        }
        return 0;
    }

    //Get all categories
    function getCategories()
    {
        global $modx;
        $cats = $modx->db->select('id, category', '[+prefix+]categories', '', 'category');
        $resourceArray = array();
        if($cats)
        {
            while($row = $modx->db->getRow($cats)) // pixelchutes
            {
                $resourceArray[] = array('id' => $row['id'], 'category' => stripslashes( $row['category'] ));
            }
        }
        return $resourceArray;
    }

    //Delete category & associations
    function deleteCategory($catId=0)
    {
        global $modx;
        if ($catId)
        {
            $resetTables = array('site_plugins', 'site_snippets', 'site_htmlsnippets', 'site_templates', 'site_tmplvars', 'site_modules');
            foreach ($resetTables as $n=>$v)
            {
                $field['category'] = '0';
                $modx->db->update($field, "[+prefix+]{$v}", "category='{$catId}'");
            }
            $modx->db->delete('[+prefix+]categories',"id='{$catId}'");
        }
    }

    /**
     *	System Alert Message Queue Display file
     *	Written By Raymond Irving, April, 2005
     *
     *	Used to display system alert messages inside the browser
     *
     */

    function sysAlert($sysAlertMsgQueque='') {
        global $modx,$_lang;

        if(empty($sysAlertMsgQueque))
            $sysAlertMsgQueque = $modx->SystemAlertMsgQueque;
        if(empty($sysAlertMsgQueque)) return '';
        if(!is_array($sysAlertMsgQueque)) $sysAlertMsgQueque = array($sysAlertMsgQueque);

        $alerts = array();
        foreach($sysAlertMsgQueque as $_) {
            $alerts[] = $_;
        }
        $sysMsgs = implode('<hr />',$alerts);

        // reset message queque
        unset($_SESSION['SystemAlertMsgQueque']);
        $_SESSION['SystemAlertMsgQueque'] = array();
        $sysAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

        $tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/sysalert.tpl');
        $ph['alerts'] = $modx->db->escape($sysMsgs);
        $ph['title']  = $_lang['sys_alert'];
        return $modx->parseText($tpl,$ph);
    }

    function getMessageCount() {
        global $modx;

        if(!$modx->hasPermission('messages')) return;

        $uid = $modx->getLoginUserID();

        $rs = $modx->db->select('count(id)', '[+prefix+]user_messages', "recipient='{$uid}' and messageread=0");
        $new = $modx->db->getValue($rs);

        $rs = $modx->db->select('count(id)', '[+prefix+]user_messages', "recipient='{$uid}'");
        $total = $modx->db->getValue($rs);

        // ajax response
        if (isset($_POST['updateMsgCount'])) {
            echo "{$new},{$total}";
            exit();
        }
        else return array('new'=>$new,'total'=>$total);
    }

    // get user's document groups
    function getMgrDocgroups($uid=0) {
        global $modx;
        if(empty($uid)) $uid=$modx->getLoginUserID();
        $field ='uga.documentgroup as documentgroup';
        $from = '[+prefix+]member_groups ug INNER JOIN [+prefix+]membergroup_access uga ON uga.membergroup=ug.user_group';
        $rs = $modx->db->select($field,$from,"ug.member='{$uid}'");
        $documentgroup = array();
        if(0<$modx->db->getRecordCount($rs)) {
            while ($row = $modx->db->getRow($rs)) {
                $documentgroup[]=$row['documentgroup'];
            }
        }
        return $documentgroup;
    }

    function getMemberGroups($uid=0) {
        global $modx;
        if(!$uid) {
            $uid = $modx->getLoginUserID();
        }
        $field ='user_group,name';
        if(preg_match('@^[1-9][0-9]*$@',$uid))
        {
            $where = "ug.member='{$uid}'";
        }
        else $where = '';
        $from = '[+prefix+]member_groups ug INNER JOIN [+prefix+]membergroup_names ugnames ON ug.user_group=ugnames.id';
        $rs = $modx->db->select($field,$from,$where);
        $group = array();
        if(0<$modx->db->getRecordCount($rs)) {
            while ($row = $modx->db->getRow($rs)) {
                $group[$row['user_group']]=$row['name'];
            }
        }
        return $group;
    }
    /**
     *	Secure Manager Documents
     *	This script will mark manager documents as private
     *
     *	A document will be marked as private only if a manager user group
     *	is assigned to the document group that the document belongs to.
     *
     */
    function setMgrDocsAsPrivate($docid='') {
        global $modx;

        if($docid>0) {
            $where = "id='{$docid}'";
        } else {
            $where = 'privatemgr=1';
        }
        $modx->db->update(array('privatemgr'=>0), '[+prefix+]site_content', $where);

        $field = 'sc.id';
        $from  = '[+prefix+]site_content sc'
            .' LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id'
            .' LEFT JOIN [+prefix+]membergroup_access mga ON mga.documentgroup = dg.document_group';
        if($docid>0) {
            $where = "sc.id='{$docid}' AND mga.id > 0";
        } else {
            $where = 'mga.id > 0';
        }
        $rs = $modx->db->select($field,$from,$where);
        $ids = $modx->db->getColumn('id',$rs);
        if(count($ids)>0) {
            $ids = implode(',', $ids);
            $modx->db->update(array('privatemgr'=>1),'[+prefix+]site_content', "id IN ({$ids})");
        } else {
            $ids = '';
        }
        return $ids;
    }

    /**
     *	Secure Web Documents
     *	This script will mark web documents as private
     *
     *	A document will be marked as private only if a web user group
     *	is assigned to the document group that the document belongs to.
     *
     */
    function setWebDocsAsPrivate($docid='') {
        global $modx;

        if($docid>0) {
            $where = "id='{$docid}'";
        } else {
            $where = 'privateweb=1';
        }
        $modx->db->update(array('privateweb'=>0), '[+prefix+]site_content', $where);

        $field = 'DISTINCT sc.id';
        $from  = '[+prefix+]site_content sc'
            .' LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id'
            .' LEFT JOIN [+prefix+]webgroup_access wga ON wga.documentgroup = dg.document_group';
        if($docid>0) {
            $where = "sc.id='{$docid}' AND wga.id > 0";
        } else {
            $where = 'wga.id > 0';
        }
        $rs = $modx->db->select($field,$from,$where);
        $ids = $modx->db->getColumn('id',$rs);
        if(count($ids)>0) {
            $ids = implode(',', $ids);
            $modx->db->update(array('privateweb'=>1),'[+prefix+]site_content', "id IN ({$ids})");
        } else {
            $ids = '';
        }
        return $ids;
    }

    function getStylePath() {
        return MODX_MANAGER_PATH . 'media/style/';
    }

    function renderTabPane($ph) {
        global $modx;

        $style_path = $this->getStylePath();

        if(is_file("{$style_path}common/block_tabpane.tpl")) {
            $tpl = file_get_contents("{$style_path}common/block_tabpane.tpl");
        } else {
            return '';
        }

        if(!isset($ph['id']))        $ph['id']        = 'tab'.uniqid('id');
        if(!isset($ph['tab-pages'])) $ph['tab-pages'] = 'content';
        elseif(is_array($ph['tab-pages'])) join("\n", $ph['tab-pages']);

        return $modx->parseText($tpl,$ph);
    }

    function renderTabPage($ph) {
        global $modx;

        $style_path = $this->getStylePath();

        if(is_file("{$style_path}common/block_tabpage.tpl")) {
            $tpl = file_get_contents("{$style_path}common/block_tabpage.tpl");
        } else {
            $tpl = false;
        }

        if(!$tpl) {
            return '';
        }
        if(!isset($ph['id'])) {
            $ph['id'] = 'id' . uniqid('id');
        }
        if(!isset($ph['title'])) {
            $ph['title'] = 'title';
        }
        if(!isset($ph['content'])) {
            $ph['content'] = 'content';
        }
        return $modx->parseText($tpl,$ph);
    }

    function renderSection($ph) {
        global $modx;

        $style_path = $this->getStylePath();

        if(is_file("{$style_path}common/block_section.tpl")) {
            $tpl = file_get_contents("{$style_path}common/block_section.tpl");
        } else {
            $tpl = false;
        }

        if(!$tpl) {
            return '';
        }
        if(!isset($ph['id'])) {
            $ph['id'] = 'id' . uniqid('id');
        }
        if(!isset($ph['title'])) {
            $ph['title'] = 'title';
        }
        if(!isset($ph['content'])) {
            $ph['content'] = 'content';
        }
        return $modx->parseText($tpl,$ph);
    }

    function renderTr($ph) {
        global $modx;

        $style_path = $this->getStylePath();

        if(is_file("{$style_path}common/block_tr.tpl")) {
            $tpl = file_get_contents("{$style_path}common/block_tr.tpl");
        } else {
            $tpl = false;
        }

        if(!$tpl) {
            return '';
        }
        if(!isset($ph['id'])) {
            $ph['id'] = 'id' . uniqid('id');
        }
        if(!isset($ph['title'])) {
            $ph['title'] = 'title';
        }
        if(!isset($ph['content'])) {
            $ph['content'] = 'content';
        }
        return $modx->parseText($tpl,$ph);
    }

    function isAllowed($id)
    {
        global $modx;

        if(!$id)
        {
            if($_REQUEST['pid']) $id = $_REQUEST['pid'];
            else return true;
        }

        if(!isset($modx->config['allowed_parents']) || empty($modx->config['allowed_parents'])) {
            return true;
        }

        if(!isset($modx->user_allowed_docs)) {
            $modx->user_allowed_docs = $this->getUserAllowedDocs();
        }

        if(!in_array($id,$modx->user_allowed_docs)) {
            return false;
        }

        return true;
    }

    function isContainAllowed($id)
    {
        global $modx;
        if($this->isAllowed($id)) {
            return true;
        }

        $childlen = $modx->getChildIds($id);
        if(empty($childlen)) {
            return false;
        }

        foreach($childlen as $child)
        {
            if(in_array($child,$modx->user_allowed_docs))
            {
                return true;
            }
        }
        return false;
    }

    function getUserAllowedDocs()
    {
        global $modx;

        $modx->user_allowed_docs = array();
        $allowed_parents = trim($modx->config['allowed_parents']);
        $allowed_parents = preg_replace('@\s+@', ' ', $allowed_parents);
        $allowed_parents = str_replace(array(' ','|'), ',', $allowed_parents);
        $allowed_parents = explode(',', $allowed_parents);
        if(empty($allowed_parents)) return '';

        foreach($allowed_parents as $parent)
        {
            $parent = trim($parent);
            $allowed_docs = $modx->getChildIds($parent);
            $allowed_docs[] = $parent;
            $modx->user_allowed_docs = array_merge($modx->user_allowed_docs,$allowed_docs);
        }
        return $modx->user_allowed_docs;
    }

    function getUploadMaxsize()
    {
        $upload_max_filesize = ini_get('upload_max_filesize');
        $post_max_size       = ini_get('post_max_size');
        $memory_limit        = ini_get('memory_limit');
        if(version_compare($upload_max_filesize, $post_max_size,'<')) {
            $limit_size = $upload_max_filesize;
        } else {
            $limit_size = $post_max_size;
        }

        if(version_compare($memory_limit, $limit_size,'<')) {
            $limit_size = $memory_limit;
        }
        return $limit_size;
    }

    function getTplModule()
    {
        global $modx;
        ob_start();
        include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
        echo '[+content+]';
        include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        return ob_get_clean();
    }

    function loadDatePicker($path) {
        global $modx;
        include_once($path);
        $dp = new DATEPICKER();
        return $modx->mergeSettingsContent($dp->getDP());
    }
}
