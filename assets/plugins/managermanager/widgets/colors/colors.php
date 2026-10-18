<?php
/**
 * mm_widget_colors
 * @version 1.2 (2026-10-18)
 *
 * Adds a color selection widget to the specified TVs.
 * Uses the browser's native <input type="color">.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_widget_colors/1.1
 *
 * @copyright 2012
 */

function mm_widget_colors($fields, $default = '#ffffff', $roles = '', $templates = '')
{
    global $mm_fields, $mm_current_page;

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    // if we've been supplied with a string, convert it into an array
    $fields = makeArray($fields);

    // Does this page's template use any of these TVs? If not, quit.
    if (!tplUseTvs($mm_current_page['template'], $fields)) {
        return;
    }

    // #rgb / #rrggbb -> #rrggbb, otherwise ''
    $output = '
        function mmColorsToHex6(value) {
            const m = value.trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
            if (!m) {
                return "";
            }
            let hex = m[1].toLowerCase();
            if (hex.length === 3) {
                hex = hex.replace(/(.)/g, "$1$1");
            }
            return "#" + hex;
        }
    ';

    // Go through each of the fields supplied
    foreach ($fields as $tv) {
        if (!isset($mm_fields[$tv])) {
            continue;
        }
        $output .= parseText('
            // ----------- Color widget for  [+tv_id+]  --------------
            (() => {
                const text = document.getElementById("[+tv_id+]");
                if (!text) {
                    return;
                }
                const picker = document.createElement("input");
                picker.type = "color";
                picker.id = "colorpicker[+tv_id+]";
                picker.style.marginLeft = "4px";
                picker.style.verticalAlign = "middle";
                text.style.backgroundImage = "none";
                text.after(picker);
                if (text.value === "") {
                    text.value = [+default+];
                }
                const syncPicker = () => {
                    const hex = mmColorsToHex6(text.value);
                    if (hex) {
                        picker.value = hex;
                    }
                };
                syncPicker();
                text.addEventListener("input", syncPicker);
                text.addEventListener("change", syncPicker);
                picker.addEventListener("input", () => {
                    text.value = picker.value;
                    // mark the document as dirty, or the value wont be saved
                    text.dispatchEvent(new Event("change", { bubbles: true }));
                });
            })();
            documentDirty = false;
            ', [
                'tv_id' => $mm_fields[$tv]['fieldname'],
                'default' => js_literal($default)
            ]
        );
    }

    event()->output($output . "\n");
}
