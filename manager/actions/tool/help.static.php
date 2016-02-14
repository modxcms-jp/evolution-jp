<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
?>
<h1><?php echo $_lang['help']; ?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="sectionBody">
    <div class="tab-pane" id="helpPane">
        <script type="text/javascript">
            tpHelp = new WebFXTabPane( document.getElementById( "helpPane" ), <?php echo $modx->config['remember_last_tab'] == 0 ? 'false' : 'true'; ?> );
        </script>
<?php
$help_dir = MODX_BASE_PATH . 'assets/templates/help';
if(is_dir($help_dir)==false)
{
	echo '<h3>' . $_lang["credits"] . '</h3>';
	echo '<div>' . $_lang["about_msg"] . '</div>';
	echo '<h3>' . $_lang["help"] . '</h3>';
	echo '<div>' . $_lang["help_msg"] . '</div>';
	exit;
}

if ($files = scandir($help_dir))
{
	foreach ($files as $file)
	{
		if ($file != "." && $file != ".." && $file != ".svn")
		{
			$help[] = $file;
		}
	}
}


natcasesort($help);

foreach($help as $k=>$v) {

    $helpname =  substr($v, 0, strrpos($v, '.'));

    $prefix = substr($helpname, 0, 2);
    if(is_numeric($prefix)) {
        $helpname =  substr($helpname, 2, strlen($helpname)-1 );
    }

    $helpname = str_replace('_', ' ', $helpname);
    echo '<div class="tab-page" id="tab'.$v.'Help">';
    echo '<h2 class="tab">'.$helpname.'</h2>';
    include ($help_dir . '/' . $v);
    echo '</div>';
}
?>
    </div>
</div>
