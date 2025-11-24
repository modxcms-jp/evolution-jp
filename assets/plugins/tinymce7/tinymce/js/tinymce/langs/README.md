TinyMCE 公式の `langs/*.js` を設置してください（CDN 配布はありません）。
================================

TinyMCE 7 の UI をローカライズする場合は、公式配布パッケージの `langs/` ディレクトリにあるファイルをこのフォルダー（`assets/plugins/tinymce7/tinymce/js/tinymce/langs/`）へそのままコピーしてください。

必要な言語ファイルだけを置きたい場合は、`assets/plugins/tinymce7/langs/` に `<lang>.js` を配置しても読み込まれます。

ローカルにファイルが無い場合は TinyMCE 既定の英語 UI で表示されます（CDN フォールバックはありません）。
