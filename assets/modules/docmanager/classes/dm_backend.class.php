<?php

class DocManagerBackend
{
    public $dm = null;
    /** @var int 直前の processRange() で、文書グループの制限により対象から外した文書の数 */
    private $skippedCount = 0;

    public function __construct(&$dm)
    {
        $this->dm = &$dm;
    }

    public function handlePostback()
    {
        $tabAction = post_strvar('tabAction');
        switch ($tabAction) {
            case 'changeTemplate':
                echo $this->changeTemplate(post_strvar('pids'), post_strvar('newvalue'));
                break;
            case 'changeTV':
                echo $this->changeTemplateVariables(post_strvar('pids'));
                break;
            case 'pushDocGroup':
            case 'pullDocGroup':
                echo $this->changeDocGroups(post_strvar('pids'), post_intvar('newvalue'), $tabAction);
                break;
            case 'changeOther':
                echo $this->changeOther(post_strvar('pids'));
                break;
            case 'sortMenu':
                echo $this->showSortList(post_strvar('new_parent'));
                break;
            case 'sortList':
                echo $this->changeSort(post_strvar('list'));
                break;
            default:
                echo 'No tab action defined';
        }
    }

    public function showSortList($id)
    {
        $this->dm->ph['sort.disable_tree_select'] = 'false';
        $this->dm->ph['sort.options'] = '';
        $this->dm->ph['sort.save'] = '';

        if ($id == '') {
            $this->dm->ph['sort.disable_tree_select'] = 'true';
            $this->dm->ph['sort.save'] = 'none';
            $this->dm->ph['sort.message'] = $this->dm->lang['DM_sort_noid'];
            return $this->dm->parseTemplate('sort_list.tpl', $this->dm->ph);
        }

        $resource = [];
        $parentId = to_uintvar($id);
        if ($parentId !== null) {
            // 開けない文書（文書グループ）は一覧に出さない
            $accessCondition = $this->accessCondition();
            $rs = db()->select(
                'sc.id,sc.pagetitle,sc.parent,sc.menuindex,sc.published,sc.hidemenu,sc.deleted,sc.isfolder',
                '[+prefix+]site_content sc',
                'sc.parent=' . (int)$parentId . ($accessCondition !== '' ? ' AND ' . $accessCondition : ''),
                'sc.menuindex ASC'
            );
            if (!$rs) {
                return false;
            }

            while ($row = db()->getRow($rs)) {
                $resource[] = $row;
            }
        }

        if (!$resource) {
            $this->dm->ph['sort.disable_tree_select'] = 'true';
            $this->dm->ph['sort.save'] = 'none';
            $this->dm->ph['sort.message'] = $this->dm->lang['DM_sort_nochildren'];

            return $this->dm->parseTemplate('sort_list.tpl', $this->dm->ph);
        }

        foreach ($resource as $item) {
            $classes = [
                $item['hidemenu'] ? 'notInMenuNode' : 'inMenuNode',
                $item['published'] ? 'publishedNode ' : 'unpublishedNode ',
                $item['isfolder'] ? 'hasChildren' : 'noChildren',
            ];
            if ($item['deleted']) {
                $classes[] = 'deletedNode';
            }
            if ($item['id'] == evo()->config('site_start')) {
                $classes[] = 'homeNode';
            }
            if ($item['id'] == evo()->config('error_page')) {
                $classes[] = 'errorNode';
            }
            if ($item['id'] == evo()->config('site_unavailable_page')) {
                $classes[] = 'unavailableNode';
            }
            if ($item['id'] == evo()->config('unauthorized_page')) {
                $classes[] = 'unauthorizedNode';
            }
            $this->dm->ph['sort.options'] .= sprintf(
                '<li id="item_%s" class="sort %s">%s</li>',
                $item['id'],
                implode(' ', $classes),
                evo()->hsc($item['pagetitle'])
            );
        }

        return $this->dm->parseTemplate('sort_list.tpl', $this->dm->ph);
    }

