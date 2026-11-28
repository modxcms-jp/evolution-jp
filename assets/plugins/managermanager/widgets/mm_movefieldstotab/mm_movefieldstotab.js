// mm_moveFieldsToTab の画面側の処理。PHP から mmMoveFields.forTab() で移し先のタブごとの操作を作り、欄ごとに呼び出す
var mmMoveFields = {
    // tab: 移す先のタブ。ruleHtml: 移した行の後ろに足す区切りの行（空なら足さない）。tab が無ければ null
    forTab: function (tab, ruleHtml) {
        if (!tab) { return null; }
        // 移す先は、タブの直下の data-moved-fields の付いた表。無ければタブの直下の最初の表（mm_createTab のタブなど）
        var table = tab.querySelector(":scope > [data-moved-fields]") || tab.querySelector(":scope > table");
        // 以前の appendTo と同じく、集合の順に表の末尾へ移す（表が無ければ動かさない）。
        // 表に直下の tbody があれば、以前と同じくその中へ入れる
        function moveRows(rows) {
            if (!table) { return []; }
            var body = table.querySelector(":scope > tbody") || table;
            rows.forEach(function (r) { body.appendChild(r); });
            return rows;
        }
        // それぞれの次の兄弟が tr なら、その tr
        function nextRows(rows) {
            return rows.map(function (r) { return r.nextElementSibling; }).filter(function (n) { return n && n.matches("tr"); });
        }
        // それぞれの後ろに区切りの行を足す（表の直下でも <tr> のまま入るよう template で組み立てる）
        function addRule(rows) {
            if (ruleHtml === "") { return; }
            rows.forEach(function (r) {
                var t = document.createElement("template");
                t.innerHTML = ruleHtml;
                r.after(t.content);
            });
        }
        // 行を移し、後ろの区切りの行を消し、区切りを足す
        function moveFieldRows(toMove) {
            var helps = [];
            nextRows(toMove).forEach(function (n) { helps.push.apply(helps, n.querySelectorAll('td[colspan="2"]')); });
            MM.parents(helps, "tr").forEach(function (r) { r.remove(); }); // Get rid of line after, if there is one
            var movedTV = moveRows(toMove); // Move the table row
            addRule(movedTV); // Insert a rule after
        }
        return {
            // 本文の欄（content）
            content: function () {
                MM.all("#content_body").forEach(function (el) { tab.appendChild(el); });
                MM.hide("#content_header");
            },
            // menuindex・hidemenu・show_in_menu は同じ行
            menuindex: function () {
                var rows = MM.all(Evo.fieldRow("menuindex"));
                // 後ろの区切りの行だけを消す（次の行が親などの欄のこともある）
                nextRows(rows).filter(function (r) { return !r.hasAttribute("data-field") && r.querySelector(':scope > td[colspan="2"]'); }).forEach(function (r) { r.remove(); });
                addRule(moveRows(rows));
            },
            weblink: function () {
                moveFieldRows(MM.all(Evo.fieldRow("weblink")));
            },
            // 欄を selector で探して移す
            field: function (selector) {
                moveFieldRows(MM.fieldRows(selector));
                // This prevents an IE6/7 bug where the moved field would not be visible until you switched tabs
                MM.all(Evo.fieldPart(document.querySelector(selector), "value")).forEach(function (td) { td.removeAttribute("style"); });
            }
        };
    }
};
