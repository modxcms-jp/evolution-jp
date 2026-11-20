// mm_widget_showimagetvs の画面側の処理。PHP から mmImageTvs.attach() と mmImageTvs.watch() で呼ぶ
var mmImageTvs = {
    // 値（URL）から、プレビューの画像の URL を求める
    // settings: {baseUrl, thumbnailerUrl, w, h}。thumbnailerUrl が空なら画像そのものを使う
    previewUrl: function (value, settings) {
        let url = value;
        url = (url != '' && url.search(/^@[a-z]+/i) == -1) ? url : url.replace(new RegExp(/^@[a-z]+/i), '');
        url = (url != '' && url.search(/https?:\/\//i) == -1 && url.search(/^\//i) == -1) ? (settings.baseUrl + url) : url;
        if (settings.thumbnailerUrl) {
            url = settings.thumbnailerUrl + '?src=' + encodeURIComponent(url) + '&w=' + settings.w + '&h=' + settings.h;
        }
        return url;
    },
    // 値が変わったとき（change）と最初の 1 回にプレビューを作り直す。前回の値は dataset.lastvalue に置く
    // id: 欄の id（tv12）、settings: previewUrl() の設定に style（img の style 属性）を足したもの
    attach: function (id, settings) {
        const self = this;
        const field = document.getElementById(id);
        if (!field) { return; }
        field.classList.add('imageField');
        const update = function () {
            field.dataset.lastvalue = field.value;
            field.classList.add('imageField');
            const url = self.previewUrl(field.value, settings);

            // Remove the old preview
            const old = document.getElementById(id + 'PreviewContainer');
            if (old) { old.remove(); }

            if (url != '' && !/::/.test(url)) {
                // Create a new preview（行の入力のセルに足す）
                // 値を HTML として解釈しないよう、要素を作って src に入れる
                MM.all(Evo.fieldPart(field, 'value')).forEach(function (td) {
                    const box = document.createElement('div');
                    box.className = 'tvimage';
                    box.id = id + 'PreviewContainer';
                    const img = document.createElement('img');
                    img.src = url;
                    img.setAttribute('style', settings.style);
                    img.id = id + 'Preview';
                    box.appendChild(img);
                    td.appendChild(box);
                });

                // Attach a browse event to the picture, so it can trigger too
                const preview = document.getElementById(id + 'Preview');
                if (preview) {
                    preview.addEventListener('click', function () {
                        BrowseServer(id);
                    });
                }
            }
        };
        field.addEventListener('change', update);
        update();
    },
    // 画像の欄の値が書き換えられたら（ファイルピッカーなど）、change を起こしてプレビューを更新する
    watch: function () {
        setInterval(function () {
            MM.all('.imageField').forEach(function (el) {
                if (el.value != el.dataset.lastvalue) {
                    // 素の change を起こす（管理画面の未保存の検知に届く）
                    MM.fire(el, 'change');
                }
            });
        }, 250);
    }
};
