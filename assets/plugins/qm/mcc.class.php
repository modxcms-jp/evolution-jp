<?php

/**
 * QuickManager+ Manager Control Class
 *
 * @author      Mikko Lammi, www.maagit.fi
 * @license     GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @version     1.5.5 updated 02/08/2010
 */

if (!class_exists('Mcc')) {
    class Mcc
    {
        var $script;
        var $head;
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
        function addLine($line)
        {
            $this->script .= $line . "\n";
        }

        //_______________________________________________________
        function Output()
        {
            $out = $this->head;
            $this->addLine('document.body.style.display = "block";');
            // qmHide: 要素の配列や NodeList を隠す（無い要素は飛ばす。表の行も扱うので hidden ではなく display を none に）
            $out .= '<script type="text/javascript">Evo.ready(function () {' . "\n"
                . 'var qmHide = function (els) { Array.prototype.forEach.call(els, function (el) { if (el) { el.style.display = "none"; } }); };' . "\n"
                . $this->script
                // 欄をすべて隠したまとまり（アコーディオンなど）は、見出しだけを残さない
                . 'Evo.hideEmptyFieldGroups();' . "\n"
                . '});</script>';

            return $out;
        }

        // Section
        //_______________________________________________________
        function hideSection($section)
        {
            $section = trim($section);
            if (!isset($this->sections[$section])) return;
            $this->addLine('qmHide(document.querySelectorAll(' . js_literal($this->sections[$section]) . '));');
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
            $this->addLine('qmHide([document.getElementById(' . js_literal($tabId . 'Header') . '), document.getElementById(' . js_literal($tabId) . ')]);');
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
            $this->addLine(
                'document.querySelectorAll(\'[name="' . $field . '"]\').forEach(function (el) { qmHide([Evo.fieldRow(el)]); });'
            );
        }
    }
}
