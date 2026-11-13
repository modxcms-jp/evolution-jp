<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$action = request_intvar('a');

switch ($action) {
    case 35:
        if (!evo()->hasPermission('edit_role')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    case 38:
        if (!evo()->hasPermission('new_role')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    default:
        alert()->setError(3);
        alert()->dumpError();
}

$role = request_intvar('id');
if ($action === 38) {
    $role = 0; // 新規は id を使わない
}

// check to see the role editor isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', "action=35 and id='{$role}'");
$total = db()->count($rs);
if ($total > 1) {
    for ($i = 0; $i < $total; $i++) {
        $lock = db()->getRow($rs);
        if ($lock['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf(lang("lock_msg"), $lock['username'], lang('role'));
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
}
// end check for lock


if ($action === 35) {
    $rs = db()->select('*', '[+prefix+]user_roles', "id='{$role}'");
    $total = db()->count($rs);
    if ($total > 1) {
        echo "More than one role returned!<p>";
        exit;
    }
    if ($total < 1) {
        echo "No role returned!<p>";
        exit;
    }
    $roledata = db()->getRow($rs);
    $_SESSION['itemname'] = role('name');
} else {
    $roledata = [];
    $_SESSION['itemname'] = "New role";
}

function role($key, $default = null)
{
    global $roledata;
    return $roledata[$key] ?? $default;
}
?>
<script type="text/javascript">
    Evo.ready(function() {
        // 「すべて」のチェックに合わせて、操作できる権限のチェックと値をそろえる
        const allcheck = document.getElementById('allcheck');
        if (!allcheck) {
            return;
        }
        allcheck.addEventListener('click', function() {
            const checked = this.checked;
            document.querySelectorAll('input.click:not(:disabled)').forEach(function(el) {
                el.checked = checked;
            });
            document.querySelectorAll('input.set').forEach(function(el) {
                el.value = checked ? '1' : '0';
            });
        });
    });

    function changestate(element) {
        documentDirty = true;
        currval = eval(element).value;
        if (currval == 1) {
            eval(element).value = 0;
        } else {
            eval(element).value = 1;
        }
    }

    function deletedocument() {
        jConfirm(<?= js_literal(lang('confirm_delete_role')) ?>, <?= js_literal(lang('confirm')) ?>,
            function(r) {
                if (r)
                    EvoCsrf.post("index.php?id=" + document.userform.id.value + "&a=37");
                else return false;
            }
        );
    }
</script>
<form action="index.php?a=36" method="post" name="userform" class="role-form" enctype="multipart/form-data">
    <input type="hidden" name="mode" value="<?= hsc(EvoFormFlow::mode('role')) ?>">
    <input type="hidden" name="id" value="<?= $role ?>">

    <h1><?= lang('role_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_role')): ?>
                <li id="Button1" class="mutate"><a href="#" onclick="documentDirty=false; document.userform.save.click();"><?= lang('update') ?></a></li>
            <?php endif; ?>
            <?php
            if ($action === 35) {
                $params = [
                    'onclick' => 'deletedocument();',
                    'label' => lang('delete')
                ];
                if (evo()->hasPermission('delete_role')) {
                    echo manager()->ab($params);
                }
            }
            $params = [
                'onclick' => "EvoShell.go('index.php?a=86');",
                'label' => lang('cancel')
            ];
            echo manager()->ab($params);
            ?>
        </ul>
    </div>

    <div class="section">
        <div class="sectionBody">
            <fieldset>
                <table>
                    <tr>
                        <td><?= lang('role_name') ?>:</td>
                        <td><input name="name" type="text" maxlength="50" value="<?= hsc(role('name')) ?>">
                        </td>
                    </tr>
                    <tr>
                        <td><?= lang('resource_description') ?>:</td>
                        <td><textarea name="description"><?= hsc(role('description')) ?></textarea></td>
                    </tr>
                </table>
            </fieldset>
            <label><input type="checkbox" id="allcheck" /> Select all</label>
            <style type="text/css">
                .role-form label {
                    display: block;
                }

                .role-form td {
                    vertical-align: top;
                }
            </style>
            <table>
                <tr>
                    <td>
                        <fieldset>
                            <h3><?= lang('page_data_general') ?></h3>
                            <?php
                            echo render_form('frames', lang('role_frames'), 'disabled');
                            echo render_form('home', lang('role_home'), 'disabled');
                            echo render_form('logout', lang('role_logout'), 'disabled');
                            echo render_form('help', lang('role_help'));
                            echo render_form('action_ok', lang('role_actionok'), 'disabled');
                            echo render_form('error_dialog', lang('role_errors'), 'disabled');
                            echo render_form('about', lang('role_about'), 'disabled');
                            echo render_form('credits', lang('role_credits'), 'disabled');
                            echo render_form('change_password', lang('role_change_password'));
                            echo render_form('save_password', lang('role_save_password'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_content_management') ?></h3>
                            <?php
                            echo render_form('view_document', lang('role_view_docdata'), 'disabled');
                            echo render_form('new_document', lang('role_create_doc'));
                            echo render_form('edit_document', lang('role_edit_doc'));
                            echo render_form('save_document', lang('role_save_doc'));
                            echo render_form('publish_document', lang('role_publish_doc'));
                            echo render_form('move_document', lang('role_move_doc'));
                            echo render_form('delete_document', lang('role_delete_doc'));
                            echo render_form('empty_trash', lang('role_empty_trash'));
                            echo render_form('empty_cache', lang('role_cache_refresh'));
                            echo render_form('view_unpublished', lang('role_view_unpublished'));
                            echo render_form('view_schedule', lang('role_site_schedule'));
                            ?>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <table>
                <tr>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_template_management') ?></h3>
                            <?php
                            echo render_form('new_template', lang('role_create_template'));
                            echo render_form('edit_template', lang('role_edit_template'));
                            echo render_form('save_template', lang('role_save_template'));
                            echo render_form('delete_template', lang('role_delete_template'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_snippet_management') ?></h3>
                            <?php
                            echo render_form('new_snippet', lang('role_create_snippet'));
                            echo render_form('edit_snippet', lang('role_edit_snippet'));
                            echo render_form('save_snippet', lang('role_save_snippet'));
                            echo render_form('delete_snippet', lang('role_delete_snippet'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_chunk_management') ?></h3>
                            <?php
                            echo render_form('new_chunk', lang('role_create_chunk'));
                            echo render_form('edit_chunk', lang('role_edit_chunk'));
                            echo render_form('save_chunk', lang('role_save_chunk'));
                            echo render_form('delete_chunk', lang('role_delete_chunk'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_plugin_management') ?></h3>
                            <?php
                            echo render_form('new_plugin', lang('role_create_plugin'));
                            echo render_form('edit_plugin', lang('role_edit_plugin'));
                            echo render_form('save_plugin', lang('role_save_plugin'));
                            echo render_form('delete_plugin', lang('role_delete_plugin'));
                            ?>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <fieldset>
                <h3><?= lang('role_module_management') ?></h3>
                <?php
                echo render_form('new_module', lang('role_new_module'));
                echo render_form('edit_module', lang('role_edit_module'));
                echo render_form('save_module', lang('role_save_module'));
                echo render_form('delete_module', lang('role_delete_module'));
                echo render_form('exec_module', lang('role_run_module'));
                ?>
            </fieldset>

            <table>
                <tr>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_user_management') ?></h3>
                            <?php
                            echo render_form('new_user', lang('role_new_user'));
                            echo render_form('edit_user', lang('role_edit_user'));
                            echo render_form('save_user', lang('role_save_user'));
                            echo render_form('delete_user', lang('role_delete_user'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_web_user_management') ?></h3>
                            <?php
                            echo render_form('new_web_user', lang('role_new_web_user'));
                            echo render_form('edit_web_user', lang('role_edit_web_user'));
                            echo render_form('save_web_user', lang('role_save_web_user'));
                            echo render_form('delete_web_user', lang('role_delete_web_user'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_udperms') ?></h3>
                            <?php
                            echo render_form('access_permissions', lang('role_access_persmissions'));
                            echo render_form('web_access_permissions', lang('role_web_access_persmissions'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_role_management') ?></h3>
                            <?php
                            echo render_form('new_role', lang('role_new_role'));
                            echo render_form('edit_role', lang('role_edit_role'));
                            echo render_form('save_role', lang('role_save_role'));
                            echo render_form('delete_role', lang('role_delete_role'));
                            ?>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <table>
                <tr>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_eventlog_management') ?></h3>
                            <?php
                            echo render_form('view_eventlog', lang('role_view_eventlog'));
                            echo render_form('delete_eventlog', lang('role_delete_eventlog'));
                            ?>
                        </fieldset>
                    </td>
                    <td>
                        <fieldset>
                            <h3><?= lang('role_config_management') ?></h3>
                            <?php
                            echo render_form('logs', lang('role_view_logs'));
                            echo render_form('settings', lang('role_edit_settings'));
                            echo render_form('file_manager', lang('role_file_manager'));
                            echo render_form('bk_manager', lang('role_bk_manager'));
                            echo render_form('import_static', lang('role_import_static'));
                            echo render_form('export_static', lang('role_export_static'));
                            echo render_form('remove_locks', lang('role_remove_locks'));
                            ?>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <input type="submit" name="save" style="display:none">
</form>
</div>
</div>


<?php
function render_form($name, $label, $status = '')
{
    $tpl = <<< EOT
<label>
	<input name="[+name+]check" class="click" type="checkbox" onchange="changestate(document.userform.[+name+])" [+checked+] [+status+]>
	<input type="hidden" class="[+set+]" name="[+name+]" value="[+value+]">
	[+label+]
</label>

EOT;
    $checked = (role($name) == 1) ? 'checked' : '';
    $value = (role($name) == 1) ? 1 : 0;
    if ($status == 'disabled') {
        $checked = 'checked';
        $value = 1;
        $set = 'fix';
    } else {
        $set = 'set';
    }

    $output = $tpl;
    $output = str_replace('[+name+]', $name, $output);
    $output = str_replace('[+checked+]', $checked, $output);
    $output = str_replace('[+status+]', $status, $output);
    $output = str_replace('[+value+]', $value, $output);
    $output = str_replace('[+label+]', $label, $output);
    $output = str_replace('[+set+]', $set, $output);
    return $output;
}
