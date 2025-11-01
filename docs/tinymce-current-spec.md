# TinyMCE 現行仕様まとめ

## 概要
- プラグインは TinyMCE 3.5.12 をベースにしており、MODX Evolution のプラグインとして `OnRichTextEditorRegister`、`OnRichTextEditorInit`、`OnInterfaceSettingsRender` のイベントにフックしている。【F:assets/plugins/tinymce/tinymce.install_base.tpl†L3-L21】【F:assets/plugins/tinymce/plugin.tinymce.php†L8-L25】
- プラグイン本体は `TinyMCE` クラスで構成され、イベントパラメータを元に TinyMCE のアセットパス (`assets/plugins/tinymce/`) と URL を初期化する。【F:assets/plugins/tinymce/functions.php†L3-L18】

## マネージャー設定 UI
- `OnInterfaceSettingsRender` で呼び出される設定フォームは `inc/gsettings.inc.html` テンプレートをプレースホルダー置換して出力する。ここではテーマ、スキン、テンプレートドキュメント／チャンク、Enter モード、要素フォーマット、スキーマ、カスタムプラグイン／ボタン、CSS セレクタを管理者が調整できる。【F:assets/plugins/tinymce/functions.php†L94-L233】【F:assets/plugins/tinymce/inc/gsettings.inc.html†L1-L108】
- フォームに供給する初期値は `config()` で取得したシステム設定を使用し、未設定の場合の既定値も指定されている（例: テーマ `editor`、スキン `default`、Enter モード `p`、要素フォーマット `xhtml`、スキーマ `html4`、CSS セレクタ `左寄せ=justifyleft;右寄せ=justifyright`）。【F:assets/plugins/tinymce/functions.php†L110-L123】
- テーマ選択肢は `inherit`（グローバル設定継承）を含むプリセット群から生成され、ユーザー設定が存在しない場合は継承が選択される。スキン選択肢は `tiny_mce/themes/advanced/skins/` からディレクトリとバリアントを走査して構築する。【F:assets/plugins/tinymce/functions.php†L132-L161】【F:assets/plugins/tinymce/functions.php†L37-L79】
- Enter モード、要素フォーマット、スキーマの各ラジオボタン群はリソース編集（アクション 4/27/78）やユーザー設定画面（11/12/74）などのコンテキストでグローバル設定に戻すオプションも提供する。【F:assets/plugins/tinymce/functions.php†L163-L226】

## 設定レイヤー別のカスタマイズ
- **グローバル設定（システム設定）**: マネージャー全体に適用されるテーマ、スキン、Enter モード、要素フォーマット、スキーマ、カスタムプラグイン／ボタン、テンプレート参照、CSS セレクタといった値は `config('tinymce_*')` や `config('mce_*')` 経由で取得され、未入力のときは既定値（テーマ `editor`、スキン `default` など）が補われる。これらの値は初期化処理でもそのまま参照されるため、システム設定を変更することでマネージャー利用時の TinyMCE 全体挙動を制御できる。【F:assets/plugins/tinymce/functions.php†L110-L123】【F:assets/plugins/tinymce/functions.php†L247-L263】
- **ユーザー設定（アクション 11/12/74）**: プロファイル画面からはテーマ・スキン・Enter モード・要素フォーマット・スキーマをユーザー単位で上書きでき、`inherit`／空値を選択すると再びグローバル設定が適用される。`get_mce_settings()` はユーザー設定配列を参照して選択状態を切り替え、UI ではグローバルに戻すための追加ラジオボタンを表示している。【F:assets/plugins/tinymce/functions.php†L101-L220】
- **プラグイン設定（プロパティ）**: プラグインのプロパティでは追加パラメータやブロックフォーマット、URL 変換ポリシー、エディタサイズ、フロントエンド向けテーマ／ボタン／アライメントなどを切り替えられる。フロントエンド初期化時は `webtheme`、`webPlugins`、`webButtons1-4`、`webAlign` などのプロパティ値がそのまま適用され、`width` や `height`、`mce_path_options` なども初期化テンプレートに反映される。【F:assets/plugins/tinymce/tinymce.install_base.tpl†L10-L20】【F:assets/plugins/tinymce/functions.php†L268-L358】

