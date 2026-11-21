# ExecPlan: 管理画面のフォームの行と直書きの style を整理する

## Purpose / Big Picture

管理画面の編集画面には、画面ごとに場当たり的に書き足してきた行の HTML と直書きの `style="..."` が積み重なっている。行を描く部品を 1 つに決め、見た目の決まりを共通の CSS とテーマの変数に寄せ、画面の HTML にはその画面だけの事情しか残らない状態にする。直書きの style が減るのはその結果であって、件数そのものは目標にしない。

利用者から見える成果は、画面によって見出しの付き方・説明の位置・余白がばらばらな状態が無くなること。開発者から見える成果は、行の HTML を直す場所が 1 か所になり、ManagerManager・QuickManager・管理画面の JS が `data-field` 属性だけで行を探せる範囲が、文書の編集画面から管理画面の編集画面全体に広がること。

## Progress

- [x] (2026-11-20) 現状を調べ、段階 0 の設計と互換の扱いを決めた（Decision Log）
- [x] (2026-11-20) 段階 0: `EvoFieldRow` を作り、文書の編集画面・`EvoSettingsForm`・システム情報・スタイルガイドを移し、`manager()->renderTr()` などを消した（7 テーマ × 10 画面で計画どおり以外の違い 0）
- [x] (2026-11-20) 段階 1: 送信用の隠し input 11 件を `class="hidden"` に、管理ログの検索・システム情報の詳細・サイトの書き出し・バックアップのスナップショットの行を部品へ移し、見出しの右の間を共通の 12px にした（直書きの style の行 189 → 164）
- [ ] 段階 2: エレメントの編集画面（TV・スニペット・チャンク・プラグイン・テンプレート・モジュール）の行を部品へ移し、余白をそろえる
- [ ] 段階 3: ユーザーの画面。ユーザーの設定の手書きのタブを `EvoSettingsForm` へ、Web ユーザーとプロフィールの重複をなくす。設定の説明を部品の説明の欄へ移し、`EvoSettingsForm::style()` を共通の CSS へ移す
- [ ] 段階 4: 文書の画面の残り（TV の行 `tv_row.tpl` を部品へ、ほかの直書き）

## Surprises & Discoveries

- 行を描く部品は Issue に挙げた 4 つではなく 6 系統あった。`renderFieldTr()`（`render_tr.tpl`）、TV の `tv_row.tpl`、`EvoSettingsForm::renderRow()`、`report/sysinfo.static.php` の `render_tr()`、`manager()->renderTr()`（`common/block_tr.tpl`）、手書き。`data-field` の約束（#1632）と `custom_tpl_dir` での差し替えを持つのは `render_tr.tpl` と `tv_row.tpl` だけ。
- 見出しのセルは、手書きの行と `EvoSettingsForm` が `th`、`render_tr.tpl` と `tv_row.tpl` だけが `td`。
- 見出しの列の幅は、文書の編集画面 120px（`.mutate-field-label`）、TV 200px、設定の表 200px（`.settings th`、`!important`）と 3 つある。スタイルガイドだけに当てる案 `manager/media/style/common/foundation-proposal.css` が `--form-label-width: 150px` で 1 つにそろえる案を持っている。
- 見出しの色の変数も 2 つある（`--mutate-field-title-color` と `--settings-th-color`）。
- `EvoSettingsForm::style()` は画面に `<style>` を直に出している。`table.settings`（詳細度 0,1,1）の `width: 100%` が `theme-base.css` の `.settings`（0,1,0）の 600px に勝つので、`style()` を出す画面（グローバル設定・ユーザー・プロフィール）では 600px が効かず、出さない Web ユーザーの画面では効く（推測。段階 3 で計算後のスタイルで確かめる）。
- `custom_tpl_dir` は設定の定義に無い隠れた設定で、読むのは文書の編集画面の `file_get_tpl()`（`manager/actions/document/mutate_content/functions.php`）だけ。
- 段階 1: 見出しのセルの縦の揃えは、画面ごとに上揃え（設定の表、システム情報の詳細の表、サイトの書き出しの `td.head`）と中央（文書の行、th の既定）に分かれている。共通で上揃えにすると、文書の行の見出しが入力欄の文字とずれる（そろえるには `foundation-proposal.css` のように上の余白の計算と組みにする）。これは先送りした「見出しの列の幅・色をそろえる」判断に含めて決め、段階 1 では移した表の見出しは th の既定（中央）のままにした。システム情報の詳細の表で値が 2 行になる数項目は、見出しが縦の中央に出る。

