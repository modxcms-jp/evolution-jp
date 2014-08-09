<form id="install" action="index.php?action=connection" method="POST">
<input type="hidden" name="prev_action" value="mode" />
<input type="hidden" name="installmode" value="[+installmode+]" />
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
	jQuery('a.next').click(function(){
		if(installmode==1) jQuery('form#install').attr({action:'index.php?action=options'});
		jQuery('#install').submit();
	});
</script>