    public function changeSort($items)
    {
        $skipped = [];
        if (strlen($items) > 0) {
            $order = [];
            foreach (explode(';', $items) as $key => $value) {
                $id = to_intvar(ltrim($value, 'item_'));
                if ($id > 0) {
                    $order[$id] = $key + 1;
                }
            }
            [$accessible, $skipped] = $this->splitAccessibleIds(array_keys($order));
            $menuindexes = $order;
            if ($this->accessCondition() !== '' && $accessible) {
                // 絞られる利用者は、見えない同じ親の子と menuindex が重ならないよう、
                // 開ける子が今持つ値を、新しい順番へ割り当て直す（値は必ず増える形に整える）
                $current = [];
                $rs = db()->select(
                    'id,menuindex',
                    '[+prefix+]site_content',
                    'id IN (' . implode(',', array_map('intval', $accessible)) . ')'
                );
                while ($row = db()->getRow($rs)) {
                    $current[(int)$row['id']] = (int)$row['menuindex'];
                }
                $accessible = array_values(array_filter($accessible, function ($id) use ($current) {
                    return isset($current[$id]);
                }));
                usort($accessible, function ($x, $y) use ($order) {
                    return $order[$x] <=> $order[$y];
                });
                $values = array_values($current);
                sort($values);
                foreach ($values as $i => $v) {
                    if ($i > 0 && $v <= $values[$i - 1]) {
                        $values[$i] = $values[$i - 1] + 1;
                    }
                }
                $menuindexes = [];
                foreach ($accessible as $i => $id) {
                    $menuindexes[$id] = $values[$i];
                }
            }
            foreach ($accessible as $id) {
                $sql = sprintf(
                    "UPDATE %s set menuindex=%s WHERE id=%s",
                    evo()->getFullTableName('site_content'),
                    (int)$menuindexes[$id],
                    (int)$id
                );
                db()->query($sql);
            }
            $this->logDocumentChange('sortmenu');
            evo()->clearCache();
        }
        $this->dm->ph['sort.message'] = $this->dm->lang['DM_sort_updated'];
        if ($skipped) {
            $this->dm->ph['sort.message'] .= $this->skippedNotice(count($skipped));
        }
        $this->dm->ph['sort.save'] = 'none';
        $this->dm->ph['sort.disable_tree_select'] = 'true';
        return $this->dm->parseTemplate('sort_list.tpl', $this->dm->ph);
    }

    public function changeTemplate($pids, $template)
    {
        $results = $this->processRange($pids, 'id', 1);
        $pids = $results[0];
        $error = $results[1];

        $templateId = to_uintvar($template);
        $written = false;
        if ($pids !== '' && $templateId !== null) {
            $values = rtrim($pids, ' OR ');
            $fields = ['template' => $templateId];
            db()->update(db()->escape($fields), evo()->getFullTableName('site_content'), $values);
            $written = true;
        } else {
            $error .= '<br />' . $this->dm->lang['DM_process_noselection'] . '<br />';
        }

        if ($written) {
            evo()->clearCache();
        }
        if (!$error) {
            $this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_success'];
        } else {
            $this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
        }
        $this->dm->ph['update.message'] .= $this->skippedNotice();
        $this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_tpl_results_message'];

        $this->logDocumentChange('template');
        return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
    }

