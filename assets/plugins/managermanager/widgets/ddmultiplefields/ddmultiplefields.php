<?php
/**
 * mm_ddMultipleFields
 * @param tvs {comma separated string} - Имена TV, для которых необходимо применить виджет.
 * @param roles {comma separated string} - Роли, для которых необходимо применить виждет, пустое значение — все роли. По умолчанию: ''.
 * @param templates {comma separated string} - Id шаблонов, для которых необходимо применить виджет, пустое значение — все шаблоны. По умолчанию: ''.
 * @param coloumns {comma separated string} - Типы колонок (field — колонка типа поля, text — текстовая колонка, id — скрытое поле с уникальным идентификатором, select — список с выбором значений (см. coloumnsData)). По умолчанию: 'field'.
 * @param coloumnsTitle {comma separated string} - Названия колонок. По умолчанию: ''.
 * @param colWidth {comma separated string} - Ширины колонок (может быть задана одна ширина). По умолчанию: 180;
 * @param splY {string} - Разделитель между строками. По умолчанию: '||'.
 * @param splX {string} - Разделитель между колонками. По умолчанию: '::'.
 * @param imgW {integer} - Максимальная ширина превьюшки. По умолчанию: 300.
 * @param imgH {integer} - Максимальная высота превьюшки. По умолчанию: 100.
 * @param minRow {integer} - Минимальное количество строк. По умолчанию: 0.
 * @param maxRow {integer} - Максимальное количество строк. По умолчанию: 0 (без лимита).
 * @param coloumnsData {separated string} - Список возможных значений для полей в формате json, через ||. По умолчанию: ''.
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

    global $mm_current_page, $_lang;

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $widgetDir = MODX_BASE_URL . 'assets/plugins/managermanager/widgets/ddmultiplefields/';

    if ($columnsData) {
        $columnsDataTemp = explode('||', $columnsData);
        $columnsData = [];
        foreach ($columnsDataTemp as $value) {
            //Евалим знение и записываем результат или исходное значени
            try {
                $eval = eval($value);
            } catch (\Throwable $e) {
                $eval = null; // not PHP code: use the value as is
            }
            $columnsData[] = $eval ? json_encode($eval) : $value;
        }
        //Сливаем в строку, что бы передать на клиент
        $columnsData = implode('||', $columnsData);
    }

    //Стиль превью изображения
    $stylePrewiew = "max-width:{$imgW}px; max-height:{$imgH}px; margin: 4px 0; cursor: pointer;";

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

    $output = '';
    $output .= "// ---------------- mm_ddMultipleFields :: Begin ------------- \n";
    //General functions
    $output .= '
//行の並べ替えは管理画面共通の dragdrop-sort.js を使う（同じ URL は includeJs が 1 回にまとめる）
' . includeJs(MODX_MANAGER_URL . 'media/script/dragdrop-sort.js') . '
//Проверяем на всякий случай (если вдруг вызывается пару раз)
if (!ddMultiple){
' . includeCss($widgetDir . 'ddmultiplefields.css') . '
var ddMultiple = {
datePickerOffset: ' . evo()->config('datepicker_offset') . ',
datePickerFormat: "' . evo()->config('datetime_format') . '" + " hh:mm:00",
ids: [],
trimValue: function(value){
    return String(value == null ? "" : value).trim();
},
//HTML から要素を 1 つ作る（<tr> や <td> も余分な tbody を作らずにそのまま作れるよう template で組み立てる）
make: function(html){
    var tpl = document.createElement("template");
    tpl.innerHTML = html;
    return tpl.content.firstElementChild;
},
//変更を知らせる。素の change にすると、画面を開いた直後に未保存の扱いになるので独自の名前にする
fire: function(el){
    if (el){
        el.dispatchEvent(new CustomEvent("ddmultiple:change", {bubbles: true}));
    }
},
//マルチフィールドの行の一覧
rows: function(id){
    return MM.all("#" + id + "ddMultipleField .ddFieldBlock");
},
//行の中の最後の列
lastCol: function(row){
    var cols = row ? row.querySelectorAll(".ddFieldCol") : [];
    return cols.length ? cols[cols.length - 1] : null;
},
//el の兄弟のうち sel に一致するもの
siblings: function(el, sel){
    return Array.from(el.parentElement ? el.parentElement.children : []).filter(function(c){
        return c !== el && c.matches(sel);
    });
},
//Обновляет мульти-поле, берёт значение из оригинального поля
updateField: function(id){
    //Если есть текущее поле
    if (ddMultiple[id].currentField){
        var original = document.getElementById(id);
        //Задаём значение текущему полю (берём у оригинального поля), запускаем событие изменения
        ddMultiple[id].currentField.value = ddMultiple.trimValue(original ? original.value : "");
        ddMultiple.fire(ddMultiple[id].currentField);
        //Забываем текущее поле (ибо уже обработали)
        ddMultiple[id].currentField = false;
    }
},
//Обновляет оригинальное поле TV, собирая данные по мульти-полям
updateTv: function(id){
    var masRows = [];

    //Перебираем все строки
    ddMultiple.rows(id).forEach(function(row){
        var masCol = [],
            id_field = {index: false, val: false, field: false};

        //Перебираем все колонки, закидываем значения в массив
        row.querySelectorAll(".ddField").forEach(function(field, index){
            //Если поле с типом id
            if (ddMultiple[id].coloumns[index] == "id"){
                id_field.index = index;
                id_field.field = field;

                //Сохраняем значение поля
                id_field.val = field.value;
                //Если значение пустое, то генерим
                if (id_field.val == "") id_field.val = (new Date).getTime();

                //Обнуляем значение
                field.value = "";
            }
            //Собираем значения строки в массив
            masCol.push(ddMultiple.trimValue(field.value));
        });

        var col = masCol.join(ddMultiple[id].splX);
        if (col.length != ((masCol.length - 1) * ddMultiple[id].splX.length)){
            //Проверяем было ли поле с id
            if (id_field.index !== false){
                //Записываем значение в поле
                id_field.field.value = id_field.val;
                //Обновляем значение в массиве
                masCol[id_field.index] = id_field.val;
                //Пересобираем строку
                col = masCol.join(ddMultiple[id].splX);
            }
            masRows.push(col);
        }
    });

    //Записываем значение в оригинальное поле
    var original = document.getElementById(id);
    if (original){
        original.value = ddMultiple.maskQuoutes(masRows.join(ddMultiple[id].splY));
    }
},
//Инициализация
//Принимает id оригинального поля, его значения и родителя поля
init: function(id, val, target){
    //Делаем таблицу мульти-полей（元の欄への書き戻しは保存のときにまとめて行う）
    var table = ddMultiple.make("<table class=\"ddMultipleField\" id=\"" + id + "ddMultipleField\"></table>");
    target.appendChild(table);

    //Если заголовков хватает
    if (ddMultiple[id].coloumnsTitle.length == ddMultiple[id].coloumns.length){
        var text = "";
        //Создадим шапку
        ddMultiple[id].coloumnsTitle.forEach(function(title){
            text += "<th>" + title + "</th>";
        });

        table.appendChild(ddMultiple.make("<tr><th></th>" + text + "<th></th></tr>"));
    }

    //Делаем новые мульти-поля
    var arr = val.split(ddMultiple[id].splY);

    //Проверяем на максимальное и минимальное количество строк
    if (ddMultiple[id].maxRow && arr.length > ddMultiple[id].maxRow) arr.length = ddMultiple[id].maxRow;
    else if (ddMultiple[id].minRow && arr.length < ddMultiple[id].minRow) arr.length = ddMultiple[id].minRow;

    for (var i = 0, len = arr.length; i < len; i++){
        //В случае, если размер массива был увеличен по minRow, значением будет undefined, посему зафигачим пустую строку
        ddMultiple.makeFieldRow(id, arr[i] || "");
    }

    //Создаём кнопку +
    ddMultiple.makeAddButton(id);

    //Добавляем возможность перетаскивания
    table.setAttribute("data-sortable", "true");
    table.setAttribute("data-sortable-items", ".ddFieldBlock");
    table.setAttribute("data-sortable-handle", ".ddSortHandle");
    table.addEventListener("modxsortable:end", function(){
        ddMultiple.fire(table);
        ddMultiple.moveAddButton(id);
    });
    window.MODXSortable.prepareList(table);
    //Запускаем обновление, если были ограничения
    if (ddMultiple[id].maxRow || ddMultiple[id].minRow) ddMultiple.fire(table);

},
//Функция создания строки
//Принимает id и данные строки
makeFieldRow: function(id, val){
    //Проверяем привышает ли количество строк максимальное
    if (ddMultiple[id].maxRow && ddMultiple.rows(id).length >= ddMultiple[id].maxRow) return;
    var fieldBlock = ddMultiple.make("<tr class=\"ddFieldBlock " + id + "ddFieldBlock\" ><td class=\"ddSortHandle\"><div></div></td></tr>");
    var table = document.getElementById(id + "ddMultipleField");
    if (table) table.appendChild(fieldBlock);

    //Разбиваем переданное значение на колонки
    val = ddMultiple.maskQuoutes(val).split(ddMultiple[id].splX);

    var field;

    //Перебираем колонки
    ddMultiple[id].coloumns.forEach(function(type, key){
        if (!val[key]) val[key] = "";
        if (!ddMultiple[id].coloumnsTitle[key]) ddMultiple[id].coloumnsTitle[key] = "";
        if (!ddMultiple[id].colWidth[key] || ddMultiple[id].colWidth[key] == "") ddMultiple[id].colWidth[key] = ddMultiple[id].colWidth[key - 1];

        var col = ddMultiple.makeFieldCol(fieldBlock);

        //По умолчанию создаём поле как текстовое
        field = ddMultiple.makeText(val[key], ddMultiple[id].coloumnsTitle[key], ddMultiple[id].colWidth[key], col);

        //Если текущая колонка является полем
        if (type == "field"){
            ddMultiple[id].makeFieldFunction(id, col);

            //If is file or image
            if (ddMultiple[id].browseFuntion){
                //Create Attach browse button
                var button = ddMultiple.make("<input class=\"ddAttachButton\" type=\"button\" value=\"' . $_lang['insert'] . '\" />");
                field.after(button);
                button.addEventListener("click", function(){
                    ddMultiple[id].currentField = ddMultiple.siblings(button, ".ddField")[0] || false;
                    ddMultiple[id].browseFuntion(id);
                });
            }
        }else if (type == "id"){
            if (!field.value){
                field.value = (new Date).getTime();
            }

            MM.hide(col);
        }else if (type == "date"){
            field.remove();
            ddMultiple.makeDate(val[key], ddMultiple[id].coloumnsTitle[key], col);
        }else if (type == "textarea"){
            field.remove();
            ddMultiple.makeTextarea(val[key], ddMultiple[id].coloumnsTitle[key], ddMultiple[id].colWidth[key], col);
        }else if (type == "select"){
            field.remove();
            ddMultiple.makeSelect(val[key], ddMultiple[id].coloumnsTitle[key], ddMultiple[id].coloumnsData[key], ddMultiple[id].colWidth[key], col);
        }

    });

    //Create DeleteButton
    ddMultiple.makeDeleteButton(id, ddMultiple.makeFieldCol(fieldBlock));

    //При изменении（入力欄の素の change を表の変更として知らせる）
    fieldBlock.querySelectorAll(".ddField").forEach(function(input){
        input.addEventListener("change", function(){
            ddMultiple.fire(input.closest(".ddMultipleField"));
        });
    });

    //Специально для полей, содержащих изображения необходимо инициализировать
    fieldBlock.querySelectorAll(".ddFieldCol").forEach(function(col){
        if (col.querySelector(".ddField_image")){
            col.querySelectorAll(".ddField").forEach(function(input){
                ddMultiple.fire(input);
            });
        }
    });

    return fieldBlock;
},
//Создание колонки поля
makeFieldCol: function(fieldRow){
    var col = ddMultiple.make("<td class=\"ddFieldCol\"></td>");
    fieldRow.appendChild(col);
    return col;
},
//Make delete button
makeDeleteButton: function(id, fieldCol){
    var button = ddMultiple.make("<input class=\"ddDeleteButton\" type=\"button\" value=\"×\" />");
    fieldCol.appendChild(button);
    button.addEventListener("click", function(){
        //Проверяем на минимальное количество строк
        if (ddMultiple[id].minRow && ddMultiple.rows(id).length <= ddMultiple[id].minRow){
            return;
        }

        var par = button.closest(".ddFieldBlock"),
            table = button.closest(".ddMultipleField");
        if (!par){
            return;
        }
        //Отчистим значения полей
        par.querySelectorAll(".ddField").forEach(function(input){
            input.value = "";
        });

        //最後の 1 行は消さずに値を空にし、プレビューを隠す（元の作りのままページ内のすべてのプレビューが対象）
        if (ddMultiple.rows(id).length == 1)
            MM.hide(".ddField_image");
        //Если больше одной строки, то можно удалить текущую строчку（元はフェードアウトの後に消していた）
        if (ddMultiple.siblings(par, ".ddFieldBlock").length > 0){
            //Если контейнер имеет кнопку добалвения, перенесём её
            var prev = par.previousElementSibling;
            if (par.querySelector(".ddAddButton") && prev && prev.matches(".ddFieldBlock")){
                ddMultiple.moveAddButton(id, prev);
            }
            par.remove();
        }
        //Инициализируем событие изменения
        ddMultiple.fire(table);
    });
},
//Функция создания кнопки +, вызывается при инициализации
makeAddButton: function(id){
    var button = ddMultiple.make("<input class=\"ddAddButton\" type=\"button\" value=\"+\" />");
    var rows = ddMultiple.rows(id);
    var col = ddMultiple.lastCol(rows[rows.length - 1]);
    if (col) col.appendChild(button);
    button.addEventListener("click", function(){
        //Вешаем на кнопку создание новой строки（行の上限に達していれば何もしない）
        var row = ddMultiple.makeFieldRow(id, "");
        if (!row){
            return;
        }
        var last = ddMultiple.lastCol(row);
        if (last) last.appendChild(button);
        ddMultiple.fire(button.closest(".ddMultipleField"));
    });
},
//Перемещение кнопки +
moveAddButton: function(id, target){
    //Если не передали, куда вставлять, вставляем в самый конец
    if (!target){
        var rows = ddMultiple.rows(id);
        target = rows[rows.length - 1];
    }

    //Находим кнопку добавления и переносим куда надо
    var button = document.querySelector("#" + id + "ddMultipleField .ddAddButton");
    var col = ddMultiple.lastCol(target);
    if (button && col) col.appendChild(button);
},
//Make text field
makeText: function(value, title, width, fieldCol){
    var field = ddMultiple.make("<input type=\"text\" value=\"" + value + "\" title=\"" + title + "\" style=\"width:" + width + "px;\" class=\"ddField\" />");
    fieldCol.appendChild(field);
    return field;
},
//Make image field
makeImage: function(id, fieldCol){
    // Create a new preview and Attach a browse event to the picture, so it can trigger too
    var box = ddMultiple.make("<div class=\"ddField_image\"><img src=\"\" style=\"" + ddMultiple[id].imageStyle + "\" /></div>");
    fieldCol.appendChild(box);
    MM.hide(box);
    var img = box.querySelector("img");
    img.addEventListener("click", function(){
        var attach = fieldCol.querySelector(".ddAttachButton");
        if (attach) attach.click();
    });
    img.addEventListener("load", function(){
        //Удаление дерьма, блеать (превьюшка, оставленная от виджета showimagetvs)
        var old = document.getElementById(id + "PreviewContainer");
        if (old) old.remove();
    });

    //Находим поле, привязываем события（利用者の入力の change と、値を入れたときの ddmultiple:change）
    fieldCol.querySelectorAll(".ddField").forEach(function(field){
        var update = function(){
            var url = field.value;

            url = (url != "" && url.search(/https?:\/\//i) == -1 && url.search(/^\//i) == -1) ? ("' . MODX_BASE_URL . '" + url) : url;

            var previews = ddMultiple.siblings(field, ".ddField_image");
            //If field not empty
            if (url != ""){
                //Show preview
                MM.show(previews);
                previews.forEach(function(preview){
                    preview.querySelectorAll("img").forEach(function(image){
                        image.src = url;
                    });
                });
            }else{
                //Hide preview
                MM.hide(previews);
            }
        };
        field.addEventListener("change", update);
        field.addEventListener("ddmultiple:change", update);
    });
},
//Make date field
makeDate: function(value, title, fieldCol){
    //name нужен для DatePicker`а
    var field = ddMultiple.make("<input type=\"text\" title=\"" + title + "\" class=\"ddField DatePicker\" name=\"ddMultipleDate\" />");
    field.value = value;
    fieldCol.appendChild(field);

    return field;
},
//Make textarea field
makeTextarea: function(value, title, width, fieldCol){
    var field = ddMultiple.make("<textarea title=\"" + title + "\" style=\"width:" + width + "px;\" class=\"ddField\">" + value + "</textarea>");
    fieldCol.appendChild(field);
    return field;
},
//Функция создания списка
makeSelect: function(value, title, data, width, fieldCol){
    var select = document.createElement("select");
    select.className = "ddField";
    if (data){
        var dataMas = JSON.parse(data);
        var options = "";
        //配列でもオブジェクトでも回す
        Object.keys(dataMas).forEach(function(index){
            options += "<option value=\""+ dataMas[index][0] +"\">" + (dataMas[index][1] ? dataMas[index][1] : dataMas[index][0]) +"</option>";
        });
        select.insertAdjacentHTML("beforeend", options);
    }
    if (value) select.value = value;
    fieldCol.appendChild(select);
    return select;
},
//Функция ничего не делает
makeNull: function(id, fieldCol){return false;},
//Маскирует кавычки
maskQuoutes: function(text){
    text = text.replace(/"/g, "&#34;");
    text = text.replace(/\'/g, "&#39;");
    return text;
}
};
//ルールの処理がすべて終わった後に動かす
Evo.ready(function(){
//If we have imageTVs on this page, modify the SetUrl function so it triggers a "change" event on the URL field
if (typeof(SetUrl) != "undefined") {
    var OldSetUrl = SetUrl; // Copy the existing Image browser SetUrl function
    SetUrl = function(url, width, height, alt){    // Redefine it to also tell the preview to update
        var c = null;
        if(lastFileCtrl) {
            c = document.mutate[lastFileCtrl];
        } else if(lastImageCtrl) {
            c = document.mutate[lastImageCtrl];
        }
        //同じ name の欄が複数あれば最初の 1 つ
        if (c && c.nodeType !== 1) c = c[0];
        OldSetUrl(url, width, height, alt);
        // 素の change を起こす（管理画面の未保存の検知と、元の欄の change のリスナーに届く）
        if (c) c.dispatchEvent(new Event("change", {bubbles: true}));
    };
}
//Самбмит главной формы
var form = document.getElementById("mutate");
if (form){
    form.addEventListener("submit", function(){
        for (var i = 0, len = ddMultiple.ids.length; i < len; i++){
            ddMultiple.updateTv(ddMultiple.ids[i]);
        }
    });
}
});
}
';

    foreach ($tvsMas as $tv) {
        if ($tv['type'] === 'image') {
            $browseFuntion = 'BrowseServer';
            $makeFieldFunction = 'makeImage';
        } else if ($tv['type'] === 'file') {
            $browseFuntion = 'BrowseFileServer';
            $makeFieldFunction = 'makeNull';
        } else {
            $browseFuntion = 'false';
            $makeFieldFunction = 'makeNull';
        }
        $output .= '
//元の欄をマルチフィールドにする（要素が無ければ何もしない）
(function(original){
if (!original) { return; }
var id = original.id;//id оригинального поля

//Проверим на существование (возникали какие-то непонятные варианты, при которых два раза вызов был)
if (!ddMultiple[id]){
    //Инициализация текущего объекта с правилами
    ddMultiple[id] = {
        splY: ' . js_literal($splY) . ',
        splX: ' . js_literal($splX) . ',
        coloumns: ' . js_literal($columns) . '.split(","),
        coloumnsTitle: ' . js_literal($columnsTitle) . '.split(","),
        coloumnsData: ' . js_literal($columnsData) . '.split("||"),
        colWidth: ' . js_literal($colWidth) . '.split(","),
        imageStyle: ' . js_literal($stylePrewiew) . ',
        minRow: ' . (int)$minRow . ',
        maxRow: ' . (int)$maxRow . ',
        makeFieldFunction: ddMultiple.' . $makeFieldFunction . ',
        browseFuntion: ' . $browseFuntion . '
    };

    ddMultiple.ids.push(id);

    //Скрываем оригинальное поле
    original.classList.remove("imageField");
    original.classList.add("originalField");
    MM.hide(original);

    //Назначаем обработчик события при изменении (необходимо для того, чтобы после загрузки фотки адрес вставлялся в нужное место)
    original.addEventListener("change", function(){
        //Обновляем текущее мульти-поле
        ddMultiple.updateField(original.id);
    });

    //Если это файл или изображение, cкрываем оригинальную кнопку
    if (ddMultiple[id].browseFuntion){
        var next = original.nextElementSibling;
        if (next && next.matches("input[type=button]")) MM.hide(next);
    }

    //Создаём мульти-поле
    ddMultiple.init(id, original.value, original.parentElement);
}
})(document.getElementById("tv' . $tv['id'] . '"));
        ';
    }

    $output .= "\n// ---------------- mm_ddMultipleFields :: End -------------";

    event()->output($output . "\n");
} // end of widget
