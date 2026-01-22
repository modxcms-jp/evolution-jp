<?php

/*
 * Title: Main Class
 * Purpose:
 *      The Ditto class contains all functions relating to Ditto's
 *      functionality and any supporting functions they need
*/

class ditto
{
    public $template, $resource, $format, $debug, $advSort, $sqlOrderBy, $customReset, $fields, $constantFields, $prefetch, $sortOrder, $customPlaceholdersMap;
    public $tmpCache = [];

    function __construct($format, $language, $debug)
    {
        $this->format = $format;
        $GLOBALS['ditto_lang'] = $language;
        $this->prefetch = false;
        $this->advSort = false;
        $this->sqlOrderBy = [];
        $this->customReset = [];
        $this->constantFields[] = ['db', 'tv'];
        $this->constantFields['db'] = [
            'id', 'type', 'contentType', 'pagetitle', 'longtitle',
            'description', 'alias', 'link_attributes', 'published',
            'pub_date', 'unpub_date', 'parent', 'isfolder',
            'introtext', 'content', 'richtext', 'template',
            'menuindex', 'searchable', 'cacheable', 'createdby',
            'createdon', 'editedby', 'editedon', 'deleted',
            'deletedon', 'deletedby', 'publishedon', 'publishedby',
            'menutitle', 'donthit', 'haskeywords', 'hasmetatags',
            'privateweb', 'privatemgr', 'content_dispo', 'hidemenu'
        ];
        $this->constantFields['tv'] = $this->getTVList();
        $GLOBALS['ditto_constantFields'] = $this->constantFields;
        $this->fields = [
            'display' => [],
            'backend' => [
                'tv' => [],
                'db' => ['id', 'published']
            ]
        ];
        $this->sortOrder = false;
        $this->customPlaceholdersMap = [];
        $this->template = new template();

        if ($debug !== null) {
            $this->debug = new debug($debug);
        }
    }

    // ---------------------------------------------------
    // Function: getTVList
    // Get a list of all available TVs
    // ---------------------------------------------------

    function getTVList()
    {
        static $dbfields = null;

        if ($dbfields !== null) {
            return $dbfields;
        }

        $rs = db()->select('name', '[+prefix+]site_tmplvars');
        $dbfields = [];
        while ($row = db()->getRow($rs)) {
            $dbfields[] = $row['name'];
        }
        return $dbfields;
    }

    // ---------------------------------------------------
    // Function: addField
    // Add a field to the internal field detection system
    // ---------------------------------------------------

    function addField($name, $location, $type = false)
    {
        if ($type === false) {
            $type = $this->getDocVarType($name);
        }
        if ($type === 'tv:prefix') {
            $type = 'tv';
            $name = substr($name, 2);
        }
        if ($location === '*') {
            $this->fields['backend'][$type][] = $name;
            $this->fields['display'][$type][] = $name;
        } else {
            $this->fields[$location][$type][] = $name;
        }
    }

    // ---------------------------------------------------
    // Function: addFields
    // Add a field to the internal field detection system
    // from an array or delimited string
    // ---------------------------------------------------

    function addFields($fields, $location = '*', $delimiter = ',', $callback = null)
    {
        if (empty($fields)) return false;
        if (!is_array($fields)) {
            if (strpos($fields, $delimiter) !== false) {
                $fields = explode($delimiter, $fields);
            } else {
                $fields = [$fields];
            }
        }
        foreach ($fields as $field) {
            if (is_array($field)) {
                $type = isset($field[2]) ? $field[2] : false;
                $name = $field[0];
            } else {
                $name = $field;
                $type = false;
            }

            $this->addField($name, $location, $type);
            if ($callback && is_callable($callback)) {
                call_user_func_array($callback, [$name]);
            }
        }
        return true;
    }

    function removeField($name, $location, $type)
    {
        $key = array_search($name, $this->fields[$location][$type]);
        if ($key !== false) {
            unset($this->fields[$location][$type][$key]);
        }
    }

    // ---------------------------------------------------
    // Function: setDisplayFields
    // Move the detected fields into the Ditto fields array
    // ---------------------------------------------------

    function setDisplayFields($fields, $hiddenFields)
    {
        $this->fields['display'] = $fields;
        if ($this->fields['display']['qe']) {
            $this->addField('pagetitle', 'display', 'db');
        }
        if ($hiddenFields) {
            $this->addFields($hiddenFields, 'display');
        }
    }

    // ---------------------------------------------------
    // Function: getDocVarType
    // Determine if the provided field is a tv, a database field, or something else
    // ---------------------------------------------------

    function getDocVarType($field)
    {
        global $ditto_constantFields;
        $tvFields = $ditto_constantFields['tv'];
        $dbFields = $ditto_constantFields['db'];

        if (in_array($field, $tvFields, true)) {
            return 'tv';
        }

        if (in_array(substr($field, 2), $tvFields, true)) {
            return 'tv:prefix';
            // TODO: Remove TV Prefix support
        }

        if (in_array($field, $dbFields, true)) {
            return 'db';
        }

        return 'unknown';
    }

    // ---------------------------------------------------
    // Function: parseOrderBy
    // Parse out orderBy parameter string
    // ---------------------------------------------------

