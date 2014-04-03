<input id="[+id+]" name="[+name+]" class="[+class+]" type="text" value="[+value+]" style="[+style+]" onblur="documentDirty=true;" />
<a onclick="document.forms['mutate'].elements['[+id+]'].value='';document.forms['mutate'].elements['[+id+]'].onblur(); return true;"
   style="cursor:pointer; cursor:hand"><img src="[+cal_nodate+]" border="0" alt="No date"></a>
<script type="text/javascript">
	window.addEvent('domready', function() {
		new DatePicker($('[+id+]'), {'yearOffset' : [+yearOffset+], 'format' : '[+datetime_format+]'});
	});
</script>
