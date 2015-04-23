<?php

class DocManagerBackend {
	var $dm = null;
	var $modx = null;

    function DocManagerBackend(&$dm, &$modx) {
    	$this->dm = &$dm;
    	$this->modx = &$modx;
    }
    
    function handlePostback() {
    	switch($_POST['tabAction']) {
    		case 'changeTemplate':
    			echo $this->changeTemplate($_POST['pids'], $_POST['newvalue']);
    			break;
    		case 'changeTV':
    			echo $this->changeTemplateVariables($_POST['pids']);
    			break;
    		case 'pushDocGroup':
    		case 'pullDocGroup':
    			echo $this->changeDocGroups($_POST['pids'], $_POST['newvalue'], $_POST['tabAction']);
    			break;
    		case 'changeOther':
    			echo $this->changeOther($_POST['pids']);
    			break;
    		case 'sortMenu':
    			echo $this->showSortList($_POST['new_parent']);
    			break;
    		case 'sortList':
    			echo $this->changeSort($_POST['list']);
    			break;
			default:
				echo 'No tab action defined';
    	}
    }
    
    function showSortList($id) {
        $this->dm->ph['sort.disable_tree_select'] = 'false';
    	$this->dm->ph['sort.options'] = '';
    	$this->dm->ph['sort.save'] = '';
    	$resource = array();

    	if (is_numeric($id)) {
			$query = 'SELECT id , pagetitle , parent , menuindex, published, hidemenu, deleted  FROM '. $this->modx->getFullTableName('site_content') .' WHERE parent=' . $id . ' ORDER BY menuindex ASC';
			if (!$rs = $this->modx->db->query($query)) {
				return false;
			}
		
			while ($row = $this->modx->db->getRow($rs)) {
				$resource[] = $row;
			}
		} elseif ($id == '') {
			$noId = true;
			$this->dm->ph['sort.disable_tree_select'] = 'true';
			$this->dm->ph['sort.save'] = 'none';
			$this->dm->ph['sort.message'] =  $this->dm->lang['DM_sort_noid'];
		}

		if (!$noId) {
			$cnt = count($resource);
			if ($cnt < 1) {
			    $this->dm->ph['sort.disable_tree_select'] = 'true';
				$this->dm->ph['sort.save'] = 'none';
				$this->dm->ph['sort.message'] =  $this->dm->lang['DM_sort_nochildren'];
			} else {
				foreach ($resource as $item) {
                    // Add classes to determine whether it's published, deleted, not in the menu
                    // or has children.
                    // Use class names which match the classes in the document tree
                    $classes = '';
                    $classes .= ($item['hidemenu']) ? ' notInMenuNode ' : ' inMenuNode' ;
                    $classes .= ($item['published']) ? ' publishedNode ' : ' unpublishedNode ' ;
                    $classes = ($item['deleted']) ? ' deletedNode ' : $classes ;
                    $classes .= (count($this->modx->getChildIds($item['id'], 1)) > 0) ? ' hasChildren ' : ' noChildren ';
                    $this->dm->ph['sort.options'] .= '<li id="item_' . $item['id'] . '" class="sort '.$classes.'">' . $item['pagetitle'] . '</li>';
				}
			}
		}
		return $this->dm->parseTemplate('sort_list.tpl', $this->dm->ph);
    }
    
    function changeSort($items) {
    	if (strlen($items) > 0) {
    		$items = explode(';', $items);
    		foreach ($items as $key => $value) {
    			$key++;
    			$id = ltrim($value, 'item_');
    			if (is_numeric($id) && is_numeric($key) ) {
	    			$sql = 'UPDATE '.$this->modx->getFullTableName('site_content') ." set menuindex={$key} WHERE id={$id}";
					$this->modx->db->query($sql);
    			}
    		}
    		$this->logDocumentChange('sortmenu');
    		
			$this->modx->clearCache();
    	}
    	$this->dm->ph['sort.message'] = $this->dm->lang['DM_sort_updated'];
    	$this->dm->ph['sort.save'] = 'none';
    	$this->dm->ph['sort.disable_tree_select'] = 'true';
 		return $this->dm->parseTemplate('sort_list.tpl', $this->dm->ph);
    }
    
