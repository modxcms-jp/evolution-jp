<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

switch ((int) $_REQUEST['a']) {
	case 107:
		if(!$modx->hasPermission('new_module')) {
			$e->setError(3);
			$e->dumpError();
		}
		break;
	case 108:
		if(!$modx->hasPermission('edit_module')) {
			$e->setError(3);
			$e->dumpError();
		}
		break;
	default:
		$e->setError(3);
		$e->dumpError();
}

if (isset($_REQUEST['id']))
        $id = (int)$_REQUEST['id'];
else    $id = 0;

if ($manager_theme)
        $manager_theme .= '/';
else    $manager_theme  = '';

// Check to see the editor isn't locked
$rs = $modx->db->select('internalKey, username','[+prefix+]active_users',"action=108 AND id='{$id}'");
$limit = $modx->db->getRecordCount($rs);
if ($limit > 1)
{
	for ($i = 0; $i < $limit; $i++)
	{
		$lock = $modx->db->getRow($rs);
		if ($lock['internalKey'] != $modx->getLoginUserID())
		{
			$msg = sprintf($_lang['lock_msg'], $lock['username'], 'module');
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}
// end check for lock

// make sure the id's a number
if (!is_numeric($id)) {
	echo 'Passed ID is NaN!';
	exit;
}

if (isset($_GET['id']))
{
	$rs = $modx->db->select('*','[+prefix+]site_modules',"id='{$id}'");
	$limit = $modx->db->getRecordCount($rs);
	if ($limit > 1)
	{
		echo '<p>Multiple modules sharing same unique id. Not good.<p>';
		exit;
	}
	if ($limit < 1)
	{
		echo '<p>No record found for id: '.$id.'.</p>';
		exit;
	}
	$content = $modx->db->getRow($rs);
	$_SESSION['itemname'] = $content['name'];
} else {
	$_SESSION['itemname'] = 'New Module';
	$content['wrap'] = '1';
}

?>
<script type="text/javascript">
$j(function(){
	$j('select[name="categoryid"]').change(function(){
		if($j(this).val()=='-1')
		{
			$j('#newcategry').fadeIn();
		}
		else
		{
			$j('#newcategry').fadeOut();
			$j('input[name="newcategory"]').val('');
		}
	});
	$j('input[name="enable_sharedparams"]').change(function(){
		var checked = $j('input[name="enable_sharedparams"]').is(':checked');
		if(checked)
		{
			$j('.sharedparams').fadeIn();
		}
		else
		{
			$j('.sharedparams').fadeOut();
		}
	});
});
function loadDependencies() {
	if (documentDirty) {
		if (!confirm("<?php echo $_lang['confirm_load_depends']?>")) {
			return;
		}
	}
	documentDirty = false;
	window.location.href="index.php?id=<?php echo $_REQUEST['id']?>&a=113";
};
function duplicaterecord() {
	if(confirm("<?php echo $_lang['confirm_duplicate_record']?>")==true) {
		documentDirty=false;
		document.location.href="index.php?id=<?php echo $_REQUEST['id']?>&a=111";
	}
}

function deletedocument() {
	if(confirm("<?php echo $_lang['confirm_delete_module']?>")==true) {
		documentDirty=false;
		document.location.href="index.php?id=" + document.mutate.id.value + "&a=110";
	}
}

function setTextWrap(ctrl,b) {
	if(!ctrl) return;
	ctrl.wrap = (b)? "soft":"off";
}

// Current Params
var currentParams = {};

function showParameters(ctrl) {
	var c,p,df,cp;
	var ar,desc,value,key,dt;

	currentParams = {}; // reset;

	if (ctrl) {
		f = ctrl.form;
	} else {
		f= document.forms['mutate'];
		if(!f) return;
	}

	// setup parameters
	tr = (document.getElementById) ? document.getElementById('displayparamrow'):document.all['displayparamrow'];
	dp = (f.properties.value) ? f.properties.value.split("&"):"";
	if(!dp) tr.style.display='none';
	else {
		t='<table style="margin-bottom:3px;margin-left:14px;background-color:#EEEEEE" cellpadding="2" cellspacing="1"><thead><tr><td><?php echo $_lang['parameter']?></td><td><?php echo $_lang['value']?></td></tr></thead>';
		for(p = 0; p < dp.length; p++) {
			dp[p]=(dp[p]+'').replace(/^\s|\s$/,""); // trim
			ar = dp[p].split("=");
			key = ar[0];     // param
			ar = (ar[1]+'').split(";");
			desc = ar[0];   // description
			dt = ar[1];     // data type
			value = decode((ar[2])? ar[2]:'');

			// store values for later retrieval
			if (key && dt=='list') currentParams[key] = [desc,dt,value,ar[3]];
			else if (key) currentParams[key] = [desc,dt,value];

			if (dt) {
				switch(dt) {
					case 'int':
						c = '<input type="text" name="prop_'+key+'" value="'+value+'" size="30" onchange="setParameter(\''+key+'\',\''+dt+'\',this)" />';
						break;
					case 'menu':
						value = ar[3];
						c = '<select name="prop_'+key+'" style="width:168px" onchange="setParameter(\''+key+'\',\''+dt+'\',this)">';
						ls = (ar[2]+'').split(",");
						if(currentParams[key]==ar[2]) currentParams[key] = ls[0]; // use first list item as default
						for(i=0;i<ls.length;i++) {
							c += '<option value="'+ls[i]+'"'+((ls[i]==value)? ' selected="selected"':'')+'>'+ls[i]+'</option>';
						}
						c += '</select>';
						break;
					case 'list':
						value = ar[3];
						ls = (ar[2]+'').split(",");
						if(currentParams[key]==ar[2]) currentParams[key] = ls[0]; // use first list item as default
						c = '<select name="prop_'+key+'" size="'+ls.length+'" style="width:168px" onchange="setParameter(\''+key+'\',\''+dt+'\',this)">';
						for(i=0;i<ls.length;i++) {
							c += '<option value="'+ls[i]+'"'+((ls[i]==value)? ' selected="selected"':'')+'>'+ls[i]+'</option>';
						}
						c += '</select>';
						break;
					case 'list-multi':
						value = (ar[3]+'').replace(/^\s|\s$/,"");
						arrValue = value.split(",")
							ls = (ar[2]+'').split(",");
						if(currentParams[key]==ar[2]) currentParams[key] = ls[0]; // use first list item as default
						c = '<select name="prop_'+key+'" size="'+ls.length+'" multiple="multiple" style="width:168px" onchange="setParameter(\''+key+'\',\''+dt+'\',this)">';
						for(i=0;i<ls.length;i++) {
							if(arrValue.length) {
								for(j=0;j<arrValue.length;j++) {
									if(ls[i]==arrValue[j]) {
										c += '<option value="'+ls[i]+'" selected="selected">'+ls[i]+'</option>';
									} else {
										c += '<option value="'+ls[i]+'">'+ls[i]+'</option>';
									}
								}
							} else {
								c += '<option value="'+ls[i]+'">'+ls[i]+'</option>';
							}
						}
						c += '</select>';
						break;
					case 'textarea':
                        c = '<textarea class="phptextarea" name="prop_'+key+'" cols="50" rows="4" onchange="setParameter(\''+key+'\',\''+dt+'\',this)">'+value+'</textarea>';
						break;
					default:  // string
						c = '<input type="text" name="prop_'+key+'" value="'+value+'" size="30" onchange="setParameter(\''+key+'\',\''+dt+'\',this)" />';
						break;

				}
				t +='<tr><td bgcolor="#FFFFFF">'+desc+'</td><td bgcolor="#FFFFFF">'+c+'</td></tr>';
			};
		}
		t+='</table>';
		td = (document.getElementById) ? document.getElementById('displayparams'):document.all['displayparams'];
		td.innerHTML = t;
		tr.style.display='';
	}
	implodeParameters();
}

function setParameter(key,dt,ctrl) {
	var v;
	if(!ctrl) return null;
	switch (dt) {
		case 'int':
			ctrl.value = parseInt(ctrl.value);
			if(isNaN(ctrl.value)) ctrl.value = 0;
			v = ctrl.value;
			break;
		case 'menu':
			v = ctrl.options[ctrl.selectedIndex].value;
			currentParams[key][3] = v;
			implodeParameters();
			return;
			break;
		case 'list':
			v = ctrl.options[ctrl.selectedIndex].value;
			currentParams[key][3] = v;
			implodeParameters();
			return;
			break;
		case 'list-multi':
			var arrValues = new Array;
			for(var i=0; i < ctrl.options.length; i++) {
				if(ctrl.options[i].selected) {
					arrValues.push(ctrl.options[i].value);
				}
			}
			currentParams[key][3] = arrValues.toString();
			implodeParameters();
			return;
			break;
		default:
			v = ctrl.value+'';
			break;
	}
	currentParams[key][2] = v;
	implodeParameters();
}

// implode parameters
function implodeParameters() {
	var v, p, s='';
	for(p in currentParams) {
		if(currentParams[p]) {
			v = currentParams[p].join(";");
			if(s && v) s+=' ';
			if(v) s += '&'+p+'='+ encode(v);
		}
	}
	document.forms['mutate'].properties.value = s;
}

function encode(s) {
	s=s+'';
	s = s.replace(/\=/g,'%3D'); // =
	s = s.replace(/\&/g,'%26'); // &
	return s;
}

function decode(s) {
	s=s+'';
	s = s.replace(/\%3D/g,'='); // =
	s = s.replace(/\%26/g,'&'); // &
	return s;
}

// Resource browser
function OpenServerBrowser(url, width, height ) {
	var iLeft = (screen.width  - width) / 2 ;
	var iTop  = (screen.height - height) / 2 ;

	var sOptions = "toolbar=no,status=no,resizable=yes,dependent=yes" ;
	sOptions += ",width=" + width ;
	sOptions += ",height=" + height ;
	sOptions += ",left=" + iLeft ;
	sOptions += ",top=" + iTop ;

	var oWindow = window.open( url, "FCKBrowseWindow", sOptions ) ;
}

function BrowseServer() {
	var w = screen.width * 0.7;
	var h = screen.height * 0.7;
	OpenServerBrowser("<?php echo $base_url?>manager/media/browser/mcpuk/browser.php?Type=images", w, h);
}

function SetUrl(url, width, height, alt) {
	document.mutate.icon.value = url;
}
</script>
<form name="mutate" id="mutate" class="module" method="post" action="index.php?a=109" enctype="multipart/form-data">
<?php
    // invoke OnModFormPrerender event
    $evtOut = $modx->invokeEvent('OnModFormPrerender', array('id' => $id));
    if(is_array($evtOut)) echo implode('',$evtOut);
?>
<input type="hidden" name="id" value="<?php echo $content['id']?>">
<input type="hidden" name="mode" value="<?php echo $_GET['a']?>">

	<h1><?php echo $_lang['module_title']?></h1>
	
    <div id="actions">
    	  <ul class="actionButtons">
<?php if($modx->hasPermission('save_module')):?>
    		  <li id="Button1">
    			<a href="#" onclick="documentDirty=false; document.mutate.save.click();">
    			  <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']?>
    			</a>
    			  <span class="and"> + </span>
    			<select id="stay" name="stay">
    			  <?php if ($modx->hasPermission('new_module')) { ?>
    			  <option id="stay1" value="1" <?php echo $_REQUEST['stay']=='1' ? ' selected=""' : ''?> ><?php echo $_lang['stay_new']?></option>
    			  <?php } ?>
    			  <option id="stay2" value="2" <?php echo $_REQUEST['stay']=='2' ? ' selected="selected"' : ''?> ><?php echo $_lang['stay']?></option>
    			  <option id="stay3" value=""  <?php echo $_REQUEST['stay']=='' ? ' selected=""' : ''?>  ><?php echo $_lang['close']?></option>
    			</select>
    		  </li>
<?php endif; ?>
<?php
    if ($_REQUEST['a'] == '108')
    {
    	$params = array('onclick'=>'deletedocument();','icon'=>$_style['icons_delete_document'],'label'=>$_lang['delete']);
    	if($modx->hasPermission('delete_module'))
    		echo $modx->manager->ab($params);
    }
	$params = array('onclick'=>"document.location.href='index.php?a=106';",'icon'=>$_style['icons_cancel'],'label'=>$_lang['cancel']);
	echo $modx->manager->ab($params);
?>
    	  </ul>
    </div>
	<!-- end #actions -->

<div class="sectionBody">
<p><img class="icon" src="<?php echo $_style['icons_modules'];?>" alt="." style="vertical-align:middle;text-align:left;" /> <?php echo $_lang['module_msg']?></p>

<div class="tab-pane" id="modulePane">
	<script type="text/javascript">
	tpModule = new WebFXTabPane( document.getElementById( "modulePane"), <?php echo (($modx->config['remember_last_tab'] == 2) || ($_GET['stay'] == 2 )) ? 'true' : 'false'; ?> );
	</script>

	<!-- General -->
	<div class="tab-page" id="tabModule">
	<h2 class="tab"><?php echo $_lang['settings_general']?></h2>
	<script type="text/javascript">tpModule.addTabPage( document.getElementById( "tabModule" ) );</script>

	<table>
		<tr>
			<td align="left"><?php echo $_lang['module_name']?>:</td>
			<td align="left"><input name="name" type="text" maxlength="100" value="<?php echo htmlspecialchars($content['name'])?>" class="inputBox"><span class="warning" id="savingMessage">&nbsp;</span></td>
		</tr>
		<tr><td align="left" valign="top" colspan="2"><input name="disabled" type="checkbox" <?php echo $content['disabled'] == 1 ? 'checked="checked"' : ''?> value="on" class="inputBox" />
			<span style="cursor:pointer" onclick="document.mutate.disabled.click();"><?php echo  $content['disabled'] == 1 ? '<span class="warning">'.$_lang['module_disabled'].'</span>' : $_lang['module_disabled']?></span></td>
		</tr>
	</table>

	<!-- PHP text editor start -->
	<div style="position:relative">
		<div style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;">
			<span style="float:left;font-weight:bold;"><?php echo $_lang['module_code']?></span>
			<span style="float:right; color:#707070"><?php echo $_lang['wrap_lines']?><input name="wrap" type="checkbox"<?php echo $content['wrap']== 1 ? ' checked="checked"' : ''?> class="inputBox" onclick="setTextWrap(document.mutate.post,this.checked)" /></span>
		</div>
<?php
	if($content['locked'] === '1')
		$readonly = 'readonly';
	else $readonly = '';
?>
        <textarea dir="ltr" <?php echo $readonly;?> class="phptextarea" name="post" style="width:100%; height:370px;" wrap="<?php echo $content['wrap']== 1 ? 'soft' : 'off'?>"><?php echo htmlspecialchars($content['modulecode'])?></textarea>
	</div>
	<!-- PHP text editor end -->
	</div>

	<!-- Configuration -->
	<div class="tab-page" id="tabConfig">
		<h2 class="tab"><?php echo $_lang['settings_config']?></h2>
		<script type="text/javascript">tpModule.addTabPage( document.getElementById( "tabConfig" ) );</script>

		<table>
		<tr>
			<td align="left"><?php echo $_lang['existing_category']?>:</td>
			<td align="left">
			<select name="categoryid">
				<option value="0"><?php echo $_lang["no_category"]; ?></option>
<?php
				$ds = $modx->manager->getCategories();
				if ($ds) {
					foreach($ds as $n => $v) {
						echo "\t\t\t".'<option value="'.$v['id'].'"'.($content['category'] == $v['id'] ? ' selected="selected"' : '').'>'.htmlspecialchars($v['category'])."</option>\n";
					}
				}
?>
            <option value="-1">&gt;&gt; <?php echo $_lang["new_category"]; ?></option>
            </select></td>
        </tr>
		<tr id="newcategry" style="display:none;">
			<td align="left" valign="top" style="padding-top:5px;"><?php echo $_lang['new_category']?>:</td>
			<td align="left" valign="top" style="padding-top:5px;"><input name="newcategory" type="text" maxlength="45" value="" class="inputBox"></td>
		</tr>
		<tr>
			<td align="left"><?php echo $_lang['module_desc']?>:</td>
			<td align="left"><textarea name="description" style="padding:0;width:300px;height:4em;"><?php echo $content['description'];?></textarea></td>
		</tr>
		<tr>
			<td align="left"><?php echo $_lang['icon']?> <span class="comment">(32x32)</span>:</td>
			<td align="left"><input type="text" maxlength="255" style="width: 235px;" name="icon" value="<?php echo $content['icon']?>" /> <input type="button" value="<?php echo $_lang['insert']?>" onclick="BrowseServer();" /></td>
		</tr>
		<tr style="display:none;"><td align="left"><input name="enable_resource" title="<?php echo $_lang['enable_resource']?>" type="checkbox"<?php echo $content['enable_resource']==1 ? ' checked="checked"' : ''?> class="inputBox" /> <span style="cursor:pointer" onclick="document.mutate.enable_resource.click();" title="<?php echo $_lang['enable_resource']?>"><?php echo $_lang["element"]?></span>:</td>
			<td align="left"><input name="resourcefile" type="text" maxlength="255" value="<?php echo $content['resourcefile']?>" class="inputBox" /></td>
		</tr>
<?php if($modx->hasPermission('save_module')==1) {?>
		<tr>
			<td align="left" valign="top" colspan="2"><input name="locked" type="checkbox"<?php echo $content['locked'] == 1 ? ' checked="checked"' : ''?> class="inputBox" />
			<span style="cursor:pointer" onclick="document.mutate.locked.click();"><?php echo $_lang['lock_module']?></span> <span class="comment"><?php echo $_lang['lock_module_msg']?></span></td>
		</tr>
<?php } ?>
		<tr>
			<td align="left" valign="top"><?php echo $_lang['module_config']?>:</td>
			<td align="left" valign="top"><textarea name="properties" style="display:block;" maxlength="65535" class="inputBox phptextarea" onchange="showParameters(this);" /><?php echo $content['properties']?></textarea><input type="button" value="<?php echo $_lang['update_params'] ?>" style="width:16px; margin-left:2px;" title="<?php echo $_lang['update_params']?>" /></td>
		</tr>
		<tr id="displayparamrow">
			<td valign="top" align="left">&nbsp;</td>
			<td align="left" id="displayparams">&nbsp;</td>
		</tr>
		</table>
	</div>

<?php if ($_REQUEST['a'] == '107') { ?>
	<input name="guid" type="hidden" value="<?php echo createGUID(); ?>" />
<?php }
elseif ($_REQUEST['a'] == '108') { ?>
	<!-- Dependencies -->
	<div class="tab-page" id="tabDepend">
	<h2 class="tab"><?php echo $_lang['settings_dependencies']?></h2>
	<script type="text/javascript">tpModule.addTabPage( document.getElementById( "tabDepend" ) );</script>
	<div class="sectionBody">
<?php
$display = ($content['enable_sharedparams']!=1) ? 'style="display:none;"' : '';
?>
	<table>
		<tr>
			<td align="left" valign="top" colspan="2"><input name="enable_sharedparams" type="checkbox"<?php echo $content['enable_sharedparams']==1 ? ' checked="checked"' : ''?> class="inputBox" /> <span style="cursor:pointer" onclick="document.mutate.enable_sharedparams.click();"><?php echo $_lang['enable_sharedparams']?>:</span></td>
		</tr>
		<tr class="sharedparams" <?php echo $display; ?>>
			<td align="left" valign="top"><?php echo $_lang['guid']?>:</td>
			<td align="left" valign="top"><input name="guid" type="text" maxlength="32" value="<?php echo ($content['guid']!='') ? $content['guid'] : createGUID(); ?>" class="inputBox" /><br />
			<span class="comment"><?php echo $_lang['enable_sharedparams_msg']?></span><br /></td>
		</tr>
	</table>
	</div>
	<div class="sectionBody sharedparams" <?php echo $display; ?>>
		<p><?php echo $_lang['module_viewdepend_msg']?></p>
		<p class="actionButtons" style="float:none;overflow:hidden;zoom:1">
		<a href="#" onclick="loadDependencies();return false;"><img src="<?php echo $_style["icons_edit_document"]?>" align="absmiddle" /> <?php echo $_lang['manage_depends']?></a></p>
<?php
	$field = 'smd.id, COALESCE(ss.name,st.templatename,sv.name,sc.name,sp.name,sd.pagetitle) AS `name`, '.
	       'CASE smd.type'.
	       " WHEN 10 THEN 'Chunk'".
	       " WHEN 20 THEN 'Document'".
	       " WHEN 30 THEN 'Plugin'".
	       " WHEN 40 THEN 'Snippet'".
	       " WHEN 50 THEN 'Template'".
	       " WHEN 60 THEN 'TV'" .
	       'END AS `type`';
	$from = '[+prefix+]site_module_depobj AS smd '.
	       'LEFT JOIN [+prefix+]site_htmlsnippets AS sc ON sc.id = smd.resource AND smd.type = 10 '.
	       'LEFT JOIN [+prefix+]site_content AS sd ON sd.id = smd.resource AND smd.type = 20 '.
	       'LEFT JOIN [+prefix+]site_plugins AS sp ON sp.id = smd.resource AND smd.type = 30 '.
	       'LEFT JOIN [+prefix+]site_snippets AS ss ON ss.id = smd.resource AND smd.type = 40 '.
	       'LEFT JOIN [+prefix+]site_templates AS st ON st.id = smd.resource AND smd.type = 50 '.
	       'LEFT JOIN [+prefix+]site_tmplvars AS sv ON sv.id = smd.resource AND smd.type = 60 ';
$ds = $modx->db->select($field, $from, "smd.module='{$id}' ORDER BY smd.type,name");
if (!$ds) {
	echo "An error occured while loading module dependencies.";
} else {
	include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
	$grd = new DataGrid('', $ds, 0); // set page size to 0 t show all items
	$grd->noRecordMsg = $_lang['no_records_found'];
	$grd->cssClass = 'grid';
	$grd->columnHeaderClass = 'gridHeader';
	$grd->itemClass = 'gridItem';
	$grd->altItemClass = 'gridAltItem';
	$grd->columns = $_lang['element_name']." ,".$_lang['type'];
	$grd->fields = "name,type";
	echo $grd->render();
} ?>
	</div>
</div>
<?php } ?>
<?php
if ($modx->config['use_udperms'] == 1)
{
?>
<!-- Access permissions -->
<div class="tab-page" id="tabAccess">
<h2 class="tab"><?php echo $_lang['group_access_permissions']?></h2>
<script type="text/javascript">tpModule.addTabPage( document.getElementById("tabAccess") );</script>
<?php
	// fetch user access permissions for the module
	$groupsarray = array();
	$rs = $modx->db->select('*','[+prefix+]site_module_access',"module='{$id}'");
	$limit = $modx->db->getRecordCount($rs);
	for ($i = 0; $i < $limit; $i++)
	{
		$currentgroup = $modx->db->getRow($rs);
		$groupsarray[$i] = $currentgroup['usergroup'];
	}

	if($modx->hasPermission('access_permissions')) {
?>
<!-- User Group Access Permissions -->
	<script type="text/javascript">
	function makePublic(b) {
		var notPublic=false;
		var f=document.forms['mutate'];
		var chkpub = f['chkallgroups'];
		var chks = f['usrgroups[]'];
		if (!chks && chkpub) {
			chkpub.checked=true;
			return false;
		} else if (!b && chkpub) {
			if(!chks.length) notPublic=chks.checked;
			else for(i=0;i<chks.length;i++) if(chks[i].checked) notPublic=true;
			chkpub.checked=!notPublic;
		} else {
			if(!chks.length) chks.checked = (b) ? false : chks.checked;
			else for(i=0;i<chks.length;i++) if (b) chks[i].checked=false;
			chkpub.checked=true;
		}
	}
	</script>
	<p><?php echo $_lang['module_group_access_msg']?></p>
<?php
	}
	$chk = '';
	$rs = $modx->db->select('name, id','[+prefix+]membergroup_names');
	$limit = $modx->db->getRecordCount($rs);
	for ($i = 0; $i < $limit; $i++)
	{
		$row = $modx->db->getRow($rs);
		$groupsarray = is_numeric($id) && $id > 0 ? $groupsarray : array();
		$checked = in_array($row['id'], $groupsarray);
		if($modx->hasPermission('access_permissions'))
		{
			if ($checked) $notPublic = true;
			$chks .= '<label><input type="checkbox" name="usrgroups[]" value="'.$row['id'].'"'.($checked ? ' checked="checked"' : '').' onclick="makePublic(false)" />'.$row['name']."</label><br />\n";
		}
		elseif($checked)
		{
			$chks = '<input type="hidden" name="usrgroups[]"  value="'.$row['id'].'" />' . "\n" . $chks;
		}
	}
	if($modx->hasPermission('access_permissions'))
	{
		$chks = '<label><input type="checkbox" name="chkallgroups"'.(!$notPublic ? ' checked="checked"' : '').' onclick="makePublic(true)" /><span class="warning">'.$_lang['all_usr_groups'].'</span></label><br />' . "\n" . $chks;
	}
	echo $chks;
?>
</div>
<?php
}
?>
</div>
</div>

<input type="submit" name="save" style="display:none;">
<?php
// invoke OnModFormRender event
$evtOut = $modx->invokeEvent('OnModFormRender', array('id' => $id));
if(is_array($evtOut)) echo implode('',$evtOut);
?>
</form>
<script type="text/javascript">setTimeout('showParameters();',10);</script>

<?php
// create globally unique identifiers (guid)
function createGUID(){
	srand((double)microtime()*1000000);
	$r = rand() ;
	$u = uniqid(getmypid() . $r . (double)microtime()*1000000,1);
	$m = md5 ($u);
	return $m;
}
