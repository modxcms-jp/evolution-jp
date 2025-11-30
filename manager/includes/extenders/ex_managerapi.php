<?php
$this->manager = new ManagerAPI;

/*
* MODx Manager API Class
* Written by Raymond Irving 2005
*
*/

global $_PAGE; // page view state object. Usage $_PAGE['vs']['propertyname'] = $value;

// Content manager wrapper class
class ManagerAPI
{

    public $action; // action directive

    public function __construct()
    {
        global $action;
        $this->action = $action; // set action directive
    }

    public function initPageViewState($id = 0)
    {
        global $_PAGE;

        if (sessionv('mgrPageViewSID', '') != $this->action) {
            $_SESSION['mgrPageViewSDATA'] = []; // new view state
            $_SESSION['mgrPageViewSID'] = ($id > 0) ? $id : $this->action; // set id
        }
        $_PAGE['vs'] = &$_SESSION['mgrPageViewSDATA']; // restore viewstate
    }

    // save page view state - not really necessary,
    public function savePageViewState($id = 0)
    {
        global $_PAGE;
        $_SESSION['mgrPageViewSDATA'] = $_PAGE['vs'];
        $_SESSION['mgrPageViewSID'] = ($id > 0) ? $id : $this->action;
    }

    // check for saved form
    public function hasFormValues()
    {
        if (isset($_SESSION['mgrFormValues'][$this->action])) {
            return true;
        }

        if (isset($_SESSION['mgrFormValues'])) {
            unset($_SESSION['mgrFormValues']);
        }

        return false;
    }

    // saved form post from $_POST
    public function saveFormValues($id = 0)
    {
        if (!$_POST) {
            return false;
        }
        $actionId = $id ?: $this->action;
        $_SESSION['mgrFormValues'][$actionId] = $_POST;
        return true;
    }

    // load saved form values into $_POST
    public function loadFormValues()
    {
        if (!$this->hasFormValues()) {
            return [];
        }

        $values = sessionv('mgrFormValues', []);
        unset($_SESSION['mgrFormValues']);

        return $values[$this->action];
    }

    public function get_alias_from_title($id = 0, $pagetitle = '')
    {
        if ($id === '') {
            $id = 0;
        }

        $pagetitle = trim($pagetitle);
        if ($pagetitle !== '') {
            $alias = strtolower(evo()->stripAlias($pagetitle));
        }

        if (evo()->config['allow_duplicate_alias']) {
            return '';
        }

        $rs = db()->select(
            'id',
            '[+prefix+]site_content',
            sprintf("id<>'%s' AND alias='%s'", $id, $alias)
        );
        if (db()->count($rs)) {
            $c = 2;
            $_ = $alias;
            while (
                0 < db()->count(
                    db()->select(
                        'id',
                        '[+prefix+]site_content',
                        sprintf("id!='%s' AND alias='%s'", $id, $_)
                    )
                )
            ) {
                $_ = $alias;
                $_ .= '_' . $c;
                $c++;
            }
            $alias = $_;
        }

        return $alias;
    }

    public function get_alias_num_in_folder($id = '0', $parent = '0')
    {
        $rs = db()->select(
            'MAX(cast(alias as SIGNED))',
            '[+prefix+]site_content',
            sprintf(
                "id<>'%s' AND parent='%s' AND alias REGEXP '^[0-9]+$'",
                (int)$id,
                (int)$parent
            )
        );
        $_ = db()->getValue($rs);
        if (empty($_)) {
            $_ = 0;
        }
        $_++;
        while (!isset($noduplex)) {
            $rs = db()->select(
                'id',
                '[+prefix+]site_content',
                sprintf(
                    "id='%s' AND parent=%s AND alias=''",
                    $_,
                    (int)$parent
                )
            );
            if (db()->count($rs) == 0) {
                $noduplex = true;
            } else {
                $_++;
            }
        }
        return $_;
    }

    public function modx_move_uploaded_file($tmp_path, $target_path)
    {
        return evo()->move_uploaded_file($tmp_path, $target_path);
    }

