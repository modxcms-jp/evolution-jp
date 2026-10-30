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

    /**
     * アップデート待ち（未適用のマイグレーションがある）かどうか。判定の正本は EvoMigrator::hasPending()。
     * 戻す先の install/ があるかは見ない（呼び出し側が、ある・無いで案内を分ける）
     *
     * @return bool
     */
    public function isUpdatePending(): bool
    {
        require_once MODX_CORE_PATH . 'migrator.class.inc.php';
        return (new EvoMigrator())->hasPending();
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

    /**
     * 要素（チャンク・スニペット・テンプレート・TV・プラグイン・モジュール）の名前を正規化する。
     * 前後の Unicode の空白（半角空白・改行・全角スペースなど）を落とす。
     * 文字列でない値と不正な UTF-8 は空文字として扱う
     *
     * @param mixed $name
     * @return string
     */
    public function normalizeElementName($name): string
    {
        if (!is_string($name)) {
            return '';
        }
        return (string)preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $name);
    }

    /**
     * 要素の保存で、正規化した名前を返す。空なら保存せず、入力を残して新規・編集の画面へ差し戻して終了する
     *
     * @param mixed $name 送信された名前
     * @param string $typeLabel 種別名（$_lang['chunk'] など）
     * @param int $newAction 新規作成画面のアクション番号
     * @param int $editAction 編集画面のアクション番号（id を付けて戻す）
     * @param int $id 編集対象の id（新規は 0）
     * @return string
     */
    public function requireElementName($name, $typeLabel, $newAction, $editAction, $id = 0): string
    {
        $name = $this->normalizeElementName($name);
        if ($name !== '') {
            return $name;
        }
        global $_lang;
        $isEdit = (bool)$id;
        $this->saveFormValues($isEdit ? $editAction : $newAction);
        evo()->webAlertAndQuit(
            sprintf($_lang['element_name_required'], $typeLabel),
            $isEdit ? "index.php?a={$editAction}&id=" . (int)$id : "index.php?a={$newAction}"
        );
        exit;
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

        $alias = '';
        $pagetitle = trim($pagetitle);
        if ($pagetitle !== '') {
            $alias = strtolower(evo()->stripAlias($pagetitle));
        }

        if (evo()->config['allow_duplicate_alias']) {
            return '';
        }

        if ($alias === '') {
            return '';
        }

        $rs = db()->select(
            'id',
            '[+prefix+]site_content',
            sprintf("id<>%d AND alias='%s'", (int) $id, db()->escape($alias))
        );
        if (db()->count($rs)) {
            $c = 2;
            $_ = $alias;
            while (
                0 < db()->count(
                    db()->select(
                        'id',
                        '[+prefix+]site_content',
                        sprintf("id<>%d AND alias='%s'", (int) $id, db()->escape($_))
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
                $ph['label']
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

        // アラートは意図して HTML を含む（system.event の alert() が組み立てる）。差し込む値は呼び出し側でエスケープする。
        // ここでは <script> 内の JS 文字列リテラルとして安全に埋め込む（js_literal()）。
        // 埋め込んだ値の中が再び置換されないよう、parseText() ではなく strtr() で一度だけ置換する
        return strtr(
            file_get_contents(MODX_MANAGER_PATH . 'media/style/common/sysalert.tpl'),
            [
                '[+alerts+]' => js_literal(implode('<hr />', $alerts)),
                '[+title+]' => js_literal($_lang['sys_alert'])
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

    // get permissions of the role（ロールが無ければ空配列＝権限なし）
    public function getMgrPermissions($role)
    {
        $row = db()->getRow(
            db()->select('*', '[+prefix+]user_roles', sprintf("id='%d'", $role))
        );
        if (!$row) {
            return [];
        }
        // 管理ユーザーが 1 人だけならメッセージ機能は使えないので無効にする
        if ($row['messages'] == 1 && db()->getValue(db()->select('COUNT(*)', '[+prefix+]manager_users')) == 1) {
            $row['messages'] = '0';
        }
        return $row;
    }

    // ロール設定の変更をログインし直さずに反映するため、管理画面のリクエストごとに読み直す
    public function refreshMgrPermissions($uid = 0)
    {
        if (!$uid) {
            $uid = evo()->getLoginUserID();
        }
        $role = db()->getValue(
            db()->select('role', '[+prefix+]user_attributes', sprintf("internalKey='%d'", $uid))
        );
        if ($role === false || $role === null) {
            return;
        }
        sessionv('*mgrRole', $role);
        sessionv('*mgrPermissions', $this->getMgrPermissions($role));
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

    /**
     * リソースの祖先 ID を近い順に返す。
     * getParentIds() と違い、深さの上限やエイリアスのキャッシュに頼らず DB の parent を直接たどる。
     * 既に循環しているデータでも止まるよう、一度たどった ID に戻ったら打ち切る
     *
     * @param int $id
     * @return int[]
     */
    public function getAncestorIds($id)
    {
        $id = (int)$id;
        $visited = [$id];
        $ancestors = [];
        while ($id) {
            $id = (int)db()->getValue(
                db()->select('parent', '[+prefix+]site_content', sprintf('id=%d', $id))
            );
            if (!$id || in_array($id, $visited, true)) {
                break;
            }
            $visited[] = $id;
            $ancestors[] = $id;
        }
        return $ancestors;
    }

    /**
     * $parentId を $docIds の親にできないとき、その理由を表示用のメッセージで返す。親にできるなら空文字。
     * ルート（0）はいつでも親にできる。
     * 画面側（ツリーの JS やダイアログ）でも弾いているが、リクエストを直接送られても
     * 循環や存在しない親を保存しないよう、保存処理はこれで確かめる
     *
     * @param int[] $docIds 親を変えるリソース。新規作成なら空配列
     * @param int $parentId
     * @return string
     */
    public function invalidParentMessage(array $docIds, $parentId)
    {
        $parentId = (int)$parentId;
        if (!$parentId) {
            return '';
        }
        $docIds = array_map('intval', $docIds);
        if (in_array($parentId, $docIds, true)) {
            // 言語ファイルの文字列は JS の alert() 向けに \n を含むため、改行へ戻す
            return stripcslashes(lang('illegal_parent_self'));
        }
        $exists = db()->getValue(
            db()->select('COUNT(id)', '[+prefix+]site_content', sprintf('id=%d', $parentId))
        );
        if (!$exists) {
            return sprintf(lang('parent_not_found'), $parentId);
        }
        if (array_intersect($docIds, $this->getAncestorIds($parentId))) {
            return stripcslashes(lang('illegal_parent_child'));
        }
        return '';
    }

    /**
     * $docIds の親を $parentId に変えられないとき、その理由を表示用の文言で返す。変えられるなら空文字。
     * 循環と存在（invalidParentMessage()）、ドキュメントグループ（evo()->checkPermissions()）、
     * 許可するリソースの範囲（isInAllowedParents()）の順に確かめる。
     * 親が今の親から変わるときだけ呼ぶ（範囲の頂点の文書の今の親は範囲の外のため）。
     *
     * @param int[] $docIds 親を変える文書
     * @param int $parentId 新しい親の ID
     * @return string
     */
    public function parentChangeDeniedMessage(array $docIds, $parentId)
    {
        $parentId = (int)$parentId;
        $message = $this->invalidParentMessage($docIds, $parentId);
        if ($message) {
            return $message;
        }
        if (!evo()->checkPermissions($parentId) || !$this->isInAllowedParents($parentId)) {
            return lang('access_permission_parent_denied');
        }
        return '';
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

    /**
     * 文書が、ログイン中の利用者の「許可するリソース」（allowed_parents）の範囲の中かを返す。
     * 文書を変える処理から、確かめる文書や作成先・移動先の親の ID を明示して呼ぶ。リクエストの値は読まない。
     * 設定が空なら常に true（画面の側の isAllowed() と同じ基準）。設定があるのに正しい ID が 1 つも無いときは false。
     * 一覧に 0（ルート）が含まれていれば、サイト全体を許可する設定として常に true（制限なし）。
     * 一覧に 0 が無いとき、ルート（0）は範囲の外（false）。存在しない ID は、設定に書いた ID そのものでなければ範囲の外。
     * 存在の確認は呼ぶ側で行う。
     * 削除済みの文書も、親をたどって判定する。
     *
     * @param int $id 文書の ID（作成先・移動先の親を確かめるときは親の ID）
     * @return bool
     */
    public function isInAllowedParents($id)
    {
        if (!evo()->conf_var('allowed_parents')) {
            return true;
        }
        $allowed = $this->getAllowedParentIds();
        if (!$allowed) {
            return false;
        }
        if (in_array(0, $allowed, true)) {
            return true;
        }
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }
        if (in_array($id, $allowed, true)) {
            return true;
        }
        return (bool)array_intersect($allowed, $this->getAncestorIds($id));
    }

    /**
     * 「許可するリソース」の設定値を、0 と正の整数の配列にして返す（空白・カンマ・| 区切り）
     * 0 か、正の整数の書式（先頭が 0 でない半角数字だけ）に当たる値だけを採る。0 はルート（サイト全体）を表す。
     *
     * @return int[]
     */
    private function getAllowedParentIds()
    {
        $setting = trim((string)evo()->conf_var('allowed_parents'));
        if ($setting === '') {
            return [];
        }
        $ids = [];
        foreach (preg_split('@[\s,|]+@', $setting) as $v) {
            if (!preg_match('@^(0|[1-9][0-9]*)$@', $v)) {
                continue;
            }
            $v = (int)$v;
            if (!in_array($v, $ids, true)) {
                $ids[] = $v;
            }
        }
        return $ids;
    }

    public function getUserAllowedDocs()
    {
        global $modx;

        $modx->user_allowed_docs = [];
        $allowed_parents = $this->getAllowedParentIds();

        if (!$allowed_parents) {
            return '';
        }

        // 0（ルート）を含むときは getChildIds(0) がサイト全体を返す（修正前と同じ）
        $_ = [];
        foreach ($allowed_parents as $parent) {
            $children = evo()->getChildIds($parent);
            $_[] = $parent;
            foreach ($children as $child) {
                $_[] = $child;
            }
        }
        $modx->user_allowed_docs = $_;
        return $modx->user_allowed_docs;
    }

    // ルート直下にリソースを作ってよいか
    public function isAllowroot()
    {
        return evo()->hasPermission('save_role') || (bool)evo()->config('udperms_allowroot');
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
