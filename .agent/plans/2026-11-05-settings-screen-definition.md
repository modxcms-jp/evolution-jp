# ExecPlan: グローバル設定の画面を設定の定義から組み、構成を整理する

## Purpose / Big Picture

グローバル設定（`a=17`）の約 100 行を、1 行ずつの手書きから「設定の定義（データ）」と 1 つの描画の部品に置き換える。設定の追加が定義の数行で済み、エスケープと入力エラー時の差し戻しが全行でそろい、コードが減る。そのうえでタブを役割ごとに短い名前で組み直し（ファイルのタブは #1066）、ユーザー編集画面とプロフィールの設定のタブも同じ定義から描いて重複をなくす。

Issue: #1067（親）、#1066（段階 2 の一部と段階 3）

## Progress

- [x] (2026-11-05) ExecPlan を作成（ユーザーさんと決めた前提と、計画で決めた設計を Decision Log に記録）
- [x] (2026-11-08) 段階 1・M1: 比較の道具（`form-snapshot.js`）を足し、main の基準（欄の一覧・画面・保存結果）を取る（c006d37f5、自己レビューの指摘（画面写真が途中で切れる・row がタブの開閉で変わる・時間切れを成功扱い）を 18c09c3ad で直し、基準を取り直した。基準は作業用ディレクトリ `/home/yamamoto/.claude/jobs/be752b0b/tmp/evo-1067/main/`、検証環境 `evo-validate-1067`・`127.0.0.5`）
- [ ] 段階 1・M2: 定義と描画の部品を作り、値の出どころを 1 つにして、ファイルのタブを定義から描く
- [ ] 段階 1・M3: サイト・リソース・キャッシュ・フレンドリーURL のタブを定義から描く
- [ ] 段階 1・M4: セキュリティ・管理画面のタブを定義から描き、旧ヘルパーとタブのファイルを消す（TinyMCE 7 を合わせ、文書を改める）→ PR 1
- [ ] 段階 2・M5: タブ列が折り返しても崩れない CSS（6 テーマ）と、小見出しの描画
- [ ] 段階 2・M6: タブと小見出しを役割ごとに組み替え、名前を短くそろえ、イベントの位置を直す → PR 2
- [ ] 段階 3・M7: ユーザー編集画面（`a=11`/`a=12`）の設定のタブを同じ定義から描く
- [ ] 段階 3・M8: プロフィール（`a=74`）の設定のタブも同じ定義から描き、PHP 7.4 と配布の大きさを確かめて振り返る → PR 3

## Surprises & Discoveries

- 2026-11-05: 入力エラー時の差し戻しが、一部の行にしか効いていない。`settings()`（`manager/actions/tool/mutate_settings/functions.inc.php`）は `evo()->config` を差し戻し前の値で置き換え、差し戻した値は `extract()` した変数にだけ入る。`form_text()` と `config()` で描く行（文字の欄すべてと、ラジオの約半数）は DB の値で出る。たとえば `filemanager_path` の誤りで保存が止まると、直したい `filemanager_path` の欄には打った値ではなく DB の値が出る。差し戻しが効くのは `$use_browser`・`$friendly_urls` など変数で描く行だけ。
- 2026-11-05: `checkConfig()`（`topmenu_*` の `* ` を外す）は効いていない。グローバルの `$settings` を書き換えるが、欄は `form_text()` が `evo()->config` から描くため。
- 2026-11-05: `OnSiteSettingsRender` はサイト・リソース・キャッシュの 3 つのタブの末尾で呼ばれ、引数でタブを区別できない。同梱のプラグインはこのイベントを使っていない（`grep -rn SettingsRender` で確認）。使うプラグインがあると同じ欄が 3 回出て、送信では同じ名前の最後（キャッシュのタブ）の値が勝つので、サイトのタブで変えた値が保存されない（コードからの推測。M1 で試しのプラグインで確かめる）。
- 2026-11-05: `OnMiscSettingsRender` の出力の後ろに `convert_datauri_to_file` の行がある（ファイルのタブの途中）。`OnSystemSettingsRender` は最後のタブの後ろ・表の外で呼ばれ、プラグインがタブ（`.tab-page`）ごと足せる位置になっている。
- 2026-11-05: 見出しと説明に直書きがある。日本語: タブ名「リソース設定」「キャッシュ設定」、「公開開始日時自動設定」の見出しと説明、「旧式のキャッシュ機構」の見出しと説明。英語: `automatic_alias` の選択肢（`pagetitle`・`numbering in each folder`）、`send_errormail` の選択肢（`error` など）、`remember_last_tab` の `(Full)`・`(Stay mode)`。
- 2026-11-05: 保存（`manager/processors/save_settings.processor.php` の `save_settiongs()`）は、送られたキーを許可リスト無しですべて `system_settings` に入れる。設定でない欄（言語の読み込みの select など）は `nonSettingKeys()` の一覧で除いている。画面の欄の名前を変えると、保存の処理も変えることになる。`assets/docs/core-issues.md` に記録した。
- 2026-11-05: 同じ設定の行が 3 か所で手書きされている。グローバル設定のタブ、ユーザー編集画面（`manager/actions/permission/mutate_user/tpl/form/tab_page5.php`・`tab_page7.php`）、プロフィール（`manager/actions/permission/mutate_user_pf.dynamic.php` の `tabPage5`）。プロフィールで保存してよい名前は、`manager/processors/permission/save_user.functions.php` の `profileSettingNames()` にもう一度書かれている。
- 2026-11-05: タブ列の作り。テーマのタブ（`.dynamic-tab-pane-control .tab-row .tab`）は `float: left` で並ぶ。RevoClassic は選ばれたタブだけ下の枠が 0 で、ほかのタブより 1px 低い。高さの違うタブが折り返すと、次の行のタブが低いタブに引っかかって段違いになる（float の性質。推測で、M5 で main で再現を確かめる）。RevoClassic・RubberWhite・RevoStyle の `.tab-row` は float を包まない。
- 2026-11-05: `functions.js` の確認文 `lang_chg` が `js_literal()` を通らずに `<script>` へ埋め込まれている（AGENTS.md の出力のエスケープの決まりから外れる）。
- 2026-11-05: `settings()` に、ロケールが `Japanese_Japan.932`（Windows）のとき 2 つのパスを SJIS から UTF-8 へ変える処理がある。PHP 7.1 から Windows のファイル関数は UTF-8 を受けるので、UTF-8 で保存された値を化けさせる側に働く。main の対象は PHP 7.4 以上。
- 2026-11-05: SMTP の 4 つの欄の `onchange="documentDirty=true;"` は、`a=17` の共通の変更検知（`manager/actions/header.inc.php` の `evoBindDirtyTracking`）と重複している。
- 2026-11-05: 配布の zip（`git archive --format=zip HEAD | wc -c`）は 1,571,241 バイト（HEAD `7801541d0`）。1.5MiB（1,572,864 バイト）まで 1.6KB しかない。この計画はコードを減らす方向なので、各 PR で差を測って本文に書く。
- 2026-11-08（M1）: 欄の一覧はラジオを選択肢ごとに 1 件と数えるので、A は 201 件（計画の「120 件前後」は行の数え方の違い）。B で表示が A と逆になるのは 40 欄・24 行で、条件の行の数と一致した。TinyMCE 7 の表は逆にならない（計画どおり）。
- 2026-11-08（M1）: 状態 B は `use_captcha=1` なので、先に DB へ入れるとログイン画面が captcha を求めて入れない。`form-snapshot.js` に、ログインしてからページを開く前に SQL を流してキャッシュを消す `--sql` を足した。
- 2026-11-08（M1）: C（差し戻し）は Surprises の 1 件めのとおり。`use_browser`・`friendly_urls` は差し戻した値、`filemanager_path`・`site_name`・`upload_maxsize` は DB の値で出た。
- 2026-11-08（M1）: 試しのプラグインで `OnSiteSettingsRender` の欄が 3 回出ることを確かめた。保存すると最後の欄（キャッシュのタブ）の値が勝つ（`system_settings` の値は `x`）。
- 2026-11-08（M1）: 保存のたびに `recent_update` が変わるので、S1・S2 の比較からは除く。S2 の値のうち、インストール直後の値と同じで変化の出ない 6 つ（`datetime_format`・`server_protocol`・`failed_login_attempts`・`rb_base_url`・`new_file_permissions`・`convert_datauri_to_file`）は別の値に変えた（`<work>/fill-s2.json`）。`export_dir` を空で送ると、保存後は既定の `[(base_path)]temp/export/` に戻る。`site_id` は環境ごとにランダムなので、比較は同じ環境で取った JSON 同士で行う。

## Decision Log

ユーザーさんと決めたこと（2026-11-05 / yamamoto・Claude）:

- グローバル設定の構成（タブ名・並び・タブをまたぐ移動・タブのファイル名・言語キー）は自由に組み替えてよい。変えないのは、`system_settings` に保存する設定キーと保存の処理（`save_settings.processor.php`）だけ。
- 表のマークアップは自由に変えてよい。互換を気にするプラグインは同梱の TinyMCE 7 だけ（`assets/plugins/tinymce7/src/TinyMCE7/Settings/SystemSettingsRenderer.php` が `OnInterfaceSettingsRender` で自前の `<table id="editorRow_TinyMCE7" class="settings editorRow">` を出し、エディターの使用の切り替えで表示が変わる）。必要なら同じ作業でプラグイン側も合わせる。TinyMCE 3 は多少崩れてよい。ほかの外部プラグインは考えない。
- コード量を減らすことを目標の 1 つにする。今のヘルパー（`mutate_settings/functions.inc.php`）は手早く作られたもので、置き換えてよい。
- タブ名は短くそろえ、長くても崩れないようにする。ファイルのタブは「ファイル」/"Files"。
- 段階は (1) 設定をデータで定義し 1 つの部品で描く（見た目と構成を変えず、前後で出る設定・値・保存結果が同じことを確かめる）→ (2) 構成の整理（#1066 を含む、全タブ）→ (3) ユーザー編集画面のタブも同じ定義から描く。PR の分け方は計画で決める。

計画で決めたこと（2026-11-05 / Claude（planner））:

