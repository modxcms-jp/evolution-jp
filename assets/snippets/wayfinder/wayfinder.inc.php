<?php
/*
::::::::::::::::::::::::::::::::::::::::
 Snippet name: Wayfinder
 Short Desc: builds site navigation
 Version: 2.0.2
 Authors: 
    Kyle Jaebker (muddydogpaws.com)
    Ryan Thrash (vertexworks.com)
 Date: February 27, 2006
::::::::::::::::::::::::::::::::::::::::
*/

class Wayfinder {
    var $_config;
    var $_templates;
    var $_css;
    var $docs = array();
    var $parentTree = array();
    var $hasChildren = array();
    var $placeHolders = array();
    var $tvList = array();
    var $debugInfo = array();
    
    function __construct() {
        $_[] = '[+wf.wrapper+]';
        $_[] = '[+wf.classes+]';
        $_[] = '[+wf.classnames+]';
        $_[] = '[+wf.link+]';
        $_[] = '[+wf.title+]';
        $_[] = '[+wf.linktext+]';
        $_[] = '[+wf.id+]';
        $_[] = '[+wf.alias+]';
        $_[] = '[+wf.attributes+]';
        $_[] = '[+wf.docid+]';
        $_[] = '[+wf.introtext+]';
        $_[] = '[+wf.description+]';
        $_[] = '[+wf.subitemcount+]';
        $this->placeHolders['rowLevel'] = $_;
        $this->placeHolders['wrapperLevel'] = array('[+wf.wrapper+]','[+wf.classes+]','[+wf.classnames+]');
        $this->placeHolders['tvs']          = array();
        
    }
    
    function run() {
        global $modx;
        if ($this->_config['debug']) {
            $this->addDebugInfo('settings','Settings','Settings','Settings used to create this menu.',$this->_config);
            $this->addDebugInfo('settings','CSS','CSS Settings','Available CSS options.',$this->_css);
        }
        //setup here checking array
        $this->parentTree = $modx->getParentIds($this->_config['hereId']);
        $this->parentTree[] = $this->_config['hereId'];
        //Load the templates
        $this->checkTemplates();
        //Register any scripts
        if ($this->_config['cssTpl'] || $this->_config['jsTpl']) {
            $this->regJsCss();
        }
        //Get all of the documents
        $this->docs = $this->getData();
        if (!empty($this->docs)) {
            //Sort documents by level for proper wrapper substitution
            ksort($this->docs);
            //build the menu
            return $this->buildMenu();
        } else {
            $noneReturn = $this->_config['debug'] ? '<p style="color:red">No documents found for menu.</p>' : '';
            return $noneReturn;
        }
    }
    
    function buildMenu() {
        global $modx;
        //Loop through all of the menu levels
        foreach ($this->docs as $level => $subDocs) {
            //Loop through each document group (grouped by parent doc)
            foreach ($subDocs as $parentId => $docs) {
                //only process document group, if starting at root, hidesubmenus is off, or is in current parenttree
                if (!$this->_config['hideSubMenus'] || $this->isHere($parentId) || $level <= 1) {
                    //Build the output for the group of documents
                    $menuPart = $this->buildSubMenu($docs,$level);
                    //If we are at the top of the menu start the output, otherwise replace the wrapper with the submenu
                    if (($level == 1 && (!$this->_config['displayStart'] || $this->_config['id'] == 0)) || ($level == 0 && $this->_config['displayStart'])) {
                        $output = $menuPart;
                    } else {
                        $output = str_replace("[+wf.wrapper.{$parentId}+]",$menuPart,$output);
                    }
                }
            }
        }
        //Return the final Menu
        return $output;
    }

