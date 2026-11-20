// 下書きを採用する画面（publish_draft.dynamic.php）の操作。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoPublishDraft) {
    window.EvoPublishDraft = {};
    // 「今すぐ」以外を選んだときだけ日時の欄を出す
    document.addEventListener('click', function (e) {
        if (!e.target.closest("input[name='publishoption']")) {
            return;
        }
        const pubdate = document.getElementById('pubdate');
        if (!pubdate) {
            return;
        }
        const checked = document.querySelector("input[name='publishoption']:checked");
        pubdate.style.display = (checked && checked.value === 'now') ? 'none' : 'block';
    });
}
