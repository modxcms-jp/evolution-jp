<?php
#
# DataGrid Class
# Created By Raymond Irving 15-Feb,2004
# Based on CLASP 2.0 (www.claspdev.com)
# -----------------------------------------
# Licensed under the LGPL
# -----------------------------------------
#

$__DataGridCnt = 0;

class DataGrid
{

    public $id;

    public $ds; // datasource

    public $pageSize;            // pager settings
    public $pageNumber;
    public $pager;
    public $pagerLocation;        // top-right, top-left, bottom-left, bottom-right, both-left, both-right

    public $cssStyle;
    public $cssClass;

    public $columnHeaderStyle;
    public $columnHeaderClass;
    public $itemStyle;
    public $itemClass;
    public $altItemStyle;
    public $altItemClass;

    public $fields = '';
    public $columns = '';
    public $colWidths = '';
    public $colAligns = '';
    public $colWraps = '';
    public $colColors = '';
    public $colTypes = ''; // coltype1, coltype2, etc or coltype1:format1, e.g. date:%Y %m
    // data type: integer,float,currency,date

    public $header;
    public $footer;
    public $cellPadding;
    public $cellSpacing;

    public $rowAlign;            // vertical alignment: top, middle, bottom
    public $rowIdField;

    public $noRecordMsg = "No records found.";

    public $cdelim;
    public $cwrap;
    public $src_encode;
    public $detectHeader;

    public $pagerClass;
    public $pagerStyle;

    private $_alt;
    private $_total;
    private $_isDataset;
    private $_colcount;
    private $_colnames;
    private $_fieldnames;
    private $_colwidths;
    private $_colaligns;
    private $_colwraps;
    private $_colcolors = [];
    private $_coltypes;
    private $_itemStyle;
    private $_itemClass;
    private $_altItemStyle;
    private $_altItemClass;


    function __construct($id = '', $ds = '', $pageSize = 20, $pageNumber = -1)
    {
        global $__DataGridCnt;

        // set id
        $__DataGridCnt++;
        $this->id = $this->id ? $id : "dg" . $__DataGridCnt;

        // set pager
        $this->pageSize = $pageSize;
        $this->pageNumber = $pageNumber; // by setting pager to -1 will cause pager to load it's last page number
        $this->pagerLocation = 'top-right';

        $this->ds = $ds;
        $this->cdelim = ',';
        $this->detectHeader = 'none';
        $this->itemStyle = "color:#333333;";
        $this->altItemStyle = "color:#333333;background-color:#eeeeee";
        $this->itemClass = 'cell';
        $this->altItemClass = 'altCell';

        $this->src_encode = evo()->config['modx_charset'];
    }

    function setDataSource()
    {
        if (db()->isResult($this->ds)) {
            return;
        }

        $ds = trim($this->ds);
        if ((strpos($ds, "\n") === false) && is_file($ds)) {
            $ds = trim(file_get_contents($ds));
            if ($ds) {
                $ds = mb_convert_encoding($ds, evo()->config['modx_charset'], $this->src_encode);
            }
        }
        $this->ds = $ds;
    }

