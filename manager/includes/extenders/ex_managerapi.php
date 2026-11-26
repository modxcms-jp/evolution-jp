<?php
require_once MODX_CORE_PATH . 'form_flow.class.inc.php';
require_once MODX_CORE_PATH . 'field_row.class.inc.php';
require_once MODX_CORE_PATH . 'password_policy.class.inc.php';
require_once MODX_CORE_PATH . 'error_reporting.class.inc.php';
require_once MODX_CORE_PATH . 'element_types.class.inc.php';

$this->manager = new ManagerAPI;

/*
* MODx Manager API Class
* Written by Raymond Irving 2005
*
*/

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

    // check for saved form
    public function hasFormValues()
    {
        if (sessionv('mgrFormValues.' . $this->action) !== null) {
            return true;
        }

        if (sessionv('mgrFormValues') !== null) {
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
        $this->redirectToElementForm(
            sprintf(lang('element_name_required'), $typeLabel),
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
        // 戻った画面は、読み込んだ行の id（新規は 0）と入力の id が一致したときだけ入力を重ねる
        $this->saveFormValues(EvoFormFlow::screen($kind, $mode), ['id' => (int)$id]);
        evo()->webAlertAndQuit($message, EvoFormFlow::formUrl($kind, $mode, (int)$id, EvoFormFlow::postedAfterSave()));
        exit;
    }

    /**
     * 差し戻す画面のために、送られた入力（POST）をセッションに残す
     *
     * @param int $id 残す先の画面の番号（0 なら今の画面）
     * @param array $override POST の値の代わりに残す値（キー => 値）
     */
    public function saveFormValues($id = 0, array $override = [])
    {
        if (!$_POST) {
            return false;
        }
        $actionId = $id ?: $this->action;
        // パスワードは差し戻さない（セッションに平文を残さない）
        $values = array_diff_key(array_merge($_POST, $override), array_flip(EvoPasswordPolicy::FORM_FIELDS));
        $_SESSION['mgrFormValues'][$actionId] = $values;
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
        if ((int)($values['id'] ?? 0) !== (int)($content['id'] ?? 0)) {
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

        if (config('allow_duplicate_alias')) {
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

    /**
     * 編集中のほかの利用者を探す（active_users に、自分以外でそのアクションの行があるか）。
     *
     * @param int $action 編集の画面のアクション番号
     * @param int|null $id 編集している対象の id。null なら id を問わない（グローバル設定のように対象が 1 つの画面）
     * @return string 最初に見つかった利用者の username。いなければ空文字
     */
    public function editLockedBy(int $action, ?int $id = null): string
    {
        $where = [
            where('action', '=', (int) $action),
            and_where('internalKey', '!=', (int) evo()->getLoginUserID()),
        ];
        if ($id !== null) {
            $where[] = and_where('id', '=', (int) $id);
        }
        $row = db()->getRow(db()->select('username', '[+prefix+]active_users', $where, '', 1));
        return $row ? (string) $row['username'] : '';
    }

    /**
     * 管理画面の利用者のパスワードを保存用のハッシュにする（作り方は EvoPasswordPolicy::hash()）
     */
    public function hashPassword($password)
    {
        return EvoPasswordPolicy::hash((string) $password);
    }

    /**
     * 新しいパスワードが決まりに合うかを確かめる（決まりは EvoPasswordPolicy::check()）。
     * 戻り値は '' (問題なし) か、利用者に見せるエラー文（HTML。言語ファイルの文）
     *
     * @param string[] $context 推測されやすい値（ログイン名・メールアドレス・氏名など）。サイト名はここで足す
     */
    public function passwordError($password, array $context = [])
    {
        $context[] = (string) config('site_name');
        $reason = EvoPasswordPolicy::check((string) $password, $context);
        if ($reason === '') {
            return '';
        }
        return sprintf(
            lang('password_error_' . $reason),
            EvoPasswordPolicy::MIN_LENGTH,
            EvoPasswordPolicy::MAX_BYTES
        ) . ($reason === 'common' || $reason === 'context' ? ' ' . lang('password_advice') : '');
    }

    public function getHashType($db_value = '')
    { // password_hash | md5 | v1 | phpass
        $db_value = (string) $db_value;
        $info = password_get_info($db_value);
        if (!empty($info['algo'])) {
            return 'password_hash';
        }

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
        $algorithm = config('pwd_hash_algo') ?: 'UNCRYPT';

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
            $_SESSION['current_request_uri'] = serverv('REQUEST_URI');
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
     * エレメントの新規・編集の画面を開く前の確かめをして、編集する行を読み込む。
     * 権限が無い・新規でも編集でもない画面・行が無いときはエラー 3、id が正しくないときはエラー 2、
     * ほかの利用者が編集中のときはエラー 5 の画面を出して止まる。
     *
     * @param string $type EvoElementTypes の種類
     * @param string $lockLabel 編集中のメッセージ（lock_msg）に出す種類の名前
     * @return array 読み込んだ行。新規の画面では、URL に id が付いていても読まずに空の配列
     */
    public function openElementForm(string $type, string $lockLabel): array
    {
        $def = EvoElementTypes::get($type);
        if (!$def) {
            throw new InvalidArgumentException("Unknown element type: {$type}");
        }

        $mode = EvoFormFlow::mode($type);
        if ($mode === '' || !evo()->hasPermission($def['permissions'][$mode])) {
            alert()->setError(3);
            alert()->dumpError();
        }

        if ($mode === 'new') {
            return [];
        }

        abort_if_invalid_id('id', true);
        $id = request_intvar('id');

        $username = $this->editLockedBy(EvoFormFlow::screen($type, 'edit'), $id);
        if ($username !== '') {
            alert()->setError(5, sprintf(lang('lock_msg'), $username, $lockLabel));
            alert()->dumpError();
        }

        $row = db()->getRow(db()->select('*', "[+prefix+]{$def['table']}", where('id', '=', $id)));
        if (!$row) {
            alert()->setError(3);
            alert()->dumpError();
        }
        return $row;
    }

    /**
     * エレメントを id で 1 件削除する（関連テーブルの行も消し、削除の前後のイベントを呼ぶ）。
     * 権限の確かめとキャッシュの削除は呼び出し側で行う。
     *
     * @param string $type EvoElementTypes の種類
     * @param int $id エレメントの id
     * @return bool 削除の SQL がすべて通ったら true
     */
    public function deleteElement($type, $id)
    {
        $def = EvoElementTypes::get($type);
        $id = (int) $id;
        if (!$def || $id < 1) {
            return false;
        }

        $params = ['id' => $id];
        evo()->invokeEvent($def['events']['before_delete'], $params);

        if (!db()->delete("[+prefix+]{$def['table']}", where('id', '=', $id))) {
            return false;
        }
        foreach ($def['relations'] as $relation) {
            if (!db()->delete("[+prefix+]{$relation['table']}", where($relation['key'], '=', $id))) {
                return false;
            }
        }

        $params = ['id' => $id];
        evo()->invokeEvent($def['events']['after_delete'], $params);
        return true;
    }

    /**
     * エレメントを id で 1 件複製する（関連テーブルの行も写す）。
     * 権限の確かめは呼び出し側で行う。
     *
     * @param string $type EvoElementTypes の種類
     * @param int $id 複製元の id
     * @return int|false 新しい id。複製元が無いときは 0、SQL が通らなかったときは false
     */
    public function duplicateElement($type, $id)
    {
        $def = EvoElementTypes::get($type);
        $id = (int) $id;
        if (!$def || $id < 1) {
            return 0;
        }

        $table = "[+prefix+]{$def['table']}";
        $source = db()->getRow(db()->select('*', $table, where('id', '=', $id)));
        if (!$source) {
            return 0;
        }

        $row = array_intersect_key($source, array_flip($def['copy_fields']));
        $nameField = $def['name_field'];
        $row[$nameField] = str_replace('[+title+]', $source[$nameField], lang('duplicate_title_string'));
        if (isset($def['guid_field'])) {
            $row[$def['guid_field']] = $this->createGuid();
        }

        $newId = (int) db()->insert(db()->escape($row), $table);
        if (!$newId) {
            return false;
        }

        foreach ($def['relations'] as $relation) {
            if ($relation['copy'] === null) {
                continue;
            }
            $relationTable = "[+prefix+]{$relation['table']}";
            $rs = db()->select('*', $relationTable, where($relation['key'], '=', $id));
            while ($related = db()->getRow($rs)) {
                $copy = array_intersect_key($related, array_flip($relation['copy']));
                $copy[$relation['key']] = $newId;
                if (db()->insert(db()->escape($copy), $relationTable) === false) {
                    return false;
                }
            }
        }

        return $newId;
    }

    /**
     * 同じ名前のエレメントがすでにあるか。
     *
     * @param string $type EvoElementTypes の種類
     * @param string $name 確かめる名前（生の値。エスケープしない）
     * @param int $exceptId 数えない id（更新のときの自分。新規は 0）
     * @param array $among 列 => 値。この値を持つ行の間でだけ確かめる（プラグインは有効なものどうしだけ）
     */
    public function elementNameExists($type, $name, $exceptId = 0, array $among = []): bool
    {
        $def = EvoElementTypes::get($type);
        if (!$def) {
            throw new InvalidArgumentException("Unknown element type: {$type}");
        }

        $where = [where($def['name_field'], '=', db()->escape($name))];
        if ((int) $exceptId) {
            $where[] = and_where('id', '!=', (int) $exceptId);
        }
        foreach ($among as $field => $value) {
            $where[] = and_where($field, '=', db()->escape($value));
        }
        return db()->getValue(db()->select('COUNT(id)', "[+prefix+]{$def['table']}", $where)) > 0;
    }

    /**
     * エレメントの名前を変えたとき、文書・テンプレート・チャンク・テンプレート変数の値の中のタグを新しい名前に書き換える。
     *
     * @param string[] $forms タグの形。名前の位置を %s にした sprintf() の書式（'{{%s}}'・'[*%s:' など）
     * @param string $oldName 前の名前（生の値。エスケープしない）
     * @param string $newName 新しい名前（生の値。エスケープしない）
     */
    public function renameElementTags(array $forms, $oldName, $newName): void
    {
        $columns = [
            'site_content' => 'content',
            'site_templates' => 'content',
            'site_htmlsnippets' => 'snippet',
            'site_tmplvar_contentvalues' => 'value',
        ];
        foreach ($forms as $form) {
            foreach ($columns as $table => $column) {
                db()->update(
                    sprintf(
                        "%s=REPLACE(%s,'%s','%s')",
                        $column,
                        $column,
                        db()->escape(sprintf($form, $oldName)),
                        db()->escape(sprintf($form, $newName))
                    ),
                    "[+prefix+]{$table}"
                );
            }
        }
    }

    /**
     * モジュールの GUID を作る
     */
    public function createGuid()
    {
        return md5(uniqid(getmypid() . mt_rand() . (float) microtime() * 1000000, true));
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
                '[+title+]' => js_literal(lang('sys_alert'))
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

    /**
     * ロール設定の変更をログインし直さずに反映するため、管理画面のリクエストごとにロールの権限を読み直す。
     * ユーザーが削除された・管理者がブロックした（期限つきを含む）ときは、管理画面のセッションを破棄して false を返す。
     * 失敗回数による一時ロック（blockeduntil が未来で、failedlogincount が failed_login_attempts 以上。isTemporaryLoginLock()）は、ログインを止めるだけで、開いているセッションは切らない（管理者が自分で解除できるように）
     *
     * @return bool 続けてよいとき true。セッションを破棄したとき false
     */
    public function refreshMgrPermissions($uid = 0)
    {
        if (!$uid) {
            $uid = sessionv('mgrInternalKey', 0);
        }
        $row = db()->getRow(
            db()->select(
                'mu.id, ua.role, ua.blocked, ua.blockeduntil, ua.blockedafter, ua.failedlogincount',
                [
                    '[+prefix+]manager_users mu',
                    'LEFT JOIN [+prefix+]user_attributes ua ON ua.internalKey=mu.id'
                ],
                sprintf("mu.id='%d'", (int)$uid)
            )
        );
        if (!$row || isUserBlockedByAdmin($row)) {
            session_unset();
            @session_destroy();
            return false;
        }
        // 属性の行が無いユーザーはロールが無いので、権限は今のまま
        if ($row['role'] === null) {
            return true;
        }
        sessionv('*mgrRole', $row['role']);
        sessionv('*mgrPermissions', $this->getMgrPermissions($row['role']));
        return true;
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
            // pid が送られたかどうかだけを見る（'0'・空は無いものと同じ）
            if (!anyv('pid')) {
                return true;
            }

            // 形式違い・配列は 0（ルート）になり、ルートが許可の一覧に無ければ下で拒否される
            $id = request_intvar('pid');
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
            if (in_array($child, (array)evo()->user_allowed_docs)) {
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

    /**
     * allowed_parents の範囲の中の文書だけに絞る SQL の条件式（前に AND は付けない）。制限なし（設定が空、または 0 を含む）なら空文字。
     * 範囲は getUserAllowedDocs() の一覧と同じ（許可した親と、その子孫。削除済みとその下を除き、10 階層まで）
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

    // ルート直下にリソースを作ってよいか
    public function isAllowroot()
    {
        return evo()->hasPermission('save_role') || (bool)evo()->config('udperms_allowroot');
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
     * 文書グループの集合が付いた文書を、この利用者が管理画面で開けるか。
     * 編集画面が文書を開くときの 2 つの確認を、そのまま式にしたもの。
     * - 到達できる（$reachable）: 集合に管理側で非公開のグループ（membergroup_access に行がある。
     *   setMgrDocsAsPrivate() と同じ定義。これが privatemgr=1 の元）が無い、または利用者の所属
     *   （sessionv('mgrDocgroups')）と重なる
     * - checkPermissions()（document.parser.subparser.trait.php）: save_role があるか、$reachable
     * - db_value()（mutate_content/functions.php）: isAdmin()、所属が空、または $reachable のとき絞り込みを省く
     * 両方を通ったときだけ true。use_udperms が 1 でなければ常に true。
     * 2 つの確認の食い違い自体は #1338 で 1 か所にまとめる。
     * sessionv('mgrDocgroups') は、管理画面のリクエストで accesscontrol.inc.php が読み直す値が前提
     * （管理画面のリクエストで使う）
     *
     * @param int[] $groupIds 文書に付く文書グループの id
     * @return bool
     */
    public function canOpenWithDocumentGroups($groupIds)
    {
        if (evo()->config('use_udperms') != 1) {
            return true;
        }
        $private = $this->mgrPrivateDocumentGroupIds($groupIds);
        $mine = array_map('intval', (array)sessionv('mgrDocgroups', []));
        $reachable = !$private || (bool)array_intersect($private, $mine);

        $passCheckPermissions = evo()->hasPermission('save_role') || $reachable;
        $passDbValue = $this->isAdmin() || !$mine || $reachable;
        return $passCheckPermissions && $passDbValue;
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
