<?php
$installmode = get_installmode();
setOption('installmode', $installmode);

$ph['installmode']   = $installmode;
$ph['installImg']    = ($installmode==0) ? 'install_new.png'                       : 'install_upg.png';
$ph['welcome_title'] = ($installmode==0) ? $_lang['welcome_message_welcome']       : $_lang['welcome_message_upd_welcome'];
$ph['welcome_text']  = ($installmode==0) ? $_lang['welcome_message_text']          : $_lang['welcome_message_upd_text'];
$ph['installTitle']  = ($installmode==0) ? $_lang['installation_new_installation'] : $_lang['installation_upgrade_existing'];
$ph['installNote']   = ($installmode==0) ? $_lang['installation_install_new_note'] : $_lang['installation_upgrade_existing_note'];
$ph['btnback_value'] = $_lang['btnback_value'];
$ph['btnnext_value'] = $_lang['btnnext_value'];

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
	</div>
</div>
<p class="buttonlinks">
    <a href="javascript:void(0);" class="prev" title="[+btnback_value+]"><span>[+btnback_value+]</span></a>
    <a href="javascript:void(0);" class="next" title="[+btnnext_value+]"><span>[+btnnext_value+]</span></a>
</p>
</form>

<script type="text/javascript">
	var installmode = [+installmode+];
	\$('a.prev').click(function(){
		\$('form#install').attr({action:'index.php?action=language'});
		\$('#install').submit();
	});
	\$('a.next').click(function(){
		if(installmode==1) \$('form#install').attr({action:'index.php?action=options'});
		\$('#install').submit();
	});
</script>
EOT;
	return $tpl;
}
