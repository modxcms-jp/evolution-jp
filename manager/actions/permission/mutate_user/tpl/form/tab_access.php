<h2 class="tab"><?php echo lang('access_permissions') ?></h2>
<div class="sectionHeader"><?php echo lang('access_permissions'); ?></div>
<div class="sectionBody">
    <?php
    $groupsarray = array ();

    if ($_GET['a'] == '12')
    { // only do this bit if the user is being edited
        $memberid = $_GET['id'];
        $rs = $modx->db->select('*','[+prefix+]member_groups',"member='{$memberid}'" );
        $limit = $modx->db->getRecordCount($rs);
        for ($i = 0; $i < $limit; $i++)
        {
            $currentgroup = $modx->db->getRow($rs);
            $groupsarray[$i] = $currentgroup['user_group'];
        }
    }

    // retain selected doc groups between post
    if (is_array($_POST['user_groups']))
    {
        foreach ($_POST['user_groups'] as $n => $v)
        {
            $groupsarray[] = $v;
        }
    }
    echo "<p>" . lang('access_permissions_user_message') . "</p>";
    $rs = $modx->db->select('name, id','[+prefix+]membergroup_names','','name');
    if($modx->db->getRecordCount($rs)<1):
        echo '<div class="actionButtons"><a href="index.php?a=40" class="primary">Create user group</a></div>';
    else:
        $tpl = '<label><input type="checkbox" name="user_groups[]" value="[+id+]" [+checked+] />[+name+]</label><br />';
        while($row = $modx->db->getRow($rs))
        {
            $src = $tpl;
            $ph = array();
            $ph['id'] = $row['id'];
            $ph['checked'] = in_array($row['id'], $groupsarray) ? 'checked="checked"' : '';
            $ph['name'] = $row['name'];
            $src = $modx->parseText($src,$ph);
            echo $src;
        }
    endif;
    ?>
</div>
