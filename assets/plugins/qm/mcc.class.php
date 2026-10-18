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
        var $fields;

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

            $this->fields = ['content', 'pagetitle', 'longtitle', 'menuindex', 'parent', 'description', 'alias', 'link_attributes', 'introtext', 'template', 'menutitle'];
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
                . '});</script>';

            return $out;
        }

        // Template
        //_______________________________________________________
        function hideTemplate($tpl)
        {
            $this->addLine(
                'document.querySelectorAll("select#field_template option").forEach(function (el) { if (el.getAttribute("value") === '
                . js_literal($tpl)
                . ') { el.remove(); } });'
            );
        }

        //_______________________________________________________
        function hideTemplates($tpls)
        {
            if (is_array($tpls)) {
                foreach ($tpls as $tpl) {
                    $this->hideTemplate($tpl);
                }
                $this->hideTemplate(0); // remove blank
            } else {
                $this->hideTemplate(0); // remove blank
            }
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
                $this->addLine('qmHide([document.querySelector("#tabGeneral table")]);');
                return;
            }

            // 文書の中で n 番目（0 始まり）の 1 つだけ（以前の :eq(n) と同じ）
            $this->addLine('qmHide([document.querySelectorAll("div.sectionHeader")[' . $sectionHeaderIndex . ']]);');
            $this->addLine('qmHide([document.querySelectorAll("div.sectionBody")[' . $sectionBodyIndex . ']]);');
        }

        // Tab
        //_______________________________________________________
        function hideTab($tab)
        {
            $tab = trim($tab);
            if (!isset($this->tabs[$tab])) return;
            $tabIndex = $this->tabs[$tab]['index'];
            $tabId = $this->tabs[$tab]['id'];
            $this->addLine('qmHide(document.querySelectorAll("div#documentPane h2:nth-child(' . $tabIndex . ')"));');
            $this->addLine('qmHide([document.getElementById("' . $tabId . '")]);');
        }

        // Field
        //_______________________________________________________
        function hideField($field)
        {
            $field = trim($field);
            // field names only (used in a selector)
            if (!preg_match('/^[A-Za-z0-9_\-]+$/', $field)) return;
            if ($field == 'content') return $this->hideSection($field);
            // 祖先の tr をすべて隠す（直近の 1 つではない。以前の .parents("tr") と同じ）
            $this->addLine(
                'document.querySelectorAll(\'[name="' . $field . '"]\').forEach(function (el) { for (var p = el.parentElement; p; p = p.parentElement) { if (p.tagName === "TR") { p.style.display = "none"; } } });'
            );
        }

        //_______________________________________________________
        function showFields($fields)
        {
            if (!($fields = explode(',', $fields))) return;
            foreach ($fields as $key => $value) {
                $fields[$key] = trim($value);
            }
            foreach ($this->fields as $field) {
                if (!in_array($field, $fields))
                    $this->hideField($field);
            }
        }

        //_______________________________________________________
        function doSafe($string)
        {
            global $modx;
            $string = htmlentities($string, ENT_QUOTES, $modx->config['modx_charset']);
        }
    }
}