- **PR は 1 段階を 1 PR にする**（3 つの PR）。段階 1 は「前後で同じ」を確かめる純粋な作り替えで、段階 2 は見た目と構成を変える。1 つの PR に積むと、段階 1 の同一性の確認が段階 2 の差分に埋もれ、レビューも重い。ブランチの扱いは Artifacts and Notes に書く。いまのブランチ `refactor/1067-settings-screen-definition` は `gh issue develop` で #1067 に紐づいていて、そのブランチの PR はマージで #1067 を閉じる。そこで段階 1・2 は紐づけないブランチで出し、紐づいたブランチは最後の段階 3 に使う。代替案の「段階 1 を紐づいたブランチで出し、マージ後に #1067 を開き直す」は、GitHub への書き込みが段階ごとに要り、閉じた Issue をロードマップの同期が完了と読み違えるおそれがあるため採らない。
- **定義は PHP の配列、描画の部品は 1 つのクラス**にする。定義 `manager/includes/settings_form.definition.inc.php` が配列を返し、`final class EvoSettingsForm`（`manager/includes/settings_form.class.inc.php`）がそれを HTML にする。形は Interfaces and Dependencies に書く。代替案の JSON や YAML は、文言の `lang()`、選択肢の DB からの取得、説明の差し込みが書けないため採らない。行ごとのクラスは、コード量を減らす目標に反するため採らない。名前と置き場所は、既存の `EvoBackupManager`（`backup_manager.class.inc.php`）・`EvoSiteExporter` の作法に合わせる。段階 3 でユーザー画面からも読むので、`manager/actions/tool/` ではなく `manager/includes/` に置く。
- **文言は定義を読むときに `lang()` で引く**。見出し・説明・ラジオのラベルは、言語ファイルとコードからだけ来る信頼する HTML として出す（言語ファイルの文言には `<strong>` や `<a>` が入っている）。設定の値、select の選択肢の値と表示、hidden の値は、描画の部品が出力の直前に必ず `hsc()` する。DB や環境から来る名前（テンプレート・ロール・テーマ・エディター）は select の選択肢としてだけ出すので、部品の `hsc()` に乗る。説明の関数が DB や環境の値を文に差し込むとき（`send_errormail` の送信者、`fixed_site_url` の警告）は、その関数の中で `hsc()` する。代替案の「文言を言語キーの文字列で持ち、部品が `lang()` する」は、直書きの文言（`pagetitle` など）と言語キーの区別が要り、取り違えると表示が変わるため採らない。
- **値の出どころを 1 つにする**。`EvoSettingsForm::storedValues()` が DB の `system_settings` に `default.config.php` を下敷きにした値を返し、`EvoSettingsForm::systemValues()` がそれをもとに、`filemanager_path`・`rb_base_dir`・`export_dir` の先頭の `MODX_BASE_PATH` を `[(base_path)]` に、`base_url` の `[(base_url)]` を `MODX_BASE_URL` に直し、差し戻し（`manager()->hasFormValues()` のとき `manager()->loadFormValues()`）を**全行に**重ねる。`$_POST` には書かず、`extract()` もしない（M2・M3 の間だけ、まだ移していないタブのために残し、M4 で消す）。`evo()->config` は main と同じく、差し戻しを含まない DB と既定値で置き換える（表示中の管理者のユーザー設定を、プラグインがシステムの値と取り違えないため。プラグインの欄の差し戻しは main でも無いので変えない）。代替案の「差し戻しを main と同じ一部の行だけに保つ」は、上の不具合を残すため採らない。この違いは段階 1 の許す違いに入れる。
- **段階 1 では、送る欄の名前を 1 つも変えない**。設定でない欄（`reload_*`、`*_default` の hidden、`old_template`、`reset_template`、`txt_custom_contenttype`、`lst_custom_contenttype`）も今の名前のまま出す。保存の処理の `nonSettingKeys()` に触らずに済み、保存結果が同じことを DB の比較だけで言えるため。
- **条件で出し入れする行は `data-show-if="<名前>=<値>"` と 1 つの共通の JS にする**。初めの表示はサーバーが決め（条件に合わない行に `style="display:none"`）、ラジオを切り替えたときだけ JS が同じ属性の要素を出し入れする。今の 6 組（`use_udperms=1`・`use_editor=1`・`use_browser=1`・`friendly_urls=1`・`use_captcha=1`・`email_method=smtp`）はすべて「1 つのラジオがある値のとき」の形で書ける。TinyMCE 7 の表も同じ属性（`data-show-if="use_editor=1"`）を持つように直す（M4）。プラグインの出力はサーバーが初めの表示を決められないので、段階 2（M6）で JS が読み込み時にも 1 回当てるようにする（段階 1 は、main と同じく切り替えたときだけ）。代替案の「今のクラス名（`editorRow` など）を残す」は、名前ごとに JS が要り、行の型として 1 つにならないため採らない。TinyMCE 3 などクラス名に頼る外部プラグインの欄は切り替えに追従しなくなる（ユーザーさんの判断の範囲）。
- **廃止するもの**: `checkConfig()`（効いていない）、SJIS の変換（PHP 7.4 以上では害になる）、`manager/includes/charsets.php`（`UTF-8` だけの select で、ほかから使われていない）、SMTP の欄の `onchange`（共通の変更検知と重複）、3 か所のインラインの切り替えスクリプト（共通の JS に置き換える）。`lang_chg` は `js_literal()` で埋め込む。
- **段階 1 の「同じ」の定義**: 欄の一覧（名前・種類・値・選択・無効・表示）が main と完全に同じで、保存後の `system_settings` が main と同じこと。見た目はタブごとの画面写真を並べて見比べる。許す違いは Validation and Acceptance の「段階 1 で許す違い」に挙げたものだけにし、ほかの違いは失敗とする。
- **段階 2 の構成は Plan of Work の表のとおりにする**（9 タブ、2026-11-05 にユーザーさんが了承）。ユーザーさんの画面（表示幅 約 1,360px）で、今の 7 タブは約 1,210px。名前から「設定」を外すので、9 タブでも約 1,260px の見込みで 1 段に収まる。狭い画面は M5 の折り返しで 2 段にする。PR 2 の画面写真で幅を確かめ、余裕が足りなければ「キャッシュ」（5 行）を「サイト」に入れて 8 タブにする。
- **小見出しは軽い見出し（`<h3 class="settings-section">`）にし、規則は共通部に 1 か所だけ置く**（2026-11-05、ユーザーさんが決めた）。既存の `.sectionHeader` と `.sectionBody`（帯と白い枠）は、タブの中に並べると重く見えるため採らない。7 テーマそれぞれに規則を足す形もコードが増えるため採らず、`manager/media/style/common/shell.css` に 1 つの規則を置き、色と余白はテーマの変数（`assets/docs/theme-variables.md`・`theme-spacing.md` の決まりにある `--color-*`・`--space-*`、既定値付き）で読む。RevoStyle は凍結のため、既定値で崩れなければよい。新しく変数が要るときは `theme-variables.md` に書く。
- **タブ列は 6 テーマの `.dynamic-tab-pane-control .tab-row` を `display: flex; flex-wrap: wrap` にする**。flex の子には `float` が効かないので、折り返しても段違いにならない。RevoStyle は廃止予定で凍結のため対象外にし、`theme-spacing.md` にそう書く。代替案の `common/shell.css` での一括指定は、テーマの同じ詳細度の規則との読み込み順で結果が変わるため採らない。タブの並びが変わると、覚えたタブの番号（Cookie `webfxtab_settingsPane`）が別のタブを指すことがある。1 回だけのことなので許す。
- **イベントの位置**: 段階 1 は今の位置のまま（`OnSiteSettingsRender` の 3 回も含む）。段階 2 で、`OnSiteSettingsRender` はサイトのタブの末尾で 1 回、`OnInterfaceSettingsRender` はエディターのタブの末尾（受けるのは TinyMCE 7 と TinyMCE 3 で、どちらもエディターの設定のため）、`OnMiscSettingsRender` はファイルのタブの末尾、`OnFriendlyURLSettingsRender`・`OnUserSettingsRender` は今のタブの末尾、`OnSystemSettingsRender` は全タブの後ろにする。
- **段階 3 にプロフィール（`a=74`）を含める**（2026-11-05、ユーザーさんが了承）。同じ行の 3 つめの手書きで、`profileSettingNames()` の名前の重複も定義から引けば消せるため。ウェブユーザーの編集画面（`a=88`）は、グローバル設定と重なる設定が無いので対象外。ユーザー画面のタブは、グローバル設定と同じタブ名・小見出し・順で、ユーザーごとに変えられる行だけを出す。#1066 の「ユーザー編集画面のタブも同じ順・同じ小見出しにそろえる」は段階 3 で満たす（段階 2 では、言語キーの改名に合わせて `tab_page7.php` の見出しのキーだけを直す）。
- **比較の道具は `adaptive-validation-runner` の同梱スクリプトとして足す**（`.claude/skills/adaptive-validation-runner/scripts/form-snapshot.js`）。3 段階のすべてと、ほかのフォームの作り替えでも使えるため。`.claude/` は配布の zip に入らない（`.gitattributes` の `export-ignore`）。

## Outcomes & Retrospective

## Context and Orientation

用語:

- グローバル設定: 管理画面の「ツール → グローバル設定」（`index.php?a=17`）。サイト全体の設定を DB の `system_settings` 表（`setting_name` と `setting_value` の組）に保存する。コードでは「システム設定」とも呼ぶ。
- ユーザー設定: ユーザーごとにグローバル設定を上書きする値。`user_settings` 表に入り、ユーザー編集画面（`a=11` 新規・`a=12` 編集）とプロフィール（`a=74`、自分の設定）で変える。空のままにすると「システム設定に従う」。
- タブ: `manager/media/script/tabpane.js` の `WebFXTabPane`。`<div class="tab-pane">` の中の `<div class="tab-page"><h2 class="tab">見出し</h2>…</div>` をタブにする。設定画面では `tpSettings = new WebFXTabPane(document.getElementById("settingsPane"), …)`。
- 行: 設定画面の表（`<table class="settings">`）の 1 行。左のセル（`th`）に見出し、右のセル（`td`）に入力欄と説明を置く。
- 設定の定義: タブ・小見出し・行の並びと、各行の設定キー・種類・文言・出す条件を書いた PHP の配列。この計画で作る。
- 描画の部品: 定義と値から HTML を作るクラス `EvoSettingsForm`。この計画で作る。
- 特殊な行: 文字・はい／いいえ・選択の普通の型に収まらない行（パスとリセットボタン、言語から文面を読み込む欄など）。部品の中の個別のメソッドで描く。
- 差し戻し: 保存の前の確かめ（存在しないフォルダなど）で保存が止まったとき、打った値を持って設定画面へ戻る動き。保存側が `manager()->saveFormValues(17)` でセッションに入れ、画面側が `manager()->hasFormValues()` と `loadFormValues()` で取り出す（`manager/includes/extenders/ex_managerapi.php`）。取り出すとセッションから消えるので、1 回の表示で 1 回だけ呼ぶ。
- プラグイン用のイベント: 設定画面の決まった位置で `evo()->invokeEvent('On…SettingsRender')` を呼び、プラグインが返した HTML をそこに出す。プラグインの欄も同じフォームで送られ、同じ保存の処理で `system_settings` に入る。
- `[(base_path)]`: サイトの設置先の絶対パス（`MODX_BASE_PATH`）を表す置き換え文字。パスの設定はこの形で保存する。

