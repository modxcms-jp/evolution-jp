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
        var $noconflictjq;

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
            if ($this->noconflictjq == 'true') {
                $line = str_replace('$(', '$j(', $line);
            }

            $this->script .= $line . "\n";
        }

        //_______________________________________________________
        function Output()
        {
            $out = $this->head;
            $this->addLine('document.body.style.display="block";');
            if ($this->noconflictjq == 'true') {
                $out .= '<script type="text/javascript">var $j = jQuery.noConflict(); $j(function(){' . $this->script . '});</script>';
            } else $out .= '<script type="text/javascript">$(function(){' . $this->script . '});</script>';

            return $out;
        }

        // Template
        //_______________________________________________________
        function hideTemplate($tpl)
        {
            $this->addLine('$("select#field_template option[value=' . $tpl . ']").remove();');
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
            if (!isset($this->sections[$section])) return;
            $sectionBodyIndex = $this->sections[$section]['index'];
            $sectionHeaderIndex = $sectionBodyIndex - 1;

            // Handle docsettings
            if ($sectionHeaderIndex == -1) {
                $this->addLine('$("#tabGeneral table:eq(0)").hide()');
                return;
            }

            $this->addLine('$("div.sectionHeader:eq(' . $sectionHeaderIndex . ')").hide()');
            $this->addLine('$("div.sectionBody:eq(' . $sectionBodyIndex . ')").hide()');
        }

        // Tab
        //_______________________________________________________
        function hideTab($tab)
        {
            global $modx;
            $tabIndex = $this->tabs[$tab]['index'];
            $tabId = $this->tabs[$tab]['id'];
            $this->addLine('$("div#documentPane h2:nth-child(' . ($tabIndex) . ')").hide();');
            $this->addLine('$("#' . $tabId . '").hide();');
        }

        // Field
        //_______________________________________________________
        function hideField($field)
        {
            if (empty($field)) return;
            if ($field == 'content') return $this->hideSection($field);
            $this->addLine('$("[name=' . $field . ']").parents("tr").hide();');
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
