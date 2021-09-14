<?php
if (IN_MANAGER_MODE != "true") {
    die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
}
$_SESSION['browser'] = (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 1') !== false) ? 'legacy_IE' : 'modern';
$mxla = $modx_lang_attribute ? $modx_lang_attribute : 'en';
if (!isset($modx->config['manager_menu_height'])) {
    $modx->config['manager_menu_height'] = '58';
}
if (!isset($modx->config['manager_tree_width'])) {
    $modx->config['manager_tree_width'] = '260';
}

if (isset($_SESSION['mainframe'])) {
    $action = $_SESSION['mainframe']['a'];
    $mainurl = 'index.php?' . http_build_query($_SESSION['mainframe']);
    unset($_SESSION['mainframe']);
} else {
    if (isset($_SESSION['mgrForgetPassword'])) {
        $action = '28';
    } else {
        $action = '2';
    }
    $mainurl = "index.php?a={$action}";
}

$tmp = array('action' => $action);
evo()->invokeEvent('OnManagerPreFrameLoader', $tmp);
?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">
    <html <?php echo ($modx_textdir === 'rtl' ? 'dir="rtl" lang="' : 'lang="') . $mxla . '" xml:lang="' . $mxla . '"'; ?>>
    <head>
        <title><?php echo $site_name; ?> - (MODX CMS Manager)</title>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $modx_manager_charset ?>"/>
    </head>
    <?php
    $treePane = '<frame name="tree" src="index.php?a=1&amp;f=tree" scrolling="no" frameborder="0" onresize="top.tree.resizeTree();">';
    $mainPane = sprintf('<frame name="main" src="%s" scrolling="auto" frameborder="0" onload="if (mainMenu.stopWork()) mainMenu.stopWork();">',
        $mainurl);
    ?>
    <frameset rows="<?php echo $modx->config['manager_menu_height']; ?>,*" border="0">
        <frame name="mainMenu" src="index.php?a=1&amp;f=menu" scrolling="no" frameborder="0" noresize="noresize">
        <?php if ($modx_textdir === 'ltr') {
        // Left-to-Right reading (sidebar on left)
        ?>
        <frameset
            cols="<?php echo $modx->config['manager_tree_width']; ?>,*"
            border="1"
            frameborder="3"
            framespacing="3"
            bordercolor="#f7f7f7"
        >
            <?php echo $treePane; ?>
            <?php echo $mainPane; ?>
            );">
            <?php } else {
            // Right-to-Left reading (sidebar on right)
            ?>
            <frameset
                cols="*,<?php echo $modx->config['manager_tree_width']; ?>"
                border="1"
                frameborder="3"
                framespacing="3"
                bordercolor="#f7f7f7"
            >
                <?php echo $mainPane; ?>
                <?php echo $treePane; ?>
                <?php } ?>
            </frameset>
        </frameset>
        <noframes>This software requires a browser with support for frames.</noframes>
    </html>
<?php
$tmp = array('action' => $action);
evo()->invokeEvent('OnManagerFrameLoader', $tmp);
