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
 * @uses modx 1.0.5, modx ddTools class 0.1, ManagerManager plugin 0.3.11
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

/**
 * mm_ddAutoFolders
 * @param $ddRoles {comma separated string} - ID ролей, к которым необходимо применить правило. [Optional; Default: '']
 * @param $ddTemplates {comma separated string} - ID шаблонов, к которым необходимо применить правило. [Optional; Default: '']
 * @param $ddParent {integer} - ID корневого родителя. [Required]
 * @param $ddDateSource {string} - Поле, откуда необходимо брать дату. [Optional; Default: 'pub_date']
 * @param $ddYearTpl {integer} - ID шаблона, который необходимо выставлять документам-годам. [Optional; Default: 0]
 * @param $ddMonthTpl {integer} - ID шаблона, который необходимо выставлять документам-месяцам. [Optional; Default: 0]
 * @param $ddYearPub {0; 1} - Надо ли публиковать документы-годы. [Optional; Default: 0]
 * @param $ddMonthPub {0; 1} - Надо ли публиковать документы-месяцы. [Optional; Default: 0]
 *
 * @version 1.0.1 (2012-02-15)
 *
 * При сохранении (событие OnBeforeDocFormSave) автоматически перемещает необходимый документ, основываясь на его дате (дата публикации, или любая дата в tv) в папку года и месяца. Если папки (документы) года и/или месяца ещё не созданы, они создадутся автоматически.
 *
 * @uses modx 1.0.5, modx ddTools class 0.1, ManagerManager plugin 0.3.11
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

function mm_ddAutoFolders($ddRoles = '', $ddTemplates = '', $ddParent = '', $ddDateSource = 'pub_date', $ddYearTpl = 0, $ddMonthTpl = 0, $ddYearPub = '0', $ddMonthPub = '0')
{
    global $modx, $modx_lang_attribute;
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

    //Дата
    $ddDate = [];

    //Если задано, откуда брать дату и это не дата публикации, пытаемся найти в tv`шках
    if ($ddDateSource && $ddDateSource !== 'pub_date') {
        //Получаем tv с датой для данного шаблона
        $dateTv = tplUseTvs($template, $ddDateSource);

        //Если tv удалось получить, такая tv есть и есть её значение
        $dateTvValue = $dateTv && $dateTv[0]['id'] ? ($tvVars[$dateTv[0]['id']] ?? false) : false;
        if ($dateTvValue) {
            //Если дата в юникс-времени
            if (is_numeric($dateTvValue)) {
                $ddDate['date'] = (int)$dateTvValue;
            } else {
                //Пытаемся преобразовать в unix-время
                $ddDate['date'] = strtotime($dateTvValue);
            }
        }
    } else {
        $ddDate['date'] = $docVars['pub_date'] ?? 0;
    }

    //Если не задана дата, выбрасываем
    if (empty($ddDate['date'])) {
        return;
    }

    //Псевдонимы родителей (какие должны быть)
    //Год в формате 4 цифры
    $ddDate['y'] = date('Y', $ddDate['date']);
    //Название месяца на английском
    $ddDate['m'] = strtolower(date('F', $ddDate['date']));
    //Порядковый номер месяца
    $ddDate['n'] = date('n', $ddDate['date']);

    //Если язык админки — русский
    if (strtolower($modx_lang_attribute) === 'ru') {
        //Все месяцы на русском
        $ruMonthes = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];

        //Название месяца на русском
        $ddDate['mTitle'] = $ruMonthes[$ddDate['n'] - 1];
    } else {
        //Просто запишем на английском
        $ddDate['mTitle'] = date('F', $ddDate['date']);
    }

    //Получаем список групп документов, к которым принадлежит текущий документ (пригодится при создании годов и месяцев)
    $docGroups = [];
    if (postv('chkalldocs') !== 'on') {
        foreach ((array)postv('docgroups', []) as $docGroup) {
            $docGroups[] = (int)$docGroup;
        }
    }

    //年・月のフォルダを作る（値は DB 操作の直前にエスケープする）
    $createDocument = function ($fields, $groups) {
        $fields['createdon'] = time();
        $fields['createdby'] = (int)evo()->getLoginUserID();
        //Если группы заданы, то это приватный документ
        if ($groups) {
            $fields['privatemgr'] = 1;
        }
        //Если надо публиковать, поставим дату публикации текущей
        if ($fields['published'] == 1) {
            $fields['pub_date'] = $fields['createdon'];
        }
        $id = db()->insert(db()->escape($fields), '[+prefix+]site_content');
        if (!$id) {
            return false;
        }
        foreach ($groups as $group) {
            db()->insert(
                db()->escape(['document_group' => (int)$group, 'document' => (int)$id]),
                '[+prefix+]document_groups'
            );
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

    //Получаем id нужного нам года
    $yearId = $findChild($ddParent, $ddDate['y']);
    $monthId = 0;
    if ($docId && $docId === $yearId) {
        return;
    }

    //Если нужный год существует
    if ($yearId) {
        //Проставим году нужные параметры
        $updateDocument($yearId, [
            'isfolder' => 1,
            'template' => $ddYearTpl,
            'published' => $ddYearPub
        ]);
        //Получаем id нужного нам месяца
        $monthId = $findChild($yearId, $ddDate['m']);
        if ($docId && $docId === $monthId) {
            return;
        }
        //Если нужный год не существует
    } else {
        //Создадим его
        $yearId = $createDocument([
            'pagetitle' => $ddDate['y'],
            'alias' => $ddDate['y'],
            'parent' => $ddParent,
            'isfolder' => 1,
            'template' => $ddYearTpl,
            //Да пусть будут тупо по году, сортироваться нормально зато будут
            'menuindex' => $ddDate['y'] - 2000,
            'published' => $ddYearPub
        ], $docGroups);
    }

    //         if (!$monthId && $yearId){
    //Если нужный месяц существует
    if ($monthId) {
        //Проставим месяцу нужные параметры
        $updateDocument($monthId, [
            'isfolder' => 1,
            'template' => $ddMonthTpl,
            'published' => $ddMonthPub
        ]);
        //Если нужный месяц не существует (на всякий случай проверим ещё и год)
    } else if ($yearId) {
        $monthId = $createDocument([
            'pagetitle' => $ddDate['mTitle'],
            'alias' => $ddDate['m'],
            'parent' => $yearId,
            'isfolder' => 1,
            'template' => $ddMonthTpl,
            //Для месяца выставляем menuindex в соответствии с его порядковым номером
            'menuindex' => $ddDate['n'] - 1,
            'published' => $ddMonthPub
        ], $docGroups);
    }

    //Ещё раз на всякий случай проверим, что с месяцем всё хорошо
    if ($monthId && $monthId != ($docVars['parent'] ?? 0)) {
        //本体の保存に反映させるため、params の doc_vars を書き換える
        $docVars['parent'] = (int)$monthId;
        $e->cm->setParam('doc_vars', $docVars);
    }
}