    function RenderRowFnc($n, $row)
    {
        if ($this->_alt == 0) {
            $Style = $this->_itemStyle;
            $Class = $this->_itemClass;
            $this->_alt = 1;
        } else {
            $Style = $this->_altItemStyle;
            $Class = $this->_altItemClass;
            $this->_alt = 0;
        }
        $o = "<tr>";
        for ($c = 0; $c < $this->_colcount; $c++) {
            $colStyle = $Style;
            $fld = trim($this->_fieldnames[$c] ?? '');
            if ($this->_isDataset && $fld) {
                $key = $fld;
            } else {
                $key = $c;
            }

            if (is_array($row)) {
                $value = array_key_exists($key, $row) ? $row[$key] : '';
            } elseif (is_object($row)) {
                $value = isset($row->{$key}) ? $row->{$key} : '';
            } else {
                $value = '';
            }

            $width = $this->_colwidths[$c] ?? '';
            $align = $this->_colaligns[$c] ?? '';
            $color = $this->_colcolors[$c] ?? '';
            $type = $this->_coltypes[$c] ?? '';
            $nowrap = $this->_colwraps[$c] ?? '';
            if ($color && $Style) {
                $colStyle = substr($colStyle, 0, -1) . ";background-color:{$color};'";
            }
            $value = $this->formatColumnValue($row, $value, $type, $align);

            if ($align) {
                $align = 'align="' . $align . '"';
            }
            if ($color) {
                $color = 'bgcolor="' . $color . '"';
            }
            if ($nowrap) {
                $nowrap = 'nowrap="' . $nowrap . '"';
            }
            if ($width) {
                $width = 'width="' . $width . '"';
            }
            $attr = '';
            foreach ([$colStyle, $Class, $align, $color, $nowrap, $width] as $v) {
                $v = trim($v);
                if (!empty($v)) {
                    $attr .= ' ' . $v;
                }
            }
            $o .= "<td{$attr}>{$value}</td>";
        }
        $o .= "</tr>\n";
        return $o;
    }

    // format column values
    function formatColumnValue($row, $value, $type, &$align)
    {
        global $modx;

        if ($value === null) {
            $value = '';
        }

        if (strpos($type, ":") !== false) {
            [$type, $type_format] = explode(":", $type, 2);
        }
        switch (strtolower($type)) {
            case "integer":
                if ($align == "") {
                    $align = "right";
                }
                $value = number_format($value);
                break;

            case "float":
                if ($align == "") {
                    $align = "right";
                }
                if (!$type_format) {
                    $type_format = 2;
                }
                $value = number_format($value, $type_format);
                break;

            case "currency":
                if ($align == "") {
                    $align = "right";
                }
                if (!$type_format) {
                    $type_format = 2;
                }
                $value = "$" . number_format($value, $type_format);
                break;

            case "date":
                if (!empty($value)) {
                    if ($align == "") {
                        $align = "right";
                    }
                    if (!is_numeric($value)) {
                        $value = strtotime($value);
                    }
                    if (!$type_format) {
                        $type_format = "%A %d, %B %Y";
                    }
                    $value = evo()->mb_strftime($type_format, $value);
                } else {
                    if ($align == "") {
                        $align = "center";
                    }
                    $value = '-';
                }
                break;

            case "boolean":
                if ($align == '') {
                    $align = "center";
                }
                $value = number_format($value);
                if ($value) {
                    $value = '&bull;';
                } else {
                    $value = '&nbsp;';
                }
                break;

            case "template":
                // replace [+value+] first
                $value = str_replace("[+value+]", $value, $type_format);
                // replace other [+fields+]
                if (strpos($value, "[+") !== false) {
                    foreach ($row as $k => $v) {
                        $modx->placeholders[$k] = $v;
                    }
                    $value = evo()->mergePlaceholderContent($value);
                }
                break;

        }
        if (isset($this->cwrap) && !empty($this->cwrap)) {
            $value = trim($value, $this->cwrap);
        }
        return $value;
    }