    function changeTemplate($pids, $template) {	
		$results = $this->processRange($pids, 'id', 1);
		$pids = $results[0];
		$error = $results[1];

		if ($pids !== '' && $template !== '') {	
			$values = rtrim($pids, ' OR ');
			$fields = array (
				'template' => intval($template
			));
			$this->modx->db->update($fields, $this->modx->getFullTableName('site_content'), $values);
		} else {
			$error .= '<br />' . $this->dm->lang['DM_process_noselection'] . '<br />';
		}

		if ($error == '') {
			$this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_success'];
		} else {
			$this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
		}
		$this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_tpl_results_message'];
										
		$this->modx->clearCache();
		$this->logDocumentChange('template');
		return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
	}
	
	function changeTemplateVariables($pids) {
		$tbl_site_tmplvar_contentvalues = $this->modx->getFullTableName('site_tmplvar_contentvalues');
		$updateError = '';
	
		/*
		$ignoreList = array();
		if (trim($_POST['ignoreTV']) <> '') {
			$ignoreList = explode(',', $_POST['ignoreTV']);
			foreach ($ignoreList as $key => $value) {
				$ignoreList[$key] = trim($value);
			}
		}
		 */
	
		$results = $this->processRange($pids, 'id', 0);
		$pids = $results[0];
		$error = $results[1];

		if (count($pids) > 0) {
			$tmplVars = array ();
			foreach ($_POST as $key => $value) {
				if (substr($key, 0, 10) == 'update_tv_' && $value == 'yes') {
					//echo $key;
					$tvKeyName = substr($key, 10);
					//if (strpos($key,'_prefix') !== false)
					//	continue;
					
					$typeSQL = $this->modx->db->select('*', $this->modx->getFullTableName('site_tmplvars'), "id={$tvKeyName}");
					$row = $this->modx->db->getRow($typeSQL);
					if ($row['type'] == 'url') {
						$tmplvar = $_POST["tv" . $row['id']];
						if ($_POST["tv" . $row['id'] . '_prefix'] != '--') {
							$tmplvar = str_replace(array (
								"ftp://",
								"http://"
							), "", $tmplvar);
							$tmplvar = $_POST["tv" . $row['id'] . '_prefix'] . $tmplvar;
						}
					} elseif ($row['type'] == 'file') {
							$tmplvar = $_POST["tv" . $row['id']];
					} else {
						if (is_array($_POST["tv" . $tvKeyName])) {
							$feature_insert = array();
							$lst = $_POST["tv".$row['id']];
							while (list($featureValue, $feature_item) = each ($lst)) {
								$feature_insert[count($feature_insert)] = $feature_item;
							}
							$tmplvar = implode("||",$feature_insert);
         				} else {
  	  	    				$tmplvar = $_POST["tv".$row['id']];
         				}
					}
					$tmplVars["{$tvKeyName}"] = $tmplvar;
				}
			}
		
			foreach ($pids as $docID)
			{
				$tempSQL = $this->modx->db->select('template', $this->modx->getFullTableName('site_content'), "id={$docID}");
				if ($this->modx->db->getRecordCount($tempSQL) > 0)
				{
					$row = $this->modx->db->getRow($tempSQL);
					if ($row['template'] == $_POST['template_id'])
					{
						$tvID = $this->getTemplateVarIds($tmplVars,$docID);
						if (count($tvID) > 0)
						{
							foreach ($tvID as $tvIndex => $tvValue)
							{
								if($_POST['update_tv_' . $tvIndex] == 'yes')
								{
									$checkSQL = $this->modx->db->select('value',$tbl_site_tmplvar_contentvalues,"contentid='{$docID}' AND tmplvarid='{$tvValue}'");
									$checkCount = $this->modx->db->getRecordCount($checkSQL);
									if ($checkCount)
									{
										$checkRow = $this->modx->db->getRow($checkSQL);
										if ($checkRow['value'] == $tmplVars["$tvIndex"])
										{
											$noUpdate = true;
										}
										elseif (trim($tmplVars["$tvIndex"]) == '')
										{
											$this->modx->db->delete($tbl_site_tmplvar_contentvalues, "contentid='{$docID}' AND tmplvarid='{$tvValue}'");
											$noUpdate = true;
										}
									}
									
									if ($checkCount > 0 && !isset ($noUpdate))
									{
										$fields = array (
										'value' => $this->modx->db->escape($tmplVars["$tvIndex"])
										);
										$this->modx->db->update($fields, $tbl_site_tmplvar_contentvalues, "contentid='{$docID}' AND tmplvarid='{$tvValue}'");
										$updated = true;
									}
									elseif (!isset ($noUpdate) && ltrim($tmplVars["$tvIndex"]) !== '')
									{
										$fields = array (
											'value' => $this->modx->db->escape($tmplVars["$tvIndex"]),
											'contentid' => $this->modx->db->escape($docID),
											'tmplvarid' => $this->modx->db->escape($tvValue)
										);
										$this->modx->db->insert($fields, $tbl_site_tmplvar_contentvalues);
										$updated = true;
									}
								}
							unset($noUpdate);
							}
						}
					}
					else
					{
						$updateError .= "ID: {$docID} " . $this->dm->lang['DM_tv_template_mismatch'] . '<br />';
					}
				}
				else
				{
					if ($docID !== '0')
					{
						$updateError .= "ID: {$docID} " . $this->dm->lang['DM_tv_doc_not_found'] . '<br />';
					}
				}
			}
		} else {
			$updateError .= $this->dm->lang['DM_tv_no_docs'] . '<br />';
		}
	
		if ($updated) {
			$this->logDocumentChange('templatevariables');
		}
	
		if ($error == '' && $updateError == '') {
			$this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_success'];
		} else {
			$this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
		}
	
		if ($updateError <> '') {
			$this->dm->ph['update.message'] .= '<br />' . $updateError;
		}
		$this->dm->ph['update.message'] .= '<br />'. $this->dm->lang['DM_tpl_results_message'];
	
		$this->modx->clearCache();
		return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
	}
	
