<?php
/**
 * 行の配列と列の定義から、ページ送り付きの HTML の表を作る部品。
 * 決まりは assets/docs/data-table.md を正本にする。
 *
 * 描く処理（DataTable::render()・DataTablePager::render()）は引数だけから HTML を作り、
 * evo()・db()・リクエスト・グローバル変数に触れない。リクエストを読むのは
 * DataTableLinks::fromRequest() だけで、型つきのヘルパー（helpers.php）を使う。
 * 保存済みの設定の文字列から表を作る入口の DataTableSettings だけは、evo()・db()・getv() を使う。
 */

/**
 * リンクの URL と、今の状態（ページ・並べ替え）を持つ。
 */
final class DataTableLinks
{
    private $baseUrl;
    private $params;
    private $page;
    private $orderby;
    private $orderdir;
    private $pageParam;

    public function __construct(
        string $baseUrl,
        array $params = [],
        int $page = 1,
        string $orderby = '',
        string $orderdir = 'asc',
        string $pageParam = 'page'
    ) {
        $this->baseUrl = $baseUrl;
        $this->params = $params;
        $this->page = $page;
        $this->orderby = $orderby;
        $this->orderdir = $orderdir === 'desc' ? 'desc' : 'asc';
        $this->pageParam = $pageParam;
    }

    /**
     * リクエストのページ番号と並べ替えを、型つきヘルパーで読んで作る。
     * orderby は $sortKeys（列の定義の sort のキー）にあるものだけを受ける。
     */
    public static function fromRequest(
        string $baseUrl,
        array $params,
        array $sortKeys,
        string $pageParam = 'page'
    ): self {
        return new self(
            $baseUrl,
            $params,
            get_intvar($pageParam),
            get_choicevar('orderby', $sortKeys, ''),
            get_choicevar('orderdir', ['asc', 'desc'], 'asc'),
            $pageParam
        );
    }

    public function page(): int
    {
        return $this->page;
    }

    public function orderby(): string
    {
        return $this->orderby;
    }

    public function orderdir(): string
    {
        return $this->orderdir;
    }

    public function pageParam(): string
    {
        return $this->pageParam;
    }

    /**
     * エスケープ前の URL。HTML に入れるときの hsc() は描く側が行う。
     * params → 今の並べ替え → 上書き の順に重ねる。
     */
    public function url(array $overrides = []): string
    {
        $q = $this->params;
        if ($this->orderby !== '') {
            $q['orderby'] = $this->orderby;
            $q['orderdir'] = $this->orderdir;
        }
        foreach ($overrides as $k => $v) {
            $q[$k] = $v;
        }
        $query = http_build_query($q, '', '&');
        if ($query === '') {
            return $this->baseUrl;
        }
        return $this->baseUrl . (strpos($this->baseUrl, '?') !== false ? '&' : '?') . $query;
    }
}

/**
 * ページ数・SQL の LIMIT/OFFSET を計算し、ページ送りを描く。
 */
final class DataTablePager
{
    private $total;
    private $perPage;
    private $page;