    function buildSubMenu($menuDocs,$level) {
        global $modx;
        $subMenuOutput = '';
        $firstItem = 1;
        $counter = 1;
        $numSubItems = count($menuDocs);
        //Loop through each document to render output
        foreach ($menuDocs as $docId => $docInfo) {
            $docInfo['level'] = $level;
            $docInfo['first'] = $firstItem;
            $firstItem = 0;
            //Determine if last item in group
            if ($counter == $numSubItems && 0 < $numSubItems) {
                $docInfo['last'] = 1;
            } else {
                $docInfo['last'] = 0;
            }
            //Determine if document has children
            $docInfo['hasChildren'] = in_array($docInfo['id'],$this->hasChildren) ? 1 : 0;
            $numChildren = $docInfo['hasChildren'] ? count($this->docs[$level+1][$docInfo['id']]) : 0;
            //Render the row output
            $subMenuOutput .= $this->renderRow($docInfo,$numChildren);
            //Update counter for last check
            $counter++;
        }
        
        if ($level > 0) {
            //Determine which wrapper template to use
            if ($this->_templates['innerTpl'] && $level > 1) {
                $useChunk = $this->_templates['innerTpl'];
                $usedTemplate = 'innerTpl';
            } else {
                $useChunk = $this->_templates['outerTpl'];
                $usedTemplate = 'outerTpl';
            }
            //Determine wrapper class
            if ($level > 1) {
                $wrapperClass = 'innercls';
            } else {
                $wrapperClass = 'outercls';
            }
            //Get the class names for the wrapper
            $classNames = $this->setItemClass($wrapperClass);
            $useClass = ($classNames) ? " class=\"{$classNames}\"" : '';
            
            $phArray = array($subMenuOutput,$useClass,$classNames);
            //Process the wrapper
            $subMenuOutput = str_replace($this->placeHolders['wrapperLevel'],$phArray,$useChunk);
            //Debug
            if ($this->_config['debug']) {
                $debugParent = $docInfo['parent'];
                $debugDocInfo = array();
                $debugDocInfo['template'] = $usedTemplate;
                foreach ($this->placeHolders['wrapperLevel'] as $n => $v) {
                    if ($v !== '[+wf.wrapper+]')
                        $debugDocInfo[$v] = $phArray[$n];
                }
                $this->addDebugInfo('wrapper',$debugParent,"Wrapper for items with parent {$debugParent}.","These fields were used when processing the wrapper for the following documents.",$debugDocInfo);
            }
        }
        //Return the submenu
        return $subMenuOutput;
    }
    
