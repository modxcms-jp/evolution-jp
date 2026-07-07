<!-- Doc Manager: シェル内に表示される断片テンプレート -->
<script type="text/javascript">
    function reset() {
        var form = document.getElementById('backform');
        if (form) {
            form.submit();
        }
    }
</script>
<style type="text/css">
    #mainPane .topdiv {
        border: 0;
    }

    #mainPane .subdiv {
        border: 0;
    }

    #mainPane .section ul, #mainPane .section li {
        list-style: none;
    }
</style>
<script type="text/javascript">if (window.tree && tree.updateTree) tree.updateTree();</script>
<h1>[+lang.DM_module_title+]</h1>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button1"><a href="index.php?a=2"><img
                        src="media/style[+theme+]/images/icons/stop.png" align="absmiddle"> [+lang.DM_close+]</a></li>
        <li id="Button4"><a href="#" onclick="reset();"><img src="media/style[+theme+]/images/icons/cancel.png"
                                                             align="absmiddle"> [+lang.DM_cancel+]</a></li>
    </ul>
</div>

<div class="section">
    <div class="sectionHeader">[+lang.DM_update_title+]</div>
    <div class="sectionBody">
        <p>[+update.message+]</p>
        <form id="backform" method="post" style="display: none;">
            [+csrf_token+]
            <input type="submit" name="back" value="[+lang.DM_process_back+]"/>
        </form>
    </div>
</div>
