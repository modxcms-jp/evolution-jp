<?php

/**
 * mm_ddAutoFolders
 * @param $ddRoles {comma separated string} - List of role IDs this should be applied to. Leave empty (or omit) for all roles. [Optional; Default: '']
 * @param $ddTemplates {comma separated string} - List of template IDs this should be applied to. Leave empty (or omit) for all templates. [Optional; Default: '']
 * @param $ddParent {integer} - Ultimate parent ID (parent of the years). [Required]
 * @param $ddDateSource {string} - Name of template variable which contains the date. [Optional; Default: 'pub_date']
 * @param $ddYearTpl {integer} - Template ID for documents of year. [Optional; Default: 0]
 * @param $ddMonthTpl {integer} - Template ID for documents of month. [Optional; Default: 0]
 * @param $ddYearPub {0; 1} - Would the documents of year published? [Optional; Default: 0]
 * @param $ddMonthPub {0; 1} - Would the documents of month published? [Optional; Default: 0]
 *
 * @version 1.0.1 (2012-02-15)
 *
 * Automatically move documents (OnBeforeDocFormSave event) based on their date (publication date; any date in tv) into folders of year and month (like 2012/02/). If folders (documents) of year and month doesn`t exist they are created automatically OnBeforeDocFormSave event.
 *
 * @uses modx 1.0.5, ManagerManager plugin 0.3.11
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

/**
 * mm_ddAutoFolders
 * @param $ddRoles {comma separated string} - ルールを適用するロールの ID。省略時はすべて
 * @param $ddTemplates {comma separated string} - ルールを適用するテンプレートの ID。省略時はすべて
 * @param $ddParent {integer} - 年のドキュメントを置く親の ID（必須）
 * @param $ddDateSource {string} - 日付を読む欄。既定は pub_date
 * @param $ddYearTpl {integer} - 年のドキュメントに設定するテンプレートの ID
 * @param $ddMonthTpl {integer} - 月のドキュメントに設定するテンプレートの ID
 * @param $ddYearPub {0; 1} - 年のドキュメントを公開するか
 * @param $ddMonthPub {0; 1} - 月のドキュメントを公開するか
 *
 * @version 1.0.1 (2012-02-15)
 *
 * @uses modx 1.0.5, ManagerManager plugin 0.3.11
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

function mm_ddAutoFolders($ddRoles = '', $ddTemplates = '', $ddParent = '', $ddDateSource = 'pub_date', $ddYearTpl = 0, $ddMonthTpl = 0, $ddYearPub = '0', $ddMonthPub = '0')
{
    global $modx;
    $e = &$modx->event;

    //$ddParent is required
    if (!is_numeric($ddParent) || $e->name != 'OnBeforeDocFormSave' || !useThisRule($ddRoles, $ddTemplates)) {
        return;
    }

    //保存される値は、イベントの params（doc_vars・tv_vars）から読み書きする
    $docVars = $e->params['doc_vars'] ?? null;
    if (!is_array($docVars)) {
        return;
    }
    $tvVars = $e->params['tv_vars'] ?? [];
    $template = (int)($docVars['template'] ?? 0);

    $ddDate = [];

    if ($ddDateSource && $ddDateSource !== 'pub_date') {
        $dateTv = tplUseTvs($template, $ddDateSource);

        $dateTvValue = $dateTv && $dateTv[0]['id'] ? ($tvVars[$dateTv[0]['id']] ?? false) : false;
        if ($dateTvValue) {
            if (is_numeric($dateTvValue)) {
                $ddDate['date'] = (int)$dateTvValue;
            } else {
                $ddDate['date'] = strtotime($dateTvValue);
            }
        }
    } else {
        $ddDate['date'] = $docVars['pub_date'] ?? 0;
    }

    if (empty($ddDate['date'])) {
        return;
    }

    $ddDate['y'] = date('Y', $ddDate['date']);
    $ddDate['m'] = strtolower(date('F', $ddDate['date']));
    $ddDate['n'] = date('n', $ddDate['date']);

    $ddDate['mTitle'] = date('F', $ddDate['date']);

    // 文書グループは、フォルダを実際に作るとき（$createDocument の中）に初めて求める（文書の保存と同じ判定は manager()->setNewDocumentGroups()。文書グループの権限は 1 回だけ求めて年・月で使い回す）
    // OnBeforeDocFormSave は save_resource.processor.php のほか qm.inc.php も呼ぶが、後者は doc_vars が無く途中で抜ける

    //年・月のフォルダを作る（値は DB 操作の直前にエスケープする）
    $access = null;
    $createDocument = function ($fields) use (&$access) {
        $fields['createdon'] = time();
        $fields['createdby'] = (int)evo()->getLoginUserID();
        if ($fields['published'] == 1) {
            $fields['pub_date'] = $fields['createdon'];
        }
        $id = db()->insert(db()->escape($fields), '[+prefix+]site_content');
        if (!$id) {
            return false;
        }
        //文書グループは保存と同じ規則で付ける（親は $fields['parent']）
        $access = $access ?: manager()->documentGroupAccess();
        manager()->setNewDocumentGroups($id, (int)$fields['parent'], $access);
        //非公開の印は文書本体の保存と同じく、実際の所属から求める
        if (evo()->config('use_udperms')) {
            manager()->refreshDocumentPrivateFlags($id);
        }
        return $id;
    };
    $updateDocument = function ($id, $fields) {
        return db()->update(db()->escape($fields), '[+prefix+]site_content', sprintf('id=%d', (int)$id));
    };

    //保存する文書の ID（新規では予測 ID が入るため、編集のときだけ取る）
    $docId = (($e->params['mode'] ?? '') === 'upd') ? (int)($e->params['id'] ?? 0) : 0;
    //最上位の親が自分自身・自分の子孫・存在しない場合は、フォルダを作る前に何もしない
    if (manager()->invalidParentMessage($docId ? [$docId] : [], $ddParent) !== '') {
        return;
    }

    //親の ID と alias で子のフォルダを探す
    $findChild = function ($parent, $alias) {
        $rs = db()->select(
            'id',
            '[+prefix+]site_content',
            sprintf("parent=%d AND alias='%s' AND deleted=0", (int)$parent, db()->escape($alias)),
            '',
            1
        );
        return (int)db()->getValue($rs);
    };

    $yearId = $findChild($ddParent, $ddDate['y']);
    $monthId = 0;
    if ($docId && $docId === $yearId) {
        return;
    }

    if ($yearId) {
        $updateDocument($yearId, [
            'isfolder' => 1,
            'template' => $ddYearTpl,
            'published' => $ddYearPub
        ]);
        $monthId = $findChild($yearId, $ddDate['m']);
        if ($docId && $docId === $monthId) {
            return;
        }
    } else {
        $yearId = $createDocument([
            'pagetitle' => $ddDate['y'],
            'alias' => $ddDate['y'],
            'parent' => $ddParent,
            'isfolder' => 1,
            'template' => $ddYearTpl,
            'menuindex' => $ddDate['y'] - 2000,
            'published' => $ddYearPub
        ]);
    }

    //         if (!$monthId && $yearId){
    if ($monthId) {
        $updateDocument($monthId, [
            'isfolder' => 1,
            'template' => $ddMonthTpl,
            'published' => $ddMonthPub
        ]);
    } else if ($yearId) {
        $monthId = $createDocument([
            'pagetitle' => $ddDate['mTitle'],
            'alias' => $ddDate['m'],
            'parent' => $yearId,
            'isfolder' => 1,
            'template' => $ddMonthTpl,
            'menuindex' => $ddDate['n'] - 1,
            'published' => $ddMonthPub
        ]);
    }

    if ($monthId && $monthId != ($docVars['parent'] ?? 0)) {
        //本体の保存に反映させるため、params の doc_vars を書き換える
        $docVars['parent'] = (int)$monthId;
        $e->cm->setParam('doc_vars', $docVars);
    }
}
