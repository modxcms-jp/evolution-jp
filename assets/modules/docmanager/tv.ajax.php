<?php
/**
 * This file includes slightly modified code from the MODx core distribution.
 */

define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', 'true');
$self = 'assets/modules/docmanager/tv.ajax.php';
$base_path = str_replace(['\\', $self], ['/', ''], __FILE__);
include_once($base_path . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;
include_once($base_path . "assets/modules/docmanager/classes/docmanager.class.php");
$modx->getSettings();

$dm = new DocManager($modx);
$dm->getLang();
$dm->getTheme();

checkCsrfToken();

$output = '';

if (!is_numeric(postv('tplID'))) {
    return;
}

$tplID = postv('tplID');
$rs = db()->select(
    '*',
    ["[+prefix+]site_tmplvars tv", "LEFT JOIN [+prefix+]site_tmplvar_templates tvtpl ON tv.id = tvtpl.tmplvarid"],
    "tvtpl.templateid ='" . $tplID . "'"
);
$total = db()->count($rs);

header('Content-Type: text/html; charset=' . $modx->config['modx_charset']);

if ($total > 0) {
    require(MODX_CORE_PATH . 'tmplvars.commands.inc.php');
    $output .= "<table class='mutate-tv-table' border='0' cellspacing='0' cellpadding='3'>";

    for ($i = 0; $i < $total; $i++) {
        $row = db()->getRow($rs);

        if ($i > 0 && $i < $total) {
            $output .= '<tr><td colspan="2"><div class="split"></div></td></tr>';
        }

        $output .= '<tr class="mutate-tv-row">
                        <td class="mutate-tv-label">
                                <label class="mutate-field-title" for="cb_update_tv_' . $row['id'] . '"><input type="checkbox" name="update_tv_' . $row['id'] . '" id="cb_update_tv_' . $row['id'] . '" value="yes" />&nbsp;' . $row['caption'] . '</label><br /><span class="comment">' . $row['description'] . '</span>
                        </td>
                        <td class="mutate-tv-value">';
        $base_url = $modx->config['base_url'];
        $output .= renderFormElement(
            $row['type'],
            $row['id'],
            $row['default_text'],
            $row['elements'],
            $row['value'],
            ' style="width:300px;"'
        );
        $output .= '</td></tr>';
    }
    $output .= '</table>';
} else {
    echo $dm->lang['DM_tv_no_tv'];
}
echo $output;


function renderFormElement($field_type, $field_id, $default_text, $field_elements, $field_value, $field_style = '')
{
    global $dm;
    global $base_url;

    $field_html = '';
    $field_value = ($field_value != "" ? $field_value : $default_text);

    switch ($field_type) {
        case 'text': // handler for regular text boxes
        case 'rawtext'; // non-htmlentity converted text boxes
        case 'email': // handles email input fields
        case 'number': // handles the input of numbers
            $field_html .= sprintf(
                '<input type="text" id="tv%s" name="tv%s" value="%s" %s tvtype="%s" onchange="documentDirty=true;" style="width:100%%" />',
                $field_id,
                $field_id,
                hsc($field_value),
                $field_style,
                $field_type
            );
            break;
        case 'textareamini': // handler for textarea mini boxes
            $field_html .= sprintf(
                '<textarea id="tv%s" name="tv%s" cols="40" rows="5" onchange="documentDirty=true;" style="width:100%%">%s</textarea>',
                $field_id,
                $field_id,
                hsc($field_value)
            );
            break;
        case 'textarea': // handler for textarea boxes
        case 'rawtextarea': // non-htmlentity convertex textarea boxes
        case 'htmlarea': // handler for textarea boxes (deprecated)
        case "richtext": // handler for textarea boxes
            $field_html .= sprintf(
                '<textarea id="tv%s" name="tv%s" cols="40" rows="15" onchange="documentDirty=true;" style="width:100%%;">%s</textarea>',
                $field_id,
                $field_id,
                hsc($field_value)
            );
            break;
        case 'date':
            $field_id = str_replace(['-', '.'], '_', urldecode($field_id));
            if ($field_value == '') $field_value = 0;
            $field_html .= sprintf(
                '<input id="tv%s" name="tv%s" class="DatePicker" type="text" value="%d" onblur="documentDirty=true;" />',
                $field_id,
                $field_id,
                $field_value == 0 || !isset($field_value) ? '' : $field_value
            );
            $field_html .= sprintf(
                ' <a onclick="document.forms[\'templatevariables\'].elements[\'tv%s\'].value=\'\';document.forms[\'templatevariables\'].elements[\'tv%s\'].onblur(); return true;" onmouseover="window.status=\'clear the date\'; return true;" onmouseout="window.status=\'\'; return true;" style="cursor:pointer; cursor:hand"><img src="media/style/%simages/icons/cal_nodate.gif" width="16" height="16" border="0" alt="No date"></a>',
                $field_id,
                $field_id,
                !empty($dm->theme) ? $dm->theme . "/" : ""
            );

            break;
        case 'dropdown': // handler for select boxes
            $field_html .= sprintf(
                '<select id="tv%s" name="tv%s" size="1" onchange="documentDirty=true;">',
                $field_id,
                $field_id
            );
            $index_list = ParseIntputOptions(ProcessTVCommand($field_elements, $field_id));
            foreach ($index_list as $item => $itemvalue) {
                [$item, $itemvalue] = (is_array($itemvalue)) ? $itemvalue : explode("==", $itemvalue);
                if (strlen($itemvalue) == 0) {
                    $itemvalue = $item;
                }
                $field_html .= sprintf(
                    '<option value="%s"%s>%s</option>',
                    hsc($itemvalue),
                    $itemvalue == $field_value ? ' selected="selected"' : '',
                    hsc($item)
                );
            }
            $field_html .= "</select>";
            break;
        case "listbox": // handler for select boxes
            $field_html .= sprintf(
                '<select id="tv%s" name="tv%s" onchange="documentDirty=true;" size="8">',
                $field_id,
                $field_id
            );
            $index_list = ParseIntputOptions(ProcessTVCommand($field_elements, $field_id));
            foreach ($index_list as $item => $itemvalue) {
                [$item, $itemvalue] = (is_array($itemvalue)) ? $itemvalue : explode("==", $itemvalue);
                if (strlen($itemvalue) == 0) {
                    $itemvalue = $item;
                }
                $field_html .= sprintf(
                    '<option value="%s"%s>%s</option>',
                    hsc($itemvalue),
                    $itemvalue == $field_value ? ' selected="selected"' : '', hsc($item)
                );
            }
            $field_html .= "</select>";
            break;
        case 'listbox-multiple': // handler for select boxes where you can choose multiple items
            $field_value = explode('||', $field_value);
            $field_html .= sprintf(
                '<select id="tv%s[]" name="tv%s[]" multiple="multiple" onchange="documentDirty=true;" size="8">',
                $field_id,
                $field_id
            );
            $index_list = ParseIntputOptions(ProcessTVCommand($field_elements, $field_id));
            foreach ($index_list as $item => $itemvalue) {
                [$item, $itemvalue] = (is_array($itemvalue)) ? $itemvalue : explode('==', $itemvalue);
                if (strlen($itemvalue) == 0) {
                    $itemvalue = $item;
                }
                $field_html .= sprintf(
                    '<option value="%s"%s>%s</option>',
                    hsc($itemvalue),
                    in_array($itemvalue, $field_value) ? ' selected="selected"' : '',
                    hsc($item)
                );
            }
            $field_html .= "</select>";
            break;
        case 'url': // handles url input fields
            $urls = ['' => '--', 'http://' => 'http://', 'https://' => 'https://', 'ftp://' => 'ftp://', 'mailto:' => 'mailto:'];
            $field_html = sprintf(
                '<table border="0" cellspacing="0" cellpadding="0"><tr><td><select id="tv%s_prefix" name="tv%s_prefix" onchange="documentDirty=true;">',
                $field_id,
                $field_id
            );
            foreach ($urls as $k => $v) {
                if (strpos($field_value, $v) === false) {
                    $field_html .= sprintf('<option value="%s">%s</option>', $v, $k);
                } else {
                    $field_value = str_replace($v, '', $field_value);
                    $field_html .= sprintf('<option value="%s" selected="selected">%s</option>', $v, $k);
                }
            }
            $field_html .= '</select></td><td>';
            $field_html .= sprintf(
                '<input type="text" id="tv%s" name="tv%s" value="%s" width="100" %s onchange="documentDirty=true;" /></td></tr></table>',
                $field_id,
                $field_id,
                hsc($field_value),
                $field_style
            );
            break;
        case 'checkbox': // handles check boxes
            $field_value = !is_array($field_value) ? explode('||', $field_value) : $field_value;
            $index_list = ParseIntputOptions(ProcessTVCommand($field_elements, $field_id));
            static $i = 0;
            foreach ($index_list as $item => $itemvalue) {
                [$item, $itemvalue] = (is_array($itemvalue)) ? $itemvalue : explode("==", $itemvalue);
                if (strlen($itemvalue) == 0) {
                    $itemvalue = $item;
                }
                $field_html .= sprintf(
                    '<input type="checkbox" value="%s" id="tv_%d" name="tv%s[]" %s onchange="documentDirty=true;" /><label for="tv_%d">%s</label><br />',
                    hsc($itemvalue),
                    $i,
                    $field_id,
                    in_array($itemvalue, $field_value) ? " checked='checked'" : "",
                    $i,
                    $item
                );
                $i++;
            }
            break;
        case 'option': // handles radio buttons
            $index_list = ParseIntputOptions(ProcessTVCommand($field_elements, $field_id));
            foreach ($index_list as $item => $itemvalue) {
                [$item, $itemvalue] = (is_array($itemvalue)) ? $itemvalue : explode("==", $itemvalue);
                if (strlen($itemvalue) == 0) {
                    $itemvalue = $item;
                }
                $field_html .= '<input type="radio" value="' . hsc($itemvalue) . '" name="tv' . $field_id . '" ' . ($itemvalue == $field_value ? 'checked="checked"' : '') . ' onchange="documentDirty=true;" />' . $item . '<br />';
            }
            break;
        case 'image':    // handles image fields using htmlarea image manager
            global $ResourceManagerLoaded;
            global $content, $use_editor, $which_editor;
            if (!$ResourceManagerLoaded && !(($content['richtext'] == 1 || getv('a') == 4) && $use_editor == 1 && $which_editor == 3)) {
                $field_html .= "
				<script type=\"text/javascript\">
						var lastImageCtrl;
						var lastFileCtrl;
						function OpenServerBrowser(url, width, height ) {
							var iLeft = (screen.width  - width) / 2 ;
							var iTop  = (screen.height - height) / 2 ;

							var sOptions = 'toolbar=no,status=no,resizable=yes,dependent=yes' ;
							sOptions += ',width=' + width ;
							sOptions += ',height=' + height ;
							sOptions += ',left=' + iLeft ;
							sOptions += ',top=' + iTop ;

							var oWindow = window.open( url, 'FCKBrowseWindow', sOptions ) ;
						}
						function BrowseServer(ctrl) {
							lastImageCtrl = ctrl;
							var w = screen.width * 0.7;
							var h = screen.height * 0.7;
							OpenServerBrowser('{$base_url}manager/media/browser/mcpuk/browser.php?Type=images', w, h);
						}

						function BrowseFileServer(ctrl) {
							lastFileCtrl = ctrl;
							var w = screen.width * 0.7;
							var h = screen.height * 0.7;
							OpenServerBrowser('{$base_url}manager/media/browser/mcpuk/browser.php?Type=files', w, h);
						}

						function SetUrl(url, width, height, alt){
							if(lastFileCtrl) {
								var c = document.templatevariables[lastFileCtrl];
								if(c) c.value = url;
								lastFileCtrl = '';
							} else if(lastImageCtrl) {
								var c = document.templatevariables[lastImageCtrl];
								if(c) c.value = url;
								lastImageCtrl = '';
							}
						}
				</script>";
                $ResourceManagerLoaded = true;
            }
            $field_html .= sprintf(
                '<input type="text" id="tv%s" name="tv%s"  value="%s" %s onchange="documentDirty=true;" />&nbsp;<input type="button" value="%s" onclick="BrowseServer(\'tv%s\')" />',
                $field_id,
                $field_id,
                $field_value,
                $field_style,
                $dm->lang['insert'],
                $field_id
            );
            break;
        case 'file': // handles the input of file uploads
            /* Modified by Timon for use with resource browser */
            global $ResourceManagerLoaded;
            global $content, $use_editor, $which_editor;
            if (!$ResourceManagerLoaded && !(($content['richtext'] == 1 || getv('a') == 4) && $use_editor == 1 && $which_editor == 3)) {
                /* I didn't understand the meaning of the condition above, so I left it untouched ;-) */
                $field_html .= "
				<script type=\"text/javascript\">
						var lastImageCtrl;
						var lastFileCtrl;
						function OpenServerBrowser(url, width, height ) {
							var iLeft = (screen.width  - width) / 2 ;
							var iTop  = (screen.height - height) / 2 ;

							var sOptions = 'toolbar=no,status=no,resizable=yes,dependent=yes' ;
							sOptions += ',width=' + width ;
							sOptions += ',height=' + height ;
							sOptions += ',left=' + iLeft ;
							sOptions += ',top=' + iTop ;

							var oWindow = window.open( url, 'FCKBrowseWindow', sOptions ) ;
						}

							function BrowseServer(ctrl) {
							lastImageCtrl = ctrl;
							var w = screen.width * 0.7;
							var h = screen.height * 0.7;
							OpenServerBrowser('{$base_url}manager/media/browser/mcpuk/browser.php?Type=images', w, h);
						}

						function BrowseFileServer(ctrl) {
							lastFileCtrl = ctrl;
							var w = screen.width * 0.7;
							var h = screen.height * 0.7;
							OpenServerBrowser('{$base_url}manager/media/browser/mcpuk/browser.php?Type=files', w, h);
						}

						function SetUrl(url, width, height, alt){
							if(lastFileCtrl) {
								var c = document.templatevariables[lastFileCtrl];
								if(c) c.value = url;
								lastFileCtrl = '';
							} else if(lastImageCtrl) {
								var c = document.templatevariables[lastImageCtrl];
								if(c) c.value = url;
								lastImageCtrl = '';
							} else {
								return;
							}
						}
				</script>";
                $ResourceManagerLoaded = true;
            }
            $field_html .= sprintf(
                '<input type="text" id="tv%s" name="tv%s"  value="%s" %s onchange="documentDirty=true;" />&nbsp;<input type="button" value="%s" onclick="BrowseFileServer(\'tv%s\')" />',
                $field_id,
                $field_id,
                $field_value,
                $field_style,
                $dm->lang['insert'],
                $field_id
            );

            break;
        default: // the default handler -- for errors, mostly
            $field_html .= sprintf(
                '<input type="text" id="tv%s" name="tv%s" value="%s" %s onchange="documentDirty=true;" />',
                $field_id,
                $field_id,
                hsc($field_value),
                $field_style
            );
    } // end switch statement
    return $field_html;
} // end renderFormElement function

function ParseIntputOptions($v)
{
    if (is_array($v)) {
        return $v;
    }

    if (!db()->isResult($v)) {
        return explode('||', $v);
    }

    $a = [];
    while ($cols = db()->getRow($v, 'num')) {
        $a[] = $cols;
    }
    return $a;
}
