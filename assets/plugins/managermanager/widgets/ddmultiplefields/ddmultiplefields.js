var ddMultiple = {
baseUrl: "",
insertLabel: "",
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
            if (ddMultiple[id].columns[index] == "id"){
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
    //Делаем таблицу мульти-полей（元の欄への書き戻しは送信（保存・プレビュー）のときにまとめて行う）
    var table = ddMultiple.make("<table class=\"ddMultipleField\" id=\"" + id + "ddMultipleField\"></table>");
    target.appendChild(table);

    //Если заголовков хватает
    if (ddMultiple[id].columnsTitle.length == ddMultiple[id].columns.length){
        var text = "";
        //Создадим шапку
        ddMultiple[id].columnsTitle.forEach(function(title){
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
    ddMultiple[id].columns.forEach(function(type, key){
        if (!val[key]) val[key] = "";
        if (!ddMultiple[id].columnsTitle[key]) ddMultiple[id].columnsTitle[key] = "";
        if (!ddMultiple[id].colWidth[key] || ddMultiple[id].colWidth[key] == "") ddMultiple[id].colWidth[key] = ddMultiple[id].colWidth[key - 1];

        var col = ddMultiple.makeFieldCol(fieldBlock);

        //По умолчанию создаём поле как текстовое
        field = ddMultiple.makeText(val[key], ddMultiple[id].columnsTitle[key], ddMultiple[id].colWidth[key], col);

        //Если текущая колонка является полем
        if (type == "field"){
            ddMultiple[id].makeFieldFunction(id, col);

            //If is file or image
            if (ddMultiple[id].browseFuntion){
                //Create Attach browse button
                var button = ddMultiple.make("<input class=\"ddAttachButton\" type=\"button\" />");
                button.value = ddMultiple.insertLabel;
                field.after(button);
                button.addEventListener("click", function(){
                    ddMultiple[id].currentField = ddMultiple.siblings(button, ".ddField")[0] || false;
                    //選んだ値は元の欄で受け取り、ピッカーはこの行の値の場所で開く
                    ddMultiple[id].browseFuntion(id, ddMultiple[id].currentField ? ddMultiple[id].currentField.value : "");
                });
            }
        }else if (type == "id"){
            if (!field.value){
                field.value = (new Date).getTime();
            }

            MM.hide(col);
        }else if (type == "date"){
            field.remove();
            ddMultiple.makeDate(val[key], ddMultiple[id].columnsTitle[key], col);
        }else if (type == "textarea"){
            field.remove();
            ddMultiple.makeTextarea(val[key], ddMultiple[id].columnsTitle[key], ddMultiple[id].colWidth[key], col);
        }else if (type == "select"){
            field.remove();
            ddMultiple.makeSelect(val[key], ddMultiple[id].columnsTitle[key], ddMultiple[id].columnsData[key], ddMultiple[id].colWidth[key], col);
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

            url = (url != "" && url.search(/https?:\/\//i) == -1 && url.search(/^\//i) == -1) ? (ddMultiple.baseUrl + url) : url;

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
        var dataMas = null;
        try {
            dataMas = JSON.parse(data);
        } catch (e) {
            console.warn("mm_ddMultipleFields: columnsData を読めません", data);
        }
        //配列でもオブジェクトでも回す
        Object.keys(dataMas && typeof dataMas === "object" ? dataMas : {}).forEach(function(index){
            var option = document.createElement("option");
            option.value = dataMas[index][0];
            option.textContent = dataMas[index][1] ? dataMas[index][1] : dataMas[index][0];
            select.appendChild(option);
        });
    }
    if (value) select.value = value;
    fieldCol.appendChild(select);
    return select;
},
//Функция ничего не делает
makeNull: function(id, fieldCol){return false;},
//ページ共通の値を受け取る（ルールごとに呼ばれてよい）
configure: function(settings){
    ddMultiple.baseUrl = settings.baseUrl || "";
    ddMultiple.insertLabel = settings.insertLabel || "";
},
//元の欄をマルチフィールドにする（要素が無い・設定済みなら何もしない）
//settings: type（image・file・text・textarea）, columns, columnsTitle, columnsData, colWidth, splX, splY, imageStyle, minRow, maxRow
attach: function(id, settings){
    var original = document.getElementById(id);
    if (!original || ddMultiple[id]){
        return;
    }
    var type = settings.type;
    ddMultiple[id] = {
        splY: settings.splY,
        splX: settings.splX,
        columns: settings.columns.split(","),
        columnsTitle: settings.columnsTitle.split(","),
        columnsData: settings.columnsData.split("||"),
        colWidth: settings.colWidth.split(","),
        imageStyle: settings.imageStyle,
        minRow: settings.minRow,
        maxRow: settings.maxRow,
        makeFieldFunction: type === "image" ? ddMultiple.makeImage : ddMultiple.makeNull,
        browseFuntion: type === "image" ? window.BrowseServer : (type === "file" ? window.BrowseFileServer : false)
    };

    ddMultiple.ids.push(id);

    //元の欄は隠し、値の受け渡しだけに使う
    original.classList.remove("imageField");
    original.classList.add("originalField");
    MM.hide(original);

    //ファイルピッカーで選んだ値が元の欄に入ったら、編集中の行に反映する
    original.addEventListener("change", function(){
        ddMultiple.updateField(id);
    });

    //画像・ファイルの元の参照ボタンは隠す
    if (ddMultiple[id].browseFuntion){
        var next = original.nextElementSibling;
        if (next && next.matches("input[type=button]")) MM.hide(next);
    }

    ddMultiple.init(id, original.value, original.parentElement);
},
//Маскирует кавычки
maskQuoutes: function(text){
    text = text.replace(/"/g, "&#34;");
    text = text.replace(/'/g, "&#39;");
    return text;
}
};
//ページの準備ができてから動かす
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
    //new FormData(form)（保存・プレビュー）とネイティブの送信のどちらでも、送る値を最新にする
    form.addEventListener("formdata", function(e){
        for (var i = 0, len = ddMultiple.ids.length; i < len; i++){
            ddMultiple.updateTv(ddMultiple.ids[i]);
            var original = document.getElementById(ddMultiple.ids[i]);
            if (original && original.name && e.formData.has(original.name)){
                e.formData.set(original.name, original.value);
            }
        }
    });
}
});
