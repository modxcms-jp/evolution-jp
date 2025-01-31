<?php

/*
 * Title: Debug Class
 * Purpose:
 *  	The Debug class contains all functions relating to Ditto's
 * 		implimentation of the MODX debug console
*/
if (!defined('MODX_BASE_PATH') || strpos(str_replace('\\', '/', __FILE__), MODX_BASE_PATH) !== 0) exit;

class debug extends modxDebugConsole
{
    public $debug;

    // ---------------------------------------------------
    // Function: render_link
    // Render the links to the debug console
    // ---------------------------------------------------
    function render_link($dittoID, $ditto_base)
    {
        global $ditto_lang;
        return $this->makeLink(
            $ditto_lang['debug'],
            $ditto_lang['open_dbg_console'],
            $ditto_lang['save_dbg_console'],
            str_replace(MODX_BASE_PATH, MODX_SITE_URL, $ditto_base . 'debug/'),
            'ditto_' . $dittoID
        );
    }
    // ---------------------------------------------------
    // Function: render_popup
    // Render the contents of the debug console
    // ---------------------------------------------------
    function render_popup(
        $ditto,
        $ditto_base,
        $ditto_version,
        $ditto_params,
        $IDs,
        $fields,
        $summarize,
        $templates,
        $orderBy,
        $start,
        $stop,
        $total,
        $filter,
        $resource
    ) {
        global $ditto_lang;
        $tabs = [];
        if ($fields['db'] && $fields['tv']) {
            $fields = array_merge_recursive($ditto->fields, ['retrieved' => $fields]);
        } else {
            $fields = $ditto->fields;
        }

        $tabs[$ditto_lang['info']] = $this->prepareBasicInfo(
            $ditto_version,
            $IDs,
            $summarize,
            $orderBy,
            $start,
            $stop,
            $total
        );
        $tabs[$ditto_lang['params']] = $this->makeParamTable($ditto_params, $ditto_lang['params']);
        $tabs[$ditto_lang['fields']] = sprintf(
            '<div class="ditto_dbg_fields">%s</div>',
            $this->array2table($this->cleanArray($fields), true, true)
        );
        $tabs[$ditto_lang['templates']] = $this->makeParamTable(
            $this->prepareTemplates($templates),
            $ditto_lang['templates']
        );

        if ($filter !== false) {
            $tabs[$ditto_lang['filters']] = $this->prepareFilters($this->cleanArray($filter));
        }

        if ($ditto->prefetch === true) {
            $tabs[$ditto_lang['prefetch_data']] = $this->preparePrefetch($ditto->prefetch);
        }
        if ($resource) {
            $tabs[$ditto_lang['retrieved_data']] = $this->prepareDocumentInfo($resource);
        }

        return $this->render(
            $tabs,
            $ditto_lang['debug'],
            str_replace(MODX_BASE_PATH, MODX_SITE_URL, $ditto_base)
        )
            . "\r\n\r\n" . '<!--- ' . date('c') . ' --->';
    }

    // ---------------------------------------------------
    // Function: preparePrefetch
    // Create the content of the Prefetch tab
    // ---------------------------------------------------
    function preparePrefetch($prefetch)
    {
        global $ditto_lang;
        $ditto_IDs = [];
        if ($prefetch['dbg_IDs_pre']) {
            $k = sprintf('%s (%s)', $ditto_lang['ditto_IDs_all'], count($prefetch['dbg_IDs_pre']));
            $ditto_IDs[$k] = implode(',', $prefetch['dbg_IDs_pre']);
        }
        if ($prefetch['dbg_IDs_post']) {
            $k = sprintf('%s (%s)', $ditto_lang['ditto_IDs_selected'], count($prefetch['dbg_IDs_post']));
            $ditto_IDs[$k] = implode(', ', $prefetch['dbg_IDs_post']);
        } else {
            $k = sprintf('%s (0)', $ditto_lang['ditto_IDs_selected']);
            $ditto_IDs[$k] = strip_tags($ditto_lang['no_documents']);
        }
        $out = $this->array2table([$ditto_lang['prefetch_data'] => $ditto_IDs], true, true);
        return $out . $this->prepareDocumentInfo($prefetch['dbg_resource']);
    }

    // ---------------------------------------------------
    // Function: prepareFilters
    // Create the content of the Filters tab
    // ---------------------------------------------------
    function prepareFilters($filter)
    {
        $output = '';
        foreach ($filter as $name => $value) {
            if ($name === 'custom') {
                foreach ($value as $k => $v) {
                    $output .= $this->array2table([$k => $v], true, true);
                }
            } else {
                $output .= $this->array2table([$name => $value], true, true);
            }
        }
        return $output;
    }

    // ---------------------------------------------------
    // Function: prepareDocumentInfo
    // Create the output for the Document Info tab
    // ---------------------------------------------------
    function prepareDocumentInfo($resource)
    {
        global $modx;
        if (!$resource) {
            return '';
        }
        $output = '';
        foreach ($resource as $item) {
            $item['createdon'] = sprintf(
                '%s(%s)',
                $item['createdon'],
                $modx->toDateFormat(
                    $item['createdon']
                )
            );
            $output .= $this->makeParamTable(
                $item,
                str_replace(
                    ['[+pagetitle+]', '[+id+]'],
                    [$item['pagetitle'], $item['id']],
                    $this->templates['item']
                ),
                true,
                true,
                true,
                'resource'
            );
        }
        return $output;
    }

    // ---------------------------------------------------
    // Function: prepareBasicInfo
    // Create the outut for the Info ta
    // ---------------------------------------------------
    function prepareBasicInfo($ditto_version, $IDs, $summarize, $orderBy, $start, $stop, $total)
    {
        global $ditto_lang;
        $items[$ditto_lang['version']] = $ditto_version;
        $items[$ditto_lang['summarize']] = $summarize;
        $items[$ditto_lang['total']] = $total;
        $items[$ditto_lang['start']] = $start;
        $items[$ditto_lang['stop']] = $stop;
        if ($IDs) {
            $items[$ditto_lang['ditto_IDs']] = wordwrap(implode(', ', $IDs), 100, '<br />');
        } else {
            $items[$ditto_lang['ditto_IDs']] = $ditto_lang['none'];
        }
        $output = '';
        if (is_array($orderBy['parsed']) && $orderBy['parsed']) {
            $sort = [];
            foreach ($orderBy['parsed'] as $key => $value) {
                $sort[$key] = [$ditto_lang['sortBy'] => $value[0], $ditto_lang['sortDir'] => $value[1]];
            }
            $output = $this->array2table($this->cleanArray($sort), true, true);
        }
        return $this->makeParamTable($items, $ditto_lang['basic_info'], false, false) . $output;
    }

    // ---------------------------------------------------
    // Function: prepareTemplates
    // Create the output for the Templates tab
    // ---------------------------------------------------
    function prepareTemplates($templates)
    {
        $displayTPLs = [];
        foreach ($templates as $name => $value) {
            switch ($name) {
                case 'base':
                    $displayName = 'tpl';
                    break;

                case 'default':
                    $displayName = 'tpl';
                    break;

                default:
                    $displayName = 'tpl' . strtoupper($name[0]) . substr($name, 1);
                    break;
            }
            $displayTPLs[$displayName] = $value;
        }
        return $displayTPLs;
    }
}