## Decision Log

- 2026-11-20 目的（本人の指示）: 積み重なった場当たり的な対応を整理することが目的。直書きの style を別の場当たり（画面専用のクラス、部品の中の分岐の積み重ね）へ移し替えるだけの変更は入れない。種類ごとの違いは呼び出し側の定義（配列）で表し、部品の中の分岐は少しにとどめる。
- 2026-11-20 行の部品の正本: 新しいクラス `EvoFieldRow`（`manager/includes/field_row.class.inc.php`）と tpl 1 つ（`manager/media/style/common/field_row.tpl`）にする。
  - 理由: `data-field` の約束を持つのは `render_tr.tpl` 系だけで、ManagerManager・QM・`Evo.fieldRow()` がこれに乗っている。これを文書の画面の外でも使える場所へ出すのが、約束を広げるいちばん短い道。`EvoSettingsForm` は設定の定義と値の正本で、エレメントの画面の欄（設定ではない）を描くのには向かない。`EvoSettingsForm` は定義から行の配列を作り、HTML は部品に任せる。
  - クラスにする理由: `ex_managerapi.php`（`manager()`）はすでに大きく、行の描画は管理画面の API の責務ではない。`EvoSettingsForm`・`FormFlow` と同じく、1 つの責務を 1 つのクラスに置く。
  - 却下した案: `manager()->renderTr()` を入口に作り直す（本人の判断で消すことにした）。`EvoSettingsForm::renderRow()` に寄せる（設定の値・保存の都合と行の HTML が 1 つのクラスに混ざる）。
- 2026-11-20 部品の渡し方: 位置で決まる引数をやめ、名前付きの配列 1 つにする。キーは `field`（`data-field` の値。フィールドでない行は空）、`label`（見出しの HTML）、`label_extra`（見出しのセルで名前の後ろに置く HTML。鍵のアイコン・言語の select など）、`body`（値のセルの HTML）、`help`（値の下の説明の HTML。空なら出さない）、`class`（行に足す class）、`attr`（行に足す属性の配列。`data-show-if`・`style="display:none"` など）。HTML を受けるキー（`label`・`label_extra`・`body`・`help`）は、呼び出し側で値をエスケープしてから渡す（利用者の入力は `hsc_for_tpl()`）。`attr` の値は部品が `hsc()` する。
- 2026-11-20 行の HTML（tpl）:

      <tr class="field-row[+class+]" data-field="[+field+]"[+attr+]>
          <th class="field-label">
              <span class="field-title" data-field-part="title">[+label+]</span>[+label_extra+]
          </th>
          <td class="field-value" data-field-part="value">[+body+][+help+]</td>
      </tr>

  `help` は `<div class="form-text" data-field-part="help">…</div>` で包む。見出しは `th` にそろえる（手書きの行の大半と `EvoSettingsForm` が `th`。表の行見出しとして正しい）。`data-field-part="title"` は名前だけを包む `span` に付ける（ManagerManager の名前の変更が `title` の中身を置き換えるので、鍵のアイコンなどを巻き込まない）。`style` 属性は `attr` に指定したときだけ出す（今の `render_tr.tpl` は空でも `style=""` を出している）。
