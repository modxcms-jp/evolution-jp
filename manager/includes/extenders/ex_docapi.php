<?php

$this->doc = new DocAPI;
require_once MODX_CORE_PATH . 'special_pages.class.inc.php';

// 文書の作成・更新・削除と、編集画面の値の整形・権限の確認（evo()->doc）。文書を扱うコードはこちらを使う。
// ex_documentapi.php の Document クラスは本体から使われておらず、非推奨（#1085）
class DocAPI
{
    // 値が複数になる TV の型。選ばなかったときは、項目が送られないので空として扱う
    const MULTI_VALUE_TV_TYPES = ['checkbox', 'listbox-multiple', 'custom_tv'];

    public $mode;

    public function __construct()
    {
    }

    public function create($f = [], $groups = [])
    {
        $f = $this->correctResourceFields($f, 'create');

        $f['editedon'] = $f['createdon'];
        $f['editedby'] = $f['createdby'];

//		$f = $this->setPubStatus($f);
        $newdocid = $this->getNewDocID();
        if ($newdocid) {
            $f['id'] = $newdocid;
        }

        $fields = [];
        foreach ($f as $k => $v) {
            if ($this->isTv($k)) {
                $fields['tv'][$k] = $v;
                continue;
            }
            $fields['doc'][$k] = $v;
        }
        $id = db()->insert(db()->escape($fields['doc']), '[+prefix+]site_content');
        if (!$id) {
            return false;
        }
        if (preg_match('@^[1-9][0-9]*$@', array_get($fields, 'doc.parent', 0))) {
            db()->update(
                ['isfolder' => 1],
                '[+prefix+]site_content',
                sprintf("id='%s'", array_get($fields, 'doc.parent')));
        }

        if (!empty($fields['tv'])) {
            foreach ($fields['tv'] as $k => $v) {
                $this->saveTV($id, $k, $v);
            }
        }

        if ($groups) {
            foreach ($groups as $group) {
                $group = (int)$group;
                if ($group < 1) {
                    continue;
                }
                db()->insert(
                    ['document_group' => $group, 'document' => $id],
                    '[+prefix+]document_groups'
                );
            }
            // DocAPI は use_udperms に関係なくグループを書くので、印も関係なく計算する
            // ManagerAPI は公開側・CLI からも呼ばれるので、無ければ読み込む
            // （#1338 の PR-A（#1361）で manager() が自分で読み込むようになったら不要）
            if (!evo()->manager) {
                evo()->loadExtension('ManagerAPI');
            }
            manager()->refreshDocumentPrivateFlags($id);
        }
        evo()->clearCache();
        return $id;
    }

    public function update($f = [], $docid = 0, $where = '')
    {
        if (!preg_match('@^[1-9][0-9]*$@', $docid)) {
            return false;
        }
        if (is_string($f) && str_contains($f, '=')) {
            [$k, $v] = explode('=', $f, 2);
            $f = [
                trim($k) => trim($v)
            ];
        }
        $f['id'] = $docid;
        $f = $this->correctResourceFields($f, 'update');

        $fields = [];
        foreach ($f as $k => $v) {
            if ($this->isTv($k)) {
                $fields['tv'][$k] = $v;
                continue;
            }
            $fields['doc'][$k] = $v;
        }
        // $f = $this->setPubStatus($f);

        if (!empty($fields['doc'])) {
            $rs = db()->update(
                db()->escape(
                    $fields['doc']
                ),
                '[+prefix+]site_content',
                sprintf("%s `id`='%d'", $where, $docid)
            );
        }
        if (!empty($fields['tv'])) {
            foreach ($fields['tv'] as $k => $v) {
                $this->saveTV($docid, $k, $v);
            }
        }
        evo()->clearCache();
        return $rs;
    }

    public function delete($id = 0, $where = '')
    {
        if (!preg_match('@^[0-9]+$@', $id)) {
            return;
        }
        if (empty($id)) {
            if (evo()->documentIdentifier) {
                $id = evo()->documentIdentifier;
            } else {
                return;
            }
        }

        $f['deleted'] = '1';
        $f['published'] = '0';
        $f['publishedon'] = 0;
        $f['pub_date'] = 0;
        $f['unpub_date'] = 0;

        db()->update($f, '[+prefix+]site_content', "id='" . $id . "'");
    }

