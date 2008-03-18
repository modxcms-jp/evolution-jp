<?php

class BlogMenu_rankupdate
{
	function execute(&$controller, $msg)
	{
		global $CONF;
		
		$admin =& $controller->getAdmin();
		$plugin =& $admin->getPlugin();
		
		$context = postVar('context');
		if ($context != 'blog' && $context != 'category') {
			$admin->error(_ERROR_BADACTION);
		}
		
		if ($context == 'blog')
		{
			$admin->memberAuth();
		
			$ranks = postVar('rank');
			$hidden = postVar('rankhidden');
			
			$this->_updateRank('blog', $ranks, $hidden);
			$controller->forward('blogrankedit', __NP_MSG_RANK_UPDATED);
		}
		else
		{
			$blogid = intPostVar('blogid');
			if (!$blogid) $admin->error(_ERROR_BADACTION);
			
			$from = postVar('from');
			if ($from != 'blogsettings' && $from != 'management') {
				$admin->error(_ERROR_BADACTION);
			}
			
			if ($plugin->getOption('show_extst_rank') == 'yes') {
				$admin->memberAuth('BlogAdmin', $blogid);
			} else {
				$admin->memberAuth();
			}
			
			$ranks = postVar('rank');
			$hidden = postVar('rankhidden');
			
			$this->_updateRank('category', $ranks, $hidden);
			
			if ($from == 'blogsettings') {
				redirect($CONF['AdminURL'] . 'index.php?action=blogsettings&blogid=' . $blogid . '#np_blogmenu');
			} else {
				$controller->forward('catrankedit', __NP_MSG_RANKCONF_UPDATED);
			}
			
		}
	}
	
	function _updateRank($type, $ranks, $hidden)
	{
		if (!is_array($hidden)) $hidden = array();
		
		foreach ($ranks as $k => $v) {
			if (array_key_exists($k, $hidden) && $hidden[$k] == 1) {
				$v = 1000;
			}
			$query = 'UPDATE '.sql_table('plug_blogmenu_rank').' SET'
				. ' rank='.intval($v).' WHERE rcid='.intval($k)
				. ' and rcontext="'.addslashes($type).'"';
			sql_query($query);
		}
	}
	
}


?>