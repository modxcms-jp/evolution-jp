<?php
	if(!defined('IN_PARSER_MODE') && !defined('IN_MANAGER_MODE')) exit();

    include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
    $grd = new DataGrid('',$value);
    $grd->noRecordMsg        =$params['egmsg'];
    
    $grd->columnHeaderClass  =$params['chdrc'];
    $grd->cssClass           =$params['tblc'];
    $grd->itemClass          =$params['itmc'];
    $grd->altItemClass       =$params['aitmc'];
    
    $grd->columnHeaderStyle  =$params['chdrs'];
    $grd->cssStyle           =$params['tbls'];
    $grd->itemStyle          =$params['itms'];
    $grd->altItemStyle       =$params['aitms'];
    
    $grd->columns            =$params['cols'];
    $grd->fields             =$params['flds'];
    $grd->colWidths          =$params['cwidth'];
    $grd->colAligns          =$params['calign'];
    $grd->colColors          =$params['ccolor'];
    $grd->colTypes           =$params['ctype'];
    
    $grd->cellPadding        =$params['cpad'];
    $grd->cellSpacing        =$params['cspace'];
    $grd->header             =$params['head'];
    $grd->footer             =$params['foot'];
    $grd->pageSize           =$params['psize'];
    $grd->pagerLocation      =$params['ploc'];
    $grd->pagerClass         =$params['pclass'];
    $grd->pagerStyle         =$params['pstyle'];
    
    $grd->cdelim             =$params['cdelim'];
    $grd->cwrap              =$params['cwrap'];
    $grd->src_encode         =$params['enc'];
    $grd->detectHeader       =$params['detecthead'];
    
    return $grd->render();
