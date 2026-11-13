<?php
/**
 * 管理画面の検索（a=71）の条件。
 *
 * 入力欄の書き方（Google の検索に倣う）と「詳しく検索」の欄を、同じ条件の並び（conditions）に読み替え、
 * そこから SQL の WHERE と、結果の欄に出す「読み取った条件」の文言を作る。書き方と欄で結果がずれないよう、
 * SQL は conditions からだけ組む。
 *
 * 入力欄の書き方:
 *   語 語          どれも含む（リソース名・タイトル・エイリアス。「本文も探す」なら本文も）
 *   "語句"         語句をそのまま探す（数字を ID ではなく文字列として探すときにも使う）
 *   -語 / -"語句"  含むものを除く
 *   title:語  alias:語  content:語    その項目だけで探す（値は "語句" でもよい。- を前に付けると除く）
 *   id:12  parent:8  template:2 (または名前)  link:12
 *   is:published / is:unpublished / is:deleted / is:folder
 *   after:2026-01-01  before:2026-06-30   更新日
 * 入力全体が数字だけなら ID、URL（http(s)://・/ で始まる・?id= を含む）ならそのページのリソース。
 * 知らない書き方（foo:bar）は、そのまま文字として探す。
 */
class ResourceSearchQuery
{
    /** 書き方の項目名 => 探す列 */
    private const FIELDS = [
        'title' => ['pagetitle', 'longtitle'],
        'alias' => ['alias'],
        'content' => ['content'],
    ];
    /** 項目名を付けない語を探す列（「本文も探す」なら content も） */
    private const DEFAULT_FIELDS = ['pagetitle', 'longtitle', 'alias'];
    /** 探す列の組 => 「読み取った条件」に出す言い方（言語のキー）。探す列と文言はここで 1 つに決める */
    private const FIELD_LABELS = [
        'pagetitle,longtitle,alias' => 'search_cond_any',
        'pagetitle,longtitle,alias,content' => 'search_cond_any_content',
        'pagetitle,longtitle' => 'search_cond_title',
        'pagetitle' => 'search_cond_pagetitle',
        'longtitle' => 'search_cond_longtitle',
        'alias' => 'search_cond_alias',
        'content' => 'search_cond_content',
    ];
    /** 公開状態の選択肢（「詳しく検索」の欄の値。with_deleted は削除済みも含めたすべて） */
    public const STATUSES = ['published', 'unpublished', 'deleted', 'with_deleted'];
    /** 書き方 is: の値（フォルダかどうかは公開状態ではない） */
    private const IS_VALUES = ['published', 'unpublished', 'deleted', 'folder'];

    /** @var array<int, array<string, mixed>> */
    private $conditions = [];
    /** 入力全体が数字だけのときの、その数字（ID として出しつつ、文字として探し直す道を添える） */
    private $bareNumber = '';
    /** 入力全体が URL のときの、その入力 */
    private $target = '';

    /**
     * @param string $q 入力欄
     * @param bool $alsoContent 「本文も探す」
     * @param array<string, string> $form 「詳しく検索」の欄（pagetitle・longtitle・alias・content・status・parent・template・edited_from・edited_to）
     */
    public function __construct(string $q, bool $alsoContent, array $form = [])
    {
        // 不正な UTF-8 は、正規表現や表示で壊れる前に取り除く
        $form = array_map(static function ($v) {
            return mb_scrub((string) $v);
        }, $form);
        $this->parseInput(trim(mb_scrub($q)), $alsoContent);
        $this->addForm($form);
    }

    public function bareNumber(): string
    {
        return $this->bareNumber;
    }

    public function target(): string
    {
        return $this->target;
    }