DocumentParser（`manager/includes/document.parser.class.inc.php`）の `executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` には影響しない（管理画面の表示だけを変える）。保存の処理と、保存後の `evo()->clearCache()` は変えないので、キャッシュの整合性も変わらない。

今の作り（`a=17`）:

1. `manager/index.php` が、グローバルのスコープで `manager/actions/tool/mutate_settings.dynamic.php` を読む（`manager/index.php:721`）。その前に `extract($modx->config)` で設定がグローバル変数になっている（`manager/index.php:54`）。
2. `mutate_settings.dynamic.php` が権限（`settings`）とほかの管理者の編集中（`active_users` の `action=17`）を確かめ、`settings()` で値を作って `extract($settings, EXTR_OVERWRITE)` する。全言語ファイルを読んで `$lang_keys` を作る。
3. 7 つのタブを `include_once` する。各タブは `<tr><th>…</th><td>…</td></tr>` の手書き。値は `config()`・`form_text()`（どちらも `evo()->config`）と、extract した変数（`$use_browser` など）が混ざっている。
4. 末尾のインラインのスクリプトが `WebFXTabPane` を作り、`udPerms`・`editorRow`・`rbRow` の行を出し入れする。`furlRow`（`tab2_cache_settings.inc.php` の中）と `captchaRow`・`emailMethodRow`（`tab3_user_settings.inc.php` の中）は別のスクリプト。
5. 「更新」で `a=30` へ送り、`save_settings.processor.php` が `warnings()` で確かめ、`save_settiongs()` で `$_POST + default.config.php` を `REPLACE INTO system_settings` する。

ファイルと行数（2026-11-05）:

- `manager/actions/tool/mutate_settings.dynamic.php`（134 行）
- `manager/actions/tool/mutate_settings/functions.inc.php`（189 行。`get_lang_keys()`・`get_langs_by_key()`・`get_lang_options()`・`form_text()`・`form_radio()`・`wrap_label()`・`get_role_list()`・`checkConfig()`・`settings()`。ほかから使われていない）
- `manager/actions/tool/mutate_settings/functions.js`（コンテンツタイプの追加・削除、言語からの文面の読み込み `confirmLangChange()`。読み込みは `a=118` の `manager/includes/mutate_settings.ajax.php`）
- タブ（合計 1,664 行）: `tab1_site_settings.inc.php`（サイト `settings_site`、id `tabPage2`）、`tab1_doc_settings.inc.php`（リソース設定・直書き、`tabPageDoc`）、`tab2_cache_settings.inc.php`（キャッシュ設定・直書き、`tabPageCache`）、`tab2_furl_settings.inc.php`（フレンドリーURL `settings_furls`、`tabPage3`）、`tab3_user_settings.inc.php`（セキュリティ `settings_users`、`tabPage4`）、`tab4_manager_settings.inc.php`（管理画面の設定 `settings_ui`、`tabPage5`）、`tab6_filemanager_settings.inc.php`（ファイルマネージャーの設定 `settings_misc`、`tabPage7`）
- `manager/includes/charsets.php`（`UTF-8` の option 1 つ。設定画面だけが読む）
- 言語: `manager/includes/lang/japanese-utf8.inc.php`・`english.inc.php`（この 2 つだけ）
- 文書: `assets/docs/global-settings.md`（設定の追加手順とタブの表）

行の棚卸し（約 106 行）。普通の行は、ラジオ（はい／いいえ・有効／無効・3〜4 択）が約 48、文字の欄が約 30、選択（select）が約 6、文字の複数行（textarea）が 2。特殊な行と特殊な点は次のとおり。

| 行（設定キー） | タブ | 特殊な点 |
|---|---|---|
| `fixed_site_url` | サイト | 説明に、環境変数で固定・未設定・ホスト違いの警告と `MODX_SITE_URL` を差し込む |
| `base_url` | サイト | 説明に `MODX_BASE_URL` を差し込む |
| `site_unavailable_message`・`captcha_words`・`emailsubject`・`signupemail_message`・`websignupemail_message`・`webpwdreminder_message` | サイト・セキュリティ | 見出しのセルに「言語から読み込む」select（`reload_*`）、隠し欄に言語の初期文面（`*_default`）。後ろ 3 つは見出しのセルが `<td nowrap class="warning"><b>` |
| `custom_contenttype` | サイト | 追加・削除のボタンとリスト（`txt_custom_contenttype`・`lst_custom_contenttype`、`functions.js`）と hidden |
| `modx_charset` | サイト | `charsets.php` の select |
| `server_offset_time` | サイト | -24〜24 時間の select。説明に今の時刻と補正後の時刻 |
| `default_template` | リソース | カテゴリーごとの optgroup。今と違う値を選ぶと、一括変更のラジオ（`reset_template`）が出る。hidden `old_template` |
| `auto_pub_date`・`legacy_cache` | リソース・キャッシュ | 見出しと説明が日本語の直書き |
| `automatic_alias`・`send_errormail`・`remember_last_tab` | フレンドリーURL・セキュリティ・管理画面 | 選択肢の名前に英語の直書きが混ざる。`send_errormail` の説明に送信者を差し込む |
| `default_role` | セキュリティ | DB のロールの一覧（id 1 を除く） |
| `use_captcha` | セキュリティ | GD が無いと両方のラジオが無効で「いいえ」 |
| `smtp_host`・`smtp_port`・`smtp_username` | セキュリティ | 値が無いときの既定（`smtp.example.com`・`25`・送信者） |
| `smtppw` | セキュリティ | 値を出さず、伏せ字 `********************` を出す（保存側がこの値を無視する） |
| `manager_theme` | 管理画面 | `manager/media/style/*/style.php` の一覧 |
| `manager_language`・`fe_editor_lang` | 管理画面 | 言語ファイルの一覧 |
| `topmenu_site` ほか 6 つ | 管理画面 | 1 行の中の入れ子の表に 6 つの欄 |
| `resource_tree_sortby_default`・`resource_tree_node_name` | 管理画面 | 選択肢の表示が `[*値*]` |
| `which_editor` | 管理画面 | `OnRichTextEditorRegister` の結果と `none`。結果が配列でなければ select ごと出ない |
| `filemanager_path`・`export_dir`・`rb_base_dir` | ファイル | 既定値の表示とリセットボタン。`rb_base_dir` の既定は `content/` があれば `content/`、無ければ `assets/` |
| `upload_maxsize` | ファイル | 説明にサーバーの上限（`manager()->getUploadMaxsize()`。0 なら説明なし） |
| `image_limit_width`・`rb_base_url` | ファイル | 欄の後ろに `px`、前にサイトの URL |
| 条件の行 | 各タブ | `use_udperms=1`（2 行）、`use_editor=1`（3 行）、`use_browser=1`（4 行）、`friendly_urls=1`（8 行）、`use_captcha=1`（1 行）、`email_method=smtp`（6 行）。計 24 行 |
| イベント | 各タブ | `OnSiteSettingsRender`×3、`OnFriendlyURLSettingsRender`、`OnUserSettingsRender`、`OnInterfaceSettingsRender`、`OnMiscSettingsRender`（ファイルのタブの途中）、`OnSystemSettingsRender`（全タブの後ろ） |

表の外: hidden `a=30`・`site_id`・`settings_version`、`settings_version` が空か 0 のときのインストール後の案内、CSS（`table.settings` のインラインの `<style>`）。

ユーザー画面（段階 3）:

- ユーザー編集画面 `manager/actions/permission/mutate_user.dynamic.php` → `mutate_user/tpl/form.php` がタブを読む。設定のタブは `tab_page5.php`（`settings_ui`: `manager_theme`・`manager_inline_style`・`manager_login_startup`・`manager_language`・`which_editor`・`editor_css_path`・`OnInterfaceSettingsRender`）と `tab_page7.php`（`settings_misc`: `filemanager_path`・`upload_images`・`upload_media`・`upload_files`・`upload_maxsize`・`rb_base_dir`・`rb_base_url`）。値は `user()`（`mutate_user/functions.php`、グローバル `$user` に差し戻しを重ねたもの）。select は先頭に「システム設定に従う」（`user_use_config`、値は空）。`manager_inline_style` と `upload_*` は「システム設定に従う」のチェックボックス `default_<キー>` を持つ。`which_editor`・`editor_css_path` は `use_editor` が 1 でないと隠れ、`rb_base_dir`・`rb_base_url` は `use_browser` が 1 でないと隠れる（切り替えは無い）。`manager_login_startup` はユーザーにしか無い設定。テーマの select は変えると hidden `theme_refresher` に時刻を入れる。`mutate_user/tpl/changed_settings.php` が、画面で変えた欄の名前を `changed_settings` で送る。
- プロフィール `manager/actions/permission/mutate_user_pf.dynamic.php` の `tabPage5` は `tab_page5.php` と同じ行の手書き（値は `setting()`）。プラグインの欄の名前を `fieldNames()` で拾ってセッション `profileSettingNames` に覚える。
- 保存は `manager/processors/permission/save_user.functions.php` の `saveUserSettings()`。この計画では変えない（段階 3 で `profileSettingNames()` の固定の一覧だけを定義から引く）。
- CSS は `mutate_user/tpl/form.php` の先頭に、設定画面と同じ `table.settings` のインラインの `<style>` がある。

テーマとタブ:

- テーマは 7 つ（`manager/media/style/` の AromaHop・AuroraFlow・RevoClassic・RevoStyle・RubberWhite・SakuraFlow・SoftSlate）。新規インストールの既定は RevoClassic。RevoStyle は廃止予定で凍結（`assets/docs/theme-spacing.md` の「対象外のテーマ」）。
- タブの見た目は各テーマの `style.css` の「Tabs」の節（`.dynamic-tab-pane-control .tab-row` と `.tab-row .tab`）。色と余白は `assets/docs/theme-variables.md`・`theme-spacing.md` の決まりに従う。
- `tabpane.js` は見出しを `<span>` で包み、`role="tab"` を付ける。開いたタブの番号を Cookie `webfxtab_<pane の id>` に覚える（`remember_last_tab` が 0 でないとき）。

