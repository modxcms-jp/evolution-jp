<?php

global $tv;
// 文書を保存できる人だけ（管理画面の保存処理と同じく、save_document と文書へのアクセス権を見る）
if (!evo()->hasPermission('save_document') || !$this->checkAccess()) {
    return 'Error: Access denied.';
}
// 編集できるのは、組み込みの項目とこの文書の TV だけ
$tvName = (string)getv('tvname');
$tv = $this->getField($tvName, $docID);
if (!$tv) {
    return 'Error: Unknown field.';
}

$locked = false;
$access = tv('access');
if ($access === null) {
    $access = $this->checkTvAccess(tv('tvid'));
}

if (!$access) {
    return 'Error: Access denied.';
}

if (postv('save')) {
    $this->setLocked(0); // Remove document locked
    $this->saveTv($tvName, tv('tvid', 0));
    exit('<body onload="parent.location.reload();">');
}

if ($this->checkLocked()) {
    $locked = true;
} else {
    $this->setLocked(1);
}

if (tv('type') === 'richtext') {
    $tmp = [
        'editor' => evo()->config['which_editor'],
        'elements' => ['tv' . $tvName]
    ];
    $eventOutput = evo()->invokeEvent('OnRichTextEditorInit', $tmp);
    if (is_array($eventOutput)) {
        $editorHtml = implode('', $eventOutput);
    }
}
$tvHtml = evo()->renderFormElement(
    tv('type'),
    $tvName,
    tv('default_text'),
    tv('elements'),
    tv('value')
);

$theme = evo()->config['manager_theme'];
$output = <<< EOT
<!DOCTYPE html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>qm edit</title>
<link rel="stylesheet" type="text/css" href="[+site_url+]manager/media/style/[+theme+]/style.css" />
<link rel="stylesheet" type="text/css" href="[+site_url+]assets/plugins/qm/css/style.css" />
<script src="[+site_url+][+jquery_path+]" type="text/javascript"></script>
</head>
EOT;
$output = parseText(
    $output,
    [
        'theme' => $theme,
        'site_url' => MODX_SITE_URL,
        'jquery_path' => $this->jqpath
    ]
);

$output .= '<body id="qm-tv-body">';

// Document is locked message
if ($locked) {
    $output .= '
	<h1>' . $_lang['locked'] . '</h1>
	<div id="qm-tv-description">' . $_lang['lock_msg'] . '</div>
	';
} else { // Normal form
    // Image preview
    if (tv('type') === 'image') {
        $imagePreview = $this->get_img_prev_src();
        $imagePreview = str_replace(
            ['[+site_url+]', '[+tv_value+]', '[+tv_name+]'],
            // tv_name is inside JS strings of the template
            [MODX_SITE_URL, hsc(tv('value')), substr(js_literal($tvName), 1, -1)],
            $imagePreview
        );
    } else $imagePreview = '';
    $output .= parseText('
<form
id="qm-tv-form"
name="mutate"
method="post"
enctype="multipart/form-data"
action="[+site_url+]index.php?id=[+docid+]&amp;quickmanagertv=1&amp;tvname=[+tvname+]"
>
<input type="hidden" name="tvid" value="[+tvid+]" />
<input id="save" type="hidden" name="save" value="1" />
<div id="actions">
<ul class="actionButtons">
<li><a
		href="#"
		onclick="document.forms[\'mutate\'].submit(); return false;"
		class="primary"
	><span>[+lang_save+]</span></a></li>
<li><a
		href="#"
		onclick="parent.$j.colorbox.close(); return false;"
	><span>[+lang_cancel+]</span></a></li>
</ul>
</div>
<h1>[+caption+]</h1>
<div id="qm-tv-description">[+description+]</div>
<div class="section">
<div id="qm-tv-tv" class="sectionBody qm-tv-[+type+]">
[+form+]
</div>
</div>
[+img_preview+]
</form>
[+editor_html+]
</body>
</html>
', [
        'site_url' => MODX_SITE_URL,
        'docid' => $docID,
        'tvname' => urlencode($tvName),
        'tvid' => (int)tv('tvid', 0) ?: '',
        'lang_save' => lang('save'),
        'lang_cancel' => lang('cancel'),
        'caption' => hsc(tv('caption')),
        'description' => hsc(tv('description', '')),
        'type' => hsc(tv('type')),
        'form' => $tvHtml,
        'img_preview' => $imagePreview,
        'editor_html' => isset($editorHtml) ? $editorHtml : ''
    ]);
}

return $output;


function tv($key = null, $default = null)
{
    return array_get(globalv('tv'), $key, $default);
}
