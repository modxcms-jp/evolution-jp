<!DOCTYPE html
        PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>

<head>
    <title>[+lang.DM_module_title+]</title>
    <meta http-equiv="Content-Type" content="text/html; charset=[(modx_charset)]"/>
    [+csrf_meta+]
    <link rel="stylesheet" type="text/css" href="media/style[+theme+]/style.css"/>
    <script type="text/javascript" src="media/script/tabpane.js"></script>
    <script type="text/javascript" src="media/script/jquery/jquery.min.js?[+settings_version+]"></script>
    <script type="text/javascript" src="../assets/modules/docmanager/js/docmanager.js"></script>
    <script type="text/javascript">
        var $j = jQuery.noConflict();
        jQuery('#workText', parent.mainMenu.document).html('');
        var baseurl = '[+baseurl+]';
        top.mainMenu.defaultTreeFrame();
        var $j = jQuery.noConflict();

        function loadTemplateVars(tplId) {
            $j('#tvloading').css('display', 'block');
            $j.ajax({
                'type': 'POST',
                'url': '[+ajax.endpoint+]',
                'data': {'tplID':tplId},
                'success': function (r, s) {
                    document.getElementById('results').innerHTML = r;
                    document.getElementById('tvloading').style.display = 'none';
                }
            });
        }

        function save() {
            document.newdocumentparent.submit();
        }

        function setMoveValue(pId, pName) {
            if (!pId || checkParentChildRelation(pId, pName)) {
                document.newdocumentparent.new_parent.value = pId;
                document.getElementById('parentName').innerHTML = "Parent: <strong>" + pId + "</strong> (" + pName +
                    ")";
            }
        }

        function checkParentChildRelation(pId, pName) {
            const id = document.newdocumentparent.id.value;
            const tdoc = parent.tree.document;
            let pn = tdoc.getElementById("node" + pId);
            if (!pn) return false;
            while (pn.p > 0) {
                pn = tdoc.getElementById("node" + pn.p);
                if (pn.id.substr(4) === id) {
                    alert("Illegal Parent");
                    return false;
                }
            }
            return true;
        }
    </script>
</head>

<body>
<h1>[+lang.DM_module_title+]</h1>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button1"><a href="#" onclick="document.location.href='index.php?a=2';"><img
                        src="media/style[+theme+]/images/icons/stop.png"/> [+lang.DM_close+]</a></li>
    </ul>
</div>
<div class="section">
    <div class="sectionHeader">[+lang.DM_action_title+]</div>
    <div class="sectionBody">
        <div class="tab-pane" id="docManagerPane">

            <div class="tab-page" id="tabTemplates">
                <h2 class="tab">[+lang.DM_change_template+]</h2>
                [+view.templates+]
            </div>

            <div class="tab-page" id="tabTemplateVariables">
                <h2 class="tab">[+lang.DM_template_variables+]</h2>
                [+view.templatevars+]
            </div>

            <div class="tab-page" id="tabDocPermissions">
                <h2 class="tab">[+lang.DM_doc_permissions+]</h2>
                [+view.documentgroups+]
            </div>

            <div class="tab-page" id="tabSortMenu">
                <h2 class="tab">[+lang.DM_sort_menu+] </h2>
                [+view.sort+]
            </div>

            <div class="tab-page" id="tabOther">
                <h2 class="tab">[+lang.DM_other+]</h2>
                [+view.misc+]
                [+view.changeauthors+]
            </div>
        </div>
        <script type="text/javascript">
            tpDM = new WebFXTabPane(document.getElementById('docManagerPane'));
        </script>
    </div>
</div>
[+view.documents+]
</body>

</html>