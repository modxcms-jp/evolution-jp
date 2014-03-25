<?php
/**
 * mm_ddAutoFolders
 * @version 1.1.1 (2014-03-19)
 * 
 * @desc Automatically move documents (OnBeforeDocFormSave event) based on their date (publication date; any date in tv) into folders of year and month (like 2012/02/). If folders (documents) of year and month doesn`t exist they are created automatically OnBeforeDocFormSave event.
 * 
 * @uses ManagerManager plugin 0.5
 * 
 * @param $roles {comma separated string} - List of role IDs this should be applied to. Leave empty (or omit) for all roles. Default: ''.
 * @param $templates {comma separated string} - List of template IDs this should be applied to. Leave empty (or omit) for all templates. Default: ''.
 * @param $yearsParents {comma separated string} - IDs of ultimate parents (parents of the years). @required
 * @param $dateSource {string} - Name of template variable which contains the date. Default: 'pub_date'.
 * @param $yearTpl {integer} - Template ID for documents of year. Default: 0.
 * @param $monthTpl {integer} - Template ID for documents of month. Default: 0.
 * @param $yearPublished {0; 1} - Would the documents of year published? Default: 0.
 * @param $monthPublished {0; 1} - Would the documents of month published? Default: 0.
 * @param $numericMonth {boolean} - Numeric aliases for month documents. Default: false.
 * 
 * @link http://code.divandesign.biz/modx/mm_ddautofolders/1.1.1
 * 
 * @copyright 2014, DivanDesign
 * http://www.DivanDesign.biz
 */

