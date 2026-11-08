<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('view_schedule')) {
    alert()->setError(3);
    alert()->dumpError();
}
include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
?>
<h1><?= lang('site_schedule') ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li
            id="Button5"
            class="mutate">
            <a
                href="#"
                onclick="EvoShell.go('index.php?a=2', {discard: true});"
            >
                <?= lang('cancel') ?>
            </a>
        </li>
    </ul>
</div>

<div class="section">
    <div class="sectionHeader"><?= lang("publish_events") ?></div>
    <div class="sectionBody" id="lyr1">
        <?php
        $rs = db()->select(
            'id, pagetitle, pub_date',
            '[+prefix+]site_content',
            'pub_date > ' . request_time(),
            'pub_date ASC'
        );
        $rows = [];
        while ($row = db()->getRow($rs)) {
            $rows[] = $row;
        }
        if (!$rows) {
            echo "<p>" . lang("no_docs_pending_publishing") . "</p>";
        } else {
            $table = new DataTable(
                [
                    ['key' => 'id', 'label' => lang('id')],
                    [
                        'key' => 'pagetitle',
                        'label' => lang('resource'),
                        'html' => true,
                        'format' => function ($value, array $row) {
                            return sprintf('<a href="index.php?a=3&amp;id=%d">%s</a>', (int)$row['id'], hsc($value));
                        },
                    ],
                    [
                        'key' => 'pub_date',
                        'label' => lang('publish_date'),
                        'format' => function ($value) {
                            return evo()->toDateFormat($value + config('server_offset_time', 0));
                        },
                    ],
                ],
                ['id' => 'schedule-publish', 'rowClasses' => ['gridItem', 'gridAltItem']]
            );
            echo $table->render($rows);
        }
        ?>
    </div>
</div>

<div class="section">
    <div class="sectionHeader"><?= lang("unpublish_events") ?></div>
    <div class="sectionBody" id="lyr2">
        <?php
        $rs = db()->select(
            'id, pagetitle, unpub_date',
            '[+prefix+]site_content',
            'unpub_date > ' . request_time(),
            'unpub_date ASC'
        );
        $rows = [];
        while ($row = db()->getRow($rs)) {
            $rows[] = $row;
        }
        if (!$rows) {
            echo "<p>" . lang("no_docs_pending_unpublishing") . "</p>";
        } else {
            $table = new DataTable(
                [
                    ['key' => 'id', 'label' => lang('id')],
                    [
                        'key' => 'pagetitle',
                        'label' => lang('resource'),
                        'html' => true,
                        'format' => function ($value, array $row) {
                            return sprintf('<a href="index.php?a=3&amp;id=%d">%s</a>', (int)$row['id'], hsc($value));
                        },
                    ],
                    [
                        'key' => 'unpub_date',
                        'label' => lang('unpublish_date'),
                        'format' => function ($value) {
                            return evo()->toDateFormat($value + config('server_offset_time', 0));
                        },
                    ],
                ],
                ['id' => 'schedule-unpublish', 'rowClasses' => ['gridItem', 'gridAltItem']]
            );
            echo $table->render($rows);
        }
        ?>
    </div>
</div>

<div class="section">
    <div class="sectionHeader">更新を予定している下書きリソースの一覧</div>
    <div class="sectionBody" id="lyr2">
        <?php
        $rs = db()->select(
            'rv.*, sc.*, rv.pub_date AS pub_date',
            [
                '[+prefix+]site_revision rv',
                'INNER JOIN [+prefix+]site_content sc ON rv.elmid=sc.id'
            ],
            "0<rv.pub_date AND rv.status='standby' ",
            'rv.pub_date ASC'
        );
        $rows = [];
        while ($row = db()->getRow($rs)) {
            $rows[] = $row;
        }
        if (!$rows) {
            echo "<p>更新を予定している下書きリソースはありません。</p>";
        } else {
            $table = new DataTable(
                [
                    ['key' => 'elmid', 'label' => lang('id')],
                    [
                        'key' => 'pagetitle',
                        'label' => lang('resource'),
                        'html' => true,
                        'format' => function ($value, array $row) {
                            return sprintf('<a href="index.php?a=131&amp;id=%d">%s</a>', (int)$row['elmid'], hsc($value));
                        },
                    ],
                    [
                        'key' => 'pub_date',
                        'label' => '更新予約日時',
                        'format' => function ($value) {
                            return evo()->toDateFormat($value + config('server_offset_time', 0));
                        },
                    ],
                    [
                        'key' => 'version',
                        'label' => '操作',
                        'html' => true,
                        'format' => function ($value, array $row) {
                            return sprintf(
                                '<a href="%s" target="_blank">プレビュー</a> / '
                                . '<a href="%s" data-evo-post class="unpub_draft">公開取り消し</a>',
                                hsc(sprintf('%s?revision=%s', evo()->makeUrl($row['elmid']), $row['version'])),
                                hsc('index.php?a=134&id=' . (int)$row['elmid'] . '&back=publist')
                            );
                        },
                    ],
                ],
                ['id' => 'schedule-draft', 'rowClasses' => ['gridItem', 'gridAltItem']]
            );
            echo $table->render($rows);
            ?>
            <script type="text/javascript">
                document.querySelectorAll('.unpub_draft').forEach(function (link) {
                    link.addEventListener('click', function (e) {
                        // 取り消したときは data-evo-post の POST も止める
                        if (!confirm('公開設定を取り消してもよろしいですか？')) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    });
                });
            </script>
            <?php
        }
        ?>
    </div>
</div>
