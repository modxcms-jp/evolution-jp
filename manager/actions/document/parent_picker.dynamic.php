<?php
/**
 * 親リソースの選択ダイアログ（a=135）。
 *
 * リソースツリーは子の数が limit_by_container を超えると子を省略するため、
 * ツリーのクリックだけでは省略されたリソースを親に指定できない。
 * タイトルか ID で検索して選べるようにする。リソースの編集画面と移動画面から
 * EvoShell.openPicker() でモーダルに開き、選んだリソースを EvoShell.pick() で返す。
 *
 * - id: 親を変えるリソース（移動画面の一括移動ではカンマ区切り）。自分自身と子孫は選べないよう示す
 * - ajax=search&q=...: 検索結果を JSON で返す
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

const PARENT_PICKER_LIMIT = 50;

$docIds = [];
foreach (explode(',', (string)anyv('id', '')) as $v) {
    $v = to_intvar(trim($v));
    if ($v) {
        $docIds[] = $v;
    }
}

if (getv('ajax') === 'search') {
    header(sprintf('Content-Type: application/json; charset=%s', config('modx_charset', 'utf-8')));
    echo json_encode(
        parent_picker_search(trim((string)getv('q', '')), $docIds),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$searchUrl = 'index.php?' . http_build_query(['a' => 135, 'ajax' => 'search', 'id' => implode(',', $docIds)]);
?>
<h1><?= hsc(lang('parent_picker_title')) ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <?php if (evo()->checkPermissions(0)): ?>
            <li><a href="#" id="parentPickerRoot"><?= hsc(lang('parent_picker_root')) ?></a></li>
        <?php endif; ?>
        <li class="mutate"><a href="#" onclick="EvoShell.closeModal({reloadMain: false}); return false;"><?= hsc(lang('cancel')) ?></a></li>
    </ul>
</div>

<div class="sectionBody evo-parent-picker">
    <input type="search" id="parentPickerQuery" class="inputBox" autocomplete="off"
           placeholder="<?= hsc(lang('parent_picker_placeholder')) ?>"/>
    <p id="parentPickerStatus"><?= hsc(lang('parent_picker_hint')) ?></p>
    <ul id="parentPickerResults"></ul>
</div>

<style>
    .evo-parent-picker #parentPickerQuery {
        width: 100%;
        box-sizing: border-box;
    }

    .evo-parent-picker #parentPickerResults {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .evo-parent-picker #parentPickerResults li {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .evo-parent-picker #parentPickerResults button {
        display: block;
        width: 100%;
        padding: 0.5em 0.75em;
        border: 0;
        border-bottom: 1px solid var(--gray-200, #e5e5e5);
        background: none;
        text-align: left;
        font: inherit;
        color: inherit;
        cursor: pointer;
    }

    .evo-parent-picker #parentPickerResults button:hover:not(:disabled),
    .evo-parent-picker #parentPickerResults button:focus-visible {
        background: var(--gray-100, #f3f3f3);
    }

    .evo-parent-picker #parentPickerResults button:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    .evo-parent-picker .parent-picker-path,
    .evo-parent-picker .parent-picker-note {
        display: block;
        font-size: 0.85em;
        color: var(--gray-600, #666);
    }

    .evo-parent-picker .parent-picker-deleted {
        text-decoration: line-through;
    }
</style>

<script>
    (function () {
        const searchUrl = <?= js_literal($searchUrl) ?>;
        const text = {
            hint: <?= js_literal(lang('parent_picker_hint')) ?>,
            noResults: <?= js_literal(lang('no_results')) ?>,
            more: <?= js_literal(sprintf(lang('parent_picker_more'), PARENT_PICKER_LIMIT)) ?>,
            descendant: <?= js_literal(lang('parent_picker_descendant')) ?>,
            root: <?= js_literal(config('site_name')) ?>
        };
        const input = document.getElementById('parentPickerQuery');
        const status = document.getElementById('parentPickerStatus');
        const list = document.getElementById('parentPickerResults');
        const rootLink = document.getElementById('parentPickerRoot');
        let timer = null;
        let controller = null;

        function render(data) {
            list.textContent = '';
            data.items.forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.disabled = item.disabled;
                const title = document.createElement('span');
                title.textContent = item.id + ' (' + item.pagetitle + ')';
                if (item.deleted) {
                    title.className = 'parent-picker-deleted';
                }
                button.appendChild(title);
                if (item.path.length) {
                    const path = document.createElement('span');
                    path.className = 'parent-picker-path';
                    path.textContent = item.path.join(' / ');
                    button.appendChild(path);
                }
                if (item.disabled) {
                    const note = document.createElement('span');
                    note.className = 'parent-picker-note';
                    note.textContent = text.descendant;
                    button.appendChild(note);
                } else {
                    button.addEventListener('click', function () {
                        EvoShell.pick({ id: item.id, pagetitle: item.pagetitle });
                    });
                }
                const li = document.createElement('li');
                li.appendChild(button);
                list.appendChild(li);
            });
            if (!data.items.length) {
                status.textContent = text.noResults;
            } else {
                status.textContent = data.more ? text.more : '';
            }
        }

        function search() {
            const q = input.value.trim();
            if (controller) {
                controller.abort();
            }
            if (q === '') {
                list.textContent = '';
                status.textContent = text.hint;
                return;
            }
            controller = new AbortController();
            fetch(searchUrl + '&q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
                signal: controller.signal
            })
                .then(function (response) {
                    return response.json();
                })
                .then(render)
                .catch(function (e) {
                    if (e.name !== 'AbortError') {
                        status.textContent = text.noResults;
                    }
                });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 250);
        });
        // IME 変換の確定前に Enter で送信されないよう、Enter では検索だけを即時に行う
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.isComposing) {
                e.preventDefault();
                clearTimeout(timer);
                search();
            }
        });
        if (rootLink) {
            rootLink.addEventListener('click', function (e) {
                e.preventDefault();
                EvoShell.pick({ id: 0, pagetitle: text.root });
            });
        }
        input.focus();
    })();
</script>
<?php

/**
 * タイトル・メニュータイトル・エイリアスの部分一致か ID の完全一致で、親の候補を探す。
 * ツリーと同じく、管理画面で非公開（privatemgr）のリソースは所属する文書グループのものだけを返す。
 * allowed_parents の範囲外も返さない
 *
 * @param string $q
 * @param int[] $docIds 親を変えるリソース。自分自身と子孫は選べない印を付ける
 * @return array{items: array, more: bool}
 */
