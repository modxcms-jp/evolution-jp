<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('edit_template') && $_REQUEST['a']=='301') {
    $e->setError(3);
    $e->dumpError();
}
if(!$modx->hasPermission('new_template') && $_REQUEST['a']=='300') {
    $e->setError(3);
    $e->dumpError();
}

if(isset($_REQUEST['id'])) $id = (int) $_REQUEST['id'];
else                       $id = 0;

// check to see the variable editor isn't locked
$rs = $modx->db->select('internalKey, username','[+prefix+]active_users',"action=301 AND id='{$id}'");
$total = $modx->db->getRecordCount($rs);
if($total>1)
{
	while($row = $modx->db->getRow($rs))
	{
		if($row['internalKey']!=$modx->getLoginUserID())
		{
			$msg = sprintf($_lang['lock_msg'], $row['username'], ' template variable');
			$e->setError(5, $msg);
			$e->dumpError();
		}
	}
}
// end check for lock

// make sure the id's a number
if(!is_numeric($id))
{
    echo 'Passed ID is NaN!';
    exit;
}

global $content;
$content = array();
if(isset($_GET['id']))
{
	$rs = $modx->db->select('*','[+prefix+]site_tmplvars',"id={$id}");
	$total = $modx->db->getRecordCount($rs);
	if($total>1)
	{
		echo 'Oops, Multiple variables sharing same unique id. Not good.';
		exit;
	}
	if($total<1)
	{
		header("Location: /index.php?id={$site_start}");
	}
	$content = $modx->db->getRow($rs);
	$_SESSION['itemname'] = $content['caption'];
}
else
{
	$_SESSION['itemname']="New Template Variable";
}

$form_v = $modx->manager->loadFormValues();
if($form_v) $content = array_merge($content, $form_v);

// get available RichText Editors
$RTEditors = '';
$evtOut = $modx->invokeEvent('OnRichTextEditorRegister',array('forfrontend' => 1));
if(is_array($evtOut)) $RTEditors = implode(',',$evtOut);

if($content['locked']==='1')
	$readonly = 'readonly';
else $readonly = '';

$form_elements = '<textarea name="elements" maxlength="65535" style="width:400px;height:110px;" class="inputBox phptextarea">' . htmlspecialchars($content['elements']) . "</textarea>\n";

$tooltip_tpl = '<img src="[+src+]" title="[+title+]" alt="[+alt+]" class="tooltip" onclick="alert(this.alt);" style="cursor:help" />';
$ph=array();
$ph['src']   = $_style['icons_tooltip_over'];
$ph['title'] = $_lang['tmplvars_input_option_msg'];
$ph['alt']   = $_lang['tmplvars_input_option_msg'];
$tooltip_input_option = $modx->parseText($tooltip_tpl,$ph);

?>
<script language="JavaScript">
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
	var itype = $j('#type');
	itype.change(function(){
		switch(itype.val())
		{
			case 'dropdown':
			case 'listbox':
			case 'listbox-multiple':
			case 'checkbox':
			case 'option':
			case 'custom_tv':
				$j('#inputoption').fadeIn();
				var ctv = '<textarea name="[+name+]">[+value+]</textarea>';
				if(itype.val()=='custom_tv')
				{
					$j('#inputoption th:first').css('visibility','hidden');
					if($j('#inputoption textarea').val()=='') $j('#inputoption textarea').val(ctv);
				}
				else
				{
					$j('#inputoption th:first').css('visibility','visible');
					if($j('#inputoption textarea').val()==ctv) $j('#inputoption textarea').val('');
				}
				break;
			default:
				$j('#inputoption').fadeOut();
		}
	});
});

function duplicaterecord(){
    if(confirm("<?php echo $_lang['confirm_duplicate_record'] ?>")==true) {
        documentDirty=false;
        document.location.href="index.php?id=<?php echo $_REQUEST['id']; ?>&a=304";
    }
}

