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
    'no_match' => lang('element_filter_no_match'),
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

function element_filter_input(string $listId): string
{
    return sprintf(
        '<div class="element-filter"><input type="search" data-element-filter="%s" placeholder="%s" aria-label="%s" autocomplete="off"></div>',
        hsc($listId),
        hsc(lang('element_filter_placeholder')),
        hsc(lang('element_filter_placeholder'))
    );
}
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
                    <?= element_filter_input("elementList-{$type}") ?>
                    <?= ElementList::render(ElementList::rows($type), $listOptions + ['id' => "elementList-{$type}"]) ?>
                </div>
            <?php } ?>

            <div class="tab-page" id="tabCategory">
                <h2 class="tab"><?= lang('element_categories') ?></h2>
                <div><?= lang('category_msg') ?></div>
                <?= element_filter_input('elementList-all') ?>
                <?= ElementList::render(ElementList::allRows(), $listOptions + [
                    'id' => 'elementList-all',
                    'showType' => true,
                    'categoryDelete' => $canDeleteCategory,
                ]) ?>
            </div>
        </div>
    </div>
    <script src="<?= manager_asset('media/script/element-filter.js') ?>" type="text/javascript"></script>
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
