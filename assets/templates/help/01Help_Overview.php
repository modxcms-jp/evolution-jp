<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
?>

<div class="sectionHeader"><?php echo $_lang['about_title']; ?></div><div class="sectionBody">
<?php echo $_lang['about_msg']; ?>
</div>

<div class="sectionHeader"><?php echo $_lang['help_title']; ?></div><div class="sectionBody">
<?php echo $_lang['help_msg']; ?>
</div>

<div class="sectionHeader"><?php echo $_lang['credits']; ?></div>
<div class="sectionBody">
<ul>
<li><a href="http://www.php.net/copyright.php" target="_blank">Copyright&copy; 2001-2012 The PHP Group</a></li>
<li><a href="http://www.mysql.com" target="_blank">&copy; 2012, Oracle Corporation and/or its affiliates</a></li>
<li><a href="http://www.destroydrop.com/javascripts/tree/" target="_blank">&copy;2002-2003 Geir Landr&ouml;</a></li>
<li><a href="http://www.everaldo.com" target="_blank">Copyright (c)  2006-2007  Everaldo Coelho.</a></li>
</ul>
<?php echo $_lang['credits_shouts_msg']; ?>
</div>