- 2026-11-20 文書の編集画面の行は、`class` に `mutate-field-row` を渡して残す（外のサイトの CSS・JS が使っている可能性があり、残しても害が無い）。値のセルの `.field-inline` の包みは、部品ではなく呼び出し側（`renderFieldTr()`）で行う（設定の表は包まないので、部品に分岐を持たせない）。
- 2026-11-20 段階 0 は見た目を変えない。見出しの列の幅・色・余白をそろえる（`foundation-proposal.css` の案を本採用するか）は見た目が変わる判断なので、段階 0〜4 の対象外とし、部品が 1 つになった後に別の Issue で決める。そのため段階 0 の CSS は、今の見た目を保つための付け替え（`.mutate-field-label` → `.mutate-field-row > .field-label` など）だけにする。
- 2026-11-20 見出しのセルは th の既定の太字を使う。手書きの行（`<th align=left>` が大半）を移すとき、打ち消しを積まないため。太字にしない見出しが要る画面は、段階ごとに理由を書く。共通の規則は揃え（`text-align: start`）だけ。
- 2026-11-20 互換（本人の判断）: 本体・同梱のプラグイン・同梱のモジュールから呼ばれていない `manager()->renderTr()`・`renderSection()`・`renderTabPane()`・`renderTabPage()` と `manager/media/style/common/block_tr.tpl`・`block_section.tpl`・`block_tabpage.tpl`・`block_tabpane.tpl` を消す。自作プラグインで使っているサイトでは呼び出しがエラーになるので、リリースノートに書く。
- 2026-11-20 互換（本人の判断）: `custom_tpl_dir` での差し替えは、新しい `field_row.tpl` 1 つだけを対象にする（`EvoFieldRow` が `custom_tpl_dir` に同名のファイルがあればそれを読む）。古い名前の `render_tr.tpl` は読まなくなる（段階 4 で `tv_row.tpl` も）。差し替えているサイトは作り直しが要るので、リリースノートに書く。`custom_tpl_dir` の `field_row.tpl` は文書の画面だけでなく、部品を使うすべての画面に効く。
- 2026-11-20 外の ManagerManager の部品が `closest('td')` などの構造に頼っている場合は考えない（互換を気にする外部のプラグインは TinyMCE 7 だけ、という本人の方針）。同梱の部品は #1632 で `data-field` に移っている。
- 2026-11-20 行の見出しと値の間（本人の判断）: 行の部品の共通の CSS で、見出しのセルの右に `--space-3`（12px）の間を取る（`.field-label { padding-inline-end: var(--space-3, 12px) }`、詳細度 0,1,0。行の部品の行は `.grid` の中に出ないので、表の th の規則とは競わない）。文書の編集画面の入力欄も右へずれる。設定の表は `EvoSettingsForm::style()` の `table.settings th`（0,1,2、5px）が勝つので変わらない（設定の表の余白は段階 3 で `style()` を共通の CSS へ移すときに合わせる）。段階 0 の「見た目を変えない」の例外として、この 1 点だけ先に決めた。
- 2026-11-20 段階 1 の範囲: 手で組んだ一覧表（システム情報の最新の編集・DB の一覧・オンラインの一覧、バックアップの DB の一覧）は #1204（DataTable へ移す）と重なるので触らない。管理者しか値を入れない欄（DB の変数・テーブルのコメントなど）にエスケープを足すことはしない（今壊れていない値に守りを足さない、という本人の方針）。管理ログの検索フォームの値の右揃えと 1 行おきの白い地はやめ、ほかのフォームと同じにする。サイトの書き出しの設定の表は `class="settings"` を持つので、グローバル設定と同じ見出し（`.settings th`）にそろえる。
- 2026-11-22 見出しの縦の揃え（本人の判断）: 行の部品の見出しのセルは、共通の CSS で上揃えにし、上の余白を `calc((var(--control-height) - var(--line-box)) / 2)` にする（入力欄は中の文字が縦の中央に来るので、見出しの 1 行目が 1 行の入力欄の文字と同じ高さに並ぶ。複数行の欄でも見出しは上に来る）。段階 1 で先送りにした縦の揃えをここで決めた。画面ごとの `padding-top`・`valign` の直書きは消す。`EvoSettingsForm::style()` の `table.settings th` の 5px は段階 3 で合わせる。見出しの列の幅・色は引き続き対象外。
- 2026-11-22 見出しの無い行（本人の判断）: 「ロックする」「無効にする」など見出しの無いチェックボックスの行は、部品に `label` を空で渡し、値を入力欄の列にそろえる（部品に「見出しの無い行」の形は足さない）。
- 2026-11-22 段階 2 の細かい決まり: 見出しのコロンは付けない（チャンク・TV・設定の表に合わせる）。モジュールの見出しは `td` から `th` にする。入力欄の下の注意書き（`span.comment` と `<br>`）は `help`（`.form-text`）にする。入力欄の幅の直書き（300px など）は欄ごとの値なので残す。JS が表示を切り替える行（新しいカテゴリ・パラメーターの表・`#inputoption`・`.sharedparams`）は `attr` で今と同じ `id`・`style` を出し、`td` に付いていた `id`（`#displayparams`）は値の中の要素に移す。見出しと値の行ではない表（プラグインのイベントの表、JS が組むパラメーターの表、権限のチェックの一覧、モジュールの依存の DataTable、テンプレートの「名前と親テンプレート」の 1 行）は触らない。段階 2 は差分が大きいので、スニペットとチャンク（2A）、プラグインとテンプレート（2B）、モジュールと TV（2C）の 3 PR に分ける。
- 2026-11-20 PR の分け方: 1 段階を 1 PR にする。段階ごとのブランチは `origin/main` から `git worktree add -b` で作り、Issue には紐づけない（紐づけると `Refs` でもマージで #1631 が閉じる）。段階 0〜3 の PR は `Refs #1631`、段階 4 の PR だけ `Closes #1631`。

