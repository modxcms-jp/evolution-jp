<?php

/**
 * QuickManager+ Manager Control Class
 *
 * 編集画面で隠す対象と保存・キャンセルの設定を集め、assets/plugins/qm/js/qm-edit.js の QMEdit.init() に渡す。
 *
 * @author      Mikko Lammi, www.maagit.fi
 * @license     GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @version     1.5.5 updated 02/08/2010
 */

if (!class_exists('Mcc')) {
    class Mcc
    {
        var $head;
        // 保存・キャンセルの文言と、キャンセルの戻り先（QMEdit.init() の controls）
        var $controls = [];
        // 隠す対象（QMEdit.init() の hide）
        var $hide = [];
        var $tabs;
        var $sections;

        //_______________________________________________________
        function __construct()
        {
            $this->tabs = [
                'general' => ['index' => 1, 'id' => 'tabGeneral'],
                'settings' => ['index' => 2, 'id' => 'tabSettings'],
                'access' => ['index' => 3, 'id' => 'tabAccess']
            ];

            $this->sections = [
                'docsettings' => ['index' => 0, 'name' => 'DocSettings'],
                'content' => ['index' => 1, 'name' => 'Content'],
                'tvs' => ['index' => 2, 'name' => 'TVs']
            ];
        }

        //_______________________________________________________
        function Output()
        {
            $config = ['controls' => $this->controls, 'hide' => $this->hide];

            return $this->head
                . '<script src="' . hsc(site_asset(MODX_SITE_URL . 'assets/plugins/qm/js/qm-edit.js')) . '"></script>'
                . '<script>QMEdit.init(' . js_value($config) . ');</script>';
        }

        // Section
        //_______________________________________________________
        function hideSection($section)
        {
            $section = trim($section);
            if (!isset($this->sections[$section])) return;
            $sectionBodyIndex = $this->sections[$section]['index'];
            $sectionHeaderIndex = $sectionBodyIndex - 1;

            // Handle docsettings
            if ($sectionHeaderIndex == -1) {
                $this->hide[] = ['sel' => '#tabGeneral table'];
                return;
            }

            // 文書の中で n 番目（0 始まり）の 1 つだけ
            $this->hide[] = ['sel' => 'div.sectionHeader', 'nth' => $sectionHeaderIndex];
            $this->hide[] = ['sel' => 'div.sectionBody', 'nth' => $sectionBodyIndex];
        }

        // Tab
        //_______________________________________________________
        function hideTab($tab)
        {
            $tab = trim($tab);
            if (!isset($this->tabs[$tab])) return;
            $tabIndex = $this->tabs[$tab]['index'];
            $tabId = $this->tabs[$tab]['id'];
            $this->hide[] = ['sel' => 'div#documentPane h2:nth-child(' . $tabIndex . ')', 'all' => true];
            $this->hide[] = ['sel' => '#' . $tabId];
        }

        // Field
        //_______________________________________________________
        function hideField($field)
        {
            $field = trim($field);
            // field names only (used in a selector)
            if (!preg_match('/^[A-Za-z0-9_\-]+$/', $field)) return;
            if ($field == 'content') return $this->hideSection($field);
            // $field は欄の name。その欄が入っている行（data-field の付いた行）を隠す
            $this->hide[] = ['field' => $field];
        }
    }
}
