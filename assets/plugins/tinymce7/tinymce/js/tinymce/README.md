# TinyMCE 7 ローカル設置手順

このプラグインはデフォルトで CDN (`https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js`) から TinyMCE を読み込みます。サイズ削減のため TinyMCE 本体は同梱していません。npm パッケージ名は `tinymce` です。`tinymce7@7` のような存在しないパッケージを指定するとブラウザーが `text/plain` 応答として扱い、読み込みに失敗します。

ローカルに配置して利用したい場合は、TinyMCE 公式サイトから取得した TinyMCE 7 パッケージをこの `tinymce/` ディレクトリに展開してください。公式配布のディレクトリ構造をそのまま配置すると、`tinymce.min.js` が `assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js` に設置され、設定ファイルで `"tinymce_use_local": true` を指定するだけでローカル読み込みを有効にできます。

独自の CDN や社内ホスティングを利用したい場合は、`config/manager.json` や `config/frontend.json` に `"tinymce_script_url"` を指定してスクリプト URL を上書きしてください。