function parent_picker_search($q, array $docIds)
{
    if ($q === '') {
        return ['items' => [], 'more' => false];
    }

    $like = db()->escape(addcslashes($q, '%_\\'));
    $match = [
        "sc.pagetitle LIKE '%{$like}%'",
        "sc.menutitle LIKE '%{$like}%'",
        "sc.alias LIKE '%{$like}%'",
    ];
    $idOrder = '';
    if (preg_match('@^[1-9][0-9]*$@', $q)) {
        $match[] = sprintf('sc.id=%d', $q);
        $idOrder = sprintf('sc.id=%d DESC, ', $q);
    }
    $where = '(' . implode(' OR ', $match) . ')' . parent_picker_access_where();
    // 件数で打ち切る前に絞らないと、範囲内の候補が範囲外の候補に押し出されて出てこない
    if (evo()->conf_var('allowed_parents')) {
        $allowed = array_filter(array_map('intval', (array)manager()->getUserAllowedDocs()));
        $where .= $allowed ? sprintf(' AND sc.id IN (%s)', implode(',', $allowed)) : ' AND 0';
    }

    $rs = db()->select(
        'sc.id, sc.pagetitle, sc.deleted',
        '[+prefix+]site_content AS sc',
        $where,
        "{$idOrder}sc.isfolder DESC, sc.id",
        PARENT_PICKER_LIMIT + 1
    );

    $items = [];
    $more = false;
    while ($row = db()->getRow($rs)) {
        if (count($items) === PARENT_PICKER_LIMIT) {
            $more = true;
            break;
        }
        $ancestors = manager()->getAncestorIds($row['id']);
        $items[] = [
            'id' => (int)$row['id'],
            'pagetitle' => $row['pagetitle'],
            'deleted' => (bool)$row['deleted'],
            'path' => parent_picker_titles(array_reverse($ancestors)),
            'disabled' => in_array((int)$row['id'], $docIds, true) || (bool)array_intersect($docIds, $ancestors),
        ];
    }
    return ['items' => $items, 'more' => $more];
}

/**
 * 管理画面で非公開（privatemgr）のリソースを、所属する文書グループのものだけに絞る条件（' AND ...'）
 *
 * @return string
 */
function parent_picker_access_where()
{
    if (manager()->isAdmin()) {
        return '';
    }
    $docgroups = array_filter(array_map('intval', (array)sessionv('mgrDocgroups', [])));
    if (!$docgroups) {
        return ' AND sc.privatemgr=0';
    }
    return sprintf(
        ' AND (sc.privatemgr=0 OR sc.id IN (SELECT document FROM %s WHERE document_group IN (%s)))',
        db()->getFullTableName('document_groups'),
        implode(',', $docgroups)
    );
}

/**
 * ID の並びをタイトルの並びにする（同じ名前のリソースを見分けるための階層表示）。
 * 見る権限のない祖先は、ツリーと同じくタイトルを出さずに伏せる
 *
 * @param int[] $ids
 * @return string[]
 */
function parent_picker_titles(array $ids)
{
    static $titles = [];
    $missing = array_diff($ids, array_keys($titles));
    if ($missing) {
        $missing = array_map('intval', $missing);
        foreach ($missing as $id) {
            $titles[$id] = '…';
        }
        $rs = db()->select(
            'sc.id, sc.pagetitle',
            '[+prefix+]site_content AS sc',
            sprintf('sc.id IN (%s)', implode(',', $missing)) . parent_picker_access_where()
        );
        while ($row = db()->getRow($rs)) {
            $titles[(int)$row['id']] = $row['pagetitle'];
        }
    }
    $path = [];
    foreach ($ids as $id) {
        $path[] = $titles[$id];
    }
    return $path;
}
