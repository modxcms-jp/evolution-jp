<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('edit_template') && $_REQUEST['a'] == '301') {
    alert()->setError(3);
    alert()->dumpError();
}
if (!evo()->hasPermission('new_template') && $_REQUEST['a'] == '300') {
    alert()->setError(3);
    alert()->dumpError();
}

if (isset($_REQUEST['id'])) {
    $id = (int)$_REQUEST['id'];
} else {
    $id = 0;
}

// check to see the variable editor isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', "action=301 AND id='{$id}'");
$total = db()->count($rs);
if ($total > 1) {
    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf($_lang['lock_msg'], $row['username'], ' template variable');
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
}
// end check for lock

// make sure the id's a number
if (!is_numeric($id)) {
    echo 'Passed ID is NaN!';
    exit;
}

global $content;
$content = array();
if (isset($_GET['id']) && preg_match('@^[0-9]+$@', $_GET['id'])) {
    $rs = db()->select('*', '[+prefix+]site_tmplvars', "id={$id}");
    $total = db()->count($rs);
    if ($total > 1) {
        echo 'Oops, Multiple variables sharing same unique id. Not good.';
        exit;
    }
    if ($total < 1) {
        header("Location: /index.php?id={$site_start}");
    }
    $content = db()->getRow($rs);
    $_SESSION['itemname'] = $content['caption'];
} else {
    $_SESSION['itemname'] = "New Template Variable";
}

$form_v = $modx->manager->loadFormValues();
if ($form_v) {
    $content = array_merge($content, $form_v);
}

// get available RichText Editors
$RTEditors = '';
$tmp = array('forfrontend' => 1);
$evtOut = evo()->invokeEvent('OnRichTextEditorRegister', $tmp);
if (is_array($evtOut)) {
    $RTEditors = implode(',', $evtOut);
}

$form_elements = '<textarea name="elements" maxlength="65535" style="width:400px;height:110px;" class="inputBox phptextarea">' . hsc($content['elements']) . "</textarea>\n";

$tooltip_tpl = '<img src="[+src+]" title="[+title+]" alt="[+alt+]" class="tooltip" onclick="alert(this.alt);" style="cursor:help" />';
$ph = array();
$ph['src'] = $_style['icons_tooltip_over'];
$ph['title'] = $_lang['tmplvars_input_option_msg'];
$ph['alt'] = $_lang['tmplvars_input_option_msg'];
$tooltip_input_option = $modx->parseText($tooltip_tpl, $ph);

