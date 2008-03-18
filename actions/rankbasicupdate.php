<?php

class BlogMenu_rankbasicupdate
{
	function execute(&$controller, $msg)
	{
		$admin =& $controller->getAdmin();
		$admin->memberAuth();
		
		$plugin =& $admin->getPlugin();
		
		if (intPostVar('update_existdef') == 1) {
			$old_blogdef = $plugin->getOption('defblogrank');
			$old_catdef = $plugin->getOption('defcatrank');
		}
		
		$blogdef = intPostVar('blogdef');
		$blogmax = intPostVar('blogmax');
		$catdef = intPostVar('catdef');
		$catmax = intPostVar('catmax');
		
		if ($blogmax > 999) $blogmax = 999;
		if ($catmax > 999) $catmax = 999;
		
		if (intPostVar('blogdef2hidden') == 1) {
			$blogdef = 1000;
		} elseif ($blogdef > $blogmax && $blogdef != 1000) {
			$blogdef = $blogmax;
		}
		if (intPostVar('catdef2hidden') == 1) {
			$catdef = 1000;
		} elseif ($catdef > $catmax && $catdef != 1000) {
			$catdef = $catmax;
		}
		
		$plugin->setOption('defblogrank', $blogdef);
		$plugin->setOption('maxblogrank', $blogmax);
		$plugin->setOption('defcatrank', $catdef);
		$plugin->setOption('maxcatrank', $catmax);
		
		$plugin->plugin_options = 0;
		
		// replace rank with new default rank
		if (intPostVar('update_existdef') == 1) {
			sql_query('UPDATE '.sql_table('plug_blogmenu_rank').' SET rank='.$blogdef.' WHERE rcontext="blog" and rank='.$old_blogdef);
			sql_query('UPDATE '.sql_table('plug_blogmenu_rank').' SET rank='.$catdef.' WHERE rcontext="category" and rank='.$old_catdef);
		}
		
		// replace lowest rank with new max rank
		sql_query('UPDATE '.sql_table('plug_blogmenu_rank').' SET rank='.$blogmax.' WHERE rcontext="blog" and (rank>'.$blogmax.' and rank<1000)');
		sql_query('UPDATE '.sql_table('plug_blogmenu_rank').' SET rank='.$catmax.' WHERE rcontext="category" and (rank>'.$catmax.' and rank<1000)');
		
		$controller->forward('overview', __NP_MSG_RANKCONF_UPDATED);
	}
}

?>