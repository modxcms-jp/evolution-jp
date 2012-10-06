<?php
$install_language = autoDetectLang();
setOption('install_language', $install_language);

$ph['lang_options']        = get_lang_options($install_language);
$ph['_lang_begin']         = $_lang['begin'];
$ph['_lang_btnnext_value'] = $_lang['btnnext_value'];
echo  parse(get_src_content(),$ph);

function get_langs()
{
	$langs = array();
	foreach(glob('lang/*.inc.php') as $path)
	{
		$langs[] = substr($path,5,strpos($path,'.inc.php')-5);
	}
	sort($langs);
	return $langs;
}

function get_lang_options($install_language)
{
	$langs = get_langs();
	
	foreach ($langs as $language)
	{
		$abrv_language = explode('-',$language);
		$option[] = '<option value="' . $language . '"'. (($language == $install_language) ? ' selected="selected"' : null) .'>' . ucwords($abrv_language[0]). '</option>'."\n";
	}
	return join("\n",$option);
}

function get_src_content()
{
	$src = <<< EOT
<form id="install_form" action="index.php" method="POST">
<input type="hidden" name="action" value="mode" />
    <h2>Choose language:&nbsp;&nbsp;</h2>
    <select name="install_language">
    [+lang_options+]
    </select>
        <p class="buttonlinks">
            <a class="next" style="display:inline;" href="#" title="[+_lang_begin+]"><span>[+_lang_btnnext_value+]</span></a>
        </p>
</form>

<script type="text/javascript">
	$('a.next').click(function(){
		$('#install_form').submit();
	});
</script>
EOT;
	return $src;
}