    /**
     * 公開日・公開終了日から published を決める（リソースの保存、チャンクの保存、下書きの保存と採用の共通の規則）。
     * 未来の公開日は非公開、過去の公開日は公開、公開終了日が過去なら非公開（公開日より優先）。
     * 日付が空なら 0 にして published はそのまま。日付が解釈できないかは呼び出し側が先に
     * evo()->parseDateInput() で確かめる。整数（負を含む）の値はそのまま使う
     */
    public function setPubStatus($f)
    {
        $toTime = function ($value) {
            return is_int($value) ? $value : evo()->toTimeStamp($value);
        };

        if (!isset($f['pub_date']) || empty($f['pub_date'])) {
            $f['pub_date'] = 0;
        } else {
            $f['pub_date'] = $toTime($f['pub_date']);
            if ($f['pub_date'] < request_time()) {
                $f['published'] = 1;
            } elseif ($f['pub_date'] > request_time()) {
                $f['published'] = 0;
            }
        }

        if (empty($f['unpub_date'])) {
            $f['unpub_date'] = 0;
            return $f;
        }

        $f['unpub_date'] = $toTime($f['unpub_date']);
        if ($f['unpub_date'] < request_time()) {
            $f['published'] = 0;
        }
        return $f;
    }

    /**
     * 設定で使っているページ（EvoSpecialPages::protectedSettings()）に許さない公開の状態なら、その理由の文を返す（保存と下書きの採用の共通の判定）。
     * 非公開にする、未来の公開日がある、公開終了日がある、のどれかに当たると返す。当たるページでない・問題が無ければ空文字。
     * 警告と差し戻しは呼び出し側で行う。日付はタイムスタンプ（setPubStatus() を通した値）で渡す
     */
    public function protectedPubError($id, $published, $pub_date, $unpub_date)
    {
        $setting = $this->protectedPageSetting($id);
        if ($setting === '') {
            return '';
        }
        if ($published == 0) {
            return stripcslashes(lang('cannot_unpublish_' . $setting));
        }
        if ($pub_date > request_time() || $unpub_date) {
            return stripcslashes(lang('cannot_pub_dates_' . $setting));
        }
        return '';
    }

    /**
     * 下書きを文書へ採用するときの値を決める（今すぐ採用・予約の受け付け・予約の時刻の採用の共通の規則）。
     * 今の文書の公開の状態を起点に、リソースの保存と同じ規則（setPubStatus()）で published と日付を決める。
     * $canPublish が偽（公開の権限が無い）なら、published・pub_date・unpub_date を今の値のままにする。
     * 設定で使っているページに許さない状態なら、その理由の文も返す（protectedPubError()。無ければ空文字）。
     * 下書きが空なら、日付を 0 で上書きしないよう規則をかけず、published だけ今の値を返す。
     * 返り値は [採用する値, 理由]。警告と差し戻し・ログは呼び出し側で行う
     */
    public function draftForAdoption($docid, $draft, $canPublish = true)
    {
        $current = db()->getRow(
            db()->select('published,pub_date,unpub_date', '[+prefix+]site_content', where('id', '=', (int)$docid))
        ) ?: [];
        $published = (int)array_get($current, 'published', 0);
        if (!$draft) {
            return [['published' => $published], ''];
        }
        $draft['published'] = $published;
        if ($canPublish) {
            $draft = $this->setPubStatus($draft);
        } else {
            $draft['pub_date'] = (int)array_get($current, 'pub_date', 0);
            $draft['unpub_date'] = (int)array_get($current, 'unpub_date', 0);
        }
        return [
            $draft,
            $this->protectedPubError($docid, $draft['published'], $draft['pub_date'], $draft['unpub_date'])
        ];
    }