検証環境: `.claude/skills/adaptive-validation-runner/SKILL.md` と `references/environment.md`。標準の版は `manager/docker/Dockerfile` の `php:8.4-apache` と `mariadb:11`。PHP 7.4 は同じ文書の「ほかの PHP の版で検証するとき」の手順で作る。

## Plan of Work

### 段階 1（M1〜M4、PR 1）: 定義から描く。見た目と構成は変えない

M1 で、先に比較の道具と基準を作る。`form-snapshot.js` は、ログインしてページを開き、フォームの名前のある欄をすべて DOM の順に JSON へ書き出す（タブの見出し、行の見出し、名前、要素、種類、値、選択、無効、表示）。指定すれば、タブごとの画面写真、値を入れて送信、送信後の画面の書き出しもする。これを今の HEAD（main と同じコード）で流し、状態 A・B・C と保存 S1・S2 の基準を作業用ディレクトリに残す。試しのプラグインで `OnSiteSettingsRender` の重複も確かめておく。

M2 で、部品の骨組みを作る。定義ファイル（全タブの枠）と `EvoSettingsForm` を足し、`mutate_settings.dynamic.php` の値の作り方を `EvoSettingsForm::systemValues()` に替える（`settings()` と `$_POST` への書き込みをやめる）。まだ移していないタブのために、M2・M3 の間だけ `extract($values, EXTR_OVERWRITE)` を残す（M4 で消す）。最初に移すのはファイルのタブ。パスとリセット、条件の行、途中のイベント、欄の前後の文字、説明の関数と、部品の型がいちばん多く出るので、ここで形を固める（試作）。

M3 で、サイト・リソース・キャッシュ・フレンドリーURL を移す。特殊な行は `lang_text`（言語から読み込む欄）、`content_types`、`template` の 3 つ。直書きの日本語と英語は、段階 1 では今の文字のまま定義に書く。

M4 で、セキュリティと管理画面を移し、後片付けをする。特殊な行は `topmenu` と `password`。条件の行の切り替えを共通の JS（`functions.js`）にまとめ、TinyMCE 7 の表に `data-show-if` を付ける。`functions.inc.php`・7 つのタブのファイル・`charsets.php` を消す。`assets/docs/global-settings.md` の「設定画面への表示」を定義の書き方に改める（タブの表は段階 2 で改める）。最後に、状態 A・B・C と S1・S2 を main の基準と比べ、PHP 7.4 でも表示と保存を確かめて PR 1 を出す。

### 段階 2（M5〜M6、PR 2）: 構成を整理する

M5 で、まず main（段階 1 の後）でタブ列が崩れることを再現する（幅 768px、RevoClassic など）。6 テーマの `.dynamic-tab-pane-control .tab-row` に `display: flex; flex-wrap: wrap;` を足し（`display: flow-root` を持つテーマはそれを置き換える）、崩れないことを確かめる。`theme-spacing.md` の「画面とテーマの約束」に、タブ列を折り返せる作りにすることと、RevoStyle が対象外であることを書く。あわせて、定義の小見出し（section の `title`）を、共通部の 1 つの規則で描く軽い見出しとして出せるようにする（見出しの無い section は今の表だけ）。

M6 で、定義を下の表の構成に組み替える。設定キーは 1 つも増減させない。タブの id は `settingsTab` に英語の名前を付けたもの（`settingsTabSite` など）にする。

| タブ（日本語 / 英語、言語キー） | 小見出し（言語キー `settings_section_*`）と行 |
|---|---|
| サイト / Site（`settings_site`） | 基本（`site_status`・`site_name`・`site_slogan`・`track_visitors`）、URL（`fixed_site_url`・`base_url`・`server_protocol`・`xhtml_urls`）、特別なページ（`site_start`・`error_page`・`unauthorized_page`・`site_unavailable_page`・`site_unavailable_message`）、文字コードと時刻（`modx_charset`・`server_offset_time`）。末尾に `OnSiteSettingsRender` |
| リソース / Resources（`settings_resources`、新規） | 新しいリソースの初期値（`default_template`・`auto_template_logic`・`publish_default`・`cache_default`・`search_default`・`auto_menuindex`・`auto_pub_date`）、作成と編集（`docid_incrmnt_method`・`enable_draft`・`custom_contenttype`） |
| キャッシュ / Cache（`settings_cache`、新規） | 小見出しなし（`cache_type`・`disable_cache_at_login`・`individual_cache`・`conditional_get`・`legacy_cache`） |
| フレンドリーURL / Friendly URLs（`settings_furls`） | 小見出しなし（今の 9 行）。末尾に `OnFriendlyURLSettingsRender` |
| セキュリティ / Security（`settings_users`） | ユーザーとグループ（`default_role`・`allow_mgr2web`・`use_udperms`・`udperms_allowroot`・`tree_show_protected`）、ログイン（`failed_login_attempts`・`blocked_minutes`・`use_captcha`・`captcha_words`）、エラー（`error_reporting`・`warning_visibility`・`send_errormail`）、テンプレート変数（`enable_bindings`）。末尾に `OnUserSettingsRender` |
| メール / Mail（`settings_mail`、新規） | 送信（`emailsender`・`email_method`・`smtp_auth`・`smtp_host`・`smtp_port`・`smtp_username`・`smtppw`・`smtp_secure`・`modxmailer_log`）、メールの文面（`emailsubject`・`signupemail_message`・`websignupemail_message`・`webpwdreminder_message`） |
| 管理画面 / Manager（`settings_ui`、文言を変える） | 表示（`manager_theme`・`manager_language`・`manager_inline_style`・`datetime_format`・`datepicker_offset`・`remember_last_tab`）、ツリー（`tree_pane_open_default`・`tree_page_click`・`resource_tree_sortby_default`・`resource_tree_node_name`・`manager_treepane_trim_title`・`manager_docs_orderby`・`limit_by_container`）、メニュー（`topmenu_*`）、件数（`top_howmany`・`number_of_logs`・`number_of_results`）、メッセージ（`pm2email`・`mail_check_timeperiod`・`number_of_messages`）、その他（`automatic_optimize`） |
| エディター / Editor（`settings_editor`、新規） | 小見出しなし（`use_editor`・`which_editor`・`fe_editor_lang`・`editor_css_path`）。末尾に `OnInterfaceSettingsRender` |
| ファイル / Files（`settings_files`、`settings_misc` を改名） | ファイルピッカー（`use_browser`・`rb_base_dir`・`rb_base_url`・`rb_webuser`・`strip_image_paths`）、ファイルマネージャー（`filemanager_path`）、アップロード（`upload_files`・`upload_images`・`upload_media`・`upload_maxsize`・`new_file_permissions`・`new_folder_permissions`・`clean_uploaded_filename`・`convert_datauri_to_file`・`image_limit_width`）、HTML エクスポート（`export_dir`）。末尾に `OnMiscSettingsRender` |

直書きの文言は言語キーにする（日本語と英語の両方）: 公開開始日時自動設定 → `auto_pub_date_title`・`auto_pub_date_message`、旧式のキャッシュ機構 → `setting_legacy_cache`・`setting_legacy_cache_desc`（同じタブの `setting_*` にそろえる）、`numbering in each folder` → `automatic_alias_opt_numbering`（フォルダごとの連番 / Numbering in each folder）。`pagetitle`・`error`・`ssl` などの技術用語と `(Full)`・`(Stay mode)` はそのまま。`settings_misc` を消して `settings_files` を足し、`tab_page7.php` の見出しのキーを合わせる。言語ファイルのほかの文言にタブ名の言及（「管理画面の設定」「ファイルマネージャーの設定」など）があれば直す。`assets/docs/global-settings.md` のタブの表を新しい構成に改める。

### 段階 3（M7〜M8、PR 3）: ユーザー画面も同じ定義から描く

定義の行に `scope`（`system`・`user`・`profile` の配列、既定は `['system']`）を持たせ、ユーザーごとに変えられる行に `user`・`profile` を付ける。`manager_login_startup` は `['user', 'profile']` だけの行として、管理画面のタブの「表示」に置く。`EvoSettingsForm` の `$scope` が `user` か `profile` のとき、部品は次のように描く。

- その scope の行だけを、グローバル設定と同じタブ名・小見出し・順で出す。行の無いタブと小見出しは出さない
- select の先頭に「システム設定に従う」（値は空）を足す
- `userDefault` を持つ行（`manager_inline_style`・`upload_images`・`upload_media`・`upload_files`）に、`default_<キー>` のチェックボックスを付ける（ユーザーの値が無いと入る）
- 条件の行は、システム設定の値で出すかどうかを決め、切り替えはしない
- イベント行は、その scope に含まれるもの（`OnInterfaceSettingsRender`）だけを呼ぶ

M7 で、ユーザー編集画面の `tab_page5.php`・`tab_page7.php` を消し、`form.php` で部品のタブ（管理画面・エディター・ファイル）を出す。値は今の `$user`（差し戻し込み）を渡す。テーマの `theme_refresher`、`upload_maxsize` の既定（ユーザーの値、無ければシステムの値、無ければサーバーの上限）、`upload_*` の説明の後ろの `user_upload_message` は、行の定義の項目（`userAttr`・`userValue`・`userDesc`）で書く。M8 で、プロフィールの `tabPage5` を部品のタブ（管理画面・エディター）に置き換え、`profileSettingNames()` の固定の一覧を `EvoSettingsForm::names('profile')` から引く。`table.settings` のインラインの `<style>` は、部品の `EvoSettingsForm::style()` から 3 画面で出す（中身は今と同じ）。

## Concrete Steps

共通: 作業ディレクトリは `/home/yamamoto/ghq/github.com/modxcms-jp/evolution-jp/.claude/worktrees/issue-1067`（以下「作業ツリー」）。`<work>` は作業ツリーの外の作業用ディレクトリ（例: `/tmp/evo-1067`）。検証環境の名前とアドレスは `EVO_VALIDATE_NAME=evo-validate-1067 EVO_BIND_IP=<空いているアドレス、例 127.0.0.5>` とし、docker compose と同梱スクリプトの実行のたびに先頭に付ける（以下 `<env>` と書く）。起動・Playwright の用意・インストール（`setup-site.js --sample --editor`）・スモークテストは `references/environment.md` の「起動からスモークテストまで」のとおり。DB とアプリのコンテナは `evo-validate-1067-db`・`evo-validate-1067-app`。