    //render each rows output
    function renderRow(&$resource,$numChildren) {
        global $modx;
        $output = '';
        //Determine which template to use
        if ($this->_config['displayStart'] && $resource['level'] == 0) {
            $usedTemplate = 'startItemTpl';
        } elseif ($resource['id'] == $modx->documentIdentifier
            && $resource['isfolder']
            && $this->_templates['parentRowHereTpl']
            && ($resource['level'] < $this->_config['level'] || $this->_config['level'] == 0)
            && $numChildren) {
            $usedTemplate = 'parentRowHereTpl';
        } elseif ($resource['id'] == $modx->documentIdentifier && $this->_templates['innerHereTpl'] && $resource['level'] > 1) {
            $usedTemplate = 'innerHereTpl';
        } elseif ($resource['id'] == $modx->documentIdentifier && $this->_templates['hereTpl']) {
            $usedTemplate = 'hereTpl';
        } elseif ($resource['isfolder']
            && $this->_templates['activeParentRowTpl']
            && ($resource['level'] < $this->_config['level'] || $this->_config['level'] == 0)
            && $this->isHere($resource['id'])) {
            $usedTemplate = 'activeParentRowTpl';
        } elseif ($resource['isfolder']
            && ($resource['template']=="0" || is_numeric(strpos($resource['link_attributes'],'rel="category"')))
            && $this->_templates['categoryFoldersTpl']
            && ($resource['level'] < $this->_config['level'] || $this->_config['level'] == 0)) {
            $usedTemplate = 'categoryFoldersTpl';
        } elseif ($resource['isfolder']
            && $this->_templates['parentRowTpl']
            && ($resource['level'] < $this->_config['level'] || $this->_config['level'] == 0)
            && $numChildren) {
            $usedTemplate = 'parentRowTpl';
        } elseif ($resource['level'] > 1 && $this->_templates['innerRowTpl']) {
            $usedTemplate = 'innerRowTpl';
        } elseif ($resource['last'] && $this->_templates['rowTplLast']) {
            $usedTemplate = 'rowTplLast'; // By tonatos
        } else {
            $usedTemplate = 'rowTpl';
        }
        //Get the template
        $useChunk = $this->_templates[$usedTemplate];
        //Setup the new wrapper name and get the class names
        $useSub = $resource['hasChildren'] ? "[+wf.wrapper.{$resource['id']}+]" : "";
        $classNames = $this->setItemClass('rowcls',$resource['id'],$resource['first'],$resource['last'],$resource['level'],$resource['isfolder'],$resource['type']);
        $useClass = ($classNames) ? $useClass = ' class="' . $classNames . '"' : '';
        
        //Setup the row id if a prefix is specified
        if ($this->_config['rowIdPrefix']) {
            $useId = ' id="' . $this->_config['rowIdPrefix'] . $resource['id'] . '"';
        } else {
            $useId = '';
        }
        //Load row values into placholder array
        $charset = $modx->config['modx_charset'];
        if ($this->_config['entityEncode']) {
            $phArray = array($useSub,$useClass,$classNames,$resource['link'],htmlspecialchars($resource['title'], ENT_COMPAT, $charset),htmlspecialchars($resource['linktext'], ENT_COMPAT, $charset),$useId,$resource['alias'],$resource['link_attributes'],$resource['id'],htmlspecialchars($resource['introtext'], ENT_COMPAT, $charset),htmlspecialchars($resource['description'], ENT_COMPAT, $charset),$numChildren);
        } else {
            $phArray = array($useSub,$useClass,$classNames,$resource['link'],$resource['title'],$resource['linktext'],$useId,$resource['alias'],$resource['link_attributes'],$resource['id'],$resource['introtext'],$resource['description'],$numChildren);
        }
        //Add document variables to the placeholder array
        foreach ($resource as $dvName => $dvVal) {
            $this->placeHolders['rowLevel'][] = "[+{$dvName}+]";
            $phArray[] = $dvVal;
        }
        //If tvs are used add them to the placeholder array
        if (!empty($this->tvList)) {
            $usePlaceholders = array_merge($this->placeHolders['rowLevel'],$this->placeHolders['tvs']);
            foreach ($this->tvList as $tvName) {
                $phArray[] = $resource[$tvName];
            }
        } else {
            $usePlaceholders = $this->placeHolders['rowLevel'];
        }
        //Debug
        if ($this->_config['debug']) {
            $debugDocInfo = array();
            $debugDocInfo['template'] = $usedTemplate;
            foreach ($usePlaceholders as $n => $v) {
                $debugDocInfo[$v] = $phArray[$n];
            }
            $this->addDebugInfo("row","{$resource['parent']}:{$resource['id']}","Doc: #{$resource['id']}","The following fields were used when processing this document.",$debugDocInfo);
            $this->addDebugInfo("rowdata","{$resource['parent']}:{$resource['id']}","Doc: #{$resource['id']}","The following fields were retrieved from the database for this document.",$resource);
        }
        //Process the row
        $output .= str_replace($usePlaceholders,$phArray,$useChunk);
        //Return the row
        return $output . $this->_config['nl'];
    }
    
