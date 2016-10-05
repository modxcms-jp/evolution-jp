<?php
// Action 133
if(!isset($modx) || !$modx->isLoggedin()) exit;

if(!$modx->hasPermission('save_document')) {
	$e->setError(3);
	$e->dumpError();
}

if(isset($_POST['id']) && preg_match('@^[1-9][0-9]*$@',$_POST['id']))
	$docid = $_POST['id'];
elseif(isset($_GET['id']) && preg_match('@^[1-9][0-9]*$@',$_GET['id']))
	$docid = $_GET['id'];
else {
	$e->setError(2);
	$e->dumpError();
}

include_once(MODX_MANAGER_PATH . 'actions/document/mutate_content.functions.inc.php');

$ph['id'] = $docid;
$ph['style_icons_cancel'] = $_style['icons_cancel'];
$ph['lang_cancel']        = $_lang['cancel'];

$tpl = getTplDraft();
$ph['title'] = '下書きを採用'; // $_lang['draft_data_publishdate']
$ph['fieldDraftPub_date']  = fieldDraftPub_date($docid);
$ph['id'] = $docid;

echo $modx->parseTextSimple($tpl,$ph);



function fieldDraftPub_date($docid) {
	global $modx,$_lang,$_style;

	$pub_date = 0;
	if( !empty($docid) && ($docid = intval($docid)) != 0 ){
		//statusはdraft/standbyでも気にしない
        $rs = $modx->db->select('pub_date', '[+prefix+]site_revision', "element = 'resource' AND elmid='{$docid}'");
		if( ($row = $modx->db->getRow($rs)) && !empty($row['pub_date']) ){
			$pub_date = $modx->toDateFormat($row['pub_date']);
		}
	}
	if( empty($pub_date) ){
		$pub_date = $modx->toDateFormat(time());
	}

	$tpl[] = '<input type="text" id="pub_date" name="pub_date" class="DatePicker imeoff" value="[+pub_date+]" />';
	$tpl[] = '<a style="cursor:pointer; cursor:hand;">';
	$tpl[] = '<img src="[+icons_cal_nodate+]" alt="[+remove_date+]" /></a>';
	$tpl = implode("\n",$tpl);
	$ph['pub_date']         = $pub_date;
	$ph['icons_cal_nodate'] = $_style['icons_cal_nodate'];
	$ph['remove_date']      = $_lang['remove_date'];
	$ph['datetime_format']  = $modx->config['datetime_format'];
	$body = $modx->parseTextSimple($tpl,$ph);
	$body = renderTr($_lang['draft_data_publishdate'],$body);
	$tpl = <<< EOT
<tr>
	<td></td>
	<td style="line-height:1;margin:0;color: #555;font-size:10px">[+datetime_format+] HH:MM:SS</td>
</tr>
EOT;
	$body .= $modx->parseTextSimple($tpl,$ph);
	return $body;
}

function getTplDraft()
{
	$tpl = <<< EOT
<style>
	label {display:block;}
</style>
<script>
	jQuery(function(){
		jQuery("input[name='publishoption']").click(function(){
			var val = jQuery("input[name='publishoption']:checked").val();
			if(val=='now') {
				jQuery('#pubdate').fadeOut();
			}
			else jQuery('#pubdate').fadeIn();
    	});
	});
</script>
<form name="mutate" id="mutate" method="post" enctype="multipart/form-data" action="index.php" target="main" onsubmit="documentDirty=false;">
	<input type="hidden" name="a" value="129" />
	<input type="hidden" name="id" value="[+id+]" />
	<input type="hidden" name="token" value="[+token+]" />
	<h1>[+title+]</h1>
    <div id="actions">
    <ul class="actionButtons">
        <li class="mutate">
        <a href="javascript:void(0)" onclick="document.location.href='index.php?a=131&id=[+id+]'">
        <img src="[+style_icons_cancel+]" /> [+lang_cancel+]
        </a>
        </li>
    </ul>
    </div>
    <div class="section">
    <div class="sectionBody">
	<p class="okmsg">
	下書きを保存しました。
	</p>
    	<div style="margin-bottom:1em;">
    		<label><input name="publishoption" type="radio" value="now" checked /> 今すぐ採用する</label>
    		<label><input name="publishoption" type="radio" value="reserve" /> 採用日時を指定する</label>
    		<label style="color:#ccc;"><input name="publishoption" type="radio" value="approve" disabled /> 承認を申請する</label>
    	</div>
    	<div id="pubdate" style="display:none;">
        	<table width="99%" border="0" cellspacing="5" cellpadding="0">
        		[+fieldDraftPub_date+]
        	</table>
        </div>
	<ul class="actionButtons">
        <li class="primary">
        <a href="javascript:void(0)" onclick="document.mutate.submit();">
        採用する
        </a>
        </li>
	</ul>
    </div>
    </div>
</form>
EOT;
	return $tpl;
}

