<?php
$upgradeable = get_upgradeable_status();
?>
<form id="install_form" action="index.php" method="POST">
<input type="hidden" name="action" value="connection" />
	<?php
		echo '	<h2>' . $_lang['welcome_message_welcome'] . '</h2>';
		echo '	<p>' . $_lang['welcome_message_text'] . ' ' . $_lang['welcome_message_start'] . '</p>';
	?>
	<h2 style="margin:1em 0"><?php echo $_lang['installation_mode']?></h2>
	<div>
		<div class="installImg"><img src="img/install_new.png" alt="new install" /></div>
		<div class="installDetails">
			<h3><label class="nofloat"><input type="radio" name="installmode" value="0" <?php echo !$upgradeable ? 'checked="checked"':'' ?> />
			<?php echo $_lang['installation_new_installation']?></label></h3>
			<p><?php echo $_lang['installation_install_new_copy'] . $moduleName?></p>
			<p><strong><?php echo $_lang['installation_install_new_note']?></strong></p>
		</div>
	</div>
	<div style="margin:0;padding:0;<?php if($upgradeable !== 1 && $upgradeable !== 2) echo 'display:none;'; ?>">
	<hr />
	<div>
		<div class="installImg"><img src="img/install_upg.png" alt="upgrade existing install" /></div>
		<div class="installDetails">
			<h3><label class="nofloat"><input type="radio" name="installmode" value="1" <?php echo $upgradeable !== 1 ? 'disabled="disabled"' : '' ?> <?php echo ($upgradeable === 1) ? 'checked="checked"':'' ?> />
			<?php echo $_lang['installation_upgrade_existing']?></label></h3>
			<p><?php echo $_lang['installation_upgrade_existing_note']?></p>
		</div>
	</div>
	</div>

    <p class="buttonlinks">
        <a href="#" class="prev" title="<?php echo $_lang['btnback_value']?>"><span><?php echo $_lang['btnback_value']?></span></a>
        <a href="#" class="next" title="<?php echo $_lang['btnnext_value']?>"><span><?php echo $_lang['btnnext_value']?></span></a>
    </p>
</form>

<script type="text/javascript">
	$('a.prev').click(function(){
		$('input[name="action"]').val('language');
		$('#install_form').submit();
	});
	$('a.next').click(function(){
		var target = $('input[name="installmode"]:checked').val()==1 ? 'options' : 'connection';
		$('input[name="action"]').val(target);
		$('#install_form').submit();
	});
</script>
