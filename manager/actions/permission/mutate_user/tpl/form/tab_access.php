<h2 class="tab"><?= lang('access_permissions') ?></h2>
<div class="sectionHeader"><?= lang('access_permissions') ?></div>
<div class="sectionBody">
    <?php
    $groupsarray = [];
    if (evo()->input_get('a') == 12) { // only do this bit if the user is being edited
        $rs = db()->select(
            '*'
            , '[+prefix+]member_groups'
            , sprintf("member='%s'", evo()->input_get('id'))
        );
        while ($row = db()->getRow($rs)) {
            $groupsarray[] = $row['user_group'];
        }
    }

    // retain selected doc groups between post
    if (is_array($_POST['user_groups'])) {
        foreach ($_POST['user_groups'] as $v) {
            $groupsarray[] = $v;
        }
    }
    echo "<p>" . lang('access_permissions_user_message') . "</p>";
    $rs = db()->select('name, id', '[+prefix+]membergroup_names', '', 'name');
    if (!db()->count($rs)) {
        echo '<div class="actionButtons"><a href="index.php?a=40" class="primary">Create user group</a></div>';
    } else {
        $tpl = '<label><input type="checkbox" name="user_groups[]" value="[+id+]" [+checked+] />[+name+]</label><br />';
        while ($row = db()->getRow($rs)) {
            $src = $tpl;
            $ph = [];
            $ph['id'] = $row['id'];
            $ph['checked'] = in_array($row['id'], $groupsarray) ? 'checked="checked"' : '';
            $ph['name'] = $row['name'];
            echo $modx->parseText($src, $ph);
        }
    }
    ?>
</div>