    public function changeTemplateVariables($pids)
    {
        $tbl_site_tmplvar_contentvalues = evo()->getFullTableName('site_tmplvar_contentvalues');
        $updateError = '';
        $updated = false;
        $templateId = post_uintvar('template_id');

        $results = $this->processRange($pids, 'id', 0);
        $pids = $results[0];
        $error = $results[1];

        if (count($pids) <= 0) {
            $updateError .= $this->dm->lang['DM_tv_no_docs'] . '<br />';
        } else {
            $tmplVars = [];
            foreach (postv() as $key => $value) {
                if (strpos($key, 'update_tv_') !== 0 || $value !== 'yes') {
                    continue;
                }

                //echo $key;
                $tvKeyName = (int)substr($key, 10);
                if (!$tvKeyName) {
                    continue;
                }

                $typeSQL = db()->select('*', evo()->getFullTableName('site_tmplvars'), "id=" . (int)$tvKeyName);
                $row = db()->getRow($typeSQL);
                if (!$row) {
                    continue;
                }
                if ($row['type'] === 'url') {
                    $tmplvar = normalize_url_tv_value(
                        post_strvar("tv" . $row['id']),
                        post_choicevar("tv" . $row['id'] . '_prefix', tv_url_prefixes(), '--')
                    );
                } elseif ($row['type'] === 'file') {
                    $tmplvar = post_strvar("tv" . $row['id']);
                } else {
                    // 文字列か、チェックボックスなどの配列（文字列の要素だけを「||」でつなぐ）
                    $lst = postv("tv" . $row['id']);
                    if (is_array($lst)) {
                        $tmplvar = implode('||', array_filter($lst, 'is_string'));
                    } else {
                        $tmplvar = post_strvar("tv" . $row['id']);
                    }
                }
                $tmplVars[(string)($tvKeyName)] = $tmplvar;
            }

            foreach ($pids as $docID) {
                $tempSQL = db()->select('template', evo()->getFullTableName('site_content'), "id=" . (int)$docID);
                if (db()->count($tempSQL) <= 0) {
                    if ((int) $docID !== 0) {
                        $updateError .= "ID: " . $docID . " " . $this->dm->lang['DM_tv_doc_not_found'] . '<br />';
                    }
                    continue;
                }

                $row = db()->getRow($tempSQL);
                if ($templateId === null || (int)$row['template'] !== $templateId) {
                    $updateError .= "ID: " . $docID . " " . $this->dm->lang['DM_tv_template_mismatch'] . '<br />';
                    continue;
                }
                $tvID = $this->getTemplateVarIds($docID, $tmplVars);
                if (count($tvID) <= 0) {
                    continue;
                }
                foreach ($tvID as $tvIndex => $tvValue) {
                    if (postv('update_tv_' . $tvIndex) !== 'yes') {
                        unset($noUpdate);
                        continue;
                    }
                    $checkSQL = db()->select(
                        'value',
                        $tbl_site_tmplvar_contentvalues,
                        sprintf("contentid=%d AND tmplvarid=%d", (int)$docID, (int)$tvValue)
                    );
                    $checkCount = db()->count($checkSQL);
                    if ($checkCount) {
                        $checkRow = db()->getRow($checkSQL);
                        if ($checkRow['value'] == $tmplVars["$tvIndex"]) {
                            $noUpdate = true;
                        } elseif (trim($tmplVars["$tvIndex"]) == '') {
                            db()->delete(
                                $tbl_site_tmplvar_contentvalues,
                                sprintf("contentid=%d AND tmplvarid=%d", (int)$docID, (int)$tvValue)
                            );
                            $noUpdate = true;
                        }
                    }

                    if (!isset($noUpdate)) {
                        if ($checkCount > 0) {
                            $fields = ['value' => db()->escape($tmplVars["$tvIndex"])];
                            db()->update(
                                $fields,
                                $tbl_site_tmplvar_contentvalues,
                                sprintf("contentid=%d AND tmplvarid=%d", (int)$docID, (int)$tvValue)
                            );
                            $updated = true;
                        } elseif (ltrim($tmplVars[(string)$tvIndex]) !== '') {
                            $fields = [
                                'value' => db()->escape($tmplVars["$tvIndex"]),
                                'contentid' => (int)$docID,
                                'tmplvarid' => (int)$tvValue
                            ];
                            db()->insert($fields, $tbl_site_tmplvar_contentvalues);
                            $updated = true;
                        }
                    }
                    unset($noUpdate);
                }
            }
        }

        if ($updated) {
            $this->logDocumentChange('templatevariables');
        }

        if (!$error && !$updateError) {
            $this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_success'];
        } else {
            $this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
        }

        if ($updateError) {
            $this->dm->ph['update.message'] .= '<br />' . $updateError;
        }
        $this->dm->ph['update.message'] .= $this->skippedNotice();
        $this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_tpl_results_message'];

        evo()->clearCache();
        return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
    }