    function parseOrderBy($orderBy, $randomize)
    {
        if ($randomize != 0) return false;
        $orderBy['sql'] = [];

        foreach ($orderBy['parsed'] as $item) {
            $this->addFields($item[0], 'backend');
            $this->checkAdvSort($item[0], $item[1]);
        }

        foreach ($orderBy['custom'] as $item) {
            $this->addFields($item[0], 'backend');
            $this->checkAdvSort($item[0]);
        }

        if ($orderBy['unparsed'] !== null) {
            $inputs = explode(',', $orderBy['unparsed']);
            foreach ($inputs as $input) {
                $input = trim($input);
                $position = strrpos($input, ' ');
                // find last space
                $sortBy = substr($input, 0, $position);
                if (!$sortBy) {
                    $sortBy = 'id';
                }
                $sortDir = substr($input, $position);
                $sortDir = $sortDir ? trim($sortDir) : 'asc';
                $sortBy = $this->checkAdvSort($sortBy, $sortDir);
                $this->addField($sortBy, 'backend');
                $orderBy['parsed'][] = [$sortBy, strtoupper($sortDir)];
            }
        }
        $orderBy['sql'] = implode(', ', $this->sqlOrderBy);
        unset($orderBy['unparsed']);
        return $orderBy;
    }

    // ---------------------------------------------------
    // Function: checkAdvSort
    // Check the advSortString
    // ---------------------------------------------------
    function checkAdvSort($sortBy, $sortDir = 'asc')
    {
        $advSort = ['pub_date', 'unpub_date', "editedon", "deletedon", "publishedon"];
        $type = $this->getDocVarType($sortBy);
        switch ($type) {
            case 'tv:prefix':
                $sortBy = substr($sortBy, 2);
                $this->advSort = true;
                break;
            case 'tv':
                $this->advSort = true;
                break;
            case 'db':
                if (in_array($sortBy, $advSort)) {
                    $this->advSort = true;
                    $this->customReset[] = $sortBy;
                } else {
                    $this->sqlOrderBy[] = 'sc.' . $sortBy . ' ' . $sortDir;
                }
                break;
        }
        return $sortBy;
    }

    // ---------------------------------------------------
    // Function: parseFilters
    // Split up the filters into an array and add the required fields to the fields array
    // ---------------------------------------------------

    public function parseFilters($filter_params = false, $cFilters = [], $pFilters = [], $globalDelimiter = ',', $localDelimiter = ':')
    {
        $parsedFilters = ['basic' => [], 'custom' => []];
        $filters = explode($globalDelimiter, $filter_params);
        if ($filter_params) {
            foreach ($filters as $filter) {
                if (!$filter) {
                    continue;
                }
                $filterArray = explode($localDelimiter, $filter);
                $source = $filterArray[0];
                $this->addField($source, 'backend');
                $value = $filterArray[1];
                $mode = isset ($filterArray[2]) ? $filterArray[2] : 1;
                $parsedFilters['basic'][] = ['source' => $source, 'value' => $value, 'mode' => $mode];
            }
        }
        if ($cFilters) {
            foreach ($cFilters as $name => $value) {
                if ($name && $value) {
                    $parsedFilters['custom'][$name] = $value[1];
                    $this->addFields($value[0], 'backend');
                }
            }
        }
        if ($pFilters) {
            foreach ($pFilters as $filter) {
                foreach ($filter as $name => $value) {
                    $parsedFilters['basic'][] = $value;
                    $this->addFields($value['source'], 'backend');
                }
            }
        }
        return $parsedFilters;
    }

    // ---------------------------------------------------
    // Function: render
    // Render the document output
    // ---------------------------------------------------

    function render($resource, $template, $removeChunk, $dateSource, $dateFormat, $ph = [], $x = 0)
    {
        global $ditto_lang;

        if (!is_array($resource)) {
            return $ditto_lang['resource_array_error'];
        }

        $placeholders = [];
        $contentVars = [];
        foreach ($resource as $name => $value) {
            $placeholders[$name] = $value;
            $contentVars['[*' . $name . '*]'] = $value;
        }

        $placeholders = $this->setCustomVar($placeholders, $resource, $dateFormat, $dateSource, $x);

        if (in_array('content', $this->fields['display']['db'], true) && $this->format !== 'html') {
            $placeholders['content'] = $this->relToAbs($resource['content'], MODX_SITE_URL);
        }

        if (in_array('introtext', $this->fields['display']['db'], true) && $this->format !== 'html') {
            $placeholders['introtext'] = $this->relToAbs($resource['introtext'], MODX_SITE_URL);
        }

        $customPlaceholders = $ph;
        // set custom placeholder
        foreach ($ph as $name => $value) {
            if ($name === '*') {
                continue;
            }
            $placeholders[$name] = call_user_func($value[1], $resource);
            unset($customPlaceholders[$name]);
        }

        foreach ($customPlaceholders as $name => $cmd) {
            $placeholders = $cmd($placeholders);
        }

        $PHs = $placeholders;
        foreach ($PHs as $key => $output) {
            $placeholders[$key] = str_replace(array_keys($contentVars), array_values($contentVars), $output);
        }
        unset($PHs);

        $output = $template;
        $i = 0;
        while ($i < 10) {
            $_ = $output;
            $output = evo()->parseText($output, $placeholders);
            if ($_ === $output) break;
            $i++;
        }

        if ($removeChunk) {
            foreach ($removeChunk as $chunk) {
                $output = str_replace(
                    ['{{' . $chunk . '}}', evo()->getChunk($chunk)],
                    '',
                    $output
                );
                // remove chunk that is not wanted
            }
        }

        return $output;
    }