	function changeDocGroups($pids, $docgroup, $action) {
		$tbl_document_groups = $this->modx->getFullTableName('document_groups');
		$doc_id = array ();
		$this->dm->ph['update.message'] = '';
		$doc_vals = $this->processRange($pids, '', 0);
		$doc_id = $doc_vals[0];
		$error = $doc_vals[1];
		
		if (!empty($docgroup) && is_numeric($docgroup) ) {
			switch ($action) {
				case 'pushDocGroup' :
					if (count($doc_id) > 0) {
						foreach ($doc_id as $value) {
							$docsAdded = 0;
							$sqlResult = $this->modx->db->select('*',$tbl_document_groups,"document_group = {$docgroup} AND document = {$value}");
							$NotAMember = ($this->modx->db->getRecordCount($sqlResult) == 0);
							if ($NotAMember) {
								$sql = "INSERT INTO {$tbl_document_groups} (document_group, document) VALUES ({$docgroup},{$value})";
								$sqlResult = $this->modx->db->query($sql);
								$this->secureWebDocument($value);
								$this->secureMgrDocument($value);
								$docsAdded += 1;
							} else {
								$this->dm->ph['update.message'] .= $this->dm->lang['DM_doc_skip_message1'] . ' ' . $value . ' ' . $this->dm->lang['DM_doc_skip_message2'] . "<br />";
							}
						}
					}
					
					break;
				case 'pullDocGroup' :
					if (count($doc_id) > 0) {
						foreach ($doc_id as $value) {
							$docsRemoved = 0;
							$sqlResult = $this->modx->db->select('*',$tbl_document_groups,"document_group = {$docgroup} AND document = {$value}");
							$AMember = ($this->modx->db->getRecordCount($sqlResult) <> 0);
							if ($AMember) {
								$sql = "DELETE FROM {$tbl_document_groups} WHERE document_group = {$docgroup} AND document = {$value}";
								$sqlResult = $this->modx->db->query($sql);
								$this->secureWebDocument($value);
								$this->secureMgrDocument($value);
								$docsRemoved += 1;
							} else {
								$this->dm->ph['update.message'] .= $this->dm->lang['DM_doc_skip_message1'] . $value . $this->dm->lang['DM_doc_skip_message2'] . "<br />";
							}
						}
					}
					break;
			}
		} else {
			$error = $this->dm->lang['DM_doc_no_docs'];
		}
	
		if ($error == '') {
			$this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_process_update_success'];
		} else {
			$this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
		}
	
		$this->logDocumentChange('docpermissions');
		return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
	}
	
