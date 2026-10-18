<?php
// 出力は 3 つに分ける。
// 1. ルールを mm_run に包んで定義だけする <script>
// 2. ルールの実行中に includeJs() / includeCss() で登録された <script src> / <link>
// 3. Evo.ready(mm_run)。<script src> の読み込みの後に動くので、ルールの中で読み込んだファイルをすぐ使える
// CSS は head に足す（accessdenied などがフォームごと消しても見た目が残るように）
global $mm_includes;

$mm_js = 'assets/plugins/managermanager/mm.js';
$e->output('
    <!-- ManagerManager Plugin :: ' . $mm_version . ' -->
    <!-- This document is using template: ' . (int)$mm_current_page['template'] . ' -->
    <!-- You are logged into the following role: ' . hsc($mm_current_page['role']) . ' -->

    <script src="' . hsc(MODX_BASE_URL . $mm_js . '?' . filemtime(MODX_BASE_PATH . $mm_js)) . '"></script>
    <script>
    // シェルで画面を移ったとき、ルールの定義が構文エラーで動かなくても、前の画面の mm_run を動かさない
    window.mm_run = null;
    </script>
    <script charset="' . $modx->config['modx_charset'] . '">
    var mm_lastTab = "tabGeneral";
    var mm_sync_field_count = 0;
    var synch_field = new Array();

var mm_run = function(){
        // Lets handle errors nicely...
    try {
    ');

// ルールを実行する。includeJs() / includeCss() はこの間だけ URL を登録する
$mm_includes = ['js' => [], 'css' => []];
$e->output($this->make_changes($config_chunk));
$includes = $mm_includes;
$mm_includes = null;

// Close it off
$e->output('
            // Misc tidying up

            // General tab table container is too narrow for receiving TVs -- make it a bit wider
            MM.all("div#tabGeneral table").forEach(function (table) {
                table.width = "100%";
            });

            // if template variables containers are empty, remove their section
            if (MM.all("div.tmplvars input, div.tmplvars select, div.tmplvars textarea, div.tmplvars button").length === 0) {
                MM.all("div.tmplvars").forEach(function (el) {
                    MM.hide(el);    // Still contains an empty table and some dividers
                    const prev = el.previousElementSibling;
                    if (prev && prev.tagName === "DIV") {
                        MM.hide(prev);
                    }
                });
            }

            // If template category is empty, hide the optgroup
            MM.all("#field_template optgroup").forEach(function (group) {
                const visible = Array.from(group.querySelectorAll("option")).some(function (option) {
                    return getComputedStyle(option).display !== "none";
                });
                if (!visible) {
                    MM.hide(group);
                }
            });

    }catch(e){
            // If theres an error, fail nicely
            alert("ManagerManager: An error has occurred: " + e.name + " - " + e.message);
    }finally{
            // Whatever happens, hide the loading mask（mm.js が読めなくても隠せるよう、素の DOM で）
            var mask = document.getElementById("loadingmask");
            if (mask) mask.style.display = "none";
        }
    };
    </script>
');

foreach ($includes['js'] as $url) {
    $e->output('<script src="' . hsc($url) . '"></script>' . "\n");
}

$e->output('
    <script>
    [' . implode(',', array_map('js_literal', $includes['css'])) . '].forEach(function (href) {
        const exists = Array.from(document.head.querySelectorAll("link[rel=stylesheet]")).some(function (link) {
            return link.getAttribute("href") === href;
        });
        if (!exists) {
            document.head.appendChild(Object.assign(document.createElement("link"), {rel: "stylesheet", type: "text/css", href: href}));
        }
    });
    if (typeof mm_run === "function") {
        Evo.ready(mm_run);
    }
    </script>
    <!-- ManagerManager Plugin :: End -->
');