    //determine style class for current item being processed
    function setItemClass($classType, $docId = 0, $first = 0, $last = 0, $level = 0, $isFolder = 0, $type = 'document') {
        global $modx;
        $returnClass = '';
        $hasClass = 0;

        if ($classType === 'outercls' && !empty($this->_css['outer'])) {
            //Set outer class if specified
            $returnClass .= $this->_css['outer'];
            $hasClass = 1;
        } elseif ($classType === 'innercls' && !empty($this->_css['inner'])) {
            //Set inner class if specified
            $returnClass .= $this->_css['inner'];
            $hasClass = 1;
        } elseif ($classType === 'rowcls') {
            //Set row class if specified
            if (!empty($this->_css['row'])) {
                $returnClass .= $this->_css['row'];
                $hasClass = 1;
            }
            //Set first class if specified
            if ($first && !empty($this->_css['first'])) {
                $returnClass .= $hasClass ? ' ' . $this->_css['first'] : $this->_css['first'];
                $hasClass = 1;
            }
            //Set last class if specified
            if ($last && !empty($this->_css['last'])) {
                $returnClass .= $hasClass ? ' ' . $this->_css['last'] : $this->_css['last'];
                $hasClass = 1;
            }
            //Set level class if specified
            if (!empty($this->_css['level'])) {
                $returnClass .= $hasClass ? ' ' . $this->_css['level'] . $level : $this->_css['level'] . $level;
                $hasClass = 1;
            }
            //Set parentFolder class if specified
            if ($isFolder && !empty($this->_css['parent']) && ($level < $this->_config['level'] || $this->_config['level'] == 0)) {
                $returnClass .= $hasClass ? ' ' . $this->_css['parent'] : $this->_css['parent'];
                $hasClass = 1;
            }
            //Set here class if specified
            if (!empty($this->_css['here']) && $this->isHere($docId)) {
                $returnClass .= $hasClass ? ' ' . $this->_css['here'] : $this->_css['here'];
                $hasClass = 1;
            }
            //Set self class if specified
            if (!empty($this->_css['self']) && $docId == $this->_config['hereId']) {
                $returnClass .= $hasClass ? ' ' . $this->_css['self'] : $this->_css['self'];
                $hasClass = 1;
            }
            //Set class for weblink
            if (!empty($this->_css['weblink']) && $type == 'reference') {
                $returnClass .= $hasClass ? ' ' . $this->_css['weblink'] : $this->_css['weblink'];
                $hasClass = 1;
            }
        }

        return $returnClass;
    }

    //determine "you are here"
    function isHere($did) {
        return in_array($did,$this->parentTree);
    }

    //Add the specified css & javascript chunks to the page
    function regJsCss() {
        global $modx;
        //Debug
        if ($this->_config['debug']) {
            $jsCssDebug = array('js' => 'None Specified.', 'css' => 'None Specified.');
        }
        //Check and load the CSS
        if ($this->_config['cssTpl']) {
            $cssChunk = $this->fetch($this->_config['cssTpl']);
            if ($cssChunk) {
                $modx->regClientCSS($cssChunk);
                if ($this->_config['debug']) {$jsCssDebug['css'] = "The CSS in {$this->_config['cssTpl']} was registered.";}
            } else {
                if ($this->_config['debug']) {$jsCssDebug['css'] = "The CSS in {$this->_config['cssTpl']} was not found.";}
            }
        }
        //Check and load the Javascript
        if ($this->_config['jsTpl']) {
            $jsChunk = $this->fetch($this->_config['jsTpl']);
            if ($jsChunk) {
                $modx->regClientStartupScript($jsChunk);
                if ($this->_config['debug']) {$jsCssDebug['js'] = "The Javascript in {$this->_config['jsTpl']} was registered.";}
            } else {
                if ($this->_config['debug']) {$jsCssDebug['js'] = "The Javascript in {$this->_config['jsTpl']} was not found.";}
            }
        }
        //Debug
        if ($this->_config['debug']) {$this->addDebugInfo("settings","JSCSS","JS/CSS Includes","Results of CSS & Javascript includes.",$jsCssDebug);}
    }

