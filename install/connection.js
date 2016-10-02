// get collation from the database server
jQuery(function(){
	jQuery('#servertest').click(function()
	{
		var url = 'connection.servertest.php';
		var pars =
		{
			q: url,
			host: jQuery('#database_server').val(),
			uid:  jQuery('#database_user').val(),
			pwd:  jQuery('#database_password').val(),
			language: language
		};
		$.post(url, pars, function(data)
		{
			jQuery('#serverstatus').html(data).fadeIn();
			if(0<data.indexOf('server_pass'))
			{
				jQuery('#setCollation').fadeIn();
			}
		});
	});
	
	// database test
	jQuery('#databasetest').click(function()
	{
		var url = 'connection.databasetest.php';
		var pars = 
		{
			'q': url,
			'host': jQuery('#database_server').val(),
			'uid': jQuery('#database_user').val(),
			'pwd': jQuery('#database_password').val(),
			'dbase': jQuery('#dbase').val(),
			'table_prefix': jQuery('#table_prefix').val(),
			'database_collation': jQuery('#collation').val(),
			'database_connection_method': 'SET CHARACTER SET',
			'language': language,
			'installMode': installMode
		};
		jQuery.post(url, pars, function(data)
		{
			jQuery('#databasestatus').html(data).fadeIn();
			if(0<data.indexOf('database_pass'))
			{
				jQuery('#AUH').fadeIn();
			}
		});
	});
});
