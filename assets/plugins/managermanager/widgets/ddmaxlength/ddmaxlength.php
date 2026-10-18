<?php
/**
 * mm_ddMaxLength
 * @version 1.0.1 (2012-01-13)
 *
 * Позволяет ограничить количество вводимых символов в TV.
 *
 * @copyright 2012, DivanDesign
 * http://www.DivanDesign.ru
 */

function mm_ddMaxLength($tvs = '', $roles = '', $templates = '', $length = 150)
{

    global $modx, $content;
    $e = &$modx->Event;

    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = '';

    $base_url = $modx->config['base_url'];

    // Which template is this page using?
    if (isset($content['template'])) {
        $page_template = $content['template'];
    } else {
        // If no content is set, it's likely we're adding a new page at top level.
        // So use the site default template. This may need some work as it might interfere with a default template set by MM?
        $page_template = $modx->config['default_template'];
    }

    // Does this page's template use any image or file or text TVs?
    $tvs = tplUseTvs($page_template, $tvs, 'text,textarea');
    if ($tvs == false) {
        return;
    }


    $output .= "// ---------------- mm_ddMaxLength :: Begin ------------- \n";
    //General functions
    $output .= includeJs($base_url . 'assets/plugins/managermanager/widgets/ddmaxlength/ddmaxlength.js');
    $output .= includeCss($base_url . 'assets/plugins/managermanager/widgets/ddmaxlength/ddmaxlength.css');

    foreach ($tvs as $tv) {
        $output .= '
MM.all("#tv' . $tv['id'] . '").forEach(function (field) {
    field.classList.add("ddMaxLengthField");
    field.parentElement.insertAdjacentHTML("beforeend", "<div class=\"ddMaxLengthCount\"><span></span></div>");
    ddMaxLength(field, {
        max: ' . $length . ',
        containerSelector: "div.ddMaxLengthCount span",
        warningClass: "maxLenghtWarning"
    });
});
        ';
    }

    // 残りが負の欄があれば保存を止める（以前の return false と同じく、既定の動作と伝播の両方を止める）
    $output .= '
(function () {
    const form = document.getElementById("mutate");
    if (!form) { return; }
    form.addEventListener("submit", function (e) {
        const ddErrors = [];
        MM.all("div.ddMaxLengthCount span").forEach(function (span) {
            const box = span.parentElement ? span.parentElement.closest(".ddMaxLengthCount") : null;
            const fields = box && box.parentElement ? MM.all(".ddMaxLengthField", box.parentElement) : [];
            if (parseInt(span.textContent) < 0) {
                fields.forEach(function (field) {
                    field.classList.add("maxLenghtErrorField");
                    field.addEventListener("focus", function () {
                        fields.forEach(function (f) { f.classList.remove("maxLenghtErrorField"); });
                    });
                });
                // 欄の祖先の行にある、最初の列の項目名（文書の順につなぐ）
                const titles = [];
                MM.parents(fields, "tr").forEach(function (tr) {
                    MM.all(":scope td:first-child .mutate-field-title", tr).forEach(function (t) {
                        if (titles.indexOf(t) === -1) { titles.push(t); }
                    });
                });
                titles.sort(function (a, b) {
                    return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
                });
                ddErrors.push(titles.map(function (t) { return t.textContent; }).join(""));
            }
        });

        if (ddErrors.length > 0) {
            alert("Некорректно заполнены поля: " + ddErrors.join(","));
            e.preventDefault();
            e.stopPropagation();
        }
    });
})();
    ';

    $output .= "\n// ---------------- mm_ddMaxLength :: End -------------";

    $e->output($output . "\n");
} // end of widget