    //Get all of the documents from the database
    function getData() {
        global $modx;
        $depth = !empty($this->_config['level']) ? $this->_config['level'] : 10;
        $ids = array();
        
        if(strtolower(substr($this->_config['id'],0,1))==='i')
            $this->_config['id'] = $this->getIndexID($modx->documentIdentifier);
        if(strtolower(substr($this->_config['id'],0,1))==='p')
            $this->_config['id'] = $this->getParentID($modx->documentIdentifier);
        
        if (!$this->_config['hideSubMenus']) {
            $ids = $modx->getChildIds($this->_config['id'],$depth);
        } else { // then hideSubMenus is checked, we don`t need all children
            // first we always included the chilren of startId document
            // this fix problem with site root chidrens,
            // because site root not included in $modx->getParentIds
            $ids = $modx->getChildIds($this->_config['id'], 1, $ids);

            $parents = array($this->_config['hereId']);
            $parents += $modx->getParentIds($this->_config['hereId']);

            // if startId not in parents, only show children of startId
            if ($this->_config['id'] == 0 || in_array($this->_config['id'], $parents)){

                //remove parents higher than startId(including startId)
                $startId_parents = array($this->_config['id']);
                $startId_parents += $modx->getParentIds($this->_config['id']);
                $parents = array_diff($parents, $startId_parents);

                //remove parents lower than level of startId + level depth
                $parents = array_slice(array_reverse($parents), 0, $depth-1);

                foreach($parents as $p)
                    $ids = $modx->getChildIds($p, 1, $ids);
            }
        }
        //Get all of the ids for processing
        if ($this->_config['displayStart'] && $this->_config['id'] !== 0) {
            $ids[] = $this->_config['id'];
        }
        if (!empty($ids)) {
            //Setup the fields for the query
            $fields = "DISTINCT sc.id, sc.menutitle, sc.pagetitle, sc.introtext, sc.menuindex, sc.published, sc.hidemenu, sc.parent, sc.isfolder, sc.description, IF(sc.alias='', sc.id, sc.alias) AS alias, sc.longtitle, sc.type,if(sc.type='reference',sc.content,'') as content, sc.template, sc.link_attributes";
            //Get the table names
            $tbl_site_content = $modx->getFullTableName('site_content');
            $tbl_document_groups = $modx->getFullTableName('document_groups');
            //Add the ignore hidden option to the where clause
            if ($this->_config['ignoreHidden']) {
                $menuWhere = '';
            } else {
                $menuWhere = ' AND sc.hidemenu=0';
            }
            //add the include docs to the where clause
            if ($this->_config['includeDocs']) {
                $menuWhere .= " AND sc.id IN ({$this->_config['includeDocs']})";
            }
            //add the exclude docs to the where clause
            if ($this->_config['excludeDocs']) {
                $menuWhere .= " AND (sc.id NOT IN ({$this->_config['excludeDocs']}))";
            }
            //add custom where conditions
            if (!empty($this->_config['where'])) {
                $menuWhere .= " AND ({$this->_config['where']})";
            }
            //add the limit to the query
            if ($this->_config['limit']) {
                $sqlLimit = "0, {$this->_config['limit']}";
            } else {
                $sqlLimit = '';
            }
            //Determine sorting
            if (strtolower($this->_config['sortBy']) == 'random') {
                $sort = 'rand()';
                $dir = '';
            } else {
                // modify field names to use sc. table reference
                $sort = 'sc.'.implode(',sc.',array_filter(array_map('trim', explode(',', $this->_config['sortBy']))));
            }

            // get document groups for current user
            if($docgrp = $modx->getUserDocGroups()) $docgrp = implode(",",$docgrp);
            // build query
            $access_privateweb = '';
            if($modx->isFrontend()) {
                if(!$this->_config['showPrivate']) {
                    $access_privateweb = "sc.privateweb=0";
                }
            } else {
                $access_privateweb = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0";
            }
            
            $access = array();
            if($access_privateweb && $docgrp) {
                $access[] = "({$access_privateweb} OR dg.document_group IN ({$docgrp}))";
            } elseif($access_privateweb) {
                $access[] = $access_privateweb;
            } elseif($docgrp) {
                $access[] = "(sc.privateweb=0 OR dg.document_group IN ({$docgrp}))";
            }
            if(!empty($access)) $access = 'AND (' . join(' AND ', $access) . ')';
            else $access = '';
            
             $groupby = 'GROUP BY sc.id';
             
            //run the query
            $joind_ids = implode(',',$ids);
            $from = "{$tbl_site_content} sc LEFT JOIN {$tbl_document_groups} dg ON dg.document = sc.id";
            $where = "sc.published=1 AND sc.deleted=0 {$access} {$menuWhere} AND sc.id IN ({$joind_ids}) {$groupby}";
            $orderby = "{$sort} {$this->_config['sortOrder']}";
            $result = $modx->db->select($fields,$from,$where,$orderby,$sqlLimit);
            $resourceArray = array();
            $level = 1;
            $prevParent = -1;
            //Setup startlevel for determining each items level
            if ($this->_config['id'] == 0) {
                $startLevel = 0;
            } else {
                $startLevel = count($modx->getParentIds($this->_config['id']));
                $startLevel = $startLevel ? $startLevel+1 : 1;
            }
            $resultIds = array();
            //loop through the results
            while($tempDocInfo = $modx->db->getRow($result)) {
                $resultIds[] = $tempDocInfo['id'];
                //Create the link
                $linkScheme = $this->_config['fullLink'] ? 'full' : '';
                if ($this->_config['useWeblinkUrl'] !== 'FALSE' && $tempDocInfo['type'] == 'reference') {
                    if (is_numeric($tempDocInfo['content'])) {
                        $tempDocInfo['link'] = $modx->makeUrl(intval($tempDocInfo['content']),'','',$linkScheme);
                    } else {
                        $tempDocInfo['link'] = $tempDocInfo['content'];
                    }
                } elseif ($tempDocInfo['id'] == $modx->config['site_start']) {
                    $tempDocInfo['link'] = $modx->config['site_url'];
                } else {
                    $tempDocInfo['link'] = $modx->makeUrl($tempDocInfo['id'],'','',$linkScheme);
                }
                //determine the level, if parent has changed
                if ($prevParent !== $tempDocInfo['parent']) {
                    $level = count($modx->getParentIds($tempDocInfo['id'])) + 1 - $startLevel;
                }
                //add parent to hasChildren array for later processing
                if (($level > 1 || $this->_config['displayStart']) && !in_array($tempDocInfo['parent'],$this->hasChildren)) {
                    $this->hasChildren[] = $tempDocInfo['parent'];
                }
                //set the level
                $tempDocInfo['level'] = $level;
                $prevParent = $tempDocInfo['parent'];
                //determine other output options
                $useTextField = '';
                if(strpos($this->_config['textOfLinks'],',')!==false)
                {
                    $_ = explode(',', $this->_config['textOfLinks']);
                    foreach($_ as $v)
                    {
                        $v = trim($v);
                        if(!empty($tempDocInfo[$v]))
                        {
                            $useTextField = $v;
                            break;
                        }
                    }
                    if(empty($useTextField)) $useTextField = 'pagetitle';
                }
                elseif(!empty($tempDocInfo[$this->_config['textOfLinks']]))
                {
                    $useTextField = $this->_config['textOfLinks'];
                }
                else $useTextField = 'pagetitle';
                
                $tempDocInfo['linktext'] = $tempDocInfo[$useTextField];
                
                $useTitleField = '';
                if(strpos($this->_config['titleOfLinks'],',')!==false)
                {
                    $_ = explode(',', $this->_config['titleOfLinks']);
                    foreach($_ as $v)
                    {
                        $v = trim($v);
                        if(!empty($tempDocInfo[$v]))
                        {
                            $useTitleField = $v;
                            break;
                        }
                    }
                }
                else $useTitleField = $this->_config['titleOfLinks'];
                
                $tempDocInfo['title'] = $tempDocInfo[$useTitleField];
                
                //If tvs were specified keep array flat otherwise array becomes level->parent->doc
                if (!empty($this->tvList)) {
                    $tempResults[] = $tempDocInfo;
                } else {
                    $resourceArray[$tempDocInfo['level']][$tempDocInfo['parent']][] = $tempDocInfo;
                }
            }
            //Process the tvs
            if (!empty($this->tvList) && !empty($resultIds)) {
                $tvValues = array();
                //loop through all tvs and get their values for each document
                foreach ($this->tvList as $tvName) {
                    $tvValues = array_merge_recursive($this->appendTV($tvName,$resultIds),$tvValues);
                }
                //loop through the document array and add the tvar values to each document
                foreach ($tempResults as $tempDocInfo) {
                    if (array_key_exists("#{$tempDocInfo['id']}",$tvValues)) {
                        foreach ($tvValues["#{$tempDocInfo['id']}"] as $tvName => $tvValue) {
                            $tempDocInfo[$tvName] = $tvValue;
                        }
                    }
                    $resourceArray[$tempDocInfo['level']][$tempDocInfo['parent']][] = $tempDocInfo;
                }
            }
        }
        //return final docs
        return $resourceArray;
    }