## Outcomes & Retrospective

## Context and Orientation

用語:

- 行: 編集画面のフォームの 1 項目を表す表の行（`<tr>`）。見出し（項目名）のセルと、値（入力欄など）のセルからなる。
- `data-field` 属性: 行がどのフィールドのものかを表す属性。決まりは `assets/docs/jquery-migration.md` の「編集画面の行と data-field 属性」。行の中の部分には `data-field-part`（`title`・`value`・`help`・`input`）を付ける。探す JS は `manager/media/script/evo.js` の `Evo.fieldRow()`・`Evo.fieldPart()`、ManagerManager は `assets/plugins/managermanager/mm.js` の `MM.fieldRows`。
- tpl: `[+名前+]` の置き場所を持つ HTML の断片。`parseText()` で値を差し込む。値の中の `[+…+]` も評価されるので、利用者の入力は `hsc_for_tpl()` で差し込む（`assets/docs/template-system.md`）。

今の行の部品（origin/main ff1c12dfd 時点）:

- `manager/actions/document/mutate_content/functions.php` の `renderFieldTr($field, $head, $body, $rowstyle, $note)` と `renderTr()`。tpl は同じフォルダの `render_tr.tpl`。呼び出しは `fields.php`（24）、`manager/actions/document/publish_draft.dynamic.php`（1）、`manager/actions/report/styleguide_sample.static.php`（スタイルガイド、7）。
- `manager/actions/document/mutate_content/tv_row.tpl`（`fields.php` の TV の行）。
- `manager/includes/settings_form.class.inc.php` の `EvoSettingsForm::renderRow()`・`renderHeading()`・`renderCell()`。定義は `manager/includes/settings_form.definition.inc.php`。使う画面はグローバル設定（`manager/actions/tool/mutate_settings.dynamic.php`）、ユーザーの編集（`manager/actions/permission/mutate_user/tpl/form.php`）、プロフィール（`manager/actions/permission/mutate_user_pf.dynamic.php`）。説明は `renderCell()` の中で型ごとに `<br/>` と一緒に値のセルに入れている。
- `manager/actions/report/sysinfo.static.php` の `render_tr()`。`<td width="150">` が閉じておらず、値を `hsc` せずに `parseText()` に渡している。
- `manager/includes/extenders/ex_managerapi.php` の `renderTabPane()`・`renderTabPage()`・`renderSection()`・`renderTr()` と `manager/media/style/common/block_*.tpl`。呼び出しは無い。
- 手書き: エレメントの編集画面（`manager/actions/element/`）、ユーザーの画面（`manager/actions/permission/`）、レポート・ツールの画面など。見出しの書き方（`th` の属性、コロンの有無）、説明の置き方（`span.comment`・`<small>`・`<div>`・無し）、直書きの style がばらばら。

行に当たる CSS:

- `manager/media/style/common/theme-base.css`: `.settings th`（200px `!important`、1.2em、`--settings-th-color`）、`.settings`（600px）、`.mutate-field-row`（height 24px）、`.mutate-field-label`（120px）、`.mutate-tv-row`・`.mutate-tv-label`（200px）、`.sectionBody input.inputBox`（350px）、`form input.hidden`。
- `manager/media/style/common/forms.css`（`@layer evo-components` の中）: `.field-inline`、`.form-text`、`.mutate-field-title`（`--mutate-field-title-color`）、`.comment`、`form textarea`。
- `EvoSettingsForm::style()` が画面に直に出す `<style>`（`table.settings` の幅・行の点線・セルの余白 5px）と、`mutate_settings.dynamic.php` の `<style>`。
- 余白の変数は `assets/docs/theme-spacing.md`（`--space-1`〜）、色の変数は `assets/docs/theme-variables.md`。

守ること（Issue #1631 の「守ること」より）:

- 行の構造と `mutate-field-row` などの既存の class、JS が触る id・name（`showname`・`editname`・`passwordBlock`・`blocked`・`pubdate`・`newcategry`・`document.mutate.menuindex` など）は変えない。
- プラグインが画面に HTML を足すイベント（`OnDocFormRender`・`OnUserFormRender`・`OnTVFormRender`・`OnChunkFormRender`・`OnInterfaceSettingsRender` など）の出力位置を変えない。
- 直書きの style は詳細度が高くテーマの規則に勝つ。消すとテーマの規則が新しく効くことがあるので、段階ごとに 7 テーマ × 対象画面の計算後のスタイルを main と比べる。

## Plan of Work

段階 0 で、行を描く場所を 1 つにする。新しい画面の行も、移した画面の行も、`EvoFieldRow::render()` だけを通る。`data-field` は部品が必ず出す（空でもよい）ので、移した画面の行は自動的に `Evo.fieldRow()` で探せるようになる。

段階 1〜4 では、手書きの行を画面のまとまりごとに部品へ移し、そのとき直書きの style を次の規則で振り分ける。

- 共通の規則と同じ値（`.settings` 600px、`.sectionBody input.inputBox` 350px など）は消す。
- 送信用の隠し input の `display:none` は `class="hidden"` へ。
- JS が表示を切り替える行の `display:none`、テンプレートの値で決まる値（z-index など）は残す。
- 余白は、行の部品の CSS（共通）で決め、画面ごとの `padding-top` は消す。行の中の余白の段は、`theme-spacing.md` の変数から選ぶ。
- `cursor:pointer; cursor:hand` はクリックできる要素の class（既存の `.clickable-label` など、無ければ共通の class を 1 つ）へ。
- 横並びのためだけの float は flex へ。回り込みの float は残す。
- 文字・色・揃えは既存の class（`.warning`・`.comment`）とテーマの変数へ。

画面ごとに決まった値がどうしても要る場合は、画面専用の class を作らず、その理由を行の定義（呼び出し側）で分かる形で残す。

## Concrete Steps

### 段階 0（ブランチ `refactor/1631-form-row-component`）

1. `manager/includes/field_row.class.inc.php` に `final class EvoFieldRow` を作る。`public static function render(array $row): string` が Decision Log の配列を受け、`field_row.tpl` に差し込んで返す。tpl は `custom_tpl_dir` に `field_row.tpl` があればそれを、無ければ `manager/media/style/common/field_row.tpl` を読む（読んだ内容は静的に持って使い回す）。クラスの読み込みは、`EvoSettingsForm` と同じ方法（呼び出し側の `require_once` か、管理画面の初期化）にそろえる。
2. `manager/media/style/common/field_row.tpl` を Decision Log の HTML で作る。
3. `renderFieldTr()` を `EvoFieldRow::render()` の薄い包みにする（`$head` の配列の 2 つ目以降は `label_extra`、`$body` は `<div class="field-inline">` で包んで `body`、`$note` は `help`、`$rowstyle` は `attr` の `style`、`class` は `mutate-field-row`）。`renderTr()` は消し、呼び出し（`publish_draft.dynamic.php`・`styleguide_sample.static.php`）を `renderFieldTr('', …)` か `EvoFieldRow::render()` に替える。`render_tr.tpl` を消す。`file_get_tpl()` はほかの tpl で使っているので残す。
4. CSS の付け替え: `theme-base.css` の `.mutate-field-label` を `.mutate-field-row > .field-label` に、`forms.css` の `.mutate-field-title` を `.mutate-field-row .field-title` にする（今の見た目を保つ。`th` の既定の `text-align: center`・太字が入るので、`.mutate-field-row > .field-label` に今の `td` と同じ揃え・太さを書く）。7 テーマの CSS に `.mutate-field-label`・`.mutate-field-title` があれば同じく付け替える（`grep -rn "mutate-field-label\|mutate-field-title" manager/media/style assets`）。
5. `EvoSettingsForm::renderRow()` を、定義の行から配列（`field` は設定のキー、`label`、`lang_text` の select は `label_extra`、`body` は `renderCell()` の結果、`attr` は `data-show-if` と `style`）を作って `EvoFieldRow::render()` に渡す形にする。`renderHeading()` は `label` と `label_extra` を返す形にする。説明（`desc()`）の置き方は段階 3 まで今のまま（`renderCell()` の中）。見出しの `span.field-title` には色・太さの規則が当たらないことを確かめる（設定の表の見出しは今どおり `th` の規則で決まる）。
6. `report/sysinfo.static.php` の `render_tr()` を消し、`EvoFieldRow::render()` に替える。値は呼び出し側で `hsc()` するか、HTML を返す項目だけそのまま渡す（項目ごとに確かめる）。空の 2 列目（`<td width="20">`）は無くす。見た目が変わるので、段階 0 の検証で前後の画像を残す。
7. `ex_managerapi.php` の 4 メソッドと `manager/media/style/common/block_*.tpl` の 4 ファイルを消す。
8. 文書: `assets/docs/manager-form-row.md` を作り、部品の使い方（配列のキー、エスケープ、`data-field` の決まりとの関係、`custom_tpl_dir`、直書きの style の振り分けの規則）を書く。`AGENTS.md` のドキュメントマップに足す。`assets/docs/jquery-migration.md` の `custom_tpl_dir` の注意（`render_tr.tpl` の名前）を新しい tpl に合わせる。リリースノートに当たる場所（`assets/docs/` か変更履歴。リポジトリの慣習を確かめる）に、消した API と tpl の名前の変更を書く。