	function changeOther($pids) {
		$tbl_site_content = $this->modx->getFullTableName('site_content');
		session_start();

		/* misc document settings */
		switch ($_POST['setoption']) {
			case 1:
				$fieldval = 'published';
				$secondaryFields = array (
					'publishedon' => (($_POST['newvalue'] == '1') ? time() : 0), 
					'publishedby' => (($_POST['newvalue'] == '1') ? $_SESSION['mgrInternalKey'] : 0)
				);
				$this->logDocumentChange('publish');
				break;
			case 2:
				$fieldval = 'hidemenu';
				$this->logDocumentChange('hidemenu');
				break;
			case 3:
				$fieldval = 'searchable';
				$this->logDocumentChange('search');
				break;
			case 4:
				$fieldval = 'cacheable';
				$this->logDocumentChange('cache');
				break;
			case 5:
				$fieldval = 'richtext';
				$this->logDocumentChange('richtext');
				break;
			case 6:
				$fieldval = 'deleted';
				$secondaryFields = array (
					'deletedon' => (($_POST['newvalue'] == '1') ? time() : '0'),
					'deletedby' => (($_POST['newvalue'] == '1') ? $_SESSION['mgrInternalKey'] : '0')
				);
				$this->logDocumentChange('delete');
				break;
			default:
				break;
		}
	
		/* document date settings */
		$dateval = array();
	
		if ($_POST['pubdate'] <> '')
			$dateval['pub_date'] = $this->modx->toTimeStamp($_POST['pubdate']);
		if ($_POST['unpubdate'] <> '')
			$dateval['unpub_date'] = $this->modx->toTimeStamp($_POST['unpubdate']);
		if ($_POST['createdon'] <> '')
			$dateval['createdon'] = $this->modx->toTimeStamp($_POST['createdon']);
		if ($_POST['editedon'] <> '')
			$dateval['editedon'] = $this->modx->toTimeStamp($_POST['editedon']);
	
		/* document author settings */
		$authorval = array ();
		if ($_POST['author_createdby'] <> 0)
			$authorval['createdby'] = intval($_POST['author_createdby']);
		if ($_POST['author_editedby'] <> 0)
			$authorval['editedby'] = intval($_POST['author_editedby']);
	
		$new = false;
		$results = $this->processRange($pids, 'id', 1);
		$pids = $results[0];
		$error = $results[1];
		$values = rtrim($pids, ' OR ');

		if ($pids !== '' && $_POST['newvalue'] !== '') {
			$fields = array (
				$fieldval => intval($_POST['newvalue'])
			);
			if (isset ($secondaryFields) && is_array($secondaryFields)) {
				$fields = array_merge($fields, $secondaryFields);
			}

			$this->modx->db->update($fields, $tbl_site_content, $values);
			$new = true;
		}

		if ($pids !== '' && count($dateval) > 0) {
			$this->modx->db->update($dateval, $tbl_site_content, $values);
			$new = true;
			$this->logDocumentChange('dates');
		}

		if ($pids <> '' && count($authorval) > 0) {
			$this->modx->db->update($authorval, $tbl_site_content, $values);
			$new = true;
			$this->logDocumentChange('authors');
		}

		if (!$new) {
			$error .= '<br />' . $this->dm->lang['DM_process_noselection'] . '<br />';
		}
	
		if ($error == '') {
			$this->dm->ph['update.message'] = '<br />' . $this->dm->lang['DM_process_update_success'];
		} else {
			$this->dm->ph['update.message'] = '<br />' . $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
		}
	
		return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
	}
    
