# ExecPlan: ファイルブラウザ全面刷新(frameset廃止・JSON API・EvoShellモーダル統合)

## Purpose / Big Picture

管理画面のファイルブラウザ(mcpuk)は、画像やファイルを選ぶたびに別ウィンドウ(ポップアップ)が開く2003年FCKeditor由来の7フレーム構成で、ポップアップブロッカーに阻まれ、機能も貧弱(単一アップロード・一括操作なし・検索なし)。これをゼロから作り直し、新規JSON APIとVanilla JS(ライブラリ非依存の素のJavaScript)のモダンな1ページ構成にして、管理画面シェルのモーダル(ページ遷移せずその場へ重ねるダイアログ)で開けるようにする。D&D複数アップロード・一括操作・ファイル移動・検索・ソート・表示切替・プレビュー拡大を備え、将来的にKCFinder級(クリップボード、zip一括DL、画像編集等)へ拡張できる土台を作る。TinyMCEの画像挿入やテンプレート変数(TV)の画像/ファイル入力からの利用は従来どおり機能させ、ロードマップ「frame要素廃止」のマネージャ内最後のframesetを解消する。

## Progress

- [x] (2026-07-11) M1: JSON APIバックエンド(list/thumb)+PoCフロント(一覧表示・フォルダ移動・ファイル選択)。manager/media/browser/evo/{api.php,browser.php,filebrowser.js,filebrowser.css,lib/{PathResolver,BrowserConfig,ThumbnailService}.php}を新設。実機検証: HTTP経由でbrowser.php(スタンドアロン/モーダル断片)・api.php(list/thumb)を実セッション(手動注入)で確認。realpath包含チェックによる../traversal遮断、透過PNG保持、304キャッシュ、日本語ファイル名を確認済み。
- [x] (2026-07-11) M2: 書き込み系API+UI(D&D複数アップロード進捗付き・フォルダ作成・リネーム・削除)。api.phpへupload/mkdir/rename/delete(files[]/folders[]複数対応)を追加、全POSTでcheckCsrfToken()必須。filebrowser.jsへD&Dドロップゾーン・進捗バー付きXHRアップロード・アイテムごとのrename/deleteアイコンを実装。実機検証: curl+実セッションでmkdir→rename→delete、upload(拡張子拒否/サイズ/同名衝突連番)、CSRFなしPOSTの403を確認。D&D自体のブラウザ操作確認は未実施(ヘッドレスでのファイルD&D操作は手動ブラウザ確認が必要、M4のTinyMCE実機検証と合わせて実施予定)。
- [x] (2026-07-11) M3: 高機能化(複数選択・一括削除・フォルダ間移動・検索・ソート・グリッド/リスト切替・プレビュー拡大)。api.phpへaction=move追加。filebrowser.jsへチェックボックス多重選択・一括削除/移動・移動先フォルダ選択オーバーレイ(既存list APIを再利用したミニブラウザ)・検索/ソート(クライアント側再フィルタ、再フェッチ不要)・グリッド/リスト切替(localStorage永続化)・サムネイルクリックでの拡大プレビュー(実寸URL表示・寸法・サイズ・「このファイルを選択」)を実装。バックエンド(move含む全action)はcurl+実セッションで検証済み。フロントの対話操作(検索入力・チェックボックス・ドラッグ等の実際のブラウザ操作)は、本セッションにブラウザ自動化ツールが無いため未検証(コードレビューとJS構文チェックのみ)。M4の実機検証(TinyMCE干渉含む)と合わせてユーザーによるブラウザでの確認が必要。
- [x] (2026-07-11) M4実装: EvoShellモーダル統合・呼び出し元7箇所の切替。shell.jsへEvoShell.openFilePicker(url, onPick)+グローバルフックwindow.EvoFileBrowserPick+モーダルのfocusin伝播停止(TinyMCEフォーカストラップ対策)を追加。browser.phpのモーダル断片にX-Evo-Pane:1ヘッダーと再初期化インラインscript(executeScriptsのsrc重複排除対策)を追加、apiUrlをモーダル時は/manager/基準に切替。filebrowser.jsを初期化関数化(EvoFileBrowserInit)。SetUrl互換のためAPIへpathフィールド(旧mcpuk同等の相対パス content/images/...)を追加し、選択確定はurlでなくpathを渡す。呼び出し元切替: browser.js(共通)・mutate_content/functions.php・mutate_module・mutate_user/tpl/javascript.php・mutate_user_pf・mutate_web_user(各インラインOpenServerBrowserへシェル分岐追加)・FileBrowserResolver.php・mcpuk-picker.js(シェル分岐追加)。HTTPレベル検証済(X-Evo-Paneヘッダー・path値・Type=大文字互換)。
- [x] (2026-07-11) 追加要望対応(エクスプローラ準拠UI): (1)左ペインにフォルダツリー(遅延読み込み・開閉トグル・現在フォルダのハイライト・グリッドのlist応答から現在ノードを自動同期)。APIのlistへhasChildrenとfoldersOnly=1(ツリー展開用軽量モード)を追加。(2)ファイルのD&D移動: ファイルをドラッグしてツリーのフォルダ・グリッド内フォルダ・パンくずへドロップで移動(選択中ファイルをドラッグすると選択全体を移動)。内部ドラッグ(application/x-evo-fb-files)とOSファイルドラッグ(アップロード)をdataTransfer.typesで判別し共存。(3)クリック操作をエクスプローラ準拠に変更: シングルクリック=選択(Ctrl/Cmd+クリックで追加選択)、ダブルクリック=選択確定、プレビューは虫眼鏡アイコンへ分離。(4)ツリーにファイルも表示(ユーザー要望): 軽量モードをfoldersOnly→light=1に変更しfilesのname/pathも返す。ツリー内ファイルはクリック=そのフォルダへ移動+選択、ダブルクリック=選択確定、ドラッグ元(フォルダへのD&D移動)にもなる。(5)チェックボックス廃止(ユーザー要望): Ctrl/Cmd+クリックで足りるため丸いチェックボックスのUIを削除し、Shift+クリックの範囲選択を追加。(6)ラバーバンド(矩形)選択(ユーザー要望): 空白部分をドラッグして複数ファイルを選択するマーキー選択をjs/marquee.jsとして新設。
- 2026-07-11 (yamamoto/Claude): filebrowser.js(実装当初985行)が機能追加のたびに肥大化する懸念をユーザーから指摘され、ESモジュール(`<script type="module">`)へ分割した。分割構成: `js/utils.js`(HTMLエスケープ・表示整形・パンくず・アイコン)/`js/api.js`(list/thumb/postForm通信)/`js/dnd.js`(内部移動D&DとOSファイルD&Dの判別・バインド)/`js/marquee.js`(矩形選択、新設)/`js/tree.js`(左ペインツリー)/`js/grid.js`(右ペイングリッド・選択)/`js/upload.js`(アップロードUI)/`js/dialogs.js`(移動先選択・プレビューの2オーバーレイ)、`filebrowser.js`(状態を保持し各モジュールへコールバックを渡す構成ルート、288行)。ESモジュールの相対import(`./js/utils.js`等)はモジュール自身の解決済みURL基準になるため、モーダル断片(/manager/index.php上で実行)とスタンドアロン(/manager/media/browser/evo/上で実行)でscriptタグのsrcパスが異なっていても正しく解決される(モーダル判定でapiUrlだけ切り替えている非対称性と対照的に、モジュール分割後はこの種の相対パス問題が構造的に起きない)。
- [ ] M4実機検証(ユーザー実施): 下記チェックリスト参照。特にTinyMCEダイアログとモーダルの干渉。追加分: ツリー開閉・ツリーへのD&D移動・ダブルクリック選択。
- [ ] M5: mcpuk削除・ドキュメント更新・将来拡張(KCFinder級)の課題記録