## プラグインプロパティとデフォルト
- プラグインのインストールテンプレートは、多数のプロパティ（カスタムパラメータ、ブロックフォーマット、エンティティエンコーディング、パス処理、リサイズ可否、無効化ボタン、リンクリスト、フロントエンド用テーマ／ボタンセットなど）を宣言している。これらはマネージャーとフロントエンド双方の挙動を制御する。【F:assets/plugins/tinymce/tinymce.install_base.tpl†L7-L21】
- フロントエンド用（QuickManager／webユーザー）には、テーマ・プラグイン・ボタン群・ツールバー位置・エディタサイズ等のプロパティを `web*` 接頭辞で用意している。【F:assets/plugins/tinymce/tinymce.install_base.tpl†L7-L21】

## エディタ初期化フロー
- `OnRichTextEditorInit` でエディタが TinyMCE の場合に `get_mce_script()` を呼び出し、出力された `<script>` 群と初期化コードを返す。【F:assets/plugins/tinymce/plugin.tinymce.php†L15-L20】
- `get_mce_script()` はバックエンドかフロントエンドかで設定ソースを分岐する。バックエンドではシステム設定（テーマ、スキン、Enter モード、言語、カスタムボタンなど）を参照し、ツールバー整列はマネージャーの方向設定から決定する。フロントエンドではプラグインプロパティからテーマやボタン構成、言語、ツールバー整列を取得し、必要に応じて Web ユーザー名を渡す。【F:assets/plugins/tinymce/functions.php†L240-L278】
- テーマが `custom` の場合はカスタムプラグイン／ボタン設定をそのまま使用し、それ以外は `settings/toolbar.settings.inc.php` のプリセットを読み込む。`editor` または空文字のテーマは `default` プリセットを使用する。【F:assets/plugins/tinymce/functions.php†L282-L308】
- `quickupload` プラグインフォルダが存在すると、自動的にプラグインリストとツールバー第 2 行に `quickupload` を追加する。【F:assets/plugins/tinymce/functions.php†L309-L312】
- リソース作成／編集時（アクション 4/27/78）にテンプレートが未設定（ID 0）の場合は `fullpage` プラグインとボタンを強制追加し、テンプレート機能が無効なときは `template` プラグイン／ボタンを除去する。【F:assets/plugins/tinymce/functions.php†L313-L335】
- 生成した初期化コードの末尾では、MODX 用ファイルブラウザーコールバックのスクリプトを追加し、`link_list` プロパティが `enabled` のときだけリンクリスト用スクリプトを読み込む。【F:assets/plugins/tinymce/functions.php†L339-L344】

## TinyMCE 初期化スクリプト詳細
- `build_mce_init()` は `mce_init.inc.js.template` をもとに初期化コードを生成し、エディタ対象要素、サイズ、言語、スキン／バリアント、ドキュメントベース URL、URL 処理ポリシー、Enter モード挙動、スキーマ、ツールバー配置、プラグイン、ボタン構成、ブロックフォーマット、スタイルセレクタ、無効ボタン、リサイズ可否、日付／時刻フォーマット、エンティティエンコーディングなどを挿入する。【F:assets/plugins/tinymce/functions.php†L347-L479】【F:assets/plugins/tinymce/js/mce_init.inc.js.template†L1-L88】
- URL 処理は `mce_path_options` プロパティに応じて `relative_urls`／`remove_script_host`／`convert_urls` を切り替える。`Site config` はシステム設定 `strip_image_paths` を参照し、`Root relative`/`Absolute path`/`URL`/`No convert` などをサポートしている。【F:assets/plugins/tinymce/functions.php†L374-L411】
- `content_css` はプラグイン同梱の `style/content.css` を必ず読み込み、設定値 `editor_css_path` が絶対パス・URL・相対パスのいずれかで指定されている場合には追加で連結する。【F:assets/plugins/tinymce/functions.php†L454-L462】
- カスタムパラメータ文字列は MODX プレースホルダーを評価したうえでカンマ区切りの末尾カンマを除去して挿入される。【F:assets/plugins/tinymce/functions.php†L439-L453】
- 初期化テンプレートでは、エディタ読み込み時に `tiny_mce.js` と `xconfig.js` を読み込んだ後、`valid_elements` を `xconfig.js` 内のルールに設定し、`onPostProcess` で `{{ }}` や MODX タグを `<p>` で囲まないように後処理を行うカスタム処理を追加している。【F:assets/plugins/tinymce/js/mce_init.inc.js.template†L1-L83】【F:assets/plugins/tinymce/js/xconfig.js†L1-L27】
- `myCustomOnChangeHandler` はエディタ内容変更時に `documentDirty` フラグを更新し、未保存警告ロジックと連携する。【F:assets/plugins/tinymce/js/mce_init.inc.js.template†L81-L83】

