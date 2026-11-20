// ルールで欄を並べ替えている間、フォームを覆う #loadingmask の大きさを決める。
// mm.js はまだ読まれていないので素の DOM で書く。
// 覆いは画面を開くたびに作られるので、読み込まれるたびに動かす（登録は残さない）。
// 隠すのは OnDocFormRender.inc.php の mm_run の最後。
(function () {
    const mask = document.getElementById("loadingmask");
    if (!mask) {
        return;
    }
    Object.assign(mask.style, {
        width: "100%",
        height: document.body.offsetHeight + "px",
        position: "absolute",
        zIndex: "1000",
        backgroundColor: "#ffffff"
    });
})();
