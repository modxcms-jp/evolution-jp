<script>
    jAlert('[+message+]', '[+warning+]', function () {
        if (window.EvoShell && typeof EvoShell.navigate === 'function') {
            EvoShell.navigate('[+url+]');
            return;
        }
        top.main.document.location.href = '[+url+]';
    });
</script>
