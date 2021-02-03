<?php

class DocManagerBackend {
	var $dm = null;
	var $modx = null;

    function __construct(&$dm, &$modx) {
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
			$query = 'SELECT id , pagetitle , parent , menuindex, published, hidemenu, deleted  FROM '. evo()->getFullTableName('site_content') .' WHERE parent=' . $id . ' ORDER BY menuindex ASC';
			if (!$rs = db()->query($query)) {
				return false;
			}
		
			while ($row = db()->getRow($rs)) {
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
                    $classes .= (count(evo()->getChildIds($item['id'], 1)) > 0) ? ' hasChildren ' : ' noChildren ';
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
	    			$sql = 'UPDATE '.evo()->getFullTableName('site_content') ." set menuindex={$key} WHERE id={$id}";
					db()->query($sql);
    			}
    		}
    		$this->logDocumentChange('sortmenu');
    		
			evo()->clearCache();
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
				'template' => (int)$template);
			db()->update($fields, evo()->getFullTableName('site_content'), $values);
		} else {
			$error .= '<br />' . $this->dm->lang['DM_process_noselection'] . '<br />';
		}

		if ($error == '') {
			$this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_success'];
		} else {
			$this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
		}
		$this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_tpl_results_message'];
										
		evo()->clearCache();
		$this->logDocumentChange('template');
		return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
	}
	
	function changeTemplateVariables($pids) {
		$tbl_site_tmplvar_contentvalues = evo()->getFullTableName('site_tmplvar_contentvalues');
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

		if (count($pids) <= 0) {
            $updateError .= $this->dm->lang['DM_tv_no_docs'] . '<br />';
        } else {
            $tmplVars = array();
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'update_tv_') !== 0 || $value !== 'yes') {
                    continue;
                }

                //echo $key;
                $tvKeyName = substr($key, 10);
                //if (strpos($key,'_prefix') !== false)
                //	continue;

                $typeSQL = db()->select('*', evo()->getFullTableName('site_tmplvars'), "id={$tvKeyName}");
                $row = db()->getRow($typeSQL);
                if ($row['type'] === 'url') {
                    $tmplvar = $_POST["tv" . $row['id']];
                    if ($_POST["tv" . $row['id'] . '_prefix'] != '--') {
                        $tmplvar = str_replace(array(
                            "ftp://",
                            "http://"
                        ), "", $tmplvar);
                        $tmplvar = $_POST["tv" . $row['id'] . '_prefix'] . $tmplvar;
                    }
                } elseif ($row['type'] === 'file') {
                    $tmplvar = $_POST["tv" . $row['id']];
                } else {
                    if (is_array($_POST["tv" . $tvKeyName])) {
                        $feature_insert = array();
                        $lst = $_POST["tv" . $row['id']];
                        foreach ($lst as $feature_item) {
                            $feature_insert[count($feature_insert)] = $feature_item;
                        }
                        $tmplvar = implode("||", $feature_insert);
                    } else {
                        $tmplvar = $_POST["tv" . $row['id']];
                    }
                }
                $tmplVars[(string)($tvKeyName)] = $tmplvar;
            }

            foreach ($pids as $docID) {
                $tempSQL = db()->select('template', evo()->getFullTableName('site_content'), "id={$docID}");
                if (db()->count($tempSQL) <= 0) {
                    if ($docID !== '0') {
                        $updateError .= "ID: {$docID} " . $this->dm->lang['DM_tv_doc_not_found'] . '<br />';
                    }
                    continue;
                }

                $row = db()->getRow($tempSQL);
                if ($row['template'] != $_POST['template_id']) {
                    $updateError .= "ID: {$docID} " . $this->dm->lang['DM_tv_template_mismatch'] . '<br />';
                    continue;
                }
                $tvID = $this->getTemplateVarIds($tmplVars, $docID);
                if (count($tvID) <= 0) {
                    continue;
                }
                foreach ($tvID as $tvIndex => $tvValue) {
                    if ($_POST['update_tv_' . $tvIndex] !== 'yes') {
                        unset($noUpdate);
                        continue;
                    }
                    $checkSQL = db()->select(
                        'value',
                        $tbl_site_tmplvar_contentvalues,
                        "contentid='{$docID}' AND tmplvarid='{$tvValue}'"
                    );
                    $checkCount = db()->getRecordCount($checkSQL);
                    if ($checkCount) {
                        $checkRow = db()->getRow($checkSQL);
                        if ($checkRow['value'] == $tmplVars["$tvIndex"]) {
                            $noUpdate = true;
                        } elseif (trim($tmplVars["$tvIndex"]) == '') {
                            db()->delete(
                                $tbl_site_tmplvar_contentvalues,
                                "contentid='{$docID}' AND tmplvarid='{$tvValue}'"
                            );
                            $noUpdate = true;
                        }
                    }

                    if ($checkCount > 0 && !isset ($noUpdate)) {
                        $fields = array(
                            'value' => db()->escape($tmplVars["$tvIndex"])
                        );
                        db()->update(
                            $fields,
                            $tbl_site_tmplvar_contentvalues,
                            "contentid='{$docID}' AND tmplvarid='{$tvValue}'"
                        );
                        $updated = true;
                    } elseif (!isset ($noUpdate) && ltrim($tmplVars[(string)$tvIndex]) !== '') {
                        $fields = array(
                            'value' => db()->escape($tmplVars["$tvIndex"]),
                            'contentid' => db()->escape($docID),
                            'tmplvarid' => db()->escape($tvValue)
                        );
                        db()->insert($fields, $tbl_site_tmplvar_contentvalues);
                        $updated = true;
                    }
                    unset($noUpdate);
                }
            }
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
	
		evo()->clearCache();
		return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
	}
	
	function changeDocGroups($pids, $docgroup, $action) {
		$tbl_document_groups = evo()->getFullTableName('document_groups');
		$this->dm->ph['update.message'] = '';
		$doc_vals = $this->processRange($pids, '', 0);
		$doc_id = $doc_vals[0];
		$error = $doc_vals[1];
		
		if (!empty($docgroup) && is_numeric($docgroup) ) {
			switch ($action) {
				case 'pushDocGroup' :
					if (count($doc_id) > 0) {
						foreach ($doc_id as $value) {
							$sqlResult = db()->select(
							    '*',
                                $tbl_document_groups,
                                sprintf('document_group=%d AND document=%s', $docgroup, $value)
                            );
							$NotAMember = (db()->getRecordCount($sqlResult) == 0);
							if ($NotAMember) {
								$sql = sprintf(
								    'INSERT INTO %s (document_group, document) VALUES (%d,%s)',
                                    $tbl_document_groups,
                                    $docgroup,
                                    $value
                                );
								db()->query($sql);
								$this->secureWebDocument($value);
								$this->secureMgrDocument($value);
							} else {
								$this->dm->ph['update.message'] .= sprintf(
								    '%s %s %s<br />',
                                    $this->dm->lang['DM_doc_skip_message1'],
                                    $value,
                                    $this->dm->lang['DM_doc_skip_message2']
                                );
							}
						}
					}
					
					break;
				case 'pullDocGroup' :
					if (count($doc_id) > 0) {
						foreach ($doc_id as $value) {
							$docsRemoved = 0;
							$sqlResult = db()->select('*',$tbl_document_groups,"document_group = {$docgroup} AND document = {$value}");
							$AMember = (db()->getRecordCount($sqlResult) <> 0);
							if ($AMember) {
								$sql = sprintf(
								    'DELETE FROM %s WHERE document_group = %d AND document = %s',
                                    $tbl_document_groups,
                                    $docgroup,
                                    $value
                                );
								db()->query($sql);
								$this->secureWebDocument($value);
								$this->secureMgrDocument($value);
							} else {
								$this->dm->ph['update.message'] .= sprintf(
								    '%s%s%s<br />',
                                    $this->dm->lang['DM_doc_skip_message1'],
                                    $value,
                                    $this->dm->lang['DM_doc_skip_message2']
                                );
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
		$tbl_site_content = evo()->getFullTableName('site_content');
		session_start();

		/* misc document settings */
		switch ($_POST['setoption']) {
			case 1:
				$fieldval = 'published';
				$secondaryFields = array (
					'publishedon' => postv('newvalue') == 1 ? time() : 0,
					'publishedby' => postv('newvalue') == 1 ? $_SESSION['mgrInternalKey'] : 0
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
					'deletedon' => postv('newvalue') == 1 ? time() : '0',
					'deletedby' => postv('newvalue') == 1 ? $_SESSION['mgrInternalKey'] : '0'
				);
				$this->logDocumentChange('delete');
				break;
			default:
				break;
		}
	
		/* document date settings */
		$dateval = array();
	
		if (postv('pubdate') != '') {
            $dateval['pub_date'] = evo()->toTimeStamp(postv('pubdate'));
        }
		if (postv('unpubdate') != '') {
            $dateval['unpub_date'] = evo()->toTimeStamp(postv('unpubdate'));
        }
		if (postv('createdon') != '')
			$dateval['createdon'] = evo()->toTimeStamp(postv('createdon'));
		if (postv('editedon') != '')
			$dateval['editedon'] = evo()->toTimeStamp(postv('editedon'));
	
		/* document author settings */
		$authorval = array ();
		if ($_POST['author_createdby'] <> 0)
			$authorval['createdby'] = (int)$_POST['author_createdby'];
		if ($_POST['author_editedby'] <> 0)
			$authorval['editedby'] = (int)$_POST['author_editedby'];
	
		$new = false;
		$results = $this->processRange($pids, 'id', 1);
		$pids = $results[0];
		$error = $results[1];
		$values = rtrim($pids, ' OR ');

		if ($pids !== '' && $_POST['newvalue'] !== '') {
			$fields = array (
				$fieldval => (int)$_POST['newvalue']
			);
			if (isset ($secondaryFields) && is_array($secondaryFields)) {
				$fields = array_merge($fields, $secondaryFields);
			}

			db()->update($fields, $tbl_site_content, $values);
			$new = true;
		}

		if ($pids !== '' && count($dateval) > 0) {
			db()->update($dateval, $tbl_site_content, $values);
			$new = true;
			$this->logDocumentChange('dates');
		}

		if ($pids <> '' && count($authorval) > 0) {
			db()->update($authorval, $tbl_site_content, $values);
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
		$tbl_site_content = evo()->getFullTableName('site_content');
		$values = array();
		$error = '';
	
		if (trim($pids) <> '') {
			$values = explode(',', $pids);
		} else {
			$error .= $this->dm->lang['DM_process_novalues'];
		}
		$pids = '';
		$rs = db()->select('MAX(id)',$tbl_site_content);
		$total = db()->getValue($rs);
		
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
	
				$group = db()->select('id', $tbl_site_content, 'parent=' . $match);

				if ($returnval == 0) {
					$idarray[] = $match;
				} else {
					$pids .= '' . $column . '=\'' . $match . '\' OR ';
				}
				if (db()->getRecordCount($group) > 0) {
				while ($row = db()->getRow($group)) {
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

                foreach ($idarray as $iValue) {
                    $where = 'parent=' . $iValue;
                    $rs = db()->select('id', $tbl_site_content, $where);
                    if (db()->getRecordCount($rs) > 0) {
                        while ($row = db()->getRow($rs)) {
                            $idarray[] = $row['id'];
                        }
                    }
                }

                foreach ($idarray as $iValue) {
					$pids .= "{$column}='$iValue' OR ";
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
    	$tbl_site_tmplvar_contentvalues = evo()->getFullTableName("site_tmplvar_contentvalues");
		$output = array ();
		if (count($tvNames) > 0) {
			foreach ($tvNames as $name => $value) {
				if (in_array($name,$ignoreList)) {
					continue;
				}
				$sql = db()->select('id,default_text', evo()->getFullTableName('site_tmplvars'), "id='{$name}'");
				if (db()->getRecordCount($sql) > 0) {
					$row = db()->getRow($sql);
					if ($value !== $row['default_text'] || trim($value) == '') {
						$output["$name"] = $row['id'];
					} elseif ($value == $row["default_text"]) {
						$newSql = db()->select("value", $tbl_site_tmplvar_contentvalues, "tmplvarid={$row['id']} AND contentid={$documentId}");
						if (db()->getRecordCount($newSql) == 1) {
							db()->delete($tbl_site_tmplvar_contentvalues, "tmplvarid={$row['id']} AND contentid={$documentId}");
						}
					}
				}
			}
		}
		return $output;
	}
    
    function secureWebDocument($docId = '') {
		$tbl_site_content = evo()->getFullTableName('site_content');
		$sql = sprintf("SELECT DISTINCT sc.id
									 FROM %s sc
									 LEFT JOIN %s dg ON dg.document = sc.id
									 LEFT JOIN %s wga ON wga.documentgroup = dg.document_group
									 WHERE %swga.id>0", $tbl_site_content, evo()->getFullTableName("document_groups"), evo()->getFullTableName("webgroup_access"), $docId > 0 ? " sc.id={$docId} AND " : "");
		$ids = db()->getColumn("id", $sql);
		if (count($ids) > 0) {
			db()->query("UPDATE " . $tbl_site_content . " SET privateweb = 1 WHERE id IN (" . implode(",", $ids) . ")");
		} else {
			db()->query("UPDATE " . $tbl_site_content . " SET privateweb = 0 WHERE " . ($docId > 0 ? "id={$docId}" : "privateweb = 1"));
		}
	}
	
	function secureMgrDocument($docId = '') {
		$tbl_site_content = evo()->getFullTableName('site_content');
		$sql = "SELECT DISTINCT sc.id
									 FROM " . $tbl_site_content . " sc
									 LEFT JOIN " . evo()->getFullTableName("document_groups") . " dg ON dg.document = sc.id
									 LEFT JOIN " . evo()->getFullTableName("membergroup_access") . " mga ON mga.documentgroup = dg.document_group
									 WHERE " . ($docId > 0 ? " sc.id={$docId} AND " : "") . "mga.id>0";
		$ids = db()->getColumn("id", $sql);
		if (count($ids) > 0) {
			db()->query("UPDATE " . $tbl_site_content . " SET privatemgr = 1 WHERE id IN (" . implode(",", $ids) . ")");
		} else {
			db()->query("UPDATE " . $tbl_site_content . " SET privatemgr = 0 WHERE " . ($docId > 0 ? "id={$docId}" : "privatemgr = 1"));
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