よく使うコマンド:

    # DB 全体を控える・戻す（戻したらキャッシュも消す）
    docker exec evo-validate-1067-db mariadb-dump -uuser -ppassword evodb > <work>/db-A.sql
    docker exec -i evo-validate-1067-db mariadb -uuser -ppassword evodb < <work>/db-A.sql
    docker exec -w /var/www/html evo-validate-1067-app php evo cache:clear
    # 保存結果を書き出す
    docker exec evo-validate-1067-db mariadb -uuser -ppassword evodb -N -e "SELECT setting_name, setting_value FROM modx_system_settings ORDER BY setting_name" > <work>/<版>/S1.tsv
    # 欄の一覧を書き出す（--shots を付けるとタブごとの画面写真も撮る）
    <env> NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/form-snapshot.js --path 'index.php?a=17' --out <work>/<版>/A.json --shots <work>/<版>/A
    # 比べる
    diff -u <work>/main/A.json <work>/branch/A.json

### M1: 比較の道具と main の基準

編集対象: `.claude/skills/adaptive-validation-runner/scripts/form-snapshot.js`（新規）、`.claude/skills/adaptive-validation-runner/SKILL.md`（References の「同梱スクリプト」に 1 行足す）。

`form-snapshot.js` の仕様（`lib.js` の `login()`・`newPage()`・`clickAlertOk()` を使い、`smoke.js` を見本にする）:

- 引数: `--path <manager からの相対 URL>`（必須）、`--out <JSON>`、`--shots <ディレクトリ>`、`--fill <JSON>`、`--submit <セレクター>`、`--width <px>`（既定 1280）、`--user <ログイン名>`（既定は管理者）、`--sql <SQL>`（ログインしてからページを開く前に流し、キャッシュを消す。DB は元に戻さないので、戻すのは呼び出し側。M1 で足した）
- `--shots` は、開いているタブの高さにビューポートを合わせて全体を撮る（高さが足りなければ `[warn]`）。`--submit` で 30 秒のうちにフォームが差し替わらなければ `FAIL` で終了コード 1。`row` はセルの最初の `<br>` かブロック要素までの文字（タブが開いているかに依らない）。`tabRow` の位置は `.tab-pane` からの相対。`csrf_token`・`sessToken` の値は伏せる
- `--fill` の JSON は `{"名前": "値"}`。値は DOM に直接入れて `input` と `change` を起こす（隠れたタブや行の欄にも入れるため）。ラジオは同じ名前で値が合うものを選び、select は値を選び、hidden も値を入れ、チェックボックスは値が空でなければ入れ、空なら外す。文字の欄・select・hidden は、同じ名前の欄が複数あれば最初の 1 つだけに入れる（重複した欄の動きを確かめるため）
- `--submit` があれば、`--fill` の後にそのセレクターを押し、確認のダイアログは受け入れ、`<dialog>` の警告は `clickAlertOk()` で閉じ、遷移が落ち着くのを待つ。`--out` があれば、着いたページを書き出す（JSON の `url` に着いた URL）
- 書き出す JSON: `{"url", "tabs": [見出しの文字…], "tabRow": [{"text","x","y","w","h"}…], "fields": [{"tab","row","name","tag","type","value","checked","selected","disabled","visible"}…]}`。`fields` は `form` の中の名前のある欄を DOM の順に並べる。`tab` は最も近い `.tab-page` の `tabPage.tab` の文字（`tabpane.js` が `h2.tab` をタブ列へ移すので、`.tab-page` の中を探しても見つからない）、`row` は最も近い `table.settings` の直下の行の最初のセルの文字の 1 行目、`value` は select では選ばれた値、`selected` は select の選択肢の `[値, 表示]` の配列、`visible` はその欄から `.tab-page` までの祖先と自身の計算後の `display` に `none` が無いこと（タブが開いているかは問わない）。`csrf_token` の値は `"<masked>"` にする
- `--shots` があれば、最初の `.tab-pane` のタブを順に押し、`.tab-pane` を `tab<番号>.png` で撮る。`tabRow` はタブの見出しの位置と大きさ（`getBoundingClientRect()`）

基準の取り方（今の HEAD は main と同じコード）:

1. 検証環境を起動し、インストールしてスモークテストを通す
2. 状態 A に揃えて控える:
   `docker exec evo-validate-1067-db mariadb -uuser -ppassword evodb -e "REPLACE INTO modx_system_settings (setting_name, setting_value) VALUES ('use_udperms','0'),('use_editor','1'),('use_browser','1'),('friendly_urls','1'),('use_captcha','0'),('email_method','mail')"`、キャッシュを消し、`db-A.sql` に控える
3. A: `form-snapshot.js --path 'index.php?a=17' --out <work>/main/A.json --shots <work>/main/A`
4. B: 2 の 6 つを逆（`1`・`0`・`0`・`0`・`1`・`smtp`）にして書き出す（`main/B.json`・`main/B/`）。`use_captcha=1` を先に DB へ入れるとログインできないので、`--sql "REPLACE INTO modx_system_settings ..."` で、ログインの後に入れる。終わったら `db-A.sql` に戻す
5. C（差し戻し）: `--fill` に `{"filemanager_path":"/nonexistent-1067/","site_name":"restore-1067","upload_maxsize":"12345","use_browser":"0","friendly_urls":"0"}` を渡し、`--submit '#Button1 a' --out <work>/main/C.json`。`db-A.sql` に戻す
6. S1: `--submit '#Button1 a'`（何も変えずに保存）→ `S1.tsv` を書き出し、`recent_update` が変わったこと（保存されたこと）を確かめてから、`db-A.sql` に戻す。比較では `recent_update` の行を除く
7. S2: `<work>/fill-s2.json` を作り、`--fill` と `--submit` で保存 → `S2.tsv`、`db-A.sql` に戻す。中身は型ごとに 1 つ以上を変えるもの: `site_name`（`<b>"&'` を含む文字）、`site_slogan`（2 行）、`error_page`、`xhtml_urls`、`server_offset_time`（`3600`）、`server_protocol`（`https`）、`custom_contenttype`（hidden に `application/json,text/css`）、`auto_template_logic`（`parent`）、`enable_draft`、`cache_type`（`2`）、`friendly_url_suffix`（`.htm`）、`automatic_alias`（`1`）、`use_udperms`（`1`）、`udperms_allowroot`（`1`）、`default_role`（2 番目の選択肢）、`failed_login_attempts`（`5`）、`error_reporting`（`2`）、`send_errormail`（`3`）、`email_method`（`smtp`）、`smtp_host`、`smtp_secure`（`tls`）、`emailsubject`、`topmenu_site`、`resource_tree_node_name`（`menutitle`）、`datetime_format`（`YYYY/mm/dd`）、`use_editor`（`0`）、`which_editor`（`none`）、`filemanager_path`（`[(base_path)]assets/`）、`export_dir`（空）、`upload_maxsize`（`1048576`）、`new_file_permissions`（`644`）、`rb_base_url`（`content/`）、`convert_datauri_to_file`（`1`）、`tinymce7_toolbar_preset`（`simple`）。`smtppw` と `manager_language` は変えない（`smtppw` は保存時に乱数が付く。言語は次の画面の文言が変わる）
8. 重複の確認: 試しのプラグインを入れる。
   `docker exec evo-validate-1067-db mariadb -uuser -ppassword evodb -e "INSERT INTO modx_site_plugins (name, plugincode) VALUES ('evo1067dup', 'if (evo()->event->name === ''OnSiteSettingsRender'') { evo()->event->output(''<table class=\"settings\"><tr><th>dup</th><td><input type=\"text\" name=\"evo1067_dup\" value=\"x\"></td></tr></table>''); }'); INSERT INTO modx_site_plugin_events (pluginid, evtid) VALUES (LAST_INSERT_ID(), 82)"`、キャッシュを消して A と同じく `main/dup.json` を書き出す。`db-A.sql` に戻す

期待: `main/A.json` の `fields` が 120 件前後（設定の欄・設定でない欄・プラグインの欄。ラジオを選択肢ごとに数えると 201 件。Surprises を参照）、`tabs` が今の 7 つ。B では条件の 24 行の欄の `visible` が A と逆（TinyMCE 7 の表は初めの表示が条件に従わないので逆にならない）。C では `use_browser`・`friendly_urls` は差し戻した値、`filemanager_path`・`site_name`・`upload_maxsize` は DB の値（Surprises の 1 件め）。`dup.json` に `evo1067_dup` が 3 件（サイト・リソース設定・キャッシュ設定のタブ）。`git -C <作業ツリー> status --short` に `form-snapshot.js` と `SKILL.md` 以外が無い。

コミット: `test(validation): フォームの欄と画面写真を書き出す確認スクリプトを足す`

### M2: 定義と描画の部品、ファイルのタブ

編集対象: `manager/includes/settings_form.class.inc.php`（新規）、`manager/includes/settings_form.definition.inc.php`（新規）、`manager/actions/tool/mutate_settings.dynamic.php`、`manager/actions/tool/mutate_settings/tab6_filemanager_settings.inc.php`（消す）、`manager/actions/tool/mutate_settings/functions.inc.php`（`settings()` を消す。ほかは M4 まで残す）、`manager/actions/tool/mutate_settings/functions.js`（条件の行の共通の切り替え）。

1. `EvoSettingsForm` を Interfaces and Dependencies の形で作る。普通の型（`text`・`textarea`・`radio`・`yesno`・`enabled`・`select`）、`event`、特殊な型 `path` を実装する。`text` は今の `form_text()` と同じく、`size`（maxlength、既定 255）が 10 以下なら `style="width:<size>em;"` を付ける
2. 定義ファイルに 7 つのタブを今の id・見出しで並べ、ファイルのタブの行を今の順で書く（`OnMiscSettingsRender` は `convert_datauri_to_file` の前の `event` 行）。ほかの 6 タブは `'legacy' => 'tab1_site_settings.inc.php'` のように今のファイル名だけを持たせ、M3・M4 で行に置き換える（M4 の終わりに `legacy` の項目ごと消す）
3. `mutate_settings.dynamic.php`: `settings()` の代わりに `evo()->config = EvoSettingsForm::storedValues();` と `$values = EvoSettingsForm::systemValues();`、`extract($values, EXTR_OVERWRITE)`（M4 で消す）。タブは定義を順に回し、`legacy` を持つタブはこのファイルの中で `include` し（今のタブのファイルはグローバルのスコープの変数 `$_lang`・`$modx`・extract した値に頼るので、部品のメソッドの中では読めない）、ほかは `$form->renderTab($tab)` を出す。全タブの後ろで `OnSystemSettingsRender`。`lang_chg` は `js_literal()` で出す。`udPerms`・`editorRow` の切り替えと `$lang_keys` は、まだ移していないタブのために M4 まで残す
4. `functions.js` に、`[data-show-if]` を持つ要素を、同じ名前のラジオの `change` で出し入れする関数を足す（`Evo.ready` で 1 回だけ結ぶ）