## Surprises & Discoveries

- (2026-07-11 M4) shell.jsのexecuteScriptsは同一srcのscriptを再実行しない(loadedScriptSrcs)。モーダルを2回目に開くとfilebrowser.jsが実行されず初期化されないため、初期化をwindow.EvoFileBrowserInitとして公開し、断片末尾のインラインscript(毎回実行される)から呼び直す構造にした。
- (2026-07-11 M4) 旧mcpukがSetUrlへ渡す値はurlprefix+パスの相対パス(この環境では content/images/foo.jpg)で、TV値にもこの形式が入る。新APIの絶対URLをそのまま渡すとTV値が絶対URL化して後方互換が壊れるため、応答にpath(相対)とurl(絶対・プレビュー表示用)を分けて持たせた。TinyMCE経由はmcpuk-picker.jsのnormalizeUrlが従来どおり相対→絶対化する。
- (2026-07-11 M4) モーダル断片は/manager/index.php上で動くため、断片内の相対URL(api.php)は/manager/api.phpへ誤解決される。apiUrlをモーダル時はmedia/browser/evo/api.phpに切替え、サムネイルURLはAPIが返さずフロントがapiUrl基準で組み立てる方式(thumb:'api'マーカー)にした。
- (2026-07-11 M4) TinyMCEフォーカストラップ対策として、モーダルオーバーレイでfocusinの伝播を停止(document側の監視に届かせない)。capture段階で監視された場合は効かない可能性があり、実機検証が必要。干渉時はmcpuk-picker.jsのシェル分岐を外せばポップアップへ戻せる。
- (2026-07-11 M4実機検証) 上記の懸念が的中。TinyMCEの「画像の挿入/編集」ダイアログから「ファイルをブラウズ」→EvoShellモーダルを開く→ファイル選択、の操作で `The component must be in a context to execute: triggerEvent` (theme.min.js) が発生し、選択操作後にTinyMCEエディタ自体が解除される事象を確認。TinyMCEのダイアログはコンポーネントツリーの整合性を厳密に管理しており、上に別のフォーカストラップ付きモーダル(EvoShellモーダル)を重ねるとダイアログ側のコンテキストが破棄される。計画通りmcpuk-picker.jsのEvoShellモーダル分岐を削除し、TinyMCE経由は常にポップアップウィンドウへ固定した(他6箇所の呼び出し元はモーダルのまま)。**注意**: mcpuk-picker.jsの`<script>`タグにキャッシュバスターが無く、EvoShellのSPA遷移は同一src再読み込みをしないため、この修正はブラウザの**フルページリロード**後でないと反映されない(browser.js/filebrowser.cssで踏んだのと同種の問題)。
- (2026-07-11 M4追加検証・ユーザー要望) TinyMCEでもモーダル的表示(ポップアップ回避)を試したいとの要望を受け、`editor.windowManager.openUrl()`(TinyMCE公式のURL/iframeダイアログAPI)を試験導入。file_picker_callbackで外部にwindow.open/EvoShellモーダルを開く方式と異なり、TinyMCE自身のダイアログ管理下(コンポーネントツリー内)にiframeとして表示されるため、「画像の挿入/編集」ダイアログとのコンテキスト競合が起きない可能性がある。mcpuk-picker.jsに`openInEditorDialog()`を追加し、`tinymce.activeEditor.windowManager.openUrl`が使える場合はそちらを優先(非対応環境ではポップアップへフォールバック)。iframe内のfilebrowser.js(pickFile)は`window.parent.postMessage({mceAction:'evoFbPick', url: path}, origin)`で選択結果をダイアログのonMessageハンドラへ返す。実機検証が必要(mcpuk-picker.jsはキャッシュバスターが無いため要フルリロード)。
- (2026-07-11 M4実機検証・ユーザー報告) windowManager.openUrl()方式は成功(TinyMCE解除なし)。一方、TV画像入力からのモーダル利用で「本文欄のTinyMCEエディタが消えてtextareaに戻る」別の不具合を発見。原因はshell.jsのteardownPane()が#mainPane差し替え時・モーダルclose時の両方で使われる共通関数でありながら`tinymce.remove()`(無引数=全インスタンス削除)を呼んでいたため、モーダルを閉じるだけで背後の#mainPane側のエディタまで巻き添えで消えていた(ファイルブラウザ固有ではなく、TinyMCEを開いた状態でモーダルを閉じる操作全般で起きる一般的な副作用)。`tinymce.editors`を`pane.contains(editor.getElement())`で絞り込み、閉じるpaneの内側のエディタだけを削除するよう修正(詳細はassets/docs/core-issues.md参照)。

