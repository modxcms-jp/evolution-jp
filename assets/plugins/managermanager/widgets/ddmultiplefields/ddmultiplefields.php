<?php
/**
 * mm_ddMultipleFields
 * @param tvs {comma separated string} - ウィジェットを適用する TV の名前
 * @param roles {comma separated string} - 適用するロール。空ならすべて。既定は空
 * @param templates {comma separated string} - 適用するテンプレートの ID。空ならすべて。既定は空
 * @param columns {comma separated string} - 列の種類（field: 欄、text: テキスト、id: 一意の ID を持つ隠し欄、select: 値の一覧から選ぶ。columnsData を参照）。既定は field
 * @param columnsTitle {comma separated string} - 列の見出し。既定は空
 * @param colWidth {comma separated string} - 列の幅（1 つだけ指定してもよい）。既定は 180
 * @param splY {string} - 行の区切り。既定は '||'
 * @param splX {string} - 列の区切り。既定は '::'
 * @param imgW {integer} - プレビュー画像の最大の幅。既定は 300
 * @param imgH {integer} - プレビュー画像の最大の高さ。既定は 100
 * @param minRow {integer} - 行数の下限。既定は 0
 * @param maxRow {integer} - 行数の上限。既定は 0（無制限）
 * @param columnsData {separated string} - select 列の選択肢。JSON を || で区切る。既定は空
 *
 * @version 4.3.4 (2012-12-20)
 *
 * 1 つのドキュメントに任意の数の欄（TV）を足す。値は区切り文字でつないで 1 つの TV に保存する
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
        $columnsData = implode('||', $columnsData);
    }

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