実行: `docker run --rm -v <作業ツリー>:/app -w /app php:7.4-cli php -l manager/includes/settings_form.class.inc.php`（定義ファイルと `mutate_settings.dynamic.php` も同じく）。M1 の 3〜7 を `branch/` に書き出して `diff -u`。

期待: `php -l` が 3 つとも `No syntax errors detected`（7.4 と 8.4 の両方）。A・B・S1・S2 は main と差が無い。C はファイルのタブの `filemanager_path`・`upload_maxsize` が差し戻した値になる差だけ。A のファイルのタブの画面写真が main と見分けられない。`git status --short` の変更が上の編集対象だけ。

コミット: `refactor(settings): 設定の定義と描画の部品を作り、ファイルのタブを定義から描く`

### M3: サイト・リソース・キャッシュ・フレンドリーURL

編集対象: 定義ファイル、`EvoSettingsForm`（`lang_text`・`content_types`・`template` と、`languages()`・`languagesHavingKey()` を足す）、`tab1_site_settings.inc.php`・`tab1_doc_settings.inc.php`・`tab2_cache_settings.inc.php`・`tab2_furl_settings.inc.php`（消す）。`functions.inc.php` の `get_lang_*()` は、まだ移していないセキュリティのタブが使うので M4 で消す。

- `lang_text` は、見出しのセルに「言語から読み込む」select（名前は定義の `reload`、先頭に `language_title` の空の選択肢、その言語キーを持つ言語だけ）と、隠し欄（名前は `langKey` が `_default` で終われば `langKey`、そうでなければ `langKey . '_default'`、id は `langKey . '_hidden'`）を出す。select の `onchange` は `confirmLangChange(this, '<langKey>', '<欄の id>')`。欄の id は定義の `id`（今の id）。`warningHeading` が真なら見出しのセルを今と同じ `<td nowrap class="warning" valign="top"><b>…</b>` にする
- `template` は今の `tab1_doc_settings.inc.php` の出力と同じ欄（optgroup、`(blank)`、一括変更のラジオ、`old_template`、`onchange`）を出す
- 直書きの文言は、定義でそのまま文字で書く（例 `'label' => '公開開始日時自動設定'`）
- 条件の行（`friendly_urls=1`）は `showIf`。`tab2_cache_settings.inc.php` の中の切り替えスクリプトは消す
- `OnSiteSettingsRender` は 3 か所とも今の位置の `event` 行

実行と期待: M2 と同じ比較。A・B・S1・S2 は main と差が無い。C の差は、M2 の差に `site_name` が差し戻した値になる差を足したものだけ。4 つのタブの画面写真が main と見分けられない。言語から読み込む select を動かすと、確認のあとに欄の文面が変わる（Playwright で `reload_site_unavailable` を `english` にし、`site_unavailable_message` の値が英語の初期文面になる）。`dup.json` 相当（M1 の 8）が main と同じく 3 件。

コミット: `refactor(settings): サイト・リソース・キャッシュ・フレンドリーURLのタブを定義から描く`

### M4: セキュリティ・管理画面と後片付け

編集対象: 定義ファイル（`legacy` の項目を消す）、`EvoSettingsForm`（`topmenu`・`password` を足す）、`tab3_user_settings.inc.php`・`tab4_manager_settings.inc.php`・`functions.inc.php`・`manager/includes/charsets.php`（消す）、`mutate_settings.dynamic.php`（`extract()`・`$lang_keys`・`legacy` の分かれ道・残りの切り替えスクリプトを消し、`$form->render()` だけにする）、`functions.js`、`assets/plugins/tinymce7/src/TinyMCE7/Settings/SystemSettingsRenderer.php`（表に `data-show-if="use_editor=1"` を足す）、`assets/docs/global-settings.md`（「3. 設定画面への表示」を定義の書き方に。表は段階 2）、`assets/docs/core-issues.md`（このとき解決したものがあれば付記）。

- `use_captcha` は `disabled` と `value`（GD が無ければ `'0'`）で書く
- `smtp_host`・`smtp_port`・`smtp_username` は `default`（`smtp_username` の既定は `emailsender` の値なので、`default` に関数を書ける形にする）
- `which_editor` の選択肢の関数が `null` を返したら、欄を出さずに説明だけを出す
- `manager_theme`・`manager_language`・`fe_editor_lang`・`default_role` の選択肢は関数（`EvoSettingsForm::themes()`・`languages()`・`roles()`）
- 消したあとで `grep -rn "form_radio\|form_text\|wrap_label\|get_lang_options\|charsets.php\|checkConfig" manager/ assets/ install/` が何も出さない（`install/functions.php` の別物の `get_lang_options()` は除く）

実行と期待: M2 と同じ比較に加え、次を行う。

- 全体の比較: A・B・S1・S2 は main と差が無い。C の差は Validation and Acceptance の「段階 1 で許す違い」の 1 だけ。画面写真の差は「許す違い」の 2 だけ
- TinyMCE 7: 管理画面のタブで「リッチテキストエディターの使用」を「いいえ」にすると `#editorRow_TinyMCE7` が隠れ、「はい」で戻る（Playwright）
- `php .github/scripts/security-lint.php` が `0 violations`
- PHP 7.4: 環境の文書のとおり `php:7.4-apache` の Dockerfile で別の環境（`EVO_VALIDATE_NAME=evo-validate-1067-74`、別のアドレス）を作り、インストールして A を書き出し、S1 で保存する。`docker exec -w /var/www/html evo-validate-1067-74-app php evo log:search "" --level=error --limit=10` にこの画面のエラーが無い。終わったら片付ける
- 配布の大きさ: `git -C <作業ツリー> archive --format=zip HEAD | wc -c` が M1 の前（1,571,241 バイト）より小さい。差を PR の本文に書く
- `/doc-audit` を `global-settings.md` に対して流し、指摘を直す

コミット（3 つに分ける）: `refactor(settings): セキュリティと管理画面のタブを定義から描き、旧ヘルパーを消す`、`refactor(tinymce7): 設定の表を data-show-if で切り替えられるようにする`、`docs(settings): 設定画面への追加手順を定義の書き方に改める`

PR 1 を出す（Artifacts and Notes のブランチ、`Refs #1067`）。本文の確認方法に、A・B・C・S1・S2 の比較の表と許す違いを書く。

### M5: タブ列と小見出し

編集対象: `manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RubberWhite,SakuraFlow,SoftSlate}/style.css`（`.dynamic-tab-pane-control .tab-row` の規則）、`assets/docs/theme-spacing.md`、`manager/media/style/common/shell.css`（小見出しの規則 1 つ）、`assets/docs/theme-variables.md`（新しい変数を使うときだけ）、`EvoSettingsForm`（section の `title` を小見出しとして描く）。

1. 再現: main（段階 1 の後）の検証環境で、テーマを順に `UPDATE modx_system_settings SET setting_value='<テーマ>' WHERE setting_name='manager_theme'` にしてキャッシュを消し、`form-snapshot.js --path 'index.php?a=17' --width 768 --out <work>/main/tabs-<テーマ>.json --shots <work>/main/tabs-<テーマ>` を撮る。`tabRow` のどの 2 つの箱も重ならないか、同じ行のタブの `y` がそろうかを、`node -e` で `tabRow` を読んで確かめる。崩れたテーマと幅を Surprises に書く。768px で再現しなければ 600px で撮り直す。それでも再現しなければ「main では崩れを再現できなかった」と Surprises に書き、2 の CSS は、段階 2 でタブが 9 つに増えても折り返しで段違いにならないための予防として入れる
2. 6 テーマに `display: flex; flex-wrap: wrap;` を足す（`display: flow-root` は置き換える。`white-space: nowrap` は残す）。RevoStyle は変えない
3. `theme-spacing.md` の「画面とテーマの約束」に、`.tab-row` を `flex-wrap: wrap` で折り返す作りにすること、RevoStyle は対象外のことを書く
4. 定義の section に `title` があれば `<h3 class="settings-section">見出し</h3><table class="settings">…</table>` で描く（この時点の定義には `title` が無いので、画面は変わらない）。`shell.css` に `.tab-page .settings-section` の規則を 1 つ足し、色・大きさ・上下の余白はテーマの変数を既定値付きで読む。テーマの `style.css` には足さない

期待: 6 テーマとも、幅 768px・1024px・1280px で `tabRow` の箱が重ならず、同じ行のタブの `y` がそろう（main で崩れたものが直る）。RevoStyle の画面写真は main と同じ。リソースの編集画面（`a=27`）のタブも崩れない。

コミット: `style(theme): タブ列を折り返せるようにし、名前が長くても崩れないようにする`、`feat(settings): 設定のタブの中に小見出しを付けられるようにする`

### M6: 構成の組み替え

編集対象: 定義ファイル、`manager/includes/lang/japanese-utf8.inc.php`・`english.inc.php`、`manager/actions/permission/mutate_user/tpl/form/tab_page7.php`（見出しのキーだけ）、`assets/docs/global-settings.md`（タブの表）。

1. Plan of Work の表のとおりに組み替え、言語キーを足す・改める（日本語と英語の両方。英語の小見出しは Basics・URL・Special pages・Charset and time・Defaults for new resources・Creating and editing・Users and groups・Login・Errors・Template variables・Sending・Message texts・Display・Tree・Menu・Counts・Messages・Other・File picker・File manager・Uploads・HTML export）
2. `OnSiteSettingsRender` をサイトのタブの末尾の 1 か所にし、ほかのイベントも Decision Log の位置へ動かす。`lang_text` の `warningHeading` をやめ、メールの文面の 3 行の見出しもほかの行と同じ `th` にする。`functions.js` の条件の切り替えを読み込み時にも 1 回当て、`use_editor` が 0 のときは TinyMCE 7 の表も初めから隠す（段階 1 では main に合わせて、切り替えたときだけ隠していた）
3. `grep -rn "settings_misc" manager/ assets/` が何も出さない。`grep -n "管理画面の設定\|ファイルマネージャーの設定\|リソース設定\|キャッシュ設定" manager/includes/lang/japanese-utf8.inc.php` で、タブ名を指す文言が残っていない（キャッシュの設定の見出し `setting_cache_type` のような、タブ名でない言葉は残す）

