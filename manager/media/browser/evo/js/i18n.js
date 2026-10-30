// UI 文言の表。PHP(browser.php)が data-config の lang に入れた言語のキーを受け取り、t() で引く。
// 文言中の %s は引数で順に置き換える(PHP の言語ファイルの書き方と同じ)。

var messages = {};

export function setMessages(obj) {
    messages = obj || {};
}

export function t(key, ...args) {
    var text = Object.prototype.hasOwnProperty.call(messages, key) ? String(messages[key]) : key;
    var i = 0;
    return text.replace(/%s/g, function (match) {
        return i < args.length ? String(args[i++]) : match;
    });
}
