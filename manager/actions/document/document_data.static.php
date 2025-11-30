<?php
/**
 * @var array $_lang
 * @var  array $_style
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('view_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (!preg_match('@^[0-9]+$@', anyv('id'))) {
    alert()->setError(1);
    alert()->dumpError();
}

$icons_path = manager_style_image_path('icons');

$id = anyv('id');
if (!manager()->isAllowed($id)) {
    alert()->setError(3);
    alert()->dumpError();
}

evo()->updatePublishStatus();

// Get the document content
$where = [
    sprintf("sc.id ='%s'", $id)
];
if (sessionv('mgrDocgroups') && !manager()->isAdmin()) {
    $where[] = sprintf(
        "AND (sc.privatemgr=0 OR dg.document_group IN (%s))",
        implode(',', sessionv('mgrDocgroups'))
    );
}
$rs = db()->select(
    'DISTINCT sc.*',
    [
        '[+prefix+]site_content AS sc',
        'LEFT JOIN [+prefix+]document_groups AS dg ON dg.document=sc.id'
    ],
    $where
);
$total = db()->count($rs);
if ($total > 1) {
    echo "<p>Internal System Error...</p>"
        . "<p>More results returned than expected. </p>"
        . "<p><strong>Aborting...</strong></p>";
    exit;
}

if ($total == 0) {
    alert()->setError(3);
    alert()->dumpError();
}

$content = db()->getRow($rs);
/**
 * "General" tab setup
 */
$rs = db()->select(
    'username',
    '[+prefix+]manager_users',
    sprintf("id='%s'", $content['createdby'])
);
if ($row = db()->getRow($rs)) {
    $createdbyname = $row['username'];
}

// Get Editor's username
$rs = db()->select(
    'username',
    '[+prefix+]manager_users',
    sprintf("id='%s'", $content['editedby'])
);
if ($row = db()->getRow($rs)) {
    $editedbyname = $row['username'];
}

// Get Template name
$rs = db()->select(
    'templatename',
    '[+prefix+]site_templates',
    sprintf("id='%s'", entity('template'))
);
if ($row = db()->getRow($rs)) {
    $templatename = $row['templatename'];
} else {
    $templatename = 'blank';
}

// Set the item name for logging
$_SESSION['itemname'] = $content['pagetitle'];

foreach ($content as $k => $v) {
    $content[$k] = hsc($v);
}

function entity($key, $default = null)
{
    global $content;
    return $content[$key] ?? $default;
}

?>
<script type="text/javascript">
    function duplicatedocument() {
        if (confirm("<?= $_lang['confirm_resource_duplicate'] ?>")) {
            document.location.href = "index.php?id=<?= $id ?>&a=94";
        }
    }

    function deletedocument() {
        if (confirm("<?= $_lang['confirm_delete_resource'] ?>")) {
            document.location.href = "index.php?id=<?= $id ?>&a=6";
        }
    }

    function editdocument() {
        document.location.href = "index.php?id=<?= $id ?>&a=27";
    }

    function movedocument() {
        document.location.href = "index.php?id=<?= $id ?>&a=51";
    }
