<?php
$install_language = autoDetectLang();
setOption('install_language', $install_language);

$ph['lang_options']        = get_lang_options($install_language);
$ph['_lang_begin']         = $_lang['begin'];
$ph['_lang_btnnext_value'] = $_lang['btnnext_value'];
echo  parse(getTpl(),$ph);

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

function getTpl()
{
	$tpl = 
<<< EOT
<form id="install" action="index.php?action=mode" method="POST">
    <h2>Choose language:</h2>
    <select name="install_language">
    [+lang_options+]
    </select>
        <p class="buttonlinks">
            <a class="next" style="display:inline;" href="javascript:void(0);" title="[+_lang_begin+]"><span>[+_lang_btnnext_value+]</span></a>
        </p>
</form>

<script type="text/javascript">
	\$('a.next').click(function(){
		\$('form#install').submit();
	});
</script>
EOT;
	return $tpl;
}