    function processRange($pids, $column, $returnval = 1) {
		$tbl_site_content = $this->modx->getFullTableName('site_content');
		$values = array();
		$error = '';
	
		if (trim($pids) <> '') {
			$values = explode(',', $pids);
		} else {
			$error .= $this->dm->lang['DM_process_novalues'];
		}
		$pids = '';
		$rs = $this->modx->db->select('MAX(id)',$tbl_site_content);
		$total = $this->modx->db->getValue($rs);
		
		/* parse values, and check for invalid entries */
		foreach ($values as $key => $value) {
			/* value is a range */
			$value = trim($value);
			if(substr($value,0,1)==='-') $value = "0{$value}";
			if(substr($value,-1)==='-') $value .= $total;
			
			if (preg_match('/^[\d]+\-[\d]+$/', $value)) {
				$match = explode('-', $value);

				if (($match[1] - $match[0]) < 0) {
					$error = $this->dm->lang['DM_process_limits_error'] . $value . '<br />';
				}
				
				$loop = $match[1] - $match[0];
				for ($i = 0; $i <= $loop; $i++) {
					if ($returnval == 0) {
						$idarray[] = ($i + $match[0]);
					} else {
						$pids .= '' . $column . '=\'' . ($i + $match[0]) . '\' OR ';
					}
				}
			}
	
			/* value is a group for immediate children */
			elseif (preg_match('/^[\d]+\*$/', $value, $match)) {
				$match = rtrim($match[0], '*');
	
				$group = $this->modx->db->select('id', $tbl_site_content, 'parent=' . $match);

				if ($returnval == 0) {
					$idarray[] = $match;
				} else {
					$pids .= '' . $column . '=\'' . $match . '\' OR ';
				}
				if ($this->modx->db->getRecordCount($group) > 0) {
				while ($row = $this->modx->db->getRow($group)) {
					if ($returnval == 0) {
						$idarray[] = ($row['id']);
					} else {
						$pids .= '' . $column . '=\'' . $row['id'] . '\' OR ';
					}
				}
				}
			}
			/* value is a group for ALL children */
			elseif (preg_match('/^[\d]+\*\*$/', $value, $match)) {
				$match = rtrim($match[0], '**');
				$idarray[] = $match;

				for ($i = 0; $i < count($idarray); $i++) {
					$where = 'parent=' . $idarray[$i];
					$rs = $this->modx->db->select('id', $tbl_site_content, $where);
					if ($this->modx->db->getRecordCount($rs) > 0) {
						while ($row = $this->modx->db->getRow($rs)) {
							$idarray[] = $row['id'];
						}
					}
				}

				for ($i = 0; $i < count($idarray); $i++) {
					$pids .= "{$column}='{$idarray[$i]}' OR ";
				}
			}
			/* value is a single document */
			elseif (preg_match('/^[\d]+$/', $value, $match)) {
				if ($returnval == 0) {
					$idarray[] = ($i + $match[0]);
				} else {
					$pids .= "{$column}='{$value}' OR ";
				}
			} else {
				$error .= $this->dm->lang['DM_process_invalid_error'] . $value . '<br />';
			}
		}
		
		if ($returnval == 0) {
			$results[] = $idarray;
			$results[] = $error;
		} else {
			$results[] = $pids;
			$results[] = $error;
		}
		
		return $results;
	}
    
    function getTemplateVarIds($tvNames = array (), $documentId, $ignoreList=array()) {
    	$tbl_site_tmplvar_contentvalues = $this->modx->getFullTableName("site_tmplvar_contentvalues");
		$output = array ();
		if (count($tvNames) > 0) {
			foreach ($tvNames as $name => $value) {
				if (in_array($name,$ignoreList)) {
					continue;
				}
				$sql = $this->modx->db->select('id,default_text', $this->modx->getFullTableName('site_tmplvars'), "id='{$name}'");
				if ($this->modx->db->getRecordCount($sql) > 0) {
					$row = $this->modx->db->getRow($sql);
					if ($value !== $row['default_text'] || trim($value) == '') {
						$output["$name"] = $row['id'];
					} elseif ($value == $row["default_text"]) {
						$newSql = $this->modx->db->select("value", $tbl_site_tmplvar_contentvalues, "tmplvarid={$row['id']} AND contentid={$documentId}");
						if ($this->modx->db->getRecordCount($newSql) == 1) {
							$this->modx->db->delete($tbl_site_tmplvar_contentvalues, "tmplvarid={$row['id']} AND contentid={$documentId}");
						}
					}
				}
			}
		}
		return $output;
	}
    