    /**
     * 下書きを文書へ採用する（今すぐ採用・予約の時刻の採用の共通の入口）。
     * 文書の項目は update() で、TV は下書きの tv<ID> の値を通常の保存と同じ規則（tmplvarValueForSave()）で保存する。
     * $draft は draftForAdoption() の返り値
     */
    public function updateFromDraft($draft, $docid)
    {
        $rs = $this->update($draft, $docid);
        if ($rs === false) {
            return false;
        }
        $tmplvars = [];
        foreach ($this->templateTmplvars($draft['template'] ?? 0, false) as $row) {
            $key = 'tv' . $row['id'];
            if (array_key_exists($key, $draft)) {
                $tmplvars[$row['id']] = $this->tmplvarValueForSave($row, $draft[$key]);
            }
        }
        if ($tmplvars) {
            $this->saveTmplvars($docid, $tmplvars);
            evo()->clearCache();
        }
        return $rs;
    }

    /**
     * 下書きに入れる TV の値を、通常の保存と同じ範囲に絞る。
     * テンプレートに無い TV と、今のユーザーが変えられない TV は落とす。選ばなかった複数選択の TV は、
     * 採用で空にできるよう空文字で残す（下書きの項目が無いことは「変えない」の意味にするため）。
     * 変えられない TV の下書きの値は、$existing（保存済みの下書き）のものを引き継ぐ
     */
    public function limitDraftTmplvars($fields, $existing = [])
    {
        $allowed = [];
        foreach ($this->templateTmplvars($fields['template'] ?? 0) as $row) {
            $key = 'tv' . $row['id'];
            $allowed[$key] = true;
            if (!isset($fields[$key]) && in_array($row['type'], self::MULTI_VALUE_TV_TYPES, true)) {
                $fields[$key] = '';
            }
        }
        foreach (array_keys($fields) as $key) {
            if (preg_match('@^(tv[0-9]+)(_prefix)?$@', (string)$key, $m) && !isset($allowed[$m[1]])) {
                unset($fields[$key]);
            }
        }
        foreach ($existing as $key => $value) {
            if (preg_match('@^tv[0-9]+$@', (string)$key) && !isset($allowed[$key])) {
                $fields[$key] = $value;
            }
        }
        return $fields;
    }

