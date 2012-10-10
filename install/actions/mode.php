<?php
$installmode = get_installmode();
setOption('installmode', $installmode);

$ph['installmode']   = $installmode;
$ph['installImg']    = installImg($installmode);
$ph['welcome_title'] = welcomeTitle($installmode);
$ph['welcome_text']  = welcomeText($installmode);
$ph['installTitle']  = installTitle($installmode);
$ph['installNote']   = installNote($installmode);
$ph['btnback_value'] = $_lang['btnback_value'];
$ph['btnnext_value'] = $_lang['btnnext_value'];

echo  parse(getTpl(),$ph);

function getTpl()
{
	$tpl = 
<<< EOT
<form id="install_form" action="index.php" method="POST">
<input type="hidden" name="action" value="connection" />
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
    <a href="#" class="prev" title="[+btnback_value+]"><span>[+btnback_value+]</span></a>
    <a href="#" class="next" title="[+btnnext_value+]"><span>[+btnnext_value+]</span></a>
</p>
</form>

<script type="text/javascript">
	var installmode = [+installmode+];
	\$('a.prev').click(function(){
		\$('input[name="action"]').val('language');
		\$('#install_form').submit();
	});
	\$('a.next').click(function(){
		var target = installmode==1 ? 'options' : 'connection';
		\$('input[name="action"]').val(target);
		\$('#install_form').submit();
	});
</script>
EOT;
	return $tpl;
}

function installImg($installmode)
{
	if($installmode==0) return 'install_new.png';
	else                return 'install_upg.png';
}

function installTitle($installmode)
{
	global $_lang;
	if($installmode==0) return $_lang['installation_new_installation'];
	else                return $_lang['installation_upgrade_existing'];
}

function installNote($installmode)
{
	global $_lang;
	if($installmode==0) return $_lang['installation_install_new_note'];
	else                return $_lang['installation_upgrade_existing_note'];
}

function welcomeTitle($installmode)
{
	global $_lang;
	if($installmode==0) return $_lang['welcome_message_welcome'];
	else                return $_lang['welcome_message_upd_welcome'];
}

function welcomeText($installmode)
{
	global $_lang;
	if($installmode==0) return $_lang['welcome_message_text'];
	else                return $_lang['welcome_message_upd_text'];
}