    public function remove_locks($action = 'all', $limit_time = 120)
    {
        $limit_time = time() - $limit_time;
        if ($action === 'all') {
            $action = '';
        } else {
            $action = (int)$action;
            $action = "action={$action} and";
        }
        db()->delete(
            '[+prefix+]active_users',
            sprintf('%s lasthit < %s', $action, $limit_time)
        );
    }

    public function getHashType($db_value = '')
    { // md5 | v1 | phpass
        $c = substr($db_value, 0, 1);
        if ($c === '$') {
            return 'phpass';
        }

        if (strlen($db_value) === 32) {
            return 'md5';
        }

        if ($c !== '$' && strpos($db_value, '>') !== false) {
            return 'v1';
        }

        return 'unknown';
    }

    public function genV1Hash($password, $seed = '1')
    { // $seed is user_id basically
        if (isset(evo()->config['pwd_hash_algo']) && !empty(evo()->config['pwd_hash_algo'])) {
            $algorithm = evo()->config['pwd_hash_algo'];
        } else {
            $algorithm = 'UNCRYPT';
        }

        $salt = md5($password . $seed);

        switch ($algorithm) {
            case 'BLOWFISH_Y':
                $salt = '$2y$07$' . substr($salt, 0, 22);
                break;
            case 'BLOWFISH_A':
                $salt = '$2a$07$' . substr($salt, 0, 22);
                break;
            case 'SHA512':
                $salt = '$6$' . substr($salt, 0, 16);
                break;
            case 'SHA256':
                $salt = '$5$' . substr($salt, 0, 16);
                break;
            case 'MD5':
                $salt = '$1$' . substr($salt, 0, 8);
                break;
            case 'UNCRYPT':
                break;
        }

        if ($algorithm !== 'UNCRYPT') {
            $password = sha1($password) . crypt($password, $salt);
        } else {
            $password = sha1($salt . $password);
        }

        $result = sprintf(
            '%s>%s%s',
            strtolower($algorithm),
            md5($salt . $password),
            substr(md5($salt), 0, 8)
        );

        return $result;
    }

    public function getV1UserHashAlgorithm($uid)
    {
        $user = db()->getObject('manager_users', "id='{$uid}'");

        if (strpos($user->password, '>') === false) {
            return 'NOSALT';
        }

        return strtoupper(
            substr(
                $user->password,
                0,
                strpos($user->password, '>')
            )
        );
    }

    public function setView($action)
    {
        $actions = explode(
            ',',
            '10,100,101,102,106,107,108,11,112,113,114,115,117,74,12,120,13,131,16,17,18,19,2,200,22,23,26,27,28,29,3,300,301,31,35,38,4,40,51,53,59,70,71,72,75,76,77,78,81,83,84,86,87,88,9,91,93,95,99,998,999'
        );
        if (in_array($action, $actions)) {
            if (sessionv('current_request_uri')) {
                $_SESSION['previous_request_uri'] = sessionv('current_request_uri');
            }
            $_SESSION['current_request_uri'] = $_SERVER['REQUEST_URI'];
        }
    }

    public function ab($ph)
    {
        return html_tag(
            '<li>',
            ['class' => $ph['label'] == lang('cancel') ? 'mutate' : ''],
            html_tag(
                '<a>',
                [
                    'href' => '#',
                    'onclick' => $ph['onclick']
                ],
                img_tag(
                    $ph['icon'],
                    [
                        'alt' => $ph['alt'] ?? '',
                    ]
                ) . $ph['label']
            )
        );
    }

    public function newCategory($newCat)
    {
        $newCatid = db()->insert(
            ['category' => db()->escape($newCat)],
            '[+prefix+]categories'
        );

        if (!$newCatid) {
            return 0;
        }
        return $newCatid;
    }

    //check if new category already exists
    public function checkCategory($newCat = '')
    {
        $rs = db()->select(
            'id,category',
            '[+prefix+]categories',
            '',
            'category'
        );

        if (!$rs) {
            return 0;
        }

        while ($row = db()->getRow($rs)) {
            if ($row['category'] == $newCat) {
                return $row['id'];
            }
        }
        return 0;
    }

