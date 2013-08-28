<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('settings'))
{
	$e->setError(3);
	$e->dumpError();
}
// check to see the edit settings page isn't locked
$rs = $modx->db->select('internalKey, username', '[+prefix+]active_users', 'action=17');
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

// reload system settings from the database.
// this will prevent user-defined settings from being saved as system setting
if(!isset($default_config) || !is_array($default_config)) $default_config = include_once($modx->config['base_path'] . 'manager/includes/default.config.php');

$settings = array();
$rs = $modx->db->select('setting_name, setting_value', '[+prefix+]system_settings');
while($row = mysql_fetch_assoc($rs))
{
	$settings[$row['setting_name']] = $row['setting_value'];
}
$settings = array_merge($default_config,$settings);

if ($modx->manager->hasFormValues()) {
	$modx->manager->loadFormValues();
}
if(setlocale(LC_CTYPE, 0)==='Japanese_Japan.932')
{
	$settings['filemanager_path'] = mb_convert_encoding($settings['filemanager_path'], 'utf-8', 'sjis-win');
	$settings['rb_base_dir']      = mb_convert_encoding($settings['rb_base_dir'], 'utf-8', 'sjis-win');
}
$settings['filemanager_path'] = preg_replace('@^' . MODX_BASE_PATH . '@', '[(base_path)]', $settings['filemanager_path']);
$settings['rb_base_dir']      = preg_replace('@^' . MODX_BASE_PATH . '@', '[(base_path)]', $settings['rb_base_dir']);
if(isset($_POST)) $settings = array_merge($settings, $_POST);

extract($settings, EXTR_OVERWRITE);

$displayStyle = ($_SESSION['browser']!=='ie') ? 'table-row' : 'block' ;

// load languages and keys
$lang_keys = array();
$dir = scandir("{$base_path}manager/includes/lang");
foreach ($dir as $filename)
{
	if(substr($filename,-8)!=='.inc.php') continue;
	$languagename = str_replace('.inc.php', '', $filename);
	$lang_keys[$languagename] = get_lang_keys($filename);
}

//tonatos - delete as unused
//$isDefaultUnavailableMsg = $site_unavailable_message == $_lang['siteunavailable_message_default'];
//$isDefaultUnavailableMsgJs = $isDefaultUnavailableMsg ? 'true' : 'false';
//$site_unavailable_message_view = isset($site_unavailable_message) ? $site_unavailable_message : $_lang['siteunavailable_message_default'];

?>

<script type="text/javascript">
//$j(function(){
//	$j('#furlRow1').change(function() {$j('.furlRow').fadeIn();});
//	$j('#furlRow0').change(function() {$j('.furlRow').fadeOut();});
//	$j('#udPerms1').change(function() {$j('.udPerms').fadeIn();});
//	$j('#udPerms0').change(function() {$j('.udPerms').fadeOut();});
//	$j('#udPerms1').change(function() {$j('.udPerms').fadeIn();});
//	$j('#udPerms0').change(function() {$j('.udPerms').fadeOut();});
//	$j('#editorRow1').change(function() {$j('.editorRow').fadeIn();});
//	$j('#editorRow0').change(function() {$j('.editorRow').fadeOut();});
//	$j('#rbRow1').change(function() {$j('.rbRow').fadeIn();});
//	$j('#rbRow0').change(function() {$j('.rbRow').fadeOut();});
//});

function addContentType()
{
	var i,o,exists=false;
	var txt = document.settings.txt_custom_contenttype;
	var lst = document.settings.lst_custom_contenttype;
	for(i=0;i<lst.options.length;i++)
	{
		if(lst.options[i].value==txt.value) {
			exists=true;
			break;
		}
	}
	if (!exists)
	{
		o = new Option(txt.value,txt.value);
		lst.options[lst.options.length]= o;
		updateContentType();
	}
	txt.value='';
}
function removeContentType()
{
	var i;
	var lst = document.settings.lst_custom_contenttype;
	for(i=0;i<lst.options.length;i++)
	{
		if(lst.options[i].selected)
		{
			lst.remove(i);
			break;
		}
	}
	updateContentType();
}
function updateContentType()
{
	var i,o,ol=[];
	var lst = document.settings.lst_custom_contenttype;
	var ct = document.settings.custom_contenttype;
	while(lst.options.length)
	{
		ol[ol.length] = lst.options[0].value;
		lst.options[0]= null;
	}
	if(ol.sort) ol.sort();
	ct.value = ol.join(",");
	for(i=0;i<ol.length;i++)
	{
		o = new Option(ol[i],ol[i]);
		lst.options[lst.options.length]= o;
	}
	documentDirty = true;
}
/**
 * @param element el were language selection comes from
 * @param string lkey language key to look up
 * @param id elupd html element to update with results
 * @param string default_str default value of string for loaded manager language - allows some level of confirmation of change from default
 */
