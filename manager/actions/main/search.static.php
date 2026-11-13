<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
unset($_SESSION['itemname']); // clear this, because it's only set for logging purposes

include_once MODX_CORE_PATH . 'resource_search_query.class.inc.php';

/*
 * 検索の入力。ふだんは 1 つの欄（q）と「本文も探す」だけで、「詳しく検索」を開くと欄ごとの条件と絞り込みが出る。
 * 前の版のパラメータ（searchid・url・pagetitle・longtitle・alias・content）も受ける（ブックマーク・リンクのため）
 */
// 文字列の入力。不正な UTF-8 は取り除き、前後の空白を除く
$textParam = static function ($name) {
    return trim(mb_scrub(get_strvar($name)));
};
$q = $textParam('q');
if ($q === '') {
    // 前の版の「ID」「URL」の欄は、どちらも 1 つのリソースを指す入力なので、1 つの欄に読み替える
    // （url の欄から読み替えたときは、数字でも http(s):// でもなければ、サイトの基準のパスからのパスとして、URL の形にそろえる。
    // 並べ替えやページ送りのリンクは q で作るので、そろえた形が引き継がれる）
    $q = $textParam('searchid');
    if ($q === '') {
        $q = $textParam('url');
        if ($q !== '' && !ctype_digit($q) && !preg_match('@^https?://@i', $q) && strpos($q, MODX_BASE_URL) !== 0) {
            $q = MODX_BASE_URL . ltrim($q, '/');
        }
    }
}
$alsoContent = get_boolvar('also_content');
$advanced = [
    'pagetitle' => $textParam('pagetitle'),
    'longtitle' => $textParam('longtitle'),
    'alias' => $textParam('alias'),
    'content' => $textParam('content'),
];
// 既定（空）は削除済みを除くすべて。with_deleted は削除済みも含めたすべて
$status = get_choicevar('status', ResourceSearchQuery::STATUSES, '');
// 0 以上の整数（未指定・範囲外は null。0 は「親がトップ」「テンプレートなし」として使う）
$parent = get_uintvar('parent');
$template = get_uintvar('template');
// 更新日（editedon）の範囲。YYYY-MM-DD だけを受ける（date の入力欄の形）
$dateParam = static function ($name) use ($textParam) {
    $v = $textParam($name);
    return ResourceSearchQuery::isDate($v) ? $v : '';
};
$editedFrom = $dateParam('edited_from');
$editedTo = $dateParam('edited_to');
// 詳しい条件が 1 つでもあれば、「詳しく検索」を開いた状態で出す
$advancedUsed = array_filter($advanced, 'strlen') || $status !== '' || $parent !== null || $template !== null
    || $editedFrom !== '' || $editedTo !== '';

