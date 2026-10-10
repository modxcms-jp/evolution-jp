<?php
if (!isset($modx) || !evo()->isLoggedin()) exit;
evoRenderPaneFooterExtras();
// AJAX断片応答(X-Evo-Pane)では後処理だけを出力し、</body></html>は閉じない(header.inc.phpと対)
if (evoIsPaneResponse()) {
    return;
}
?>
<?php if (defined('EVO_SHELL_MAIN_OPENED')): ?>
</main>
<?php endif; ?>
</body>
</html>
