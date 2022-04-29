<?php
/**
 * ManagerManager plugin
 * @version 0.4 (2012-11-14)
 *
 * @for MODX Evolution 1.0.x
 *
 * @author Nick Crossland - www.rckt.co.uk, DivanDesign studio - www.DivanDesign.biz
 *
 * @description Used to manipulate the display of document fields in the manager.
 *
 * @installation See /docs/install.htm
 *
 * @inspiration HideEditor plugin by Timon Reinhard and Gildas; HideManagerFields by Brett @ The Man Can!
 *
 * @license Released under the GNU General Public License: http://creativecommons.org/licenses/GPL/2.0/
 *
 * @link http://code.divandesign.biz/modx/managermanager/0.4
 *
 * @copyright 2012
 */

class MANAGERMANAGER
{

    function __construct()
    {
    }

    function run()
    {
        global $modx;

        extract($modx->event->params);
        $mm_version = '0.4';

        $pluginDir = MODX_BASE_PATH . 'assets/plugins/managermanager/';

        //Include Utilites
        include_once($pluginDir . 'utilities.inc.php');

        // When loading widgets, ignore folders / files beginning with these chars
        $ignore_first_chars = array('.', '_', '!');

        // Include widgets
        // We look for a PHP file with the same name as the directory - e.g.
        // /widgets/widgetname/widgetname.php
        $widget_dir = $pluginDir . 'widgets';
        if ($handle = opendir($widget_dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file === '..') {
                    continue;
                }
                if (in_array(substr($file, 0, 1), $ignore_first_chars) || !is_dir($widget_dir . '/' . $file)) {
                    continue;
                }
                include_once sprintf('%s/%s/%s.php', $widget_dir, $file, $file);
            }
            closedir($handle);
        }

        // Set variables
        global $content, $default_template, $mm_current_page, $mm_fields;
        $mm_current_page = array();

        if (isset($_POST['template'])) {
            $mm_current_page['template'] = $_POST['template'];
        } elseif (isset($_GET['newtemplate'])) {
            $mm_current_page['template'] = $_GET['newtemplate'];
        } elseif (isset($content['template'])) {
            $mm_current_page['template'] = $content['template'];
        } else {
            $mm_current_page['template'] = $default_template;
        }

        $mm_current_page['role'] = $_SESSION['mgrRole'];

        // What are the fields we can change, and what types are they?
        $field['pagetitle'] = array('input', 'pagetitle', 'pagetitle');
        $field['longtitle'] = array('input', 'longtitle', 'longtitle');
        $field['description'] = array('textarea', 'description', 'description');
        $field['alias'] = array('input', 'alias', 'alias');
        $field['link_attributes'] = array('input', 'link_attributes', 'link_attributes');
        $field['menutitle'] = array('input', 'menutitle', 'menutitle');
        $field['menuindex'] = array('input', 'menuindex', 'menuindex');
        $field['show_in_menu'] = array('input', 'hidemenucheck', 'hidemenu');
        $field['hide_menu'] = array('input', 'hidemenucheck', 'hidemenu'); // synonym for show_in_menu
        $field['parent'] = array('input', 'parent', 'parent');
        $field['is_folder'] = array('input', 'isfoldercheck', 'isfolder');
        $field['is_richtext'] = array('input', 'richtextcheck', 'richtext');
        $field['log'] = array('input', 'donthitcheck', 'donthit');
        $field['published'] = array('input', 'publishedcheck', 'published');
        $field['pub_date'] = array('input', 'pub_date', 'pub_date');
        $field['unpub_date'] = array('input', 'unpub_date', 'unpub_date');
        $field['searchable'] = array('input', 'searchablecheck', 'searchable');
        $field['cacheable'] = array('input', 'cacheablecheck', 'cacheable');
        $field['clear_cache'] = array('input', 'syncsitecheck', '');
        $field['weblink'] = array('input', 'ta', 'content');
        $field['introtext'] = array('textarea', 'introtext', 'introtext');
        $field['content'] = array('textarea', 'ta', 'content');
        $field['template'] = array('select', 'template', 'template');
        $field['content_type'] = array('select', 'contentType', 'contentType');
        $field['content_dispo'] = array('select', 'content_dispo', 'content_dispo');
        $field['keywords'] = array('select', 'keywords[]', '');
        $field['metatags'] = array('select', 'metatags[]', '');
        $field['which_editor'] = array('select', 'which_editor', '');
        $field['resource_type'] = array('select', 'type', 'isfolder');
        foreach ($field as $k => $a) {
            $mm_fields[$k]['fieldtype'] = $a[0];
            $mm_fields[$k]['fieldname'] = $a[1];
            $mm_fields[$k]['dbname'] = $a[2];
            $mm_fields[$k]['tv'] = false;
            $mm_fields[$k]['tvtype'] = false;
        }
        unset($field);

