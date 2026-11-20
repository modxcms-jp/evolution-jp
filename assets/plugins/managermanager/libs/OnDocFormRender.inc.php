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

    <script src="' . hsc(site_asset(MODX_BASE_URL . $mm_js)) . '"></script>
    <script>
    // シェルで画面を移ったとき、ルールの定義が構文エラーで動かなくても、前の画面の mm_run を動かさない
    window.mm_run = null;
    </script>
    <script charset="' . config('modx_charset') . '">
    var mm_lastTab = "tabGeneral";

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
    MM.tidyUp();
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
    try {
        MM.loadCss(' . js_value($includes['css']) . ');
    } finally {
        // mm.js が読めなくても mm_run は動かし、覆いを隠す
        if (typeof mm_run === "function") {
            Evo.ready(mm_run);
        }
    }
    </script>
    <!-- ManagerManager Plugin :: End -->
');
