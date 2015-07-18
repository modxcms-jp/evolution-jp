<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<script type="text/javascript">
jQuery(function(){
	var dpOffset = [+datepicker_offset+];
	var dpformat = '[+datetime_format+]' + ' hh:mm:00';
	var dayNames = [+dayNames+];
	var monthNames = [+monthNames+];
	new DatePicker($('pub_date'),   {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
	new DatePicker($('unpub_date'), {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
});
</script>