function deletedocument() {
    if(confirm("<?php echo $_lang['confirm_delete_tmplvars']; ?>")==true) {
        documentDirty=false;
        document.location.href="index.php?id=" + document.mutate.id.value + "&a=303";
    }
}

// Widget Parameters
var widgetParams = {};          // name = description;datatype;default or list values - datatype: int, string, list : separated by comma (,)
    widgetParams['date']        = '&dateformat=Date Format;string;%Y年%m月%d日 &default=If no value, use current date;list;Yes,No;No';
    widgetParams['string']      = '&stringformat=String Format;list;Zen-Han,Han-Zen,Upper Case,Lower Case,Sentence Case,Capitalize,nl2br,Number Format,HtmlSpecialChars,HtmlEntities';
    widgetParams['delim']       = '&delim=Delimiter;string;,';
    widgetParams['hyperlink']   = '&text=Display Text;string; &title=Title;string; &linkclass=Class;string &linkstyle=Style;string &target=Target;string &linkattrib=Attributes;string';
    widgetParams['htmltag']     = '&tagoutput=Content;textarea;[+value+] &tagname=Tag Name;string;div &tagid=Tag ID;string &tagclass=Class;string &tagstyle=Style;string &tagattrib=Attributes;string';
    widgetParams['datagrid']    = '&cdelim=Column Delimiter;list;%2C,tab,||,:: &cwrap=Column Wrapper;string;" &enc=Src Encode;list;utf-8,sjis-win,sjis,eucjp-win,euc-jp,jis,auto &detecthead=Detect Header;list;first line,none;first line &cols=Column Names;string &cwidth=Column Widths;string &calign=Column Alignments;string &ccolor=Column Colors;string &ctype=Column Types;string &cpad=Cell Padding;string; &cspace=Cell Spacing;string; &psize=Page Size;int;100 &ploc=Pager Location;list;top-right,top-left,bottom-left,bottom-right,both-right,both-left; &pclass=Pager Class;string &pstyle=Pager Style;string &head=Header Text;string &foot=Footer Text;string &tblc=Grid Class;string &tbls=Grid Style;string &itmc=Item Class;string; &itms=Item Style;string &aitmc=Alt Item Class;string &aitms=Alt Item Style;string &chdrc=Column Header Class;string &chdrs=Column Header Style;string;&egmsg=Empty message;string;No records found;';
    widgetParams['richtext']    = '&w=Width;string;100% &h=Height;string;300px &edt=Editor;list;<?php echo $RTEditors; ?>';
    widgetParams['image']       = '&imgoutput=Src;textarea;[+value+] &alttext=Alternate Text;string &align=Align;list;none,baseline,top,middle,bottom,texttop,absmiddle,absbottom,left,right &name=Name;string &imgclass=Class;string &id=ID;string &imgstyle=Style;string &imgattrib=Other Attribs;string';
    widgetParams['custom_widget']       = '&output=Output;textarea;[+value+]';

// Current Params
var currentParams = {};
var lastdf, lastmod = {};