    // ---------------------------------------------------
    // Function: appendTV taken from Ditto (thanks Mark)
    // Apeend a TV to the documents array
    // ---------------------------------------------------

    function appendTV($tvname,$docIDs){
        global $modx;
        
        $resourceArray = array();
        foreach($docIDs as $id) {
            $tv = $modx->getTemplateVarOutput($tvname, $id);
            $resourceArray["#{$id}"][$tvname] = $tv[$tvname];
        }
        return $resourceArray;
    }
    
    // ---------------------------------------------------
    // Get a list of all available TVs
    // ---------------------------------------------------

    function getTVList() {
        global $modx;
        $tvs = $modx->db->select("name", $modx->getFullTableName('site_tmplvars'));
            // TODO: make it so that it only pulls those that apply to the current template
        $dbfields = $modx->db->getColumn('name', $tvs); 
        return $dbfields;
    }

    //debugging to check for valid chunks
    function checkTemplates() {
        global $modx;
        $nonWayfinderFields = array();

        foreach ($this->_templates as $n => $v) {
            $templateCheck = $this->fetch($v);
            if (empty($v) || !$templateCheck) {
                if ($n === 'outerTpl') {
                    $this->_templates['outerTpl'] = '<ul[+wf.classes+]>[+wf.wrapper+]</ul>';
                } elseif ($n === 'rowTpl') {
                    $this->_templates['rowTpl'] = '<li[+wf.id+][+wf.classes+]><a href="[+wf.link+]" title="[+wf.title+]" [+wf.attributes+]>[+wf.linktext+]</a>[+wf.wrapper+]</li>';
                } elseif ($n === 'startItemTpl') {
                    $this->_templates['startItemTpl'] = '<h2[+wf.id+][+wf.classes+]>[+wf.linktext+]</h2>[+wf.wrapper+]';
                } else {
                    $this->_templates[$n] = FALSE;
                }
                if ($this->_config['debug']) { $this->addDebugInfo('template',$n,$n,"No template found, using default.",array($n => $this->_templates[$n])); }
            } else {
                $this->_templates[$n] = $templateCheck;
                $check = $this->findTemplateVars($templateCheck);
                if (is_array($check)) {
                    $nonWayfinderFields = array_merge($check, $nonWayfinderFields);
                }
                if ($this->_config['debug']) { $this->addDebugInfo('template',$n,$n,"Template Found.",array($n => $this->_templates[$n])); }
            }
        }

        if (!empty($nonWayfinderFields)) {
            $nonWayfinderFields = array_unique($nonWayfinderFields);
            $allTvars = $this->getTVList();

            foreach ($nonWayfinderFields as $field) {
                if (in_array($field, $allTvars)) {
                    $this->placeHolders['tvs'][] = "[+{$field}+]";
                    $this->tvList[] = $field;
                }
            }
            if ($this->_config['debug']) { $this->addDebugInfo('tvars','tvs','Template Variables',"The following template variables were found in your templates.",$this->tvList); }
        }
    }

