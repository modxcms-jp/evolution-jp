<?php
require_once MODX_CORE_PATH . 'form_flow.class.inc.php';

$this->manager = new ManagerAPI;

/*
* MODx Manager API Class
* Written by Raymond Irving 2005
*
*/

// Content manager wrapper class
class ManagerAPI
{
    /**
     * エレメントの種類。キーは OPERATION_PERMISSIONS の 'element.<種類>.manage'・'element.<種類>.save' に対応する
     * （TV はテンプレートの権限に従う）
     */
    private const ELEMENT_TYPES = ['template', 'snippet', 'chunk', 'plugin', 'module'];

    /**
     * 操作 → 必要なロールの権限。ロールの権限の組み合わせの正（can() が引く）。
     * 'all' はすべて、'any' はどれか 1 つ（両方あれば、'all' のすべてと 'any' のどれか）。
     * 文書の操作の組み合わせは、処理（manager/processors/ と manager/actions/ の先頭の確かめ）が今求めるものに合わせてある。
     * 通る経路が複数ある操作は、すべての経路の条件を合わせたもの
     */
    private const OPERATION_PERMISSIONS = [
        // 文書。編集画面（a=27）を開く入口の edit_document は、処理が見る view_document（ロールの編集画面で外せない）とは別に入口の条件として持つ
        'document.edit' => ['all' => ['edit_document']],
        'document.save' => ['all' => ['save_document']],
        // 新規作成（a=4・72 の保存 a=5 は save_document と、新規のとき new_document を求める）
        'document.create' => ['all' => ['new_document', 'save_document']],
        // 複製（a=94）
        'document.duplicate' => ['all' => ['new_document', 'save_document']],
        // 移動。画面 a=51 は move・save・publish、処理 a=52 は move・edit を求め、a=51 から a=52 へ続けて通る
        'document.move' => ['all' => ['move_document', 'save_document', 'publish_document', 'edit_document']],
        // 公開・非公開（a=61・62、一括の a=121 の publish・unpublish）
        'document.publish' => ['all' => ['save_document', 'publish_document']],
        // 削除・元に戻す（a=6・63、一括の a=121 の delete）
        'document.delete' => ['all' => ['delete_document']],
        // モジュール。実行（a=112。execute_module.processor.php）。グループの絞り込みは canExecuteModule() が合わせる
        'module.execute' => ['all' => ['exec_module']],
        // 管理ユーザー・Web ユーザー・ロール。一覧（a=75・99・86）の先頭の確かめは edit_*、
        // 一覧の入口は、押した先（a=11・87・38 の画面、削除の処理 a=12・90・37 など）が求める権限に合わせる
        'role.edit' => ['all' => ['edit_role']],
        'role.create' => ['all' => ['new_role']],
        'user.edit' => ['all' => ['edit_user']],
        'user.create' => ['all' => ['new_user']],
        'user.delete' => ['all' => ['delete_user']],
        'webuser.edit' => ['all' => ['edit_web_user']],
        'webuser.create' => ['all' => ['new_web_user']],
        'webuser.delete' => ['all' => ['delete_web_user']],
        // テンプレート・TV の複製（処理 a=96 は new_template、a=304 は edit_template を求める）
        'template.duplicate' => ['all' => ['new_template']],
        'tmplvar.duplicate' => ['all' => ['edit_template']],
        // エレメントの管理を開く（新規作成か編集のどちらか）
        'element.template.manage' => ['any' => ['new_template', 'edit_template']],
        'element.snippet.manage' => ['any' => ['new_snippet', 'edit_snippet']],
        'element.chunk.manage' => ['any' => ['new_chunk', 'edit_chunk']],
        'element.plugin.manage' => ['any' => ['new_plugin', 'edit_plugin']],
        'element.module.manage' => ['any' => ['new_module', 'edit_module']],
        // エレメントの保存
        'element.template.save' => ['all' => ['save_template']],
        'element.snippet.save' => ['all' => ['save_snippet']],
        'element.chunk.save' => ['all' => ['save_chunk']],
        'element.plugin.save' => ['all' => ['save_plugin']],
        'element.module.save' => ['all' => ['save_module']],
    ];

    /** @var array|null モジュールの ID → その利用者がグループ経由で実行できるか（グループの指定があるモジュールだけ） */
    private $moduleGroupAccessCache;
    /** @var array|null [設定値, 範囲の中の文書の ID の配列, その array_flip（判定用の集合）]（設定値が変わったら作り直す） */
    private $allowedDocIdsCache;
    /** @var array|null [設定値, 許可した親の祖先の ID の配列] */
    private $allowedAncestorIdsCache;

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
     * @param string $kind 要素の種類（EvoFormFlow の表のキー。chunk・snippet・template・tmplvar・plugin・module）
     * @param int $id 編集対象の id（新規は 0。0 以外なら編集画面へ id を付けて戻す）
     * @return string
     */
    public function requireElementName($name, $typeLabel, string $kind, $id = 0): string
    {
        $name = $this->normalizeElementName($name);
        if ($name !== '') {
            return $name;
        }
        global $_lang;
        $this->redirectToElementForm(
            sprintf($_lang['element_name_required'], $typeLabel),
            $kind,
            $id
        );
        exit; // redirectToElementForm() は戻らない
    }

