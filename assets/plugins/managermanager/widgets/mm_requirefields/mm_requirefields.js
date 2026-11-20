// mm_requireFields の画面側の処理。PHP から mmRequire.run() で、必須にする欄の設定を渡される
var mmRequire = {
    // 欄の行の見出し
    titles: function (els) {
        var found = [];
        els.forEach(function (el) {
            var title = Evo.fieldPart(el, "title");
            if (title && found.indexOf(title) === -1) { found.push(title); }
        });
        return found;
    },
    // 最初の欄の値（複数選択は選んだ値をつないだもの。欄が無ければ空）
    fieldValue: function (el) {
        if (!el) { return ""; }
        if (el.matches("select[multiple]")) {
            return Array.from(el.selectedOptions).map(function (o) { return o.value; }).join(",");
        }
        return el.value;
    },
    // 欄にフォーカスしたら印を外す
    unmark: function () {
        this.classList.remove("mmRequired");
    },
    // settings: {fixDateType: bool, selectors: [CSS セレクタ, ...]}
    run: function (settings) {
        var self = this;
        MM.addStyle(".mmRequired { background-image: none !important; background-color: #ff9999 !important; } .requiredIcon { color: #ff0000; font-weight: bold; margin-left: 3px; cursor: help; }");
        var requiredHTML = "<span class=\"requiredIcon\" title=\"Required\">*</span>";
        // 公開日・公開終了日は 1.0.2 では input に type が無いので足す
        if (settings.fixDateType) {
            MM.all("#pub_date, #unpub_date").forEach(function (el) { el.type = "text"; });
        }
        settings.selectors.forEach(function (selector) {
            self.titles(MM.all(selector)).forEach(function (span) { span.insertAdjacentHTML("beforeend", requiredHTML); });
        });
        var form = document.getElementById("mutate");
        if (!form) { return; }
        // 空の欄があれば保存を止める（以前の return false と同じく、既定の動作と伝播の両方を止める）
        form.addEventListener("submit", function (e) {
            var errors = [];
            settings.selectors.forEach(function (selector) {
                var els = MM.all(selector);
                if (String(self.fieldValue(els[0])).trim() === "") {
                    errors.push(self.titles(els).map(function (span) { return span.textContent; }).join("").replace("*", ""));
                    els.forEach(function (el) {
                        el.classList.add("mmRequired");
                        el.addEventListener("focus", self.unmark);
                    });
                }
            });
            if (errors.length > 0) {
                var errMsg = errors.length + " required fields are missing:\n\n ";
                for (var i = 0; i < errors.length; i++) {
                    errMsg += " - " + errors[i] + " \n";
                }
                errMsg += " \nPlease correct the indicated fields.";
                alert(errMsg);
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }
};
