<?php

class ContextMenu
{

    public $i;
    private $html;
    private $id;
    private $width;
    private $visible;
    private static $scriptLoaded = false;

    public function __construct($id = '', $width = 120, $visible = false)
    {
        global $ContextMenuCnt;
        if (!$ContextMenuCnt) {
            $ContextMenuCnt = 0;
        }
        $ContextMenuCnt++;
        $this->html = '';
        $this->visible = $visible ?: false;
        $this->width = is_numeric($width) ? intval($width) : 120;
        $this->id = $id ?: "cntxMnu{$ContextMenuCnt}"; // set id
        $this->i = 0;
    }

    public function addItem($text, $action = "", $img = "", $disabled = 0)
    {
        if (!$img) {
            $img = config('base_url') . style('tx');
        }
        if (substr($action, 0, 3) == "js:") {
            $action = substr($action, 3);
        } elseif (substr($action, 0, 3) == "hl:") {
            $action = "EvoShell.go('" . substr($action, 3) . "')";
        } else {
            $action = "EvoShell.go('{$action}')";
        }
        $action = " onmouseover=\"this.className='cntxMnuItemOver';\" onmouseout=\"this.className='cntxMnuItem';\" onclick=\"{$action}; EvoContextMenu.hide('{$this->id}');\"";
        if ($disabled) {
            $action = "";
        }
        $this->html .= "
            <div class='" . ($disabled ? "cntxMnuItemDisabled" : "cntxMnuItem") . "' {$action}>
                <img src='{$img}' align='absmiddle' />&nbsp;{$text}
            </div>
        ";
        $this->i++;
    }

    public function addSeparator()
    {
        $this->html .= "
            <div class='cntxMnuSeparator'></div>
        ";
    }

    public function render()
    {
        // 部品の JS は 1 ページで 1 回だけ読む
        $script = '';
        if (!self::$scriptLoaded) {
            self::$scriptLoaded = true;
            $script = '<script src="' . hsc(manager_asset('media/script/context-menu.js')) . '"></script>';
        }
        return $script .
            "<div id='{$this->id}' class='contextMenu' style='min-width:{$this->width}px; visibility:" . ($this->visible ? 'visible' : 'hidden') . "'>{$this->html}</div>";
    }

    public function getClientScriptObject()
    {
        return "EvoContextMenu.get('{$this->id}')";
    }
}
