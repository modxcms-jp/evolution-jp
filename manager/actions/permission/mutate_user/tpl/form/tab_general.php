<?php if ($userid === (int) evo()->getLoginUserID()) { ?>
    <p><?= lang('user_edit_self_msg') ?></p>
<?php } ?>
<h2 class="tab"><?= lang('login_settings') ?></h2>
<table class="settings">
    <?php
    echo userFormBlockedNotice($user, (bool) user('id'));

    // 新規と、名前を変えた入力のまま差し戻したときは、初めから名前の入力欄を出す
    $oldUsername = user('oldusername');
    echo userFormNameRows([
        'username' => user('username'),
        'oldusername' => $oldUsername ?: user('username'),
        'hasName' => (bool) user('id'),
        'editing' => $action === 11 || ($oldUsername && $oldUsername != user('username')),
    ]);

    echo userFormPasswordRow($action === 11, true);

    echo userFormEmailRow($user);

    // ロール。自分自身は、ロールを変える権限の有無に合うロールだけを選べる
    if ($userid == evo()->getLoginUserID()) {
        $where = evo()->hasPermission('save_role') ? 'save_role=1' : 'save_role=0';
    } else {
        $where = '';
    }
    $rs = db()->select(
        'name, id',
        '[+prefix+]user_roles',
        $where,
        'save_role DESC, new_role DESC, id ASC'
    );
    $options = [];
    while ($row = db()->getRow($rs)) {
        if ($action === 11) {
            $selected = ($row['id'] == config('default_role'));
        } else {
            $selected = ($row['id'] == user('role'));
        }
        $options[] = html_tag(
            'option',
            [
                'value' => (int) $row['id'],
                'selected' => $selected ? null : ''
            ],
            hsc($row['name'])
        );
    }
    echo EvoFieldRow::render([
        'label' => lang('user_role'),
        'body' => '<select name="role" class="inputBox">' . implode("\n", $options) . '</select>',
    ]);
    ?>
</table>
<?php
// ログインの状態（設定ではないので、設定の定義には入れない。編集のときだけ出る）
if ($action === 12) {
    ?>
    <table class="settings">
        <?= userFormLoginStateRows($user) ?>
    </table>
    <?php
}