    function secureWebDocument($docId = '') {
		$tbl_site_content = $this->modx->getFullTableName('site_content');
		$sql = "SELECT DISTINCT sc.id
									 FROM " . $tbl_site_content . " sc
									 LEFT JOIN " . $this->modx->getFullTableName("document_groups") . " dg ON dg.document = sc.id
									 LEFT JOIN " . $this->modx->getFullTableName("webgroup_access") . " wga ON wga.documentgroup = dg.document_group
									 WHERE " . ($docId > 0 ? " sc.id={$docId} AND " : "") . "wga.id>0";
		$ids = $this->modx->db->getColumn("id", $sql);
		if (count($ids) > 0) {
			$this->modx->db->query("UPDATE " . $tbl_site_content . " SET privateweb = 1 WHERE id IN (" . implode(",", $ids) . ")");
		} else {
			$this->modx->db->query("UPDATE " . $tbl_site_content . " SET privateweb = 0 WHERE " . ($docId > 0 ? "id={$docId}" : "privateweb = 1"));
		}
	}
	
	function secureMgrDocument($docId = '') {
		$tbl_site_content = $this->modx->getFullTableName('site_content');
		$sql = "SELECT DISTINCT sc.id
									 FROM " . $tbl_site_content . " sc
									 LEFT JOIN " . $this->modx->getFullTableName("document_groups") . " dg ON dg.document = sc.id
									 LEFT JOIN " . $this->modx->getFullTableName("membergroup_access") . " mga ON mga.documentgroup = dg.document_group
									 WHERE " . ($docId > 0 ? " sc.id={$docId} AND " : "") . "mga.id>0";
		$ids = $this->modx->db->getColumn("id", $sql);
		if (count($ids) > 0) {
			$this->modx->db->query("UPDATE " . $tbl_site_content . " SET privatemgr = 1 WHERE id IN (" . implode(",", $ids) . ")");
		} else {
			$this->modx->db->query("UPDATE " . $tbl_site_content . " SET privatemgr = 0 WHERE " . ($docId > 0 ? "id={$docId}" : "privatemgr = 1"));
		}
	}
	
	function logDocumentChange($action) {
		include_once(MODX_CORE_PATH . 'log.class.inc.php');
		$log = new logHandler;
	
		switch ($action) {
			case 'template' :
				$log->initAndWriteLog($this->dm->lang['DM_log_template']);
				break;
			case 'templatevariables' :
				$log->initAndWriteLog($this->dm->lang['DM_log_templatevariables']);
				break;
			case 'docpermissions' :
				$log->initAndWriteLog($this->dm->lang['DM_log_docpermissions']);
				break;	
			case 'sortmenu' :
				$log->initAndWriteLog($this->dm->lang['DM_log_sortmenu']);
				break;	
			case 'publish' :
				$log->initAndWriteLog($this->dm->lang['DM_log_publish']);
				break;	
			case 'hidemenu' :
				$log->initAndWriteLog($this->dm->lang['DM_log_hidemenu']);
				break;
			case 'search' :
				$log->initAndWriteLog($this->dm->lang['DM_log_search']);
				break;	
			case 'cache' :
				$log->initAndWriteLog($this->dm->lang['DM_log_cache']);
				break;
			case 'richtext' :
				$log->initAndWriteLog($this->dm->lang['DM_log_richtext']);
				break;
			case 'delete' :
				$log->initAndWriteLog($this->dm->lang['DM_log_delete']);
				break;	
			case 'dates' :
				$log->initAndWriteLog($this->dm->lang['DM_log_richtext']);
				break;
			case 'authors' :
				$log->initAndWriteLog($this->dm->lang['DM_log_authors']);
				break;
		}
	}
}
