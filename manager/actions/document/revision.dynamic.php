<?php
/**
 * @var array $_style
 * @var array $_lang
 */
// Action 127
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

$ph['id'] = $id;
$ph['lang_cancel'] = lang('cancel');

switch (get_choicevar('mode', ['deleted', 'nodeleted', 'publish_draft'], '')) {
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

echo $modx->parseText($tpl, $ph);

function tpl()
{
    $tpl = <<< TPL
<h1>[+title+]</h1>
<div id="actions">
<ul class="actionButtons">
    <li class="mutate">
    <a href="javascript:void(0)" onclick="EvoShell.go('index.php?a=2');">
    [+lang_cancel+]
    </a>
    </li>
</ul>
</div>
<div class="section">
<div class="sectionBody">
<div class="msg">
[+msg+]
</div>
</div>
</div>
TPL;
    return $tpl;
}