    public function changeDocGroups($pids, $docgroup, $action)
    {
        $this->dm->ph['update.message'] = '';
        if (!evo()->hasPermission('access_permissions') || !evo()->hasPermission('web_access_permissions')) {
            $this->dm->ph['update.message'] = $this->dm->lang['DM_process_update_error']
                . '<br />' . $this->dm->lang['DM_process_no_permission'];
            return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
        }
        $doc_vals = $this->processRange($pids, '', 0);
        $doc_id = $doc_vals[0];
        $error = $doc_vals[1];

        if ($docgroup > 0) {
            switch ($action) {
                case 'pushDocGroup':
                    if (count($doc_id) <= 0) {
                        break;
                    }
                    // 範囲の入力は存在しない id も含むので、存在する文書だけに行を入れる
                    $existingIds = array_flip(array_map('intval', db()->getColumn(
                        'id',
                        db()->select(
                            'id',
                            '[+prefix+]site_content',
                            'id IN (' . implode(',', array_map('intval', $doc_id)) . ')'
                        )
                    )));
                    foreach ($doc_id as $value) {
                        $value = (int)$value;
                        if (!isset($existingIds[$value])) {
                            if ($value !== 0) {
                                $this->dm->ph['update.message'] .= sprintf(
                                    'ID: %d %s<br />',
                                    $value,
                                    $this->dm->lang['DM_tv_doc_not_found']
                                );
                            }
                            continue;
                        }
                        $sqlResult = db()->select(
                            '*',
                            '[+prefix+]document_groups',
                            sprintf('document_group=%d AND document=%d', (int)$docgroup, $value)
                        );
                        $NotAMember = (db()->count($sqlResult) == 0);
                        if (!$NotAMember) {
                            $this->dm->ph['update.message'] .= sprintf(
                                '%s %s %s<br />',
                                $this->dm->lang['DM_doc_skip_message1'],
                                $value,
                                $this->dm->lang['DM_doc_skip_message2']
                            );
                            continue;
                        }
                        db()->insert(
                            ['document_group' => (int)$docgroup, 'document' => $value],
                            '[+prefix+]document_groups'
                        );
                        manager()->refreshDocumentPrivateFlags($value);
                    }
                    break;
                case 'pullDocGroup':
                    if (count($doc_id) > 0) {
                        foreach ($doc_id as $value) {
                            $value = (int)$value;
                            $docsRemoved = 0;
                            $sqlResult = db()->select(
                                '*',
                                '[+prefix+]document_groups',
                                sprintf('document_group=%d AND document=%d', (int)$docgroup, $value)
                            );
                            $AMember = (db()->count($sqlResult) <> 0);
                            if ($AMember) {
                                db()->delete(
                                    '[+prefix+]document_groups',
                                    sprintf('document_group=%d AND document=%d', (int)$docgroup, $value)
                                );
                                manager()->refreshDocumentPrivateFlags($value);
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

        if (!$error) {
            $this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_process_update_success'];
            evo()->clearCache();
        } else {
            $this->dm->ph['update.message'] .= '<br />' . $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
        }
        $this->dm->ph['update.message'] .= $this->skippedNotice();
        $this->logDocumentChange('docpermissions');
        return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
    }

    public function changeOther($pids)
    {
        $tbl_site_content = evo()->getFullTableName('site_content');

        /* misc document settings */
        $fieldval = '';
        $logAction = '';
        $newvalue = post_choicevar('newvalue', [1, 0], -1);
        switch (post_choicevar('setoption', [1, 2, 3, 4, 5, 6], 0)) {
            case 1:
                $fieldval = 'published';
                $secondaryFields = [
                    'publishedon' => $newvalue === 1 ? time() : 0,
                    'publishedby' => $newvalue === 1 ? sessionv('mgrInternalKey') : 0
                ];
                $logAction = 'publish';
                break;
            case 2:
                $fieldval = 'hidemenu';
                $logAction = 'hidemenu';
                break;
            case 3:
                $fieldval = 'searchable';
                $logAction = 'search';
                break;
            case 4:
                $fieldval = 'cacheable';
                $logAction = 'cache';
                break;
            case 5:
                $fieldval = 'richtext';
                $logAction = 'richtext';
                break;
            case 6:
                $fieldval = 'deleted';
                $secondaryFields = [
                    'deletedon' => $newvalue === 1 ? time() : '0',
                    'deletedby' => $newvalue === 1 ? sessionv('mgrInternalKey') : '0'
                ];
                $logAction = 'delete';
                break;
            default:
                break;
        }

        /* document date settings */
        $dateval = [];
        $invalidInputs = [];

        $dateFields = [
            'pub_date' => 'pubdate',
            'unpub_date' => 'unpubdate',
            'createdon' => 'createdon',
            'editedon' => 'editedon',
        ];
        foreach ($dateFields as $column => $inputName) {
            $input = trim(post_strvar($inputName));
            if ($input === '') {
                continue;
            }
            $timestamp = evo()->parseDateInput($input);
            if ($timestamp === null) {
                $invalidInputs[] = hsc($input);
                continue;
            }
            $dateval[$column] = $timestamp;
        }

        /* document author settings */
        $authorval = [];
        if (post_intvar('author_createdby') > 0) {
            $authorval['createdby'] = post_intvar('author_createdby');
        }
        if (post_intvar('author_editedby') > 0) {
            $authorval['editedby'] = post_intvar('author_editedby');
        }

        $new = false;
        $results = $this->processRange($pids, 'id', 1);
        $pids = $results[0];
        $error = $results[1];
        $values = rtrim($pids, ' OR ');

        // 解釈できない日付があるときは、DB に何も書かずにエラーとして知らせる
        if ($invalidInputs) {
            $this->dm->ph['update.message'] = '<br />' . $this->dm->lang['DM_process_date_invalid']
                . '<br />' . implode('<br />', $invalidInputs);
            return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
        }

        // setoption「-」のときは、公開状態などの項目は変えない
        if ($pids !== '' && $fieldval !== '' && $newvalue !== -1) {
            $fields = [$fieldval => $newvalue];
            if (isset($secondaryFields) && is_array($secondaryFields)) {
                $fields = array_merge($fields, $secondaryFields);
            }

            db()->update(db()->escape($fields), $tbl_site_content, $values);
            $new = true;
            $this->logDocumentChange($logAction);
        }

        if ($pids !== '' && count($dateval) > 0) {
            db()->update(db()->escape($dateval), $tbl_site_content, $values);
            $new = true;
            $this->logDocumentChange('dates');
        }

        if ($pids <> '' && count($authorval) > 0) {
            db()->update(db()->escape($authorval), $tbl_site_content, $values);
            $new = true;
            $this->logDocumentChange('authors');
        }

        if (!$new) {
            $error .= '<br />' . $this->dm->lang['DM_process_noselection'] . '<br />';
        }

        if ($new) {
            evo()->clearCache();
        }
        if (!$error) {
            $this->dm->ph['update.message'] = '<br />' . $this->dm->lang['DM_process_update_success'];
        } else {
            $this->dm->ph['update.message'] = '<br />' . $this->dm->lang['DM_process_update_error'] . '<br />' . $error;
        }
        $this->dm->ph['update.message'] .= $this->skippedNotice();

        return $this->dm->parseTemplate('update.tpl', $this->dm->ph);
    }

    function processRange($pids, $column, $returnval = 1)
    {
        $tbl_site_content = evo()->getFullTableName('site_content');
        $values = [];
        $idarray = [];
        $error = '';
        $this->skippedCount = 0;

        if (trim($pids) !== '') {
            $values = explode(',', $pids);
        } else {
            $error .= $this->dm->lang['DM_process_novalues'];
        }
        $rs = db()->select('MAX(id)', $tbl_site_content);
        $total = db()->getValue($rs);

        /* parse values, and check for invalid entries */
        foreach ($values as $key => $value) {
            /* value is a range */
            $value = trim($value);
            if (strpos($value, '-') === 0) {
                $value = "0" . $value;
            }
            if (substr($value, -1) === '-') {
                $value .= $total;
            }

            if (preg_match('/^[\d]+-[\d]+$/', $value)) {
                $match = explode('-', $value);

                if (($match[1] - $match[0]) < 0) {
                    $error = $this->dm->lang['DM_process_limits_error'] . hsc($value) . '<br />';
                }

                $loop = $match[1] - $match[0];
                for ($i = 0; $i <= $loop; $i++) {
                    $idarray[] = (int) ($i + $match[0]);
                }
            } elseif (preg_match('/^[\d]+\*$/', $value, $match)) {
                /* value is a group for immediate children */
                $match = rtrim($match[0], '*');

                $group = db()->select('id', $tbl_site_content, 'parent=' . (int)$match);

                $idarray[] = (int) $match;
                if (db()->count($group) > 0) {
                    while ($row = db()->getRow($group)) {
                        $idarray[] = (int) $row['id'];
                    }
                }
            } elseif (preg_match('/^[\d]+\*\*$/', $value, $match)) {
                /* value is a group for all descendants */
                $descendants = [(int) rtrim($match[0], '*')];
                $visited = [$descendants[0] => true];

                $level = $descendants;
                while ($level) {
                    // 階層ごとにまとめて問い合わせる（問い合わせ回数は木の深さの分だけ）
                    $rs = db()->select(
                        'id,parent',
                        $tbl_site_content,
                        'parent IN (' . implode(',', array_map('intval', $level)) . ')'
                    );
                    $childrenByParent = [];
                    while ($row = db()->getRow($rs)) {
                        $childrenByParent[(int) $row['parent']][] = (int) $row['id'];
                    }
                    $next = [];
                    foreach ($level as $parentId) {
                        foreach ($childrenByParent[$parentId] ?? [] as $childId) {
                            if (isset($visited[$childId])) {
                                continue;
                            }
                            $visited[$childId] = true;
                            $descendants[] = $childId;
                            $next[] = $childId;
                        }
                    }
                    $level = $next;
                }

                foreach ($descendants as $descendantId) {
                    $idarray[] = $descendantId;
                }
            } elseif (preg_match('/^[\d]+$/', $value, $match)) {
                $idarray[] = (int) $match[0];
            } else {
                $error .= $this->dm->lang['DM_process_invalid_error'] . hsc($value) . '<br />';
            }
        }

        // 開けない文書（文書グループ）は対象から外す。判定は splitAccessibleIds() の 1 か所で、
        // 範囲の入力を受ける更新はこの関数を通る。外した数は skippedNotice() でお知らせとして出す（エラーにしない）
        [$idarray, $skipped] = $this->splitAccessibleIds($idarray);
        $this->skippedCount = count($skipped);

        if ($returnval == 0) {
            return [$idarray, $error];
        }

        $pids = '';
        foreach ($idarray as $id) {
            $pids .= $column . "='" . (int) $id . "' OR ";
        }
        return [$pids, $error];
    }

    /**
     * 文書グループで絞る SQL の条件式（site_content の別名 sc）。絞らないときは空文字。判定の入口はここ 1 か所
     *
     * @return string
     */
    private function accessCondition()
    {
        return manager()->documentAccessCondition('sc');
    }

    /**
     * 文書の id を、文書グループで開けるものと、存在するが開けないものに分ける（ManagerAPI の判定を使う）。
     * 絞らないとき（管理者・use_udperms 無効）は何も除かない。存在しない id は除かない（呼び出し側の既存の扱いのまま）
     *
     * @param int[] $ids
     * @return array [開けるまたは存在しない id の配列, 開けない id の配列]
     */
    private function splitAccessibleIds(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $condition = $this->accessCondition();
        if ($condition === '' || !$ids) {
            return [$ids, []];
        }

        $denied = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $rs = db()->select(
                'sc.id',
                '[+prefix+]site_content sc',
                'sc.id IN (' . implode(',', $chunk) . ') AND NOT (' . $condition . ')'
            );
            while ($row = db()->getRow($rs)) {
                $denied[(int)$row['id']] = true;
            }
        }
        if (!$denied) {
            return [$ids, []];
        }

        $accessible = array_values(array_filter($ids, function ($id) use ($denied) {
            return !isset($denied[$id]);
        }));
        $skipped = array_keys($denied);
        sort($skipped);
        return [$accessible, $skipped];
    }

    /**
     * 外した文書の数を伝えるお知らせ（エラーとは別。id は出さない）。外していなければ空文字
     *
     * @param int $count 省略時は直前の processRange() の数
     * @return string
     */
    private function skippedNotice($count = null)
    {
        $count = $count ?? $this->skippedCount;
        if ($count <= 0) {
            return '';
        }
        return '<br />' . sprintf($this->dm->lang['DM_process_no_access'], (int)$count);
    }

    function getTemplateVarIds($documentId, $tvNames = [], $ignoreList = [])
    {
        $tbl_site_tmplvar_contentvalues = evo()->getFullTableName("site_tmplvar_contentvalues");
        if (count($tvNames) <= 0) {
            return [];
        }
        $output = [];
        foreach ($tvNames as $name => $value) {
            if (in_array($name, $ignoreList)) {
                continue;
            }
            $sql = db()->select(
                'id,default_text',
                '[+prefix+]site_tmplvars',
                sprintf('id=%d', (int) $name)
            );
            if (db()->count($sql) <= 0) {
                continue;
            }
            $row = db()->getRow($sql);
            if ($value !== $row['default_text'] || trim($value) == '') {
                $output["$name"] = $row['id'];
                continue;
            }
            if ($value != $row["default_text"]) {
                continue;
            }
            $newSql = db()->select(
                'value',
                '[+prefix+]site_tmplvar_contentvalues',
                sprintf('tmplvarid=%d AND contentid=%d', (int)$row['id'], (int)$documentId)
            );
            if (db()->count($newSql) != 1) {
                continue;
            }
            db()->delete(
                '[+prefix+]site_tmplvar_contentvalues',
                sprintf('tmplvarid=%d AND contentid=%d', (int)$row['id'], (int)$documentId)
            );
        }
        return $output;
    }

    function logDocumentChange($action)
    {
        include_once(MODX_CORE_PATH . 'log.class.inc.php');
        $log = new logHandler;

        switch ($action) {
            case 'template':
                $log->initAndWriteLog($this->dm->lang['DM_log_template']);
                break;
            case 'templatevariables':
                $log->initAndWriteLog($this->dm->lang['DM_log_templatevariables']);
                break;
            case 'docpermissions':
                $log->initAndWriteLog($this->dm->lang['DM_log_docpermissions']);
                break;
            case 'sortmenu':
                $log->initAndWriteLog($this->dm->lang['DM_log_sortmenu']);
                break;
            case 'publish':
                $log->initAndWriteLog($this->dm->lang['DM_log_publish']);
                break;
            case 'hidemenu':
                $log->initAndWriteLog($this->dm->lang['DM_log_hidemenu']);
                break;
            case 'search':
                $log->initAndWriteLog($this->dm->lang['DM_log_search']);
                break;
            case 'cache':
                $log->initAndWriteLog($this->dm->lang['DM_log_cache']);
                break;
            case 'richtext':
                $log->initAndWriteLog($this->dm->lang['DM_log_richtext']);
                break;
            case 'delete':
                $log->initAndWriteLog($this->dm->lang['DM_log_delete']);
                break;
            case 'dates':
                $log->initAndWriteLog($this->dm->lang['DM_log_dates']);
                break;
            case 'authors':
                $log->initAndWriteLog($this->dm->lang['DM_log_authors']);
                break;
        }
    }
}