実行と期待:

- A の名前の一覧が main と同じ集まり: `node -e` で `main/A.json` と `branch/A.json` の `fields` の `name` を並べ替えて比べ、同じ。名前ごとの `value`・`checked`・`selected`・`disabled`・`visible` も同じ（違うのは `tab` と `row` と並び）
- `tabs` が `["サイト","リソース","キャッシュ","フレンドリーURL","セキュリティ","メール","管理画面","エディター","ファイル"]`。`manager_language` を `english` にした管理者で `["Site","Resources","Cache","Friendly URLs","Security","Mail","Manager","Editor","Files"]`
- S1・S2 の `system_settings` が main と同じ（`--fill` は名前で入れるので、並びが変わっても同じ JSON で流せる）
- M1 の 8 の試しのプラグインで、`evo1067_dup` が 1 件（サイトのタブ）。`--fill '{"evo1067_dup":"y"}' --submit '#Button1 a'` で保存すると、`system_settings` の `evo1067_dup` が `y`。main で同じことをすると、最初の欄（サイトのタブ）にだけ `y` が入り、最後の欄（キャッシュのタブ）の `x` が保存される
- TinyMCE 7 の表がエディターのタブに出て、「リッチテキストエディターの使用」で隠れる・戻る
- 7 テーマ × 9 タブの画面写真を PR に付ける（ファイルのタブは全テーマ、ほかは RevoClassic と AuroraFlow）

コミット: `refactor(settings): タブを役割ごとに組み替え、名前を短くそろえる`、`fix(settings): OnSiteSettingsRender をサイトのタブで 1 回だけ呼ぶ`、`docs(settings): グローバル設定のタブの構成の表を改める`

PR 2 を出す（`Refs #1067`、`Refs #1066`）。

### M7: ユーザー編集画面

編集対象: 定義ファイル（`scope`・`userDefault`・`userAttr`・`userValue`・`userDesc` と `manager_login_startup` の行）、`EvoSettingsForm`（`$scope` が `user`・`profile` のときの描き方、`names()`、`style()`）、`manager/actions/permission/mutate_user/tpl/form.php`、`tab_page5.php`・`tab_page7.php`（消す）、`manager/actions/tool/mutate_settings.dynamic.php`（`<style>` を `EvoSettingsForm::style()` に）。

基準（main で取る）: editor ユーザーの id を `docker exec evo-validate-1067-db mariadb -uuser -ppassword evodb -N -e "SELECT id FROM modx_manager_users WHERE username='editor'"` で調べ、ユーザー設定を入れる。
`REPLACE INTO modx_user_settings (user, setting_name, setting_value) VALUES (<id>,'manager_theme','AuroraFlow'),(<id>,'upload_images','jpg,png'),(<id>,'rb_base_dir','[(base_path)]assets/'),(<id>,'which_editor','none'),(<id>,'tinymce7_menubar','0')`。DB を `db-U.sql` に控え、`form-snapshot.js --path 'index.php?a=12&id=<id>' --out <work>/main/U.json --shots <work>/main/U` を撮る。何も変えずに保存（`--submit '#Button1 a'`）して `SELECT setting_name, setting_value FROM modx_user_settings WHERE user=<id> ORDER BY setting_name` を `US1.tsv` に、`{"manager_language":"english","upload_media":"mp4","default_upload_images":"1","editor_css_path":"/x.css","filemanager_path":"[(base_path)]content/"}` を入れて保存して `US2.tsv` に書き出す（各回の前に `db-U.sql` に戻す）。

実行と期待:

- `U.json` の名前の集まり・名前ごとの値が main と同じ。タブは「セキュリティ」の後に「管理画面」「エディター」「ファイル」が続き、ファイルのタブの小見出しと並びがグローバル設定のファイルのタブと同じ（ユーザーごとに変えられない行は出ない）
- US1・US2 の `user_settings` が main と同じ
- 差し戻し: `{"email":"not-an-email","upload_media":"mp4"}` を入れて保存すると、`save_user.processor.php` のメールアドレスの確かめで止まって `a=12` へ戻り、`upload_media` の欄に `mp4` が残る（main と同じ。main で先に確かめる）
- TinyMCE 7 の欄（ツールバー・メニューバー・Enter キー）が編集中のユーザーの値で出る（`tinymce7_menubar` が「隠す」）

コミット: `refactor(user): ユーザー編集画面の設定のタブを設定の定義から描く`

### M8: プロフィールと仕上げ

編集対象: `manager/actions/permission/mutate_user_pf.dynamic.php`（`tabPage5` を部品のタブに）、`manager/processors/permission/save_user.functions.php`（`profileSettingNames()` の固定の一覧を `EvoSettingsForm::names('profile')` に。プラグインの名前をセッションから足す処理は残す）、`assets/docs/global-settings.md`（ユーザー画面に出す行の書き方）、この ExecPlan。

実行と期待:

- 管理者で `form-snapshot.js --path 'index.php?a=74'` を main と比べ、名前の集まりと値が同じ。タブは「管理画面」「エディター」
- プロフィールで何も変えずに保存、`manager_theme` を変えて保存した後の、管理者の `user_settings` が main と同じ
- `php -l`（7.4 と 8.4）、`security-lint.php` が `0 violations`、PHP 7.4 の環境で `a=12`・`a=74` を開いて保存できる
- `git archive --format=zip HEAD | wc -c` を段階 1 の前と比べ、PR の本文に書く
- `wc -l manager/includes/settings_form.*.php manager/actions/tool/mutate_settings.dynamic.php manager/actions/tool/mutate_settings/functions.js` を記録し、Outcomes に「消した行数・足した行数」を書く

コミット: `refactor(user): プロフィールの設定のタブを設定の定義から描く`、`docs(settings): ユーザー画面に出す設定の書き方を足す`

PR 3 を出す（`Closes #1067`、`Closes #1066`）。

## Validation and Acceptance

### 段階 1（PR 1）

| 条件 | main | 作業ブランチ（期待） |
|---|---|---|
| A: 既定の状態で `a=17` を開く | 7 タブ、欄の一覧 `main/A.json` | `diff` で差が無い |
| B: 条件の 6 つを逆にして開く | 条件の行の表示が A と逆 | `diff` で差が無い |
| C: 存在しないフォルダで保存して戻る | `use_browser`・`friendly_urls` だけが打った値 | 打った値がすべての欄に戻る（許す違い 1） |
| S1: 何も変えずに保存 | `S1.tsv` | 差が無い |
| S2: 型ごとに値を変えて保存 | `S2.tsv` | 差が無い |
| 条件の行の切り替え（6 組） | ラジオで出し入れ | 同じ。TinyMCE 7 の表も `use_editor` で出し入れ |
| 言語から読み込む（6 行） | 確認のあと欄の文面が変わる | 同じ |
| コンテンツタイプの追加・削除 | hidden とリストが変わる | 同じ |
| テンプレートを変える | 一括変更のラジオが出る | 同じ |
| 試しのプラグインの `OnSiteSettingsRender` | 3 回出る | 3 回出る（段階 2 で直す） |
| PHP 7.4 の環境 | （対象外） | 表示と保存ができ、エラーのログが無い |
| `security-lint.php` | 0 violations | 0 violations |

段階 1 で許す違い（これ以外の違いは失敗）:

1. 差し戻しの画面（C）で、main が DB の値を出していた欄にも、打った値が出る
2. 画面写真で、サイトのタブの `server_offset_time` の説明の時刻（撮った時刻で変わる）
3. DB に `[(base_path)]` を使わない絶対パスで入っている古いパスの設定は、`[(base_path)]…` の形で出る（保存すると main でも同じ形になる。A・B では起きない）

インラインの属性（SMTP の欄の `onchange`、`style` の書き方）と HTML の空白は欄の一覧に出ないので、比べない。見た目に違いが出れば画面写真で見つかる。

### 段階 2（PR 2）

| 条件 | main（段階 1 の後） | 作業ブランチ（期待） |
|---|---|---|
| タブの見出し | 7 つ（長い名前を含む） | 9 つの短い名前（日本語・英語） |
| 欄の名前と値の集まり（A） | `main/A.json` | 同じ集まり、名前ごとに同じ値 |
| S1・S2 | `S1.tsv`・`S2.tsv` | 差が無い |
| 幅 768px・1024px のタブ列（6 テーマ） | 崩れるものがある（M5 で記録） | 箱が重ならず、行ごとにそろう |
| RevoStyle | — | 画面写真が main と同じ |
| 試しのプラグインの `OnSiteSettingsRender` | 3 回、サイトのタブの変更が保存されない | 1 回、変更が保存される |
| ファイルのタブ | 1 つの表に混在 | ファイルピッカー・ファイルマネージャー・アップロード・HTML エクスポートの小見出しで分かれる |
| TinyMCE 7 の表 | 管理画面のタブ。`use_editor=0` でも初めは出る | エディターのタブ。`use_editor=0` なら初めから隠れ、切り替えで出し入れ |
| ユーザー編集画面のファイルのタブの見出し | ファイルマネージャーの設定 | ファイル |

### 段階 3（PR 3）

| 条件 | main（段階 2 の後） | 作業ブランチ（期待） |
|---|---|---|
| `a=12` の欄の名前と値の集まり | `main/U.json` | 同じ集まり、同じ値 |
| `a=12` の設定のタブ | 管理画面（エディターの行を含む）・ファイル | 管理画面・エディター・ファイル。ファイルのタブの小見出しと並びはグローバル設定と同じ |
| `a=12` で保存（US1・US2） | `user_settings` | 差が無い |
| `a=74` の欄の集まりと保存 | main | 同じ |
| 「システム設定に従う」 | select の空の選択肢、`default_*` のチェックボックス | 同じ |
| `use_editor=0` / `use_browser=0` のとき | エディターの 2 行・ピッカーの 2 行が隠れる | 同じ |
| PHP 7.4 | — | `a=12`・`a=74` の表示と保存ができる |

全体の完了条件: 3 つの PR がマージされ、#1067 と #1066 が閉じている。`manager/actions/tool/mutate_settings/` には `functions.js` だけが残り、設定の行の手書きが 3 画面とも無い（`grep -rn "<th><?= lang(" manager/actions/tool manager/actions/permission/mutate_user manager/actions/permission/mutate_user_pf.dynamic.php` に設定の行が出ない）。配布の zip が段階 1 の前より小さい。