    function fetch($tpl){
        // based on version by Doze at http://forums.modx.com/thread/41066/support-comments-for-ditto?page=2#dis-post-237942
        global $modx;
        $template = '';
        if(substr($tpl, 0, 5) == '@FILE') {
            $template = file_get_contents(substr($tpl, 6));
        } elseif (substr($tpl, 0, 5) == '@CODE') {
            $template = substr($tpl, 6);
        } elseif ($modx->getChunk($tpl) != '') {
            $template = $modx->getChunk($tpl);
        } else {
            $template = FALSE;
        }
        return $template;
    }


    function findTemplateVars($tpl) {
        preg_match_all('~\[\+([^:]*?)(:|\+\])~', $tpl, $matches);
        $tvnames = array();
        foreach($matches[1] as $tv) {
            if (strpos(strtolower($tv), 'phx')===0) continue;
            if (strpos(strtolower($tv), 'wf.')===0) continue;
            $tvnames[] = $tv;
        }

        if (count($tvnames) >= 1) {
            return array_unique($tvnames);
        } else {
            return false;
        }
    }

    function addDebugInfo($group,$groupkey,$header,$message,$info) {
        $infoString = '<table border="1" cellpadding="3px">';
        $numInfo = count($info);
        $count = 0;

        foreach ($info as $key => $value) {
            $key = $this->modxPrep($key);
            if ($value === TRUE || $value === FALSE) {
                $value = $value ? 'TRUE' : 'FALSE';
            } else {
                $value = $this->modxPrep($value);
            }
            if ($count == 2) { $infoString .= '</tr>'; $count = 0; }
            if ($count == 0) { $infoString .= '<tr>'; }
            $value = empty($value) ? '&nbsp;' : $value;
            $infoString .= "<td><strong>{$key}</strong></td><td>{$value}</td>";
            $count++;
        }
        $infoString .= '</tr></table>';

        $this->debugInfo[$group][$groupkey] = array(
            'header' => $this->modxPrep($header),
            'message' => $this->modxPrep($message),
            'info' => $infoString,
        );
    }