    function setCustomVar($placeholders, $resource, $dateFormat, $dateSource, $x)
    {
        $custom_v = &$this->fields['display']['custom'];

        if (in_array('author', $custom_v, true)) {
            $placeholders['author'] = self::getAuthor($resource['createdby']);
        } // set author placeholder
        if (in_array('title', $custom_v, true)) {
            $placeholders['title'] = $resource['pagetitle'];
        }// set title placeholder
        if (in_array('ditto_iteration', $custom_v, true)) {
            $placeholders['ditto_iteration'] = $x;
        } // set sequence placeholder

        if (in_array('class', $custom_v, true)) {
            if (evo()->documentIdentifier == $resource['id']) {
                $placeholders['class'] = 'active';
            } else {
                $placeholders['class'] = '';
            }
        }

        // set url placeholder
        if (in_array('url', $custom_v, true)) {
            if ($resource['id'] == evo()->config('site_start')) {
                $placeholders['url'] = MODX_SITE_URL;
            } else {
                $placeholders['url'] = evo()->makeURL($resource['id'], '', '', 'full');
            }
        }

        if (in_array('date', $custom_v, true)) {
            if ($resource[$dateSource] != '0') {
                $timestamp = $resource[$dateSource];
            } else {
                $timestamp = $resource['createdon'];
            }
            if (is_array($timestamp)) {
                if (!preg_match('@^[1-9][0-9]*$@', $timestamp[1])) {
                    $timestamp[1] = strtotime($timestamp[1]);
                }
                $timestamp = $timestamp[1] + $timestamp[0];
            }
            $placeholders['date'] = $this->mb_strftime($dateFormat, $timestamp);
        }

        return $placeholders;
    }

    function parseFields($placeholders, $seeThruUnpub, $dateSource, $randomize)
    {
        $this->parseCustomPlaceholders($placeholders);
        $this->parseDBFields($seeThruUnpub);
        if ($randomize != 0) {
            $this->addField($randomize, 'backend');
        }
        $this->addField('id', 'display', 'db');
        $this->addField('pagetitle', 'display', 'db');
        $this->addField('parent', 'display', 'db');
        $checkOptions = ['pub_date', 'unpub_date', 'editedon', 'deletedon', 'publishedon'];
        if (in_array($dateSource, $checkOptions)) {
            $this->addField('createdon', 'display');
        }
        if (in_array('date', $this->fields['display']['custom'], true)) {
            $this->addField($dateSource, 'display');
        }
        $this->fields = $this->arrayUnique($this->fields);
    }


    // ---------------------------------------------------
    // Function: arrayUnique
    // Make fields array unique
    // ---------------------------------------------------
    function arrayUnique($array)
    {
        foreach ($array as $u => $a) {
            foreach ($a as $n => $b) {
                $array[$u][$n] = array_unique($b);
            }
        }
        return $array;
    }

    // ---------------------------------------------------
    // Function: parseCustomPlaceholders
    // Parse the required fields out of the custom placeholders
    // ---------------------------------------------------

    function parseCustomPlaceholders($placeholders)
    {
        foreach ($placeholders as $name => $value) {
            $this->addField($name, 'display', 'custom');
            $this->removeField($name, 'display', 'unknown');
            $source = $value[0];

            if (is_array($source)) {
                if (strpos($source[0], ',') !== false) {
                    $fields = explode(',', $source[0]);
                    foreach ($fields as $field) {
                        if (!empty($field)) {
                            $this->addField($field, $source[1]);
                            $this->customPlaceholdersMap[$name] = $field;
                        }
                    }
                } else {
                    $this->addField($source[0], $source[1]);
                    $this->customPlaceholdersMap[$name] = $source[0];
                }
            } else if (is_array($value)) {
                $fields = explode(',', $source);
                foreach ($fields as $field) {
                    if (!empty($field)) {
                        $field = trim($field);
                        $this->addField($field, 'display');
                        $this->customPlaceholdersMap[$name] = $field;
                    }
                }
            }

            $qe = $value[2] ?? null;
            if ($qe === null) {
                return;
            }
            $this->customPlaceholdersMap[$name] = ['qe', $qe];
        }
    }

    // ---------------------------------------------------
    // Function: parseDBFields
    // Parse out the fields required for each state
    // ---------------------------------------------------

    function parseDBFields($seeThruUnpub)
    {
        if (!$seeThruUnpub) {
            $this->addField('parent', 'backend', 'db');
        }

        if (in_array('author', $this->fields['display']['custom'], true)) {
            $this->fields['display']['db'][] = 'createdby';
        }

        if (count($this->fields['display']['tv']) >= 0) {
            $this->addField('published', 'display', 'db');
        }
    }

    // ---------------------------------------------------
    // Function: getAuthor
    // Get the author name, or if not available the username
    // ---------------------------------------------------

    static function getAuthor($createdby)
    {
        if ($createdby > 0) {
            $user = evo()->getUserInfo($createdby);
        } else {
            $user = evo()->getWebUserInfo(abs($createdby));
        }

        if ($user === false) {
            $user = evo()->getUserInfo(1);
        }

        return ($user['fullname'] != '') ? $user['fullname'] : $user['username'];
    }

