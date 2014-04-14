<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!isset($modx->config['preview_mode']))      $modx->config['preview_mode'] = '1';
if(!isset($modx->config['tvs_below_content'])) $modx->config['tvs_below_content'] = '1';

include_once(MODX_MANAGER_PATH . 'actions/mutate_content.functions.inc.php');

$id = getDocId(); // New is '0'

checkPermissions($id);
checkDocLock($id);

global $config, $docObject;
$config = & $modx->config;
$docgrp = getDocgrp();

global $default_template; // For plugins (ManagerManager etc...)
$default_template = getDefaultTemplate();

$initial_v = $id==='0' ? getInitialValues() : array();
$db_v      = $id==='0' ? array()            : getValuesFromDB($id,$docgrp);
$form_v    = $_POST    ? $_POST             : array();

$docObject = mergeValues($initial_v,$db_v,$form_v);

$tmplVars  = getTmplvars($id,$docgrp);
$docObject = $docObject + $tmplVars;

$content = $docObject; //Be compatible with old plugins
$modx->documentObject = & $content;

$docObject = (object) $docObject;

global $template, $selected_editor; // For plugins (ManagerManager etc...)

$template = $docObject->template;

$selected_editor = (isset ($form_v['which_editor'])) ? $form_v['which_editor'] : $config['which_editor'];

checkViewUnpubDocPerm($docObject->published,$docObject->editedby);// Only a=27

$_SESSION['itemname'] = to_safestr($docObject->pagetitle);

$tpl['head'] = <<< EOT
[+JScripts+]
<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php">
	<input type="hidden" name="a" value="5" />
	<input type="hidden" name="id" value="[+id+]" />
	<input type="hidden" name="mode" value="[+a+]" />
	<input type="hidden" name="MAX_FILE_SIZE" value="[+upload_maxsize+]" />
	<input type="hidden" name="newtemplate" value="" />
	<input type="hidden" name="pid" value="[+pid+]" />
	<input type="submit" name="save" style="display:none" />
	[+OnDocFormPrerender+]
	
	<fieldset id="create_edit">
	<h1>[+title+]</h1>

	[+actionButtons+]

	<div class="sectionBody">
	<div class="tab-pane" id="documentPane">
		<script type="text/javascript">
			tpSettings = new WebFXTabPane(document.getElementById('documentPane'), [+remember_last_tab+] );
		</script>
EOT;

$tpl['foot'] = <<< EOT
		[+OnDocFormRender+]
	</div><!--div class="tab-pane" id="documentPane"-->
	</div><!--div class="sectionBody"-->
	</fieldset>
</form>
<script type="text/javascript">
    storeCurTemplate();
</script>
[+OnRichTextEditorInit+]
EOT;

$tpl['tab-page']['general'] = <<< EOT
<!-- start main wrapper -->
	<!-- General -->
	<div class="tab-page" id="tabGeneral">
		<h2 class="tab">[+_lang_settings_general+]</h2>
		<script type="text/javascript">
			tpSettings.addTabPage(document.getElementById('tabGeneral'));
		</script>
		<table width="99%" border="0" cellspacing="5" cellpadding="0">
			[+fieldPagetitle+]
			[+fieldLongtitle+]
			[+fieldDescription+]
			[+fieldAlias+]
			[+fieldWeblink+]
			[+fieldIntrotext+]
			[+fieldTemplate+]
			[+fieldMenutitle+]
			[+fieldMenuindex+]
			[+renderSplit+]
			[+fieldParent+]
		</table>
		[+sectionContent+]
		[+sectionTV+]
	</div><!-- end #tabGeneral -->
EOT;

$tpl['tab-page']['tv'] = <<< EOT
<!-- TVs -->
<div class="tab-page" id="tabTv">
	<h2 class="tab">[+_lang_tv+]</h2>
	<script type="text/javascript">
		tpSettings.addTabPage(document.getElementById('tabTv'));
	</script>
	[+TVFields+]
</div>
EOT;

$tpl['tab-page']['settings'] = <<< EOT
	<!-- Settings -->
	<div class="tab-page" id="tabSettings">
		<h2 class="tab">[+_lang_settings_page_settings+]</h2>
		<script type="text/javascript">
			tpSettings.addTabPage(document.getElementById('tabSettings'));
		</script>
		<table width="99%" border="0" cellspacing="5" cellpadding="0">
			[+fieldPublished+]
			[+fieldPub_date+]
			[+fieldUnpub_date+]
			[+renderSplit+]
			[+fieldType+]
			[+fieldContentType+]
			[+fieldContent_dispo+]
			[+renderSplit+]
			[+fieldLink_attributes+]
			[+fieldIsfolder+]
			[+fieldRichtext+]
			[+fieldDonthit+]
			[+fieldSearchable+]
			[+fieldCacheable+]
			[+fieldSyncsite+]
		</table>
	</div><!-- end #tabSettings -->
EOT;

