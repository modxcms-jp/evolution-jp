<?php

/*
 * Title: Template Class
 * Purpose:
 *      The Template class contains all functions relating to Ditto's
 *      handling of templates and any supporting functions they need
*/

class template{
    var $language,$fields,$current;

    // ---------------------------------------------------
    // Function: template
    // Set the class language and fields variables
    // ---------------------------------------------------
    public function __construct() {
        $this->language = $GLOBALS['ditto_lang'];
        $this->fields = array (
            'db' => array (),
            'tv' => array (),
            'custom' => array (),
            'item' => array (),
            'qe' => array (),
            'phx' => array (),
            'rss' => array (),
            'json' => array (),
            'xml' => array (),
            'unknown' => array()
        );
    }

    // ---------------------------------------------------
    // Function: process
    // Take the templates and parse them for tempalte variables,
    // Check to make sure they have fields, and sort the fields
    // ---------------------------------------------------
    function process($template) {
        if (!isset($template['base'])) {
            $template['base'] = $template['default'];
        } else {
            unset($template['default']);
        }
        foreach ($template as $name=>$tpl) {
            if(!empty($tpl) && $tpl != '') {
                $templates[$name] = $this->fetch($tpl);
            }
        }
        $fieldList = array();
        foreach ($templates as $tplName=>$tpl) {
            $check = $this->findTemplateVars($tpl);
            if (is_array($check)) {
                $fieldList = array_merge($check, $fieldList);
            } else {
                switch ($tplName) {
                    case 'base':
                        $displayName = 'tpl';
                    break;
                    
                    case 'default':
                        $displayName = 'tpl';
                    break;
                    
                    default:
                        $displayName = 'tpl' .$tplName;
                    break;
                }
                $templates[$tplName] = str_replace('[+tpl+]',$displayName,$this->language['bad_tpl']);
            }
        }

        $fieldList = array_unique($fieldList);
        $fields = $this->sortFields($fieldList);
        $checkAgain = array ('qe', 'json', 'xml');
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
    function findTemplateVars($tpl) {
        $matches = $this->getTagsFromContent($tpl);
        if(!$matches) return $tpl;
        $TVs = array();
        foreach($matches[1] as $tv) {
            $match = explode(':', $tv);
            $TVs[strtolower($match[0])] = $match[0];
        }
        if (count($TVs) >= 1) {
            return array_values($TVs);
        }

        return false;
    }
    
    function getTagsFromContent($tpl) {
        global $modx;
        
        $matches = $modx->getTagsFromContent($tpl,'[+','+]');
        if(!$matches) return false;
        foreach($matches[1] as $v) {
            if(strpos($v,'[+')!=false) {
                $pair = $this->getTagsFromContent($v);
                $matches[0] = array_merge($matches[0],$pair[0]);
                $matches[1] = array_merge($matches[1],$pair[1]);
            }
        }
        return $matches;
    }

    // ---------------------------------------------------
    // Function: sortFields
    // Sort the array of fields provided by type
    // ---------------------------------------------------
    function sortFields ($fieldList) {
        global $ditto_constantFields;
        $dbFields = $ditto_constantFields['db'];
        $tvFields = $ditto_constantFields['tv'];
        $fields = array (
            'db' => array (),
            'tv' => array (),
            'custom' => array (),
            'item' => array (),
            'qe' => array (),
            'phx' => array (),
            'rss' => array (),
            'json' => array (),
            'xml' => array (),
            'unknown' => array()
        );
        
        $custom = array('author', 'date', 'url', 'title', 'ditto_iteration', 'class');

        foreach ($fieldList as $field) {
            if (strpos($field, 'rss_') === 0) {
                $fields['rss'][] = substr($field,4);
            }else if (strpos($field, 'xml_') === 0) {
                $fields['xml'][] = substr($field,4);
            }else if (strpos($field, 'json_') === 0) {
                $fields['json'][] = substr($field,5);
            }else if (strpos($field, 'item[') === 0) {
                $fields['item'][] = substr($field, 4);
            }else if (strpos($field, '#') === 0) {
                $fields['qe'][] = substr($field,1);
            }else if (strpos($field, 'phx:') === 0) {
                $fields['phx'][] = $field;
            }else if (in_array($field, $dbFields)) {
                $fields['db'][] = $field;
            }else if(in_array($field, $tvFields)){
                $fields['tv'][] = $field;
            }else if(strpos($field, 'tv') === 0 && in_array(substr($field,2), $tvFields)) {
                $fields['tv'][] = substr($field,2);
                    // TODO: Remove TV Prefix support in Ditto
            }else if (in_array($field, $custom)) {
                $fields['custom'][] = $field;
            }else {
                $fields['unknown'][] = $field; 
            }
        }
        return $fields;
    }

    // ---------------------------------------------------
    // Function: replace
    // Replcae placeholders with their values
    // ---------------------------------------------------
    static function replace( $placeholders, $tpl ) {
        $keys = array();
        $values = array();
        foreach ($placeholders as $key=>$value) {
            $keys[] = '[+'.$key.'+]';
            $values[] = $value;
        }
        return str_replace($keys,$values,$tpl);
    }

    // ---------------------------------------------------
    // Function: determine
    // Determine the correct template to apply
    // ---------------------------------------------------        
    public function determine($templates, $x, $start, $stop, $id) {
        global $modx;

        // determine current template
        $currentTPL = 'base';
        if ($x % 2 && !empty($templates['alt'])) {
            $currentTPL = 'alt';
        }
        if ($id == $modx->documentObject['id'] && !empty($templates['current'])){
            $currentTPL = 'current';
        }
        if ($x == 0 && !empty($templates['first'])) {
            $currentTPL = 'first';
        }
        if ($x == ($stop -1) && !empty($templates['last'])) {
            $currentTPL = 'last';
        } 
        $this->current = $currentTPL;
        return $templates[$currentTPL];
    }

    // ---------------------------------------------------
    // Function: fetch
    // Get a template, based on version by Doze
    // 
    // http://modxcms.com/forums/index.php/topic,5344.msg41096.html#msg41096
    // ---------------------------------------------------
    public function fetch($tpl){
        global $modx;
        $template = '';
        if(strpos($tpl, '@CHUNK') === 0) {
            $template = $modx->getChunk(substr($tpl, 7));
        } elseif(strpos($tpl, '@FILE') === 0) {
            $path = trim(substr($tpl, 6));
            if(strpos($path, $modx->config['base_url'].'manager/includes/config.inc.php')===false)
                $template = file_get_contents($path);
        } elseif(strpos($tpl, '@CODE') === 0) {
            $template = substr($tpl, 6);
        } elseif(strpos($tpl, '[+') !==false) {
            $template = $tpl;
        } elseif(strpos($tpl, '@DOCUMENT') === 0) {
            $docid = trim(substr($tpl, 10));
            if(preg_match('@^[1-9][0-9]*$@',$docid))
                $template = $modx->getField('content',$docid);
        } else {
            $template = $modx->getChunk($tpl);
        }
        
        if(strpos($template,'[!')!==false) {
            $template = str_replace(array('[!', '!]'), array('[[', ']]'), $template);
        }
        elseif($template===''||$template===false) {
            $template = $this->language['missing_placeholders_tpl'];
        }
        return $template;
    }
}
