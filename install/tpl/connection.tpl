<form id="install" action="index.php" method="POST">
<input type="hidden" name="action" value="options" />
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


    <div id="setCollation">
        <div id="collationMask">
  <h3>[+connection_screen_database_connection_information+]</h3>
  <p>[+connection_screen_database_connection_note+]</p>
  <p class="labelHolder"><label for="dbase">[+connection_screen_database_name+]</label>
    <input id="dbase" value="[+dbase+]" name="dbase" />
  </p>
  <p class="labelHolder"><label for="dbase">[+connection_screen_collation+]</label>
    <select id="collation" name="database_collation" />
    </select>
  </p>
  <p class="labelHolder"><label for="table_prefix">[+connection_screen_table_prefix+]</label>
    <input id="table_prefix" value="[+table_prefix+]" name="table_prefix" />
  </p>
  <p class="labelHolder">
    <div id="connection_method" name="connection_method">
        <input type="hidden" value="SET CHARACTER SET" id="database_connection_method" name="database_connection_method" />
    </div>
  </p>
  <div class="clickHere">
	&rarr; <a id="databasetest" href="#footer">[+connection_screen_database_test_connection+]</a>
  </div>
  <div class="status" id="databasestatus" style="display:none;">&nbsp;</div>
        </div>
    </div>

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
    <div id="AUH" style="margin-top:1.5em;display:none;">
    <div id="AUHMask">
    <h2>[+connection_screen_defaults+]</h2>
    <h3>[+connection_screen_default_admin_user+]</h3>
    <p>[+connection_screen_default_admin_note+]</p>
    <p class="labelHolder"><label for="adminname">[+connection_screen_default_admin_login+]</label>
      <input id="adminname" value="[+adminname+]" name="adminname" />
    </p>
    <p class="labelHolder"><label for="adminemail">[+connection_screen_default_admin_email+]</label>
      <input id="adminemail" value="[+adminemail+]" name="adminemail" style="width:300px;" />
    </p>
    <p class="labelHolder"><label for="adminpass">[+connection_screen_default_admin_password+]</label>
      <input id="adminpass" type="password" name="adminpass" value="[+adminpass+]" />
    </p>
    <p class="labelHolder"><label for="adminpassconfirm">[+connection_screen_default_admin_password_confirm+]</label>
      <input id="adminpassconfirm" type="password" name="adminpassconfirm" value="[+adminpassconfirm+]" />
    </p>
    </div>
    </div>
    <p class="buttonlinks">
        <a href="javascript:void(0);" class="prev" title="[+btnback_value+]"><span>[+btnback_value+]</span></a>
        <a href="javascript:void(0);" class="next" title="[+btnnext_value+]" style="display:none;"><span>[+btnnext_value+]</span></a>
    </p>
</form>

<script type="text/javascript">
	jQuery('#servertest').click(function() {
		var url = 'connection.servertest.php';
		var pars = {
			q: url,
			host: jQuery('#database_server').val(),
			uid:  jQuery('#database_user').val(),
			pwd:  jQuery('#database_password').val(),
			language: language
		};
		jQuery.post(url, pars, function(data) {
			jQuery('#serverstatus').html(data).fadeIn();
			if(0<data.indexOf('server_pass'))
				jQuery('#setCollation').fadeIn();
		});
	});
	
	// database test
	jQuery('#databasetest').click(function() {
		var url = 'connection.databasetest.php';
		var pars = {
			'q': url,
			'host': jQuery('#database_server').val(),
			'uid': jQuery('#database_user').val(),
			'pwd': jQuery('#database_password').val(),
			'dbase': jQuery('#dbase').val(),
			'table_prefix': jQuery('#table_prefix').val(),
			'database_collation': jQuery('#collation').val(),
			'database_connection_method': 'SET CHARACTER SET',
			'language': language,
			'is_upgradeable': is_upgradeable
		};
		jQuery.post(url, pars, function(data) {
			jQuery('#databasestatus').html(data).fadeIn();
			if(0<data.indexOf('database_pass'))
				jQuery('#AUH').fadeIn();
		});
	});
	
	if(jQuery('#adminpassconfirm').val() != '') jQuery('a.next').css('display','block');
	
	jQuery('#adminpassconfirm').focus(function(){
		jQuery('a.next').css('display','block');
	});
	
	jQuery('a.prev').click(function(){
		jQuery('#install input[name=action]').val('mode');
		jQuery('#install').submit();
	});
	jQuery('a.next').click(function(){
		if(jQuery('#adminpass').val() !== jQuery('#adminpassconfirm').val())
			alert("[+alert_enter_adminpassword+]");
		else
			jQuery('#install').submit();
	});
	var language ='[+install_language+]';
	var is_upgradeable ='[+is_upgradeable+]';
</script>
