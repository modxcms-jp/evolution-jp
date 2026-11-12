<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
?>
    <script type="text/javascript">
        function deleteCategory(catid) {
            jConfirm(<?= js_literal(lang('confirm_delete_category')) ?>, <?= js_literal(lang('delete_category')) ?>, function (r) {
                if (r) EvoCsrf.post("index.php?a=501&catId=" + catid);
                else return false;
            });
        }
    </script>

    <h1><?= lang('element_management') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate"><a href="#" onclick="EvoShell.go('index.php?a=2');"><?= lang('cancel') ?>
                </a></li>
        </ul>
    </div>

    <div class="sectionBody">
        <div class="tab-pane" id="elementsPane">

            <!-- Templates -->
            <?php if (evo()->hasPermission('new_template') || evo()->hasPermission('edit_template')) { ?>
                <div class="tab-page" id="tabTemplates">
                    <h2 class="tab"><?= lang("manage_templates") ?></h2>
                    <div><?= lang('template_management_msg') ?></div>
                    <ul class="actionButtons">
                        <li><a class="default" href="index.php?a=19"><?= lang('new_template') ?>
                            </a></li>
                    </ul>
                    <?= createResourceList('site_templates', 16, 'templatename') ?>
                </div>
            <?php } ?>

            <!-- Template variables -->
            <?php if (evo()->hasPermission('new_template') || evo()->hasPermission('edit_template')) { ?>
                <div class="tab-page" id="tabVariables">
                    <h2 class="tab"><?= lang("tmplvars") ?></h2>
                    <!--//
                            Modified By Raymond for Template Variables
                            Added by Apodigm 09-06-2004- DocVars - web@apodigm.com
                        -->
                    <div><?= lang('tmplvars_management_msg') ?></div>
                    <ul class="actionButtons">
                        <li><a class="default" href="index.php?a=300"><?= lang('new_tmplvars') ?>
                            </a></li>
                    </ul>
                    <?= createResourceList('site_tmplvars', 301) ?>
                </div>
            <?php } ?>

            <!-- chunks -->
            <?php if (evo()->hasPermission('new_chunk') || evo()->hasPermission('edit_chunk')) { ?>
                <div class="tab-page" id="tabChunks">
                    <h2 class="tab"><?= lang("manage_htmlsnippets") ?></h2>
                    <div><?= lang('htmlsnippet_management_msg') ?></div>

                    <ul class="actionButtons">
                        <li><a class="default" href="index.php?a=77"><?= lang('new_htmlsnippet') ?>
                            </a></li>
                    </ul>
                    <?= createResourceList('site_htmlsnippets', 78) ?>
                </div>
            <?php } ?>

            <!-- snippets -->
            <?php if (evo()->hasPermission('new_snippet') || evo()->hasPermission('edit_snippet')) { ?>
                <div class="tab-page" id="tabSnippets">
                    <h2 class="tab"><?= lang("manage_snippets") ?></h2>
                    <div><?= lang('snippet_management_msg') ?></div>

                    <ul class="actionButtons">
                        <li><a class="default" href="index.php?a=23"><?= lang('new_snippet') ?>
                            </a></li>
                    </ul>
                    <?= createResourceList('site_snippets', 22) ?>
                </div>
            <?php } ?>

            <!-- plugins -->
            <?php if (evo()->hasPermission('new_plugin') || evo()->hasPermission('edit_plugin')) { ?>
                <div class="tab-page" id="tabPlugins">
                    <h2 class="tab"><?= lang("manage_plugins") ?></h2>
                    <div><?= lang('plugin_management_msg') ?></div>

                    <ul class="actionButtons">
                        <li><a class="default" href="index.php?a=101"><?= lang('new_plugin') ?>
                            </a></li>
                        <?php if (evo()->hasPermission('save_plugin')) { ?>
                            <li><a href="index.php?a=100" data-modal><?= lang('plugin_priority') ?>
                            </a></li><?php } ?>
                    </ul>
                    <?= createResourceList('site_plugins', 102) ?>
                </div>
            <?php } ?>

            <!-- category view -->
            <div class="tab-page" id="tabCategory">
                <h2 class="tab"><?= lang("element_categories") ?></h2>
                <div><?= lang('category_msg') ?></div>
                <br/>
                <ul>
                    <?= createCategoryList() ?>
                </ul>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        var tpstatus = <?= config('remember_last_tab') == 0 ? 'false' : 'true' ?>;
        tpElements = new WebFXTabPane(document.getElementById("elementsPane"), tpstatus);
    </script>