- (2026-07-11 計画時) mcpukバックエンド(connectors/)は既にConnectorKernel+Commandsパターンへリファクタ済み。パス正規化・ファイル名サニタイズ(Commands/Base.php の buildResourcePath/sanitizeSegment 系)と設定解決(ConnectorConfigBuilder.php の rb_base_dir/use_browser/拡張子制限)は新APIの実装参考として価値が高い。
- (2026-07-11 計画時) browser.php:39 が参照する seturl_js_tinymce.inc はリポジトリに存在しない(死にパス)。現行TinyMCE7は assets/plugins/tinymce7/js/mcpuk-picker.js がグローバル SetUrl を一時定義する方式。
- (2026-07-11 計画時) 旧サムネイル管理(Commands/Thumbnail.php)には構造的な問題が複数ある: (1)各コンテンツフォルダ直下に .thumb/ 隠しディレクトリを作って保存するため、公開領域に露出しバックアップ/デプロイへ混入する、(2)フォルダのリネーム・移動・削除時に .thumb が取り残されゴミ化する、(3)元ファイルが消えてもサムネイルがあればそれを返す(isReusableThumbnail)、(4)JPEG固定出力でPNG/GIFの透過が白背景になる、(5)コネクタが no-cache ヘッダーを送るためHTTPキャッシュが効かず毎回転送される。