</script>
<h1><?= $_lang['doc_data_title'] ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <?php if (evo()->hasPermission('save_document')) : ?>
            <li id="Button1" class="mutate">
                <a href="javascript:void(0)" onclick="editdocument();">
                    <img src="<?= $_style["icons_edit_document"] ?>"/>
                    <?= $_lang['edit'] ?>
                </a>
            </li>
        <?php endif; ?>
        <?php if (evo()->hasPermission('save_document') && evo()->hasPermission('move_document')) : ?>
            <li id="Button2" class="mutate">
                <a href="#" onclick="movedocument();">
                    <img src="<?= $_style["icons_move_document"] ?>"/>
                    <?= $_lang['move'] ?>
                </a>
            </li>
        <?php endif; ?>
        <?php if (evo()->hasPermission('new_document') && evo()->hasPermission('save_document')) : ?>
            <li id="Button4">
                <a href="#" onclick="duplicatedocument();">
                    <img src="<?= $_style["icons_resource_duplicate"] ?>"/>
                    <?= $_lang['duplicate'] ?>
                </a>
            </li>
        <?php endif; ?>
        <?php if (evo()->hasPermission('delete_document') && evo()->hasPermission('save_document')) : ?>
            <li id="Button3">
                <a href="#" onclick="deletedocument();">
                    <img src="<?= $_style["icons_delete_document"] ?>"/>
                    <?= $_lang['delete'] ?>
                </a>
            </li>
        <?php endif; ?>
        <li id="Button6">
            <a
                href="#"
                onclick="<?=
                (evo()->config('friendly_urls') == 1)
                    ? sprintf("window.open('%s','previeWin')", evo()->makeUrl($id))
                    : sprintf("window.open('../index.php?id=%s','previeWin')", $id);
                ?>"
            >
                <img src="<?= $_style["icons_preview_resource"] ?>"/>
                <?= $_lang['view_resource'] ?>
            </a>
        </li>
        <li id="Button5" class="mutate"><a
                href="#"
                onclick="
                documentDirty=false;
                <?php
                if (isset($content['parent']) && $content['parent'] != 0) {
                    $tpl = 'document.location.href=\'index.php?a=120&id=%s\';';
                    echo sprintf($tpl, entity('parent'));
                } elseif (getv('pid')) {
                    $tpl = 'document.location.href=\'index.php?a=120&id=%d\';';
                    echo sprintf($tpl, (int)getv('pid'));
                } else {
                    echo 'document.location.href=\'index.php?a=2\';';
                }
                ?>"
            ><img
                    alt="icons_cancel"
                    src="<?= $_style["icons_cancel"] ?>"
                /> <?= $_lang['cancel'] ?></a>
        </li>
    </ul>
</div>

