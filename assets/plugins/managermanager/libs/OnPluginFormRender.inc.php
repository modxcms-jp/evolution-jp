<?php
$plugin_id_editing = (int)($e->params['id'] ?? 0); // The ID of the plugin we're editing
$result = db()->select('name, id', evo()->getFullTableName('site_plugins'), "id='{$plugin_id_editing}'");
$plugin_editing_name = db()->getValue($result);

// if it's the right plugin
if (strtolower($plugin_editing_name) == 'managermanager') {
    include_once(MODX_CORE_PATH . 'controls/data_table.class.php');

    // Get all templates
    $result = db()->select('templatename, id, description', evo()->getFullTableName('site_templates'), '', 'templatename ASC');
    $all_templates = db()->makeArray($result);
    array_unshift($all_templates, ['id' => 0, 'templatename' => '(blank)', 'description' => 'Blank']);
    $template_table = (new DataTable(
        [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'templatename', 'label' => 'Template name'],
            ['key' => 'description', 'label' => 'Template description'],
        ],
        ['id' => 'mm-templates']
    ))->render($all_templates);

    // Get all tvs
    $result = db()->select('name,caption,id', evo()->getFullTableName('site_tmplvars'), '', 'name ASC');
    $all_tvs = db()->makeArray($result);
    $tvs_table = (new DataTable(
        [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'TV name'],
            ['key' => 'caption', 'label' => 'TV caption'],
        ],
        ['id' => 'mm-tvs']
    ))->render($all_tvs);

    // Get all roles
    $result = db()->select('name, id', evo()->getFullTableName('user_roles'), '', 'name ASC');
    $all_roles = db()->makeArray($result);
    $roles_table = (new DataTable(
        [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Role name'],
        ],
        ['id' => 'mm-roles']
    ))->render($all_roles);

    // タブを足す出力の開始（即時に実行する）
    $output = '<!-- Begin ManagerManager output -->' . "\n";

    $output .= '<script type="text/javascript">' . "\n";

    $output .= "mm_lastTab = 'tabEvents'; \n";
    $e->output($output);

    mm_createTab('Templates, TVs &amp; Roles', 'rolestemplates', '', '', '<p>These are the IDs for current templates,tvs and roles in your site.</p>' . $template_table . '&nbsp;' . $tvs_table . '&nbsp;' . $roles_table);

    $e->output('</script>');
    $e->output('<!-- End ManagerManager output -->' . "\n");
}
