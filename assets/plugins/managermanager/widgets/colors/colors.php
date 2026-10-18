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
            var m = jQuery.trim(value).match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
            if (!m) {
                return "";
            }
            var hex = m[1].toLowerCase();
            if (hex.length === 3) {
                hex = hex.replace(/(.)/g, "$1$1");
            }
            return "#" + hex;
        }
    ';

    // Go through each of the fields supplied
    foreach ($fields as $tv) {
        $output .= parseText('
            // ----------- Color widget for  [+tv_id+]  --------------
            (function() {
                var $text = jQuery("#[+tv_id+]");
                var $picker = jQuery(\'<input type="color" id="colorpicker[+tv_id+]" style="margin-left:4px;vertical-align:middle;" />\');
                $text.css("background-image","none").after($picker);
                if ($text.val() == "") {
                    $text.val("[+default+]");
                }
                var syncPicker = function() {
                    var hex = mmColorsToHex6($text.val());
                    if (hex) {
                        $picker.val(hex);
                    }
                };
                syncPicker();
                $text.on("input change", syncPicker);
                $picker.on("input change", function() {
                    // mark the document as dirty, or the value wont be saved
                    $text.val($picker.val()).trigger("change");
                });
            })();
            documentDirty = false;
            ', [
                'tv_id' => $mm_fields[$tv]['fieldname'],
                'default' => $default
            ]
        );
    }

    event()->output($output . "\n");
}