    /**
     * 要素の保存を中断し、入力を残して新規・編集の画面へ差し戻して終了する。
     * 入力は差し戻し先の画面の番号で残す（新規は new、編集は edit + id）。
     * 編集画面側は restoreElementFormValues() が id の一致を確かめて読む。
     * 戻り先の URL（保存後の行き先の引き継ぎを含む）は EvoFormFlow::formUrl() が決める
     *
     * @param string $message 画面に出すメッセージ（生の文字列。出力時にエスケープされる）
     * @param string $kind 要素の種類（EvoFormFlow の表のキー）
     * @param int $id 編集対象の id（新規は 0）
     * @return void 戻らない（webAlertAndQuit() が出力して終了する。@return never 相当）
     */
    public function redirectToElementForm($message, string $kind, $id = 0): void
    {
        $mode = $id ? 'edit' : 'new';
        $this->saveFormValues(EvoFormFlow::screen($kind, $mode));
        evo()->webAlertAndQuit($message, EvoFormFlow::formUrl($kind, $mode, (int)$id, EvoFormFlow::postedAfterSave()));
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

    /**
     * 入力エラーで編集画面（4: 新規、72: ウェブリンク、27・131: 更新）へ戻すときの URL。
     * 差し戻した入力（saveFormValues）の template を newtemplate で渡し、
     * 戻った画面がそのテンプレートの TV を読めるようにする。親（parent）は差し戻した入力で上書きされる
     */
    public function formReturnUrl($action, $id = 0, $template = 0): string
    {
        $url = sprintf('index.php?a=%d', (int)$action);
        if ((int)$id > 0) {
            $url .= sprintf('&id=%d', (int)$id);
        }
        if ((int)$template > 0) {
            $url .= sprintf('&newtemplate=%d', (int)$template);
        }
        return $url;
    }

    /**
     * 日付の入力欄（value 属性）に入れる値を返す。出力用にエスケープ済み。
     * 数値（保存済みの時刻）は日付の書式に直し（0 は日付なしとして空）、
     * 差し戻された入力（解釈できない日付を含む）は文字列のまま返す
     *
     * @param int|string|null $value 時刻の数値、または差し戻された入力の文字列
     * @param string $mode toDateFormat() の書式（日付だけなら 'dateOnly'）
     */
    public function dateInputValue($value, string $mode = ''): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }
        if ($value === null || $value === '' || is_int($value) || preg_match('/^-?[0-9]+$/', (string)$value)) {
            if ((int)$value === 0) {
                return '';
            }
            return (string)evo()->toDateFormat($value, $mode);
        }
        return hsc($value);
    }

    /**
     * 差し戻された日付の入力を、解釈できればタイムスタンプに、できなければ入力の文字列のまま返す。
     * dateInputValue() で入力欄に出すと、解釈できない入力は打ったとおりに残る
     *
     * @param mixed $value 差し戻された入力
     * @return int|mixed
     */
    public function restoredDateValue($value)
    {
        $timestamp = evo()->parseDateInput($value);
        return $timestamp === null ? $value : $timestamp;
    }

    /**
     * 要素の編集画面で、差し戻された入力があれば $content に重ねて返す。
     * フォームの項目名を画面が読む形に直す（categoryid → category、post → $codeKey、
     * チェックボックスは 0/1）。差し戻しでなければ $content をそのまま返す
     *
     * @param array $content DB から読んだ値
     * @param string $codeKey コードの項目名（plugincode など）。フォームの post をここへ写す。無ければ空文字
     * @param string[] $checkboxes チェックボックスの項目名（locked など）
     * @param string[] $lists チェックボックスの複数選択の項目名（sysevents・user_groups など）。
     *                        未選択だと送信されないので、差し戻しのときは空配列にして DB の値と区別する
     * @return array
     */
    public function restoreElementFormValues(array $content, string $codeKey = '', array $checkboxes = ['locked'], array $lists = []): array
    {
        if (!$this->hasFormValues()) {
            return $content;
        }
        $values = $this->loadFormValues();
        // 別の要素（新規か id が違う）向けの入力は混ぜない
        if ((int)($values['id'] ?? 0) !== get_intvar('id')) {
            return $content;
        }
        if (isset($values['categoryid'])) {
            $values['category'] = $values['categoryid'];
        }
        if ($codeKey !== '' && isset($values['post'])) {
            $values[$codeKey] = $values['post'];
        }
        foreach ($checkboxes as $name) {
            $values[$name] = !empty($values[$name]) ? 1 : 0;
        }
        foreach ($lists as $name) {
            $values[$name] = (array)($values[$name] ?? []);
        }
        return array_merge($content, $values);
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
            if ((string)$row['category'] === (string)$newCat) {
                return $row['id'];
            }
        }
        return 0;
    }

    /**
     * 要素の保存で使うカテゴリの id を返す。新しいカテゴリ名が空でなければ、同じ名前を探し、無ければ作る。
     * 名前の確かめ（空・重複・予約名）をすべて通ったあと、insert・update の直前に呼ぶ（差し戻しでカテゴリを残さないため）。
     *
     * @param mixed $categoryId 送信された categoryid（to_intvar() で読む。0 以下や整数の形でない値は 0）
     * @param mixed $newCategory 送信された newcategory（生の値。エスケープしない）
     */
    public function resolveElementCategory($categoryId, $newCategory): int
    {
        $name = $this->normalizeElementName($newCategory);
        if ($name === '') {
            return to_intvar($categoryId);
        }
        $id = $this->checkCategory($name);
        if ($id) {
            return (int)$id;
        }
        return (int)$this->newCategory($name);
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

    /**
     * 要素の編集画面のカテゴリの選択（select）の option 一式を返す。
     * 「カテゴリーなし」(0)・既存のカテゴリ・「>> カテゴリー作成」(-1) の順。
     * $selectedId と値が一致する option に selected を付ける（-1 は差し戻した画面で新しいカテゴリを戻すときに使う）。
     *
     * @param mixed $selectedId 選ぶ option の値
     * @return string エスケープ済みの HTML
     */
    public function elementCategoryOptions($selectedId): string
    {
        $selectedId = (int)$selectedId;
        $options = [[0, lang('no_category')]];
        foreach ($this->getCategories() as $row) {
            $options[] = [(int)$row['id'], $row['category']];
        }
        $options[] = [-1, '>> ' . lang('new_category')];

        $html = '';
        foreach ($options as [$id, $label]) {
            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                $id,
                $id === $selectedId ? ' selected="selected"' : '',
                hsc($label)
            );
        }
        return $html;
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
     * プラグインを id で 1 件削除する（イベントの割り当ても消し、削除の前後のイベントを呼ぶ）。
     * 権限の確かめとキャッシュの削除は呼び出し側で行う。
     *
     * @param int $id プラグインの id
     * @return bool 削除の SQL がすべて通ったら true
     */
    public function deletePlugin($id)
    {
        $id = (int) $id;
        if ($id < 1) {
            return false;
        }

        $params = ['id' => $id];
        evo()->invokeEvent('OnBeforePluginFormDelete', $params);

        if (!db()->delete('[+prefix+]site_plugins', "id='{$id}'")) {
            return false;
        }
        if (!db()->delete('[+prefix+]site_plugin_events', "pluginid='{$id}'")) {
            return false;
        }

        $params = ['id' => $id];
        evo()->invokeEvent('OnPluginFormDelete', $params);
        return true;
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

    /**
     * ロールが access_permissions と web_access_permissions の両方を持つか。
     * バックアップの復元・ファイルブラウザー・モジュールの実行・ユーザーの削除などにも使うので、
     * 文書グループの絞り込みの判定には使わない（bypassesDocumentGroups() を使う。#1338）
     *
     * @return bool
     */
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

    /**
     * 操作に必要なロールの権限を、OPERATION_PERMISSIONS の表から引いて判定する。
     * 操作の名前は表のキー（'document.move' など）。表は権限の組み合わせだけを持つので、
     * 範囲・文書グループが要る操作は、呼ぶ側が canAccessDocument() などを合わせる
     *
     * @param string $operation OPERATION_PERMISSIONS のキー
     * @return bool
     * @throws InvalidArgumentException 知らない操作のとき
     */
    public function can(string $operation): bool
    {
        if (!isset(self::OPERATION_PERMISSIONS[$operation])) {
            throw new InvalidArgumentException("Unknown operation: {$operation}");
        }
        $rule = self::OPERATION_PERMISSIONS[$operation];
        foreach ($rule['all'] ?? [] as $permission) {
            if (!hasPermission($permission)) {
                return false;
            }
        }
        if (!isset($rule['any'])) {
            return true;
        }
        foreach ($rule['any'] as $permission) {
            if (hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * その種類のエレメントの管理を開けるか（新規作成か編集のどちらかの権限がある）。
     * 使う場所: エレメントの管理（a=76。element/resources.static.php）のタブと一覧、
     * メニューのモジュールの管理（frames/menu.php）、ダッシュボードのモジュールのアイコン
     * （main/welcome.static.php）。作成・編集できる人だけに出す入口で、モジュール管理（a=106）の画面は
     * exec_module だけでも開ける（permissions.md の分かっている課題を参照）
     *
     * @param string $type ELEMENT_TYPES の値（template・snippet・chunk・plugin・module）
     * @return bool
     * @throws InvalidArgumentException 知らない種類のとき
     */
    public function canManageElement($type)
    {
        if (!in_array($type, self::ELEMENT_TYPES, true)) {
            throw new InvalidArgumentException("Unknown element type: {$type}");
        }
        return $this->can("element.{$type}.manage");
    }

    /**
     * エレメントの管理（a=76）を開けるか（メニューの項目の出し分け）。
     * テンプレート・スニペット・チャンク・プラグインのどれかを管理できる（モジュールは別の入口 a=106）。
     * 使う場所: manager/frames/menu.php
     *
     * @return bool
     */
    public function canOpenElementManager()
    {
        foreach (array_diff(self::ELEMENT_TYPES, ['module']) as $type) {
            if ($this->canManageElement($type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * テンプレート・スニペット・チャンク・プラグイン・モジュールのどれかを保存できるか。
     * 使う場所: カテゴリの削除（delete_category.processor.php）と、エレメントの管理の削除リンク
     *
     * @return bool
     */
    public function canSaveAnyElement()
    {
        foreach (self::ELEMENT_TYPES as $type) {
            if ($this->can("element.{$type}.save")) {
                return true;
            }
        }
        return false;
    }

    /**
     * そのモジュールを実行してよいか。実行（a=112）の処理・メインメニュー・ダッシュボード・
     * モジュール管理の一覧の実行のメニューがこの判定を使う（#1408・#1357）。
     * exec_module を持つこと。そのうえで、管理者（isAdmin()）は全部、それ以外は use_udperms が有効なときだけ、
     * モジュールのグループ（site_module_access）で絞る。グループの指定が無いモジュールは誰でも、
     * 指定があれば所属者だけ。use_udperms が無効のときに絞らないのは、グループを付け外しする欄と保存が
     * 有効なときだけ働くので、有効だった時期の結びつきが見えないまま効き始めないようにするため
     *
     * @param int $moduleId
     * @return bool
     */
    public function canExecuteModule(int $moduleId): bool
    {
        if (!$this->can('module.execute')) {
            return false;
        }
        if ($this->isAdmin() || evo()->config('use_udperms') != 1) {
            return true;
        }
        $access = $this->moduleGroupAccess();
        return !isset($access[$moduleId]) || $access[$moduleId];
    }

    /**
     * 実行してよいモジュールの一覧（無効のモジュールは含めない）。canExecuteModule() で絞る。
     * 使う場所: メインメニュー（frames/menu.php）・ダッシュボード（main/welcome.static.php）
     *
     * @return array[] id・name・icon の行の配列（editedon の新しい順）
     */
    public function getExecutableModules(): array
    {
        if (!$this->can('module.execute')) {
            return [];
        }
        $rs = db()->select(
            'id, name, icon',
            '[+prefix+]site_modules',
            'disabled != 1',
            'editedon DESC'
        );
        $modules = [];
        while ($row = db()->getRow($rs)) {
            if ($this->canExecuteModule((int)$row['id'])) {
                $modules[] = $row;
            }
        }
        return $modules;
    }

    /**
     * グループの指定があるモジュールについて、ログイン中の利用者がどれかのグループに所属しているか。
     * グループの指定が無いモジュールのキーは無い。リクエスト内でキャッシュする
     *
     * @return bool[] モジュールの ID => 所属しているか
     */
    private function moduleGroupAccess(): array
    {
        if ($this->moduleGroupAccessCache !== null) {
            return $this->moduleGroupAccessCache;
        }
        $rs = db()->select(
            'sma.module, sma.usergroup, mg.member',
            '[+prefix+]site_module_access sma'
            . ' LEFT JOIN [+prefix+]member_groups mg ON mg.user_group = sma.usergroup AND mg.member = '
            . (int)evo()->getLoginUserID()
        );
        $access = [];
        while ($row = db()->getRow($rs)) {
            $moduleId = (int)$row['module'];
            $access[$moduleId] = ($access[$moduleId] ?? false) || ($row['usergroup'] && $row['member']);
        }
        return $this->moduleGroupAccessCache = $access;
    }

    /**
     * 文書グループの「衝突」の知らせを出すか。
     * 出すグループがあるのに、利用者がどれにも所属していない（そのため選べるグループが無い）。
     * 管理者（isAdmin()）は対象外で、グループの権限（access_permissions・web_access_permissions）の
     * どちらかを持つときだけ。
     * 使う場所: 文書の編集・下書きの編集画面（mutate_content.dynamic.php・mutate_draft.dynamic.php）。
     * use_udperms が有効なときだけ呼ぶ前提（ここでは use_udperms を見ない）
     *
     * @return bool
     */
    public function hasDocumentGroupCollision()
    {
        if ($this->isAdmin()) {
            return false;
        }
        $access = $this->documentGroupAccess();
        if ($access['yes'] != 0 || $access['no'] <= 0) {
            return false;
        }
        return hasPermission('access_permissions') || hasPermission('web_access_permissions');
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
        $role = $this->resolveMgrRole($role);
        sessionv('*mgrRole', $role);
        sessionv('*mgrPermissions', $this->getMgrPermissions($role));
    }

    /**
     * いまのロールを決める。差し替えられるのは、DB のロールが 1 の利用者だけ。
     * $forceRole が null のときは、ログインで残した差し替え（mgrForceRole）を使い、
     * DB のロールが 1 でなくなっていれば（降格など）、それを捨てて DB のロールにする。
     * ログイン時は、指定された候補を $forceRole に渡す（セッションには触れない）
     */
    public function resolveMgrRole($dbRole, $forceRole = null)
    {
        $fromSession = $forceRole === null;
        $forceRole = (int) ($fromSession ? sessionv('mgrForceRole', 0) : $forceRole);
        if ($forceRole && (int) $dbRole === 1) {
            return $forceRole;
        }
        if ($fromSession && $forceRole) {
            sessionv('*mgrForceRole', null);
        }
        return $dbRole;
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
     * 1 つの文書の非公開の印（privatemgr・privateweb）を、実際の所属から求め直す。
     * id が 0 や空だと setMgrDocsAsPrivate()・setWebDocsAsPrivate() は全文書を計算するので、
     * その落とし穴をここで防ぎ、正の id のときだけ行う。
     */
    public function refreshDocumentPrivateFlags($docId)
    {
        $docId = (int)$docId;
        if ($docId < 1) {
            return;
        }
        $this->setWebDocsAsPrivate($docId);
        $this->setMgrDocsAsPrivate($docId);
    }

    /**
     * 管理側の非公開の印（privatemgr）を計算し直す
     *
     * 文書が付く文書グループのどれかに、管理側のユーザーグループへのアクセス
     * （membergroup_access）の結びつきがあれば 1、無ければ 0 にする。
     * 0 と 1 は 1 回の UPDATE で書くので、計算の途中で非公開の文書の印が 0 に戻る間はない。
     *
     * @param int|string $docid 正の id ならその文書だけ。正の整数にならない値（0 以下・空・数字でない文字列）は全文書
     * @return string 印を 1 にした文書の id のカンマ区切り（無ければ ''）
     */
    public function setMgrDocsAsPrivate($docid = '')
    {
        return $this->recalcPrivateFlag(
            'privatemgr',
            'membergroup_access',
            'mga',
            (int)$docid
        );
    }

    /**
     * Web 側の非公開の印（privateweb）を計算し直す
     *
     * 文書が付く文書グループのどれかに、Web 側のユーザーグループへのアクセス
     * （webgroup_access）の結びつきがあれば 1、無ければ 0 にする。
     * 0 と 1 は 1 回の UPDATE で書くので、計算の途中で非公開の文書の印が 0 に戻る間はない。
     *
     * @param int|string $docid 正の id ならその文書だけ。正の整数にならない値（0 以下・空・数字でない文字列）は全文書
     * @return string 印を 1 にした文書の id のカンマ区切り（無ければ ''）
     */
    public function setWebDocsAsPrivate($docid = '')
    {
        return $this->recalcPrivateFlag(
            'privateweb',
            'webgroup_access',
            'wga',
            (int)$docid
        );
    }

    /**
     * 非公開の印の列を 1 回の UPDATE で書き直す
     *
     * 結びつきのある文書を派生表にして LEFT JOIN で 1 回の UPDATE にする。派生表は site_content を
     * 読まない（更新する表は副問い合わせで読めないため）。IN (副問い合わせ) の形にすると、
     * MySQL 5.7 では文書 1 行ごとに副問い合わせを走らせて遅くなる。1 件のときは派生表も
     * その文書の結びつきだけに絞る。
     * JOIN 付きの UPDATE は db()->update() の想定する使い方ではないため、db()->query() で流す。
     * $column・$accessTable・$alias は呼び出し元が固定で渡す。$docid は (int) 済みの値。
     */
    private function recalcPrivateFlag($column, $accessTable, $alias, $docid)
    {
        if ($docid > 0) {
            $linkedDocs = sprintf(' AND dg.document = %d', $docid);
            $where = sprintf('sc.id = %d', $docid);
        } else {
            $linkedDocs = '';
            $where = sprintf('(sc.%s = 1 OR x.document IS NOT NULL)', $column);
        }

        db()->query(
            db()->replaceFullTableName(
                sprintf(
                    'UPDATE [+prefix+]site_content sc'
                    . ' LEFT JOIN (SELECT DISTINCT dg.document FROM [+prefix+]document_groups dg'
                    . ' INNER JOIN [+prefix+]%s %s ON %s.documentgroup = dg.document_group'
                    . ' WHERE %s.id > 0%s) x ON x.document = sc.id'
                    . ' SET sc.%s = IF(x.document IS NULL, 0, 1) WHERE %s',
                    $accessTable,
                    $alias,
                    $alias,
                    $alias,
                    $linkedDocs,
                    $column,
                    $where
                )
            )
        );

        // 戻り値（印が付いた id）は UPDATE の後に求める
        $rs = db()->select(
            'id',
            '[+prefix+]site_content',
            $docid > 0
                ? sprintf('id = %d AND %s = 1', $docid, $column)
                : sprintf('%s = 1', $column)
        );
        return implode(',', db()->getColumn('id', $rs) ?: []);
    }

    /**
     * 文書グループを削除する
     *
     * 文書グループは管理側・Web 側で共通の表なので、どちらの画面から消しても
     * 文書グループの id を持つ 5 つの表（install/sql/create_tables.sql）
     * document_groups・documentgroup_names・membergroup_access・
     * site_tmplvar_access・webgroup_access の行をすべて消す。
     * そのグループに付いていた文書があれば、privatemgr / privateweb を全件で計算し直し、
     * 印ごとキャッシュされたページを消すためページキャッシュも消す。
     *
     * @param int $groupId
     * @return bool 失敗したら false
     */
    public function deleteDocumentGroup($groupId)
    {
        $groupId = (int)$groupId;
        if ($groupId <= 0) {
            return false;
        }

        // 削除の後では分からなくなるので、このグループに付いた文書があるかを先に確かめる
        $hasDocuments = (bool)db()->getValue(
            db()->select(
                'document',
                '[+prefix+]document_groups',
                sprintf('document_group=%d', $groupId),
                '',
                1
            )
        );

        $result = true;
        $targets = [
            ['[+prefix+]documentgroup_names', 'id'],
            ['[+prefix+]document_groups', 'document_group'],
            ['[+prefix+]membergroup_access', 'documentgroup'],
            ['[+prefix+]site_tmplvar_access', 'documentgroup'],
            ['[+prefix+]webgroup_access', 'documentgroup'],
        ];
        foreach ($targets as [$table, $column]) {
            if (!db()->delete($table, sprintf('%s=%d', $column, $groupId))) {
                $result = false;
            }
        }

        if ($hasDocuments) {
            $this->setMgrDocsAsPrivate();
            $this->setWebDocsAsPrivate();
            evo()->clearCache(['target' => 'pagecache']);
        }

        return $result;
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
     * 親がルート（0）のときは、文書グループの確認の代わりに canPlaceAtRoot() で決める。
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
        // ルートへの変更は、文書グループへのアクセスではなく「ルートに置いてよいか」で決める
        if ($parentId === 0) {
            return $this->canUseRootAsParent() ? '' : lang('access_permission_parent_denied');
        }
        if (!evo()->checkPermissions($parentId) || !$this->isInAllowedParents($parentId)) {
            return lang('access_permission_parent_denied');
        }
        return '';
    }

    /**
     * 文書が、許可するリソースの範囲の中か。判定は isInAllowedParents() と同じ（範囲の判定の正）。
     * $id が 0 のときは、リクエストの pid を見る（pid が無ければ true）。
     * 外部のコードが使うことがあるので残している。新しいコードは isInAllowedParents() を使う
     *
     * @param int $id
     * @return bool
     */
    public function isAllowed($id)
    {
        if (!$id) {
            // pid が送られたかどうかだけを見る（'0'・空は無いものと同じ）
            if (!anyv('pid')) {
                return true;
            }

            // 形式違い・配列は 0（ルート）になり、ルートが許可の一覧に無ければ拒否される
            $id = request_intvar('pid');
        }

        return $this->isInAllowedParents($id);
    }

    /**
     * ツリー・一覧に出してよい文書か。範囲の中か、範囲の頂点の祖先（そこへたどる途中の文書）なら true。
     * 表示用で、行ごとに呼ばれるため DB に問い合わせず、getUserAllowedDocs() の集合（リクエスト内でキャッシュ）で判定する。
     * ルート（0 以下）は常に true。リクエストの値は読まない。保存の判定は鮮度が要るので isInAllowedParents() を使う
     *
     * @param int $id
     * @return bool
     */
    public function isContainAllowed($id)
    {
        $id = (int)$id;
        if ($id <= 0 || !evo()->conf_var('allowed_parents')) {
            return true;
        }
        $allowed = $this->getAllowedParentIds();
        if (in_array(0, $allowed, true)) {
            return true;
        }
        if (!$allowed) {
            return false;
        }
        $this->getUserAllowedDocs();
        return isset($this->allowedDocIdsCache[2][$id])
            || in_array($id, $this->allowedParentAncestorIds(), true);
    }

    /**
     * 文書が、ログイン中の利用者の「許可するリソース」（allowed_parents）の範囲の中かを返す。
     * 文書を変える処理から、確かめる文書や作成先・移動先の親の ID を明示して呼ぶ。リクエストの値は読まない。
     * 設定が空なら常に true。設定があるのに正しい ID が 1 つも無いときは false。
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

    /**
     * 範囲の中の文書の ID の一覧（許可するリソースに書いた ID と、その子孫すべて）。
     * isInAllowedParents() と同じ基準（深さの上限なし・削除済みも含む）。
     * 一覧の大きさが問題になる絞り込みには allowedDocsCondition() を使う
     *
     * @return int[]|string 制限なし（設定が空、または 0 を含む）なら []、正しい ID が 1 つも無いなら ''、
     *                      それ以外は範囲の中の ID の配列
     */
    public function getUserAllowedDocs()
    {
        $setting = (string)evo()->conf_var('allowed_parents');
        if ($setting === '') {
            return [];
        }
        $parents = $this->getAllowedParentIds();
        if (!$parents) {
            return '';
        }
        if (in_array(0, $parents, true)) {
            return [];
        }
        if ($this->allowedDocIdsCache === null || $this->allowedDocIdsCache[0] !== $setting) {
            $ids = $this->collectAllowedDocIds();
            $this->allowedDocIdsCache = [$setting, $ids, array_flip($ids)];
        }
        return $this->allowedDocIdsCache[1];
    }

    /**
     * 範囲の中の文書だけに絞る SQL の条件式（前に AND は付けない）。制限なし（設定が空、または 0 を含む）なら空文字。
     * 結果は isInAllowedParents() が true になる文書と同じ。
     * 祖先をたどる条件を SQL で書くと深さが固定になるため、許可した親から子孫の ID を集めて IN で絞る
     *
     * @param string $alias site_content の別名（呼ぶ側の固定の文字列。別名が無い問い合わせは空）
     * @return string
     * @throws InvalidArgumentException $alias が識別子でないとき
     */
    public function allowedDocsCondition($alias = 'sc')
    {
        $alias = (string)$alias;
        if ($alias !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Invalid table alias');
        }
        if (!evo()->conf_var('allowed_parents') || in_array(0, $this->getAllowedParentIds(), true)) {
            return '';
        }
        $ids = array_map('intval', (array)$this->getUserAllowedDocs());
        return sprintf(
            '%sid IN (%s)',
            $alias === '' ? '' : $alias . '.',
            $ids ? implode(',', $ids) : '0'
        );
    }

    /**
     * 許可した親から、子孫を深さの上限なし・削除済みも含めて集める（循環しても止まる）
     *
     * @return int[]
     */
    private function collectAllowedDocIds()
    {
        $found = [];
        $level = array_map('intval', $this->getAllowedParentIds());
        foreach ($level as $id) {
            if ($id > 0) {
                $found[$id] = true;
            }
        }
        while ($level) {
            $rs = db()->select(
                'id',
                '[+prefix+]site_content',
                sprintf('parent IN (%s)', implode(',', $level))
            );
            $level = [];
            while ($row = db()->getRow($rs)) {
                $child = (int)$row['id'];
                if (!isset($found[$child])) {
                    $found[$child] = true;
                    $level[] = $child;
                }
            }
        }
        return array_keys($found);
    }

    /**
     * 許可した親それぞれの祖先の ID（範囲の頂点へたどる途中の文書。ツリーに出す・初期に開くために使う）。
     * 深さの上限なしで DB の parent をたどる
     *
     * @return int[]
     */
    public function allowedParentAncestorIds()
    {
        $setting = (string)evo()->conf_var('allowed_parents');
        if ($setting === '') {
            return [];
        }
        if ($this->allowedAncestorIdsCache === null || $this->allowedAncestorIdsCache[0] !== $setting) {
            $ids = [];
            foreach ($this->getAllowedParentIds() as $parent) {
                foreach ($this->getAncestorIds($parent) as $ancestor) {
                    $ids[$ancestor] = $ancestor;
                }
            }
            $this->allowedAncestorIdsCache = [$setting, array_values($ids)];
        }
        return $this->allowedAncestorIdsCache[1];
    }

    // udperms_allowroot の設定（向き A の材料）。ルートに置けるかの判定には canPlaceAtRoot() を使う。
    // udperms_allowroot は文書グループの設定の 1 つなので、文書グループの絞り込みと同じ管理者の判定を使う
    public function isAllowroot()
    {
        return $this->bypassesDocumentGroups() || (bool)evo()->config('udperms_allowroot');
    }

    /**
     * 文書をルート（親が 0）に置いてよいか。ルートに置く入口（新規・保存での親の変更・移動・複製・
     * 親の選択・作成のボタン）は、すべてこの判定を使う。
     *
     * 考え方（向き B）: udperms_allowroot は文書グループの設定の 1 つで、use_udperms が有効なときだけ効く
     * （設定の画面でも有効なときだけ見える）。use_udperms が無効なら誰でもルートに置ける。
     * 向き A（use_udperms に関係なく udperms_allowroot で決める）に切り替えるなら、この 1 行だけを
     * isAllowroot() にすればよい。
     *
     * 許可するリソースの範囲（isInAllowedParents(0)）は別の判定なので、ここには混ぜない。
     *
     * @return bool
     */
    public function canPlaceAtRoot()
    {
        return evo()->config('use_udperms') != 1 || $this->isAllowroot();
    }

    // PHP の設定から求めたアップロードサイズの上限（バイト数）。無制限なら 0
    public function getUploadMaxsize()
    {
        $limits = [];
        foreach (['upload_max_filesize', 'post_max_size', 'memory_limit'] as $key) {
            $bytes = ini_size_to_bytes(ini_get($key));
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }
        return $limits ? min($limits) : 0;
    }

    /**
     * この利用者が付け外ししてよい文書グループ（画面と保存処理、ddAutoFolders の共通の判定）
     * 変えてよいグループは、権限が無い側の非公開のグループを除き、
     * 管理者でなければ出すグループのどれかに所属している場合に限る
     *
     * @return array ids: 変えてよいグループの id（キー）／ yes・no: 出すグループのうち、所属している数・いない数
     */
    public function documentGroupAccess()
    {
        $rs = db()->select(
            'id, private_memgroup, private_webgroup',
            '[+prefix+]documentgroup_names'
        );
        $ids = [];
        $yes = 0;
        $no = 0;
        while ($row = db()->getRow($rs)) {
            if (!evo()->hasPermission('access_permissions') && $row['private_memgroup'] == 1) {
                continue;
            }
            if (!evo()->hasPermission('web_access_permissions') && $row['private_webgroup'] == 1) {
                continue;
            }
            $groupId = (int)$row['id'];
            $ids[$groupId] = true;
            if ($this->countDocumentGroupMembership($groupId) > 0) {
                ++$yes;
            } else {
                ++$no;
            }
        }
        if (!$this->isAdmin() && !$yes && $no) {
            $ids = [];
        }
        return ['ids' => $ids, 'yes' => $yes, 'no' => $no];
    }

    // 利用者が文書グループ $groupId にアクセスできる、マネージャーグループとウェブグループの数の合計
    private function countDocumentGroupMembership($groupId)
    {
        $groupId = (int)$groupId;
        $userId = (int)sessionv('mgrInternalKey');
        $mgr = db()->getValue(
            db()->select(
                'COUNT(mg.id)',
                '[+prefix+]membergroup_access mga, [+prefix+]member_groups mg',
                sprintf(
                    'mga.membergroup=mg.user_group AND mga.documentgroup=%d AND mg.member=%d',
                    $groupId,
                    $userId
                )
            )
        );
        $web = db()->getValue(
            db()->select(
                'COUNT(mg.id)',
                '[+prefix+]webgroup_access mga, [+prefix+]web_groups mg',
                sprintf(
                    'mga.webgroup=mg.webgroup AND mga.documentgroup=%d AND mg.webuser=%d',
                    $groupId,
                    $userId
                )
            )
        );
        return (int)$mgr + (int)$web;
    }

    /**
     * 送られてきた「グループの id,印」（例: 1,new）から、グループの id を取り出す
     *
     * @param string $valuePair
     * @return int
     */
    public function postedDocumentGroupId($valuePair)
    {
        return (int)strtok((string)$valuePair, ',');
    }

    /**
     * 送られた文書グループ（document_groups）のうち、この利用者が変えてよいものの id の一覧。
     * 文書グループの権限（use_udperms）が有効でないとき、「すべての文書グループ」（chkalldocs）が on のとき、
     * 配列でないときは空。文字列でない要素は無いものとして扱う
     *
     * @param array|null $access documentGroupAccess() の結果。すでに持っていれば渡して問い合わせを省く
     * @return int[]
     */
    public function postedChangeableDocumentGroupIds($access = null)
    {
        if (evo()->config('use_udperms') != 1 || postv('chkalldocs') === 'on') {
            return [];
        }
        $posted = postv('document_groups');
        if (!is_array($posted)) {
            return [];
        }
        $access = $access ?: $this->documentGroupAccess();
        $groupIds = [];
        foreach ($posted as $valuePair) {
            if (!is_string($valuePair)) {
                continue;
            }
            $groupId = $this->postedDocumentGroupId($valuePair);
            if ($groupId && isset($access['ids'][$groupId])) {
                $groupIds[$groupId] = $groupId;
            }
        }
        return array_values($groupIds);
    }

    /**
     * 新規の文書に付く文書グループの id。
     * 変えてよいグループ（documentGroupAccess() の ids）は送られた値に従い、
     * 変えてよくないグループは親から引き継ぐ（親の守りを子に伝える。ids が空ならすべて引き継ぐ）。
     * use_udperms が無効、または親が 0 で送られた値も無いときは空
     *
     * @param int $parentId 親の文書（0 は引き継ぎなし）
     * @param array|null $access documentGroupAccess() の結果。すでに持っていれば渡して問い合わせを省く
     * @return int[] 文書グループの id（昇順）
     */
    public function newDocumentGroupIds($parentId, $access = null)
    {
        if (evo()->config('use_udperms') != 1) {
            return [];
        }
        $parentId = (int)$parentId;
        $access = $access ?: $this->documentGroupAccess();
        $groups = [];
        foreach ($this->postedChangeableDocumentGroupIds($access) as $groupId) {
            $groups[(int)$groupId] = true;
        }
        if ($parentId) {
            foreach ($this->currentDocumentGroupIds($parentId) as $groupId) {
                if (!isset($access['ids'][$groupId])) {
                    $groups[$groupId] = true;
                }
            }
        }
        $groups = array_keys($groups);
        sort($groups);
        return $groups;
    }

    /**
     * 更新後の文書に付く文書グループの id。
     * 変えてよいグループは送られた値に置き換え、変えてよくないグループは今付いているものを残す。
     * use_udperms が無効のときは今付いているものをそのまま返す（保存では変えない）
     *
     * @param int $docId 更新する文書
     * @param array|null $access documentGroupAccess() の結果。すでに持っていれば渡して問い合わせを省く
     * @param int[]|null $current currentDocumentGroupIds() の結果。すでに持っていれば渡して問い合わせを省く
     * @return int[] 文書グループの id（昇順）
     */
    public function updatedDocumentGroupIds($docId, $access = null, $current = null)
    {
        $current = $current ?? $this->currentDocumentGroupIds($docId);
        if (evo()->config('use_udperms') != 1) {
            return $current;
        }
        $access = $access ?: $this->documentGroupAccess();
        $groups = [];
        foreach ($current as $groupId) {
            if (!isset($access['ids'][$groupId])) {
                $groups[$groupId] = true;
            }
        }
        foreach ($this->postedChangeableDocumentGroupIds($access) as $groupId) {
            $groups[(int)$groupId] = true;
        }
        $groups = array_keys($groups);
        sort($groups);
        return $groups;
    }

    /**
     * 文書に今付いている文書グループの id
     *
     * @param int $docId
     * @return int[] 昇順、重複なし
     */
    public function currentDocumentGroupIds($docId)
    {
        $groups = [];
        $rs = db()->select(
            'DISTINCT document_group',
            '[+prefix+]document_groups',
            sprintf('document=%d', (int)$docId),
            'document_group'
        );
        while ($row = db()->getRow($rs)) {
            $groups[] = (int)$row['document_group'];
        }
        return $groups;
    }

    /**
     * 文書グループの絞り込みを受けない管理者か（#1338）。
     * アクセスの規則（ユーザーグループの権限・ロール）を自分で変えられる人: isAdmin()、
     * または save_role を持つ人（自分のロールに access_permissions・web_access_permissions を付けて
     * isAdmin() になれるため、save_role だけのロールを絞っても守りにならない）。
     * use_udperms は見ない（公開側の判定にも使うため）。管理画面の絞り込みは restrictsByDocumentGroups() を通す
     *
     * @return bool
     */
    public function bypassesDocumentGroups()
    {
        return $this->isAdmin() || evo()->hasPermission('save_role');
    }

    /**
     * ログイン中の利用者の所属（セッション mgrDocgroups）を、重複のない正の整数の配列で返す。
     * mgrDocgroups は管理画面のリクエストごとに accesscontrol.inc.php が読み直す
     *
     * @return int[]
     */
    public function userMgrDocumentGroupIds()
    {
        $ids = array_filter(array_map('intval', (array)sessionv('mgrDocgroups', [])), function ($id) {
            return $id > 0;
        });
        return array_values(array_unique($ids));
    }

    /**
     * 管理画面で文書を文書グループで絞るか。use_udperms が 1 で、管理者でないとき
     *
     * @return bool
     */
    public function restrictsByDocumentGroups()
    {
        return evo()->config('use_udperms') == 1 && !$this->bypassesDocumentGroups();
    }

    /**
     * 文書グループで絞るときの SQL の条件式（前に AND は付けない）。絞らないときは空文字。
     * 「届く」文書: privatemgr=0、または所属のグループに入っている文書。
     * privatemgr の列で見るこの式と、保存前の集合で見る canOpenWithDocumentGroups() の定義は同じ
     * （所属のグループはすべて管理側で非公開）。privatemgr の印が実際の所属と合わない経路は #1353。
     * 結合に頼らない副問い合わせなので、どの問い合わせにも入れられる
     *
     * @param string $alias site_content の別名（呼ぶ側の固定の文字列。別名が無い問い合わせは空）
     * @return string
     * @throws InvalidArgumentException $alias が識別子でないとき
     */
    public function documentAccessCondition($alias = 'sc')
    {
        $alias = (string)$alias;
        if ($alias !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Invalid table alias');
        }
        if (!$this->restrictsByDocumentGroups()) {
            return '';
        }
        $a = $alias === '' ? '' : $alias . '.';
        $groupIds = $this->userMgrDocumentGroupIds();
        if (!$groupIds) {
            return "{$a}privatemgr=0";
        }
        return sprintf(
            '(%sprivatemgr=0 OR %sid IN (SELECT document FROM %s WHERE document_group IN (%s)))',
            $a,
            $a,
            db()->getFullTableName('document_groups'),
            implode(',', $groupIds)
        );
    }

    /**
     * 文書を、この利用者が文書グループの面で管理画面で扱ってよいか。絞らないなら true。
     * 絞るとき（restrictsByDocumentGroups() が真のとき）に限り、存在しない文書は false。
     * 絞らないときは id によらず true（checkPermissions() の修正前と同じ）
     *
     * @param int|string $docId
     * @return bool
     */
    public function canAccessDocument($docId)
    {
        $cond = $this->documentAccessCondition('sc');
        if ($cond === '') {
            return true;
        }
        $rs = db()->select(
            'COUNT(sc.id)',
            '[+prefix+]site_content sc',
            sprintf('sc.id=%d AND %s', (int)$docId, $cond)
        );
        return (int)db()->getValue($rs) === 1;
    }

    /**
     * 文書の編集画面（a=27・131・132）を開けない理由を返す。開けるなら空文字。
     * 理由の順は、許可するリソースの範囲（isInAllowedParents()）、view_document の権限、文書グループ（canAccessDocument()）。
     * 入口は理由ごとにエラーの出し方を変える。リクエストの値は読まない
     *
     * @param int|string $docId
     * @return string 'invalid_id'|'out_of_range'|'no_permission'|'document_group'|''
     */
    public function documentEditorDenial($docId)
    {
        $docId = (int)$docId;
        if ($docId <= 0) {
            return 'invalid_id';
        }
        if (!$this->isInAllowedParents($docId)) {
            return 'out_of_range';
        }
        if (!evo()->hasPermission('view_document')) {
            return 'no_permission';
        }
        if (!$this->canAccessDocument($docId)) {
            return 'document_group';
        }
        return '';
    }

    /**
     * 文書の編集画面を、この利用者が開けるか（documentEditorDenial() が空か）。
     * 保存できるかは save_document（呼ぶ側が見る）。QuickManager のボタンの表示と TV の保存が使う
     *
     * @param int|string $docId
     * @return bool
     */
    public function canOpenDocumentEditor($docId)
    {
        return $this->documentEditorDenial($docId) === '';
    }

    /**
     * 親の下に文書を新規作成する画面（a=4 に pid を付けたもの）を、この利用者が開けるか。
     * 新規作成の権限（can('document.create')。保存 a=5 が求める new_document と save_document）、
     * 許可するリソースの範囲（isInAllowedParents()。保存の処理と同じ）、親の文書グループ（canAccessDocument()）を見る。
     * 親が 0 以下（ルート）のときは、保存の処理と同じく canPlaceAtRoot() と、ルートが許可するリソースの範囲の中にあるか（isInAllowedParents(0)）の両方を見る。
     * リクエストの値は読まない。メニュー・ダッシュボード・ツリー・文書一覧・QuickManager（追加ボタンとカスタムの「new」ボタン）・
     * 保存後の「続けて新規」（save_resource.processor.php）が使う
     *
     * @param int|string $parentId
     * @return bool
     */
    public function canCreateDocumentUnder($parentId)
    {
        if (!$this->can('document.create')) {
            return false;
        }
        $parentId = (int)$parentId;
        if ($parentId <= 0) {
            return $this->canUseRootAsParent();
        }
        return $this->isInAllowedParents($parentId) && $this->canAccessDocument($parentId);
    }

    /**
     * ルート（親が 0）を親にできるか。ルートに置いてよい（canPlaceAtRoot()）うえで、
     * ルートが許可するリソースの範囲の中にある（isInAllowedParents(0)）ときだけ true。
     * 新規作成・移動・インポートなど、ルートを親にする入口の判定の正。new_document は見ない
     *
     * @return bool
     */
    public function canUseRootAsParent()
    {
        return $this->canPlaceAtRoot() && $this->isInAllowedParents(0);
    }

    /**
     * 文書グループで絞るときの、TV（テンプレート変数）の SQL の条件式（前に AND は付けない）。絞らないときは空文字。
     * TV のアクセスの判定の正はここ（編集画面・保存・QuickManager が使う）。
     * 絞るかどうかは文書の判定と同じ restrictsByDocumentGroups()（#1338）。
     * 「届く」TV: site_tmplvar_access に行が無い、または所属のグループの行がある。
     * 副問い合わせは外側の行を参照しない（相関なし）。別名があってもなくても同じ式で、
     * どの問い合わせにも入れられる。site_tmplvar_access.tmplvarid は NOT NULL
     * （install/sql/create_tables.sql）なので、NOT IN が NULL で崩れることは無い
     *
     * @param string $alias site_tmplvars の別名（呼ぶ側の固定の文字列。別名が無い問い合わせは空）
     * @return string
     * @throws InvalidArgumentException $alias が識別子でないとき
     */
    public function tmplvarAccessCondition($alias = 'tv')
    {
        $alias = (string)$alias;
        if ($alias !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Invalid table alias');
        }
        if (!$this->restrictsByDocumentGroups()) {
            return '';
        }
        $a = $alias === '' ? '' : $alias . '.';
        $table = db()->getFullTableName('site_tmplvar_access');
        $groupIds = $this->userMgrDocumentGroupIds();
        $unrestricted = sprintf('%sid NOT IN (SELECT tmplvarid FROM %s)', $a, $table);
        if (!$groupIds) {
            return $unrestricted;
        }
        return sprintf(
            '(%s OR %sid IN (SELECT tmplvarid FROM %s WHERE documentgroup IN (%s)))',
            $unrestricted,
            $a,
            $table,
            implode(',', $groupIds)
        );
    }

    /**
     * TV を、この利用者が文書グループの面で扱ってよいか。絞らないなら true。
     * 絞るとき（restrictsByDocumentGroups() が真のとき）に限り、存在しない TV は false
     *
     * @param int|string $tmplvarId
     * @return bool
     */
    public function canAccessTmplvar($tmplvarId)
    {
        $cond = $this->tmplvarAccessCondition('tv');
        if ($cond === '') {
            return true;
        }
        $rs = db()->select(
            'COUNT(tv.id)',
            '[+prefix+]site_tmplvars tv',
            sprintf('tv.id=%d AND %s', (int)$tmplvarId, $cond)
        );
        return (int)db()->getValue($rs) === 1;
    }

    /**
     * 文書グループの集合が付いた文書を、この利用者が管理画面で開けるか（#1318）。
     * 絞らない（restrictsByDocumentGroups() が false）、または集合が届く（管理側で非公開のグループが
     * 無い、または利用者の所属と重なる）とき true。documentAccessCondition() と同じ定義を、
     * 保存前の集合に当てたもの
     *
     * @param int[] $groupIds 文書に付く文書グループの id
     * @return bool
     */
    public function canOpenWithDocumentGroups($groupIds)
    {
        if (!$this->restrictsByDocumentGroups()) {
            return true;
        }
        $private = $this->mgrPrivateDocumentGroupIds($groupIds);
        return !$private || (bool)array_intersect($private, $this->userMgrDocumentGroupIds());
    }

    /**
     * 文書グループのうち、管理側で非公開のもの（membergroup_access に行があるもの）の id。
     * setMgrDocsAsPrivate() が privatemgr=1 を付ける定義と同じ
     *
     * @param int[] $groupIds
     * @return int[]
     */
    public function mgrPrivateDocumentGroupIds($groupIds)
    {
        $groupIds = array_unique(array_filter(array_map('intval', (array)$groupIds)));
        if (!$groupIds) {
            return [];
        }
        $rs = db()->select(
            'DISTINCT mga.documentgroup',
            '[+prefix+]membergroup_access mga',
            sprintf('mga.documentgroup IN (%s)', implode(',', $groupIds))
        );
        return array_map('intval', db()->getColumn('documentgroup', $rs) ?: []);
    }

    /**
     * 新規の文書に文書グループを付ける（付けるグループは newDocumentGroupIds() の規則）。
     *
     * @param int $docId 付ける先の新規の文書
     * @param int $parentId 親の文書（0 は引き継ぎなし）
     * @param array|null $access documentGroupAccess() の結果。すでに持っていれば渡して問い合わせを省く
     * @return int[] 付けた文書グループの id（昇順）。DB の書き込みの失敗は DBAPI の既定（messageQuit）に任せる
     */
    public function setNewDocumentGroups($docId, $parentId, $access = null)
    {
        $docId = (int)$docId;
        $groups = $this->newDocumentGroupIds($parentId, $access);
        if (!$groups || !$docId) {
            return [];
        }
        $values = [];
        foreach ($groups as $groupId) {
            $values[] = sprintf('(%d,%d)', $groupId, $docId);
        }
        db()->query(
            sprintf(
                'INSERT INTO %s (document_group, document) VALUES %s',
                evo()->getFullTableName('document_groups'),
                implode(',', $values)
            )
        );
        return $groups;
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
