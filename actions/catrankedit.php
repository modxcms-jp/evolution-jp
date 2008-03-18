<?php

class BlogMenu_catrankedit
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$blogid = intRequestVar('blogid');
		if (!$blogid) {
			$admin->error(_ERROR_BADACTION);
		}
		
		$view =& $admin->getView();
		$plugin =& $admin->getPlugin();
		
		if ($msg) $view->assign('message', _MESSAGE. ': ' .$msg);
		
		$blogname = getBlogNameFromID($blogid);
		$view->assign('blogid', $blogid);
		$view->assign('blogname', $blogname);
		
		$maxrank = $plugin->getOption('maxcatrank');
		$view->assign('maxrank', intval($maxrank));
		
		$order = $plugin->getOption('catorder');
		$rank = array();
		
		$query = 'SELECT c.cname as name, r.rcid as id, r.rank as rank'
			. ' FROM '.sql_table('plug_blogmenu_rank').' as r, '.sql_table('category').' as c'
			. ' WHERE c.cblog='.intval($blogid).' and r.rcid=c.catid and r.rcontext="category"'
			. ' ORDER BY r.rank ASC, '.$order;
		$res = sql_query($query);
		while ($a = mysql_fetch_assoc($res)) {
			$rank[] = $a;
		}
		
		$view->assign('rank', $rank);
		
		$popup = array();
		$popup['rank'] = $admin->createPopup('rank');
		
		$view->assign('popup', $popup);
		
		$view->display('catrankedit.tpl.php');
	}
	
}

?>