期待される観測: `git grep -n "renderTr\b\|render_tr\b\|block_tr" -- manager assets` が `EvoFieldRow` と文書以外で 0 件。文書の編集画面・グローバル設定・ユーザーの編集・プロフィール・システム情報・スタイルガイドの行が `tr.field-row[data-field]` で出る。

### 段階 1（ブランチ `refactor/1631-stage1-report-tables`）

1. 行の間の CSS: `theme-base.css` の `.field-label` に `padding-inline-end: var(--space-3, 12px)` を足す（Decision Log）。
2. 送信用の隠し input の `style="display:none"` を `class="hidden"` にする（`form input.hidden` は form の中の input に効く。全部 form の中にある）: `document/mutate_content/_template.tpl`、`permission/mutate_user_pf.dynamic.php`、`permission/mutate_password.dynamic.php`、`permission/mutate_role.dynamic.php`、`permission/mutate_web_user.dynamic.php`、`permission/mutate_user/tpl/form.php`、`element/mutate_module_resources.dynamic.php`、`report/logging.static.php`、`tool/bkmanager.static.php`（3 か所）。JS が表示を切り替える `display:none`（`#newcategry`・`#passwordBlock`・`#inputoption`・`.sharedparams`・`#showname`・`#editname`・`#allow_ids`・`#ignore_ids`）は残す。
3. `document/move_document.dynamic.php` の `<input type="save" … style="display:none">` を消す（無効な type で、送信は JS の `form.submit()` が行い、どこからも使われていない）。
4. 管理ログの検索フォーム（`report/logging.static.php` の 2 つの表）を `EvoFieldRow::render()` へ移す（`field` は空、見出しは th）。消すもの: `cellpadding`・`cellspacing`・`border`、`tr` の白い地、見出しの列の 120px、値の `align="right"`、`img` の `border="0"`、余白のための透明画像。`cursor:pointer; cursor:hand` は `.clickable-label` に。`ul.actionButtons` の `margin-left:5px` は消す。欄の幅（240px・100px）は欄ごとの値なので残す。閉じていない `</tbody>` を足し、`class="inputbox"` は `inputBox` にそろえる（幅は直書きが勝つので見た目は変わらない）。
5. システム情報（`report/sysinfo.static.php`）: サーバーの表の `border`・`cellspacing`・`cellpadding` を消す。詳細の key と値の表（PHP・DB の設定など、`padding-right:30px` の表）を `EvoFieldRow::render()` へ移し、30px の直書きを消す（間は手順 1 の共通の CSS）。値は今と同じく渡す（エスケープは足さない。Decision Log）。key は文字なので `hsc()`。一覧表 3 つは触らない。
6. サイトの書き出し（`tool/export_site.static.php`）: 設定の表の行（`td.head` + `td`）を `EvoFieldRow::render()` へ移し、ページの `<style>` の `td.head` の規則を消す（見出しは `.settings th`）。`table.settings { width: 100% }` は、共通の `.settings` の 600px を打ち消す画面の上書きなので消す。結果の `dt`/`dd` の横並びの float を grid にする。許可・除外の欄の緑・赤の地の直書きは、緑は `--color-success-bg`、赤は `color-mix(in srgb, var(--color-error) N%, var(--color-surface))`（`theme-variables.md` の規則）を使う class か、欄の `style` から変数へ（画面専用の class を作らない。既存の class が無ければ変数を使う直書きを残し、理由を書く）。欄の幅 300px は欄ごとの値なので残す。`#exportPane [hidden]` と進捗の規則は残す。
7. バックアップ（`tool/bkmanager.static.php`）: スナップショットの表（2 行）を `EvoFieldRow::render()` へ移し、説明（`<br />` の後ろ）は `help` に。`#dbmPane td { padding: 4px }` を消す（DB の一覧が共通の `.grid` の余白に戻る）。ダウンロード用の iframe は `width`・`height`・`style` をやめて `hidden` 属性にする。DB の一覧の表は触らない。

