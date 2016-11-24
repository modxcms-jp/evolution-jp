<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('settings'))
{
	$e->setError(3);
	$e->dumpError();
}
include_once(MODX_MANAGER_PATH . 'actions/tool/mutate_settings/functions.inc.php');
// check to see the edit settings page isn't locked
$rs = $modx->db->select('internalKey, username', '[+prefix+]active_users', 'action=17');
if(1<$modx->db->getRecordCount($rs)) {
	while($row = $modx->db->getRow($rs))
	{
		if($row['internalKey']!=$modx->getLoginUserID())
		{
			$msg = sprintf($_lang["lock_settings_msg"],$row['username']);
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}

if(!empty($settings_version) && $settings_version!=$modx_version)
{
	include_once(MODX_CORE_PATH . 'upgrades/upgrades.php');
}

// reload system settings from the database.
// this will prevent user-defined settings from being saved as system setting
if(!isset($default_config) || !is_array($default_config))
	$default_config = include_once(MODX_CORE_PATH . 'default.config.php');

$settings = array();
$rs = $modx->db->select('setting_name, setting_value', '[+prefix+]system_settings');
while($row = $modx->db->getRow($rs))
{
	$settings[$row['setting_name']] = $row['setting_value'];
}
$settings = array_merge($default_config,$settings);

if ($modx->manager->hasFormValues()) {
	$_POST = $modx->manager->loadFormValues();
}
if(setlocale(LC_CTYPE, 0)==='Japanese_Japan.932')
{
	$settings['filemanager_path'] = mb_convert_encoding($settings['filemanager_path'], 'utf-8', 'sjis-win');
	$settings['rb_base_dir']      = mb_convert_encoding($settings['rb_base_dir'], 'utf-8', 'sjis-win');
}
$settings['filemanager_path'] = preg_replace('@^' . MODX_BASE_PATH . '@', '[(base_path)]', $settings['filemanager_path']);
$settings['rb_base_dir']      = preg_replace('@^' . MODX_BASE_PATH . '@', '[(base_path)]', $settings['rb_base_dir']);
if(isset($_POST)) $settings = array_merge($settings, $_POST);

if(strpos($settings['site_url'],'[(site_url)]')!==false)
	$settings['site_url'] = str_replace('[(site_url)]', MODX_SITE_URL, $settings['site_url']);
if(strpos($settings['base_url'],'[(base_url)]')!==false)
	$settings['base_url'] = str_replace('[(base_url)]', MODX_BASE_URL, $settings['base_url']);

extract($settings, EXTR_OVERWRITE);

$displayStyle = ($_SESSION['browser']==='modern') ? 'table-row' : 'block' ;

// load languages and keys
$lang_keys = array();
$dir = scandir(MODX_CORE_PATH . 'lang');
foreach ($dir as $filename)
{
	if(substr($filename,-8)!=='.inc.php') continue;
	$languagename = str_replace('.inc.php', '', $filename);
	$lang_keys[$languagename] = get_lang_keys($filename);
}

$isDefaultUnavailableMsg = $site_unavailable_message == $_lang['siteunavailable_message_default'];
$isDefaultUnavailableMsgJs = $isDefaultUnavailableMsg ? 'true' : 'false';
$site_unavailable_message_view = isset($site_unavailable_message) ? $site_unavailable_message : $_lang['siteunavailable_message_default'];

?>
<script type="text/javascript">
var displayStyle = '<?php echo $displayStyle; ?>';
var lang_chg = '<?php echo $_lang['confirm_setting_language_change']; ?>';
</script>
<script type="text/javascript" src="actions/tool/mutate_settings/functions.js"></script>
<form name="settings" action="index.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="a" value="30" />
	<h1><?php echo $_lang['settings_title']; ?></h1>
	<div id="actions">
		<ul class="actionButtons">
			<li id="Button1" class="mutate">
				<a href="#" onclick="documentDirty=false; document.settings.submit();">
					<img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']; ?>
				</a>
			</li>
			<li id="Button5" class="mutate">
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
<?php
    include_once(MODX_MANAGER_PATH . 'actions/tool/mutate_settings/tab1_site_settings.inc.php');
    include_once(MODX_MANAGER_PATH . 'actions/tool/mutate_settings/tab2_furl_settings.inc.php');
    include_once(MODX_MANAGER_PATH . 'actions/tool/mutate_settings/tab3_user_settings.inc.php');
    include_once(MODX_MANAGER_PATH . 'actions/tool/mutate_settings/tab4_manager_settings.inc.php');
    include_once(MODX_MANAGER_PATH . 'actions/tool/mutate_settings/tab6_filemanager_settings.inc.php');
?>
</div>
</div>
</form>
<script type="text/javascript">
	tpSettings = new WebFXTabPane( document.getElementById( "settingsPane" ), <?php echo $modx->config['remember_last_tab'] == 0 ? 'false' : 'true'; ?> );
	jQuery('#furlRowOn').change(function()    {jQuery('.furlRow').fadeIn();});
	jQuery('#furlRowOff').change(function()   {jQuery('.furlRow').fadeOut();});
	jQuery('#udPermsOn').change(function()    {jQuery('.udPerms').slideDown();});
	jQuery('#udPermsOff').change(function()   {jQuery('.udPerms').slideUp();});
	jQuery('#editorRowOn').change(function()  {jQuery('.editorRow').slideDown();});
	jQuery('#editorRowOff').change(function() {jQuery('.editorRow').slideUp();});
	jQuery('#rbRowOn').change(function()      {jQuery('.rbRow').slideDown();});
	jQuery('#rbRowOff').change(function()     {jQuery('.rbRow').slideUp();});
</script>
