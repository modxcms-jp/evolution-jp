<?php
$plugin_id_editing = $e->params['id']; // The ID of the plugin we're editing
$result = db()->select('name, id', evo()->getFullTableName('site_plugins'), "id='{$plugin_id_editing}'");
$plugin_editing_name = db()->getValue($result);

// if it's the right plugin
if (strtolower($plugin_editing_name) == 'managermanager') {
    // Get all templates
    $result = db()->select('templatename, id, description', evo()->getFullTableName('site_templates'), '', 'templatename ASC');
    $all_templates = $modx->db->makeArray($result);
    $template_table = '<table>';
    $template_table .= '<tr><th class="gridHeader">ID</th><th class="gridHeader">Template name</th><th class="gridHeader">Template description</th></tr>';
    $template_table .= '<tr><td class="gridItem">0</td><td class="gridItem">(blank)</td><td class="gridItem">Blank</td></tr>';
    foreach ($all_templates as $count => $tpl) {
        $class = ($count % 2) ? 'gridItem' : 'gridAltItem';
        $template_table .= '<tr>';
        $template_table .= '<td class="' . $class . '">' . $tpl['id'] . '</td>';
        $template_table .= '<td class="' . $class . '">' . jsSafe($tpl['templatename']) . '</td>';
        $template_table .= '<td class="' . $class . '">' . jsSafe($tpl['description']) . '</td>';
        $template_table .= '</tr>';
    }
    $template_table .= '</table>';

    // Get all tvs
    $result = db()->select('name,caption,id', evo()->getFullTableName('site_tmplvars'), '', 'name ASC');
    $all_tvs = $modx->db->makeArray($result);
    $tvs_table = '<table>';
    $tvs_table .= '<tr><th class="gridHeader">ID</th><th class="gridHeader">TV name</th><th class="gridHeader">TV caption</th></tr>';

    foreach ($all_tvs as $count => $tv) {
        $class = ($count % 2) ? 'gridItem' : 'gridAltItem';
        $tvs_table .= '<tr>';
        $tvs_table .= '<td class="' . $class . '">' . $tv['id'] . '</td>';
        $tvs_table .= '<td class="' . $class . '">' . jsSafe($tv['name']) . '</td>';
        $tvs_table .= '<td class="' . $class . '">' . jsSafe($tv['caption']) . '</td>';
        $tvs_table .= '</tr>';
    }
    $tvs_table .= '</table>';

    // Get all roles
    $result = db()->select('name, id', evo()->getFullTableName('user_roles'), '', 'name ASC');
    $all_roles = $modx->db->makeArray($result);

    $roles_table = '<table>';
    $roles_table .= '<tr><th class="gridHeader">ID</th><th class="gridHeader">Role name</th></tr>';
    foreach ($all_roles as $count => $role) {
        $class = ($count % 2) ? 'gridItem' : 'gridAltItem';
        $roles_table .= '<tr>';
        $roles_table .= '<td class="' . $class . '">' . $role['id'] . '</td>';
        $roles_table .= '<td class="' . $class . '">' . jsSafe($role['name']) . '</td>';
        $roles_table .= '</tr>';
    }
    $roles_table .= '</table>';


    // Load the jquery library
    $output = '<!-- Begin ManagerManager output -->' . "\n";

    $output .= '<script type="text/javascript">' . "\n";

    $output .= "mm_lastTab = 'tabEvents'; \n";
    $e->output($output);

    mm_createTab('Templates, TVs &amp; Roles', 'rolestemplates', '', '', '<p>These are the IDs for current templates,tvs and roles in your site.</p>' . $template_table . '&nbsp;' . $tvs_table . '&nbsp;' . $roles_table);

    $e->output('</script>');
    $e->output('<!-- End ManagerManager output -->' . "\n");
}