## Decision Log

- 2026-07-11 (yamamoto/Claude): 当初「既存XML API温存・フロントのみリライト」で計画したが、ユーザーから「高機能化したいのでゼロから作り直してよい」「KCFinderくらいの機能を将来的には実装したい」の方針提示があり、新規JSON API設計+フルスクラッチへ変更。一括操作・移動などXML APIに無い機能を素直に実装でき、将来拡張の土台になる。
- 2026-07-11 (yamamoto/Claude): 今回スコープはD&D複数アップロード/一括操作・移動/検索・ソート・表示切替/プレビュー拡大まで(ユーザー選択)。KCFinder級の残り(クリップボードコピー&ペースト、zip一括DL、画像リサイズ・回転、右クリックメニュー)は将来課題としてM5で記録し、今回は実装しない。
- 2026-07-11 (yamamoto/Claude): 新実装は manager/media/browser/evo/ に新設し、mcpukディレクトリはM5で丸ごと削除する。既存コードの上書きではなく並行構築とし、M4の呼び出し元切替まで旧実装が常に動く状態を保つ(切り戻し容易性)。
- 2026-07-11 (yamamoto/Claude): 新APIの書き込み系(upload/mkdir/rename/delete/move)はCSRFトークン検証(checkCsrfToken)を必須にする。旧connectorsはセッション検証のみでCSRF保護がなかった。読み取り系(list/thumb)はGET+セッション検証のみ。
- 2026-07-11 (yamamoto/Claude): 選択結果の返却は従来のグローバル SetUrl(url) 規約を呼び出し元向けに温存する。シェル内では新API EvoShell.openFilePicker(url, onPick) を使い、chromeless(QuickManager等のiframe埋め込み)ではスタンドアロン表示+window.openへフォールバックする。
- 2026-07-11 (yamamoto/Claude): デザインは管理画面テーマ(manager_theme設定: RevoClassic/RubberWhite/AlomaHot/SakuraFlow等)と連動させる(ユーザー要望)。filebrowser.cssは独自の配色を定義せず、テーマのCSS変数(--color-primary系/--gray-*/--space-*等)をフォールバック値付きで参照する。この手法はモーダルタイトルバー(shell.cssのvar(--color-primary-start, var(--color-primary, #375665))方式、コミット4a74934c7)で全テーマ動作を確立済み。スタンドアロン表示時はbrowser.phpがテーマのstyle.cssを読み込んで同じ変数を供給する。
- 2026-07-11 (yamamoto/Claude): サイズ予算を設ける(ユーザー要望)。今回の改修でシステム全体の配布サイズを正味で増やさない。削除する旧mcpukは344KB、新実装はライブラリ非依存の手書きコード(api.php/browser.php/filebrowser.js/filebrowser.css、合計100KB未満想定)のため正味削減の見込み。サードパーティJSライブラリ・アイコンフォント・画像スプライトは採用せず、ファイル種別アイコンはインラインSVGまたは既存テーマ画像の再利用とする。実装中に外部ライブラリ等で追加サイズが合計1MBを超えそうになった場合は、実装を止めてユーザーと対応を検討する。M5でサイズ差分を計測して記録する。
- 2026-07-11 (yamamoto/Claude): サムネイル管理を刷新する(ユーザー要望・提案)。保存先をコンテンツフォルダ直下の .thumb/ から temp/thumbs/ へ移す。temp/ はこのフォークの書き込み領域の正本(define-path.php で MODX_CACHE_PATH=temp/cache/ と定義され、backup/export/logs も temp 配下)であり、コンテンツ領域(assets/images等)へのキャッシュ混入がなくなる。temp/cache/ 直下ではなく temp/thumbs/ とするのは、clearCache(サイトキャッシュ削除)のたびに全サムネイルが再生成されるのを避けるため。キャッシュキーは「type+相対パスのハッシュ+元ファイルmtime」をファイル名に含める方式とし、元ファイルの更新で自動的に新キーへ切り替わる(リネーム・移動・削除時の個別追随処理が不要。旧キーのファイルは参照されなくなるだけなので、list実行時などに古いものを間引く軽量な掃除を入れる)。出力はPNG/GIF透過を保持(元がJPEGならJPEG、それ以外はPNG)し、Cache-Control+Last-Modifiedで304応答を返してHTTPキャッシュを効かせる。

## Outcomes & Retrospective

## Context and Orientation

パスはすべてリポジトリルート相対。

置き換える旧実装(mcpuk、M5で削除):

- manager/media/browser/mcpuk/browser.php — エントリ。?editor=(呼び出し元名)と?type=(images/media/files)を受け、browser.html.incの7フレームframesetを出力。manager/index.phpのルーティング(a=)を通らない直アクセス型で、$_SESSION['mgrValidated']を自前検証する。
- manager/media/browser/mcpuk/frm*.html(6枚)+js/fckxml.js+js/common.js+seturl.js — フレームUI。選択時は window.top.opener.SetUrl(url)+window.close() のポップアップ前提。
- manager/media/browser/mcpuk/connectors/ — XML APIバックエンド。新APIへ流用すべき知見: ConnectorConfigBuilder.php(use_browser設定の権限確認、rb_base_dirによるベースディレクトリ解決、タイプ別サブディレクトリ=ResourceAreas、アップロード可能拡張子)、Commands/Base.php(buildResourcePath/buildRealPathのパス正規化と../遮断、sanitizeFileName/sanitizeFolderName)、Commands/Thumbnail.php(サムネイル生成)、Commands/FileUpload.php(拡張子検証)。

呼び出し元(7箇所、すべて window.open ポップアップ+グローバル SetUrl(url) 規約):

1. manager/media/browser/browser.js — 共通ヘルパー。BrowseServer(ctrl)/BrowseFileServer(ctrl)がポップアップを開き、SetUrl(url)がlastImageCtrl/lastFileCtrlのinputへ値を設定。
2. manager/actions/document/mutate_content/functions.php — TVのimage/file型入力の「挿入」ボタン。
3. assets/plugins/tinymce7/src/TinyMCE7/Editor/FileBrowserResolver.php と assets/plugins/tinymce7/js/mcpuk-picker.js — TinyMCE7のfile_picker_callback。SetUrlを一時定義してコールバックへ中継。
4. manager/actions/element/mutate_module.dynamic.php
5. manager/actions/permission/mutate_user/tpl/javascript.php
6. manager/actions/permission/mutate_user_pf.dynamic.php
7. manager/actions/permission/mutate_web_user.dynamic.php

シェル側の受け皿(実装済み前提):

- manager/media/script/shell.js — EvoShell.openModal/closeModal、モーダルDOM(#evoShellModalOverlay/#evoShellModal/#evoShellModalHeader/Body/Footer)、モーダル内フォームのURL解決(currentModalUrl)。
- manager/media/style/common/shell.css — モーダルスタイル(テーマ変数--color-primary系でヘッダー配色)。
- manager/actions/header.inc.php — bodyのdragstart制御: draggable="true"明示要素以外のネイティブドラッグを抑止する(OSからのファイルD&Dはdragstartが発生しないため、ドロップ受け入れとは競合しない)。
- CSRF: manager/includes/csrf_token.php の checkCsrfToken()(不一致時403+ログ)。シェル画面には meta[name=csrf-token] が出力済み。

新実装の構成(このプランで新設):

- manager/media/browser/evo/browser.php — エントリ。?type=と?modal=1を受け、モーダル用断片またはスタンドアロン完全HTMLを出力。CSRFトークンをJSへ受け渡す。
- manager/media/browser/evo/api.php — JSON API(単一エンドポイント、下記Interfaces参照)。
- manager/media/browser/evo/filebrowser.js / filebrowser.css — UI本体。

## Plan of Work

並行構築→切替→削除の三段構えとする。新実装をmanager/media/browser/evo/に作り、M4で呼び出し元を切り替えるまで旧mcpukを温存する。これにより各マイルストーンが独立してコミット・切り戻しできる。

バックエンドはaction パラメータで分岐する単一エンドポイント api.php とする。旧connectorsのセキュリティロジック(パス正規化・../遮断・拡張子検証・use_browser権限)を新クラスへ移植し、レスポンスはJSONに統一する。書き込み系はPOST+CSRF必須。ファイル移動(move)と一括削除は旧APIに存在しない新設コマンド。

フロントは1枚のレイアウト(ヘッダー: タイプ切替・検索・ソート・表示切替 / 左: フォルダツリー / 右: ファイルグリッド(またはリスト) / フッター: アップロード領域)をVanilla JSで描画する。状態(現在タイプ・フォルダ・選択集合・ソート条件)はfilebrowser.js内の単一ストアで管理し、操作後はlistを再取得して再描画する素朴な構成とする(フレームワーク不使用)。

スタイリングは管理画面テーマと連動させる。filebrowser.cssには配置(グリッド・余白・スクロール)のみを書き、色・境界線・フォントはテーマのCSS変数をフォールバック値付きで参照する(shell.cssのモーダルヘッダーで確立した var(--color-primary-start, var(--color-primary, #375665)) 方式)。モーダル表示ではシェルが読み込み済みのテーマstyle.cssが変数を供給し、スタンドアロン表示ではbrowser.phpが media/style/<manager_theme>/style.css を自分でリンクする。これによりテーマを切り替えるとファイルブラウザの見た目も追従する。

表示モードは2つ。スタンドアロン(完全HTML)はchromelessフォールバックのポップアップで使い、選択時はwindow.opener.SetUrl(url)+close。モーダル(?modal=1、断片)はシェルの新API EvoShell.openFilePicker(url, onPick) が描画し、選択時はonPick(url)→closeModal。断片は同一document内で動くため、旧実装のようなフレーム間参照やopener連携は不要になる。

呼び出し元の切替は browser.js と mcpuk-picker.js の2ファイルへの分岐追加に集約する(アクションファイル7箇所はBrowseServer系のURL定数を新browser.phpへ向ける変更のみ)。最大リスクはTinyMCEダイアログのフォーカストラップ(ダイアログ外へのフォーカスを奪還する機構)との干渉で、M4の最初に実機検証し、干渉時はTinyMCEからの呼び出しのみポップアップへフォールバックする。

## Concrete Steps

M1 JSON API+PoCフロント:

1. 新規: manager/media/browser/evo/api.php — DocumentParser初期化(mcpuk/connectors/connector.phpの冒頭を踏襲)、セッション検証、use_browser確認。action=list: {folders:[{name}], files:[{name,size,mtime,width,height,url,thumb}]} を返す(.thumb等の隠しディレクトリは一覧から除外)。action=thumb: サムネイルを temp/thumbs/ のキャッシュ(キー=type+相対パスのハッシュ+元mtime)から返し、無ければGDで生成して保存(リサイズ処理はCommands/Thumbnail.phpを参考に、透過保持とCache-Control/Last-Modified付き304応答へ改良。詳細はDecision Log参照)。パス解決はBase.phpのbuildResourcePath相当を新クラスFileBrowserPathResolverとして移植し、type(images/media/files)ごとのベースディレクトリをConnectorConfigBuilderと同じ設定(rb_base_dir等)から解決する。
2. 新規: manager/media/browser/evo/browser.php — レイアウトHTML出力(?modal=1で断片/なしで完全HTML)。csrfTokenField()相当のトークンをdata属性かJS変数で埋め込む。スタンドアロン時は manager_theme 設定を読み media/style/<テーマ名>/style.css をリンクする(モーダル時はシェルが供給済みのため読み込まない)。
3. 新規: filebrowser.js/filebrowser.css — fetchでlistを取得しフォルダ+ファイルグリッドを描画。フォルダクリックで移動、ファイルクリック(またはダブルクリック)で選択確定(仮コールバック)。filebrowser.cssの色・境界線はテーマCSS変数のフォールバック付き参照で書く(Plan of Work参照)。
4. 確認: `http://evo.localhost/manager/media/browser/evo/browser.php?type=images` を直接開き、一覧表示・フォルダ移動・選択発火をコンソールで観測。`api.php?action=list&type=images` をcurl(セッションCookie付き)で叩きJSONを確認。

M2 書き込み系:

1. 編集: api.php — action=upload(multipart複数対応・拡張子検証)、mkdir、rename(file/folder)、delete(単一)。POST系はcheckCsrfToken()を先頭で実行。
2. 編集: filebrowser.js — ファイル選択ボタン+一覧へのD&Dで複数アップロード(XMLHttpRequest.upload.onprogressで進捗バー)、右クリックせず各アイテムのアイコン操作(リネーム・削除)、フォルダ作成フォーム。
3. 確認: 複数ファイルを一度にD&Dアップロードし進捗表示と完了後の一覧反映、mkdir/rename/deleteの結果がファイルシステム(assets/images等)へ反映されることを確認。CSRFトークンなしのPOSTが403になることをcurlで確認。

M3 高機能化:

1. 編集: api.php — action=move(複数ファイルを指定フォルダへ移動、同名衝突は拒否)、delete複数対応。
2. 編集: filebrowser.js — チェックボックス(またはCtrl+クリック)の複数選択、一括削除、移動先フォルダ選択ダイアログ、ファイル名インクリメンタル検索、ソート(名前/更新日/サイズ)、グリッド/リスト表示切替(状態はlocalStorageへ保存)、画像クリックで拡大プレビュー(寸法・サイズ・URL表示、そこから「このファイルを選択」)。
3. 確認: 各機能をスタンドアロン表示で操作確認。

M4 モーダル統合と切替:

1. 編集: manager/media/script/shell.js — EvoShell.openFilePicker(url, onPick)を追加。browser.php?modal=1をfetchして#evoShellModalBodyへ描画(openModalの断片処理を流用)、断片内のfilebrowser.jsが選択時に呼ぶグローバルフックをonPickへ接続し、選択後closeModal。
2. 編集: manager/media/browser/browser.js — imanager_url/fmanager_urlを新browser.phpへ変更し、シェル検出時(window.EvoShellかつbody.evo-shell)はopenFilePicker、それ以外は従来window.openの分岐を追加。SetUrl規約は維持。
3. 編集: assets/plugins/tinymce7/js/mcpuk-picker.js と FileBrowserResolver.php — 参照先URLを新browser.phpへ変更し、同様の分岐を追加。
4. 確認(最初にTinyMCE干渉検証): リソース編集のTinyMCE画像ダイアログ→ファイルをブラウズ→モーダル内のリネーム/検索inputへフォーカスが入り入力できるか。不可ならTinyMCE経由のみポップアップフォールバックにし、結果をSurprisesへ記録。続いてTV画像入力・ユーザー写真・モジュール編集・quickmanager(chromeless、ポップアップ動作)の各呼び出し元を実機確認。

M5 後始末:

1. 削除: manager/media/browser/mcpuk/ ディレクトリ全体。事前に grep -rn "mcpuk" manager assets で参照残りゼロを確認(ドキュメント内の歴史的記述は除く)。既存サイトのコンテンツフォルダに残る旧 .thumb/ ディレクトリは自動削除しない(ユーザーデータ領域への破壊的操作を避ける)。掃除方法をリリースノート/ドキュメントに記載し、必要ならクリーンアップ用の管理操作追加を将来課題とする。
2. 編集: .agent/plans/2026-07-07-manager-frameset-removal.md のProgressへマネージャ内frameset全廃を追記。assets/docs/core-issues.md の関連項目を更新。KCFinder級の将来拡張候補(クリップボードコピー&ペースト、zip一括ダウンロード、画像リサイズ・回転、右クリックコンテキストメニュー、多言語化)を .agent/roadmap.md へ新タスク案として提示(登録はユーザー判断)。
3. 確認: アプリコンテナで find . -path ./vendor -prune -o -name "*.php" -exec php -l {} + が構文エラーなし。
4. 確認: サイズ差分の計測。du -sk manager/media/browser/evo/ と、削除した mcpuk(344KB)を比較し、正味増減をOutcomes & Retrospectiveへ記録する。git diff --stat main...HEAD でも追加行規模を確認する。

## Validation and Acceptance

すべてブラウザでの観察で判定する:

- TV(image型)の「挿入」でモーダルのファイルブラウザが開き、画像を選ぶとモーダルが閉じ入力欄にURLが入る。別ウィンドウは開かない。
- TinyMCEの画像ダイアログ「ファイルをブラウズ」でも同様に動き、URLがソース欄へ入る(干渉時は自動的にポップアップで開き、同じ結果になる)。
- 複数の画像ファイルを一覧へドラッグ&ドロップすると進捗表示付きで一括アップロードされ、完了後に一覧へ現れる。
- 複数ファイルを選択して一括削除・別フォルダへの移動ができる。検索ボックスへの入力で一覧が絞り込まれ、ソート切替・グリッド/リスト切替・サムネイルクリックの拡大プレビューが機能する。
- QuickManager等のchromeless画面からは従来どおりポップアップで開き、選択が機能する(後方互換)。
- サムネイルは temp/thumbs/ に生成され、assets/images 等のコンテンツフォルダに .thumb/ が新規作成されない。透過PNGのサムネイルが白背景にならない。一覧の再表示時にサムネイルリクエストが304(ブラウザキャッシュ)で返る。
- 配布サイズが正味で増えていない(旧mcpuk 344KB削除に対し新実装が同等以下。M5で計測)。
- manager/media/browser/mcpuk/ が存在せず、grep -rn "frameset" manager/media/browser/ がヒットしない。

## Idempotence and Recovery

新実装はmanager/media/browser/evo/への並行構築のため、M1〜M3の間は既存動作に一切影響しない。M4の切替はbrowser.js/mcpuk-picker.js/FileBrowserResolver.phpの3ファイルに閉じており、この3ファイルをgit checkoutで戻せば旧ポップアップ構成へ即復帰できる。mcpuk削除(M5)はM4の全呼び出し元検証完了後にのみ行う。

## Artifacts and Notes

- 関連ExecPlan: .agent/plans/2026-07-07-manager-frameset-removal.md(シェル化本体・モーダル機構の経緯はDecision Log 2026-07-10参照)
- 想定コミット分割:
  - feat(manager): ファイルブラウザ用JSON APIと新UI基盤を追加(M1)
  - feat(manager): 新ファイルブラウザにアップロード・フォルダ操作を実装(M2)
  - feat(manager): 新ファイルブラウザに一括操作・検索・プレビューを実装(M3)
  - feat(manager): ファイルブラウザをEvoShellモーダルへ統合(M4)
  - chore(manager): 旧mcpukファイルブラウザを削除(M5)

## Interfaces and Dependencies

- 新JSON API: manager/media/browser/evo/api.php。GET: action=list(type, folder)→{folders,files}、action=thumb(type, folder, file)→画像。POST(csrf_token必須): action=upload(files[])、mkdir(name)、rename(target, newName)、delete(files[], folders[])、move(files[], dest)。エラーは{error:{code,message}}とHTTPステータスで返す。セッション(mgrValidated)と設定use_browser=1を全actionで検証。
- 設定の流用元: rb_base_dir(ファイル置き場のベースディレクトリ)、uploadable_images/uploadable_media/uploadable_files(タイプ別許可拡張子。ConnectorConfigBuilder.phpの解決ロジックを移植時に確認)。
- サムネイルキャッシュ: temp/thumbs/ (書き込み可能領域。define-path.phpがtemp/の書き込み可否を起動時検証済み)。キー=ハッシュ+mtime方式のためファイル操作(rename/move/delete)との同期処理は不要。
- グローバル SetUrl(url) 規約: アクションファイル7箇所が期待する後方互換インターフェース。維持する。
- EvoShell.openFilePicker(url, onPick)(新設): shell.jsへ追加。将来の他ピッカー(リソース選択等)にも流用可能な形にする。
- TinyMCE7プラグイン: mcpuk-picker.js(改名候補: file-picker.js)とFileBrowserResolver.phpのURL変更のみ。EditorInitializer.phpは無変更。
- 将来拡張(今回スコープ外・KCFinder級): クリップボード(コピー/切り取り/貼り付け)、zip一括ダウンロード、画像リサイズ・回転、右クリックコンテキストメニュー、多言語化。APIはactionパラメータ方式のため追加が容易。