<div class="sectionBody">
    <div class="tab-pane" id="docInfo">

        <style type="text/css">
            h3 {
                font-size: 1em;
                padding-bottom: 0;
                margin-bottom: 0;
            }
        </style>
        <!-- General -->
        <div class="tab-page" id="tabDocInfo">
            <h2 class="tab"><?= $_lang['information'] ?></h2>
            <div class="sectionBody">
                <table>
                    <tr>
                        <td width="200">ID:</td>
                        <td><?= entity('id') ?></td>
                        <td>[*id*]</td>
                    </tr>
                    <tr>
                        <td width="200"><?= $_lang['page_data_template'] ?>:</td>
                        <td><?php
                            echo sprintf(
                                '%s(id:%s)',
                                $templatename,
                                entity('template')
                            );
                            ?>
                        </td>
                        <td>[*template*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['resource_title'] ?>:</td>
                        <td><?= entity('pagetitle') ?></td>
                        <td>[*pagetitle*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['long_title'] ?>:</td>
                        <td><?php
                            if (entity('longtitle') != '') {
                                echo entity('longtitle');
                            } else {
                                echo "(<i>" . $_lang['not_set'] . "</i>)";
                            } ?>
                        </td>
                        <td>[*longtitle*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['resource_description'] ?>:</td>
                        <td><?=
                            entity('description') != ''
                                ? entity('description')
                                : "(<i>" . $_lang['not_set'] . "</i>)"
                            ?></td>
                        <td>[*description*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['resource_summary'] ?>:</td>
                        <td><?=
                            entity('introtext') != ''
                                ? entity('introtext')
                                : "(<i>" . $_lang['not_set'] . "</i>)"
                            ?></td>
                        <td>[*introtext*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['type'] ?>:</td>
                        <td><?=
                            entity('type') === 'reference'
                                ? $_lang['weblink']
                                : $_lang['resource']
                            ?></td>
                        <td>[*type*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['resource_alias'] ?>:</td>
                        <td><?=
                            entity('alias') != ''
                                ? entity('alias')
                                : "(<i>" . $_lang['not_set'] . "</i>)"
                            ?></td>
                        <td>[*alias*]</td>
                    </tr>
                    <tr>
                        <td width="200"><?= $_lang['page_data_created'] ?>:</td>
                        <td><?=
                            evo()->toDateFormat(
                                entity('createdon') + evo()->config('server_offset_time', 0)
                            )
                            ?>
                            (<b><?= $createdbyname ?></b>)
                        </td>
                        <td>[*createdon:date*]</td>
                    </tr>
                    <?php if ($editedbyname != '') { ?>
                        <tr>
                            <td><?= $_lang['page_data_edited'] ?>:</td>
                            <td><?= evo()->toDateFormat(entity('editedon') + evo()->config('server_offset_time', 0)) ?>
                                (<b><?= $editedbyname ?></b>)
                            </td>
                            <td>[*editedon:date*]</td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td width="200"><?= $_lang['page_data_status'] ?>:</td>
                        <td><?=
                            entity('published') == 0
                                ? '<span class="unpublishedDoc">' . $_lang['page_data_unpublished'] . '</span>'
                                : '<span class="publisheddoc">' . $_lang['page_data_published'] . '</span>'
                            ?></td>
                        <td>[*published*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_publishdate'] ?>:</td>
                        <td><?=
                            entity('pub_date') == 0
                                ? "(<i>" . $_lang['not_set'] . "</i>)"
                                : evo()->toDateFormat(entity('pub_date'))
                            ?></td>
                        <td>[*pub_date:date*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_unpublishdate'] ?>:</td>
                        <td><?=
                            entity('unpub_date') == 0
                                ? "(<i>" . $_lang['not_set'] . "</i>)"
                                : evo()->toDateFormat(entity('unpub_date'))
                            ?></td>
                        <td>[*unpub_date:date*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_cacheable'] ?>:</td>
                        <td><?= entity('cacheable') == 0 ? $_lang['no'] : $_lang['yes'] ?></td>
                        <td>[*cacheable*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_searchable'] ?>:</td>
                        <td><?= entity('searchable') == 0 ? $_lang['no'] : $_lang['yes'] ?></td>
                        <td>[*searchable*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['resource_opt_menu_index'] ?>:</td>
                        <td><?= entity('menuindex') ?></td>
                        <td>[*menuindex*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['resource_opt_show_menu'] ?>:</td>
                        <td><?= entity('hidemenu') == 1 ? $_lang['no'] : $_lang['yes'] ?></td>
                        <td>[*hidemenu*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_web_access'] ?>:</td>
                        <td><?php
                            if (entity('privateweb') == 0) {
                                echo $_lang['public'];
                            } else {
                                echo sprintf(
                                    '<b class="access-private">%s</b> <img src="%ssecured.gif" />',
                                    $_lang['private'],
                                    $icons_path
                                );
                            } ?>
                        </td>
                        <td>[*privateweb*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_mgr_access'] ?>:</td>
                        <td><?php
                            if (entity('privatemgr') == 0) {
                                echo $_lang['public'];
                            } else {
                                echo sprintf(
                                    '<b class="access-private">%s</b> <img src="%ssecured.gif" />',
                                    $_lang['private'],
                                    $icons_path
                                );
                            } ?></td>
                        <td>[*privatemgr*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_editor'] ?>:</td>
                        <td><?= entity('richtext') == 0 ? $_lang['no'] : $_lang['yes'] ?></td>
                        <td>[*richtext*]</td>
                    </tr>
                    <tr>
                        <td><?= $_lang['page_data_folder'] ?>:</td>
                        <td><?= entity('isfolder') == 0 ? $_lang['no'] : $_lang['yes'] ?></td>
                        <td>[*isfolder*]</td>
                    </tr>
                </table>
            </div><!-- end sectionBody -->
        </div><!-- end tab-page -->
        <?php
        $cachePath = MODX_CACHE_PATH . "docid_{$id}.pageCache.php";
        if (is_file($cachePath)) :
            $cache = file_get_contents(MODX_CACHE_PATH . "docid_{$id}.pageCache.php");
            ?>
            <!-- Page Source -->
            <div class="tab-page" id="tabSource">
                <h2 class="tab"><?= $_lang['page_data_source'] ?></h2>
                <?=
                sprintf(
                    '%s<p><textarea style="width: 100%%; height: 400px;">%s</textarea>',
                    $_lang['page_data_cached'],
                    hsc($cache)
                );
                ?>
            </div><!-- end tab-page -->
        <?php endif; ?>
    </div><!-- end documentPane -->
    <script>
        tpDocInfo = new WebFXTabPane(document.getElementById("docInfo"), false);
    </script>
</div><!-- end sectionBody -->