    /**
     * テンプレートに割り当てた TV の行を rank 順に返す。$checkAccess が真なら、今の管理ユーザーが変えられる TV だけ
     * （判定は ManagerAPI::tmplvarAccessCondition()。文書グループで絞らない利用者は全部）
     */
    public function templateTmplvars($template, $checkAccess = true)
    {
        $template = (int)$template;
        if (!$template) {
            return [];
        }
        $where = sprintf('tvtpl.templateid=%d', $template);
        $cond = $checkAccess ? manager()->tmplvarAccessCondition('tv') : '';
        if ($cond !== '') {
            $where .= " AND {$cond}";
        }
        $rs = db()->select(
            'DISTINCT tv.*',
            [
                '[+prefix+]site_tmplvars AS tv',
                'INNER JOIN [+prefix+]site_tmplvar_templates AS tvtpl ON tvtpl.tmplvarid = tv.id'
            ],
            $where,
            'tv.rank'
        );
        $rows = [];
        while ($row = db()->getRow($rs)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * TV の値を保存する形にする。配列は '||' でつなぐ。空か既定値と同じなら false（保存せず、あれば行を消す）。
     * リソースの保存と下書きの採用の共通の規則。URL 型の接頭辞は呼び出し側で整えてから渡す
     */
    public function tmplvarValueForSave($row, $value)
    {
        if (is_array($value)) {
            $value = implode('||', $value);
        }
        $value = (string)$value;
        // @@EVAL の既定値は $row を読めるので、変数名は通常の保存のときと同じにしておく
        $default = $row['default_text'];
        if (substr($default, 0, 6) === '@@EVAL') {
            $default = eval(trim(substr($default, 7)));
        }
        return strlen($value) > 0 && $value != $default ? $value : false;
    }

    /**
     * TV の値を保存する。$tmplvars は [TV の id => 値]。値が false なら行を消す（tmplvarValueForSave() の返り値）
     */
    public function saveTmplvars($docid, $tmplvars)
    {
        $docid = (int)$docid;
        $rs = db()->select(
            'id, tmplvarid',
            '[+prefix+]site_tmplvar_contentvalues',
            where('contentid', '=', $docid)
        );
        $rowIds = [];
        while ($row = db()->getRow($rs)) {
            $rowIds[$row['tmplvarid']] = $row['id'];
        }
        foreach ($tmplvars as $tmplvarid => $value) {
            $tmplvarid = (int)$tmplvarid;
            if ($value === false) {
                if (isset($rowIds[$tmplvarid])) {
                    db()->delete(
                        '[+prefix+]site_tmplvar_contentvalues',
                        where('id', '=', (int)$rowIds[$tmplvarid])
                    );
                }
                continue;
            }
            $f = db()->escape(['contentid' => $docid, 'tmplvarid' => $tmplvarid, 'value' => $value]);
            if (isset($rowIds[$tmplvarid])) {
                db()->update(
                    $f,
                    '[+prefix+]site_tmplvar_contentvalues',
                    sprintf("tmplvarid='%d' AND contentid='%d'", $tmplvarid, $docid)
                );
                continue;
            }
            db()->insert($f, '[+prefix+]site_tmplvar_contentvalues');
        }
    }

    // type : 'create' or 'update'
    private function correctResourceFields($fields,$type='create')
    {
        $keys = array_keys($fields);
        foreach ($keys as $k) {
            if (!evo()->get_docfield_type($k)) {
                unset($fields[$k]);
            }
        }

        $fields['editedon'] = request_time();

        if (empty($fields['editedby']) && $editedBy = evo()->getLoginUserID()) {
            $fields['editedby'] = $editedBy;
        }

        if ($type == 'create') {
            return $this->_fieldsForCreate($fields);
        }

        return $this->fieldsForUpdate($fields);
    }

    private function _fieldsForCreate($fields)
    {
        if (!isset($fields['published'])) {
            $fields['published'] = config('publish_default', 0);
        }
        if (array_get($fields, 'pagetitle', '') === '') {
            $fields['pagetitle'] = lang('untitled_resource');
        }
        if (!isset($fields['createdon'])) {
            $fields['createdon'] = request_time();
        }
        if (!isset($fields['createdby'])) {
            $fields['createdby'] = evo()->getLoginUserID();
            if( $fields['createdby'] === false ) {
                $fields['createdby'] = 0;
            }
        }

        if (empty($fields['publishedon']) && !empty($fields['published'])) {
            $fields['publishedon'] = $fields['editedon'];
        }
        if (empty($fields['template'])) {
            $fields['template'] = config('default_template', 0);
        }

        return $fields;
    }

    private function fieldsForUpdate($fields)
    {
        if (isset($fields['pagetitle']) && $fields['pagetitle'] === '') {
            $fields['pagetitle'] = lang('untitled_resource');
        }

        if (!empty($fields['published'])) {
            $fields['publishedon'] = request_time();
            if (empty($fields['publishedby']) && $fields['editedby']) {
                $fields['publishedby'] = $fields['editedby'];
            }
        }

        return $fields;
    }

    public function saveTV($doc_id, $name, $value)
    {
        static $tv = [];
        if (!isset($tv[$doc_id][$name])) {
            $rs = db()->select(
                [
                    'doc_id' => 'doc.id',
                    'tv_name' => 'var.name',
                    'tv_id' => 'tt.tmplvarid',
                    'template_id' => 'doc.template'
                ],
                [
                    '[+prefix+]site_content doc',
                    'left join [+prefix+]site_tmplvar_templates tt on tt.templateid=doc.template',
                    'left join [+prefix+]site_tmplvars var on var.id=tt.tmplvarid'
                ],
                sprintf("doc.id='%s' and tt.tmplvarid is not null", $doc_id)
            );
            while ($row = db()->getRow($rs)) {
                $tv[$row['doc_id']][$row['tv_name']] = $row;
            }
        }
        if (!isset($tv[$doc_id][$name])) {
            return;
        }
        if ($this->hasTmplvar($tv[$doc_id][$name]['tv_id'], $doc_id)) {
            db()->update(
                [
                    'value' => db()->escape($value)
                ],
                '[+prefix+]site_tmplvar_contentvalues',
                sprintf(
                    "tmplvarid='%s' AND contentid='%s'",
                    $tv[$doc_id][$name]['tv_id'],
                    $doc_id
                )
            );
            return;
        }
        db()->insert(
            [
                'tmplvarid' => $tv[$doc_id][$name]['tv_id'],
                'contentid' => $doc_id,
                'value' => db()->escape($value)
            ],
            '[+prefix+]site_tmplvar_contentvalues'
        );
    }

    private function hasTmplvar($tmplvarid, $doc_id)
    {
        $rs = db()->select(
            '*',
            '[+prefix+]site_tmplvar_contentvalues',
            sprintf(
                "tmplvarid='%s' AND contentid='%s'",
                $tmplvarid,
                $doc_id
            )
        );
        return db()->count($rs);
    }

    private function isTv($key)
    {
        static $tv = null;
        if (isset($tv[$key])) {
            return $tv[$key];
        }
        if ($tv !== null) {
            return false;
        }
        $tv = [];
        $rs = db()->select('id,name', '[+prefix+]site_tmplvars');
        while ($row = db()->getRow($rs)) {
            $tv[$row['name']] = $row['id'];
        }
        if (!isset($tv[$key])) {
            return false;
        }
        return $tv[$key];
    }

    public function initValue($form_v)
    {
        $fields = explode(
            ',',
            'id,ta,alias,type,contentType,pagetitle,longtitle,description,link_attributes,isfolder,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,richtext,content_dispo,donthit,menutitle,hidemenu,introtext'
        );
        if (isset($form_v['ta'])) {
            $form_v['content'] = $form_v['ta'];
            unset($form_v['ta']);
        }
        // ta は上で content に移しているので、content も配列を拒否する
        if (isset($form_v['content']) && is_array($form_v['content'])) {
            alert()->setError(2);
            alert()->dumpError();
        }
        foreach ($fields as $key) {
            if (!isset($form_v[$key])) {
                $form_v[$key] = '';
            }
            // フィールドは文字列だけを受ける。配列（pagetitle[]=x など）は形式違いとして拒否する
            if (is_array($form_v[$key])) {
                alert()->setError(2);
                alert()->dumpError();
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
                    // 空は 0。解釈できない日付は元の入力を残し、setValue() で拒否させる
                    $ts = evo()->parseDateInput($value);
                    $value = $ts === null ? $value : $ts;
                    break;
                case 'editedon':
                    $value = request_time();
                    break;
                case 'editedby':
                    if (empty($value)) {
                        $value = evo()->getLoginUserID('mgr');
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

    /**
     * @param array $form_v
     * @param int $action 保存の画面の操作の番号（4: 新規、72: ウェブリンク、27: 更新）。呼び出し元が決める
     */
    public function setValue($form_v, $action = 0)
    {
        $form_v['alias'] = get_alias(
            array_get($form_v, 'id'),
            array_get($form_v, 'alias'),
            array_get($form_v, 'parent'),
            array_get($form_v, 'pagetitle')
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
            if ($form_v['type'] === 'reference') {
                $form_v['pagetitle'] = lang('untitled_weblink');
            } else {
                $form_v['pagetitle'] = lang('untitled_resource');
            }
        }

        if (substr($form_v['alias'], -1) === '/') {
            $form_v['alias'] = trim($form_v['alias'], '/');
            $form_v['isfolder'] = 1;
            $form_v['alias'] = evo()->stripAlias($form_v['alias']);
        }

        foreach (['pub_date', 'unpub_date'] as $dateField) {
            $timestamp = evo()->parseDateInput(array_get($form_v, $dateField, ''));
            // 解釈できない日付と 1970 年より前の日付は、DB に渡さず編集画面へ戻す
            if ($timestamp === null || $timestamp < 0) {
                evo()->manager->saveFormValues($action);
                evo()->webAlertAndQuit(
                    lang('mgrlog_dateinvalid'),
                    evo()->manager->formReturnUrl($action, array_get($form_v, 'id'), array_get($form_v, 'template'))
                );
                exit;
            }
            $form_v[$dateField] = $timestamp;
        }
        // 公開日・公開終了日による published の決め方は setPubStatus() に一本化している
        $form_v = $this->setPubStatus($form_v);

        // deny publishing if not permitted
        if ($action === 27) {
            return $form_v;
        }

        if (!evo()->hasPermission('publish_document')) {
            $form_v['pub_date'] = 0;
            $form_v['unpub_date'] = 0;
            $form_v['published'] = 0;
        }
        $form_v['publishedon'] = $form_v['published'] ? request_time() : 0;
        $form_v['publishedby'] = $form_v['published'] ? evo()->getLoginUserID() : 0;

        $form_v['createdby'] = evo()->getLoginUserID();
        $form_v['createdon'] = request_time();
        return $form_v;
    }

    public function getNewDocID()
    {
        $docIdIncrementMethod = (int)config('docid_incrmnt_method', 0);

        if ($docIdIncrementMethod === 1) {
            $rs = db()->select(
                'MIN(T0.id)+1',
                '[+prefix+]site_content AS T0 LEFT JOIN [+prefix+]site_content AS T1 ON T0.id + 1 = T1.id',
                'T1.id IS NULL'
            );
            return db()->getValue($rs);
        }

        if ($docIdIncrementMethod === 2) {
            $rs = db()->select(
                'MAX(id)+1',
                '[+prefix+]site_content'
            );
            return db()->getValue($rs);
        }
        return false;
    }

    public function fixTvNest($form_v)
    {
        if (isset($form_v['ta'])) {
            $form_v['content'] = $form_v['ta'];
            unset($form_v['ta']);
        }
        foreach ($form_v as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $form_v[$k] = str_replace(
                '[*' . $k . '*]',
                '[ *' . $k . '* ]',
                $v
            );
        }
        return $form_v;
    }

    public function canSaveDraft()
    {
        return 1;
    }

    /**
     * そのリソースが当たる設定名（EvoSpecialPages::protectedSettings()）。当たらなければ空文字
     */
    public function protectedPageSetting($id)
    {
        $id = (int)$id;
        if (!$id) {
            return '';
        }
        foreach (EvoSpecialPages::protectedSettings() as $setting) {
            if ($id === (int)evo()->config($setting)) {
                return $setting;
            }
        }
        return '';
    }

    /**
     * 削除を止める警告の言語キー。当たらなければ空文字。
     * $asChild が真なら、そのリソースが子孫にあって親の削除を止めるときの言語キー（文の中に ID の %d を持つ）
     */
    public function deleteGuardKey($id, $asChild = false)
    {
        $setting = $this->protectedPageSetting($id);
        if ($setting === '') {
            return '';
        }
        return ($asChild ? 'cannot_delete_child_' : 'cannot_delete_') . $setting;
    }

    /**
     * 非公開にするのを止める警告の言語キー。当たらなければ空文字
     */
    public function unpublishGuardKey($id)
    {
        $setting = $this->protectedPageSetting($id);
        return $setting === '' ? '' : 'cannot_unpublish_' . $setting;
    }

    /**
     * 文書の操作の確認の文言（document-actions.js の operations の表が言語キーで引く）。警告の文言は EvoSpecialPages::protectedSettings() から組み立てる
     */
    const ACTION_LANG_KEYS = [
        'confirm_delete_resource', 'confirm_delete_resource_children', 'confirm_batch_delete',
        'confirm_batch_publish', 'confirm_batch_unpublish',
        'confirm_publish', 'confirm_unpublish', 'confirm_resource_duplicate', 'confirm_undelete',
        'confirm_purge_resource', 'confirm_purge_resource_children',
    ];

    /**
     * document-actions.js へ渡す設定（header.inc.php が EvoDocumentActions.config に入れる）。
     * ids は「設定名 => リソースの ID」、lang は「言語キー => 文言」、menu は右クリックメニューの状態での出し分け（DocumentMenu::stateConfig()）
     */
    public function actionConfig()
    {
        $ids = [];
        $keys = self::ACTION_LANG_KEYS;
        foreach (EvoSpecialPages::protectedSettings() as $setting) {
            $ids[$setting] = (int)evo()->config($setting);
            $keys[] = 'cannot_delete_' . $setting;
            $keys[] = 'cannot_unpublish_' . $setting;
        }
        $lang = [];
        foreach ($keys as $key) {
            $lang[$key] = stripcslashes(lang($key));
        }
        include_once(MODX_CORE_PATH . 'controls/document_menu.class.php');
        return js_value(['ids' => $ids, 'lang' => $lang, 'menu' => DocumentMenu::stateConfig()]);
    }

    /**
     * 削除していない子があるか（削除で一緒に削除される子の有無。isfolder の設定とは別）
     */
    public function hasLiveChildren($id)
    {
        return (bool)$this->idsWithLiveChildren([$id]);
    }

    /**
     * 渡した ID のうち、削除していない子を持つものの ID の配列（一覧の行など、まとめて調べるとき用）
     */
    public function idsWithLiveChildren(array $ids)
    {
        return $this->idsWithChildren($ids, true);
    }

    /**
     * 渡した ID のうち、子を持つものの ID の配列。$liveOnly が false ならゴミ箱の子も数える
     */
    public function idsWithChildren(array $ids, $liveOnly = false)
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) {
            return [];
        }
        $rs = db()->select(
            'DISTINCT parent',
            '[+prefix+]site_content',
            sprintf('parent IN (%s)%s', implode(',', $ids), $liveOnly ? " AND deleted='0'" : '')
        );
        $result = [];
        while ($row = db()->getRow($rs)) {
            $result[] = (int)$row['parent'];
        }
        return $result;
    }

    /**
     * リソース $id の子孫すべて（ゴミ箱のものも含む）を「ID => ゴミ箱にあるか」で返す。$id 自身は含まない
     * （parent が循環していても含まない）。$id が 0 ならサイトの全リソース。
     * evo()->getChildIds() はゴミ箱のリソースを含まないので、DB を 1 段ずつたどる
     */
    public function descendants($id)
    {
        $id = (int)$id;
        $found = [$id => true];
        $level = [$id];
        while ($level) {
            $rs = db()->select('id, deleted', '[+prefix+]site_content', sprintf('parent IN (%s)', implode(',', $level)));
            $level = [];
            while ($row = db()->getRow($rs)) {
                if (!isset($found[(int)$row['id']])) {
                    $found[(int)$row['id']] = (bool)$row['deleted'];
                    $level[] = (int)$row['id'];
                }
            }
        }
        unset($found[$id]);
        return $found;
    }

    /**
     * ゴミ箱にあるリソースの ID の配列（ゴミ箱を空にするときの対象）
     */
    public function trashedIds()
    {
        $rs = db()->select('id', '[+prefix+]site_content', 'deleted=1');
        $ids = [];
        while ($row = db()->getRow($rs)) {
            $ids[] = (int)$row['id'];
        }
        return $ids;
    }

    /**
     * ゴミ箱のリソース $id を完全に削除するときの対象（$id と子孫）を集め、消せるかを確かめる。何も消さない。
     * 子孫を残すと親の無いリソースになるので、完全な削除は子孫ごと行う。$id がゴミ箱に無いとき、
     * ゴミ箱に無い子孫（個別に復活させたものなど）があるときは、消せない理由を返す。
     * 利用者の権限は見ない（管理画面の処理は、呼び出し側で checkPermissions() などを確かめる）。
     *
     * @param int $id
     * @return array ['warning' => 消せない理由（空なら消せる）, 'ids' => $id と子孫の ID（重なりなし。$id が先頭）, 'parent' => $id の親]
     *     ids と parent は warning があっても返す（呼び出し側が、理由を見せる前に対象の権限を確かめられるように）。
     *     $id のリソースが無いときは ids が空
     */
    public function purgeCheck($id)
    {
        $id = (int)$id;
        $target = db()->getRow(db()->select('parent, deleted', '[+prefix+]site_content', 'id=' . $id));
        if (!$target) {
            return ['warning' => '', 'ids' => [], 'parent' => 0];
        }
        $descendants = $this->descendants($id);
        $liveId = array_search(false, $descendants, true);
        if (!(int)$target['deleted']) {
            $warning = sprintf('ID: %d %s', $id, lang('not_deleted'));
        } elseif ($liveId !== false) {
            $warning = sprintf(lang('cannot_purge_live_child'), $liveId);
        } else {
            $warning = '';
        }
        return [
            'warning' => $warning,
            'ids' => array_merge([$id], array_keys($descendants)),
            'parent' => (int)$target['parent'],
        ];
    }

    /**
     * ゴミ箱のリソースを完全に削除する（元に戻せない）。1 件（purgeCheck() の ids）でも、ゴミ箱の全件（trashedIds()）でも、ここを通す。
     * 文書グループとの結び付き・テンプレート変数の値・下書きの行も消す。ゴミ箱に無いリソースは、ID を渡しても消さない。
     * OnBeforeEmptyTrash・OnEmptyTrash を発火し、消す ID を ids で渡す（OnBeforeEmptyTrash のプラグインは ids を書き換えられる）。
     * 権限・消せるかの確かめと、キャッシュの消去（evo()->clearCache()）は呼び出し側が行う。
     *
     * @param array $ids 消すリソースの ID
     * @return bool 消せたら true（$ids が空のときも true）
     */
    public function purge(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        evo()->event->vars['ids'] = &$ids;
        evo()->invokeEvent('OnBeforeEmptyTrash', evo()->event->vars);

        $result = true;
        if ($ids) {
            $content = evo()->getFullTableName('site_content');
            $trashed = sprintf('%s.id IN (%s) AND %s.deleted=1', $content, implode(',', array_map('intval', $ids)), $content);
            // リソースの行を消す前に、結び付いた行を消す（リソースの ID は別のリソースで使い回されうる）。値は「表 => リソースの ID の列と追加の条件」
            $related = [
                'document_groups' => ['document', ''],
                'site_tmplvar_contentvalues' => ['contentid', ''],
                'site_revision' => ['elmid', " AND %s.element='resource'"],
            ];
            foreach ($related as $name => [$column, $extra]) {
                $table = evo()->getFullTableName($name);
                db()->query(sprintf(
                    'DELETE %1$s FROM %1$s INNER JOIN %2$s ON %2$s.id = %1$s.%3$s WHERE %4$s%5$s',
                    $table,
                    $content,
                    $column,
                    $trashed,
                    sprintf($extra, $table)
                ));
            }
            $result = (bool)db()->delete($content, sprintf('id IN (%s) AND deleted=1', implode(',', array_map('intval', $ids))));
        }
        if ($result) {
            evo()->invokeEvent('OnEmptyTrash', evo()->event->vars);
        }
        evo()->event->vars = [];
        return $result;
    }

    /**
     * @deprecated 1.4 で非推奨、1.5 で消す。manager()->can('document.move') を使う
     */
    public function canMoveDoc()
    {
        return $this->deprecatedCan('canMoveDoc', 'document.move');
    }

    /**
     * @deprecated 1.4 で非推奨、1.5 で消す。manager()->can('document.duplicate') を使う
     */
    public function canCopyDoc()
    {
        return $this->deprecatedCan('canCopyDoc', 'document.duplicate');
    }

    /**
     * @deprecated 1.4 で非推奨、1.5 で消す。manager()->can('document.delete') を使う
     */
    public function canDeleteDoc()
    {
        return $this->deprecatedCan('canDeleteDoc', 'document.delete');
    }

    /**
     * @deprecated 1.4 で非推奨、1.5 で消す。manager()->can('document.create') を使う
     */
    public function canCreateDoc()
    {
        return $this->deprecatedCan('canCreateDoc', 'document.create');
    }

    /**
     * @deprecated 1.4 で非推奨、1.5 で消す。manager()->can('document.edit') を使う
     */
    public function canEditDoc()
    {
        return $this->deprecatedCan('canEditDoc', 'document.edit');
    }

    /**
     * 非推奨の can*Doc() の中身。警告を残し、判定は ManagerAPI の操作の表（can()）に任せる（assets/docs/deprecations.md）
     */
    private function deprecatedCan(string $method, string $operation): bool
    {
        logDeprecated(
            "DocAPI::{$method}()",
            "DocAPI::{$method}() is deprecated. Use manager()->can('{$operation}') instead."
        );
        return manager()->can($operation);
    }

    public function existsDoc($id = 0)
    {
        $rs = db()->select('id', '[+prefix+]site_content', where('id', $id));
        return db()->count($rs) != 0;
    }
}
