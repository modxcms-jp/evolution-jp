<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings'))
{
	$e->setError(3);
	$e->dumpError();
}

// check to see the edit settings page isn't locked
$rs = $modx->db->select('internalKey, username', '[+prefix+]active_users', 'action=133');
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

$id = $_REQUEST['id'];

?>

<h1><?php echo $_lang['settings_group_field_title']; ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button1">
            <a href="#" onclick="documentDirty=false; document.edit_field.submit();">
                <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']; ?>
            </a>
        </li>
        <li><a href="index.php?a=134&id=<?php echo htmlspecialchars($id)?>" onclick="return confirm('<?php echo l("setting_field_delete_confirm")?>');"><img src="<?php echo $_style["icons_delete_document"] ?>" /> <?php echo $_lang['delete']?></a></li>
        <li id="Button5">
            <a href="#" onclick="document.location.href='index.php?a=131';">
                <img src="<?php echo $_style["icons_cancel"]?>" /> <?php echo $_lang['cancel']; ?>
            </a>
        </li>
    </ul>
</div>
<style type="text/css">
    table.settings {border-collapse:collapse;width:100%;}
    table.settings tr {border-bottom:1px dotted #ccc;}
    table.settings th {font-size:inherit;vertical-align:top;text-align:left;}
    table.settings th,table.settings td {padding:5px;}
    table.settings td input[type=text] {width:250px;}
</style>
<div class="section">
    <div class="sectionBody">

        <?php

        if (isset($_POST['form_submitted_edit_field'])){

            $data = $_POST;

            //Проверяем setting_name смена имени параметра (check duplicate setting_name)
            if ($data['id']!=$data['setting_name']){
                $find = $modx->db->GetObject("system_settings_fields","setting_name='".$modx->db->escape($data['setting_name'])."'");
                if ($find!==false){
                    $error = l("dublicate_setting_name_error");
                }
            }

            if (empty($data['setting_name'])){
                $error=l("empty_setting_name_error");
            }

            $settings=$data;

            //Сохраняем при отсутсвие ошибок (save if no error)
            if (empty($error)){

                //update setting_name in table system_settings
                if ($data['id']!=$data['setting_name']){
                    $modx->db->update("setting_name='".$modx->db->escape($data['setting_name'])."'","[+prefix+]system_settings","setting_name='".$modx->db->escape($id)."'");
                }

                unset($data['a'],$data['id'],$data['form_submitted_edit_field']);
                if (empty($id)){
                    $modx->db->insert($data,"[+prefix+]system_settings_fields");
                    $modx->db->insert_ignore(array("setting_name"=>$data['setting_name']),"[+prefix+]system_settings");
                }else{
                    $modx->db->update($data,"[+prefix+]system_settings_fields","setting_name='".$modx->db->escape($id)."'");
                }
                $id = $data['setting_name'];
            }

        }else{
            $object = $modx->db->GetObject("system_settings_fields","setting_name='".$modx->db->escape($id)."'");
            if ($object===false){
                $settings = $_POST;
            }else{
                $settings = (array)$object;
            }
        };
        ?>

        <form action="index.php" method="post" name="edit_field">
            <input type="hidden" name="a" value="133"/>
            <input type="hidden" name="form_submitted_edit_field" value="1"/>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id)?>"/>

            <table class="settings">

                <tr>
                <?php

                    $input = (object)array("title"=>"setting_name_title","description"=>"setting_name_message","setting_name"=>"setting_name","error"=>$error);
                    include(MODX_BASE_PATH."manager/includes/field_text.php");
                    echo "</tr><tr>";

                    $input = (object)array("title"=>"setting_id_group_title","description"=>"setting_id_group_message","setting_name"=>"id_group");
                    $groups = $modx->db->GetObjects("system_settings_group");
                    foreach($groups as $group){
                        $options .= "$group->id=".l($group->name).";";
                    }
                    $options=array("select",$options);
                    include(MODX_BASE_PATH."manager/includes/field_select.php");
                    echo "</tr><tr>";

                    $input = (object)array("title"=>"setting_title_title","description"=>"setting_title_message","setting_name"=>"title");
                    include(MODX_BASE_PATH."manager/includes/field_text.php");
                    echo "</tr><tr>";

                    $input = (object)array("title"=>"setting_description_title","description"=>"setting_description_message","setting_name"=>"description");
                    include(MODX_BASE_PATH."manager/includes/field_text.php");
                    echo "</tr><tr>";

                    $input = (object)array("title"=>"setting_sort_title","description"=>"setting_sort_message","setting_name"=>"sort");
                    include(MODX_BASE_PATH."manager/includes/field_text.php");
                    echo "</tr><tr>";

                    $input = (object)array("title"=>"setting_options_title","description"=>"setting_options_message","setting_name"=>"options");
                    include(MODX_BASE_PATH."manager/includes/field_text.php");



                ?>
                </tr>
            </table>
        </form>
    </div>
</div>