    /**
     * 結果の中で目立たせる語（除く語は入れない）。長い語から並べる（短い語が長い語の一部を先に包まないように）
     *
     * @return string[]
     */
    public function terms(): array
    {
        $terms = [];
        foreach ($this->conditions as $c) {
            if ($c['type'] === 'text' && !$c['negate'] && $c['value'] !== '') {
                $terms[] = $c['value'];
            }
        }
        $terms = array_values(array_unique($terms));
        usort($terms, static function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });
        return $terms;
    }

    /**
     * SQL の WHERE の条件（AND でつなぐ）。公開状態の指定が無ければ、削除済みを除く。
     *
     * @return array{where: string[], error: string}
     */
    public function toWhere(): array
    {
        $where = [];
        $error = '';
        foreach ($this->conditions as $c) {
            switch ($c['type']) {
                case 'text':
                    // alias・content は NULL のことがあり、NULL の行は NOT (… LIKE …) でも当たらなくなるので、空文字に直して比べる
                    $cols = array_map(static function ($col) use ($c) {
                        return sprintf("IFNULL(%s,'') LIKE '%%%s%%'", $col, self::escapeLike($c['value']));
                    }, $c['fields']);
                    $expr = '(' . implode(' OR ', $cols) . ')';
                    $where[] = $c['negate'] ? "NOT {$expr}" : $expr;
                    break;
                case 'id':
                    // ID・URL で 1 つを指したときは、削除済みでも出す（既定の「削除済みを除く」を掛けない）
                    $where[] = sprintf('id=%d', $c['value']);
                    break;
                case 'target':
                    $r = self::resolveTarget($c['value']);
                    $error = $r['error'];
                    $where[] = sprintf('id=%d', $r['id']);
                    break;
                case 'parent':
                    $ids = self::descendants($c['value']);
                    $where[] = $ids ? 'id IN (' . implode(',', $ids) . ')' : '0=1';
                    break;
                case 'template':
                    $where[] = sprintf('template=%d', $c['value']);
                    break;
                case 'link':
                    // リンクのタグ [~12~]（本文の中）
                    $where[] = sprintf("content LIKE '%%%s%%'", self::escapeLike('[~' . $c['value'] . '~]'));
                    break;
                case 'status':
                    $map = [
                        'published' => 'published=1 AND deleted=0',
                        'unpublished' => 'published=0 AND deleted=0',
                        'deleted' => 'deleted=1',
                        'folder' => 'isfolder=1',
                        'with_deleted' => '',
                    ];
                    if ($map[$c['value']] !== '') {
                        $where[] = $map[$c['value']];
                    }
                    break;
                case 'edited_from':
                    $where[] = sprintf('editedon >= %d', strtotime($c['value'] . ' 00:00:00'));
                    break;
                case 'edited_to':
                    $where[] = sprintf('editedon < %d', strtotime($c['value'] . ' 00:00:00') + 86400);
                    break;
            }
        }
        if (!$this->hasStatusCondition()) {
            // 既定では削除済みを探さない（ごみ箱の中身が結果に混ざらないように）
            $where[] = 'deleted=0';
        }
        return ['where' => $where, 'error' => $error];
    }

    /**
     * 結果の欄に出す、読み取った条件（言語の文字列を当てた、画面に出す前の文字列。エスケープは呼ぶ側で）
     *
     * @return string[]
     */
    public function describe(): array
    {
        $out = [];
        foreach ($this->conditions as $c) {
            switch ($c['type']) {
                case 'text':
                    $where = lang(self::FIELD_LABELS[implode(',', $c['fields'])]);
                    $value = $c['phrase'] ? '"' . $c['value'] . '"' : $c['value'];
                    $out[] = sprintf(lang($c['negate'] ? 'search_cond_text_not' : 'search_cond_text'), $where, $value);
                    break;
                case 'id':
                    $out[] = 'ID: ' . $c['value'];
                    break;
                case 'target':
                    $out[] = 'URL: ' . $c['value'];
                    break;
                case 'parent':
                    $out[] = sprintf(lang('search_cond_parent'), $c['value']);
                    break;
                case 'template':
                    $out[] = lang('template') . ': ' . $c['name'];
                    break;
                case 'link':
                    $out[] = sprintf(lang('search_cond_link'), $c['value']);
                    break;
                case 'status':
                    $out[] = lang('search_cond_is_' . $c['value']);
                    break;
                case 'edited_from':
                    $out[] = sprintf(lang('search_cond_after'), $c['value']);
                    break;
                case 'edited_to':
                    $out[] = sprintf(lang('search_cond_before'), $c['value']);
                    break;
            }
        }
        if (!$this->hasStatusCondition()) {
            $out[] = lang('search_status_default');
        }
        return $out;
    }

    /**
     * 公開状態を決める条件があるか。無ければ削除済みを除く。
     * ID・URL で指したときと、公開状態の指定は決める。フォルダかどうか（is:folder）は公開状態ではないので、決めない
     * （決めると「is:deleted is:folder」で削除済みの既定が足され、入力の順番で結果が変わる）
     */
    private function hasStatusCondition(): bool
    {
        foreach ($this->conditions as $c) {
            if ($c['type'] === 'id' || $c['type'] === 'target' || ($c['type'] === 'status' && $c['value'] !== 'folder')) {
                return true;
            }
        }
        return false;
    }

    /**
     * ID か URL を、リソースの ID に引き直す。http/https・www の有無・?id=12 の形・サイトの基準のパスの扱いはここ 1 か所で決める
     * （getIdFromUrl() 自体は、ウェブリンクの保存でも使うので変えない）。
     * 数字ならそのまま ID。URL なら、このサイトの URL かサイト内のパスとして、?id=12 の形はその ID、ほかはエイリアスのパスから引く。
     *
     * @return array{id: int, error: string} 引けなかったときは id が 0 で、error に理由（言語のキー）
     */
    public static function resolveTarget(string $input): array
    {
        if (ctype_digit($input)) {
            return ['id' => (int) $input, 'error' => ''];
        }
        $parts = parse_url($input);
        if ($parts === false) {
            return ['id' => 0, 'error' => 'search_target_not_found'];
        }
        if (isset($parts['host'])) {
            $bare = static function ($host) {
                return preg_replace('/^www\./i', '', strtolower((string) $host));
            };
            if ($bare($parts['host']) !== $bare(parse_url(MODX_SITE_URL, PHP_URL_HOST))) {
                return ['id' => 0, 'error' => 'search_target_other_site'];
            }
        }
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['id']) && ctype_digit((string) $query['id'])) {
            return ['id' => (int) $query['id'], 'error' => ''];
        }
        // サイトの基準のパス（サブディレクトリに置いたとき）を外し、getIdFromUrl() にはサイト内のパスとして渡す
        $path = rawurldecode($parts['path'] ?? '');
        if (MODX_BASE_URL !== '/' && $path !== '' && (isset($parts['host']) || strpos($path, '/') === 0)) {
            // 基準のパスの外（/sub に置いたサイトでの /other/x）は、このサイトのURLではない。/sub は /sub/ と同じに扱う
            $base = rtrim(MODX_BASE_URL, '/');
            if ($path !== $base && strpos($path, $base . '/') !== 0) {
                return ['id' => 0, 'error' => 'search_target_other_site'];
            }
            $path = substr($path, strlen($base));
        }
        $path = trim($path, '/');
        if ($path === '' || $path === 'index.php') {
            // トップページに引くのは、/・index.php・ホストだけの URL（とその基準のパス）のとき。
            // パスもホストも無い入力と、末尾が ? だけの入力（不正な文字が ? に化けた値）は引けない
            if ((!isset($parts['host']) && !isset($parts['path'])) || substr($input, -1) === '?') {
                return ['id' => 0, 'error' => 'search_target_not_found'];
            }
            return ['id' => (int) config('site_start'), 'error' => ''];
        }
        $id = (int) evo()->getIdFromUrl($path);
        return $id ? ['id' => $id, 'error' => ''] : ['id' => 0, 'error' => 'search_target_not_found'];
    }

    // ---- 読み取り ----

    private function parseInput(string $q, bool $alsoContent): void
    {
        if ($q === '') {
            return;
        }
        if (ctype_digit($q)) {
            // 数字だけなら ID（文字として探したいときは "20" と囲む）
            $this->bareNumber = $q;
            $this->add(['type' => 'id', 'value' => (int) $q]);
            return;
        }
        if (!preg_match('/\s/u', $q) && (preg_match('@^(https?://|/)@i', $q) || preg_match('@[?&]id=\d+@', $q))) {
            $this->target = $q;
            $this->add(['type' => 'target', 'value' => $q]);
            return;
        }
        $defaultFields = $alsoContent ? array_merge(self::DEFAULT_FIELDS, ['content']) : self::DEFAULT_FIELDS;
        foreach (self::tokenize($q) as [$negate, $field, $value, $phrase]) {
            if ($value === '') {
                continue;
            }
            if ($field === '') {
                $this->addText($value, $defaultFields, $negate, $phrase);
                continue;
            }
            if (!$this->addOperator($field, $value, $negate, $phrase)) {
                // 知らない書き方は、そのまま文字として探す
                $this->addText($field . ':' . $value, $defaultFields, $negate, $phrase);
            }
        }
    }

    /** @return bool 書き方として読めたか */
    private function addOperator(string $field, string $value, bool $negate, bool $phrase): bool
    {
        $field = strtolower($field);
        if (isset(self::FIELDS[$field])) {
            $this->addText($value, self::FIELDS[$field], $negate, $phrase);
            return true;
        }
        if ($negate) {
            return false; // -id: などは意味を持たない
        }
        switch ($field) {
            case 'id':
            case 'parent':
            case 'link':
                if (!ctype_digit($value)) {
                    return false;
                }
                $this->add(['type' => $field, 'value' => (int) $value]);
                return true;
            case 'template':
                $tpl = self::findTemplate($value);
                if ($tpl === null) {
                    return false;
                }
                $this->add(['type' => 'template', 'value' => $tpl['id'], 'name' => $tpl['name']]);
                return true;
            case 'is':
                $value = strtolower($value);
                if (!in_array($value, self::IS_VALUES, true)) {
                    return false;
                }
                $this->add(['type' => 'status', 'value' => $value]);
                return true;
            case 'after':
            case 'before':
                if (!self::isDate($value)) {
                    return false;
                }
                $this->add(['type' => $field === 'after' ? 'edited_from' : 'edited_to', 'value' => $value]);
                return true;
        }
        return false;
    }

    /** 「詳しく検索」の欄を、書き方と同じ条件に読み替える */
    private function addForm(array $form): void
    {
        foreach (['pagetitle', 'longtitle', 'alias', 'content'] as $name) {
            $v = trim((string) ($form[$name] ?? ''));
            if ($v !== '') {
                $this->addText($v, [$name], false, false);
            }
        }
        $status = (string) ($form['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $this->add(['type' => 'status', 'value' => $status]);
        }
        if (ctype_digit(trim((string) ($form['parent'] ?? '')))) {
            $this->add(['type' => 'parent', 'value' => (int) $form['parent']]);
        }
        if (ctype_digit((string) ($form['template'] ?? ''))) {
            $tpl = self::findTemplate((string) $form['template']);
            $this->add(['type' => 'template', 'value' => (int) $form['template'], 'name' => $tpl['name'] ?? (string) $form['template']]);
        }
        foreach (['edited_from', 'edited_to'] as $name) {
            $v = trim((string) ($form[$name] ?? ''));
            if (self::isDate($v)) {
                $this->add(['type' => $name, 'value' => $v]);
            }
        }
    }

    private function addText(string $value, array $fields, bool $negate, bool $phrase): void
    {
        $this->add(['type' => 'text', 'value' => $value, 'fields' => $fields, 'negate' => $negate, 'phrase' => $phrase]);
    }

    private function add(array $condition): void
    {
        // parent:0 は「すべて」と同じ。子孫を引かず、条件にしない
        if ($condition['type'] === 'parent' && $condition['value'] === 0) {
            return;
        }
        $this->conditions[] = $condition;
    }

    /**
     * 入力を語に分ける。返り値の各要素は [除くか, 項目名（無ければ ''）, 値, 引用符で囲んだか]。
     * 引用符の中の空白は区切りにしない。閉じ忘れの引用符は、行末までを 1 つの語句とみなす
     *
     * @return array<int, array{0: bool, 1: string, 2: string, 3: bool}>
     */
    private static function tokenize(string $q): array
    {
        preg_match_all('/(-?)(?:([a-zA-Z]+):)?(?:"([^"]*)(?:"|$)|(\S+))/u', $q, $m, PREG_SET_ORDER);
        $tokens = [];
        foreach ($m as $t) {
            // 囲まない語（4 番目）は 1 文字以上なので、空なら引用符で囲んだ語句（3 番目）に当たっている
            $phrase = ($t[4] ?? '') === '';
            $value = $phrase ? ($t[3] ?? '') : $t[4];
            // 「-」だけ・語の途中の - は文字として扱う（"0.9.6-rc1" など）
            if ($t[1] === '-' && $value === '' && ($t[2] ?? '') === '') {
                continue;
            }
            $tokens[] = [$t[1] === '-', (string) ($t[2] ?? ''), trim($value), $phrase];
        }
        return $tokens;
    }

    // ---- 部品 ----

    /** LIKE の値として、% と _ と \ を文字に戻し、DB に渡す直前にエスケープする */
    private static function escapeLike(string $value): string
    {
        return db()->escape(addcslashes($value, '%_\\'));
    }

    /** YYYY-MM-DD（date の入力欄の形）か */
    public static function isDate(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
    }

    /** テンプレートを ID か名前（完全一致）で探す */
    private static function findTemplate(string $value): ?array
    {
        $where = ctype_digit($value)
            ? sprintf('id=%d', (int) $value)
            : sprintf("templatename='%s'", db()->escape($value));
        $row = db()->getRow(db()->select('id, templatename', '[+prefix+]site_templates', $where, '', '1'));
        if (!$row) {
            return $value === '0' ? ['id' => 0, 'name' => '(blank)'] : null;
        }
        return ['id' => (int) $row['id'], 'name' => $row['templatename']];
    }

    /** そのフォルダの下（子孫すべて。getChildIds() は削除済みを含まないので、DB を 1 段ずつたどる） */
    private static function descendants(int $parent): array
    {
        $ids = [];
        $level = [$parent];
        for ($depth = 0; $level && $depth < 50; $depth++) {
            $children = array_map('intval', array_column(db()->makeArray(db()->select(
                'id',
                '[+prefix+]site_content',
                'parent IN (' . implode(',', $level) . ')'
            )), 'id'));
            $level = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $level);
        }
        return $ids;
    }
}
