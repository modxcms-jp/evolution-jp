<?php
/**
 * mm_requireFields
 * @version 1.2 (2013-02-11)
 *
 * Make fields required. Currently works with text fields only.
 * In the future perhaps this could deal with other elements.
 * Originally version by Jelle Jager AKA TobyL - Make fields required
 * Updated by ncrossland to utilise simpler field handline of MM 0.3.5+; bring jQuery code into line; add indication to required fields
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_requirefields/1.2
 *
 * @copyright 2013
 */

function mm_requireFields($fields, $roles = '', $templates = '')
{
    global $mm_fields, $mm_current_page, $modx;
    $e = &$modx->event;

    // if the current page is being edited by someone in the list of roles, and uses a template in the list of templates
    if ($e->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

// if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    if (count($fields) == 0) return;

    $output = "//  -------------- mm_requireFields :: Begin ------------- \n";

    $output .= '(function () {
    MM.addStyle(".mmRequired { background-image: none !important; background-color: #ff9999 !important; } .requiredIcon { color: #ff0000; font-weight: bold; margin-left: 3px; cursor: help; }");
    const requiredHTML = "<span class=\"requiredIcon\" title=\"Required\">*</span>";
    // 欄の親の td の直前の td にある見出し（this will be easier in Evo 1.1 with more semantic code）
    function titles(els) {
        const found = [];
        els.forEach(function (el) {
            const td = el.parentElement;
            const prev = td && td.matches("td") ? td.previousElementSibling : null;
            if (!prev || !prev.matches("td")) { return; }
            prev.querySelectorAll(":scope > span.mutate-field-title").forEach(function (span) {
                if (found.indexOf(span) === -1) { found.push(span); }
            });
        });
        return found;
    }
    // 最初の欄の値（複数選択は選んだ値をつないだもの。欄が無ければ空）
    function fieldValue(el) {
        if (!el) { return ""; }
        if (el.matches("select[multiple]")) {
            return Array.from(el.selectedOptions).map(function (o) { return o.value; }).join(",");
        }
        return el.value;
    }
    // Add an event so the hilight is removed upon focussing
    function unmark() {
        this.classList.remove("mmRequired");
    }
';

    $submit_js = '';
    $load_js = '';

    foreach ($fields as $field) {
        //ignore for now
        switch ($field) {
            // fields for which this doesn't make sense - in my opinion anyway :)
            case 'keywords':
            case 'metatags':
            case 'hidemenu':
            case 'which_editor':
            case 'template':
            case 'menuindex':
            case 'show_in_menu':
            case 'parent':
            case 'is_folder':
            case 'is_richtext':
            case 'log':
            case 'searchable':
            case 'cacheable':
            case 'clear_cache':
            case 'content_type':
            case 'content_dispo':
                $output .= '';
                break;

            // Pub/unpub dates don't have a type attribute on their input tag in 1.0.2, so add this. Won't do any harm to other versions
            case 'pub_date':
            case 'unpub_date':
                $load_js .= '
    MM.all("#pub_date, #unpub_date").forEach(function (el) { el.type = "text"; });
';
            // no break, because we want to do the things below too.

            // Ones that follow the regular pattern
            default:
                if (!isset($mm_fields[$field])) {
                    break;
                }
                //if it's tv & it's not used in current template
                if ($mm_fields[$field]['tv'] && tplUseTvs($mm_current_page['template'], $field) === false) {
                    //Go to next field
                    break;
                }

                // What type is this field?
                // fieldname の \\[\\] は CSS のエスケープなので外し、属性値を引用して組み立てる
                $fieldname = str_replace('\\', '', $mm_fields[$field]['fieldname']);

                // What selector should we use for this fieldtype?
                switch ($mm_fields[$field]['fieldtype']) {
                    case 'textarea':
                        $selector = sprintf('textarea[name="%s"]', $fieldname);
                        break;

                    case 'input': // If it's an input, we only want to do something if it's a text field
                        $selector = sprintf('input[type="text"][name="%s"]', $fieldname);
                        break;

                    case 'select':
                        $selector = sprintf('select[name="%s"]', $fieldname);
                        break;

                    default:  // all other input types, do nothing
                        $selector = '';
                        break;
                }

                // If we've found something we want to use
                if (!empty($selector)) {
                    // 空なら、見出しを errors に足し、欄に印を付ける
                    $submit_js .= '
        {
            const els = MM.all(' . js_literal($selector) . ');
            if (String(fieldValue(els[0])).trim() === "") {
                // Add the label to the errors array
                errors.push(titles(els).map(function (span) { return span.textContent; }).join("").replace("*", ""));
                els.forEach(function (el) {
                    el.classList.add("mmRequired");
                    el.addEventListener("focus", unmark);
                });
            }
        }
';

                    // Add an indicator this is required
                    $load_js .= '
    titles(MM.all(' . js_literal($selector) . ')).forEach(function (span) { span.insertAdjacentHTML("beforeend", requiredHTML); });
';
                }
                break;
        }
    }

    // 空の欄があれば保存を止める（以前の return false と同じく、既定の動作と伝播の両方を止める）
    $output .= $load_js . '
    const form = document.getElementById("mutate");
    if (!form) { return; }
    form.addEventListener("submit", function (e) {
        const errors = [];
' . $submit_js . '
        if (errors.length > 0) {
            let errMsg = errors.length + " required fields are missing:\n\n ";
            for (let i = 0; i < errors.length; i++) {
                errMsg += " - " + errors[i] + " \n";
            }
            errMsg += " \nPlease correct the indicated fields.";

            alert(errMsg);
            e.preventDefault();
            e.stopPropagation();
        }
    });
})();
';

    $output .= "//  -------------- mm_requireFields :: End ------------- \n";

    $e->output($output . "\n");
}
