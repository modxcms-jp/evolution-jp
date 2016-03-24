<?php
#
# DataGrid Class
# Created By Raymond Irving 15-Feb,2004
# Based on CLASP 2.0 (www.claspdev.com)
# -----------------------------------------
# Licensed under the LGPL
# -----------------------------------------
#

$__DataGridCnt=0;

class DataGrid {

	var $ds; // datasource

	var $pageSize;			// pager settings
	var $pageNumber;
	var $pager;
	var $pagerLocation;		// top-right, top-left, bottom-left, bottom-right, both-left, both-right

	var $cssStyle;
	var $cssClass;

	var $columnHeaderStyle;
	var $columnHeaderClass;
	var $itemStyle;
	var $itemClass;
	var $altItemStyle;
	var $altItemClass;
	
	var $fields;
	var $columns;
	var $colWidths;
	var $colAligns;
	var $colWraps;
	var $colColors;
	var $colTypes;			// coltype1, coltype2, etc or coltype1:format1, e.g. date:%Y %m
							// data type: integer,float,currency,date
	
	var $header;
	var $footer;
	var $cellPadding;
	var $cellSpacing;

	var $rowAlign;			// vertical alignment: top, middle, bottom
	var $rowIdField;
	
	var $noRecordMsg = "No records found.";
	
	var $cdelim;
	var $cwrap;
	var $src_encode;
	var $detectHeader;

	function __construct($id='',$ds='',$pageSize=20,$pageNumber=-1) {
		global $modx, $__DataGridCnt;
		
		// set id
		$__DataGridCnt++;
		$this->id = $this->id ? $id:"dg".$__DataGridCnt;
		
		// set pager
		$this->pageSize = $pageSize;
		$this->pageNumber = $pageNumber; // by setting pager to -1 will cause pager to load it's last page number
		$this->pagerLocation = 'top-right';
		
		$this->ds = $ds;
		$this->cdelim = ',';
		$this->detectHeader = 'none';
		$this->itemStyle = "style='color:#333333;'";
		$this->altItemStyle = "style='color:#333333;background-color:#eeeeee'";
		$this->itemClass    = 'cell';
		$this->altItemClass = 'altCell';
		
		$this->src_encode = $modx->config['modx_charset'];
	}

	function setDataSource(){
		global $modx;
		
		if($modx->db->isResult($this->ds)) return;
		
		$ds = trim($this->ds);
		if((strpos($ds,"\n")===false) && is_file($ds))
		{
			$ds = trim(file_get_contents($ds));
			if($ds) $ds = mb_convert_encoding($ds, $modx->config['modx_charset'], $this->src_encode);
		}
		$this->ds = $ds;
	}
	
	function RenderRowFnc($n,$row){
		if ($this->_alt==0) {$Style = $this->_itemStyle;$Class = $this->_itemClass;$this->_alt=1;}
		else {$Style = $this->_altItemStyle;$Class = $this->_altItemClass; $this->_alt=0;}
		$o = "<tr>";
		for($c=0;$c<$this->_colcount;$c++){
			$colStyle = $Style;
			$fld=trim($this->_fieldnames[$c]);
			if($this->_isDataset && $fld) $key = $fld;
			else                          $key = $c;
			$value = $row[$key];
			
			$width=$this->_colwidths[$c];
			$align=$this->_colaligns[$c];
			$color=$this->_colcolors[$c];
			$type=$this->_coltypes[$c];
			$nowrap=$this->_colwraps[$c];
			if($color && $Style) $colStyle = substr($colStyle,0,-1).";background-color:{$color};'";
			$value = $this->formatColumnValue($row,$value,$type,$align);
			
			if($align)  $align  = 'align="'   . $align  . '"';
			if($color)  $color  = 'bgcolor="' . $color  . '"';
			if($nowrap) $nowrap = 'nowrap="'  . $nowrap . '"';
			if($width)  $width  = 'width="'   . $width  . '"';
			$attr = '';
			foreach(array($colStyle,$Class,$align,$color,$nowrap,$width) as $v)
			{
				$v = trim($v);
				if(!empty($v)) $attr .= ' ' . $v;
			}
			$o .= "<td{$attr}>{$value}</td>";
		}
		$o.="</tr>\n";
		return $o;
	}
	
