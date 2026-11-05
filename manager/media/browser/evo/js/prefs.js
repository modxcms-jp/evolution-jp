// 画面の表示の状態(一覧の表示・ツリーの開閉)を、入口ごとにブラウザへ残す。既定はサーバーの BrowserView::ENTRIES が config.defaults で渡す。
// 残した値が無い・使えない(プライベートウィンドウなどで localStorage が例外を出す)ときは既定を使う。

// 名前ごとに受け付ける値。これ以外が残っていたら(古い版の値など)既定に戻す
var ALLOWED = {
    view: ['grid', 'list'],
    tree: ['open', 'closed']
};

export function createPrefs(entry, defaults) {
    function key(name) {
        return 'evoFb.' + entry + '.' + name;
    }

    function get(name) {
        var value = null;
        try {
            value = window.localStorage.getItem(key(name));
        } catch (e) {
            value = null;
        }
        if (ALLOWED[name] && ALLOWED[name].indexOf(value) !== -1) {
            return value;
        }
        return (defaults || {})[name];
    }

    function set(name, value) {
        try {
            window.localStorage.setItem(key(name), value);
        } catch (e) {
            // 残せなくても、今の画面の表示は変わる
        }
    }

    return { get: get, set: set };
}
