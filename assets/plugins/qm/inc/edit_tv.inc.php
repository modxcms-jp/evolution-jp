<?php

if (postv('save')) {
    $this->setLocked(0); // Remove document locked
    $this->saveTv(getv('tvname'));
    exit('<body onload="parent.location.reload();">');
}

global $tv;
$tv = $this->getField(getv('tvname'), $docID);

$locked = false;
$access = tv('access', $this->checkTvAccess(tv('id')));

if (!$access) {
    return 'Error: Access denied.';
}

if ($this->checkLocked()) {
    $locked = true;
} else {
    $this->setLocked(1);
}

if (tv('type') === 'richtext') {
    $tmp = array(
        'editor' => evo()->config['which_editor'],
        'elements' => array('tv' . getv('tvname'))
    );
    $eventOutput = evo()->invokeEvent('OnRichTextEditorInit', $tmp);
    if (is_array($eventOutput)) {
        $editorHtml = implode('', $eventOutput);
    }
}
$tvHtml = evo()->renderFormElement(
    tv('type'),
    getv('tvname'),
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
    array(
        'theme' => $theme,
        'site_url' => config('site_url'),
        'jquery_path' => $this->jqpath
    )
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
            array('[+site_url+]', '[+tv_value+]', '[+tv_name+]'),
            array(evo()->config['site_url'], tv('value'), getv('tvname')),
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
', array(
        'site_url' => evo()->config['site_url'],
        'docid' => $docID,
        'tvname' => getv('tvname'),
        'tvid' => tv('tvid', ''),
        'lang_save' => lang('save'),
        'lang_cancel' => lang('cancel'),
        'caption' => tv('caption'),
        'description' => tv('description', ''),
        'type' => tv('type'),
        'form' => $tvHtml,
        'img_preview' => $imagePreview,
        'editor_html' => isset($editorHtml) ? $editorHtml : ''
    ));
}

return $output;


function tv($key = null, $default = null)
{
    return array_get(globalv('tv'), $key, $default);
}