    //Get all categories
    public function getCategories()
    {
        $rs = db()->select(
            'id, category',
            '[+prefix+]categories',
            '',
            'category'
        );

        if (!$rs) {
            return [];
        }

        $resourceArray = [];
        // pixelchutes
        while ($row = db()->getRow($rs)) {
            $resourceArray[] = [
                'id' => $row['id'],
                'category' => stripslashes($row['category'])
            ];
        }
        return $resourceArray;
    }

    //Delete category & associations
    public function deleteCategory($catId = 0)
    {
        if (!$catId) {
            return;
        }

        $resetTables = [
            'site_plugins',
            'site_snippets',
            'site_htmlsnippets',
            'site_templates',
            'site_tmplvars',
            'site_modules'
        ];

        foreach ($resetTables as $table_name) {
            db()->update(
                ['category' => '0'],
                '[+prefix+]' . $table_name,
                sprintf("category='%d'", $catId)
            );
        }

        db()->delete(
            '[+prefix+]categories',
            sprintf("id='%d'", $catId)
        );
    }

    /**
     *    System Alert Message Queue Display file
     *    Written By Raymond Irving, April, 2005
     *
     *    Used to display system alert messages inside the browser
     *
     */

    public function sysAlert($sysAlertMsgQueque = '')
    {
        global $_lang;

        if (!$sysAlertMsgQueque) {
            $sysAlertMsgQueque = evo()->SystemAlertMsgQueque;
            if (!$sysAlertMsgQueque) {
                return '';
            }
        }

        if (!is_array($sysAlertMsgQueque)) {
            $sysAlertMsgQueque = [$sysAlertMsgQueque];
        }

        unset($_SESSION['SystemAlertMsgQueque']);
        $_SESSION['SystemAlertMsgQueque'] = [];

        $alerts = [];
        foreach ($sysAlertMsgQueque as $_) {
            $alerts[] = $_;
        }

        return evo()->parseText(
            file_get_contents(MODX_MANAGER_PATH . 'media/style/_system/sysalert.tpl'),
            [
                'alerts' => db()->escape(implode('<hr />', $alerts)),
                'title' => $_lang['sys_alert']
            ]
        );
    }

    public function isAdmin()
    {
        if (!hasPermission('access_permissions')) {
            return false;
        }

        if (!hasPermission('web_access_permissions')) {
            return false;
        }

        return true;
    }

    public function getMessageCount()
    {
        if (!evo()->hasPermission('messages')) {
            return false;
        }

        $uid = evo()->getLoginUserID();

        $new = db()->getValue(
            db()->select(
                'count(id)',
                '[+prefix+]user_messages',
                sprintf("recipient='%s' and messageread=0", $uid)
            )
        );

        $total = db()->getValue(
            db()->select(
                'count(id)',
                '[+prefix+]user_messages',
                sprintf("recipient='%s'", $uid)
            )
        );

        // ajax response
        if (isset($_POST['updateMsgCount'])) {
            echo "{$new},{$total}";
            exit();
        } else {
            return ['new' => $new, 'total' => $total];
        }
    }

    // get user's document groups
    public function getMgrDocgroups($uid = 0)
    {
        if (!$uid) {
            $uid = evo()->getLoginUserID();
        }

        $rs = db()->select(
            'uga.documentgroup as documentgroup',
            [
                '[+prefix+]member_groups ug',
                'INNER JOIN [+prefix+]membergroup_access uga ON uga.membergroup=ug.user_group'
            ],
            sprintf("ug.member='%s'", $uid)
        );

        if (!db()->count($rs)) {
            return [];
        }

        $documentgroup = [];
        while ($row = db()->getRow($rs)) {
            $documentgroup[] = $row['documentgroup'];
        }
        return $documentgroup;
    }