	// format column values
	function formatColumnValue($row,$value,$type,&$align){
		global $modx;
		if(strpos($type,":")!==false) list($type,$type_format) = explode(":",$type,2);
		switch (strtolower($type)) {
			case "integer":
				if($align=="") $align="right";
				$value = number_format($value);
				break;

			case "float":
				if($align=="") $align="right";
				if(!$type_format) $type_format = 2;
				$value = number_format($value,$type_format);
				break;

			case "currency":
				if($align=="") $align="right";
				if(!$type_format) $type_format = 2;
				$value = "$".number_format($value,$type_format);
				break;
				
			case "date":
				if(!empty($value))
				{
					if($align=="") $align="right";
					if(!is_numeric($value)) $value = strtotime($value);
					if(!$type_format) $type_format = "%A %d, %B %Y";
					$value = $modx->mb_strftime($type_format,$value);
				}
				else
				{
					if($align=="") $align="center";
					$value = '-';
				}
				break;
			
			case "boolean":
				if ($align=='') $align="center";
				$value = number_format($value);
				if ($value) {
					$value = '&bull;';
				} else {
					$value = '&nbsp;';
				}
				break;

			case "template":
				// replace [+value+] first
				$value = str_replace("[+value+]",$value,$type_format);
				// replace other [+fields+]
				if(strpos($value,"[+")!==false)
				{
					foreach($row as $k=>$v)
					{
						$modx->placeholders[$k] = $v;
					}
					$value = $modx->mergePlaceholderContent($value);
				}
				break;
				
		}
		if(isset($this->cwrap) && !empty($this->cwrap)) $value = trim($value,$this->cwrap);
		return $value;
	}
	