$templates = db()->makeArray(db()->select('id, templatename', '[+prefix+]site_templates', '', 'templatename'));
$statusLabels = [
    'published' => 'page_data_published',
    'unpublished' => 'page_data_unpublished',
    'deleted' => 'search_status_deleted',
    'with_deleted' => 'search_status_with_deleted',
];
$selected = static function ($value, $current) {
    return (string) $value === (string) $current ? ' selected' : '';
};
?>
    <h1><?= lang('search_criteria') ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <li
                id="Button5"
                class="mutate"
            ><a
                    href="#"
                    onclick="EvoShell.go('index.php?a=2', {discard: true});"
                ><?= lang('cancel') ?></a></li>
        </ul>
    </div>
    <div id="search-sticky-sentinel" aria-hidden="true"></div>
    <div class="section search-sticky" id="search-sticky">
        <div class="sectionBody">
            <form action="index.php" method="get" id="search-form">
                <input type="hidden" name="a" value="71"/>
                <div class="search-simple">
                    <input
                        name="q"
                        type="search"
                        value="<?= hsc($q) ?>"
                        placeholder="<?= hsc(lang('search_simple_placeholder')) ?>"
                        aria-label="<?= hsc(lang('search_simple_placeholder')) ?>"
                        aria-describedby="search-help"
                        autofocus
                    />
                    <button type="submit" name="submitok" value="1" class="button primary"><?= lang('search') ?></button>
                    <label class="search-also"><input type="checkbox" name="also_content" value="1"<?= $alsoContent ? ' checked' : '' ?>/> <?= lang('search_also_content') ?></label>
                </div>
                <!-- 2 行目: 説明と、閉じた「書き方」「詳しく検索」を 1 行に並べる。開いたものだけ、中身を下に全幅で出す -->
                <div class="search-row2">
                <span id="search-help" class="search-help"><?= lang('search_simple_help') ?></span>
                <details class="search-syntax">
                    <summary><?= lang('search_syntax') ?></summary>
                    <div class="search-details-body">
                    <!-- ほかの画面（システム情報など）と同じ表のスタイル -->
                    <table class="grid">
                        <thead>
                            <tr><th><?= lang('search_syntax_example') ?></th><th><?= lang('search_syntax_meaning') ?></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (['and', 'phrase', 'not', 'field', 'ids', 'is', 'date', 'link'] as $key): ?>
                            <tr><td class="search-syntax-example"><?= hsc(lang('search_syntax_ex_' . $key)) ?></td><td><?= lang('search_syntax_' . $key) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </details>

                <details class="search-advanced" id="search-advanced"<?= $advancedUsed ? ' open' : '' ?>>
                    <summary><?= lang('search_advanced') ?></summary>
                    <div class="search-details-body">
                    <div class="search-advanced-grid">
                        <p class="search-advanced-note"><?= lang('search_advanced_note') ?></p>
                        <label for="s-pagetitle"><?= lang('search_criteria_title') ?></label>
                        <input id="s-pagetitle" name="pagetitle" type="text" value="<?= hsc($advanced['pagetitle']) ?>"/>
                        <label for="s-longtitle"><?= lang('search_criteria_longtitle') ?></label>
                        <input id="s-longtitle" name="longtitle" type="text" value="<?= hsc($advanced['longtitle']) ?>"/>
                        <label for="s-alias"><?= lang('search_criteria_alias') ?></label>
                        <input id="s-alias" name="alias" type="text" value="<?= hsc($advanced['alias']) ?>"/>
                        <label for="s-content"><?= lang('search_criteria_content') ?></label>
                        <input id="s-content" name="content" type="text" value="<?= hsc($advanced['content']) ?>"/>
                        <div class="search-sep"></div>
                        <label for="s-status"><?= lang('search_filter_status') ?></label>
                        <select id="s-status" name="status">
                            <option value=""><?= lang('search_status_default') ?></option>
                            <?php foreach (ResourceSearchQuery::STATUSES as $s): ?>
                                <option value="<?= $s ?>"<?= $selected($s, $status) ?>><?= lang($statusLabels[$s]) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="s-parent"><?= lang('search_filter_parent') ?></label>
                        <input id="s-parent" name="parent" type="text" inputmode="numeric" value="<?= $parent === null ? '' : $parent ?>" placeholder="<?= hsc(lang('search_filter_parent_placeholder')) ?>"/>
                        <label for="s-template"><?= lang('template') ?></label>
                        <select id="s-template" name="template">
                            <option value=""><?= lang('search_any') ?></option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"<?= $selected($t['id'], $template) ?>><?= hsc($t['templatename']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span><?= lang('search_filter_edited') ?></span>
                        <span class="search-range">
                            <input name="edited_from" type="date" value="<?= hsc($editedFrom) ?>" aria-label="<?= hsc(lang('search_filter_edited_from')) ?>"/>
                            <?= lang('search_range_separator') ?>
                            <input name="edited_to" type="date" value="<?= hsc($editedTo) ?>" aria-label="<?= hsc(lang('search_filter_edited_to')) ?>"/>
                        </span>
                    </div>
                    </div>
                </details>
                <script>
                    // この画面だけの動き。シェルの画面遷移では同じ window でこの script が読み直されるので、
                    // 前の画面の登録（resize・Observer）は、読み直しのとき・画面を離れたときに外す。
                    // この script は #mainPane の最後の子にしない（テーマの「最後の子」の規則に掛からないよう、フォームの中に置く）
                    (function () {
                        if (window.evoSearchTeardown) {
                            window.evoSearchTeardown();
                        }
                        var cleanups = [];

                        // 「書き方」「詳しく検索」を開閉するとき、中身の高さを動かして、どこが開いたかを分かるようにする。
                        // 視差効果を減らす設定の人には動かさない
                        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        document.querySelectorAll('.search-row2 > details').forEach(function (d) {
                            var body = d.querySelector('.search-details-body');
                            var summary = d.querySelector('summary');
                            if (!body || !summary) {
                                return;
                            }
                            // 利用者が summary を押した印。toggle は、プログラムが open を変えたときにも発火するので、区別に使う
                            summary.addEventListener('click', function () {
                                d.dataset.userToggle = '1';
                            }, true);
                            if (reduce || !body.animate) {
                                return;
                            }
                            var running = null;
                            var closing = false; // 閉じている途中か（d.open は終わるまで true のまま）
                            summary.addEventListener('click', function (e) {
                                e.preventDefault();
                                if (running) {
                                    running.cancel();
                                }
                                var opening = closing || !d.open;
                                closing = !opening;
                                if (opening) {
                                    d.open = true;
                                }
                                var h = body.scrollHeight;
                                running = body.animate(
                                    opening
                                        ? [{height: '0px', opacity: 0}, {height: h + 'px', opacity: 1}]
                                        : [{height: h + 'px', opacity: 1}, {height: '0px', opacity: 0}],
                                    {duration: 200, easing: 'ease-out'}
                                );
                                running.onfinish = function () {
                                    running = null;
                                    closing = false;
                                    if (!opening) {
                                        d.open = false;
                                    }
                                };
                            });
                        });

                        // 検索条件の欄を上に貼り付ける。欄が画面の高さの 4 割を超える（「詳しく検索」を開いたときなど）は貼り付けない。
                        // 貼り付いているあいだは影を付けて、結果との境目を分かるようにする（目印の要素が見えなくなったら貼り付いている）
                        var box = document.getElementById('search-sticky');
                        var sentinel = document.getElementById('search-sticky-sentinel');
                        if (box && sentinel) {
                            // スクロールする領域（管理画面のシェルでは main#mainPane）の上の余白と背景色を、CSS の変数に渡す
                            var scroller = box.parentElement;
                            while (scroller && scroller !== document.body && !/(auto|scroll)/.test(getComputedStyle(scroller).overflowY)) {
                                scroller = scroller.parentElement;
                            }
                            if (scroller) {
                                var cs = getComputedStyle(scroller);
                                var bg = cs.backgroundColor;
                                if (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') {
                                    bg = getComputedStyle(document.body).backgroundColor;
                                }
                                box.style.setProperty('--search-sticky-pad', cs.paddingTop);
                                box.style.setProperty('--search-sticky-bg', bg);
                            }
                            var update = function () {
                                if (!box.isConnected) {
                                    if (window.evoSearchTeardown) {
                                        window.evoSearchTeardown();
                                    }
                                    return;
                                }
                                // 貼り付けると padding-top が足される。判定が揺れないよう、その分を引いた高さで比べる
                                var height = box.offsetHeight - parseFloat(getComputedStyle(box).paddingTop || 0);
                                box.classList.toggle('is-sticky', height < window.innerHeight * 0.4);
                            };
                            update();
                            window.addEventListener('resize', update);
                            cleanups.push(function () {
                                window.removeEventListener('resize', update);
                            });
                            box.querySelectorAll('details').forEach(function (d) {
                                d.addEventListener('toggle', update);
                            });
                            if (window.ResizeObserver) {
                                var ro = new ResizeObserver(update);
                                ro.observe(box);
                                cleanups.push(function () {
                                    ro.disconnect();
                                });
                            }
                            if (window.IntersectionObserver) {
                                var io = new IntersectionObserver(function (entries) {
                                    box.classList.toggle('is-stuck', !entries[0].isIntersecting && box.classList.contains('is-sticky'));
                                });
                                io.observe(sentinel);
                                cleanups.push(function () {
                                    io.disconnect();
                                });
                            }
                        }

                        // 「詳しく検索」の開閉を覚える（条件を入れて検索したときは、覚えた状態より条件のほうを優先して開く）。
                        // 保存するのは、利用者が summary を押して開閉したときだけ（条件で開いた・覚えた状態で開いたときは書き換えない）
                        var adv = document.getElementById('search-advanced');
                        if (adv) {
                            try {
                                if (!adv.open && localStorage.getItem('evo.search.advanced') === '1') {
                                    adv.open = true;
                                }
                            } catch (e) {}
                            adv.addEventListener('toggle', function () {
                                if (!adv.dataset.userToggle) {
                                    return;
                                }
                                delete adv.dataset.userToggle;
                                try {
                                    localStorage.setItem('evo.search.advanced', adv.open ? '1' : '0');
                                } catch (e) {}
                            });
                        }

                        // 画面が差し替わったあと（evoshell:load）に、この画面がもう無ければ外す。
                        // モーダルの開け閉めでも evoshell:unload / evoshell:load は出るので、unload では外さず、box が残っているかで決める
                        // （export_site.static.php と同じ作法）。resize のときの update の isConnected も同じ確認をする
                        var onShellLoad = function () {
                            if (box && !box.isConnected && window.evoSearchTeardown) {
                                window.evoSearchTeardown();
                            }
                        };
                        window.evoSearchTeardown = function () {
                            document.removeEventListener('evoshell:load', onShellLoad);
                            cleanups.forEach(function (fn) {
                                fn();
                            });
                            cleanups = [];
                            window.evoSearchTeardown = null;
                        };
                        document.addEventListener('evoshell:load', onShellLoad);
                    })();
                </script>
                </div>
            </form>
        </div>
    </div>
