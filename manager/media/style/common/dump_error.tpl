<script>
    jAlert('[+message+]', '[+warning+]', function () {
        if (window.EvoShell && typeof EvoShell.navigate === 'function') {
            // モーダル内で発生した場合は、空のオーバーレイを残さないよう先に閉じる
            if (typeof EvoShell.closeModal === 'function') {
                EvoShell.closeModal();
            }
            EvoShell.navigate('[+url+]');
            return;
        }
        top.main.document.location.href = '[+url+]';
    });
</script>
