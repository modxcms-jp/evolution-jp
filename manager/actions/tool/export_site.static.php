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

<h1><?php echo lang('export_site_html'); ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li
            class="mutate"
            id="Button5"
        ><a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?a=2';"
            ><img
                    alt="icons_cancel"
                    src="<?php echo style('icons_cancel') ?>"
                /> <?php echo lang('cancel') ?></a>
        </li>
    </ul>
</div>
<div class="sectionBody">
    <div class="tab-pane" id="exportPane">
        <div class="tab-page" id="tabMain">
            <h2 class="tab"><?php echo lang('export_site') ?></h2>
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
                        <?php
                        $generate_mode = sessionv('export_generate_mode', 'crawl');
                        ?>
                        <tr>
                            <td class="head"><?php echo lang('a83_mode_title'); ?></td>
                            <td>
                                <label>
                                    <input
                                        name="generate_mode"
                                        type="radio"
                                        value="crawl"
                                        <?php echo $generate_mode === 'crawl' ? 'checked' : ''; ?>
                                    ><?php echo lang('a83_mode_crawl'); ?>
                                </label>
                                <label>
                                    <input
                                        name="generate_mode"
                                        type="radio"
                                        value="direct"
                                        <?php echo $generate_mode === 'direct' ? 'checked' : ''; ?>
                                    ><?php echo lang('a83_mode_direct'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="head">出力するリソース</td>
                            <?php $checked = array('all' => '', 'allow_ids' => '', 'ignore_ids' => ''); ?>
                            <?php $checked[sessionv('export_target', 'all')] = 'checked'; ?>
                            <td>
                                <label>
                                    <input
                                        name="target"
                                        type="radio"
                                        value="all"
                                        <?php echo $checked['all']; ?>
                                    >全リソース
                                </label>
                                <label>
                                    <input
                                        name="target"
                                        type="radio"
                                        value="allow_ids"
                                        <?php echo $checked['allow_ids']; ?>
                                    >一部のリソースを出力
                                </label>
                                <label>
                                    <input
                                        name="target"
                                        type="radio"
                                        value="ignore_ids"
                                        <?php echo $checked['ignore_ids']; ?>
                                    >一部のリソースを除外
                                </label>
                                <?php $display = array('allow_ids' => 'none', 'ignore_ids' => 'none'); ?>
                                <?php $display[sessionv('export_target', 'all')] = 'block'; ?>
                                <div
                                    id="allow_ids"
                                    style="display:<?php echo $display['allow_ids']; ?>"
                                >
                                    <input
                                        type="text"
                                        name="allow_ids"
                                        value="<?php echo sessionv('export_allow_ids', ''); ?>"
                                        style="width:300px;background-color:#f2fff2;"
                                    /><br/>
                                    出力するリソースのIDを指定(カンマ区切りで複数指定可)
                                </div>
                                <div
                                    id="ignore_ids"
                                    style="display:<?php echo $display['ignore_ids']; ?>"
                                >
                                    <input
                                        type="text"
                                        name="ignore_ids"
                                        value="<?php echo sessionv('export_ignore_ids'); ?>"
                                        style="width:300px;background-color:#fff2f2;"
                                    /><br/>
                                    出力しないリソースのIDを指定(カンマ区切りで複数指定可)
                                </div>
                            </td>
                        </tr>
                        <?php
                        $checked = array(0 => '', 1 => '');
                        if (sessionv('export_includenoncache')) {
                            $checked[1] = 'checked';
                        } else {
                            $checked[0] = 'checked';
                        }
                        ?>
                        <tr>
                            <td class="head"><?php echo lang('export_site_cacheable'); ?></td>
                            <td>
                                <label>
                                    <input
                                        type="radio"
                                        name="includenoncache"
                                        value="1"
                                        <?php echo $checked[1]; ?>
                                    ><?php echo lang('yes'); ?>
                                </label>
                                <label>
                                    <input
                                        type="radio"
                                        name="includenoncache"
                                        value="0"
                                        <?php echo $checked[0]; ?>
                                    ><?php echo lang('no'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="head"><?php echo lang('export_site.static.php4'); ?></td>
                            <td>
                                <input
                                    type="text"
                                    name="repl_before"
                                    value="<?php echo sessionv('export_repl_before', MODX_SITE_URL); ?>"
                                    style="width:300px;"/>
                            </td>
                        </tr>
                        <tr>
                            <td class="head"><?php echo lang('export_site.static.php5'); ?></td>
                            <td>
                                <input
                                    type="text"
                                    name="repl_after"
                                    value="<?php echo sessionv('export_repl_after', MODX_SITE_URL); ?>"
                                    style="width:300px;"
                                />
                            </td>
                        </tr>
                        <tr>
                            <td class="head"><?php echo lang('export_site_maxtime'); ?></td>
                            <td><input type="text" name="maxtime" value="60"/>
                                <br/>
                                <?php echo lang('export_site_maxtime_message'); ?>
                            </td>
                        </tr>
                    </table>

                    <ul class="actionButtons">
                        <li>
                            <a
                                href="#"
                                class="default"
                                onclick="document.exportFrm.submit();"
                            ><img
                                    src="<?php echo style('icons_save') ?>"
                                /> <?php echo lang('export_site_start'); ?></a>
                        </li>
                    </ul>
                </form>

                <?php
            }
            ?>


        </div>
        <div class="tab-page" id="tabHelp">
            <h2 class="tab"><?php echo lang('help') ?></h2>
            <?php
            echo '<p>' . lang('export_site_message') . '</p>';
            ?>
        </div>
    </div>
</div>

<script>
    tpExport = new WebFXTabPane(document.getElementById("exportPane"));
    jQuery('input[name="target"]:radio').change(function () {
        switch (jQuery('input[name="target"]:checked').val()) {
            case 'all'       :
                jQuery('#ignore_ids').fadeOut('fast');
                jQuery('#allow_ids').fadeOut('fast');
                break;
            case 'allow_ids' :
                jQuery('#ignore_ids').hide();
                jQuery('#allow_ids').fadeIn('normal');
                break;
            case 'ignore_ids':
                jQuery('#allow_ids').hide();
                jQuery('#ignore_ids').fadeIn('normal');
                break;
        }
    });
</script>
