<?php
$tvName = '';
$locked = FALSE;
$access = FALSE;
$save   = 0;

// Get save status
if (isset($_POST['save'])) $save = intval($_POST['save']);

// Get TV name
$tvName = strip_tags($_GET['tvname']);

// Get TV array
$tv = $this->modx->getTemplateVar($tvName, '*', $docID);
$field_name = isset($tv['id']) ? $tv['id'] : $tv['name'];

// Handle default TVs
switch ($tvName)
{
	case 'pagetitle'   : $tv['type'] = 'text';     $tv['caption'] = $this->getDefaultTvCaption($tvName); $access = TRUE; break;
	case 'longtitle'   : $tv['type'] = 'text';     $tv['caption'] = $this->getDefaultTvCaption($tvName); $access = TRUE; break;
	case 'description' : $tv['type'] = 'textarea'; $tv['caption'] = $this->getDefaultTvCaption($tvName); $access = TRUE; break;
	case 'content'     : $tv['type'] = getTvType();$tv['caption'] = $this->getDefaultTvCaption($tvName); $access = TRUE; break;
	case 'menutitle'   : $tv['type'] = 'text';     $tv['caption'] = $this->getDefaultTvCaption($tvName); $access = TRUE; break;
	case 'introtext'   : $tv['type'] = 'textarea'; $tv['caption'] = $this->getDefaultTvCaption($tvName); $access = TRUE; break;
}

// Check TV access
if (!$access) { $access = $this->checkTvAccess($tv['id']);}

if (!$access) return 'Error: Access denied.';



// User can access TV
// Show TV form
if ($save == 0)
{
	// Check is document locked? Someone else is editing the document...  //$_lang['lock_msg']
	if ($this->checkLocked()) $locked = TRUE;
	else                      $this->setLocked(1); // Set document locked
	
	// Handle RTE
	if($tv['type'] == 'richtext')
	{
		// Invoke OnRichTextEditorInit event
		$tmp = array('editor'=>$this->modx->config['which_editor'], 'elements'=>array('tv'.$tvName));
		$eventOutput = $this->modx->invokeEvent("OnRichTextEditorInit", $tmp);
		if(is_array($eventOutput))
		{
			$editorHtml = implode("",$eventOutput);
		}
	}
	// Render TV html
	$tvHtml = $this->modx->renderFormElement($tv['type'], addslashes($field_name), $tv['default_text'], $tv['elements'], $tv['value']);
}
else // Save TV
{
	$this->setLocked(0); // Remove document locked
	
	// Save TV
	$this->saveTv($tvName);
}

// Page output: header
$theme = $this->modx->config['manager_theme'];
$output = <<< EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title></title>
<link rel="stylesheet" type="text/css" href="{$this->modx->config['site_url']}manager/media/style/{$theme}/style.css" />
<link rel="stylesheet" type="text/css" href="{$this->modx->config['site_url']}assets/plugins/qm/css/style.css" />
<script src="{$this->modx->config['site_url']}{$this->jqpath}" type="text/javascript"></script>
</head>
EOT;
// Page output: TV form
if ($save == 0)
{
	$output .= '<body id="qm-tv-body">';
	
	// Document is locked message
	if ($locked)
	{
		$output .= '
		<h1>'.$_lang['locked'].'</h1>
		<div id="qm-tv-description">'.$_lang['lock_msg'].'</div>
		';
	}
	else
	{ // Normal form
		// Image preview
		if ($tv['type'] == 'image')
		{
			$imagePreview = $this->get_img_prev_src();
			$imagePreview = str_replace('[+site_url+]',$this->modx->config['site_url'],$imagePreview);
			$imagePreview = str_replace('[+tv_value+]',$tv['value'],$imagePreview);
			$imagePreview = str_replace('[+tv_name+]',$tvName,$imagePreview);
		}
		else $imagePreview = '';
		$output .= '
<form id="qm-tv-form" name="mutate" method="post" enctype="multipart/form-data" action="'.$this->modx->config['site_url'].'index.php?id='.$docID.'&amp;quickmanagertv=1&amp;tvname='.$tvName.'">
<input type="hidden" name="tvid" value="'.$tv['id'].'" />
<input id="save" type="hidden" name="save" value="1" />

<div id="actions">
	<ul class="actionButtons">
	<li><a href="#" onclick="document.forms[\'mutate\'].submit(); return false;" class="primary"><span>'.$_lang['save'].'</span></a></li>
	<li><a href="#" onclick="parent.jQuery.fn.colorbox.close(); return false;"><span>'.$_lang['cancel'].'</span></a></li>
	</ul>
</div>

<h1>'.$tv['caption'].'</h1>

<div id="qm-tv-description">'.$tv['description'].'</div>

<div class="section">
<div id="qm-tv-tv" class="sectionBody qm-tv-'.$tv['type'].'">
'.$tvHtml.'
</div></div>

'.$imagePreview.'

</form>
'.$editorHtml.'
';
	}
}
// Page output: close modal box and refresh parent frame
else $output .= '<body onload="parent.location.reload();">';

// Page output: footer
$output .= '
</body>
</html>
';

return $output;



function getTvType() {
	global $modx;
	
	if($modx->config['use_editor']==0 || $modx->documentObject['richtext']==0)
		return 'textarea';
	else return 'richtext';
}