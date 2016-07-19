<input id="[+id+]" name="[+name+]" class="[+class+]" type="text" value="[+value+]" style="[+style+]" onblur="documentDirty=true;" />
<a onclick="document.forms['mutate'].elements['[+id+]'].value='';document.forms['mutate'].elements['[+id+]'].onblur(); return true;"
   style="cursor:pointer; cursor:hand"><img src="[+cal_nodate+]" border="0" alt="No date"></a>
<script type="text/javascript">
	jQuery(function() {
        var start = new Date();
        start.setHours(0);
        start.setMinutes(0);
        
        var options = {
            language      : 'ja',
            timepicker    : true,
            todayButton   : new Date(),
            keyboardNav   : false,
            startDate     : start,
            autoClose     : true,
            toggleSelected: false,
            clearButton   : true,
            minutesStep   : 5,
            dateFormat    : 'yyyy/mm/dd',
            onSelect      : function (fd, d, picker) {
                documentDirty = true;
            },
            navTitles: {
               days: 'yyyy/mm'
            }
        };
		jQuery('#[+id+]').datepicker(options);
	});
</script>