<?php
function createResourceList($element_name, $action, $nameField = 'name')
{
    global $modx, $modx_textdir;

    $preCat = '';
    $insideUl = 0;
    $output = '<ul>';
    $rows = getArray($element_name, $action, $nameField);
    if (is_array($rows) && 0 < count($rows)) {
        $ph['action'] = $action;
        $ph['rlm'] = $modx_textdir === 'rtl' ? '&rlm;' : '';
        foreach ($rows as $row) {
            $row['category'] = hsc($row['category']);
            if ($preCat !== $row['category']) {
                $output .= $insideUl ? '</ul>' : '';
                $output .= '<li><strong>' . $row['category'] . '</strong><ul>';
                $insideUl = 1;
            }
            $preCat = $row['category'];

            $ph['id'] = $row['id'];
            $ph['class'] = addclass($element_name, $row);
            $ph['name'] = hsc($row['name']);
            $ph['description'] = element_description_html($row['description']);
            $ph['locked'] = $row['locked'] ? ' <em>(' . lang('locked') . ')</em>' : '';
            $src = title($element_name);
            foreach ($ph as $k => $v) {
                $k = "[+{$k}+]";
                $src = str_replace($k, $v, $src);
            }
            $output .= $src;
        }
    } else {
        $output .= $rows;
    }
    $output .= $insideUl ? '</ul>' : '';
    $output .= '</ul>';
    return $output;
}

function title($element_name)
{
    return sprintf(
        '<li><span [+class+]><a href="index.php?id=[+id+]&amp;a=[+action+]">%s</a>[+rlm+]</span> [+description+][+locked+]</li>',
        in_array($element_name, ['site_templates', 'site_tmplvars']) ? '[[+id+]] [+name+]' : '[+name+]'
    );
}

function addclass($element_name, $row)
{
    if ($element_name === 'site_plugins') {
        return $row['disabled'] ? 'class="disabledPlugin"' : '';
    }
    if ($element_name === 'site_htmlsnippets') {
        return ($row['published'] === '0') ? 'class="unpublished"' : '';
    }
    return '';
}

function getArray($element_name, $action, $nameField = 'name')
{
    global $modx;

    $tbl_element_name = evo()->getFullTableName($element_name);
    $tbl_categories = evo()->getFullTableName('categories');
    $no_category = db()->escape(lang('no_category'));

    switch ($element_name) {
        case 'site_plugins':
            $f[] = "{$tbl_element_name}.disabled";
            break;
        case 'site_htmlsnippets':
            $f[] = "{$tbl_element_name}.published";
            break;
        case 'site_tmplvars':
            $f[] = "{$tbl_element_name}.rank";
            break;
    }
    $f[] = "{$tbl_element_name}.{$nameField} as name";
    $f[] = "{$tbl_element_name}.id";
    $f[] = "{$tbl_element_name}.description";
    $f[] = "{$tbl_element_name}.locked";
    $f[] = "if(isnull({$tbl_categories}.category),'{$no_category}',{$tbl_categories}.category) as category";
    $fields = implode(',', $f);
    $from = "{$tbl_element_name} left join {$tbl_categories} on {$tbl_element_name}.category = {$tbl_categories}.id";
    if ($element_name == 'site_tmplvars') {
        $orderby = "category ASC, {$tbl_element_name}.rank ASC, name ASC";
    } else {
        $orderby = 'category ASC, name ASC';
    }

    $rs = db()->select($fields, $from, '', $orderby);
    $limit = db()->count($rs);
    if ($limit < 1) {
        return lang('no_results');
    }

    $rows = [];
    while ($row = db()->getRow($rs)) {
        $rows[$row['id']] = $row;
    }
    return $rows;
}