	function render()
	{
		global $modx;
		
		// set datasource
		$this->setDataSource();
		
		$columnHeaderStyle	= ($this->columnHeaderStyle)? 'style="' .$this->columnHeaderStyle. '"':'';
		$columnHeaderClass	= ($this->columnHeaderClass)? 'class="' .$this->columnHeaderClass. '"':'';
		$cssStyle			= ($this->cssStyle)? 'style="' .$this->cssStyle . '"':'';
		$cssClass			= ($this->cssClass)? 'class="' .$this->cssClass. '"':'';
		
		$pagerClass			= (isset($this->pagerClass))? 'class="'.$this->pagerClass.'"':'class="pager"';
		$pagerStyle			= (isset($this->pagerStyle))? 'style="'.$this->pagerStyle.'"':'style="margin:10px 0;background-color:#ffffff;"';

		$this->_itemStyle	= ($this->itemStyle)?    'style="' . $this->itemStyle . '"':'';
		$this->_itemClass	= ($this->itemClass)?    'class="' . $this->itemClass . '"':'';
		$this->_altItemStyle= ($this->altItemStyle)? 'style="' .$this->altItemStyle . '"':'';
		$this->_altItemClass= ($this->altItemClass)? 'class="' .$this->altItemClass . '"':'';

		$this->_alt = 0;
		$this->_total = 0;
		
		$this->_isDataset = $modx->db->isResult($this->ds); // if not dataset then treat as array
		if($this->_isDataset)
		{
			if(isset($this->fields))
			{
				$this->_fieldnames = explode(',', $this->fields);
				foreach($this->_fieldnames as $i=>$v)
				{
					$this->_fieldnames[$i] = trim($v);
				}
			}
			else
			{
				$tblc = $modx->db->numFields($this->ds);
				for($i=0;$i<$tblc;$i++)
				{
					$this->_fieldnames[$i] = $modx->db->fieldName($this->ds,$i);
				}
			}
		}

		if(!$cssStyle && !$cssClass) $cssStyle = '';

		if($this->_isDataset && !$this->columns) {
			$cols = $modx->db->numFields($this->ds);
			for($i=0;$i<$cols;$i++)
				$this->columns.= ($i ? ",":"").$modx->db->fieldName($this->ds,$i);
		}
		
		// start grid
		$cellpadding = '';
		$cellspacing = '';
		if(isset($this->cellPadding)) $cellpadding = 'cellpadding="' . (int)$this->cellPadding . '"';
		if(isset($this->cellSpacing)) $cellspacing = 'cellspacing="' . (int)$this->cellSpacing . '"';
		$attr = '';
		foreach(array($cssClass,$cssStyle,$cellpadding,$cellspacing) as $v)
		{
			$v = trim($v);
			if(!empty($v)) $attr .= ' ' . $v;
		}
		$tblStart	= "<table{$attr}>\n";
		$tblEnd		= "</table>\n";
		
		if($this->cdelim==='tab')    $this->cdelim = "\t";
		
		// build column header
		if($this->detectHeader==='first line')
		{
			list($firstline, $this->ds) = explode("\n", $this->ds, 2);
			$this->_colnames  = explode($this->cdelim,$firstline);
		}
		elseif(!empty($this->columns))
		{
			$this->_colnames  = explode((strstr($this->columns,"||")  !==false ? "||":","),$this->columns);
		}
		else $this->_colnames = array();
		
		$this->_colwidths = explode((strstr($this->colWidths,"||")!==false ? "||":","),$this->colWidths);
		$this->_colaligns = explode((strstr($this->colAligns,"||")!==false ? "||":","),$this->colAligns);
		$this->_colwraps  = explode((strstr($this->colWraps,"||") !==false ? "||":","),$this->colWraps);
		$this->_colcolors = explode((strstr($this->colColors,"||")!==false ? "||":","),$this->colColors);
		$this->_coltypes  = explode((strstr($this->colTypes,"||") !==false ? "||":","),$this->colTypes);
		
		if(0 < count($this->_colnames))
		{
			$this->_colcount = count($this->_colnames);
		}
		elseif(!$modx->db->isResult($this->ds) && strpos($this->ds,$this->cdelim)!==false)
		{
			if(strpos($this->ds,"\n")!==false)
				$_ = substr($this->ds,0,strpos($this->ds,"\n"));
			$this->_colcount = count(explode($this->cdelim, $_));
		}
		else $this->_colcount = 1;
		
		if(!$this->_isDataset) {
			if($this->ds==='')
				$this->ds = array();
			else
			{
				$delim = '@['.$this->cdelim."\n]@";
				$this->ds = preg_split($delim,$this->ds);
				$this->ds = array_chunk($this->ds, $this->_colcount);
			}
		}
		
		if(0 < count($this->_colnames))
		{
			$tblColHdr ="<thead>\n<tr>";
			for($c=0;$c<$this->_colcount;$c++){
				$name=$this->_colnames[$c];
				$width=$this->_colwidths[$c];
				if(!empty($width)) $width = 'width="' . $width . '"';
				$attr = '';
				foreach(array($columnHeaderStyle,$columnHeaderClass,$width) as $v)
				{
					$v = trim($v);
					if(!empty($v)) $attr .= ' ' . $v;
				}
				$tblColHdr .= "<th{$attr}>{$name}</th>";
			}
			$tblColHdr.="</tr></thead>\n";
		}
		else $tblColHdr = '';
		
		// build rows
		$rowcount = $this->_isDataset ? $modx->db->getRecordCount($this->ds):count($this->ds);
		
		
		if($rowcount==0)
		{
			$ph = array();
			$ph['colspan']     = (1<$this->_colcount) ? 'colspan="' . $this->_colcount . '"' : '';
			$ph['style']       = $this->_itemStyle;
			$ph['class']       = $this->_itemClass;
			$ph['noRecordMsg'] = $this->noRecordMsg;
			$tpl = "<tr><td [+style+] [+class+] [+colspan+]>[+noRecordMsg+]</td></tr>\n";
			$tblRows .= $modx->parseText($tpl,$ph);
		}
		else {
			// render grid items
			if($this->pageSize<=0)
			{
				for($r=0;$r<$rowcount;$r++)
				{
					if($this->_isDataset)
						$row = $modx->db->getRow($this->ds);
					else
						$row = $this->ds[$r];
					if(0<count($row)) $tblRows.= $this->RenderRowFnc($r+1,$row);
				}
			}
			else
			{
				if(!$this->pager)
				{
					include_once dirname(__FILE__)."/datasetpager.class.php";
					$this->pager = new DataSetPager($this->id,$this->ds,$this->pageSize,$this->pageNumber);
					$this->pager->setRenderRowFnc($this); // pass this object
					$this->pager->cssStyle = $pagerStyle;
					$this->pager->cssClass = $pagerClass;
				}
				else
				{
					$this->pager->pageSize	= $this->pageSize;
					$this->pager->pageNumber= $this->pageNumber;
				}
				
				$this->pager->render();
				$tblRows = $this->pager->getRenderedRows();
				$tblPager = $this->pager->getRenderedPager();
			}
		}
		
		// setup header,pager and footer
		$o = $tblStart;
		$ptop = (substr($this->pagerLocation,0,3)=="top")||(substr($this->pagerLocation,0,4)=="both");
		$pbot = (substr($this->pagerLocation,0,3)=="bot")||(substr($this->pagerLocation,0,4)=="both");
		
		if($this->header) $o = '<div class="gridheader">' . $this->header."</div>\n" . $o;
		
		$tpl = '<div align="[+align+]" [+pagerClass+] [+pagerStyle+]>[+tblPager+]</div>' . "\n";
		$ph['pagerClass'] = $pagerClass;
		$ph['pagerStyle'] = $pagerStyle;
		$ph['tblPager']   = $tblPager;
		if(substr($this->pagerLocation,-4)=='left') $ph['align'] = 'left';
		else                                        $ph['align'] = 'right';
		
		if($tblPager && $ptop) $o = $modx->parseText($tpl,$ph) . $o;
		$o .= $tblColHdr.$tblRows;
		$o.= $tblEnd;
		if($tblPager && $pbot) $o = $o . $modx->parseText($tpl,$ph);
		
		if($this->footer) $o .= '<div class="gridfooter">' . $this->footer . "</div>\n";
		
		return '<div class="gridwrap">' . $o . '</div>';
	}
}
