<?php
// Action 133
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$docid = post_intvar('id') ?: get_intvar('id');
if (!$docid) {
    alert()->setError(2);
    alert()->dumpError();
}

include_once(MODX_MANAGER_PATH . 'actions/document/mutate_content/functions.php');

$ph['id'] = $docid;
$ph['lang_cancel'] = lang('cancel');

$tpl = getTplDraft();
$ph['title'] = '下書きを採用'; // $_lang['draft_data_publishdate']
// 日付のエラーで差し戻されたときの入力（差し戻しの id が同じときだけ）
$restored = [];
if (manager()->hasFormValues()) {
    $restored = manager()->loadFormValues();
    if ((int)array_get($restored, 'id', 0) !== (int)$docid) {
        $restored = [];
    }
}
$reserve = array_get($restored, 'publishoption') === 'reserve';
$ph['checked_now'] = $reserve ? '' : ' checked';
$ph['checked_reserve'] = $reserve ? ' checked' : '';
$ph['pubdate_display'] = $reserve ? 'block' : 'none';
$ph['fieldDraftPub_date'] = fieldDraftPub_date($docid, $restored);
$ph['id'] = $docid;
$ph['token'] = $modx->genTokenString();
$_SESSION['token'] = $ph['token']; //todo:暫定対応、トークン処理はコアで統一して管理する

echo $modx->parseText($tpl, $ph);


function fieldDraftPub_date($docid, $restored = [])
{
    global $modx, $_style;

    //statusはdraft/standbyでも気にしない
    $rs = db()->select(
        'pub_date',
        '[+prefix+]site_revision',
        sprintf("(status='draft') AND element='resource' AND elmid='%s'", $docid),
        1
    );
    $pub_date = db()->getValue($rs);
    $tpl[] = '<input type="text" id="pub_date" name="pub_date" class="DatePicker imeoff" value="[+pub_date+]" />';
    $tpl[] = '<a style="cursor:pointer; cursor:hand;">';
    $tpl[] = '<img src="[+icons_cal_nodate+]" alt="[+remove_date+]" /></a>';
    $tpl = implode("\n", $tpl);
    // 日付のエラーで差し戻されたときは、入力した日付を欄に残す
    if (isset($restored['pub_date'])) {
        $ph['pub_date'] = manager()->dateInputValue(trim((string)$restored['pub_date']));
    } else {
        $ph['pub_date'] = $pub_date ? evo()->toDateFormat($pub_date) : evo()->toDateFormat(time());
    }
    $ph['icons_cal_nodate'] = $_style['icons_cal_nodate'];
    $ph['remove_date'] = lang('remove_date');
    $ph['datetime_format'] = config('datetime_format');
    $body = $modx->parseText($tpl, $ph);
    $body = renderTr(lang('draft_data_publishdate'), $body);
    $tpl = <<< EOT
<tr>
	<td></td>
	<td style="line-height:1;margin:0;color: #555;font-size:10px">[+datetime_format+] HH:MM:SS</td>
</tr>
EOT;
    $body .= $modx->parseText($tpl, $ph);
    return $body;
}

function getTplDraft()
{
    $tpl = <<< EOT
<style>
	.draft-publish-options label {display:block;}
</style>
<script>
	Evo.ready(function(){
		// 「今すぐ」以外を選んだときだけ日時の欄を出す
		document.querySelectorAll("input[name='publishoption']").forEach(function(radio){
			radio.addEventListener('click', function(){
				var checked = document.querySelector("input[name='publishoption']:checked");
				var pubdate = document.getElementById('pubdate');
				if (!pubdate) return;
				pubdate.style.display = (checked && checked.value=='now') ? 'none' : 'block';
			});
		});
	});
</script>
<form name="mutate" id="mutate" method="post" enctype="multipart/form-data" action="index.php" onsubmit="documentDirty=false;">
	<input type="hidden" name="a" value="129" />
	<input type="hidden" name="id" value="[+id+]" />
	<input type="hidden" name="token" value="[+token+]" />
	<h1>[+title+]</h1>
    <div id="actions">
    <ul class="actionButtons">
        <li class="mutate">
        <a href="javascript:void(0)" onclick="EvoShell.go('index.php?a=131&id=[+id+]');">
        [+lang_cancel+]
        </a>
        </li>
    </ul>
    </div>
    <div class="section">
    <div class="sectionBody">
	<p class="okmsg">
	下書きを保存しました。
	</p>
		<div class="draft-publish-options">
			<label><input name="publishoption" type="radio" value="now"[+checked_now+] /> 今すぐ採用する</label>
			<label><input name="publishoption" type="radio" value="reserve"[+checked_reserve+] /> 採用日時を指定する</label>
			<label class="label-disabled"><input name="publishoption" type="radio" value="approve" disabled /> 承認を申請する</label>
		</div>
		<div id="pubdate" style="display:[+pubdate_display+];">
			<table width="99%" border="0" cellspacing="5" cellpadding="0">
				[+fieldDraftPub_date+]
			</table>
		</div>
		<ul class="actionButtons">
			<li class="primary" id="save">
			<a href="javascript:void(0)" onclick="documentDirty=false;document.mutate.submit();">
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