期待される観測: 管理ログの検索・システム情報・サイトの書き出し・バックアップの見出しと値の行が `tr.field-row` で出て、見出しと値の間が 12px。`git grep -n 'display:none' -- manager/actions` に送信用の input が残らない。

### 段階 2A（ブランチ `refactor/1631-stage2-element-forms`。スニペットとチャンク）

1. 見出しの縦の揃えの CSS: `theme-base.css` の `.field-label` に `vertical-align: top` と `padding-top: calc((var(--control-height, 32px) - var(--line-box, 18px)) / 2)` を足す（Decision Log 2026-11-22）。今まで部品を使っている画面（文書の編集・下書きの公開・グローバル設定とユーザーの設定・システム情報・管理ログの検索・サイトの書き出し・バックアップ・スタイルガイド）にも効くので、検証に入れる。設定の表は `EvoSettingsForm::style()` の `table.settings th`（5px、上揃え）が勝つので変わらない見込み。`foundation-proposal.css` の見出しの規則（`padding-top`・`vertical-align`）は、同じ決まりが本採用になるので消す。
2. スニペット（`element/mutate_snippet.dynamic.php`）: 名前・既存のカテゴリ・新しいカテゴリ・説明・エラー報告・インポートのパラメーター（条件付き）・プロパティ・パラメーターの表の行を `EvoFieldRow::render()` へ。新しいカテゴリは `field` を `newcategory`、`attr` に `id="newcategry"` と表示の切り替えの `style`。インポートの説明文の行は、インポートの行の `help` に入れる（閉じていない `span` も直る）。ロックは `label` を空にした行。パラメーターの表の行は `attr` に `id="displayparamrow"` と `style`、`#displayparams` は値の中の `div` に移し、`element-properties.js` が子を置き換えられることを確かめる。消す: `align`・`valign`・`padding-top`・空の見出しの `<td>&nbsp;</td>`・見出しのコロン。残す: 欄の幅・textarea の `padding:0;height:4em`。
3. チャンク（`element/mutate_htmlsnippet.dynamic.php`）: 名前・公開・公開日・公開終了日・既存のカテゴリ・新しいカテゴリ・説明・リッチテキスト・ロックの行を `EvoFieldRow::render()` へ。日付のアイコンの `a` の `cursor:pointer; cursor:hand` は `.clickable-label` に。空の `span`（Courier の指定だけ）は消す。`.sectionBody` と `</form>` の閉じる順を直す。名前の `{{ }}` の囲みは残す。
4. 文書: `manager-form-row.md` の「使っている画面」に、段階 1・2 で移した画面を足す。「見出しと値の間」の節に縦の揃えの決まりを足す。「見出しの無い行」は `label` を空にする、と書く。

期待される観測: スニペットとチャンクの編集画面の見出しと値の行が `tr.field-row` で出て、見出しは上揃え・太字・コロン無し、ロックのチェックボックスは入力欄の列にそろう。カテゴリの新規作成・パラメーターの表の表示の切り替え・保存・ロック・RTE の切り替えが main と同じに動く。

### 段階 2B・2C・3・4

段階 2B（プラグインとテンプレート）と 2C（モジュールと TV）は、2A の PR のあとに、調査の結果（2026-11-22）をもとにこの節に手順を書き足す。範囲の外で見つかったもの: テンプレートの画面の `getParentValues()` が、入力エラーで戻したときの `parent` の値を整数にせずに SQL に入れている（ログインが要る経路。別の Issue と PR で直す）。モジュールの「パラメーターを更新」のボタンに onclick が無い（動きが変わるので別の Issue）。

