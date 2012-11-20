// get collation from the database server
$(function(){
	$('#servertest').click(function()
	{
		var url = 'connection.servertest.php';
		var pars =
		{
			q: url,
			host: $('#database_server').val(),
			uid:  $('#database_user').val(),
			pwd:  $('#database_password').val(),
			language: language
		};
		$.post(url, pars, function(data)
		{
			$('#serverstatus').html(data).fadeIn();
			if(0<data.indexOf('server_pass'))
			{
				$('#setCollation').fadeIn();
			}
		});
	});
	
	// database test
	$('#databasetest').click(function()
	{
		var url = 'connection.databasetest.php';
		var pars = 
		{
			'q': url,
			'host': $('#database_server').val(),
			'uid': $('#database_user').val(),
			'pwd': $('#database_password').val(),
			'dbase': $('#dbase').val(),
			'table_prefix': $('#table_prefix').val(),
			'database_collation': 'utf8_general_ci',
			'database_connection_method': 'SET CHARACTER SET',
			'language': language,
			'installMode': installMode
		};
		$.post(url, pars, function(data)
		{
			$('#databasestatus').html(data).fadeIn();
			if(0<data.indexOf('database_pass'))
			{
				$('#AUH').fadeIn();
			}
		});
	});
});
