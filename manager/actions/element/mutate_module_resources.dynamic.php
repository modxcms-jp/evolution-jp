<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('edit_module')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = anyv('id', 0);

// Get table names (alphabetical)
$tbl_site_module_depobj = evo()->getFullTableName('site_module_depobj');
$tbl_site_plugins = evo()->getFullTableName('site_plugins');
$tbl_site_snippets = evo()->getFullTableName('site_snippets');

manager()->initPageViewState();

// check to see the  editor isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', "action=108 AND id='{$id}'");
$limit = db()->count($rs);
if ($limit > 1) {
    while ($lock = db()->getRow($rs)) {
        if ($lock['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf($_lang['lock_msg'], $lock['username'], $_lang['modules']);
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
}
// end check for lock

// make sure the id's a number
if (!is_numeric($id)) {
    echo "Passed ID is not a valid number!";
    exit;
}

// take action
switch (anyv('op')) {
    case 'add':
        $opids = explode(',', anyv('newids'));
        if (count($opids) > 0) {
            // 1-snips, 2-tpls, 3-tvs, 4-chunks, 5-plugins, 6-docs
            $rt = strtolower(anyv('rt'));
            if ($rt === 'chunk') {
                $type = 10;
            }
            if ($rt === 'doc') {
                $type = 20;
            }
            if ($rt === 'plug') {
                $type = 30;
            }
            if ($rt === 'snip') {
                $type = 40;
            }
            if ($rt === 'tpl') {
                $type = 50;
            }
            if ($rt === 'tv') {
                $type = 60;
            }
            $v = [];
            foreach ($opids as $opid) {
                $opid = intval($opid);
                $v[] = "('{$id}','{$opid}','{$type}')";
            }
            $values = join(',', $v);
            $del_opids = join(',', $opids);
            db()->delete($tbl_site_module_depobj, "module='{$id}' AND resource IN ({$del_opids}) AND type='{$type}'");
            $ds = db()->query("INSERT INTO {$tbl_site_module_depobj} (module, resource, type) VALUES {$values}");
            if (!$ds) {
                echo '<script type="text/javascript">' .
                    'function jsalert(){ alert(\'An error occured while trying to update the database. \'' . db()->getLastError() . ');' .
                    'setTimeout(\'jsalert()\',100)' .
                    '</script>';
            }
        }
        break;
    case 'del':
        $opids = anyv('depid');
        for ($i = 0, $iMax = count($opids); $i < $iMax; $i++) {
            $opids[$i] = (int)$opids[$i]; // convert ids to numbers
        }
        // get resources that needs to be removed
        $ds = db()->query("SELECT * FROM " . $tbl_site_module_depobj . " WHERE id IN (" . implode(",", $opids) . ")");
        if ($ds) {
            // loop through resources and look for plugins and snippets
            $i = 0;
            $plids = [];
            $snid = [];
            while ($row = db()->getRow($ds)) {
                if ($row['type'] == '30') {
                    $plids[$i] = $row['resource'];
                }
                if ($row['type'] == '40') {
                    $snids[$i] = $row['resource'];
                }
            }
            // get guid
            $ds = db()->select('*', '[+prefix+]site_modules', "id='{$id}'");
            if ($ds) {
                $row = db()->getRow($ds);
                $guid = $row['guid'];
            }
            // reset moduleguid for deleted resources
            if (($cp = count($plids)) || ($cs = count($snids))) {
                $plids = implode(',', $plids);
                $snids = implode(',', $snids);
                if ($cp) {
                    db()->update("moduleguid=''", $tbl_site_plugins, "id IN ({$plids}) AND moduleguid='{$guid}'");
                }
                if ($cs) {
                    db()->update("moduleguid=''", $tbl_site_snippets, "id IN ({$snids}) AND moduleguid='{$guid}'");
                }
                // reset cache
                $modx->clearCache();
            }
        }
        $opids = implode(',', $opids);
        db()->delete($tbl_site_module_depobj, "id IN ({$opids})");
        break;
}

// load record
$rs = db()->select('*', '[+prefix+]site_modules', "id='{$id}'");
$limit = db()->count($rs);
if ($limit > 1) {
    echo "<p>Multiple modules sharing same unique id. Please contact the Site Administrator.<p>";
    exit;
}

if ($limit < 1) {
    echo "<p>Module not found for id '$id'.</p>";
    exit;
}

$content = db()->getRow($rs);
$_SESSION['itemname'] = $content['name'];
if ($content['locked'] == 1 && $_SESSION['mgrRole'] != 1) {
    alert()->setError(3);
    alert()->dumpError();
}

?>
<script type="text/javascript">
    function removeDependencies() {
        if (confirm("<?= $_lang['confirm_delete_record'] ?>")) {
            documentDirty = false;
            document.mutate.op.value = "del";
            document.mutate.submit();
        }
    }

    function addSnippet() {
        openSelector("snip", "m", "setResource");
    }

    function addDocument() {
        openSelector("doc", "m", "setResource");
    }

    function addTemplate() {
        openSelector("tpl", "m", "setResource");
    }

    function addTV() {
        openSelector("tv", "m", "setResource");
    }

    function addChunk() {
        openSelector("chunk", "m", "setResource");
    }

    function addPlugin() {
        openSelector("plug", "m", "setResource");
    }

    function setResource(rt, ids) {
        if (ids.length == 0) return;
        document.mutate.op.value = "add";
        document.mutate.rt.value = rt;
        document.mutate.newids.value = ids.join(",");
        document.mutate.submit();
    }

    function openSelector(resource, mode, callback, w, h) {
        var win
        w = w ? w : 700;
        h = h ? h : 400;
        url = "index.php?a=84&sm=" + mode + "&rt=" + resource + "&cb=" + callback
        // center on parent
        if (window.screenX) {
            var x = window.screenX + (window.outerWidth - w) / 2;
            var y = window.screenY + (window.outerHeight - h) / 2;
        } else {
            var x = (screen.availWidth - w) / 2;
            var y = (screen.availHeight - h) / 2;
        }
        self.chkBoxArray = {}; //reset checkbox array;
        win = window.open(url, "resource_selector", "left=" + x + ",top=" + y + ",height=" + h + ",width=" + w + ",status=yes,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no");
        win.opener = self;
    }
</script>

<form name="mutate" method="post" action="index.php">
    <input type="hidden" name="a" value="113" />
    <input type="hidden" name="op" value="" />
    <input type="hidden" name="rt" value="" />
    <input type="hidden" name="newids" value="" />
    <input type="hidden" name="id" value="<?= $content['id'] ?>" />
    <h1><?= $_lang['module_resource_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <li class="mutate"><a href="index.php?a=106"><img
                        src="<?= $_style["icons_cancel"] ?>" /> <?= $_lang['cancel'] ?></a>
        </ul>
    </div>

    <div class="section">
        <div class="sectionHeader"><?= $content["name"] . " - " . $_lang['module_resource_title'] ?></div>
        <div class="sectionBody">
            <p><img src="<?= $_style["icons_modules"] ?>" alt=""
                    align="left" /><?= $_lang['module_resource_msg'] ?></p>
            <br />
            <!-- Dependencies -->
            <ul class="actionButtons">
                <li><a href="#" onclick="addSnippet();return false;"><img
                            src="<?= $_style["icons_add"] ?>" /> <?= $_lang['add_snippet'] ?></a></li>
                <li><a href="#" onclick="addPlugin();return false;"><img
                            src="<?= $_style["icons_add"] ?>" /> <?= $_lang['add_plugin'] ?></a></li>
            </ul>
            <?php
            $sql = "SELECT smd.id,COALESCE(ss.name,sp.name) as 'name'," .
                "CASE smd.type " .
                " WHEN 30 THEN 'Plugin' " .
                " WHEN 40 THEN 'Snippet' " .
                "END as 'type' " .
                "FROM " . $tbl_site_module_depobj . " smd " .
                "LEFT JOIN " . $tbl_site_plugins . " sp ON sp.id = smd.resource AND smd.type = '30' " .
                "LEFT JOIN " . $tbl_site_snippets . " ss ON ss.id = smd.resource AND smd.type = '40' " .
                "WHERE smd.module=$id ORDER BY smd.type,name ";
            $ds = db()->query($sql);
            if (!$ds) {
                echo "An error occured while loading module dependencies.";
            } else {
                include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
                $grd = new DataGrid('', $ds, 0); // set page size to 0 t show all items
                $grd->noRecordMsg = $_lang["no_records_found"];
                $grd->cssClass = "grid";
                $grd->columnHeaderClass = "gridHeader";
                $grd->itemClass = "gridItem";
                $grd->altItemClass = "gridAltItem";
                $grd->columns = $_lang["element_name"] . " ," . $_lang["type"];
                $grd->colTypes = "template:<label><input type='checkbox' name='depid[]' value='[+id+]'> [+value+]</label>";
                $grd->fields = "name,type";
                $grd->colWidths = '200';
                echo $grd->render();
            }
            ?>
            <ul class="actionButtons">
                <li><a style="margin-bottom:10px;" href="#" onclick="removeDependencies();return false;"><img
                            src="<?= $_style["icons_delete_document"] ?>" /> <?= $_lang['remove'] ?>
                    </a></li>
            </ul>
        </div>
    </div>
    <input type="submit" name="save" style="display:none">
</form>
