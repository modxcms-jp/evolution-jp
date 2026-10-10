<script>
    // message / warning / url は errorHandler::dumpError() で JS の文字列リテラルにしてある
    (function () {
        var url = [+url+];
        jAlert([+message+], [+warning+], function () {
            if (window.EvoShell && typeof EvoShell.navigate === 'function') {
                // モーダル内で発生した場合は空のオーバーレイを残さないよう先に閉じる。
                // 直後にnavigateするため、閉じる際の#mainPane再取得は行わない(競合して古い画面が残るのを防ぐ)
                if (typeof EvoShell.closeModal === 'function') {
                    EvoShell.closeModal({ reloadMain: false });
                }
                EvoShell.navigate(url);
                return;
            }
            top.main.document.location.href = url;
        });
    })();
</script>
