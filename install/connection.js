// get collation from the database server
$(function(){
$('#servertest').click(function()
{
	var url = 'connection.collation.php';
	var pars =
	{
		'q': url,
		'host': $('#databasehost').val(),
		'uid': $('#databaseloginname').val(),
		'pwd': $('#databaseloginpassword').val(),
		'database_collation': $('#database_collation').val(),
		'database_connection_method': $('#database_connection_method').val(),
		'language': language
	};
	
	$.post(url, pars,function(data)
	{
		$('#collation').html(data);
		
		// get the server test status as soon as collation received
		var url = 'connection.servertest.php';
		var pars =
		{
			q: url,
			host: $('#databasehost').val(),
			uid:  $('#databaseloginname').val(),
			pwd:  $('#databaseloginpassword').val(),
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
});



	// database test
	$('#databasetest').click(function()
	{
		var url = 'connection.databasetest.php';
		
		var pars = 
		{
			'q': url,
			'host': $('#databasehost').val(),
			'uid': $('#databaseloginname').val(),
			'pwd': $('#databaseloginpassword').val(),
			'database_name': $('#database_name').val(),
			'tableprefix': $('#tableprefix').val(),
			'database_collation': $('#database_collation').val(),
			'database_connection_method': $('#database_connection_method').val(),
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