?>
<script language="JavaScript">
    function duplicaterecord() {
        if (confirm("<?= $_lang['confirm_duplicate_record'] ?>") == true) {
            documentDirty = false;
            document.location.href = "index.php?id=<?= $_REQUEST['id']; ?>&a=304";
        }
    }

    function deletedocument() {
        if (confirm("<?= $_lang['confirm_delete_tmplvars']; ?>") == true) {
            documentDirty = false;
            document.location.href = "index.php?id=" + document.mutate.id.value + "&a=303";
        }
    }

    // Widget Parameters
    var widgetParams = {}; // name = description;datatype;default or list values - datatype: int, string, list : separated by comma (,)
    widgetParams['date'] = '&dateformat=Date Format;string;%Y年%m月%d日 &default=If no value, use current date;list;Yes,No;No';
    widgetParams['string'] = '&stringformat=String Format;list;Zen-Han,Han-Zen,Upper Case,Lower Case,Sentence Case,Capitalize,nl2br,Number Format,HtmlSpecialChars,HtmlEntities';
    widgetParams['delim'] = '&delim=Delimiter;string;,';
    widgetParams['hyperlink'] = '&text=Display Text;string; &title=Title;string; &linkclass=Class;string &linkstyle=Style;string &target=Target;string &linkattrib=Attributes;string';
    widgetParams['htmltag'] = '&tagoutput=Content;textarea;[+value+] &tagname=Tag Name;string;div &tagid=Tag ID;string &tagclass=Class;string &tagstyle=Style;string &tagattrib=Attributes;string';
    widgetParams['datagrid'] = '&cdelim=Column Delimiter;list;%2C,tab,||,:: &cwrap=Column Wrapper;string;" &enc=Src Encode;list;utf-8,sjis-win,sjis,eucjp-win,euc-jp,jis,auto &detecthead=Detect Header;list;first line,none;first line &cols=Column Names;string &cwidth=Column Widths;string &calign=Column Alignments;string &ccolor=Column Colors;string &ctype=Column Types;string &cpad=Cell Padding;string; &cspace=Cell Spacing;string; &psize=Page Size;int;100 &ploc=Pager Location;list;top-right,top-left,bottom-left,bottom-right,both-right,both-left; &pclass=Pager Class;string &pstyle=Pager Style;string &head=Header Text;string &foot=Footer Text;string &tblc=Grid Class;string &tbls=Grid Style;string &itmc=Item Class;string; &itms=Item Style;string &aitmc=Alt Item Class;string &aitms=Alt Item Style;string &chdrc=Column Header Class;string &chdrs=Column Header Style;string;&egmsg=Empty message;string;No records found;';
    widgetParams['richtext'] = '&w=Width;string;100% &h=Height;string;300px &edt=Editor;list;<?= $RTEditors; ?>';
    widgetParams['image'] = '&imgoutput=Src;textarea;[+value+] &alttext=Alternate Text;string &align=Align;list;none,baseline,top,middle,bottom,texttop,absmiddle,absbottom,left,right &name=Name;string &imgclass=Class;string &id=ID;string &imgstyle=Style;string &imgattrib=Other Attribs;string';
    widgetParams['custom_widget'] = '&output=Output;textarea;[+value+]';

    // Current Params
    var currentParams = {};
    var lastdf, lastmod = {};

    function showParameters(ctrl) {
        var c, p, df, cp;
        var ar, desc, value, key, dt;

        currentParams = {}; // reset;

        if (ctrl) {
            f = ctrl.form;
        } else {
            f = document.forms['mutate'];
            if (!f) return;
            ctrl = f.display;
        }
        cp = f.params.value.split("&"); // load current setting once

        // get display format
        df = lastdf = ctrl.options[ctrl.selectedIndex].value;

        // load last modified param values
        if (lastmod[df]) cp = lastmod[df].split("&");
        for (p = 0; p < cp.length; p++) {
            cp[p] = (cp[p] + '').replace(/^\s|\s$/, ""); // trim
            ar = cp[p].split("=");
            currentParams[ar[0]] = ar[1];
        }

        // setup parameters
        tr = document.getElementById('displayparamrow');
        dp = (widgetParams[df]) ? widgetParams[df].split("&") : "";
        if (!dp) tr.style.display = 'none';
        else {
            t = '<table width="400" style="margin-bottom:3px;background-color:#EEEEEE" cellpadding="2" cellspacing="1"><thead><tr><td width="50%"><?= $_lang['parameter']; ?></td><td width="50%"><?= $_lang['value']; ?></td></tr></thead>';
            for (p = 0; p < dp.length; p++) {
                dp[p] = (dp[p] + '').replace(/^\s|\s$/, ""); // trim
                ar = dp[p].split("=");
                key = ar[0]; // param
                ar = (ar[1] + '').split(";");
                desc = ar[0]; // description
                dt = ar[1]; // data type
                value = decode((currentParams[key]) ? currentParams[key] : (dt == 'list') ? ar[3] : (ar[2]) ? ar[2] : '');
                if (value != currentParams[key]) currentParams[key] = value;
                value = (value + '').replace(/^\s|\s$/, ""); // trim
                value = value.replace(/&/g, "&amp;"); // replace & with &quot;
                value = value.replace(/\"/g, "&quot;"); // replace double quotes with &quot;
                if (dt) {
                    switch (dt) {
                        case 'int':
                        case 'float':
                            c = '<input type="text" name="prop_' + key + '" value="' + value + '" size="30" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)" />';
                            break;
                        case 'list':
                            c = '<select name="prop_' + key + '" height="1" style="width:168px" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)">';
                            ls = (ar[2] + '').split(",");
                            if (!currentParams[key] || currentParams[key] == 'undefined') {
                                currentParams[key] = ls[0]; // use first list item as default
                            }
                            for (i = 0; i < ls.length; i++) {
                                c += '<option value="' + decode(ls[i]) + '"' + ((decode(ls[i]) == value) ? ' selected="selected"' : '') + '>' + decode(ls[i]) + '</option>';
                            }
                            c += '</select>';
                            break;
                        case 'textarea':
                            c = '<textarea class="inputBox phptextarea" name="prop_' + key + '" cols="25" style="width:320px;" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)" >' + value + '</textarea>';
                            break;
                        default: // string
                            c = '<input type="text" name="prop_' + key + '" value="' + value + '" size="30" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)" />';
                            break;

                    }
                    t += '<tr><td bgcolor="#FFFFFF" width="50%">' + desc + '</td><td bgcolor="#FFFFFF" width="50%">' + c + '</td></tr>';
                }

            }
            t += '</table>';
            td = document.getElementById('displayparams');
            td.innerHTML = t;
            tr.style.display = '';
        }
        implodeParameters();
    }

    function setParameter(key, dt, ctrl) {
        var v;
        if (!ctrl) return null;
        switch (dt) {
            case 'int':
                ctrl.value = parseInt(ctrl.value);
                if (isNaN(ctrl.value)) ctrl.value = 0;
                v = ctrl.value;
                break;
            case 'float':
                ctrl.value = parseFloat(ctrl.value);
                if (isNaN(ctrl.value)) ctrl.value = 0;
                v = ctrl.value;
                break;
            case 'list':
                v = ctrl.options[ctrl.selectedIndex].value;
                break;
            case 'textarea':
                v = ctrl.value + '';
                break;
            default:
                v = ctrl.value + '';
                break;
        }
        currentParams[key] = v;
        implodeParameters();
    }

    function resetParameters() {
        document.mutate.params.value = "";
        lastmod[lastdf] = "";
        showParameters();
    }

    // implode parameters
    function implodeParameters() {
        var v, p, s = '';
        for (p in currentParams) {
            v = currentParams[p];
            if (v) s += '&' + p + '=' + encode(v);
        }
        document.forms['mutate'].params.value = s;
        if (lastdf) lastmod[lastdf] = s;
    }

    function encode(s) {
        s = s + '';
        s = s.replace(/\;/g, '%3B'); // ;
        s = s.replace(/\=/g, '%3D'); // =
        s = s.replace(/\&/g, '%26'); // &
        s = s.replace(/\,/g, '%2C'); // ,
        s = s.replace(/\\/g, '%5C'); // \

        return s;
    }

    function decode(s) {
        s = s + '';
        s = s.replace(/\%3B/g, ';'); // =
        s = s.replace(/\%3D/g, '='); // =
        s = s.replace(/\%26/g, '&'); // &
        s = s.replace(/\%2C/g, ','); // ,
        s = s.replace(/\%5C/g, '\\'); // \

        return s;
    }

    setTimeout('showParameters()', 10);
</script>

<form name="mutate" id="mutate" method="post" action="index.php" enctype="multipart/form-data">
    <?php
    // invoke OnTVFormPrerender event
    $tmp = array('id' => $id);
    $evtOut = evo()->invokeEvent('OnTVFormPrerender', $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
    <input type="hidden" name="id" value="<?= $content['id']; ?>">
    <input type="hidden" name="a" value="302">
    <input type="hidden" name="mode" value="<?= $_GET['a']; ?>">
    <input type="hidden" name="params" value="<?= hsc($content['display_params']); ?>">

    <h1><?= $_lang['tmplvars_title'];
        if ($id) {
            echo "(ID:{$id})";
        } ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_template')) : ?>
                <li id="Button1" class="mutate">
                    <a href="#" onclick="documentDirty=false;jQuery('#mutate').submit();jQuery('#Button1').hide();jQuery('input,textarea,select').addClass('readonly');">
                        <img src="<?= $_style["icons_save"] ?>" /> <?= $_lang['update'] ?>
                    </a><span class="and"> + </span>
                    <select id="stay" name="stay">
                        <option id="stay1" value="1" <?= $_REQUEST['stay'] == '1' ? ' selected=""' : '' ?>><?= $_lang['stay_new'] ?></option>
                        <option id="stay2" value="2" <?= $_REQUEST['stay'] == '2' ? ' selected="selected"' : '' ?>><?= $_lang['stay'] ?></option>
                        <option id="stay3" value="" <?= $_REQUEST['stay'] == '' ? ' selected=""' : '' ?>><?= $_lang['close'] ?></option>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if ($_GET['a'] == '301') {
                $params = array(
                    'onclick' => 'duplicaterecord();',
                    'icon' => $_style['icons_resource_duplicate'],
                    'label' => $_lang['duplicate']
                );
                if (evo()->hasPermission('new_template')) {
                    echo $modx->manager->ab($params);
                }
                $params = array(
                    'onclick' => 'deletedocument();',
                    'icon' => $_style['icons_delete_document'],
                    'label' => $_lang['delete']
                );
                if (evo()->hasPermission('delete_template')) {
                    echo $modx->manager->ab($params);
                }
            }
            $params = array(
                'onclick' => "document.location.href='index.php?a=76';",
                'icon' => $_style['icons_cancel'],
                'label' => $_lang['cancel']
            );
            echo $modx->manager->ab($params);
            ?>
        </ul>
    </div>

    <div class="sectionBody">
        <div class="tab-pane" id="tmplvarsPane">
            <div class="tab-page" id="tabGeneral">
                <h2 class="tab"><?= $_lang['settings_general']; ?></h2>
                <table>
                    <tr>
                        <th align="left"><?= $_lang['tmplvars_name']; ?></th>
                        <td align="left"><span style="font-family:'Courier New', Courier, mono">[*</span><input name="name" type="text" maxlength="50" value="<?= hsc($content['name']); ?>" class="inputBox" style="width:300px;"><span style="font-family:'Courier New', Courier, mono">*]</span></td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['tmplvars_caption']; ?></th>
                        <td align="left"><input name="caption" type="text" maxlength="80" value="<?= hsc($content['caption']); ?>" class="inputBox" style="width:300px;"></td>
                    </tr>

                    <tr>
                        <th align="left"><?= $_lang['tmplvars_type']; ?></th>
                        <td align="left">
                            <select id="type" name="type" size="1" class="inputBox" style="width:300px;">
                                <?php
                                $option = array();
                                $option['custom_tv'] = 'Custom Form';
                                $option['text'] = 'Text';
                                $option['textarea'] = 'Textarea';
                                $option['textareamini'] = 'Textarea (Mini)';
                                $option['richtext'] = 'RichText';
                                $option['dropdown'] = 'DropDown List Menu';
                                $option['listbox'] = 'Listbox (Single-Select)';
                                $option['listbox-multiple'] = 'Listbox (Multi-Select)';
                                $option['option'] = 'Radio Options';
                                $option['checkbox'] = 'Check Box';
                                $option['image'] = 'Image';
                                $option['file'] = 'File';
                                $option['url'] = 'URL';
                                $option['email'] = 'Email';
                                $option['number'] = 'Number';
                                $option['tel'] = 'Telephone';
                                $option['zipcode'] = 'Zip Code';
                                $option['date'] = 'DateTime';
                                $option['dateonly'] = 'DateOnly';
                                $option['hidden'] = 'Hidden';
                                $result = db()->select('name', '[+prefix+]site_snippets', "name like'input:%'");
                                if (0 < db()->count($result)) {
                                    while ($row = db()->getRow($result)) {
                                        $input_name = trim(substr($row['name'], 6));
                                        $option[strtolower($input_name)] = ucwords(strtolower($input_name));
                                    }
                                }
                                $result = db()->select(
                                    'name',
                                    '[+prefix+]site_plugins',
                                    "name like'input:%' and disabled!=1"
                                );
                                if (0 < db()->count($result)) {
                                    while ($row = db()->getRow($result)) {
                                        $input_name = trim(substr($row['name'], 6));
                                        $option[strtolower($input_name)] = ucwords(strtolower($input_name));
                                    }
                                }
                                if ($content['type'] == '') {
                                    $content['type'] == 'text';
                                }
                                foreach ($option as $k => $v) {
                                    $selected = '';
                                    if (empty($content['type'])) {
                                        $content['type'] = 'text';
                                    }
                                    if (strtolower($content['type']) == strtolower($k)) {
                                        $selected = 'selected="selected"';
                                    }
                                    $row[$k] = '<option value="' . $k . '" ' . $selected . '>' . $v . '</option>';
                                }
                                echo join("\n", $row);
                                ?>
                            </select>
                        </td>
                    </tr>
                    <?php
                    switch ($content['type']) {
                        case 'dropdown':
                        case 'listbox':
                        case 'listbox-multiple':
                        case 'checkbox':
                        case 'option':
                        case 'custom_tv':
                            $display = '';
                            break;
                        default:
                            $display = 'style="display:none;"';
                    }
                    $res1 = db()->select('name', '[+prefix+]site_plugins', "name like'input:%' and disabled!=1");
                    $name1 = strtolower(substr(db()->getValue($res1), 6));
                    $res2 = db()->select('name', '[+prefix+]site_snippets', "name like'input:%'");
                    $name2 = strtolower(substr(db()->getValue($res2), 6));
                    if ($name1 == $content['type'] || $name2 == $content['type']) {
                        $display = '';
                    }
                    ?>
                    <tr id="inputoption" <?= $display; ?>>
                        <th align="left" valign="top"><?= $_lang['tmplvars_elements']; ?></th>
                        <td align="left" nowrap="nowrap"><?= $form_elements . $tooltip_input_option; ?></td>
                    </tr>
                    <tr>
                        <th align="left" valign="top"><?= $_lang['tmplvars_default']; ?></th>
                        <td align="left" nowrap="nowrap"><textarea name="default_text" type="text" class="inputBox phptextarea" rows="5" style="width:400px;"><?= hsc($content['default_text']); ?></textarea><?= $tooltip_tv_binding; ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['tmplvars_widget']; ?></th>
                        <td align="left">
                            <select name="display" size="1" class="inputBox" style="width:400px;" onchange="showParameters(this);">
                                <option value="" <?= selected($content['display'] == ''); ?>>&nbsp;</option>
                                <option value="custom_widget" <?= selected($content['display'] === 'custom_widget'); ?>>
                                    Custom Processor
                                </option>
                                <option value="image" <?= selected($content['display'] === 'image'); ?>>Image
                                </option>
                                <option value="hyperlink" <?= selected($content['display'] === 'hyperlink'); ?>>
                                    Hyperlink
                                </option>
                                <option value="htmltag" <?= selected($content['display'] === 'htmltag'); ?>>HTML
                                    Generic Tag
                                </option>
                                <option value="string" <?= selected($content['display'] === 'string'); ?>>String
                                    Formatter
                                </option>
                                <option value="date" <?= selected($content['display'] === 'date'); ?>>Date
                                    Formatter
                                </option>
                                <option value="unixtime" <?= selected($content['display'] === 'unixtime'); ?>>
                                    Unixtime
                                </option>
                                <option value="delim" <?= selected($content['display'] === 'delim'); ?>>Delimited
                                    List
                                </option>
                                <option value="datagrid" <?= selected($content['display'] === 'datagrid'); ?>>
                                    Data Grid
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr id="displayparamrow">
                        <td valign="top" align="left"><?= $_lang['tmplvars_widget_prop']; ?>
                            <div style="padding-top:8px;"><a href="javascript://" onclick="resetParameters(); return false"><img src="<?= $_style['icons_refresh']; ?>" alt="<?= $_lang['tmplvars_reset_params']; ?>"></a></div>
                        </td>
                        <td align="left" id="displayparams">&nbsp;</td>
                    </tr>
                </table>
            </div>

            <!-- Template Permission -->
            <div class="tab-page" id="tabPerm">
                <h2 class="tab"><?= $_lang['tmplvar_tmpl_access']; ?></h2>
                <p><?= $_lang['tmplvar_tmpl_access_msg']; ?></p>
                <style type="text/css">
                    label {
                        display: block;
                    }
                </style>
                <table width="100%" cellspacing="0" cellpadding="0">
                    <?php
                    $from = '[+prefix+]site_templates as tpl';
                    $from .= " LEFT JOIN [+prefix+]site_tmplvar_templates as stt ON stt.templateid=tpl.id AND stt.tmplvarid='{$id}'";
                    $rs = db()->select('id,templatename,tmplvarid', $from);
                    ?>
                    <tr>
                        <td>
                            <?php
                            if (0 < db()->count($rs)) :
                                while ($row = db()->getRow($rs)) :
                                    if ($_REQUEST['a'] == '300' && $modx->config['default_template'] == $row['id']) {
                                        $checked = true;
                                    } elseif (getv('tpl') == $row['id']) {
                                        $checked = true;
                                    } elseif ($id == 0 && is_array($_POST['template'])) {
                                        $checked = in_array($row['id'], $_POST['template']);
                                    } else {
                                        $checked = $row['tmplvarid'];
                                    }

                                    $ph['checked'] = $checked ? 'checked' : '';
                                    $ph['id'] = $row['id'];
                                    $ph['templatename'] = $row['templatename'];
                                    echo $modx->parseText(
                                        '<label><input type="checkbox" name="template[]" value="[+id+]" [+checked+] /> [[+id+]] [+templatename+]</label>',
                                        $ph
                                    );
                                endwhile;
                            endif;
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="tab-page" id="tabInfo">
                <h2 class="tab"><?= $_lang['settings_properties']; ?></h2>
                <table>
                    <tr>
                        <th align="left"><?= $_lang['existing_category']; ?></th>
                        <td align="left">
                            <select name="categoryid" style="width:300px;">
                                <option value="0"><?= $_lang["no_category"]; ?></option>
                                <?php
                                $ds = $modx->manager->getCategories();
                                if ($ds) {
                                    foreach ($ds as $n => $v) {
                                        echo "<option value='" . $v['id'] . "'" . ($content["category"] == $v["id"] ? " selected='selected'" : "") . ">" . hsc($v["category"]) . "</option>";
                                    }
                                }
                                ?>
                                <option value="-1">&gt;&gt; <?= $_lang["new_category"]; ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr id="newcategry" style="display:none;">
                        <th align="left" valign="top" style="padding-top:5px;"><?= $_lang['new_category']; ?></th>
                        <td align="left" valign="top" style="padding-top:5px;"><input name="newcategory" type="text" maxlength="45" value="" class="inputBox" style="width:300px;"></td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['tmplvars_description']; ?></th>
                        <td align="left"><textarea name="description" style="padding:0;height:4em;"><?= hsc($content['description']); ?></textarea>
                        </td>
                    </tr>
                    <?php if (evo()->hasPermission('save_template') == 1) { ?>
                        <tr>
                            <td align="left" colspan="2"><label><input name="locked" value="on" type="checkbox" <?= $content['locked'] == 1 ? "checked='checked'" : ""; ?> class="inputBox" />
                                    <b><?= $_lang['lock_tmplvars']; ?></b> <span class="comment"><?= $_lang['lock_tmplvars_msg']; ?></span></label>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <th align="left"><?= $_lang['tmplvars_rank']; ?></th>
                        <td align="left"><input name="rank" type="text" maxlength="4" value="<?= (isset($content['rank'])) ? $content['rank'] : 0; ?>" class="inputBox" style="width:300px;"></td>
                    </tr>
                </table>
            </div>

            <!-- Access Permissions -->
            <?php
            if ($modx->config['use_udperms'] == 1) {
                $groupsarray = array();

                // fetch permissions for the variable
                $rs = db()->select('documentgroup', '[+prefix+]site_tmplvar_access', "tmplvarid='{$id}'");
                while ($row = db()->getRow($rs)) {
                    $groupsarray[] = $row['documentgroup'];
                }
                if (evo()->hasPermission('access_permissions')) {
            ?>
                    <div class="tab-page" id="tabAccess">
                        <h2 class="tab"><?= $_lang['access_permissions']; ?></h2>
                        <script type="text/javascript">
                            function makePublic(b) {
                                var notPublic = false;
                                var f = document.forms['mutate'];
                                var chkpub = f['chkalldocs'];
                                var chks = f['docgroups[]'];
                                if (!chks && chkpub) {
                                    chkpub.checked = true;
                                    return false;
                                } else if (!b && chkpub) {
                                    if (!chks.length) notPublic = chks.checked;
                                    else
                                        for (i = 0; i < chks.length; i++)
                                            if (chks[i].checked) notPublic = true;
                                    chkpub.checked = !notPublic;
                                } else {
                                    if (!chks.length) chks.checked = (b) ? false : chks.checked;
                                    else
                                        for (i = 0; i < chks.length; i++)
                                            if (b) chks[i].checked = false;
                                    chkpub.checked = true;
                                }
                            }
                        </script>
                        <p><?= $_lang['tmplvar_access_msg']; ?></p>
                        <?php
                        $chk = '';
                        $rs = db()->select('name, id', '[+prefix+]documentgroup_names');
                        if (empty($groupsarray) && is_array($_POST['docgroups']) && empty($_POST['id'])) {
                            $groupsarray = $_POST['docgroups'];
                        }
                        $number_of_g = 0;
                        while ($row = db()->getRow($rs)) {
                            $checked = in_array($row['id'], $groupsarray);
                            if (evo()->hasPermission('access_permissions')) {
                                if ($checked) {
                                    $notPublic = true;
                                }
                                $chks .= '<label><input type="checkbox" name="docgroups[]" value="' . $row['id'] . '"' . ($checked ? ' checked="checked"' : '') . ' onclick="makePublic(false)" />' . $row['name'] . '</label>';
                                $number_of_g++;
                            } elseif ($checked) {
                                echo '<input type="hidden" name="docgroups[]"  value="' . $row['id'] . '" />';
                            }
                        }
                        if (evo()->hasPermission('access_permissions')) {
                            $disabled = ($number_of_g === 0) ? 'disabled="disabled"' : '';
                            $chks = '<label><input type="checkbox" name="chkalldocs" ' . (!$notPublic ? "checked='checked'" : '') . ' onclick="makePublic(true)" ' . $disabled . ' /><span class="warning">' . $_lang['all_doc_groups'] . '</span></label>' . $chks;
                        }
                        echo $chks;
                        ?>
                    </div>
            <?php
                }
            }
            ?>
            <div class="tab-page" id="tabHelp">
                <h2 class="tab">ヘルプ</h2>
                <?= $_lang['tmplvars_msg']; ?>
            </div>
            <?php
            // invoke OnTVFormRender event
            $tmp = array('id' => $id);
            $evtOut = evo()->invokeEvent('OnTVFormRender', $tmp);
            if (is_array($evtOut)) {
                echo implode('', $evtOut);
            }
            ?>
        </div>
    </div>
</form>
<script>
    tpTmplvars = new WebFXTabPane(document.getElementById("tmplvarsPane"), false);
    var readonly = <?= ($content['locked'] === '1' || $content['locked'] === 'on') ? '1' : '0'; ?>;
    if (readonly == 1) {
        jQuery('textarea,input[type=text]').prop('readonly', true);
        jQuery('select').addClass('readonly');
        jQuery('#Button1').hide();
        jQuery('input[name="locked"]').click(function() {
            jQuery('#Button1').toggle();
        });
    }
    jQuery('input[name="locked"]').click(function() {
        jQuery('textarea,input[type=text]').prop('readonly', jQuery(this).prop('checked'));
        jQuery('select').toggleClass('readonly');
    });
    jQuery('select[name="categoryid"]').change(function() {
        if (jQuery(this).val() == '-1') {
            jQuery('#newcategry').fadeIn();
        } else {
            jQuery('#newcategry').fadeOut();
            jQuery('input[name="newcategory"]').val('');
        }
    });
    var itype = jQuery('#type');
    itype.change(function() {
        switch (itype.val()) {
            case 'dropdown':
            case 'listbox':
            case 'listbox-multiple':
            case 'checkbox':
            case 'option':
            case 'custom_tv':
                <?php
                $result = db()->select('name', '[+prefix+]site_plugins', "name like'input:%' and disabled!=1");
                while ($row = db()->getRow($result)) {
                    $type = strtolower(str_replace("input:", "", $row["name"]));
                    echo "\t\t\tcase '" . $type . "':\n";
                }
                ?>
                jQuery('#inputoption').fadeIn();
                var ctv = '<textarea name="[+name+]">[+value+]</textarea>';
                if (itype.val() == 'custom_tv') {
                    jQuery('#inputoption th:first').css('visibility', 'hidden');
                    if (jQuery('#inputoption textarea').val() == '') jQuery('#inputoption textarea').val(ctv);
                } else {
                    jQuery('#inputoption th:first').css('visibility', 'visible');
                    if (jQuery('#inputoption textarea').val() == ctv) jQuery('#inputoption textarea').val('');
                }
                break;
            default:
                jQuery('#inputoption').fadeOut();
        }
    });
</script>