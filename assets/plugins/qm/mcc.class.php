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
            // タブの id（見出しの id は、これに Header を付けたもの）
            $this->tabs = [
                'general' => 'tabGeneral',
                'settings' => 'tabSettings',
                'tv' => 'tabTVs',
                'access' => 'tabAccess'
            ];

            // 隠す要素。tvs は、TV を本文の下に出すときの見出しと本体と、TV のタブに出すときのタブ
            $this->sections = [
                'docsettings' => '#tabGeneral > table, #tabGeneral > [data-field-group]',
                'content' => '#content_header, #content_body',
                'tvs' => '#tv_header, #tv_body, #tabTVsHeader, #tabTVs'
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
            $this->hide[] = ['sel' => $this->sections[$section], 'all' => true];
        }

        // Tab
        //_______________________________________________________
        function hideTab($tab)
        {
            $tab = trim($tab);
            if (!isset($this->tabs[$tab])) return;
            // TV は置き場所の設定（tvs_below_content）に関係なく効くよう、セクションの指定と同じに隠す
            if ($tab === 'tv') return $this->hideSection('tvs');
            $tabId = $this->tabs[$tab];
            $this->hide[] = ['sel' => '#' . $tabId . 'Header, #' . $tabId, 'all' => true];
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
