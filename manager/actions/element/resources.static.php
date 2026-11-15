<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

include_once MODX_CORE_PATH . 'element_list.class.php';

$listLabels = [
    'no_category' => lang('no_category'),
    'locked' => lang('locked'),
    'delete_category' => lang('delete_category'),
    'empty' => lang('no_results'),
    'types' => array_map(static function ($def) {
        return lang($def['label']);
    }, ElementList::types()),
];
$listOptions = [
    'labels' => $listLabels,
    'rtl' => ($modx_textdir ?? '') === 'rtl',
];
$canDeleteCategory = evo()->hasPermission('save_plugin')
    || evo()->hasPermission('save_snippet')
    || evo()->hasPermission('save_chunk')
    || evo()->hasPermission('save_template')
    || evo()->hasPermission('save_module');
?>
    <h1><?= lang('element_management') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate"><a href="#" onclick="EvoShell.go('index.php?a=2');"><?= lang('cancel') ?>
                </a></li>
        </ul>
    </div>

    <div class="sectionBody">
        <div class="tab-pane" id="elementsPane">
            <?php foreach (ElementList::types() as $type => $def) { ?>
                <?php if (empty($def['tab']) || !ElementList::canView($type)) {
                    continue;
                } ?>
                <div class="tab-page" id="<?= $def['tab'] ?>">
                    <h2 class="tab"><?= lang($def['label']) ?></h2>
                    <div><?= lang($def['message']) ?></div>
                    <ul class="actionButtons">
                        <li><a class="default" href="index.php?a=<?= $def['newAction'] ?>"><?= lang($def['newLabel']) ?></a></li>
                        <?php if ($type === 'plugin' && evo()->hasPermission('save_plugin')) { ?>
                            <li><a href="index.php?a=100" data-modal><?= lang('plugin_priority') ?></a></li>
                        <?php } ?>
                    </ul>
                    <?= ElementList::render(ElementList::rows($type), $listOptions) ?>
                </div>
            <?php } ?>

            <div class="tab-page" id="tabCategory">
                <h2 class="tab"><?= lang('element_categories') ?></h2>
                <div><?= lang('category_msg') ?></div>
                <br/>
                <?= ElementList::render(ElementList::allRows(), $listOptions + [
                    'showType' => true,
                    'categoryDelete' => $canDeleteCategory,
                ]) ?>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        var tpstatus = <?= config('remember_last_tab') == 0 ? 'false' : 'true' ?>;
        tpElements = new WebFXTabPane(document.getElementById("elementsPane"), tpstatus);

        document.getElementById('tabCategory').addEventListener('click', function (e) {
            var link = e.target.closest('[data-delete-category]');
            if (!link) {
                return;
            }
            e.preventDefault();
            var catId = parseInt(link.getAttribute('data-delete-category'), 10);
            jConfirm(<?= js_literal(lang('confirm_delete_category')) ?>, <?= js_literal(lang('delete_category')) ?>, function (r) {
                if (r) {
                    EvoCsrf.post('index.php?a=501&catId=' + catId);
                }
            });
        });
    </script>
