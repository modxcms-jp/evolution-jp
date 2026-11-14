<?php
if (!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

include_once(MODX_CORE_PATH . 'controls/data_table.class.php');

// 保存済みの表示パラメータのうち、意味のある設定だけを読む。
// style・width・align・bgcolor・cellpadding・cellspacing の指定（tbls・itms・aitms・chdrs・pstyle・
// ccolor・cwidth・cpad・cspace・calign）は読まない（DB は書き換えない）。
// $id は tvProcessor() が作る "tv{TV 名}"
return DataTableSettings::render($value, [
    'columns' => $params['cols'] ?? '',
    'fields' => $params['flds'] ?? '',
    'types' => $params['ctype'] ?? '',
    'delimiter' => $params['cdelim'] ?? '',
    'wrapper' => $params['cwrap'] ?? '',
    'encoding' => $params['enc'] ?? '',
    'detectHeader' => $params['detecthead'] ?? '',
    'pageSize' => $params['psize'] ?? 0,
    'pagerLocation' => $params['ploc'] ?? '',
    'header' => $params['head'] ?? '',
    'footer' => $params['foot'] ?? '',
    'tableClass' => $params['tblc'] ?? '',
    'headerClass' => $params['chdrc'] ?? '',
    'pagerClass' => $params['pclass'] ?? '',
    'emptyMessage' => $params['egmsg'] ?? '',
], $id);