    public function __construct(int $total, int $perPage, int $requestedPage)
    {
        $this->total = max(0, $total);
        $this->perPage = max(0, $perPage);
        $this->page = ($requestedPage >= 1 && $requestedPage <= $this->pageCount()) ? $requestedPage : 1;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function pageCount(): int
    {
        if ($this->perPage <= 0) {
            return 1;
        }
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function offset(): int
    {
        return $this->perPage > 0 ? ($this->page - 1) * $this->perPage : 0;
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    /**
     * options: class, label, first, last, prev, next, window
     */
    public function render(DataTableLinks $links, array $options = []): string
    {
        $count = $this->pageCount();
        if ($count < 2) {
            return '';
        }
        $class = (string) ($options['class'] ?? 'pagination');
        $label = (string) ($options['label'] ?? '');
        $first = (string) ($options['first'] ?? '«');
        $last = (string) ($options['last'] ?? '»');
        $prev = (string) ($options['prev'] ?? '<');
        $next = (string) ($options['next'] ?? '>');
        $window = max(1, (int) ($options['window'] ?? 9));

        $param = $links->pageParam();
        $link = function ($n, $text, $current = false) use ($links, $param) {
            return sprintf(
                '<li><a href="%s"%s>%s</a></li>',
                hsc($links->url([$param => $n])),
                $current ? ' aria-current="page"' : '',
                hsc((string) $text)
            );
        };
        $disabled = function ($text) {
            return sprintf('<li><span>%s</span></li>', hsc((string) $text));
        };

        $items = [];
        if ($this->page > 1) {
            $items[] = $link(1, $first);
            $items[] = $link($this->page - 1, $prev);
        } else {
            $items[] = $disabled($first);
            $items[] = $disabled($prev);
        }

        // 今のページを中心に最大 $window 個
        $start = max(1, $this->page - (int) floor($window / 2));
        $end = min($count, $start + $window - 1);
        for ($n = $start; $n <= $end; $n++) {
            $items[] = $link($n, $n, $n === $this->page);
        }

        if ($this->page < $count) {
            $items[] = $link($this->page + 1, $next);
            $items[] = $link($count, $last);
        } else {
            $items[] = $disabled($next);
            $items[] = $disabled($last);
        }

        return sprintf(
            '<nav class="%s"%s><ul>%s</ul></nav>',
            hsc($class),
            $label !== '' ? ' aria-label="' . hsc($label) . '"' : '',
            implode("\n", $items)
        );
    }
}

/**
 * 行の配列と列の定義から表を描く。
 *
 * 列の定義: key, label, labelHtml, format, html, class, sort
 * options: id, tableClass, rowClasses, headerClass, caption, captionHtml,
 *          emptyMessage, showHeader, rowAttributes
 */
final class DataTable
{
    private $columns;
    private $options;

    public function __construct(array $columns, array $options = [])
    {
        $this->columns = array_values($columns);
        $this->options = $options + [
            'id' => '',
            'tableClass' => 'grid',
            'rowClasses' => [],
            'headerClass' => '',
            'caption' => null,
            'captionHtml' => false,
            'emptyMessage' => '',
            'showHeader' => true,
            'rowAttributes' => null,
        ];
    }

    public function render(array $rows, ?DataTableLinks $links = null): string
    {
        $o = $this->options;
        $out = sprintf(
            '<div class="data-table" data-table="%s"><table class="%s">',
            hsc((string) $o['id']),
            hsc((string) $o['tableClass'])
        );

        if ($o['caption'] !== null && $o['caption'] !== '') {
            $out .= '<caption>'
                . ($o['captionHtml'] ? (string) $o['caption'] : hsc((string) $o['caption']))
                . '</caption>';
        }

        if ($o['showHeader']) {
            $out .= $this->renderHead($links);
        }

        $out .= "<tbody>\n";
        if (!$rows) {
            $colspan = count($this->columns) > 1 ? ' colspan="' . count($this->columns) . '"' : '';
            $out .= sprintf(
                '<tr><td%s class="data-table-empty">%s</td></tr>',
                $colspan,
                hsc((string) $o['emptyMessage'])
            );
            $out .= "\n";
        }
        $i = 0;
        foreach ($rows as $row) {
            $out .= $this->renderRow((array) $row, $i++) . "\n";
        }
        $out .= '</tbody></table></div>';
        return $out;
    }

    private function renderHead(?DataTableLinks $links): string
    {
        $cells = '';
        foreach ($this->columns as $col) {
            $label = (string) ($col['label'] ?? '');
            $text = !empty($col['labelHtml']) ? $label : hsc($label);
            $attr = $this->classAttr([$col['class'] ?? '', $this->options['headerClass']]);
            $sort = $col['sort'] ?? null;
            if ($sort !== null && $links) {
                $current = $links->orderby() === $sort;
                $dir = ($current && $links->orderdir() === 'asc') ? 'desc' : 'asc';
                if ($current) {
                    $attr .= ' aria-sort="' . ($links->orderdir() === 'asc' ? 'ascending' : 'descending') . '"';
                }
                $text = sprintf(
                    '<a href="%s">%s</a>',
                    hsc($links->url(['orderby' => $sort, 'orderdir' => $dir])),
                    $text
                );
            }
            $cells .= sprintf('<th scope="col"%s>%s</th>', $attr, $text);
        }
        return "<thead><tr>{$cells}</tr></thead>\n";
    }

    private function renderRow(array $row, int $index): string
    {
        $rowClasses = (array) $this->options['rowClasses'];
        $class = $rowClasses ? [$rowClasses[$index % count($rowClasses)]] : [];
        $attr = '';
        if (is_callable($this->options['rowAttributes'])) {
            foreach ((array) call_user_func($this->options['rowAttributes'], $row) as $name => $value) {
                // class は rowClasses の値と 1 つの属性にまとめる
                if (strtolower((string) $name) === 'class') {
                    $class[] = (string) $value;
                    continue;
                }
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_:-]*$/', (string) $name) || stripos((string) $name, 'on') === 0
                    || in_array(strtolower((string) $name), ['style', 'width', 'align', 'bgcolor'], true)) {
                    continue;
                }
                $attr .= sprintf(' %s="%s"', $name, hsc((string) $value));
            }
        }
        $attr = $this->classAttr($class) . $attr;

        $cells = '';
        foreach ($this->columns as $col) {
            $key = $col['key'];
            $value = $row[$key] ?? null;
            if (isset($col['format']) && is_callable($col['format'])) {
                $value = call_user_func($col['format'], $value, $row);
            }
            $value = is_scalar($value) ? (string) $value : '';
            if (empty($col['html'])) {
                $value = hsc($value);
            }
            $cells .= sprintf('<td%s>%s</td>', $this->classAttr([$col['class'] ?? '']), $value);
        }
        return "<tr{$attr}>{$cells}</tr>";
    }

    private function classAttr(array $classes): string
    {
        $class = trim(implode(' ', array_filter(array_map('strval', $classes), 'strlen')));
        return $class === '' ? '' : ' class="' . hsc($class) . '"';
    }
}

/**
 * サイト側の表（TV の出力ウィジェット datagrid・修飾子 :datagrid・db()->getHTMLGrid()）の入口。
 * 保存済みの設定の文字列（区切り文字のテキスト・列名・型）から DataTable を作って描く。
 * 3 つの呼び出し側は、それぞれの設定のキーを下の $settings の形に読み替えて render() を呼ぶ。
 *
 * この入口は evo()・db()・getv() を使う（DataTable・DataTablePager とは違う）。
 *
 * $source: DB の結果、または区切り文字のテキスト（1 行のファイルのパスも可）
 * $settings のキー（無いキーは既定値）:
 *   columns, fields, types, delimiter, wrapper, encoding, detectHeader, pageSize, pagerLocation,
 *   header, footer, tableClass, itemClass, altItemClass, headerClass, pagerClass, emptyMessage
 * 見た目の指定（style・width・align・bgcolor・cellpadding・cellspacing）は受けない。
 */
final class DataTableSettings
{
    public static function render($source, array $settings, string $id): string
    {
        $delimiter = (string) ($settings['delimiter'] ?? '');
        if ($delimiter === 'tab') {
            $delimiter = "\t";
        } elseif ($delimiter === '') {
            $delimiter = ',';
        }
        $wrapper = (string) ($settings['wrapper'] ?? '');
        $keys = [];
        $names = [];

        if (db()->isResult($source)) {
            $rows = [];
            while ($row = db()->getRow($source)) {
                $rows[] = $row;
            }
            $fields = trim((string) ($settings['fields'] ?? ''));
            if ($fields !== '') {
                $keys = array_map('trim', explode(',', $fields));
            } elseif ($rows) {
                $keys = array_keys($rows[0]);
            }
            $names = self::splitList((string) ($settings['columns'] ?? ''));
            if (!$names) {
                $names = array_map('strval', $keys);
            }
        } else {
            $rows = self::readText(
                (string) $source,
                $delimiter,
                $wrapper,
                (string) ($settings['encoding'] ?? '')
            );
            if (($settings['detectHeader'] ?? '') === 'first line' && $rows) {
                $names = array_map('strval', array_shift($rows));
            } else {
                $names = self::splitList((string) ($settings['columns'] ?? ''));
            }
            $count = count($names);
            if (!$count) {
                foreach ($rows as $row) {
                    $count = max($count, count($row));
                }
            }
            $keys = $count ? range(0, $count - 1) : [];
        }

        $types = array_map('trim', self::splitList((string) ($settings['types'] ?? '')));
        $count = max(1, count($names) ?: count($keys));
        $columns = [];
        for ($i = 0; $i < $count; $i++) {
            $columns[] = self::typedColumn(
                ['key' => $keys[$i] ?? $i, 'label' => $names[$i] ?? ''],
                $types[$i] ?? ''
            );
        }

        $pagerLocation = 'top';
        if (preg_match('/^(top|both|bottom)/', strtolower(trim((string) ($settings['pagerLocation'] ?? ''))), $m)) {
            $pagerLocation = $m[1];
        }
        $pageSize = (int) ($settings['pageSize'] ?? 0);
        $nav = '';
        if ($pageSize > 0) {
            $links = self::linksFor($id);
            $pager = new DataTablePager(count($rows), $pageSize, $links->page());
            $rows = array_slice($rows, $pager->offset(), $pager->limit());
            $nav = $pager->render($links, ['class' => (string) ($settings['pagerClass'] ?? '') ?: 'pager']);
        }

        $itemClass = (string) ($settings['itemClass'] ?? '');
        $altItemClass = (string) ($settings['altItemClass'] ?? '');
        $options = [
            'id' => $id,
            'rowClasses' => ($itemClass !== '' || $altItemClass !== '') ? [$itemClass, $altItemClass] : [],
            'headerClass' => (string) ($settings['headerClass'] ?? ''),
            'caption' => (string) ($settings['header'] ?? ''),
            'captionHtml' => true,
            'emptyMessage' => (string) ($settings['emptyMessage'] ?? ''),
            'showHeader' => (bool) $names,
        ];
        $tableClass = (string) ($settings['tableClass'] ?? '');
        if ($tableClass !== '') {
            $options['tableClass'] = $tableClass;
        }
        $table = new DataTable($columns, $options);

        $out = '<div class="gridwrap">';
        $out .= ($pagerLocation === 'top' || $pagerLocation === 'both') ? $nav : '';
        $out .= $table->render($rows);
        $out .= ($pagerLocation === 'bottom' || $pagerLocation === 'both') ? $nav : '';
        $footer = (string) ($settings['footer'] ?? '');
        if ($footer !== '') {
            $out .= '<div class="gridfooter">' . $footer . "</div>\n";
        }
        return $out . '</div>';
    }

