<form id="install" action="[+site_url+]install/" method="POST">
	<input type="hidden" name="action" value="connection" />
	<input type="hidden" name="prev_action" value="mode" />
	<input type="hidden" name="is_upgradeable" value="[+is_upgradeable+]" />
	<h2>[+welcome_title+]</h2>
	<p style="margin-bottom:3em;">[+welcome_text+]</p>
	<div>
		<div class="installDetails">
			<h3>[+installTitle+]</h3>
			<div class="installImg"><img src="img/[+installImg+]" alt="new install" /></div>
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

<script>
	var is_upgradeable = '[+is_upgradeable+]';
	jQuery('a.next').click(function () {
		if (is_upgradeable == 1) {
			jQuery('#install input[name=action]').val('options');
		}
		jQuery('#install').submit();
	});
</script>