## Idempotence and Recovery

- 確認の手順は何度流してもよい。各回の前に `db-A.sql`（ユーザー画面は `db-U.sql`）に戻し、キャッシュを消してから流す。試しのプラグインも DB を戻せば消える
- 基準（`<work>/main/`）が無くなったときや、main が設定画面に関わる変更を取り込んだときは、取り直す。作業ツリーに未コミットの変更が無いことを確かめ、`git -C <作業ツリー> switch --detach origin/main` で main のコードにし、数秒待って（OPcache）M1 の 2〜8 を流し、`git -C <作業ツリー> switch <作業ブランチ>` で戻す
- 保存の確認のあとで `git -C <作業ツリー> status --short` を見る。`.htaccess` は無視の対象。`sample.htaccess` が消えていたら、`setup-site.js` と同じく保存前の中身で書き戻す（`git checkout` で戻すのは、`sample.htaccess` に作業中の変更が無いと確かめてから）
- マイルストーンの途中で止まったら、Progress の最初の未完了から続ける。M2・M3 の途中の版は、移していないタブを `legacy` の名前で今のファイルから読むので、どの時点でも画面は動く
- 段階 2・3 を前の PR のマージ前に進めるときは、前の段階のブランチの上に積み、前の段階のブランチを base にした PR を作る。前の PR がマージされたら、`origin/main` を取り込み、base を main に切り替える（`.claude/skills/exec-plan-runner/SKILL.md` の `/run-plan` の 4）
- 検証環境は `references/environment.md` の「後片付け」で消す。PHP 7.4 の環境も同じ（`EVO_VALIDATE_NAME=evo-validate-1067-74` を付けて `down -v --rmi local`）

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1067 、https://github.com/modxcms-jp/evolution-jp/issues/1066
- ロードマップ: `.agent/roadmap.md` の #1067・#1066 の項目
- PR は 1 段階を 1 PR にする（Decision Log）。ブランチと PR:
  - 計画: `refactor/1067-settings-screen-definition`（`gh issue develop` で #1067 に紐づけ済み）。ExecPlan のコミットはここに置く
  - 段階 1（M1〜M4）: `refactor/1067-s1-settings-definition`。紐づけない。作業ツリーで ExecPlan のコミットの上から `git switch -c` で作る。PR 1 は `Refs #1067`（2026-11-08 に作り、今の main を取り込んで M1 から始めた。PR はまだ）
  - 段階 2（M5〜M6）: `refactor/1067-s2-settings-layout`。紐づけない。PR 1 のマージ後に `origin/main` から作る（マージ前なら段階 1 のブランチの上に積む）。PR 2 は `Refs #1067`・`Refs #1066`
  - 段階 3（M7〜M8）: 紐づいた `refactor/1067-settings-screen-definition` を使う。PR 2 のマージ後に `git merge --ff-only origin/main` で進めてから積む（マージ前なら段階 2 のブランチへ `--ff-only` で進めて積み、段階 2 のブランチを base にする）。このブランチには ExecPlan のコミットのほかに何も push しないので、早送りで進められる。PR 3 は `Closes #1067`・`Closes #1066`
  - 作業ツリーは 3 段階とも `.claude/worktrees/issue-1067` を使い、段階ごとにブランチを切り替える
  - PR の番号は、作ったときにここへ書く
- 想定コミット単位: Concrete Steps の各マイルストーンの「コミット」のとおり（段階 1 は 6、段階 2 は 5、段階 3 は 3）
- 新しく作るファイル: `.claude/skills/adaptive-validation-runner/scripts/form-snapshot.js`（M1）、`manager/includes/settings_form.class.inc.php`・`manager/includes/settings_form.definition.inc.php`（M2）
- 消すファイル: `manager/actions/tool/mutate_settings/tab*.inc.php`（7 つ、M2〜M4）、`manager/actions/tool/mutate_settings/functions.inc.php`・`manager/includes/charsets.php`（M4）、`manager/actions/permission/mutate_user/tpl/form/tab_page5.php`・`tab_page7.php`（M7）
- 確認の記録: `<work>/main/`・`<work>/branch/` の JSON・画面写真・TSV。PR の本文には比較の表と、代表の画面写真を載せる
- 配布の zip: 1,571,241 バイト（2026-11-05、`7801541d0`）。各 PR で差を書く
- 関連の文書: `assets/docs/global-settings.md`、`assets/docs/theme-spacing.md`、`assets/docs/theme-variables.md`、`assets/docs/events-and-plugins.md`、`assets/docs/core-issues.md`（保存が許可リストを持たない件）

## Interfaces and Dependencies

### 定義ファイル `manager/includes/settings_form.definition.inc.php`

`return [ タブ, … ];` の配列を返す。読むのは `EvoSettingsForm::definition()` だけで、1 回のリクエストで 1 回読む（文言は読んだときの言語の `lang()`）。

    タブ:    ['id' => 'tabPage7', 'title' => lang('settings_misc'), 'sections' => [section, …]]
    section: ['title' => null | lang('settings_section_files_picker'), 'rows' => [行, …]]
    行（共通）:
      'key'      設定キー。送る欄の名前（必須。event 行は除く）
      'type'     text | textarea | radio | yesno | enabled | select | event
                 | path | lang_text | content_types | template | topmenu | password
      'label'    見出し（HTML）
      'desc'     説明（HTML）、または function (array $values): string
      'options'  radio・select の [値 => 表示]、または function (): ?array。select は [グループ名 => [値 => 表示]] で optgroup
      'default'  値が無いときの値、または function (array $values): string。radio はどれにも合わないときにこの値を選ぶ
      'size'     text の maxlength（既定 255。10 以下なら幅を em で決める）
      'attr'     欄に足す属性 [名前 => 値]（id・style など。値は部品が hsc() する）
      'before' / 'after'  欄の前後に出す HTML（rb_base_url の前のサイトの URL は定義で hsc() する）
      'showIf'   ['use_editor', '1'] … その値のときだけ行を出す
      'disabled' bool、'value' function ($value): string（use_captcha）
      'name'     event 行のイベント名
      'id'・'langKey'・'reload'・'warningHeading'  lang_text の欄の id・初期文面の言語キー・読み込みの select の名前・見出しのセルの形
      'defaultPath'  path の既定（文字列か関数）
      段階 3 で足す: 'scope'（既定 ['system']）、'userDefault'（bool）、'userAttr'、'userValue'（function）、'userDesc'

`yesno` は `['1' => lang('yes'), '0' => lang('no')]`、`enabled` は `['1' => lang('enabled'), '0' => lang('disabled')]` の radio の略。`disable_cache_at_login` のように値と表示が逆の行は `radio` で書く。

### 描画の部品 `final class EvoSettingsForm`（`manager/includes/settings_form.class.inc.php`）

    public function __construct(array $tabs, array $values, string $scope = 'system')
    public function render(): string            // すべての .tab-page の HTML（イベント行ではそのイベントを呼ぶ）
    public function renderTab(array $tab): string // 1 つの .tab-page（M2・M3 で、移したタブだけを描くため）
    public static function definition(): array
    public static function storedValues(): array // DB の system_settings に default.config.php を下敷きにした値（evo()->config の置き換え用）
    public static function systemValues(): array // storedValues() にパスの置き換えと差し戻しを重ねた、欄に出す値（Decision Log の「値の出どころを 1 つにする」）
    public static function languages(): array    // [言語名 => 表示名]。lang フォルダの *.inc.php
    public static function languagesHavingKey(string $key): array
    public static function themes(): array
    public static function roles(): array
    public static function style(): string       // table.settings のインラインの <style>（段階 3）
    public static function names(string $scope): array // その scope に出す設定キー（段階 3、profileSettingNames() が使う）

PHP 7.4 で動く書き方にする（アロー関数と型付きプロパティは可、`match`・名前付き引数・`str_starts_with()`・nullsafe 演算子は使わない）。グローバルなオブジェクトは `evo()`・`db()`・`manager()` で触り、スーパーグローバルに触らない。DB は `db()->select()` に固定の条件だけを渡す（リクエストの値を使わない）。

### 画面と JS の約束

- 条件の行: `data-show-if="<名前>=<値>"`。`manager/actions/tool/mutate_settings/functions.js` が、その名前のラジオの `change` で、同じ名前を持つ `[data-show-if]` の要素を `style.display` で出し入れする。初めの表示はサーバーが決める。プラグインも同じ属性を付ければ一緒に切り替わる
- 言語から読み込む欄: `confirmLangChange(el, lkey, elupd)`（今の `functions.js`）と `a=118` をそのまま使う
- コンテンツタイプ: `addContentType()`・`removeContentType()` が名前 `txt_custom_contenttype`・`lst_custom_contenttype`・`custom_contenttype` に頼るので、名前を変えない
- タブ: `tpSettings = new WebFXTabPane(document.getElementById('settingsPane'), …)` を残す（ManagerManager の `mm_createtab` と `common/block_tabpage.tpl` が `tpSettings` を使う）

### プラグインと保存

- イベント `OnSiteSettingsRender`・`OnFriendlyURLSettingsRender`・`OnUserSettingsRender`・`OnInterfaceSettingsRender`・`OnMiscSettingsRender`・`OnSystemSettingsRender` は、出す場所を残す（位置は Decision Log）。呼び方は今と同じ `evo()->invokeEvent($name)` で、配列の出力をつないで出す
- TinyMCE 7: `SystemSettingsRenderer::render()` の表に `data-show-if="use_editor=1"` を足すだけ。`a=17` かどうかの判定（`manager()->action === 17`）と、ユーザー画面での `$usersettings` の読み方は変えない
- 保存: `manager/processors/save_settings.processor.php` は変えない。段階 3 の `save_user.functions.php` は `profileSettingNames()` の固定の一覧だけを定義から引き、`saveUserSettings()` は変えない
- 言語ファイル: 日本語と英語の 2 つに同じキーを足す。段階 2 で `settings_misc` を消し、`settings_files`・`settings_resources`・`settings_cache`・`settings_mail`・`settings_editor`・`settings_section_*`・`auto_pub_date_title`・`auto_pub_date_message`・`setting_legacy_cache`・`setting_legacy_cache_desc`・`automatic_alias_opt_numbering` を足し、`settings_ui` の文言を「管理画面」/"Manager" にする（`mutate_web_user.dynamic.php` などほかの画面の `settings_users`・`settings_ui` の見出しも同じ文言になる）
