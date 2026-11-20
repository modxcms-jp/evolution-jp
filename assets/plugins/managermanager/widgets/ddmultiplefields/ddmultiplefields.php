<?php
/**
 * mm_ddMultipleFields
 * @param tvs {comma separated string} - Имена TV, для которых необходимо применить виджет.
 * @param roles {comma separated string} - Роли, для которых необходимо применить виждет, пустое значение — все роли. По умолчанию: ''.
 * @param templates {comma separated string} - Id шаблонов, для которых необходимо применить виджет, пустое значение — все шаблоны. По умолчанию: ''.
 * @param columns {comma separated string} - Типы колонок (field — колонка типа поля, text — текстовая колонка, id — скрытое поле с уникальным идентификатором, select — список с выбором значений (см. columnsData)). По умолчанию: 'field'.
 * @param columnsTitle {comma separated string} - Названия колонок. По умолчанию: ''.
 * @param colWidth {comma separated string} - Ширины колонок (может быть задана одна ширина). По умолчанию: 180;
 * @param splY {string} - Разделитель между строками. По умолчанию: '||'.
 * @param splX {string} - Разделитель между колонками. По умолчанию: '::'.
 * @param imgW {integer} - Максимальная ширина превьюшки. По умолчанию: 300.
 * @param imgH {integer} - Максимальная высота превьюшки. По умолчанию: 100.
 * @param minRow {integer} - Минимальное количество строк. По умолчанию: 0.
 * @param maxRow {integer} - Максимальное количество строк. По умолчанию: 0 (без лимита).
 * @param columnsData {separated string} - Список возможных значений для полей в формате json, через ||. По умолчанию: ''.
 *
 * @version 4.3.4 (2012-12-20)
 *
 * Позволяет добавлять произвольное количество полей (TV) к одному документу (записывается в одно через разделители).
 *
 * @link http://code.divandesign.biz/modx/mm_ddmultiplefields/4.3.4
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

function mm_ddMultipleFields($tvs = '', $roles = '', $templates = '', $columns = 'field', $columnsTitle = '', $colWidth = '180', $splY = '||', $splX = '::', $imgW = 300, $imgH = 100, $minRow = 0, $maxRow = 0, $columnsData = '')
{

    global $mm_current_page;

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $widgetDir = MODX_BASE_URL . 'assets/plugins/managermanager/widgets/ddmultiplefields/';

    if ($columnsData) {
        $columnsDataTemp = explode('||', $columnsData);
        $columnsData = [];
        foreach ($columnsDataTemp as $value) {
            $value = trim($value);
            //JSON はそのまま渡す（PHP として評価すると "$" や "\u…" の意味が変わる）
            if (is_array(json_decode($value, true))) {
                $columnsData[] = $value;
                continue;
            }
            //PHP の書き方（[...]・array(...)・return ...）だけを、末尾の ; の有無によらず値を返す形にして評価する
            $eval = null;
            if (preg_match('/^(\[|array\s*\(|return\b)/i', $value)) {
                $expr = rtrim(preg_replace('/^return\b/i', '', $value), " \t\r\n;");
                try {
                    $eval = eval("return {$expr};");
                } catch (\Throwable $e) {
                    $eval = null; // not PHP code: use the value as is
                }
            }
            $columnsData[] = $eval ? json_encode($eval) : $value;
        }
        //Сливаем в строку, что бы передать на клиент
        $columnsData = implode('||', $columnsData);
    }

    //Стиль превью изображения
    $stylePrewiew = "max-width:" . (int)$imgW . "px; max-height:" . (int)$imgH . "px; margin: 4px 0; cursor: pointer;";

    // Which template is this page using?
    if (isset($mm_current_page['template'])) {
        $page_template = $mm_current_page['template'];
    } else {
        // If no content is set, it's likely we're adding a new page at top level.
        // So use the site default template. This may need some work as it might interfere with a default template set by MM?
        $page_template = evo()->config('default_template');
    }

    $tvsMas = [];
    // Does this page's template use any image or file or text TVs?
    $types = explode(',', 'image,file,text,textarea');
    foreach ($types as $type) {
        $tvsTemp = tplUseTvs($page_template, $tvs, $type);
        if ($tvsTemp) {
            foreach ($tvsTemp as $v) {
                $v['type'] = $type;
                $tvsMas[] = $v;
            }
        }
    }

    if (count($tvsMas) == 0) {
        return;
    }

    $output = "// ---------------- mm_ddMultipleFields :: Begin ------------- \n";
    //行の並べ替えは管理画面共通の dragdrop-sort.js を使う（同じ URL は includeJs が 1 回にまとめる）
    $output .= includeJs(MODX_MANAGER_URL . 'media/script/dragdrop-sort.js');
    $output .= includeJs($widgetDir . 'ddmultiplefields.js');
    $output .= includeCss($widgetDir . 'ddmultiplefields.css');
    $output .= 'ddMultiple.configure(' . js_value([
        'baseUrl' => MODX_BASE_URL,
        'insertLabel' => lang('insert'),
    ]) . ');' . "\n";

    $settings = js_value([
        'columns' => (string)$columns,
        'columnsTitle' => (string)$columnsTitle,
        'columnsData' => (string)$columnsData,
        'colWidth' => (string)$colWidth,
        'splX' => (string)$splX,
        'splY' => (string)$splY,
        'imageStyle' => $stylePrewiew,
        'minRow' => (int)$minRow,
        'maxRow' => (int)$maxRow,
    ]);
    foreach ($tvsMas as $tv) {
        $output .= sprintf(
            'ddMultiple.attach(%s, Object.assign({type: %s}, %s));' . "\n",
            js_literal('tv' . $tv['id']),
            js_literal($tv['type']),
            $settings
        );
    }

    $output .= "\n// ---------------- mm_ddMultipleFields :: End -------------";

    event()->output($output . "\n");
} // end of widget
