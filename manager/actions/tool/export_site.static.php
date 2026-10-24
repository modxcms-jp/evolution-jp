<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('export_static')) {
    alert()->setError(3);
    alert()->dumpError();
}

// figure out the base of the server, so we know where to get the documents in order to export them
?>

<h1><?= lang('export_site_html') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li
            class="mutate"
            id="Button5"
        ><a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?a=2';"
            ><?= lang('cancel') ?></a>
        </li>
    </ul>
</div>
<div class="sectionBody">
    <div class="tab-pane" id="exportPane">
        <div class="tab-page" id="tabMain">
            <h2 class="tab"><?= lang('export_site') ?></h2>
            <?php
            if (postv('export')) {
                $rs = include(MODX_MANAGER_PATH . 'processors/export_site.processor.php');
                echo $rs;
            } else {
                ?>
                <form action="index.php" method="post" name="exportFrm">
                    <input type="hidden" name="export" value="export"/>
                    <input type="hidden" name="a" value="83"/>
                    <style type="text/css">
                        table.settings {
                            width: 100%;
                        }

                        table.settings td.head {
                            white-space: nowrap;
                            vertical-align: top;
                            padding-right: 20px;
                            font-weight: bold;
                        }
                    </style>
                    <table class="settings" cellspacing="0" cellpadding="2">
                        <tr>
                            <td class="head">出力するリソース</td>
                            <?php $checked = ['all' => '', 'allow_ids' => '', 'ignore_ids' => '']; ?>
                            <?php $checked[sessionv('export_target', 'all')] = 'checked'; ?>
                            <td>
                                <label>
                                    <input
                                        name="target"
                                        type="radio"
                                        value="all"
                                        <?= $checked['all'] ?>
                                    >全リソース
                                </label>
                                <label>
                                    <input
                                        name="target"
                                        type="radio"
                                        value="allow_ids"
                                        <?= $checked['allow_ids'] ?>
                                    >一部のリソースを出力
                                </label>
                                <label>
                                    <input
                                        name="target"
                                        type="radio"
                                        value="ignore_ids"
                                        <?= $checked['ignore_ids'] ?>
                                    >一部のリソースを除外
                                </label>
                                <?php $display = ['allow_ids' => 'none', 'ignore_ids' => 'none']; ?>
                                <?php $display[sessionv('export_target', 'all')] = 'block'; ?>
                                <div
                                    id="allow_ids"
                                    style="display:<?= $display['allow_ids'] ?>"
                                >
                                    <input
                                        type="text"
                                        name="allow_ids"
                                        value="<?= sessionv('export_allow_ids', '') ?>"
                                        style="width:300px;background-color:#f2fff2;"
                                    /><br/>
                                    出力するリソースのIDを指定(カンマ区切りで複数指定可)
                                </div>
                                <div
                                    id="ignore_ids"
                                    style="display:<?= $display['ignore_ids'] ?>"
                                >
                                    <input
                                        type="text"
                                        name="ignore_ids"
                                        value="<?= sessionv('export_ignore_ids') ?>"
                                        style="width:300px;background-color:#fff2f2;"
                                    /><br/>
                                    出力しないリソースのIDを指定(カンマ区切りで複数指定可)
                                </div>
                            </td>
                        </tr>
                        <?php
                        $checked = [0 => '', 1 => ''];
                        if (sessionv('export_includenoncache')) {
                            $checked[1] = 'checked';
                        } else {
                            $checked[0] = 'checked';
                        }
                        ?>
                        <tr>
                            <td class="head"><?= lang('export_site_cacheable') ?></td>
                            <td>
                                <label>
                                    <input
                                        type="radio"
                                        name="includenoncache"
                                        value="1"
                                        <?= $checked[1] ?>
                                    ><?= lang('yes') ?>
                                </label>
                                <label>
                                    <input
                                        type="radio"
                                        name="includenoncache"
                                        value="0"
                                        <?= $checked[0] ?>
                                    ><?= lang('no') ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="head"><?= lang('export_site_replace_before') ?></td>
                            <td>
                                <input
                                    type="text"
                                    name="repl_before"
                                    value="<?= sessionv('export_repl_before', MODX_SITE_URL) ?>"
                                    style="width:300px;"/>
                            </td>
                        </tr>
                        <tr>
                            <td class="head"><?= lang('export_site_replace_after') ?></td>
                            <td>
                                <input
                                    type="text"
                                    name="repl_after"
                                    value="<?= sessionv('export_repl_after', MODX_SITE_URL) ?>"
                                    style="width:300px;"
                                />
                            </td>
                        </tr>
                        <tr>
                            <td class="head"><?= lang('export_site_maxtime') ?></td>
                            <td><input type="text" name="maxtime" value="60"/>
                                <br/>
                                <?= lang('export_site_maxtime_message') ?>
                            </td>
                        </tr>
                    </table>

                    <ul class="actionButtons">
                        <li>
                            <a
                                href="#"
                                class="default"
                                onclick="document.exportFrm.submit();"
                            ><?= lang('export_site_start') ?></a>
                        </li>
                    </ul>
                </form>

                <?php
            }
            ?>


        </div>
        <div class="tab-page" id="tabHelp">
            <h2 class="tab"><?= lang('help') ?></h2>
            <?php
            echo '<p>' . evo()->parseText(
                lang('export_site_message'),
                ['export_dir' => hsc(str_replace('[(base_path)]', '', config('export_dir')))]
            ) . '</p>';
            ?>
        </div>
    </div>
</div>

<script>
    tpExport = new WebFXTabPane(document.getElementById("exportPane"));
    (function () {
        // 出力する範囲に合わせて ID の入力欄を出し入れする
        const show = function (id, visible) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = visible ? 'block' : 'none';
            }
        };
        document.querySelectorAll('input[type="radio"][name="target"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const checked = document.querySelector('input[name="target"]:checked');
                switch (checked ? checked.value : '') {
                    case 'all'       :
                        show('ignore_ids', false);
                        show('allow_ids', false);
                        break;
                    case 'allow_ids' :
                        show('ignore_ids', false);
                        show('allow_ids', true);
                        break;
                    case 'ignore_ids':
                        show('allow_ids', false);
                        show('ignore_ids', true);
                        break;
                }
            });
        });
    })();
</script>