    public function getMemberGroups($uid = 0)
    {
        if (!$uid) {
            $uid = evo()->getLoginUserID();
        }

        $rs = db()->select(
            'user_group,name',
            [
                '[+prefix+]member_groups ug',
                'INNER JOIN [+prefix+]membergroup_names ugnames ON ug.user_group=ugnames.id'
            ],
            preg_match('@^[1-9][0-9]*$@', $uid) ? sprintf("ug.member='%d'", $uid) : ''
        );

        if (!db()->count($rs)) {
            return [];
        }

        $group = [];
        while ($row = db()->getRow($rs)) {
            $group[$row['user_group']] = $row['name'];
        }

        return $group;
    }

    /**
     *    Secure Manager Documents
     *    This script will mark manager documents as private
     *
     *    A document will be marked as private only if a manager user group
     *    is assigned to the document group that the document belongs to.
     *
     */
    public function setMgrDocsAsPrivate($docid = '')
    {
        db()->update(
            ['privatemgr' => 0],
            '[+prefix+]site_content',
            $docid ? sprintf("id='%s'", $docid) : 'privatemgr=1'
        );

        $rs = db()->select(
            'sc.id',
            [
                '[+prefix+]site_content sc',
                'LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id',
                'LEFT JOIN [+prefix+]membergroup_access mga ON mga.documentgroup = dg.document_group'
            ],
            $docid > 0 ? sprintf("sc.id='%s' AND mga.id > 0", $docid) : 'mga.id > 0'
        );

        $ids = db()->getColumn('id', $rs);

        if (!$ids) {
            return '';
        }

        $ids = implode(',', $ids);
        db()->update(
            ['privatemgr' => 1],
            '[+prefix+]site_content',
            sprintf('id IN (%s)', $ids)
        );
        return $ids;
    }

    /**
     *    Secure Web Documents
     *    This script will mark web documents as private
     *
     *    A document will be marked as private only if a web user group
     *    is assigned to the document group that the document belongs to.
     *
     */
    public function setWebDocsAsPrivate($docid = '')
    {
        db()->update(
            ['privateweb' => 0],
            '[+prefix+]site_content',
            $docid ? sprintf("id='%s'", $docid) : 'privateweb=1'
        );

        $rs = db()->select(
            'DISTINCT sc.id',
            [
                '[+prefix+]site_content sc',
                'LEFT JOIN [+prefix+]document_groups dg ON dg.document = sc.id',
                'LEFT JOIN [+prefix+]webgroup_access wga ON wga.documentgroup = dg.document_group'
            ],
            $docid ? sprintf("sc.id='%s' AND wga.id > 0", $docid) : 'wga.id > 0'
        );

        $ids = db()->getColumn('id', $rs);

        if (!$ids) {
            return '';
        }

        $ids = implode(',', $ids);
        db()->update(
            ['privateweb' => 1],
            '[+prefix+]site_content',
            sprintf("id IN (%s)", $ids)
        );
        return $ids;
    }

    public function getStylePath()
    {
        return MODX_MANAGER_PATH . 'media/style/';
    }

    public function renderTabPane($ph)
    {
        $style_path = $this->getStylePath() . 'common/block_tabpane.tpl';

        if (!is_file($style_path)) {
            return '';
        }

        if (!isset($ph['id'])) {
            $ph['id'] = 'tab' . substr(uniqid('id', true), 0, 13);
        }
        if (!isset($ph['tab-pages'])) {
            $ph['tab-pages'] = 'content';
        } elseif (is_array($ph['tab-pages'])) {
            $ph['tab-pages'] = implode("\n", $ph['tab-pages']);
        }

        return evo()->parseText(
            file_get_contents($style_path),
            $ph
        );
    }

    public function renderTabPage($ph)
    {
        $style_path = $this->getStylePath() . 'common/block_tabpage.tpl';

        if (!is_file($style_path)) {
            return '';
        }

        if (!isset($ph['id'])) {
            $ph['id'] = 'id' . substr(uniqid('id', true), 0, 13);
        }
        if (!isset($ph['title'])) {
            $ph['title'] = 'title';
        }

        if (!isset($ph['content'])) {
            $ph['content'] = 'content';
        }

        return evo()->parseText(
            file_get_contents($style_path),
            $ph
        );
    }

