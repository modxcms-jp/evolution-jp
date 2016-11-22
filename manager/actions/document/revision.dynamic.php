<?php
// Action 127
if(!isset($modx) || !$modx->isLoggedin()) exit;

if(isset($_GET['id']) && preg_match('@^[1-9][0-9]*$@',$_GET['id']))
	$id = $_GET['id'];
else
{
	$e->setError(2);
	$e->dumpError();
}

$ph['id'] = $id;
$ph['style_icons_cancel']    = $_style["icons_cancel"];
$ph['lang_cancel']           = $_lang['cancel'];

switch($_GET['mode'])
{
	case 'deleted':
		$ph['title'] = '下書きを削除';
		$ph['msg'] = '下書きを削除しました。';
		break;
	case 'nodeleted':
		$ph['title'] = '下書きを削除';
		$ph['msg'] = '削除する下書きはありません。';
		break;
	case 'publish_draft':
		$ph['title'] = '下書きを採用';
		$ph['msg'] = '下書きを採用しました。';
		break;
}

$tpl = tpl();

echo $modx->parseText($tpl,$ph);

function tpl()
{
    $tpl = <<< TPL
<h1>[+title+]</h1>
<div id="actions">
<ul class="actionButtons">
    <li class="mutate">
    <a href="javascript:void(0)" onclick="document.location.href='index.php?a=2'">
    <img src="[+style_icons_cancel+]" /> [+lang_cancel+]
    </a>
    </li>
</ul>
</div>
<div class="section">
<div class="sectionBody">
<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<div class="msg">
[+msg+]
</div>
</div>
</div>
TPL;
    return $tpl;
}
