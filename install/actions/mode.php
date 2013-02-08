<?php

$installdata = getOption('installdata');
$template    = getOption('template');
$tv          = getOption('tv');
$chunk       = getOption('chunk');
$snippet     = getOption('snippet');
$plugin      = getOption('plugin');
$module      = getOption('module');

$install_language = getOption('install_language', $install_language);
$installmode = get_installmode();
setOption('installmode', $installmode);
setOption('installdata', $installdata);
setOption('template'   , $template);
setOption('tv'         , $tv);
setOption('chunk'      , $chunk);
setOption('snippet'    , $snippet);
setOption('plugin'     , $plugin);
setOption('module'     ,$module);

$ph['installmode']   = $installmode;
$ph['installImg']    = ($installmode==0) ? 'install_new.png'                       : 'install_upg.png';
$ph['welcome_title'] = ($installmode==0) ? $_lang['welcome_message_welcome']       : $_lang['welcome_message_upd_welcome'];
$ph['welcome_text']  = ($installmode==0) ? $_lang['welcome_message_text']          : $_lang['welcome_message_upd_text'];
$ph['installTitle']  = ($installmode==0) ? $_lang['installation_new_installation'] : $_lang['installation_upgrade_existing'];
$ph['installNote']   = ($installmode==0) ? $_lang['installation_install_new_note'] : $_lang['installation_upgrade_existing_note'];
$ph['btnnext_value'] = $_lang['btnnext_value'];
$ph['lang_options']  = get_lang_options($install_language);

echo  parse(getTpl(),$ph);

function getTpl()
{
	$tpl = 
<<< EOT
<form id="install" action="index.php?action=connection" method="POST">
<h2>[+welcome_title+]</h2>
<p style="margin-bottom:3em;">[+welcome_text+]</p>
<div>
	<div class="installImg"><img src="img/[+installImg+]" alt="new install" /></div>
	<div class="installDetails">
		<h3>[+installTitle+]</h3>
		<p>[+installNote+]</p>
		<select name="install_language" style="margin-top:20px;">
		[+lang_options+]
		</select>
	</div>
</div>
<p class="buttonlinks">
    <a href="javascript:void(0);" class="next" title="[+btnnext_value+]"><span>[+btnnext_value+]</span></a>
</p>
</form>

<script type="text/javascript">
	var installmode = [+installmode+];
	\$('a.next').click(function(){
		if(installmode==1) \$('form#install').attr({action:'index.php?action=options'});
		\$('#install').submit();
	});
</script>
EOT;
	return $tpl;
}