function mm_ddAutoFolders($roles = '', $templates = '', $yearsParents = '', $dateSource = 'pub_date', $yearTpl = 0, $monthTpl = 0, $yearPublished = '0', $monthPublished = '0', $numericMonth = false){
	global $modx, $pub_date, $parent, $template, $document_groups, $tmplvars, $modx_lang_attribute;
	$e = &$modx->Event;
	
	//$yearsParents is required
	if ($yearsParents != '' && $e->name == 'OnBeforeDocFormSave' && useThisRule($roles, $templates)){
		//Функция аналогична методу «$modx->getParentIds» за исключением того, что родитель = 0 тоже выставляется
		function getParentIds($id){
			global $modx;
			
			$parents = $modx->getParentIds($id);
			
			//Если текущего id нет в массиве, значит его родитель = 0
			if (!isset($parents[$id])){
				$parents[$id] = 0;
			//Если текущий документ есть, а его родителя нет, значит родитель родителя = 0
			}else if(!isset($parents[$parents[$id]])){
				$parents[$parents[$id]] = 0;
			}
			
			return $parents;
		}
		
		//Получаем всех родителей текущего документа (или его родителя, если это новый документ)
		$allParents = getParentIds(is_numeric($e->params['id']) ? $e->params['id'] : $parent);
		
		$yearsParents = makeArray($yearsParents);
		
		//Перебираем переданных родителей
		foreach($yearsParents as $key => $val){
			//Если текущий документ не принадлежит к переданному родителю, значит этот родитель лишний
			if (!isset($allParents[$val])){
				unset($yearsParents[$key]);
			}
		}
		
		//Если остался хоть один родитель (а остаться должен только один)
		if (count($yearsParents) > 0){
			//Сбрасываем ключи
			$yearsParents = array_values($yearsParents);
		//Если документ не относится ни к одному переданному родителю, то ничего делать не нужно
		}else{
			return;
		}
		
		//Текущее правило
		$rule = array();
		//Дата
		$ddDate = array();
		
		//Если задано, откуда брать дату и это не дата публикации, пытаемся найти в tv`шках
		if ($dateSource && $dateSource != 'pub_date'){
			//Получаем tv с датой для данного шаблона
			$dateTv = tplUseTvs($template, $dateSource);
			
			//Если tv удалось получить, такая tv есть и есть её значение
			if ($dateTv && $dateTv[0]['id'] && $tmplvars[$dateTv[0]['id']] && $tmplvars[$dateTv[0]['id']][1]){
				//Если дата в юникс-времени
				if (is_numeric($tmplvars[$dateTv[0]['id']][1])){
					$ddDate['date'] = $tmplvars[$dateTv[0]['id']][1];
				}else{
					//Пытаемся преобразовать в unix-время
					$ddDate['date'] = strtotime($tmplvars[$dateTv[0]['id']][1]);
				}
				//Пытаемся преобразовать в unix-время
				if (!is_numeric($tmplvars[$dateTv[0]['id']][1])){
					$ddDate['date'] = strtotime($tmplvars[$dateTv[0]['id']][1]);
				}
			}
		}else{
			$ddDate['date'] = $pub_date;
		}
		
		//Если не задана дата, выбрасываем
		if (!$ddDate['date']){return;}
		
		//Псевдонимы родителей (какие должны быть)
		//Год в формате 4 цифры
		$ddDate['y'] = date('Y', $ddDate['date']);
		//Псевдоним месяца (порядковый номер номер с ведущим нолём или название на английском)
		$ddDate['m'] = $numericMonth ? date('m', $ddDate['date']) : strtolower(date('F', $ddDate['date']));
		//Порядковый номер месяца
		$ddDate['n'] = date('n', $ddDate['date']);
		
		//Если язык админки — русский
		if (strtolower($modx_lang_attribute) == 'ru'){
			//Все месяцы на русском
			$ruMonthes = array('Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь');
			
			//Название месяца на русском
			$ddDate['mTitle'] = $ruMonthes[$ddDate['n'] - 1];
		}else{
			//Просто запишем на английском
			$ddDate['mTitle'] = date('F', $ddDate['date']);
		}
		
		//Получаем список групп документов, к которым принадлежит текущий документ (пригодится при создании годов и месяцев)
		$docGroups = preg_replace('/,\d*/', '', $document_groups);
		
		$yearId = 0;
		$monthId = 0;
		
		//Получаем годы (непосредственных детей корневого родителя)
		$years = ddTools::getDocumentChildrenTVarOutput($yearsParents[0], array('id'), false, 'menuindex', 'ASC', '', 'alias');
		
		if (isset($years[$ddDate['y']])){
			//Получаем id нужного нам года
			$yearId = $years[$ddDate['y']]['id'];
		}
		
		//Если нужный год существует
		if ($yearId != 0){
			//Проставим году нужные параметры
			ddTools::updateDocument($yearId, array(
				'isfolder' => 1,
				'template' => $yearTpl,
				'published' => $yearPublished
			));
			//Получаем месяцы (непосредственных детей текущего года)
			$months = ddTools::getDocumentChildrenTVarOutput($yearId, array('id'), false, 'menuindex', 'ASC', '', 'alias');
			if (isset($months[$ddDate['m']])){
				//Получаем id нужного нам месяца
				$monthId = $months[$ddDate['m']]['id'];
			}
		//Если нужный год не существует
		}else{
			//Создадим его
			$yearId = ddTools::createDocument(array(
				'pagetitle' => $ddDate['y'],
				'alias' => $ddDate['y'],
				'parent' => $yearsParents[0],
				'isfolder' => 1,
				'template' => $yearTpl,
				//Года запихиваем тупо в самый конец
// 				'menuindex' => count($years),
				//Да пусть будут тупо по году, сортироваться нормально зато будут
				'menuindex' => $ddDate['y'] - 2000,
				'published' => $yearPublished
			), $docGroups);
		}
		
		//Если нужный месяц существует
		if ($monthId != 0){
			//Проставим месяцу нужные параметры
			ddTools::updateDocument($monthId, array(
				'isfolder' => 1,
				'template' => $monthTpl,
				'published' => $monthPublished
			));
			//Если нужный месяц не существует (на всякий случай проверим ещё и год)
		}else if($yearId){
			$monthId = ddTools::createDocument(array(
				'pagetitle' => $ddDate['mTitle'],
				'alias' => $ddDate['m'],
				'parent' => $yearId,
				'isfolder' => 1,
				'template' => $monthTpl,
				//Для месяца выставляем menuindex в соответствии с его порядковым номером
				'menuindex' => $ddDate['n'] - 1,
				'published' => $monthPublished
			), $docGroups);
		}
		
		//Ещё раз на всякий случай проверим, что с месяцем всё хорошо
		if ($monthId && $monthId != $parent){
			$parent = $monthId;
		}
	}
}
?>