        // Add in TVs to the list of available fields
        $all_tvs = db()->makeArray(
            db()->select(
                'name,type,id,elements'
                , '[+prefix+]site_tmplvars'
                , ''
                , 'name ASC'
            )
        );
        foreach ($all_tvs as $thisTv) {
            $n = $thisTv['name']; // What is the field name?

            // Checkboxes place an underscore in the ID, so accommodate this...
            $fieldname_suffix = '';

            switch ($thisTv['type']) { // What fieldtype is this TV type?
                case 'textarea':
                case 'rawtextarea':
                case 'textareamini':
                case 'richtext':
                    $t = 'textarea';
                    break;

                case 'dropdown':
                case 'listbox':
                    $t = 'select';
                    break;

                case 'listbox-multiple':
                    $t = 'select';
                    $fieldname_suffix = '\\\\[\\\\]';
                    break;

                case 'checkbox':
                    $t = 'input';
                    $fieldname_suffix = '\\\\[\\\\]';
                    break;

                case 'custom_tv':
                    if (strpos($thisTv['elements'], 'tvtype="text"') !== false) {
                        $t = 'input';
                    } elseif (strpos($thisTv['elements'], 'tvtype="textarea"') !== false) {
                        $t = 'textarea';
                    } elseif (strpos($thisTv['elements'], 'tvtype="select"') !== false) {
                        $t = 'select';
                    } elseif (strpos($thisTv['elements'], 'tvtype="checkbox"') !== false) {
                        $t = 'input';
                        $fieldname_suffix = '\\\\[\\\\]';
                    } elseif (strpos($thisTv['elements'], '<textarea') !== false) {
                        $t = 'textarea';
                    } elseif (strpos($thisTv['elements'], '<select') !== false) {
                        $t = 'select';
                    } elseif (strpos($thisTv['elements'], '"checkbox"') !== false) {
                        $t = 'input';
                        $fieldname_suffix = '\\\\[\\\\]';
                    } else {
                        $t = 'input';
                    }
                    break;

                case 'tags':
                    $t = 'select';
                    $fieldname_suffix = '\\\\[\\\\]';
                    break;

                default:
                    $t = 'input';
                    break;
            }

            // check if there are any name clashes between TVs and default field names? If there is, preserve the default field
            if (!isset($mm_fields[$n])) {
                $mm_fields[$n] = array(
                    'fieldtype' => $t,
                    'fieldname' => sprintf('tv%s%s', $thisTv['id'], $fieldname_suffix),
                    'dbname' => '',
                    'tv' => true,
                    'tvtype' => $thisTv['type']
                );
            }

            $mm_fields[sprintf('tv%s%s', $thisTv['id'], $fieldname_suffix)] = array(
                'fieldtype' => $t,
                'fieldname' => 'tv' . $thisTv['id'] . $fieldname_suffix,
                'dbname' => '',
                'tv' => true,
                'tvtype' => $thisTv['type']
            );
        }

        // Check the current event
        global $e;
        $e = &$modx->event;

        // The start of adding or editing a document (before the main form)
        switch ($e->name) {
            // if it's the plugin config form, give us a copy of all the relevant values
            case 'OnPluginFormRender':
                include_once($pluginDir . 'libs/OnPluginFormRender.inc.php');
                break;

            case 'OnDocFormPrerender':
                include_once($pluginDir . 'libs/OnDocFormPrerender.inc.php');
                break;

            // The main document editing form
            case 'OnDocFormRender':
                include_once($pluginDir . 'libs/OnDocFormRender.inc.php');
                break;

            case 'OnBeforeDocFormSave':
                global $template;

                $mm_current_page['template'] = $template;

                $this->make_changes($config_chunk);
                break;

            case 'OnManagerMainFrameHeaderHTMLBlock':
                global $action;
                if (empty($action) && getv('a')) {
                    $action = getv('a');
                }
                switch ($action) {
                    case '4':
                    case '27':
                    case '72':
                    case '73':
                    case '76':
                    case '300':
                    case '301':
                        $output = '<!-- Begin ManagerManager output -->' . "\n";
                        $e->output($output);
                        break;
                    default:
                        return;
                }

                break;

        } // end switch
    }

    function make_changes($chunk)
    {
        if(strpos($chunk, '@FILE')===0) {
            return evo()->atBindFile($chunk);
        }
        if(strpos($chunk, '@INCLUDE')===0) {
            return evo()->atBindInclude($chunk);
        }
        $chunk_output = evo()->getChunk($chunk);
        if ($chunk_output) {
            eval($chunk_output); // If there is, run it.
            return "// Getting rules from chunk: $chunk \n\n";
        }

        $files = array(
            MODX_BASE_PATH . $chunk,
            str_replace('\\', '/', __DIR__) . '/mm_rules.inc.php'
        );
        foreach ($files as $config_file) {
            if (is_file($config_file) && trim(file_get_contents($config_file))) {
                include($config_file);
                return "// Getting rules from file: $config_file \n\n";
            }
        }
        return "// No rules found \n\n";
    }

    function rule_exists($chunk_name)
    {
        if (evo()->getChunk($chunk_name)) {
            return true;
        }

        $files = array(
            MODX_BASE_PATH . $chunk_name,
            str_replace('\\', '/', __DIR__) . '/mm_rules.inc.php'
        );
        foreach ($files as $config_file) {
            if (is_file($config_file) && trim(file_get_contents($config_file))) {
                return true;
            }
        }
        return false;
    }
}
