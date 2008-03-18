<?php

class BlogMenu_blogrankedit
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$view =& $admin->getView();
		$plugin =& $admin->getPlugin();
		
		if ($msg) $view->assign('message', _MESSAGE. ': ' .$msg);
		
		$maxrank = $plugin->getOption('maxblogrank');
		$view->assign('maxrank', intval($maxrank));
		
		$order = $plugin->getOption('blogorder');
		$rank = array();
		
		$query = 'SELECT b.bname as name, r.rcid as id, r.rank as rank'
			. ' FROM '.sql_table('plug_blogmenu_rank').' as r, '.sql_table('blog').' as b'
			. ' WHERE r.rcid=b.bnumber and r.rcontext="blog"'
			. ' ORDER BY r.rank ASC, '.$order;
		$res = sql_query($query);
		while ($a = mysql_fetch_assoc($res)) {
			$rank[] = $a;
		}
		
		$view->assign('rank', $rank);
		
		$popup = array();
		$popup['rank'] = $admin->createPopup('rank');
		
		$view->assign('popup', $popup);
		
		$view->display('blogrankedit.tpl.php');
	}
	
}

?>