    /**
     * ページ送りのリンク。今のリクエストの引数のうち、ページ番号（{id}_page）と、
     * サイト側では id（makeUrl() が作る）以外を残す。値は URL に入れるとき http_build_query() が、
     * HTML に入れるとき DataTablePager が整える。
     */
    private static function linksFor(string $id): DataTableLinks
    {
        $pageParam = "{$id}_page";
        $params = (array) getv();
        unset($params[$pageParam]);
        if (evo()->isFrontend()) {
            unset($params['id']);
            $baseUrl = evo()->makeUrl(evo()->documentIdentifier);
        } else {
            $baseUrl = 'index.php';
        }
        return DataTableLinks::fromRequest($baseUrl, $params, [], $pageParam);
    }

    /**
     * 行ごと・区切り文字の文字列ごとに分ける。空の行は飛ばす。
     * 1 行だけで、バインディングが有効で場所の判定を通るファイル（.php 以外）なら、その中身を読む。
     */
    private static function readText(string $text, string $delimiter, string $wrapper, string $encoding): array
    {
        $text = trim($text);
        if ($text !== '' && strpos($text, "\n") === false && evo()->config('enable_bindings') == 1) {
            $path = evo()->resolveBindFilePath($text);
            if ($path !== false && evo()->getExtention($path) !== '.php') {
                $text = trim((string) file_get_contents($path));
                if ($text !== '' && $encoding !== '') {
                    $text = mb_convert_encoding($text, evo()->config('modx_charset'), $encoding);
                }
            }
        }
        $rows = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = explode($delimiter, $line);
            if ($wrapper !== '') {
                $cells = array_map(function ($cell) use ($wrapper) {
                    return trim($cell, $wrapper);
                }, $cells);
            }
            $rows[] = $cells;
        }
        return $rows;
    }

