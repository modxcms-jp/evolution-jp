/*
 * Small script to keep session alive in MODX
 */
function keepMeAlive()
{
	var tok = document.getElementById('sessTokenInput').value;
	var o = Math.random();
	var url = 'includes/session_keepalive.php';
	
	$j.getJSON(url, {'tok':tok,'o':o},
	function(resp)
	{
		if(resp.status != 'ok') window.location.href = 'index.php?a=8';
    });
}
window.setInterval('keepMeAlive()', 1000 * 60);