    public function render()
    {
        // set datasource
        $this->setDataSource();

        $this->_itemStyle = ($this->itemStyle) ? 'style="' . $this->itemStyle . '"' : '';
        $this->_itemClass = ($this->itemClass) ? 'class="' . $this->itemClass . '"' : '';
        $this->_altItemStyle = ($this->altItemStyle) ? 'style="' . $this->altItemStyle . '"' : '';
        $this->_altItemClass = ($this->altItemClass) ? 'class="' . $this->altItemClass . '"' : '';

        $this->_alt = 0;
        $this->_total = 0;

        $this->_isDataset = db()->isResult($this->ds); // if not dataset then treat as array
        if ($this->_isDataset) {
            if (isset($this->fields)) {
                $this->_fieldnames = explode(',', $this->fields);
                foreach ($this->_fieldnames as $i => $v) {
                    $this->_fieldnames[$i] = trim($v);
                }
            } else {
                $tblc = db()->numFields($this->ds);
                for ($i = 0; $i < $tblc; $i++) {
                    $this->_fieldnames[$i] = db()->fieldName($this->ds, $i);
                }
            }
        }

        if ($this->_isDataset && !$this->columns) {
            $cols = db()->numFields($this->ds);
            for ($i = 0; $i < $cols; $i++) {
                $this->columns .= ($i ? "," : "") . db()->fieldName($this->ds, $i);
            }
        }

        // start grid
        $attrs = [
            $this->cssClass ? 'class="' . $this->cssClass . '"' : '',
            $this->cssStyle ? 'style="' . $this->cssStyle . '"' : '',
            (int)$this->cellPadding ? 'cellpadding="' . (int)$this->cellPadding . '"' : '',
            (int)$this->cellSpacing ? 'cellspacing="' . (int)$this->cellSpacing . '"' : ''
        ];
        $attr = '';
        foreach ($attrs as $v) {
            $v = trim($v);
            if (!empty($v)) {
                $attr .= ' ' . $v;
            }
        }
        $tblStart = "<table{$attr}>\n";
        $tblEnd = "</table>\n";

        if ($this->cdelim === 'tab') {
            $this->cdelim = "\t";
        }

        // build column header
        if ($this->detectHeader === 'first line') {
            [$firstline, $this->ds] = explode("\n", $this->ds, 2);
            $this->_colnames = explode($this->cdelim, $firstline);
        } elseif (!empty($this->columns)) {
            $this->_colnames = explode(
                (strstr($this->columns, "||") !== false ? "||" : ","),
                $this->columns
            );
        } else {
            $this->_colnames = [];
        }

        $this->_colwidths = explode(
            (strstr($this->colWidths, "||") !== false ? "||" : ","),
            $this->colWidths
        );
        $this->_colaligns = explode(
            (strstr($this->colAligns, "||") !== false ? "||" : ","),
            $this->colAligns
        );
        $this->_colwraps = explode(
            (strstr($this->colWraps, "||") !== false ? "||" : ","),
            $this->colWraps
        );
        $this->_colcolors = explode(
            (strstr($this->colColors, "||") !== false ? "||" : ","),
            $this->colColors
        );
        $this->_coltypes = explode(
            (strstr($this->colTypes, "||") !== false ? "||" : ","),
            $this->colTypes
        );

        if (0 < count($this->_colnames)) {
            $this->_colcount = count($this->_colnames);
        } elseif (!db()->isResult($this->ds) && strpos($this->ds, $this->cdelim) !== false) {
            if (strpos($this->ds, "\n") !== false) {
                $_ = substr($this->ds, 0, strpos($this->ds, "\n"));
            }
            $this->_colcount = count(explode($this->cdelim, $_));
        } else {
            $this->_colcount = 1;
        }

        if (!$this->_isDataset) {
            if ($this->ds === '') {
                $this->ds = [];
            } else {
                $delim = '@[' . $this->cdelim . "\n]@";
                $this->ds = preg_split($delim, $this->ds);
                $this->ds = array_chunk($this->ds, $this->_colcount);
            }
        }

        if (0 < count($this->_colnames)) {
            $tblColHdr = "<thead>\n<tr>";
            $attrs = [
                'style' => ($this->columnHeaderStyle)
                    ? 'style="' . $this->columnHeaderStyle . '"'
                    : '',
                'class' => ($this->columnHeaderClass)
                    ? 'class="' . $this->columnHeaderClass . '"'
                    : ''
            ];
            for ($c = 0; $c < $this->_colcount; $c++) {
                if (!empty($this->_colwidths[$c])) {
                    $attrs['width'] = 'width="' . $this->_colwidths[$c] . '"';
                } else {
                    $attrs['width'] = '';
                }
                $attr = '';
                foreach ($attrs as $v) {
                    $v = trim($v);
                    if (!empty($v)) {
                        $attr .= ' ' . $v;
                    }
                }
                $tblColHdr .= "<th{$attr}>{$this->_colnames[$c]}</th>";
            }
            $tblColHdr .= "</tr></thead>\n";
        } else {
            $tblColHdr = '';
        }

        $pagerClass = (isset($this->pagerClass))
            ? 'class="' . $this->pagerClass . '"'
            : 'class="pager"';
        $pagerStyle = (isset($this->pagerStyle))
            ? 'style="' . $this->pagerStyle . '"'
            : 'style="margin:10px 0;background-color:#ffffff;"';

        // build rows
        if ($this->_isDataset) {
            $rowcount = db()->count($this->ds);
        } else {
            $rowcount = is_array($this->ds) ? count($this->ds) : 0;
        }

        if ($rowcount == 0) {
            $ph = [];
            $ph['colspan'] = (1 < $this->_colcount) ? 'colspan="' . $this->_colcount . '"' : '';
            $ph['style'] = $this->_itemStyle;
            $ph['class'] = $this->_itemClass;
            $ph['noRecordMsg'] = $this->noRecordMsg;
            $tpl = "<tr><td [+style+] [+class+] [+colspan+]>[+noRecordMsg+]</td></tr>\n";
            $tblRows = evo()->parseText($tpl, $ph);
        } else {
            // render grid items
            if ($this->pageSize <= 0) {
                for ($r = 0; $r < $rowcount; $r++) {
                    if ($this->_isDataset) {
                        $row = db()->getRow($this->ds);
                    } else {
                        $row = $this->ds[$r];
                    }
                    if (0 < count($row)) {
                        $tblRows = $this->RenderRowFnc($r + 1, $row);
                    }
                }
            } else {
                if (!$this->pager) {
                    include_once __DIR__ . "/datasetpager.class.php";
                    $this->pager = new DataSetPager($this->id, $this->ds, $this->pageSize, $this->pageNumber);
                    $this->pager->setRenderRowFnc($this); // pass this object
                    $this->pager->cssStyle = $pagerStyle;
                    $this->pager->cssClass = $pagerClass;
                } else {
                    $this->pager->pageSize = $this->pageSize;
                    $this->pager->pageNumber = $this->pageNumber;
                }

                $this->pager->render();
                $tblRows = $this->pager->getRenderedRows();
                $tblPager = $this->pager->getRenderedPager();
            }
        }

        // setup header,pager and footer
        $ptop = (substr($this->pagerLocation, 0, 3) == "top") || (substr($this->pagerLocation, 0, 4) == "both");
        $pbot = (substr($this->pagerLocation, 0, 3) == "bot") || (substr($this->pagerLocation, 0, 4) == "both");

        if ($this->header) {
            $o = '<div class="gridheader">' . $this->header . "</div>\n" . $o;
        } else {
            $o = $tblStart;
        }

        $tpl = '<div align="[+align+]" [+pagerClass+] [+pagerStyle+]>[+tblPager+]</div>' . "\n";
        $ph['pagerClass'] = $pagerClass;
        $ph['pagerStyle'] = $pagerStyle;
        $ph['tblPager'] = $tblPager ?? '';
        if (substr($this->pagerLocation, -4) == 'left') {
            $ph['align'] = 'left';
        } else {
            $ph['align'] = 'right';
        }

        if (!empty($tblPager) && $ptop) {
            $o = evo()->parseText($tpl, $ph) . $o;
        }
        $o .= $tblColHdr . $tblRows;
        $o .= $tblEnd;
        if (!empty($tblPager) && $pbot) {
            $o = $o . evo()->parseText($tpl, $ph);
        }

        if ($this->footer) {
            $o .= '<div class="gridfooter">' . $this->footer . "</div>\n";
        }

        return '<div class="gridwrap">' . $o . '</div>';
    }
}