    /** 列名・型の文字列は、`||` を含めば `||`、無ければ `,` で分ける */
    private static function splitList(string $text): array
    {
        if ($text === '') {
            return [];
        }
        return explode(strpos($text, '||') !== false ? '||' : ',', $text);
    }

    /**
     * 型（integer・float[:桁]・currency[:桁]・date[:形]・boolean・description・template:文字列）の書式を、
     * 列の定義に足す。型のある列は html => true で、値のエスケープは書式の側で行う。
     * 型が無い・知らない型は、DataTable の既定のエスケープに任せる。
     */
    private static function typedColumn(array $column, string $spec): array
    {
        [$type, $format] = explode(':', $spec, 2) + ['', ''];
        $type = strtolower(trim($type));
        $decimals = is_numeric($format) ? (int) $format : 2;
        // 数として読めない値は、書式を当てずにエスケープして出す
        $numeric = function ($value, callable $fn) {
            if (is_scalar($value) && is_numeric($value)) {
                return $fn((float) $value);
            }
            return hsc(is_scalar($value) ? (string) $value : '');
        };

        switch ($type) {
            case 'integer':
                $column['format'] = function ($value) use ($numeric) {
                    return $numeric($value, function ($n) {
                        return number_format($n);
                    });
                };
                break;
            case 'float':
                $column['format'] = function ($value) use ($numeric, $decimals) {
                    return $numeric($value, function ($n) use ($decimals) {
                        return number_format($n, $decimals);
                    });
                };
                break;
            case 'currency':
                $column['format'] = function ($value) use ($numeric, $decimals) {
                    return $numeric($value, function ($n) use ($decimals) {
                        return '$' . number_format($n, $decimals);
                    });
                };
                break;
            case 'date':
                $column['format'] = function ($value) use ($format) {
                    if (!is_scalar($value) || empty($value)) {
                        return '-';
                    }
                    $time = is_numeric($value) ? (int) $value : strtotime((string) $value);
                    if ($time === false) {
                        return hsc((string) $value);
                    }
                    return hsc((string) evo()->mb_strftime($format !== '' ? $format : '%A %d, %B %Y', $time));
                };
                break;
            case 'boolean':
                $column['format'] = function ($value) {
                    return is_scalar($value) && is_numeric($value) && round((float) $value) != 0 ? '&bull;' : '';
                };
                break;
            case 'description':
                $column['format'] = function ($value) {
                    return element_description_html(is_scalar($value) ? (string) $value : '');
                };
                break;
            case 'template':
                // テンプレートは設定した人の HTML のまま。差し込む値だけをエスケープする
                // （strtr は置換後の文字列を走査し直さないので、値の中の [+…+] は展開されない）
                $column['format'] = function ($value, array $row) use ($format) {
                    $pairs = [];
                    foreach ($row as $k => $v) {
                        if (is_scalar($v)) {
                            $pairs["[+{$k}+]"] = hsc((string) $v);
                        }
                    }
                    $pairs['[+value+]'] = hsc(is_scalar($value) ? (string) $value : '');
                    return strtr($format !== '' ? $format : '[+value+]', $pairs);
                };
                break;
            default:
                return $column;
        }
        $column['html'] = true;
        return $column;
    }
}
