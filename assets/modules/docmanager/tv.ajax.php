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

$tplID = (int)postv('tplID');
$rs = db()->select(
    'tv.*, tvtpl.rank AS template_rank',
    ["[+prefix+]site_tmplvars tv", "INNER JOIN [+prefix+]site_tmplvar_templates tvtpl ON tv.id = tvtpl.tmplvarid"],
    "tvtpl.templateid ='" . $tplID . "'",
    'tvtpl.rank, tv.rank, tv.id'
);
$total = db()->count($rs);

header('Content-Type: text/html; charset=' . $modx->config('modx_charset', 'UTF-8'));

if ($total > 0) {
    require(MODX_CORE_PATH . 'tmplvars.inc.php');
    $output .= "<table class='mutate-tv-table' border='0' cellspacing='0' cellpadding='3'>";

    for ($i = 0; $i < $total; $i++) {
        $row = db()->getRow($rs);
        $defaultText = $row['default_text'] ?? '';
        $fieldElements = $row['elements'] ?? '';
        $row['form_name'] = 'templatevariables';

        if ($i > 0 && $i < $total) {
            $output .= '<tr><td colspan="2"><div class="split"></div></td></tr>';
        }

        $output .= '<tr class="mutate-tv-row">
                        <td class="mutate-tv-label">
                                <label class="mutate-field-title" for="cb_update_tv_' . $row['id'] . '"><input type="checkbox" name="update_tv_' . $row['id'] . '" id="cb_update_tv_' . $row['id'] . '" value="yes" />&nbsp;' . hsc($row['caption']) . '</label><br /><span class="comment">' . hsc($row['description']) . '</span>
                        </td>
                        <td class="mutate-tv-value">';
        $output .= renderFormElement(
            $row['type'],
            $row['id'],
            $defaultText,
            $fieldElements,
            $defaultText,
            'width:300px;',
            $row
        );
        $output .= '</td></tr>';
    }
    $output .= '</table>';
} else {
    echo $dm->lang['DM_tv_no_tv'];
}
echo $output;