$tpl['tab-page']['meta'] = <<< EOT
<!-- META Keywords -->
<div class="tab-page" id="tabMeta">
	<h2 class="tab">[+_lang_meta_keywords+]</h2>
	<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabMeta" ) );</script>
	<table width="99%" border="0" cellspacing="5" cellpadding="0">
	<tr style="height: 24px;"><td>[+_lang_resource_metatag_help+]<br /><br />
		<table border="0" style="width:inherit;">
		<tr>
			<td>
				<span class="warning">[+_lang_keywords+]</span><br />
				<select name="keywords[]" multiple="multiple" size="16" class="inputBox" style="width: 200px;">
				[+keywords+]
				</select>
				<br />
				<input type="button" value="[+_lang_deselect_keywords+]" onclick="clearKeywordSelection();" />
			</td>
			<td>
				<span class="warning">[+_lang_metatags+]</span><br />
				<select name="metatags[]" multiple="multiple" size="16" class="inputBox" style="width: 220px;">
				[+metatags+]
				</select>
				<br />
				<input type="button" class="button" value="[+_lang_deselect_metatags+]" onclick="clearMetatagSelection();" />
			</td>
		</tr>
		</table>
		</td>
	</tr>
	</table>
</div><!-- end #tabMeta -->
EOT;

$tpl['tab-page']['access'] = <<< EOT
<!-- Access Permissions -->
<div class="tab-page" id="tabAccess">
	<h2 class="tab" id="tab_access_header">[+_lang_access_permissions+]</h2>
	<script type="text/javascript">tpSettings.addTabPage( document.getElementById( "tabAccess" ) );</script>
	<script type="text/javascript">
		/* <![CDATA[ */
		function makePublic(b) {
			var notPublic = false;
			var f = document.forms['mutate'];
			var chkpub = f['chkalldocs'];
			var chks = f['docgroups[]'];
			if (!chks && chkpub) {
				chkpub.checked=true;
				return false;
			} else if (!b && chkpub) {
				if (!chks.length) notPublic = chks.checked;
				else for (i = 0; i < chks.length; i++) if (chks[i].checked) notPublic = true;
				chkpub.checked = !notPublic;
			} else {
				if (!chks.length) chks.checked = (b) ? false : chks.checked;
				else for (i = 0; i < chks.length; i++) if (b) chks[i].checked = false;
				chkpub.checked = true;
			}
		}
		/* ]]> */
	</script>
	<p>[+_lang_access_permissions_docs_message+]</p>
	<ul>
		[+UDGroups+]
	</ul>
</div><!--div class="tab-page" id="tabAccess"-->
EOT;

// invoke OnDocFormPrerender event
$evtOut = $modx->invokeEvent('OnDocFormPrerender', array('id' => $id));

$ph = array();
$ph['JScripts'] = getJScripts();
$ph['OnDocFormPrerender']  = is_array($evtOut) ? implode("\n", $evtOut) : '';
$ph['id'] = $id;
$ph['upload_maxsize'] = $modx->config['upload_maxsize'] ? $modx->config['upload_maxsize'] : 3145728;
$ph['a'] = (int) $_REQUEST['a'];
if(!$_REQUEST['pid'])
	$tpl['head'] = str_replace('<input type="hidden" name="pid" value="[+pid+]" />','',$tpl['head']);
else $ph['pid'] = $_REQUEST['pid'];
$ph['title'] = $id!=0 ? "{$_lang['edit_resource_title']}(ID:{$id})" : $_lang['create_resource_title'];
$ph['actionButtons'] = getActionButtons($id,$docObject->parent,$docObject->isfolder,$docObject->deleted);
$ph['remember_last_tab'] = ($config['remember_last_tab'] === '2' || $_GET['stay'] === '2') ? 'true' : 'false';

echo $modx->parseText($tpl['head'],$ph);


$ph = array();
$ph['_lang_settings_general'] = $_lang['settings_general'];
$ph['fieldPagetitle']   = fieldPagetitle();
$ph['fieldLongtitle']   = fieldLongtitle();
$ph['fieldDescription'] = fieldDescription();
$ph['fieldAlias']       = fieldAlias($id);
$ph['fieldWeblink']     = ($docObject->type==='reference') ? fieldWeblink() : '';
$ph['fieldIntrotext']   = fieldIntrotext();
$ph['fieldTemplate']    = fieldTemplate();
$ph['fieldMenutitle']   = fieldMenutitle();
$ph['fieldMenuindex']   = fieldMenuindex();
$ph['renderSplit']      = renderSplit();
$ph['fieldParent']      = fieldParent();

$ph['sectionContent'] =  sectionContent();
$ph['sectionTV']      =  $modx->config['tvs_below_content'] ? sectionTV() : '';

echo $modx->parseText($tpl['tab-page']['general'],$ph);


$ph['TVFields'] =  fieldsTV();
$ph['_lang_tv'] = $_lang['tmplvars'];
if($modx->config['tvs_below_content']==='0'&&0<count($tmplVars))
	echo $modx->parseText($tpl['tab-page']['tv'],$ph);

