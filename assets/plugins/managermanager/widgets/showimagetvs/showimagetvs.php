<?php

/**
 * mm_widget_showimagetvs
 * @version 1.1 (2012-11-13)
 *
 * Shows a preview of image TVs.
 * Emulates showimagestv plugin, which is not compatible with ManagerManager.
 *
 * @uses ManagerManager plugin 0.4.
 *
 * @link http://code.divandesign.biz/modx/mm_widget_showimagetvs/1.1
 *
 * @copyright 2012
 */

function mm_widget_showimagetvs($tvs = '', $w = 300, $h = 100, $thumbnailerUrl = '', $roles = '', $templates = '')
{
    global $mm_current_page;

    if (event()->name !== 'OnDocFormRender' || !useThisRule($roles, $templates)) {
        return;
    }

    $output = '';

    if ($w || $h) {
        if (!$w) $w = 300;
        if (!$h) $h = (int)$w * 0.3;
        $style = sprintf(
            "'float:left;max-width:%dpx; max-height:%dpx; margin: 4px 0; cursor: pointer;'",
            $w,
            $h
        );
    } else {
        // JS の式として埋め込むので、空でも文字列にする
        $style = "''";
    }

    // Does this page's template use any image TVs? If not, quit now!
    if ($tvs) {
        $tvs = tplUseTvs($mm_current_page['template'], $tvs);
    } else {
        $tvs = tplUseTvs($mm_current_page['template'], $tvs, 'image');
    }
    if (!$tvs) {
        return;
    }

    $output .= "//  -------------- mm_widget_showimagetvs :: Begin ------------- \n";

    // 値が変わったとき（change）と最初の 1 回にプレビューを作り直す。前回の値は dataset.lastvalue に置く
    $tpl1 = '
// Adding preview for tv[+id+]
(function (field) {
if (!field) { return; }
field.classList.add("imageField");
const update = function(){
    // Get the new URL
    var url = field.value;

    field.dataset.lastvalue = url;

    field.classList.add("imageField");
    url = (url != "" && url.search(/^@[a-z]+/i) == -1) ? url : url.replace(new RegExp(/^@[a-z]+/i), "");
    url = (url != "" && url.search(/https?:\\/\\//i) == -1 && url.search(/^\\//i) == -1) ? ("[+base_url+]" + url) : url;' . "\n";

    $tpl2 = '
    // Remove the old preview tv[+id+]
    const old = document.getElementById("tv[+id+]PreviewContainer");
    if (old) { old.remove(); }

    if (url != "" && !url.match("/.*::.*/")){
        // Create a new preview（以前の .parents("td").append() と同じく、祖先の td すべてに足す）
        // 値を HTML として解釈しないよう、要素を作って src に入れる
        MM.parents(field, "td").forEach(function (td) {
            const box = document.createElement("div");
            box.className = "tvimage";
            box.id = "tv[+id+]PreviewContainer";
            const img = document.createElement("img");
            img.src = url;
            img.setAttribute("style", [+style+]);
            img.id = "tv[+id+]Preview";
            box.appendChild(img);
            td.appendChild(box);
        });

        // Attach a browse event to the picture, so it can trigger too
        const preview = document.getElementById("tv[+id+]Preview");
        if (preview) {
            preview.addEventListener("click", function(){
                BrowseServer("tv[+id+]");
            });
        }
    }
};
field.addEventListener("change", update);
update();
})(document.getElementById("tv[+id+]"));

            ' . "\n";
    // Go through each TV
    $ph = [
        'base_url' => MODX_BASE_URL,
        'thumbnailerUrl' => $thumbnailerUrl,
        'w' => $w,
        'h' => $h
    ];
    foreach ($tvs as $tv) {
        $ph['id'] = $tv['id'];
        $output .= evo()->parseText($tpl1, $ph);

        if ($thumbnailerUrl) {
            $output .= evo()->parseText(
                'url = [+thumbnailer+] + "?src=" + encodeURIComponent(url) + "&w=[+w+]&h=[+h+]";' . "\n",
                ['thumbnailer' => js_literal($thumbnailerUrl), 'w' => (int)$w, 'h' => (int)$h]
            );
        }
        $ph['style'] = $style;
        $output .= evo()->parseText($tpl2, $ph);
    }

    $output .= '

// Monitor the image TVs for changes
checkImageTVupdates = function(){
    MM.all(".imageField").forEach(function(el){
        if (el.value != el.dataset.lastvalue){
            // 素の change を起こす（管理画面の未保存の検知に届く）
            MM.fire(el, "change");
        }
    });
}

setInterval(checkImageTVupdates, 250);

//  -------------- mm_widget_showimagetvs :: End -------------

';
    event()->output($output . "\n");
}
