<?php
if (!isset($modx) || !evo()->isLoggedin()) exit;
evoRenderPaneFooterExtras();
?>
<?php if (defined('EVO_SHELL_MAIN_OPENED')): ?>
</main>
<?php endif; ?>
</body>
</html>
