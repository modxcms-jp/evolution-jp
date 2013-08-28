<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings'))
{
	$e->setError(3);
	$e->dumpError();
}

// check to see the edit settings page isn't locked
$rs = $modx->db->select('internalKey, username', '[+prefix+]active_users', 'action=131');
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

?>

	<h1><?php echo $_lang['settings_edit_title']; ?> [<a href="index.php?a=17"><?php echo $_lang['cancel'];?></a>]</h1>


    <div class="section">
        <div class="sectionBody">
            <h2 style="margin-top:0"><?php echo l("setting_edit_tabs")?></h2>

            <?php
            //Create new tab
            if (isset($_POST['new_tab'])){
                $new_tab_name = trim($_POST['name']);
                if (empty($new_tab_name)){
                    $new_tab_error=l("new_tab_error");
                }else{
                    $modx->db->insert(array("name"=>$modx->db->escape($new_tab_name)),"[+prefix+]system_settings_group");
                }
            }

            $groups = $modx->db->GetObjects("system_settings_group");

            ?>

            <ul>
                <?php foreach($groups as $group):?>
                <li><a href="index.php?a=132&id=<?php echo $group->id?>"><?php echo l($group->name)?></a></li>
                <?php endforeach;?>
            </ul>

            <h2><?php echo l("setting_new_tab")?></h2>
            <p><?php echo l("new_tab_message")?></p>
            <?php if (!empty($new_tab_error)):?>
                <p class="fail"><?php echo $new_tab_error?></p>
            <?php endif;?>
            <form action="index.php?a=131" method="post">
                <input type="text" name="name" value="" size="40"/>
                <input type="submit" name="new_tab" value="<?php echo $_lang['settings_edit_new_tab_title']?>" />
            </form>
        </div>
    </div>

    <div class="section">
        <div class="sectionBody">
            <h2 style="margin-top:0"><?php echo l("setting_edit_field")?></h2>

            <div class="actionButtons">
                <a href="index.php?a=133" class="default"><?php echo l("setting_new_field")?></a>
            </div>

            <div class="tab-pane" id="settingsPane">
                <script type="text/javascript">
                    tpSettings = new WebFXTabPane( document.getElementById( "settingsPane" ), <?php echo $modx->config['remember_last_tab'] == 0 ? 'false' : 'true'; ?> );
                </script>

                <?php foreach($groups as $group):?>
                <div class='tab-page' id='tabPage_<?php echo $group->id?>'>
                    <h2 class="tab"><?php echo l($group->name)?></h2>
                    <script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabPage_<?php echo $group->id?>" ) );</script>
                    <ul>
                    <?php $inputs = $modx->db->GetObjects("system_settings_fields","id_group=$group->id","sort");?>

                    <?php foreach($inputs as $input):?>
                        <li>
                            <?php if (empty($input->options)):?><i><?php endif?>
                            <a href="index.php?a=133&id=<?php echo $input->setting_name?>"><?php echo $input->setting_name?></a>
                            <?php if (empty($input->options)):?></i><?php else:?> - <?php echo l($input->title)?><?php endif?>
                        </li>
                    <?php endforeach;?>

                    </ul>
                </div>
                <?php endforeach;?>
            </div>
        </div>
    </div>
