<form id="install" action="index.php?action=summary" method="POST">
<input type="hidden" name="prev_action" value="options" />
<h2>[+optional_items+]</h2>
<p>[+optional_items_new_note+]</p>
[+install_sample_site+]
<hr />

[+object_list+]

<p class="buttonlinks">
    <a class="prev" href="javascript:void(0);" title="[+btnback_value+]"><span>[+btnback_value+]</span></a>
    <a class="next" href="javascript:void(0);" title="[+btnnext_value+]"><span>[+btnnext_value+]</span></a>
</p>

</form>
<script type="text/javascript">
	var installmode = [+installmode+];
	jQuery('a.prev').click(function(){
		var target = (installmode==1) ? 'mode' : 'connection';
		jQuery('#install').attr({action:'index.php?action='+target});
		jQuery('#install').submit();
	});
	jQuery('a.next').click(function(){
		jQuery('#install').submit();
	});
	
	jQuery('#toggle_check_all').click(function(evt){
	    evt.preventDefault();
	    jQuery('input:checkbox.toggle:not(:disabled)').attr('checked', true);
	});
	jQuery('#toggle_check_none').click(function(evt){
	    evt.preventDefault();
	    jQuery('input:checkbox.toggle:not(:disabled)').attr('checked', false);
	});
	jQuery('#toggle_check_toggle').click(function(evt){
	    evt.preventDefault();
	    jQuery('input:checkbox.toggle:not(:disabled)').attr('checked', function(){
	        return !jQuery(this).attr('checked');
	    });
	});
	jQuery('#installdata_field').click(function(evt){
	    handleSampleDataCheckbox();
	});
	
	var handleSampleDataCheckbox = function(){
	    demo = jQuery('#installdata_field').attr('checked');
	    jQuery('input:checkbox.toggle.demo').each(function(ix, el){
	        if(demo) {
	            jQuery(this).attr('checked', true).attr('disabled', true);
	} else {
	            jQuery(this).attr('disabled', false);
	}
	    });
	}
	
	// handle state of demo content checkbox on page load
	handleSampleDataCheckbox();
</script>
