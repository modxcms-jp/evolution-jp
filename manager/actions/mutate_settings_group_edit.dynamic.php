<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings'))
{
	$e->setError(3);
	$e->dumpError();
}

// check to see the edit settings page isn't locked
$rs = $modx->db->select('internalKey, username', '[+prefix+]active_users', 'action=132');
$limit = $modx->db->getRecordCount($rs);
if($limit>1) {
	for ($i=0;$i<$limit;$i++)
	{
		$lock = $modx->db->getRow($rs);
		if($lock['internalKey']!=$modx->getLoginUserID())
		{
			$msg = sprintf($_lang["lock_settings_msg"],$lock['username']);
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}

function l($text){
    global $_lang, $modx;
    $result = (isset($_lang[$text]))?$_lang[$text]:$text;

    //signupemail_message_message - parsePlaceholder remove placeholder in description
    $result = str_replace(
        array('MODX_SITE_URL','MODX_BASE_URL','email_sender'),
        array(MODX_SITE_URL,MODX_BASE_URL,$modx->config['email_sender']),$result);


    return $result;
}


$id = (int)$_REQUEST['id'];
$group = $modx->db->GetObject("system_settings_group","id=$id");

if (empty($group)){
    $e->setError(5, "Group not found");
    $e->dumpError();
}


?>

<h1><?php echo $_lang['settings_group_edit_title']; ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button1">
            <a href="#" onclick="documentDirty=false; document.edit_tab.submit();">
                <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']; ?>
            </a>
        </li>
        <li><a href="index.php?a=135&id=<?php echo $id?>" onclick="return confirm('<?php echo l("setting_group_delete_confirm")?>');"><img src="<?php echo $_style["icons_delete_document"] ?>" /> <?php echo $_lang['delete']?></a></li>

        <li id="Button5">
            <a href="#" onclick="document.location.href='index.php?a=131';">
                <img src="<?php echo $_style["icons_cancel"]?>" /> <?php echo $_lang['cancel']; ?>
            </a>
        </li>
    </ul>
</div>

<div class="section">
    <div class="sectionBody">
        <?php

        if (isset($_POST['name'])){
            $new_tab_name = trim($_POST['name']);
            if (empty($new_tab_name)){
                $new_tab_error=l("new_tab_error");
            }else{
                $modx->db->update(array("name"=>$modx->db->escape($new_tab_name)),"[+prefix+]system_settings_group","id=$id");
            }
        }else{
            $new_tab_name = $group->name;
        }

        ?>
        <p><?php echo l("new_tab_message")?></p>
        <?if (!empty($new_tab_error)):?>
            <p class="fail"><?php echo $new_tab_error?></p>
        <?endif;?>
        <form action="index.php" method="post" name="edit_tab">
            <input type="hidden" name="id" value="<?php echo $id?>" />
            <input type="hidden" name="a" value="132"/>
            <input type="text" name="name" value="<?php echo htmlspecialchars($new_tab_name)?>" />
        </form>
    </div>
</div>