function createCategoryList()
{
    global $modx;

    $displayInfo = [];
    $hasPermission = 0;
    if (evo()->hasPermission('edit_plugin') || evo()->hasPermission('new_plugin')) {
        $displayInfo['plugin'] = ['table' => 'site_plugins', 'action' => 102, 'name' => lang('manage_plugins')];
        $hasPermission = 1;
    }
    if (evo()->hasPermission('edit_snippet') || evo()->hasPermission('new_snippet')) {
        $displayInfo['snippet'] = [
            'table' => 'site_snippets',
            'action' => 22,
            'name' => lang('manage_snippets')
        ];
        $hasPermission = 1;
    }
    if (evo()->hasPermission('edit_chunk') || evo()->hasPermission('new_chunk')) {
        $displayInfo['htmlsnippet'] = [
            'table' => 'site_htmlsnippets',
            'action' => 78,
            'name' => lang('manage_htmlsnippets')
        ];
        $hasPermission = 1;
    }
    if (evo()->hasPermission('edit_template') || evo()->hasPermission('new_template')) {
        $displayInfo['templates'] = [
            'table' => 'site_templates',
            'action' => 16,
            'name' => lang('manage_templates')
        ];
        $displayInfo['tmplvars'] = ['table' => 'site_tmplvars', 'action' => 301, 'name' => lang('tmplvars')];
        $hasPermission = 1;
    }
    if (evo()->hasPermission('edit_module') || evo()->hasPermission('new_module')) {
        $displayInfo['modules'] = ['table' => 'site_modules', 'action' => 108, 'name' => lang('modules')];
        $hasPermission = 1;
    }

    //Category Delete permission check
    $delPerm = 0;
    if (
        evo()->hasPermission('save_plugin') ||
        evo()->hasPermission('save_snippet') ||
        evo()->hasPermission('save_chunk') ||
        evo()->hasPermission('save_template') ||
        evo()->hasPermission('save_module')
    ) {
        $delPerm = 1;
    }

    if ($hasPermission) {
        $finalInfo = [];

        foreach ($displayInfo as $n => $v) {
            $tbl_elm = evo()->getFullTableName($v['table']);
            $tbl_categories = evo()->getFullTableName('categories');
            if ($v['table'] == 'site_templates') {
                $fields = 'templatename as name, ';
            } elseif ($v['table'] == 'site_plugins') {
                $fields = "{$tbl_elm}.disabled, name, ";
            } elseif ($v['table'] == 'site_htmlsnippets') {
                $fields = "{$tbl_elm}.published, name, ";
            } else {
                $fields = 'name, ';
            }
            $fields .= "{$tbl_elm}.id, description, locked, {$tbl_categories}.category, {$tbl_categories}.id as catid";

            $from = "{$tbl_elm} left join {$tbl_categories} on {$tbl_elm}.category = {$tbl_categories}.id";
            $orderby = ($v['table'] == 'site_plugins') ? "{$tbl_elm}.disabled ASC,6,2" : '5,1';
            $rs = db()->select($fields, $from, '', $orderby);
            $limit = db()->count($rs);
            if ($limit > 0) {
                while ($row = db()->getRow($rs)) {
                    $row['type'] = $v['name'];
                    $row['action'] = $v['action'];
                    if (empty($row['category'])) {
                        $row['category'] = lang('no_category');
                    }
                    $finalInfo[] = $row;
                }
            }
        }

        foreach ($finalInfo as $n => $v) {
            $category[$n] = $v['category'];
            $name[$n] = $v['name'];
        }

        array_multisort($category, SORT_ASC, $name, SORT_ASC, $finalInfo);

        $preCat = '';
        $insideUl = 0;
        foreach ($finalInfo as $n => $v) {
            if ($preCat !== $v['category']) {
                echo $insideUl ? '</ul>' : '';
                if ($v['category'] == lang('no_category') || !$delPerm) {
                    echo '<li><strong>' . hsc($v['category']) . '</strong><ul>';
                } else {
                    echo '<li><strong>' . hsc($v['category']) . '</strong> (<a href="javascript:deleteCategory(\'' . (int)$v['catid'] . '\');">' . lang('delete_category') . '</a>)<ul>';
                }
                $insideUl = 1;
            }
            $ph = [];
            if (array_key_exists('disabled', $v) && $v['disabled']) {
                $ph['class'] = ' class="disabledPlugin"';
            }
            if (array_key_exists('published', $v) && $v['published'] === '0') {
                $ph['class'] = ' class="unpublished"';
            } else {
                $ph['class'] = '';
            }
            $ph['id'] = $v['id'];
            $ph['action'] = $v['action'];
            $ph['name'] = hsc($v['name']);
            $ph['type'] = $v['type'];
            $ph['description'] = (!empty($v['description'])) ? ' - ' . element_description_html($v['description']) : '';
            $ph['locked'] = ($v['locked']) ? ' <em>(' . lang('locked') . ')</em>' : '';
            $tpl = '<li><span [+class+]><a href="index.php?id=[+id+]&amp;a=[+action+]">[+name+]</a></span> ([+type+])[+description+][+locked+]</li>';
            foreach ($ph as $k => $value) {
                $k = '[+' . $k . '+]';
                $tpl = str_replace($k, $value, $tpl);
            }
            echo $tpl;
            $preCat = $v['category'];
        }
        echo $insideUl ? '</ul>' : '';
    }
}