    public function renderSection($ph)
    {
        $style_path = $this->getStylePath() . 'common/block_section.tpl';

        if (!is_file($style_path)) {
            return '';
        }

        if (!isset($ph['id'])) {
            $ph['id'] = 'id' . substr(uniqid('id', true), 0, 13);
        }
        if (!isset($ph['title'])) {
            $ph['title'] = 'title';
        }
        if (!isset($ph['content'])) {
            $ph['content'] = 'content';
        }

        return evo()->parseText(
            file_get_contents($style_path),
            $ph
        );
    }

    public function renderTr($ph)
    {
        $style_path = $this->getStylePath() . 'common/block_tr.tpl';

        if (!is_file($style_path)) {
            return '';
        }


        if (!isset($ph['id'])) {
            $ph['id'] = 'id' . substr(uniqid('id', true), 0, 13);
        }
        if (!isset($ph['title'])) {
            $ph['title'] = 'title';
        }
        if (!isset($ph['content'])) {
            $ph['content'] = 'content';
        }

        return evo()->parseText(
            file_get_contents($style_path),
            $ph
        );
    }

    public function isAllowed($id)
    {
        global $modx;

        if (!$id) {
            if (!evo()->input_any('pid')) {
                return true;
            }

            $id = evo()->input_any('pid');
        }

        if (!evo()->conf_var('allowed_parents')) {
            return true;
        }

        if (!isset(evo()->user_allowed_docs)) {
            evo()->user_allowed_docs = $this->getUserAllowedDocs();
        }

        if (!in_array($id, (array)evo()->user_allowed_docs)) {
            return false;
        }

        return true;
    }

    public function isContainAllowed($id)
    {
        if ($this->isAllowed($id)) {
            return true;
        }

        $childlen = evo()->getChildIds($id);
        if (!$childlen) {
            return false;
        }

        foreach ($childlen as $child) {
            if (in_array($child, evo()->user_allowed_docs)) {
                return true;
            }
        }
        return false;
    }

    public function getUserAllowedDocs()
    {
        global $modx;

        $modx->user_allowed_docs = [];
        $allowed_parents = explode(
            ',',
            str_replace(
                [' ', '|'],
                ',',
                preg_replace(
                    '@\s+@',
                    ' ',
                    trim(evo()->config['allowed_parents'])
                )
            )
        );

        if (!$allowed_parents) {
            return '';
        }

        $_ = [];
        foreach ($allowed_parents as $parent) {
            $parent = trim($parent);
            $children = evo()->getChildIds($parent);
            $_[] = $parent;
            foreach ($children as $child) {
                $_[] = $child;
            }
        }
        $modx->user_allowed_docs = $_;
        return $modx->user_allowed_docs;
    }

    public function getUploadMaxsize()
    {
        return min(
            $this->convertToBytes(ini_get('upload_max_filesize')),
            $this->convertToBytes(ini_get('post_max_size')),
            $this->convertToBytes(ini_get('memory_limit'))
        );
    }

    private function convertToBytes($input)
    {
        $unit = strtoupper(substr($input, -1));
        $numericValue = substr($input, 0, -1);
        $validUnits = ['B', 'K', 'M', 'T'];

        if (!in_array($unit, $validUnits)) {
            return $numericValue;
        }

        $bytes = $numericValue;

        foreach ($validUnits as $validUnit) {
            if ($validUnit === $unit) {
                return $bytes;
            }
            $bytes *= 1024;
        }

        return $bytes;
    }

    public function getTplModule()
    {
        ob_start();
        include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
        echo '[+content+]';
        include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        return ob_get_clean();
    }

    public function loadDatePicker($path)
    {
        include_once($path);
        $dp = new DATEPICKER();
        return evo()->mergeSettingsContent($dp->getDP());
    }
}
