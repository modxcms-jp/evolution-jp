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

// 管理画面にログインしていて、モジュールを実行できる人だけ
if (!sessionv('mgrValidated') || !evo()->hasPermission('exec_module')) {
    http_response_code(403);
    exit;
}

checkCsrfToken();

$output = '';

$tplID = post_uintvar('tplID');
if ($tplID === null) {
    return;
}

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
    require_once MODX_CORE_PATH . 'field_row.class.inc.php';
    $rows = [];

    for ($i = 0; $i < $total; $i++) {
        $row = db()->getRow($rs);
        $defaultText = $row['default_text'] ?? '';
        $fieldElements = $row['elements'] ?? '';
        $row['form_name'] = 'templatevariables';

        // 一括変更する TV のチェックボックスを見出しにする
        $rows[] = EvoFieldRow::render([
            'label' => '<label for="cb_update_tv_' . $row['id'] . '"><input type="checkbox" name="update_tv_' . $row['id'] . '" id="cb_update_tv_' . $row['id'] . '" value="yes" />&nbsp;' . hsc($row['caption']) . '</label>',
            'body' => renderFormElement(
                $row['type'],
                $row['id'],
                $defaultText,
                $fieldElements,
                $defaultText,
                'width:300px;',
                $row
            ),
            'help' => tv_input_description_html($row['description']),
            'class' => 'mutate-tv-row',
        ]);
    }
    // 区切り線は行と行の間にだけ入れる
    $output .= "<table class='mutate-tv-table'>"
        . implode('<tr><td colspan="2"><div class="split"></div></td></tr>', $rows);
    $output .= '</table>';
} else {
    echo $dm->lang['DM_tv_no_tv'];
}
echo $output;