    function renderDebugOutput() {
        $output = '<table border="1" cellpadding="3px" width="100%">';
        foreach ($this->debugInfo as $group => $item) {
            switch ($group) {
                case 'template':
                    $output .= "<tr><th style=\"background:#C3D9FF;font-size:200%;\">Template Processing</th></tr>";
                    foreach ($item as $parentId => $info) {
                        $output .= "
                            <tr style=\"background:#336699;color:#fff;\"><th>{$info['header']} - <span style=\"font-weight:normal;\">{$info['message']}</span></th></tr>
                            <tr><td>{$info['info']}</td></tr>";
                    }
                    break;
                case 'wrapper':
                    $output .= "<tr><th style=\"background:#C3D9FF;font-size:200%;\">Document Processing</th></tr>";

                    foreach ($item as $parentId => $info) {
                        $output .= "<tr><table border=\"1\" cellpadding=\"3px\" style=\"margin-bottom: 10px;\" width=\"100%\">
                                    <tr style=\"background:#336699;color:#fff;\"><th>{$info['header']} - <span style=\"font-weight:normal;\">{$info['message']}</span></th></tr>
                                    <tr><td>{$info['info']}</td></tr>
                                    <tr style=\"background:#336699;color:#fff;\"><th>Documents included in this wrapper:</th></tr>";

                        foreach ($this->debugInfo['row'] as $key => $value) {
                            $keyParts = explode(':',$key);
                            if ($parentId == $keyParts[0]) {
                                $output .= "<tr style=\"background:#eee;\"><th>{$value['header']}</th></tr>
                                    <tr><td><div style=\"float:left;margin-right:1%;\">{$value['message']}<br />{$value['info']}</div><div style=\"float:left;\">{$this->debugInfo['rowdata'][$key]['message']}<br />{$this->debugInfo['rowdata'][$key]['info']}</div></td></tr>";
                            }
                        }

                        $output .= '</table></tr>';
                    }

                    break;
                case 'settings':
                    $output .= "<tr><th style=\"background:#C3D9FF;font-size:200%;\">Settings</th></tr>";
                    foreach ($item as $parentId => $info) {
                        $output .= "
                            <tr style=\"background:#336699;color:#fff;\"><th>{$info['header']} - <span style=\"font-weight:normal;\">{$info['message']}</span></th></tr>
                            <tr><td>{$info['info']}</td></tr>";
                    }
                    break;
                default:

                    break;
            }
        }
        $output .= '</table>';
        return $output;
    }

    function modxPrep($value) {
        global $modx;
        $value = (strpos($value,'<') !== FALSE) ? htmlentities($value,ENT_NOQUOTES,$modx->config['modx_charset']) : $value;
        $s = array('[', ']', '{', '}');
        $r = array('&#091;', '&#093;', '&#123;', '&#125;');
        $value = str_replace($s, $r, $value);
        return $value;
    }
    
    function getIndexID($id)
    {
        global $modx;
        if($modx->documentObject['parent']==0)   return $id;
        $where = "parent='{$id}' AND hidemenu=0";
        $rs = $modx->db->select('*','[+prefix+]site_content',$where);
        $total = $modx->db->getRecordCount($rs);
        if(0<$total) return $id;
        else return $modx->documentObject['parent'];
    }
    function getParentID($id)
    {
        global $modx;
        if($modx->documentObject['parent']==0)   return $id;
        return $modx->documentObject['parent'];
    }
}