    // ---------------------------------------------------
    // Function: userSort
    // Sort the resource array by a user defined function
    // ---------------------------------------------------
    function userSort($resource, $sort)
    {
        foreach ($sort['custom'] as $item) {
            usort($resource, $item[1]);
        }
        return $resource;
    }

    // ---------------------------------------------------
    // Function: multiSort
    // Sort the resource array by multiple fields
    // Rows->Columns portion by Jon L. -- intel352@gmail.com
    // Link: http://de3.php.net/manual/en/function.array-multisort.php#73498
    // ---------------------------------------------------

    function multiSort($resource, $orderBy)
    {
        $sort_arr = [];
        foreach ($resource as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $sort_arr[$key][$uniqid] = $value;
            }
        }

        $array_multisort = 'return array_multisort(';
        foreach ($orderBy['parsed'] as $sort) {
            $array_multisort .= sprintf('$sort_arr["%s"], SORT_%s, ', $sort[0], $sort[1]);
        }
        $array_multisort .= '$resource);';
        eval($array_multisort);
        return $resource;
    }

    // ---------------------------------------------------
    // Function: determineIDs
    // Get Document IDs for future use
    // ---------------------------------------------------

    function determineIDs(
        $IDs,
        $IDType,
        $TVs,
        $orderBy,
        $depth,
        $showPublishedOnly,
        $seeThruUnpub,
        $hideFolders,
        $hidePrivate,
        $showInMenuOnly,
        $myWhere,
        $keywords,
        $dateSource,
        $limit,
        $summarize,
        $filter,
        $randomize
    )
    {
        if (($summarize == 0 && $summarize !== 'all') || (is_array($IDs) && !$IDs) || $IDs === false) {
            return [];
        }
        // Get starting IDs;
        switch ($IDType) {
            case 'parents':
                $documentIDs = $this->getChildIDs(
                    explode(',', $IDs),
                    $depth
                );
                break;
            case 'documents':
                $documentIDs = explode(',', $IDs);
                break;
        }

        if ($this->advSort == false && $hideFolders == 0 && $showInMenuOnly == 0 && $myWhere == '' && $filter == false && $hidePrivate == 1 && $keywords == 0) {
            $this->prefetch = false;
            $documents = $this->getDocumentsIDs($documentIDs, $showPublishedOnly);
            $documentIDs = [];
            if ($documents) {
                foreach ($documents as $doc) {
                    $documentIDs[] = $doc['id'];
                }
            }
            return $documentIDs;
        }

        $this->prefetch = true;

        // Create where clause
        $where = [];
        if ($hideFolders) {
            $where[] = 'isfolder = 0';
        }
        if ($showInMenuOnly) {
            $where[] = 'hidemenu = 0';
        }
        if ($myWhere != '') {
            $where[] = $myWhere;
        }
        // set limit
        $where = implode(' AND ', $where);
        $limit = ($limit == 0) ? '' : $limit;

        $customReset = $this->customReset;
        if ($keywords) {
            $this->addField('haskeywords', '*', 'db');
            $this->addField('hasmetatags', '*', 'db');
        }
        if ($this->debug) {
            $this->addField('pagetitle', 'backend', 'db');
        }
        if ($customReset) {
            $this->addField('createdon', 'backend', 'db');
        }
        $resource = $this->getDocuments(
            $documentIDs,
            $this->fields['backend']['db'],
            $TVs,
            $orderBy,
            $showPublishedOnly,
            0,
            $hidePrivate,
            $where,
            $limit,
            $keywords,
            $randomize,
            $dateSource
        );
        if ($resource) {
            $resource = array_values($resource);
            // remove #'s from keys

            if (!$seeThruUnpub) {
                $parentList = $this->getParentList();
                // get parent list
            }
            foreach ($resource as $i => $iValue) {
                if (!$seeThruUnpub) {
                    $published = $parentList[$iValue['parent']];
                    if ($published == '0') {
                        unset ($resource[$i]);
                    }
                }
                if ($customReset) {
                    foreach ($customReset as $field) {
                        if ($iValue[$field] === '0') {
                            $resource[$i][$field] = $iValue['createdon'];
                        }
                    }
                }
            }
            if ($this->debug) {
                $dbg_resource = $resource;
            }
            if ($filter) {
                $filterObj = new filter();
                $resource = $filterObj->execute($resource, $filter);
            }
            if (!$resource) {
                return [];
            }
            if ($this->advSort && !$randomize) {
                $resource = $this->multiSort($resource, $orderBy);
            } elseif (event()->param('documents') && !event()->param('orderBy')) {
                $resource = $this->unsort(
                    $resource,
                    event()->param('documents')
                );
            }
            if ($orderBy['custom'] ?? null) {
                $resource = $this->userSort($resource, $orderBy);
            }
            $fields = array_intersect_key($this->fields['backend'], $this->fields['display']);
            $readyFields = [];
            foreach ($fields as $field) {
                foreach ($field as $k => $v) {
                    $readyFields[$k] = $v;
                }
            }
            $processedIDs = [];
            $keep = [];
            foreach ($resource as $key => $value) {
                $processedIDs[] = $value['id'];
                $iKey = '#' . $value['id'];
                foreach ($value as $key2 => $v2) {
                    if (in_array($key2, $readyFields)) {
                        $keep[$iKey][$key2] = $v2;
                    }
                    if ($this->getDocVarType($key2) === 'tv:prefix') {
                        if (in_array(substr($key2, 2), $readyFields)) {
                            $keep[$iKey][$key2] = $v2;
                        }
                    }
                }
            }

            $this->prefetch = ['resource' => $keep, 'fields' => $fields];
            if ($this->debug) {
                $this->prefetch['dbg_resource'] = $dbg_resource;
                $this->prefetch['dbg_IDs_pre'] = $documentIDs;
                $this->prefetch['dbg_IDs_post'] = $processedIDs;
            }
            if ($processedIDs) {
                if ($randomize) {
                    shuffle($processedIDs);
                }
                $this->sortOrder = array_flip($processedIDs);
                // saves the order of the documents for use later
            }
            return $processedIDs;
        }

        return [];
    }

    private function unsort($docs, $ids)
    {
        foreach ($docs as $doc) {
            $docs_tmp[$doc['id']] = $doc;
        }
        $ids = explode(',', $ids);
        $rs = [];
        foreach ($ids as $id) {
            $rs[] = $docs_tmp[$id];
        }
        return $rs;
    }
    // ---------------------------------------------------
    // Function: weightedRandom
    // Execute a random order sort
    // ---------------------------------------------------

    function weightedRandom($resource, $field, $show)
    {
        $type = $this->getDocVarType($field);
        if ($type === 'unknown') {
            return $resource;
            // handle vad field passed
        }
        $random = new random();
        foreach ($resource as $document) {
            $doc = $document;
            $random->add($doc, abs(intval($document[$field])));
        }
        $resource = $random->select_weighted_unique($show);
        shuffle($resource);
        return $resource;
    }


    // ---------------------------------------------------
    // Function: getParentList
    // Get a list of all available parents
    // ---------------------------------------------------

    function getParentList()
    {
        $rs = db()->select('parent,id', '[+prefix+]site_content', 'deleted=0', 'parent, menuindex');
        $kids = [];
        while ($row = db()->getRow($rs)) {
            $kids[] = $row['parent'];
        }
        $parents = [];
        foreach ($kids as $parent) {
            if ($parent == 0) {
                $parents[0] = '1';
            } else {
                $pInfo = evo()->getPageInfo($parent, 0, 'published');
                $parents[$parent] = $pInfo['published'];
            }
        }
        return $parents;
    }

    // ---------------------------------------------------
    // Function: appendTV
    // Apeend a TV to the documents array
    // ---------------------------------------------------

    function appendTV($tvname = '', $docIDs=[])
    {
        $rs = db()->select(
            'stv.*, stc.*',
            [
                '[+prefix+]site_tmplvar_contentvalues stc',
                'LEFT JOIN [+prefix+]site_tmplvars stv ON stv.id=stc.tmplvarid'
            ],
            sprintf(
                "stv.name='%s' AND stc.contentid IN (%s)",
                $tvname,
                implode(',', $docIDs)
            ),
            'stc.contentid ASC'
        );
        $total = db()->count($rs);
        $docs = [];
        while ($row = db()->getRow($rs)) {
            $k = '#' . $row['contentid'];
            $row['docid'] = $row['contentid'];
            $v = evo()->tvProcessor($row);
            $docs[$k][$row['name']] = $v;
            $docs[$k]['tv' . $row['name']] = $v;
        }
        if ($total == count($docIDs)) {
            return $docs;
        }

        $rs = db()->select(
            '*',
            '[+prefix+]site_tmplvars',
            sprintf("name='%s'", $tvname),
            '',
            1
        );
        $row = @db()->getRow($rs);
        if (strtoupper($row['default_text']) === '@INHERIT') {
            $row['value'] = '@INHERIT';
            foreach ($docIDs as $id) {
                $k = '#' . $id;
                if (!isset($docs[$k])) {
                    $row['contentid'] = $id;
                    $row['docid'] = $id;
                    $v = evo()->tvProcessor($row);
                    $docs[$k][$tvname] = $v;
                    $docs[$k]['tv' . $tvname] = $v;
                }
            }
            return $docs;
        }

        $row['contentid'] = '';
        $row['value'] = $row['default_text'];
        foreach ($docIDs as $id) {
            $k = '#' . $id;
            if (isset($docs[$k])) {
                continue;
            }
            $row['docid'] = $id;
            $v = evo()->tvProcessor($row);
            $docs[$k][$tvname] = $v;
            $docs[$k]['tv' . $tvname] = $v;
        }
        return $docs;
    }

    // ---------------------------------------------------
    // Function: appendKeywords
    // Append keywords's to the resource array
    // ---------------------------------------------------

    function appendKeywords($resource)
    {
        $keys = $this->fetchKeywords($resource);
        $resource['keywords'] = $keys;
        return $resource;
    }

    // ---------------------------------------------------
    // Function: fetchKeywords
    // Helper function to <appendKeywords>
    // ---------------------------------------------------

    function fetchKeywords($resource)
    {
        if ($resource['haskeywords'] == 1) {
            $metas = implode(',', evo()->getKeywords($resource['id']));
        }
        if (empty($resource['hasmetatags'])) {
            return $metas;
        }
        $tags = evo()->getMETATags($resource['id']);
        foreach ($tags as $n => $col) {
            $metas .= ',' . $col['tagvalue'];
        }
        return $metas;
    }

    // ---------------------------------------------------
    // Function: getChildIDs
    // Get the IDs ready to be processed
    // Similar to the modx version by the same name but much faster
    // ---------------------------------------------------

    function getChildIDs($IDs, $depth)
    {
        $depth = (int)$depth;
        $docIDs = [];
        foreach ($IDs as $id) {
            $kids = evo()->getChildIds($id, $depth);
            foreach ($kids as $k => $v) {
                $docIDs[$k] = $v;
            }
        }
        return array_unique($docIDs);
    }

    // ---------------------------------------------------
    // Function: getDocuments
    // Get documents and append TVs + Prefetch Data, and sort
    // ---------------------------------------------------

    function getDocuments($ids = [], $fields=[], $TVs=[], $orderBy=[], $published = 1, $deleted = 0, $publicOnly = 1, $extraWhere = '', $limit = '', $keywords = 0, $randomize = 0, $dateSource = false)
    {
        if (!$ids) {
            return false;
        }
        sort($ids);
        if ($publicOnly) {
            // get document groups for current user
            $docgrp = evo()->getUserDocGroups();
            if (evo()->isFrontend()) {
                $access = sprintf(
                    'sc.privateweb=0 %s',
                    $docgrp ? ' OR dg.document_group IN (' . implode(',', $docgrp) . ')' : ''
                );
            } elseif (sessionv('mgrRole') != 1) {
                $access = sprintf(
                    'sc.privatemgr=0 %s',
                    $docgrp ? ' OR dg.document_group IN (' . implode(',', $docgrp) . ')' : ''
                );
            }
        }

        if ($extraWhere !== '') {
            $_ = explode('AND', $extraWhere);
            foreach ($_ as $i => $v) {
                $_[$i] = trim($v);
            }
            $where = 'AND sc.' . implode(' AND sc.', $_);
        }

        $sort = $randomize ? 'RAND()' : $orderBy['sql'];

        $sql = sprintf(
            "SELECT DISTINCT %s FROM %s sc
                LEFT JOIN %s dg on dg.document = sc.id
                WHERE sc.id IN (%s) %s AND sc.deleted=%d %s
                %s GROUP BY sc.id%s %s",
            'sc.' . implode(',sc.', $fields),
            evo()->getFullTableName('site_content'),
            evo()->getFullTableName('document_groups'),
            implode(',', $ids),
            $published ? 'AND sc.published=1' : '',
            $deleted,
            $where ?? '',
            $publicOnly ? sprintf('AND (%s)', $access) : '',
            $sort ? ' ORDER BY ' . $sort : '',
            ($limit) ? 'LIMIT ' . $limit : ''
        );

        $rs = db()->query($sql);
        if (!db()->count($rs)) {
            return false;
        }
        $docs = [];

        $TVIDs = [];
        while ($row = db()->getRow($rs)) {
            $docid = $row['id'];
            if ($dateSource && !empty($row[$dateSource])) {
                if (!preg_match('@^[1-9][0-9]*$@', $row[$dateSource])) {
                    $row[$dateSource] = strtotime($row[$dateSource]);
                }
                $row[$dateSource] += evo()->config('server_offset_time');
            }
            if ($keywords) {
                $row = $this->appendKeywords($row);
            }

            if ($this->prefetch == true && $this->sortOrder !== false) {
                $row['ditto_sort'] = $this->sortOrder[$docid];
            }

            $TVIDs[] = $docid;
            $x = '#' . $docid;
            $docs[$x] = $row;
            if (!empty($this->prefetch['resource'])) {
                $docs[$x] = array_merge($row, $this->prefetch['resource'][$x]);
                // merge the prefetch array and the normal array
            }
        }

        foreach ($this->fields['display']['tv'] as $tv) {
            $TVs[] = $tv;
        }
        foreach ($this->fields['backend']['tv'] as $tv) {
            $TVs[] = $tv;
        }

        $TVs = array_unique($TVs);
        $TVData = [];
        if ($TVs) {
            foreach ($TVs as $tv) {
                $TVData = $this->array_merge_recursive(
                    $this->appendTV($tv, $TVIDs),
                    $TVData
                );
            }
        }

        $docs = $this->array_merge_recursive($docs, $TVData);
        if ($this->prefetch && $this->sortOrder) {
            uasort($docs, function ($a, $b) {
                return strnatcmp($a['ditto_sort'], $b['ditto_sort']);
            });
        }
        return $docs;
    }

    public function array_merge_recursive($org, $override)
    {
        foreach ($override as $k => $v) {
            if (is_array($v)) {
                $org[$k] = $this->array_merge_recursive($org[$k], $v);
            } else {
                $org[$k] = $v;
            }
        }
        return $org;
    }
    // ---------------------------------------------------
    // Function: getDocumentsLite
    // Get an array of documents
    // ---------------------------------------------------

    function getDocumentsIDs($ids = [], $published = 1)
    {
        if (!$ids) {
            return false;
        }

        $where = [];
        $docGroup = evo()->getUserDocGroups();
        if ($docGroup) {
            $where[] = sprintf('sc.id IN (%s)', implode(',', $ids));
            $where[] = 'AND sc.deleted=0';
            if (evo()->isFrontend()) {
                $where[] = sprintf(
                    'AND (sc.privateweb=0 OR dg.document_group IN (%s))',
                    implode(',', $docGroup)
                );
            } elseif ($_SESSION['mgrRole'] != 1) {
                $where[] = sprintf(
                    'AND (sc.privatemgr=0 OR dg.document_group IN (%s))',
                    implode(',', $docGroup)
                );
            }
            if ($published) {
                $where[] = 'AND sc.published=1';
            }
            $where[] = 'GROUP BY sc.id';
            $rs = db()->select(
                'DISTINCT sc.id',
                '[+prefix+]site_content sc LEFT JOIN [+prefix+]document_groups dg on dg.document=sc.id',
                $where
            );
        } else {
            $where[] = sprintf('id IN (%s)', implode(',', $ids));
            $where[] = 'AND deleted=0';
            if (evo()->isFrontend()) {
                $where[] = 'AND privateweb=0';
            } elseif ($_SESSION['mgrRole'] != 1) {
                $where[] = 'AND privatemgr=0';
            }
            if ($published) {
                $where[] = 'AND published=1';
            }
            $where[] = 'GROUP BY id';
            $rs = db()->select(
                'DISTINCT id',
                '[+prefix+]site_content',
                $where
            );
        }

        $docs = [];
        while ($row = db()->getRow($rs)) {
            $docs[] = $row;
        }
        return $docs;
    }


    // ---------------------------------------------------
    // Function: buildURL
    // Build a URL with regard to Ditto ID
    // ---------------------------------------------------

    static function buildURL($args, $id = false, $dittoIdentifier = false)
    {
        global $dittoID;
        $dittoID = ($dittoIdentifier !== false) ? $dittoIdentifier : $dittoID;
        $query = [];
        foreach ($_GET as $param => $value) {
            if ($param !== 'id' && $param !== 'q') {
                $clean_param = hsc($param, ENT_QUOTES);
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $query[$clean_param][] = hsc($val, ENT_QUOTES);
                    }
                } else $query[$clean_param] = hsc($value, ENT_QUOTES);
            }
        }
        if (!is_array($args)) {
            $args = explode('&', $args);
            foreach ($args as $arg) {
                $arg = explode('=', $arg);
                $query[$dittoID . $arg[0]] = rawurlencode(trim($arg[1]));
            }
        } else {
            foreach ($args as $name => $value) {
                $query[$dittoID . $name] = rawurlencode(trim($value));
            }
        }
        $queryString = '';
        foreach ($query as $param => $value) {
            if (!is_array($value)) {
                if ($param === $dittoID . 'start' && $value == 0) {
                    continue;
                }
                $queryString .= '&' . $param . '=' . $value;
            } else {
                foreach ($value as $val) {
                    $queryString .= '&' . $param . '[]' . $val;
                }
            }
        }
        $cID = ($id !== false) ? $id : evo()->documentObject['id'];
        $url = evo()->makeURL(trim($cID), '', $queryString);
        return evo()->config('xhtml_urls') ? $url : str_replace('&', '&amp;', $url);
    }

    // ---------------------------------------------------
    // Function: getParam
    // Get a parameter or use the default language value
    // ---------------------------------------------------

    function getParam($param)
    {
        // get a parameter value and if it is not set get the default language string value
        global $ditto_lang;

        if (substr($param, 0, 1) === '@') {
            return $this->template->fetch($param);
        }
        if (evo()->hasChunk($param)) {
            return evo()->getChunk($param);
        }

        return event()->param($param);
    }

    // ---------------------------------------------------
    // Function: paginate
    // Paginate the documents
    // ---------------------------------------------------

    function paginate($start, $stop, $total, $summarize, $tplPaginateNext, $tplPaginatePrevious, $tplPaginateNextOff, $tplPaginatePreviousOff, $tplPaginatePage, $tplPaginateCurrentPage, $paginateAlwaysShowLinks, $paginateSplitterCharacter, $maxPaginate = 10)
    {
        global $dittoID, $ditto_lang;

        if ($stop == 0 || $total == 0 || $summarize == 0) {
            return false;
        }
        $next = $start + $summarize;
        if (strpos($tplPaginateNext, 'lang:next') !== false) {
            $tplPaginateNext = str_replace('lang:next', 'lang%next', $tplPaginateNext);
        }
        if (strpos($tplPaginatePrevious, 'lang:previous') !== false) {
            $tplPaginatePrevious = str_replace('lang:previous', 'lang%previous', $tplPaginatePrevious);
        }
        $rNext = evo()->parseText(
            [
                'url' => self::buildURL('start=' . $next),
                'lang%next' => $ditto_lang['next']],
            $tplPaginateNext
        );
        $previous = $start - $summarize;
        if ($previous != 0) {
            $prevUrl = self::buildURL('start=' . $previous);
        } else {
            $args = $_GET;
            if (isset($args[$dittoID . 'start'])) {
                unset($args[$dittoID . 'start']);
            }
            if (is_array($args)) {
                foreach ($args as $k => $v) {
                    $args[$k] = sprintf('%s=%s', $k, $v);
                }
                $args = implode('&', $args);
            }
            $prevUrl = evo()->makeUrl(
                evo()->documentIdentifier, '', $args
            );
        }
        $rPrevious = evo()->parseText(
            [
                'url' => $prevUrl,
                'lang%previous' => $ditto_lang['prev']],
            $tplPaginatePrevious
        );
        $limten = $summarize + $start;
        if ($paginateAlwaysShowLinks == 1) {
            if (strpos($tplPaginatePreviousOff, 'lang:previous') !== false) {
                $tplPaginatePreviousOff = str_replace(
                    'lang:previous',
                    'lang%previous',
                    $tplPaginatePreviousOff
                );
            }
            if (strpos($tplPaginateNextOff, 'lang:next') !== false) {
                $tplPaginateNextOff = str_replace(
                    'lang:next',
                    'lang%next',
                    $tplPaginateNextOff
                );
            }
            $previousplaceholder = evo()->parseText(
                ['lang%previous' => $ditto_lang['prev']],
                $tplPaginatePreviousOff
            );
            $nextplaceholder = evo()->parseText(
                ['lang%next' => $ditto_lang['next']],
                $tplPaginateNextOff
            );
        } else {
            $previousplaceholder = '';
            $nextplaceholder = '';
        }
        $split = '';
        if ($previous > -1 && $next < $total) {
            $split = $paginateSplitterCharacter;
        }
        if ($previous > -1) {
            $previousplaceholder = $rPrevious;
        }
        if ($next < $total) {
            $nextplaceholder = $rNext;
        }
        if ($start < $total) {
            $stop = $limten;
        }
        if ($limten <= $total) {
            $limiter = $limten;
        } else {
            $limiter = $total;
        }
        $totalpages = ceil($total / $summarize);

        $max_paginate = $maxPaginate;
        $max_previous = 5;
        $cur_x = floor($start / $summarize);
        $min_x = $cur_x - $max_previous;

        if ($min_x < 0) {
            $min_x = 0;
        }

        $max_x = $min_x + $max_paginate - 1;
        if ($max_x > $totalpages - 1) {
            $max_x = $totalpages - 1;
            $min_x = $max_x - $max_paginate + 1;
        }
        $pages = [];
        for ($x = 0; $x <= $totalpages - 1; $x++) {
            $inc = $x * $summarize;
            $display = $x + 1;

            if ($x < $min_x || ($x > $max_x)) {
                continue;
            }

            if ($inc == $start) {
                evo()->setPlaceholder($dittoID . 'currentPage', $display);
                $pages[] = evo()->parseText(
                    $tplPaginateCurrentPage,
                    ['page' => $display]
                );
            } else {
                $pages[] = evo()->parseText(
                    $tplPaginatePage,
                    [
                        'url' => self::buildURL('start=' . $inc),
                        'page' => $display
                    ]
                );
            }
            if ($x < $max_x) {
                $pages[] = $paginateSplitterCharacter;
            }
        }
        if ($totalpages > 1 || $paginateAlwaysShowLinks == 1) {
            evo()->toPlaceholders(
                [
                    'next' => $nextplaceholder,
                    'previous' => $previousplaceholder,
                    'prev' => $previousplaceholder,
                    'pages' => implode("\n", $pages)
                ],
                $dittoID
            );
        }
        evo()->toPlaceholders(
            [
                'splitter' => $split,
                'start' => ($start + 1),
                'urlStart' => $start,
                'stop' => $limiter,
                'total' => $total,
                'perPage', $summarize,
                'totalPages' => $totalpages,
                'ditto_pagination_set' => true
            ],
            $dittoID
        );
    }

    // ---------------------------------------------------
    // Function: noResults
    // Render the noResults output
    // ---------------------------------------------------
    function noResults($text, $paginate)
    {
        global $dittoID;
        $set = evo()->getPlaceholder($dittoID . 'ditto_pagination_set');
        if ($paginate && $set !== true) {
            evo()->setPlaceholder($dittoID . 'next', '');
            evo()->setPlaceholder($dittoID . 'previous', '');
            evo()->setPlaceholder($dittoID . 'prev', '');
            evo()->setPlaceholder($dittoID . 'splitter', '');
            evo()->setPlaceholder($dittoID . 'start', 0);
            evo()->setPlaceholder($dittoID . 'urlStart', '#start');
            evo()->setPlaceholder($dittoID . 'stop', 0);
            evo()->setPlaceholder($dittoID . 'total', 0);
            evo()->setPlaceholder($dittoID . 'pages', '');
            evo()->setPlaceholder($dittoID . 'perPage', 0);
            evo()->setPlaceholder($dittoID . 'totalPages', 0);
            evo()->setPlaceholder($dittoID . 'currentPage', 0);
        }
        return $text;
    }

    // ---------------------------------------------------
    // Function: relToAbs
    // Convert relative urls to absolute URLs
    // Based on script from http://wintermute.com.au/bits/2005-09/php-relative-absolute-links/
    // ---------------------------------------------------
    function relToAbs($text, $base)
    {
        return preg_replace(
            '#(href|src)="([^:"]*)"#',
            '$1="' . $base . '$2"',
            $text
        );
    }

    function mb_strftime($format = '', $timestamp = '')
    {
        global $modx;
        if (method_exists($modx, 'mb_strftime')) {
            return evo()->mb_strftime($format, $timestamp);
        }

        $dateTime = new DateTime();
        $dateTime->setTimestamp($timestamp);

        $dateFormat = $format?: evo()->toDateFormat(null, 'formatOnly') . ' %H:%M';

        return $dateTime->format($dateFormat);
    }
}
