<?php

/*
 * Title: Template Class
 * Purpose:
 *      The Template class contains all functions relating to Ditto's
 *      handling of templates and any supporting functions they need
*/

class template
{
    var $language, $fields, $current;

    // ---------------------------------------------------
    // Function: template
    // Set the class language and fields variables
    // ---------------------------------------------------
    public function __construct()
    {
        $this->language = $GLOBALS['ditto_lang'];
        $this->fields = [
            'db' => [],
            'tv' => [],
            'custom' => [],
            'item' => [],
            'qe' => [],
            'phx' => [],
            'rss' => [],
            'json' => [],
            'xml' => [],
            'unknown' => []
        ];
    }

    // ---------------------------------------------------
    // Function: process
    // Take the templates and parse them for tempalte variables,
    // Check to make sure they have fields, and sort the fields
    // ---------------------------------------------------
    function process($template)
    {
        $templates = [];
        if (!isset($template['base'])) {
            $template['base'] = $template['default'];
        } else {
            unset($template['default']);
        }
        foreach ($template as $name => $tpl) {
            if (!empty($tpl) && $tpl != '') {
                $templates[$name] = $this->fetch($tpl);
            }
        }
        $fieldList = [];
        foreach ($templates as $tpl) {
            $fieldList = array_merge($this->findTemplateVars($tpl), $fieldList);
        }

        $fieldList = array_unique($fieldList);
        $fields = $this->sortFields($fieldList);
        $checkAgain = ['qe', 'json', 'xml'];
        foreach ($checkAgain as $type) {
            $fields = array_merge_recursive($fields, $this->sortFields($fields[$type]));
        }
        $this->fields = $fields;
        return $templates;
    }

    // ---------------------------------------------------
    // Function: findTemplateVars
    // Find al the template variables in the template
    // ---------------------------------------------------
    private function findTemplateVars($tpl)
    {
        $matches = $this->getTagsFromContent($tpl);
        if (!$matches) {
            return [];
        }
        $TVs = array_map(
            function($tv) {
                $match = explode(':', $tv);
                return $match[0];
            },
            $matches[1]
        );
        if (!$TVs) {
            return [];
        }
        return array_unique($TVs);
    }

    function getTagsFromContent($tpl)
    {
        $matches = evo()->getTagsFromContent($tpl, '[+', '+]');
        if (!$matches) return false;
        foreach ($matches[1] as $v) {
            if (strpos($v, '[+') != false) {
                $pair = $this->getTagsFromContent($v);
                $matches[0] = array_merge($matches[0], $pair[0]);
                $matches[1] = array_merge($matches[1], $pair[1]);
            }
        }
        return $matches;
    }

    // ---------------------------------------------------
    // Function: sortFields
    // Sort the array of fields provided by type
    // ---------------------------------------------------
    function sortFields($fieldList)
    {
        global $ditto_constantFields;
        $dbFields = $ditto_constantFields['db'];
        $tvFields = $ditto_constantFields['tv'];
        $fields = [
            'db' => [],
            'tv' => [],
            'custom' => [],
            'item' => [],
            'qe' => [],
            'phx' => [],
            'rss' => [],
            'json' => [],
            'xml' => [],
            'unknown' => []
        ];

        $custom = ['author', 'date', 'url', 'title', 'ditto_iteration', 'class'];

        foreach ($fieldList as $field) {
            if (strpos($field, 'rss_') === 0) {
                $fields['rss'][] = substr($field, 4);
            } else if (strpos($field, 'xml_') === 0) {
                $fields['xml'][] = substr($field, 4);
            } else if (strpos($field, 'json_') === 0) {
                $fields['json'][] = substr($field, 5);
            } else if (strpos($field, 'item[') === 0) {
                $fields['item'][] = substr($field, 4);
            } else if (strpos($field, '#') === 0) {
                $fields['qe'][] = substr($field, 1);
            } else if (strpos($field, 'phx:') === 0) {
                $fields['phx'][] = $field;
            } else if (in_array($field, $dbFields)) {
                $fields['db'][] = $field;
            } else if (in_array($field, $tvFields)) {
                $fields['tv'][] = $field;
            } else if (strpos($field, 'tv') === 0 && in_array(substr($field, 2), $tvFields)) {
                $fields['tv'][] = substr($field, 2);
                // TODO: Remove TV Prefix support in Ditto
            } else if (in_array($field, $custom)) {
                $fields['custom'][] = $field;
            } else {
                $fields['unknown'][] = $field;
            }
        }
        return $fields;
    }

    // ---------------------------------------------------
    // Function: replace
    // Replcae placeholders with their values
    // ---------------------------------------------------
    static function replace($placeholders, $tpl)
    {
        $keys = [];
        $values = [];
        foreach ($placeholders as $key => $value) {
            $keys[] = '[+' . $key . '+]';
            $values[] = $value;
        }
        return str_replace($keys, $values, $tpl);
    }

    // ---------------------------------------------------
    // Function: determine
    // Determine the correct template to apply
    // ---------------------------------------------------
    public function determine($templates, $x, $start, $stop, $id)
    {
        // determine current template
        $currentTPL = 'base';
        if ($x % 2 && !empty($templates['alt'])) {
            $currentTPL = 'alt';
        }
        if ($id == evo()->documentObject['id'] && !empty($templates['current'])) {
            $currentTPL = 'current';
        }
        if ($x == 0 && !empty($templates['first'])) {
            $currentTPL = 'first';
        }
        if ($x == ($stop - 1) && !empty($templates['last'])) {
            $currentTPL = 'last';
        }
        $this->current = $currentTPL;
        return $templates[$currentTPL];
    }

    // ---------------------------------------------------
    // Function: fetch
    // Get a template, based on version by Doze
    //
    // https://modxcms.com/forums/index.php/topic,5344.msg41096.html#msg41096
    // ---------------------------------------------------
    public function fetch($tpl)
    {
        $template = $this->_fetch($tpl);

        if (strpos($template, '[!') !== false) {
            return str_replace(['[!', '!]'], '[[', ']]', $template);
        }

        return $template;
    }

    private function _fetch($tpl)
    {
        if (strpos($tpl, 'manager/includes/config.inc.php') !== false) {
            throw new Exception('Invalid template path');
        }

        if (strpos($tpl, '@CHUNK') === 0) {
            return evo()->getChunk(
                preg_replace('/^@CHUNK[: ]?/', '', $tpl)
            );
        }

        if (strpos($tpl, '@FILE') === 0) {
            return file_get_contents(
                preg_replace('/^@FILE[: ]?/', '', $tpl)
            );
        }

        if (strpos($tpl, '@INCLUDE') === 0) {
            return ob_get_include(
                preg_replace('/^@INCLUDE[: ]?/', '', $tpl)
            );
        }

        if (strpos($tpl, '@CODE') === 0) {
            return preg_replace('/^@CODE[: ]?/', '', $tpl);
        }

        if (strpos($tpl, '@DOCUMENT') === 0) {
            $docid = trim(substr($tpl, 10));
            if (!preg_match('/^[1-9][0-9]*$/', $docid)) {
                throw new Exception('Invalid document id');
            }
            return evo()->getField('content', $docid);
        }

        if (evo()->hasChunk($tpl)) {
            return evo()->getChunk($tpl);
        }

        return $tpl;
    }
}
