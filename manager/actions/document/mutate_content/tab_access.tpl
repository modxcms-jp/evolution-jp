<!-- Access Permissions -->
<div class="tab-page" id="tabAccess">
    <h2 class="tab" id="tabAccessHeader">[+_lang_access_permissions+]</h2>
    <script>
        function makePublic(b) {
            let notPublic = false;
            const f = document.forms['mutate'];
            let chkpub = f['chkalldocs'];
            let chks = f['docgroups[]'];
            if (!chks && chkpub) {
                chkpub.checked = true;
                return false;
            } else if (!b && chkpub) {
                if (!chks.length) notPublic = chks.checked;
                else for (i = 0; i < chks.length; i++) if (chks[i].checked) notPublic = true;
                chkpub.checked = !notPublic;
            } else {
                if (!chks.length) chks.checked = (b) ? false : chks.checked;
                else for (i = 0; i < chks.length; i++) if (b) chks[i].checked = false;
                chkpub.checked = true;
            }
        }
    </script>
    <p>[+_lang_access_permissions_docs_message+]</p>
    <ul>
        [+UDGroups+]
    </ul>
</div><!-- end #tabAccess -->