function confirmLangChange(el, lkey, elupd)
{
	lang_current = $j('#'+elupd).val();
	lang_default = $j('#'+lkey+'_hidden').val();
	proceed = true;
	if(lang_current != lang_default)
	{
		proceed = confirm('<?php echo $_lang['confirm_setting_language_change']; ?>');
	}
	if(proceed)
	{
		//document.getElementById(elupd).value = '';
		lang = $j(el).val();
		$j.post('index.php',{'a':'118','action':'get','lang':lang,'key':lkey},function(resp)
		{
			document.getElementById(elupd).value = resp;
		});
	}
}
</script>
<style type="text/css">
    table.settings {border-collapse:collapse;width:100%;}
    table.settings tr {border-bottom:1px dotted #ccc;}
    table.settings th {font-size:inherit;vertical-align:top;text-align:left;}
    table.settings th,table.settings td {padding:5px;}
    table.settings td input[type=text] {width:250px;}
</style>
<form name="settings" action="index.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="a" value="30" />
	<h1><?php echo $_lang['settings_title']; ?> [<a href="index.php?a=131"><?php echo $_lang['edit'];?></a>]</h1>
	<div id="actions">
		<ul class="actionButtons">
			<li id="Button1">
				<a href="#" onclick="documentDirty=false; document.settings.submit();">
					<img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']; ?>
				</a>
			</li>
			<li id="Button5">
				<a href="#" onclick="document.location.href='index.php?a=2';">
					<img src="<?php echo $_style["icons_cancel"]?>" /> <?php echo $_lang['cancel']; ?>
				</a>
			</li>
		</ul>
	</div>

    <div style="margin: 0 10px 0 20px">
        <input type="hidden" name="site_id" value="<?php echo $site_id; ?>" />
        <input type="hidden" name="settings_version" value="<?php echo $modx_version; ?>" />
        <!-- this field is used to check site settings have been entered/ updated after install or upgrade -->
<?php
	if(!isset($settings_version) || $settings_version!=$modx_version)
	{
	?>
	<div class='sectionBody'><p><?php echo $_lang['settings_after_install']; ?></p></div>
<?php
	}
?>
	<div class="tab-pane" id="settingsPane">
	<script type="text/javascript">
		tpSettings = new WebFXTabPane( document.getElementById( "settingsPane" ), <?php echo $modx->config['remember_last_tab'] == 0 ? 'false' : 'true'; ?> );
	</script>

<!-- New group output-->
<?php
    $depend_list = array();
    function l($text){
        global $_lang, $modx;
        $result = (isset($_lang[$text]))?$_lang[$text]:$text;

        //signupemail_message_message - parsePlaceholder remove placeholder in description
        $result = str_replace(
            array('MODX_SITE_URL','MODX_BASE_URL','email_sender'),
            array(MODX_SITE_URL,MODX_BASE_URL,$modx->config['email_sender']),$result);


        return $result;
    }

    $groups = $modx->db->GetObjects("system_settings_group");
    foreach($groups as $group){
        ?>
        <div class='tab-page' id='tabPage_<?php echo $group->id?>'>
            <h2 class="tab"><?php echo l($group->name)?></h2>
            <script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabPage_<?php echo $group->id?>" ) );</script>
            <table class="settings">
                <?php

                $inputs = $modx->db->GetObjects("system_settings_fields","id_group=$group->id and `options`!=''","sort");

                foreach($inputs as $input){?>
                    <tr <?php //зависимость от других полей
                        if (isset($depend_list[$input->setting_name])){
                            echo "class='".$depend_list[$input->setting_name]."_depend'";
                            if (empty($settings[$depend_list[$input->setting_name]])){
                                echo ' style="display:none"';
                            }
                        }
                        ?>">
                    <?php

                    $options = explode("||",$input->options);

                    $field_type = str_replace(array("..","/","\\"),"",$options[0]);
                    $field_include = MODX_BASE_PATH."manager/includes/field_{$field_type}.php";

                    if (is_file($field_include)){
                        include ($field_include);
                    }
                    ?>
                </tr>
                <?php } ?>
                <tr class="row1" style="border-bottom:none;">
                    <td colspan="2" style="padding:0;">
                        <?php
                        // invoke custom tab event

                        switch ($group->id){
                            case 1:
                                $event_name = "OnSiteSettingsRender";
                                break;
                            case 2:
                                $event_name = "OnFriendlyURLSettingsRender";
                                break;
                            case 3:
                                $event_name = "OnUserSettingsRender";
                                break;
                            case 4:
                                $event_name = "OnInterfaceSettingsRender";
                                break;
                            case 5:
                                $event_name = "OnMiscSettingsRender";
                                break;
                            default:
                                $event_name = "OnSystemSettingsRender";
                        }

                        $evtOut = $modx->invokeEvent($event_name,array("group_id"=>$group->id));

                        if(is_array($evtOut)) {
                            echo implode("",$evtOut);
                        }
                        ?>
                    </td>

            </table>
        </div>
        <?php
    }
?>
</div>
</div>
</form>
<?php
/**
* get_lang_keys
*
* @return array of keys from a language file
*/
function get_lang_keys($filename)
{
	$file = MODX_MANAGER_PATH.'includes/lang/' . $filename;
	if(is_file($file) && is_readable($file))
	{
		include($file);
		return array_keys($_lang);
	}
	else
	{
		return array();
	}
}
/**
* get_langs_by_key
*
* @return array of languages that define the key in their file
*/
function get_langs_by_key($key)
{
	global $lang_keys;
	$lang_return = array();
	foreach($lang_keys as $lang=>$keys)
	{
		if(in_array($key, $keys))
		{
			$lang_return[] = $lang;
		}
	}
	return $lang_return;
}

/**
* get_lang_options
*
* returns html option list of languages
*
* @param string $key specify language key to return options of langauges that override it, default return all languages
* @param string $selected_lang specify language to select in option list, default none
* @return html option list
*/
function get_lang_options($key=null, $selected_lang=null)
{
	global $lang_keys, $_lang;
	$lang_options = '';
	if($key)
	{
		$languages = get_langs_by_key($key);
		sort($languages);
		$lang_options .= '<option value="">'.$_lang['manager_language_title'].'</option>';
		foreach($languages as $language_name)
		{
			$uclanguage_name = ucwords(str_replace("_", " ", $language_name));
			$lang_options .= '<option value="'.$language_name.'">'.$uclanguage_name.'</option>';
		}
		return $lang_options;
	}
	else
	{
		$languages = array_keys($lang_keys);
		sort($languages);
		foreach($languages as $language_name)
		{
			$uclanguage_name = ucwords(str_replace("_", " ", $language_name));
			$sel = $language_name == $selected_lang ? ' selected="selected"' : '';
			$lang_options .= '<option value="'.$language_name.'" '.$sel.'>'.$uclanguage_name.'</option>';
		}
		return $lang_options;
	}
}


function form_radio($name,$value,$checked=false,$add='',$disabled=false) {
	if($checked)  $checked  = ' checked="checked"';
	if($disabled) $disabled = ' disabled';
	if($add)     $add = ' ' . $add;
	return '<input type="radio" name="' . $name . '" value="' . $value . '"' . $checked . $disabled . $add . ' />';
}

function wrap_label($str='',$object) {
	return "<label>{$object}\n{$str}</label>";
}

/*

Функция перемещена (move to field_role_list.php)

function get_role_list()
{
	global $modx, $default_role;
	
	$rs = $modx->db->select('id,name', '[+prefix+]user_roles', 'id!=1', 'save_role DESC,new_role DESC,id ASC');
	$tpl = '<option value="[+id+]" [+selected+]>[+name+]</option>';
	$options = "\n";
	while($ph=$modx->db->getRow($rs))
	{
		$ph['selected'] = ($default_role == $ph['id']) ? ' selected' : '';
		$options .= $modx->parsePlaceholder($tpl,$ph);
	}
	return $options;
}
*/