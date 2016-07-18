<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<script type="text/javascript">
jQuery(function(){
	var dpOffset = [+datepicker_offset+];
	var dpformat = '[+datetime_format+]' + ' hh:mm:00';
	var dayNames = [+dayNames+];
	var monthNames = [+monthNames+];
	new DatePicker($('pub_date'),   {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
	new DatePicker($('unpub_date'), {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
	
	jQuery('#pub_date').keydown(function(){
		jQuery('div#pub_datedp_container').hide();
		documentDirty=true;
	});
	jQuery('#pub_date').click(function(){
		jQuery('div#pub_datedp_container').show();
	});
	jQuery('#unpub_date').keydown(function(){
		jQuery('div#unpub_datedp_container').hide();
		documentDirty=true;
	});
	jQuery('#unpub_date').click(function(){
		jQuery('div#unpub_datedp_container').show();
	});
});
</script>