function showParameters(ctrl) {
    var c,p,df,cp;
    var ar,desc,value,key,dt;

    currentParams = {}; // reset;

    if (ctrl) {
    	f = ctrl.form;
    } else {
        f= document.forms['mutate'];
    	if(!f) return;
    	ctrl = f.display;
    }
    cp = f.params.value.split("&"); // load current setting once

    // get display format
    df = lastdf = ctrl.options[ctrl.selectedIndex].value;

    // load last modified param values
    if (lastmod[df]) cp = lastmod[df].split("&");
    for(p = 0; p < cp.length; p++) {
        cp[p]=(cp[p]+'').replace(/^\s|\s$/,""); // trim
        ar = cp[p].split("=");
        currentParams[ar[0]]=ar[1];
    }

    // setup parameters
    tr = (document.getElementById) ? document.getElementById('displayparamrow'):document.all['displayparamrow'];
    dp = (widgetParams[df]) ? widgetParams[df].split("&"):"";
    if(!dp) tr.style.display='none';
    else {
        t='<table width="400" style="margin-bottom:3px;background-color:#EEEEEE" cellpadding="2" cellspacing="1"><thead><tr><td width="50%"><?php echo $_lang['parameter']; ?></td><td width="50%"><?php echo $_lang['value']; ?></td></tr></thead>';
        for(p = 0; p < dp.length; p++) {
            dp[p]=(dp[p]+'').replace(/^\s|\s$/,""); // trim
            ar = dp[p].split("=");
            key = ar[0];     // param
            ar = (ar[1]+'').split(";");
            desc = ar[0];   // description
            dt = ar[1];     // data type
            value = decode((currentParams[key]) ? currentParams[key]:(dt=='list') ? ar[3] : (ar[2])? ar[2]:'');
            if (value!=currentParams[key]) currentParams[key] = value;
            value = (value+'').replace(/^\s|\s$/,""); // trim
	    value = value.replace(/&/g,"&amp;"); // replace & with &quot;
	    value = value.replace(/\"/g,"&quot;"); // replace double quotes with &quot;
            if (dt) {
                switch(dt) {
                    case 'int':
                    case 'float':
                        c = '<input type="text" name="prop_'+key+'" value="'+value+'" size="30" onchange="setParameter(\''+key+'\',\''+dt+'\',this)" />';
                        break;
                    case 'list':
                        c = '<select name="prop_'+key+'" height="1" style="width:168px" onchange="setParameter(\''+key+'\',\''+dt+'\',this)">';
                        ls = (ar[2]+'').split(",");
                        if(!currentParams[key]||currentParams[key]=='undefined') {
                            currentParams[key] = ls[0]; // use first list item as default
                        }
                        for(i=0;i<ls.length;i++){
                            c += '<option value="'+decode(ls[i])+'"'+((decode(ls[i])==value)? ' selected="selected"':'')+'>'+decode(ls[i])+'</option>';
                        }
                        c += '</select>';
                        break;
                    case 'textarea':
                        c = '<textarea class="inputBox phptextarea" name="prop_'+key+'" cols="25" style="width:320px;" onchange="setParameter(\''+key+'\',\''+dt+'\',this)" >'+value+'</textarea>';
                        break;
                    default:  // string
                        c = '<input type="text" name="prop_'+key+'" value="'+value+'" size="30" onchange="setParameter(\''+key+'\',\''+dt+'\',this)" />';
                        break;

                }
                t +='<tr><td bgcolor="#FFFFFF" width="50%">'+desc+'</td><td bgcolor="#FFFFFF" width="50%">'+c+'</td></tr>';
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
        case 'float':
            ctrl.value = parseFloat(ctrl.value);
            if(isNaN(ctrl.value)) ctrl.value = 0;
            v = ctrl.value;
            break;
        case 'list':
            v = ctrl.options[ctrl.selectedIndex].value;
            break;
        case 'textarea':
            v = ctrl.value+'';
            break;
        default:
            v = ctrl.value+'';
            break;
    }
    currentParams[key] = v;
    implodeParameters();
}

function resetParameters() {
    document.mutate.params.value = "";
    lastmod[lastdf]="";
    showParameters();
}
// implode parameters
function implodeParameters(){
    var v, p, s='';
    for(p in currentParams){
        v = currentParams[p];
        if(v) s += '&'+p+'='+ encode(v);
    }
    document.forms['mutate'].params.value = s;
    if (lastdf) lastmod[lastdf] = s;
}

function encode(s){
    s=s+'';
    s = s.replace(/\;/g,'%3B'); // ;
    s = s.replace(/\=/g,'%3D'); // =
    s = s.replace(/\&/g,'%26'); // &
    s = s.replace(/\,/g,'%2C'); // ,
    s = s.replace(/\\/g,'%5C'); // \
    
    return s;
}

function decode(s){
    s=s+'';
    s = s.replace(/\%3B/g,';'); // =
    s = s.replace(/\%3D/g,'='); // =
    s = s.replace(/\%26/g,'&'); // &
    s = s.replace(/\%2C/g,','); // ,
    s = s.replace(/\%5C/g,'\\'); // \
    
    return s;
}

</script>

<form name="mutate" method="post" action="index.php" enctype="multipart/form-data">
<?php
    // invoke OnTVFormPrerender event
    $evtOut = $modx->invokeEvent('OnTVFormPrerender',array('id' => $id));
    if(is_array($evtOut)) echo implode("",$evtOut);
?>
<input type="hidden" name="id" value="<?php echo $content['id'];?>">
<input type="hidden" name="a" value="302">
<input type="hidden" name="mode" value="<?php echo $_GET['a'];?>">
<input type="hidden" name="params" value="<?php echo htmlspecialchars($content['display_params']);?>">

	<h1><?php echo $_lang['tmplvars_title'];if($id) echo "(ID:{$id})"; ?></h1>

    <div id="actions">
    	  <ul class="actionButtons">
<?php if($modx->hasPermission('save_template')):?>
    		  <li id="Button1">
    			<a href="#" onclick="documentDirty=false; document.mutate.save.click();saveWait('mutate');">
    			  <img src="<?php echo $_style["icons_save"]?>" /> <?php echo $_lang['update']?>
    			</a><span class="and"> + </span>
    			<select id="stay" name="stay">
    			  <option id="stay1" value="1" <?php echo $_REQUEST['stay']=='1' ? ' selected=""' : ''?> ><?php echo $_lang['stay_new']?></option>
    			  <option id="stay2" value="2" <?php echo $_REQUEST['stay']=='2' ? ' selected="selected"' : ''?> ><?php echo $_lang['stay']?></option>
    			  <option id="stay3" value=""  <?php echo $_REQUEST['stay']=='' ? ' selected=""' : ''?>  ><?php echo $_lang['close']?></option>
    			</select>
    		  </li>
<?php endif; ?>
<?php
    if ($_GET['a'] == '301')
    {
    	$params = array('onclick'=>'duplicaterecord();','icon'=>$_style['icons_resource_duplicate'],'label'=>$_lang['duplicate']);
    	if($modx->hasPermission('new_template'))
    		echo $modx->manager->ab($params);
    	$params = array('onclick'=>'deletedocument();','icon'=>$_style['icons_delete_document'],'label'=>$_lang['delete']);
    	if($modx->hasPermission('delete_template'))
    		echo $modx->manager->ab($params);
    }
	$params = array('onclick'=>"document.location.href='index.php?a=76';",'icon'=>$_style['icons_cancel'],'label'=>$_lang['cancel']);
	echo $modx->manager->ab($params);
?>
    	  </ul>
    </div>

<div class="sectionBody">
<div class="tab-pane" id="tmplvarsPane">
	<script type="text/javascript">
		tpTmplvars = new WebFXTabPane( document.getElementById( "tmplvarsPane" ), false );
	</script>
	<div class="tab-page" id="tabGeneral">
	<h2 class="tab"><?php echo $_lang['settings_general'];?></h2>
	<script type="text/javascript">tpTmplvars.addTabPage( document.getElementById( "tabGeneral" ) );</script>
<p><?php echo $_lang['tmplvars_msg']; ?></p>
<table>
  <tr>
    <th align="left"><?php echo $_lang['tmplvars_name']; ?></th>
    <td align="left"><span style="font-family:'Courier New', Courier, mono">[*</span><input name="name" type="text" maxlength="50" value="<?php echo htmlspecialchars($content['name']);?>" class="inputBox" style="width:300px;"><span style="font-family:'Courier New', Courier, mono">*]</span> <span class="warning" id="savingMessage">&nbsp;</span></td>
  </tr>
  <tr>
    <th align="left"><?php echo $_lang['tmplvars_caption']; ?></th>
    <td align="left"><input name="caption" type="text" maxlength="80" value="<?php echo htmlspecialchars($content['caption']);?>" class="inputBox" style="width:300px;"></td>
  </tr>

  <tr>
    <th align="left"><?php echo $_lang['tmplvars_type']; ?></th>
    <td align="left">
    <select id="type" name="type" size="1" class="inputBox" style="width:300px;">
<?php
	$option = array();
	$option['custom_tv']    = 'Custom Form';
	$option['text']         = 'Text';
	$option['textarea']     = 'Textarea';
	$option['textareamini'] = 'Textarea (Mini)';
	$option['richtext']     = 'RichText';
	$option['dropdown']     = 'DropDown List Menu';
	$option['listbox']      = 'Listbox (Single-Select)';
	$option['listbox-multiple'] = 'Listbox (Multi-Select)';
	$option['option']       = 'Radio Options';
	$option['checkbox']     = 'Check Box';
	$option['image']        = 'Image';
	$option['file']         = 'File';
	$option['url']          = 'URL';
	$option['email']        = 'Email';
	$option['number']       = 'Number';
	$option['date']         = 'DateTime';
	$option['dateonly']     = 'DateOnly';
	$option['hidden']       = 'Hidden';
	$result = $modx->db->select('name','[+prefix+]site_snippets',"name like'input:%'");
	if(0 < $modx->db->getRecordCount($result))
	{
		while($row = $modx->db->getRow($result))
		{
			$input_name = trim(substr($row['name'],6));
			$option[$input_name] = $input_name;
		}
	}
	if($content['type']=='') $content['type']=='text';
	foreach($option as $k=>$v)
	{
		$selected = '';
		if(empty($content['type'])) $content['type'] = 'text';
		if(strtolower($content['type'])==strtolower($k)) $selected = 'selected="selected"';
		$row[$k] = '<option value="' . $k . '" ' . $selected . '>' . $v . '</option>';
	}
	echo join("\n",$row);
?>
	        </select>
    </td>
  </tr>
<?php
switch($content['type'])
{
	case 'dropdown':
	case 'listbox':
	case 'listbox-multiple':
	case 'checkbox':
	case 'option':
	case 'custom_tv':
		$display = '';
		break;
	default: $display = 'style="display:none;"';
}
?>
  <tr id="inputoption" <?php echo $display;?>>
	<th align="left" valign="top"><?php echo $_lang['tmplvars_elements']; ?></th>
	<td align="left" nowrap="nowrap"><?php echo $form_elements . $tooltip_input_option;?></td>
  </tr>
  <tr>
    <th align="left" valign="top"><?php echo $_lang['tmplvars_default']; ?></th>
    <td align="left" nowrap="nowrap"><textarea <?php echo $readonly;?> name="default_text" type="text" class="inputBox phptextarea" rows="5" style="width:400px;"><?php echo htmlspecialchars($content['default_text']);?></textarea><?php echo $tooltip_tv_binding;?></td>
  </tr>
  <tr>
<?php
function selected($target='')
{
	global $content;
	return ($content['display'] === $target) ? 'selected="selected"' : '';
}
?>
    <th align="left"><?php echo $_lang['tmplvars_widget']; ?></th>
    <td align="left">
        <select name="display" size="1" class="inputBox" style="width:400px;" onchange="showParameters(this);">
            <option value="" <?php echo selected(); ?>>&nbsp;</option>
            <option value="custom_widget" <?php echo selected('custom_widget'); ?>>Custom Processor</option>
            <option value="image" <?php        echo selected('image'); ?>>Image</option>
            <option value="hyperlink" <?php    echo selected('hyperlink'); ?>>Hyperlink</option>
            <option value="htmltag" <?php      echo selected('htmltag'); ?>>HTML Generic Tag</option>
            <option value="string" <?php       echo selected('string'); ?>>String Formatter</option>
            <option value="date" <?php         echo selected('date'); ?>>Date Formatter</option>
            <option value="unixtime" <?php     echo selected('unixtime'); ?>>Unixtime</option>
            <option value="delim" <?php        echo selected('delim'); ?>>Delimited List</option>
            <option value="datagrid" <?php      echo selected('datagrid'); ?>>Data Grid</option>
	        </select>
    </td>
  </tr>
  <tr id="displayparamrow">
    <td valign="top" align="left"><?php echo $_lang['tmplvars_widget_prop']; ?><div style="padding-top:8px;"><a href="javascript://" onclick="resetParameters(); return false"><img src="<?php echo $_style['icons_refresh']; ?>" alt="<?php echo $_lang['tmplvars_reset_params']; ?>"></a></div></td>
    <td align="left" id="displayparams">&nbsp;</td>
  </tr>
</table>
    	</div>

<!-- Template Permission -->
<div class="tab-page" id="tabPerm">
<h2 class="tab"><?php echo $_lang['tmplvar_tmpl_access'];?></h2>
<script type="text/javascript">tpTmplvars.addTabPage( document.getElementById( "tabPerm" ) );</script>
	<p><?php echo $_lang['tmplvar_tmpl_access_msg']; ?></p>
	<style type="text/css">
		label {display:block;}
	</style>
<table width="100%" cellspacing="0" cellpadding="0">
	<?php
	    $from  = '[+prefix+]site_templates as tpl';
		$from .= " LEFT JOIN [+prefix+]site_tmplvar_templates as stt ON stt.templateid=tpl.id AND stt.tmplvarid='{$id}'";
	    $rs = $modx->db->select('id,templatename,tmplvarid',$from);
?>
  <tr>
    <td>
<?php
	if(0<$modx->db->getRecordCount($rs)):
	    while ($row = $modx->db->getRow($rs)):
	    	if($_REQUEST['a']=='300' && $modx->config['default_template']==$row['id'])
	    		$checked = true;
	    	elseif(isset($_GET['tpl']) && $_GET['tpl'] == $row['id'])
	    		$checked = true;
	    	elseif($id == 0 && is_array($_POST['template']))
	    		$checked = in_array($row['id'], $_POST['template']);
	    	else
	    		$checked = $row['tmplvarid'];
	    	
	    	$ph['checked']      = $checked ? 'checked':'';
	    	$ph['id']           = $row['id'];
	    	$ph['templatename'] = $row['templatename'];
	    	echo $modx->parseText('<label><input type="checkbox" name="template[]" value="[+id+]" [+checked+] />[+templatename+]</label>',$ph);
	    endwhile;
	endif;
?>
    </td>
  </tr>
</table>
</div>

<div class="tab-page" id="tabInfo">
<h2 class="tab"><?php echo $_lang['settings_properties'];?></h2>
<script type="text/javascript">tpTmplvars.addTabPage( document.getElementById( "tabInfo" ) );</script>
      <table>
      <tr>
        <th align="left"><?php echo $_lang['existing_category']; ?></th>
        <td align="left">
        <select name="categoryid" style="width:300px;">
        <option value="0"><?php echo $_lang["no_category"]; ?></option>
            <?php
                $ds = $modx->manager->getCategories();
                if($ds) foreach($ds as $n=>$v)
                {
                    echo "<option value='".$v['id']."'".($content["category"]==$v["id"]? " selected='selected'":"").">".htmlspecialchars($v["category"])."</option>";
                }
            ?>
            <option value="-1">&gt;&gt; <?php echo $_lang["new_category"]; ?></option>
            </select>
        </td>
      </tr>
      <tr id="newcategry" style="display:none;">
        <th align="left" valign="top" style="padding-top:5px;"><?php echo $_lang['new_category']; ?></th>
        <td align="left" valign="top" style="padding-top:5px;"><input name="newcategory" type="text" maxlength="45" value="" class="inputBox" style="width:300px;"></td>
      </tr>
	  <tr>
	    <th align="left"><?php echo $_lang['tmplvars_description']; ?></th>
	    <td align="left"><textarea name="description" style="padding:0;height:4em;"><?php echo htmlspecialchars($content['description']);?></textarea></td>
	  </tr>
<?php if($modx->hasPermission('save_template')==1) {?>
	  <tr>
	    <td align="left" colspan="2"><label><input name="locked" value="on" type="checkbox" <?php echo $content['locked']==1 ? "checked='checked'" : "" ;?> class="inputBox" /> <b><?php echo $_lang['lock_tmplvars']; ?></b> <span class="comment"><?php echo $_lang['lock_tmplvars_msg']; ?></span></label></td>
	  </tr>
<?php } ?>
	  <tr>
	    <th align="left"><?php echo $_lang['tmplvars_rank']; ?></th>
	    <td align="left"><input name="rank" type="text" maxlength="4" value="<?php echo (isset($content['rank'])) ? $content['rank'] : 0;?>" class="inputBox" style="width:300px;"></td>
	  </tr>
      </table>
</div>

<!-- Access Permissions -->
<?php
	if($modx->config['use_udperms']==1)
	{
		$groupsarray = array();
		
		// fetch permissions for the variable
		$rs = $modx->db->select('documentgroup','[+prefix+]site_tmplvar_access',"tmplvarid='{$id}'");
		while($row = $modx->db->getRow($rs))
		{
			$groupsarray[] = $row['documentgroup'];
		}
		if($modx->hasPermission('access_permissions'))
		{
?>
<div class="tab-page" id="tabAccess">
<h2 class="tab"><?php echo $_lang['access_permissions'];?></h2>
<script type="text/javascript">tpTmplvars.addTabPage( document.getElementById( "tabAccess" ) );</script>
<script type="text/javascript">
    function makePublic(b){
        var notPublic=false;
        var f=document.forms['mutate'];
        var chkpub = f['chkalldocs'];
        var chks = f['docgroups[]'];
        if(!chks && chkpub) {
            chkpub.checked=true;
            return false;
        }
        else if (!b && chkpub) {
            if(!chks.length) notPublic=chks.checked;
            else for(i=0;i<chks.length;i++) if(chks[i].checked) notPublic=true;
            chkpub.checked=!notPublic;
        }
        else {
            if(!chks.length) chks.checked = (b)? false:chks.checked;
            else for(i=0;i<chks.length;i++) if (b) chks[i].checked=false;
            chkpub.checked=true;
        }
    }
</script>
<p><?php echo $_lang['tmplvar_access_msg']; ?></p>
<?php
		}
		$chk ='';
		$rs = $modx->db->select('name, id','[+prefix+]documentgroup_names');
		if(empty($groupsarray) && is_array($_POST['docgroups']) && empty($_POST['id']))
		{
			$groupsarray = $_POST['docgroups'];
		}
		$number_of_g = 0;
		while($row=$modx->db->getRow($rs))
		{
		    $checked = in_array($row['id'], $groupsarray);
		    if($modx->hasPermission('access_permissions'))
		    {
		        if($checked) $notPublic = true;
		        $chks .= '<label><input type="checkbox" name="docgroups[]" value="'.$row['id'] . '"' . ($checked ? ' checked="checked"' : '') . ' onclick="makePublic(false)" />' . $row['name'] . '</label>';
		        $number_of_g++;
		    }
		    elseif($checked)
		    {
		        echo '<input type="hidden" name="docgroups[]"  value="' .$row['id'] . '" />';
		    }
		}
		if($modx->hasPermission('access_permissions'))
		{
			$disabled = ($number_of_g === 0) ? 'disabled="disabled"' : '';
		    $chks = '<label><input type="checkbox" name="chkalldocs" ' . (!$notPublic ? "checked='checked'" : '') . ' onclick="makePublic(true)" ' . $disabled . ' /><span class="warning">' . $_lang['all_doc_groups'] . '</span></label>'.$chks;
		}
		echo $chks;
?>
</div>
<?php
	}
?>
<?php
    // invoke OnTVFormRender event
    $evtOut = $modx->invokeEvent('OnTVFormRender',array('id' => $id));
    if(is_array($evtOut)) echo implode('',$evtOut);
?>
</div>
</div>
<input type="submit" name="save" style="display:none">
</form>
<script type="text/javascript">setTimeout('showParameters()',10);</script>