## ファイルブラウザー連携
- `build_tiny_callback()` は `modx_fb.js.inc` を読み込み、ファイルブラウザー URL を `manager/media/browser/mcpuk/browser.php?editor=tinymce` に差し替えて注入する。【F:assets/plugins/tinymce/functions.php†L482-L489】
- `mceOpenServerBrowser()` は要求されたリソースタイプ（画像／メディア／ファイル）を MODX ブラウザのクエリに付与し、インラインポップアップで 70% サイズのウィンドウを開く。選択後は戻り先フィールドへ URL をセットする。【F:assets/plugins/tinymce/js/modx_fb.js.inc†L1-L51】
- `tinymce.modxfb.js` ではブラウザーから TinyMCE のポップアップ API 経由で選択 URL を返し、画像ダイアログの場合はプレビュー更新も行う。【F:assets/plugins/tinymce/tinymce.modxfb.js†L1-L31】

## リンクリスト機能（任意）
- `link_list` プロパティが `enabled` の場合、`assets/plugins/tinymce/js/tinymce.linklist.php` を読み込み、公開ドキュメントの一覧を JavaScript 配列 `tinyMCELinkList` として提供する。【F:assets/plugins/tinymce/functions.php†L339-L344】【F:assets/plugins/tinymce/js/tinymce.linklist.php†L22-L154】
- スクリプトはマネージャーログインを確認し、結果をキャッシュファイル `mce_linklist.pageCache.php` に保存する。ツリー表示／パンくず表示、テンプレート除外、メニューインデックス順などのオプションを備えている。【F:assets/plugins/tinymce/js/tinymce.linklist.php†L22-L144】

## フロントエンド利用時の考慮点
- フロントエンドで使用する際（QuickManager や Web ユーザー経由）は、プラグインプロパティで指定したテーマ／ボタン構成をそのまま適用し、ファイルブラウザー利用可否は `rb_webuser` 設定で判定される。言語はシステム設定 `fe_editor_lang` を `get_lang()` に通して決定する。【F:assets/plugins/tinymce/functions.php†L265-L277】
- エディタの Enter モードが `br` でない限り、`forced_root_block='p'` で `<p>` を強制し、QuickManager（アクション 78）では `br` を許容するなど、コンテキストに応じたブロック生成ルールが適用される。【F:assets/plugins/tinymce/functions.php†L413-L421】

## 同梱プリセット
- `settings/toolbar.settings.inc.php` には `simple`/`creative`/`logic`/`legacy`/`advanced`/`full`/`default` のプリセットが定義され、各プリセットは利用する TinyMCE プラグイン一覧 (`p`) とツールバー行ごとのボタンセット (`b1`〜`b4`) を指定している。これらがテーマ選択時の初期構成になる。【F:assets/plugins/tinymce/settings/toolbar.settings.inc.php†L1-L52】
- `settings/default_params.php` はアクション 17（グローバル設定画面）ではプレースホルダー配列を初期化し、それ以外ではイベントパラメータをそのまま渡す。これによりフォーム描画時の既定値を安定させている。【F:assets/plugins/tinymce/settings/default_params.php†L1-L21】