<?php
if (getv('submitok')) {

    // 入力欄の書き方と「詳しく検索」の欄を、同じ条件の並びに読み替えてから WHERE を組む
    $query = new ResourceSearchQuery($q, $alsoContent, [
        'pagetitle' => $advanced['pagetitle'],
        'longtitle' => $advanced['longtitle'],
        'alias' => $advanced['alias'],
        'content' => $advanced['content'],
        'status' => $status,
        'parent' => $parent === null ? '' : (string) $parent,
        'template' => $template === null ? '' : (string) $template,
        'edited_from' => $editedFrom,
        'edited_to' => $editedTo,
    ]);
    $built = $query->toWhere();
    $where = $built['where'];
    // ID・URL から引けなかったとき（何も当たらない条件になっている。理由は結果の欄に出す）
    $targetError = $built['error'];
    $searchTargetInput = $query->target();

    // 閲覧できるリソースの条件（文書グループ。式はここ 1 か所）。checkPermissions() と同じく、use_udperms が無効・管理者なら制限しない。
    // tree_show_protected が無効なら、ツリー（nodes.php の getNodes()）と同じく結果から外す（$accessWhere）。
    // 有効なら、ツリーと同じくタイトルは出すが、本文の抜粋は閲覧できる行にだけ作る（can_view。document_data.static.php は拒否するため）
    $accessExpr = '';
    if (config('use_udperms') && !manager()->isAdmin()) {
        $mgrDocgroups = array_map('intval', (array) sessionv('mgrDocgroups', []));
        $access = 'privatemgr=0';
        if ($mgrDocgroups) {
            $access .= sprintf(
                ' OR id IN (SELECT document FROM %s WHERE document_group IN (%s))',
                db()->getFullTableName('document_groups'),
                implode(',', $mgrDocgroups)
            );
        }
        $accessExpr = '(' . $access . ')';
    }
    $accessWhere = config('tree_show_protected') ? '' : $accessExpr;
    $canViewColumn = config('tree_show_protected') && $accessExpr !== '' ? $accessExpr : '1';

    if ($accessWhere !== '') {
        $where[] = $accessWhere;
    }

    // 数字だけで探したときは ID で出しつつ、その数字を文字として含むものの件数を添える（"20" と囲めば文字として探せる）
    $bareNumber = $query->bareNumber();
    $asTextCount = 0;
    if ($bareNumber !== '') {
        $asText = new ResourceSearchQuery('"' . $bareNumber . '"', $alsoContent);
        // リンク先の検索と同じく、文書グループの条件も掛ける（見てはいけないリソースを件数に入れない）
        $asTextWhere = $asText->toWhere()['where'];
        if ($accessWhere !== '') {
            $asTextWhere[] = $accessWhere;
        }
        $asTextCount = (int) db()->getValue(db()->select('COUNT(id)', '[+prefix+]site_content', implode(' AND ', $asTextWhere)));
    }

    include_once(MODX_CORE_PATH . 'controls/data_table.class.php');

    // 並べ替えのキー => ORDER BY の式（リクエストの値を直接 SQL に入れない）
    $sortColumns = ['id' => 'id', 'pagetitle' => 'pagetitle'];
    $linkParams = ['a' => 71, 'submitok' => 1];
    $keep = [
        'q' => $q,
        'also_content' => $alsoContent ? '1' : '',
        'status' => $status,
        'parent' => $parent === null ? '' : (string) $parent,
        'template' => $template === null ? '' : (string) $template,
        'edited_from' => $editedFrom,
        'edited_to' => $editedTo,
    ] + $advanced;
    foreach ($keep as $name => $value) {
        if ($value !== '') {
            $linkParams[$name] = $value;
        }
    }
    $links = DataTableLinks::fromRequest('index.php', $linkParams, array_keys($sortColumns));
    // 並べ替えの指定が無いときの既定はタイトル昇順（見出しの aria-sort は付かない）
    $orderby = 'pagetitle ASC, id ASC';
    if ($links->orderby() !== '') {
        $dir = $links->orderdir() === 'desc' ? 'DESC' : 'ASC';
        $orderby = sprintf('%s %s, id %s', $sortColumns[$links->orderby()], $dir, $dir);
    }

    // 件数を数えてから、そのページの行だけを取る
    $total = (int) db()->getValue(
        db()->select('COUNT(id)', '[+prefix+]site_content', implode(' and ', $where))
    );
    $pager = new DataTablePager($total, (int) config('number_of_results') ?: 20, $links->page());
    $rs = db()->select(
        // 本文は抜粋に使う先頭だけを取る（大きな本文を全件ぶん読まない）
        'id, contenttype, pagetitle, longtitle, description, SUBSTRING(content, 1, 5000) AS content_head, ' . $canViewColumn . ' AS can_view, deleted, published, isfolder, type',
        '[+prefix+]site_content',
        implode(' and ', $where),
        $orderby,
        $pager->limit() > 0 ? sprintf('%d, %d', $pager->offset(), $pager->limit()) : ''
    );
    // タイトルを押したときの行き先。編集の権限が無ければ、リソースの情報の画面
    $action = evo()->hasPermission('edit_document') ? '27' : '3';
    ?>
    <div class="section">
        <div class="sectionHeader"><?= lang('search_results') ?></div>
        <div class="sectionBody">
            <?php
            // 読み取った条件（何で探したかを見せる。書き方を覚える助けにもなる）
            echo '<ul class="search-conds">';
            foreach ($query->describe() as $cond) {
                printf('<li>%s</li>', hsc($cond));
            }
            echo '</ul>';
            if ($bareNumber !== '' && $asTextCount > 0) {
                $asTextParams = ['a' => 71, 'submitok' => 1, 'q' => '"' . $bareNumber . '"'] + ($alsoContent ? ['also_content' => 1] : []);
                printf(
                    '<p class="search-astext"><a href="index.php?%s">%s</a></p>',
                    hsc(http_build_query($asTextParams)),
                    hsc(sprintf(lang('search_as_text'), $bareNumber, $asTextCount))
                );
            }
            if ($targetError !== '') {
                // ID または URL からリソースを引けなかった（ほかの条件で絞る前に止まっている）
                printf('<p class="search-target-error">%s</p>', sprintf(hsc(lang($targetError)), '<code>' . hsc($searchTargetInput) . '</code>'));
            } elseif ($total < 1) {
                printf('<p class="search-empty">%s</p>', lang('search_empty'));
            } else {
                // 件数は表の上の行（ページ送りの左）に出す
                $count = sprintf(lang('search_results_returned_msg'), $total);

                // icons by content type
                $icons = [
                    'application/rss+xml' => style('tree_page_rss'),
                    'application/pdf' => style('tree_page_pdf'),
                    'application/vnd.ms-word' => style('tree_page_word'),
                    'application/vnd.ms-excel' => style('tree_page_excel'),
                    'text/css' => style('tree_page_css'),
                    'text/html' => style('tree_page_html'),
                    'text/plain' => style('tree_page'),
                    'text/xml' => style('tree_page_xml'),
                    'text/javascript' => style('tree_page_js'),
                    'image/gif' => style('tree_page_gif'),
                    'image/jpg' => style('tree_page_jpg'),
                    'image/png' => style('tree_page_png')
                ];

                $rows = [];
                while ($row = db()->getRow($rs)) {
                    // figure out the icon for the document...
                    if ($row['type'] === 'reference') {
                        $row['icon'] = style('tree_linkgo');
                    } elseif ($row['isfolder'] == 0) {
                        $row['icon'] = $icons[$row['contenttype']] ?? style('tree_page_html');
                    } else {
                        $row['icon'] = style('tree_folder');
                    }
                    $rows[] = $row;
                }

                // 公開していない・削除済みのリソースは、タイトルと説明の文字の色で示す（削除済みを優先）
                $wrapNode = function ($html, array $row) {
                    $class = '';
                    if ($row['deleted'] == 1) {
                        $class = 'deletedNode';
                    } elseif ($row['published'] == 0) {
                        $class = 'unpublishedNode';
                    }
                    return $class === '' ? $html : sprintf('<span class="%s">%s</span>', $class, $html);
                };
                $terms = $query->terms();
                $charset = $modx_manager_charset;
                // 抜粋: 説明があれば説明、無ければ本文（HTML・MODX のタグを外す）。本文では、探した語の前後を切り出す
                $snippet = function (array $row) use ($terms, $charset) {
                    $text = trim((string) $row['description']);
                    $fromContent = $text === '';
                    if ($fromContent) {
                        // 正規表現が失敗しても（不正な UTF-8・バックトラックの上限）、空にして抜粋なしにする
                        if (!(int) $row['can_view']) {
                            return ''; // 閲覧できないリソースの本文は、抜粋に出さない
                        }
                        $text = mb_scrub((string) $row['content_head']);
                        $text = preg_replace('/\[\[.*?\]\]|\[\*.*?\*\]|\[\+.*?\+\]|\[~.*?~\]|\[\(.*?\)\]|\{\{.*?\}\}|\[!.*?!\]/su', ' ', $text) ?? '';
                        $text = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b.*?<\/\1>/si', ' ', $text) ?? ''), ENT_QUOTES, $charset);
                        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
                    }
                    if ($text === '') {
                        return '';
                    }
                    $len = 140;
                    if ($fromContent && mb_strlen($text, $charset) > $len) {
                        $at = false;
                        foreach ($terms as $t) {
                            $at = mb_stripos($text, $t, 0, $charset);
                            if ($at !== false) {
                                break;
                            }
                        }
                        // 語の 40 文字前から切り出す。本文の終わりに近いときは、終わりで止めて前に広げる
                        $start = $at === false ? 0 : max(0, min($at - 40, mb_strlen($text, $charset) - $len));
                        $text = ($start > 0 ? '…' : '') . mb_substr($text, $start, $len, $charset)
                            . ($start + $len < mb_strlen($text, $charset) ? '…' : '');
                    } elseif (mb_strlen($text, $charset) > $len * 1.5) {
                        $text = mb_substr($text, 0, (int) ($len * 1.5), $charset) . '…';
                    }
                    return $text;
                };
                // 探した語を太字にする。元の文字列を語で分け、片ごとにエスケープしてから、当たった片だけ <b> で包む
                // （エスケープ後の文字列に当てると、&amp; などの実体参照の中に当たって壊す）
                $highlight = function (string $text) use ($terms) {
                    if (!$terms) {
                        return hsc($text);
                    }
                    $pattern = '/(' . implode('|', array_map(static function ($t) {
                        return preg_quote($t, '/');
                    }, $terms)) . ')/iu';
                    $pieces = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                    if ($pieces === false) {
                        return hsc($text);
                    }
                    $html = '';
                    foreach ($pieces as $n => $piece) {
                        $html .= $n % 2 === 1 ? '<b>' . hsc($piece) . '</b>' : hsc($piece);
                    }
                    return $html;
                };

                $table = new DataTable(
                    [
                        [
                            'key' => 'id',
                            'label' => lang('search_results_returned_id'),
                            'sort' => 'id',
                            'class' => 'is-shrink',
                            'html' => true,
                            'format' => function ($value, array $row) {
                                return (string) (int) $row['id'];
                            },
                        ],
                        [
                            // Google の検索結果のように、タイトル（押すと編集画面）・ページの URL・説明か本文の抜粋を 1 つの欄に
                            'key' => 'pagetitle',
                            'label' => lang('search_results_returned_title'),
                            'sort' => 'pagetitle',
                            'html' => true,
                            'format' => function ($value, array $row) use ($action, $wrapNode, $snippet, $highlight) {
                                $id = (int) $row['id'];
                                $title = sprintf(
                                    '<a class="search-hit-title" href="index.php?a=%s&amp;id=%d"><img src="%s" alt="" /> %s</a>',
                                    $action,
                                    $id,
                                    hsc($row['icon']),
                                    $highlight((string) $value)
                                );
                                if ($row['isfolder'] == 1) {
                                    $title .= sprintf(
                                        ' <a class="search-hit-sub" href="index.php?a=120&amp;id=%d">%s</a>',
                                        $id,
                                        hsc(lang('search_view_children'))
                                    );
                                }
                                $url = $row['type'] === 'reference' ? '' : (string) evo()->makeUrl($id, '', '', 'full');
                                $meta = '';
                                if ($url !== '') {
                                    $meta = sprintf(
                                        '<div class="search-hit-url"><span>%s</span> <a href="%s" target="previeWin" title="%s"><img src="%s" alt="%s" /></a></div>',
                                        hsc(preg_replace('@^https?://@', '', $url)),
                                        hsc($url),
                                        hsc(lang('search_view_page')),
                                        hsc(style('icons_preview_resource')),
                                        hsc(lang('search_view_page'))
                                    );
                                }
                                $text = $snippet($row);
                                $body = $text !== '' ? '<div class="search-hit-snippet">' . $highlight($text) . '</div>' : '';
                                return '<div class="search-hit">' . $wrapNode($title, $row) . $meta . $body . '</div>';
                            },
                        ],
                    ],
                    ['id' => 'search-results', 'rowClasses' => ['gridItem', 'gridAltItem']]
                );
                $nav = $pager->render($links, [
                    'label' => lang('pagination_label'),
                    'first' => lang('pagination_table_first'),
                    'last' => lang('pagination_table_last'),
                ]);
                // 子リソースの一覧（resources_list.static.php）と共通の .list-toolbar・.list-pager-bottom（shell.css）で、
                // 上の行は件数を左・ページ送りを右に 1 行で、下のページ送りは右に寄せる。狭いときはページ送りが下に回り込む
                echo '<div class="list-toolbar"><span class="search-results-count">' . $count . '</span>' . $nav . '</div>'
                    . $table->render($rows, $links)
                    . ($nav !== '' ? '<div class="list-pager-bottom">' . $nav . '</div>' : '');
            }
            ?>
        </div>
    </div>
    <?php
}
