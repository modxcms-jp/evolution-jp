<form id="install" action="index.php?action=options" method="POST">
<input type="hidden" name="prev_action" value="connection" />
  <h2>[+connection_screen_database_info+]</h2>
  <h3>[+connection_screen_server_connection_information+]</h3>
  <p>[+connection_screen_server_connection_note+]</p>

  <p class="labelHolder"><label for="database_server">[+connection_screen_database_host+]</label>
    <input id="database_server" value="[+database_server+]" name="database_server" />
  </p>
  <p class="labelHolder"><label for="database_user">[+connection_screen_database_login+]</label>
    <input id="database_user" name="database_user" value="[+database_user+]" />
  </p>
  <p class="labelHolder"><label for="database_password">[+connection_screen_database_pass+]</label>
    <input id="database_password" type="password" name="database_password" value="[+database_password+]" />
  </p>

<!-- connection test action/status message -->
  <div class="clickHere">
	&rarr; <a id="servertest" href="#footer">[+connection_screen_server_test_connection+]</a>
  </div>
  <div class="status" id="serverstatus" style="display:none;"></div>
<!-- end connection test action/status message -->


<div id="setCollation"><div id="collationMask">
  <h3>[+connection_screen_database_connection_information+]</h3>
  <p>[+connection_screen_database_connection_note+]</p>
  <p class="labelHolder"><label for="dbase">[+connection_screen_database_name+]</label>
    <input id="dbase" value="[+dbase+]" name="dbase" />
  </p>
  <p class="labelHolder"><label for="table_prefix">[+connection_screen_table_prefix+]</label>
    <input id="table_prefix" value="[+table_prefix+]" name="table_prefix" />
  </p>
[+set_block_connection_method+]
  <div class="clickHere">
	&rarr; <a id="databasetest" href="#footer">[+connection_screen_database_test_connection+]</a>
  </div>
  <div class="status" id="databasestatus" style="display:none;">&nbsp;</div>
</div></div>

<script type="text/javascript">
jQuery('#servertest').click(function(){
	var target = jQuery('html, body');
	target.animate({ scrollTop: jQuery('#footer').offset().top }, 'slow');
});
jQuery('#databasetest').click(function(){
	var target = jQuery('html, body');
	target.animate({ scrollTop: jQuery('#footer').offset().top }, 'slow');
});
</script>
[+AUH+]
    <p class="buttonlinks">
        <a href="javascript:void(0);" class="prev" title="[+btnback_value+]"><span>[+btnback_value+]</span></a>
        <a href="javascript:void(0);" class="next" title="[+btnnext_value+]" style="display:none;"><span>[+btnnext_value+]</span></a>
    </p>
</form>

<script type="text/javascript">
	if(jQuery('#adminpassconfirm').val() != '') jQuery('a.next').css('display','block');
	jQuery('#adminpassconfirm').focus(function(){
		jQuery('a.next').css('display','block');
	});
	
	jQuery('a.prev').click(function(){
		jQuery('#install').attr({action:'index.php?action=mode'});
		jQuery('#install').submit();
	});
	jQuery('a.next').click(function(){
		if(jQuery('#adminpass').val() !== jQuery('#adminpassconfirm').val())
		{
			alert("[+alert_enter_adminpassword+]");
		}
		else
		{
			jQuery('#install').submit();
		}
	});
	var language ='[+install_language+]';
	var installMode ='[+installmode+]';
</script>
<script type="text/javascript" src="connection.js"></script>