段階 4 の前提（2026-11-21）: PR #1644 で TV の行は既定で TV のタブの中の `div.tmplvars#tv_body > table.mutate-tv-table` に出る（`tvs_below_content=1` なら本文の下の `#tv_body`）。`tv_row.tpl`・`fieldsTV()` の構造は #1644 では変わっていない。ManagerManager の `MM.tidyUp()` は TV のタブに入力欄が無いときだけタブを隠す。

段階 4 の手順に足すこと: `tv_row.tpl` を部品へ移すとき、`custom_tpl_dir` から tpl の場所を決める処理（今は `EvoFieldRow::tpl()` と `mutate_content/functions.php` の `file_get_tpl()` の 2 か所）を 1 つにまとめ、`file_get_tpl()` もそれを使う。

各段階に着手するときに、対象画面の行と直書きの style を数え直し、この節に手順を書き足してから実装する（段階 0 の部品と規則が決まっているので、段階ごとの手順は「どの画面のどの行を移すか」と「残す style とその理由」の表になる）。段階 1 では Issue の「ついでに見つかった小さな不具合」のうち `document/move_document.dynamic.php` の `<input type="save">` を、段階 3 では `permission/mutate_user/tpl/form/tab_general.php` の `echo 'style="display:none";'` を直す（`sysinfo` の `td` の閉じ忘れは段階 0 で無くなる）。

## Validation and Acceptance

段階ごとに、Docker の検証環境（`adaptive-validation-runner`）で main と比べる。

- 計算後のスタイル: 7 テーマ × 対象画面で、行（`tr`）・見出しのセル・値のセル・見出しの名前・説明の、幅・高さ・余白・文字の大きさ・太さ・色・揃えを Playwright の `getComputedStyle` で取り、main と突き合わせる。違いは「計画どおりの変化」と「不具合」に分けて PR に書く。段階 0 では、システム情報の表（列が 1 つ減る）以外は違いが 0 であること。
- 行の約束: 文書の編集画面で ManagerManager の部品（フィールドを隠す・名前を変える・説明を変える・タブへ移す・文字数の上限）と QuickManager が main と同じに動く。名前を変えたとき、鍵のアイコン（`weblink` の行）が消えない。
- 段階 0 の対象画面: 文書の編集（`a=27&id=1`、新規 `a=4`、ウェブリンク `a=72`）、下書きの公開（`publish_draft`）、グローバル設定（`a=17`、全タブ。言語から読み込む select のある行、条件で隠れる行）、ユーザーの編集（`a=12`）、プロフィール（`a=74`）、システム情報（`a=53`）、スタイルガイド（`a=202`、`proposal=1` も）。
- 保存: グローバル設定・ユーザーの編集・プロフィールを、何も変えずに保存した場合と 1 項目だけ変えて保存した場合で、DB の値が main と同じ。
- 直書きの style の数（物差しの 1 つ。目標にはしない）: `git grep -c 'style="' -- manager/actions` を段階ごとに PR に書く。
- 配布 zip の大きさ（1.5MB 未満）: `git archive` の zip で前後を比べる。

## Idempotence and Recovery

各段階は独立した PR なので、途中で止まったら、その段階のブランチのワークツリーで続ける。段階の PR がマージされる前に次の段階へ進むときは、前の段階のブランチから次のブランチを作り、前の段階のブランチを base にした PR にする（exec-plan-runner の `/run-plan` 手順 4）。

## Artifacts and Notes

- Issue: #1631（関連: #1632 / PR #1640 で `data-field` を導入、#1448 でテーマの値の差をそろえた、#1347 レスポンシブ対応）
- 段階 0: ブランチ `refactor/1631-form-row-component`、ワークツリー `.claude/worktrees/issue-1631`、PR #1642
- 段階 1: ブランチ `refactor/1631-stage1-report-tables`（段階 0 のワークツリーで main から切り替えた）、PR #1653

## Interfaces and Dependencies

- 外に見える変化: `manager()->renderTr()` などの 4 メソッドと `common/block_*.tpl` が無くなる。`custom_tpl_dir` で差し替える行の tpl の名前が `field_row.tpl` になり、中身（見出しが `th`、`data-field-part` の位置）が変わる。
- `data-field` の約束（`assets/docs/jquery-migration.md`）は変えず、部品を使う画面すべてに広げる。行を `tr` から別の要素へ変えるとき（#1347 のレスポンシブ対応など）は、`field_row.tpl` と行の CSS だけを直せば済む状態を目指す。
