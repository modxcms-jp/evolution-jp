<?php
/**
 * 行の配列と列の定義から、ページ送り付きの HTML の表を作る部品。
 * 決まりは assets/docs/data-table.md を正本にする。
 *
 * 描く処理（DataTable::render()・DataTablePager::render()）は引数だけから HTML を作り、
 * evo()・db()・リクエスト・グローバル変数に触れない。リクエストを読むのは
 * DataTableLinks::fromRequest() だけで、型つきのヘルパー（helpers.php）を使う。
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
        $count = $this->perPage > 0 ? max(1, (int) ceil($this->total / $this->perPage)) : 1;
        $this->page = ($requestedPage >= 1 && $requestedPage <= $count) ? $requestedPage : 1;
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
        $classes = (array) $this->options['rowClasses'];
        $attr = $classes ? $this->classAttr([$classes[$index % count($classes)]]) : '';
        if (is_callable($this->options['rowAttributes'])) {
            foreach ((array) call_user_func($this->options['rowAttributes'], $row) as $name => $value) {
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_:-]*$/', (string) $name) || stripos((string) $name, 'on') === 0
                    || in_array(strtolower((string) $name), ['style', 'width', 'align', 'bgcolor'], true)) {
                    continue;
                }
                $attr .= sprintf(' %s="%s"', $name, hsc((string) $value));
            }
        }

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