$ph = array();
$ph['_lang_settings_page_settings'] = $_lang['settings_page_settings'];
$ph['fieldPublished']  =  fieldPublished();
$ph['fieldPub_date']   = fieldPub_date($id);
$ph['fieldUnpub_date'] = fieldUnpub_date($id);
$ph['renderSplit'] = renderSplit();
$ph['fieldType'] = fieldType();
if($docObject->type !== 'reference') {
	$ph['fieldContentType'] = fieldContentType();
	$ph['fieldContent_dispo'] = fieldContent_dispo();
} else {
	$ph['fieldContentType'] = '<input type="hidden" name="contentType" value="' . $docObject->contentType . '" />';
	$ph['fieldContent_dispo'] = '<input type="hidden" name="content_dispo" value="' . $docObject->content_dispo . '" />';
}
$ph['fieldLink_attributes'] = fieldLink_attributes();
$ph['fieldIsfolder']   = fieldIsfolder();
$ph['fieldRichtext']   = fieldRichtext();
$ph['fieldDonthit']    = $modx->config['track_visitors']==='1' ? fieldDonthit() : '';
$ph['fieldSearchable'] = fieldSearchable();
$ph['fieldCacheable']  = $docObject->type === 'document' ? fieldCacheable() : '';
$ph['fieldSyncsite']   = fieldSyncsite();
echo $modx->parseText($tpl['tab-page']['settings'],$ph);



if ($modx->hasPermission('edit_doc_metatags') && isset($config['show_meta']) && $config['show_meta']==='1'):
	$keywords = getKeywords();
	$option = array();
	if(0<count($keywords)):
		$keywords_selected = getSelectedKeywords();
		$keys = array_keys($keywords);
		$option = array();
		foreach ($keys as $key)
		{
			$value = $keywords[$key];
			$selected = $keywords_selected[$key];
			$option[] = '<option value="'.$key.'"'.$selected.'>'."{$value}</option>";
		}
	endif;
	$ph['_lang_meta_keywords'] = $_lang['meta_keywords'];
	$ph['_lang_resource_metatag_help'] = $_lang['keywords'];
	$ph['_lang_keywords'] = $_lang['resource_metatag_help'];
	$ph['keywords'] = implode("\n",$option);
	$ph['_lang_deselect_keywords'] = $_lang['deselect_keywords'];
	
	$metatags = getMetatags();
	$option = array();
	if(0<count($metatags)):
		$metatags_selected = getSelectedMetatags();
		$tags = array_keys($metatags);
		foreach ($tags as $tag)
		{
			$value = $metatags[$tag];
			$selected = $metatags_selected[$tag];
			$option[] = '<option value="'.$tag.'"'.$selected.'>'."{$value}</option>";
		}
	endif;
	$ph['metatags'] = implode("\n",$option);
	$ph['_lang_deselect_metatags'] = $_lang['deselect_metatags'];
	$ph['_lang_metatags'] = $_lang['metatags'];
	echo $modx->parseText($tpl['tab-page']['meta'],$ph);
endif;

/*******************************
 * Document Access Permissions */
if ($modx->config['use_udperms'] == 1)
{
	global $permissions_yes, $permissions_no;
	$permissions = getUDGroups($id);
	
	// See if the Access Permissions section is worth displaying...
	if (!empty($permissions)):
		$ph = array();
		$ph['_lang_access_permissions'] = $_lang['access_permissions'];
		$ph['_lang_access_permissions_docs_message'] = $_lang['access_permissions_docs_message'];
		$ph['UDGroups'] = implode("\n", $permissions);
		echo $modx->parseText($tpl['tab-page']['access'],$ph);
	elseif($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0)
           && ($_SESSION['mgrPermissions']['access_permissions'] == 1
           || $_SESSION['mgrPermissions']['web_access_permissions'] == 1)):
		echo '<p>' . $_lang["access_permissions_docs_collision"] . '</p>';
	endif;
}
/* End Document Access Permissions *
 ***********************************/

// invoke OnDocFormRender event
$OnDocFormRender = $modx->invokeEvent('OnDocFormRender', array(
	'id' => $id,
));

$OnRichTextEditorInit = '';
if($modx->config['use_editor'] === '1') {
	if(is_array($rte_field) && 0<count($rte_field)) {
		if($_REQUEST['a'] == '4' || $_REQUEST['a'] == '27' || $_REQUEST['a'] == '72') {
			// invoke OnRichTextEditorInit event
			$evtOut = $modx->invokeEvent('OnRichTextEditorInit', array(
				'editor' => $selected_editor,
				'elements' => $rte_field
			));
			if (is_array($evtOut)) $OnRichTextEditorInit = implode('', $evtOut);
		}
	}
}
$ph['OnDocFormRender']      = is_array($OnDocFormRender) ? implode("\n", $OnDocFormRender) : '';
$ph['OnRichTextEditorInit'] = $OnRichTextEditorInit;
echo $modx->parseText($tpl['foot'],$ph);

