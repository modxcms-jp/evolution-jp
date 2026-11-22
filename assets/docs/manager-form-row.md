# 管理画面のフォームの行

管理画面の編集画面で、見出し（項目名）と値（入力欄など）を並べる行（`tr`）は、部品 `EvoFieldRow`（`manager/includes/field_row.class.inc.php`）と tpl `manager/media/style/common/field_row.tpl` で出す。この部品を使う行の HTML を直す場所はこの 2 つだけ（`tv_row.tpl` と手書きの行は、段階的に移すまで残る）。`manager()` と同じく、管理画面の初期化（`ex_managerapi.php`）が読み込む。

## 使い方

```php
echo EvoFieldRow::render([
    'field' => 'pagetitle',
    'label' => lang('resource_title'),
    'body' => $inputHtml,
    'help' => hsc_for_tpl(config('datetime_format')) . ' HH:MM:SS',
]);
```

| キー | 内容 |
| --- | --- |
| `field` | `data-field` の値。フィールドではない行は空（省略可） |
| `label` | 見出しの HTML。`span.field-title`（`data-field-part="title"`）の中に入る。名前だけを入れる |
| `label_extra` | 見出しのセルで、名前の後ろに置く HTML（鍵のアイコン・言語の select など）。名前の変更（ManagerManager）が `title` の中身を置き換えても消えない |
| `body` | 値のセルの HTML |
| `help` | 値の下の説明の HTML。空なら出さない。`div.form-text`（`data-field-part="help"`）で包む。`.form-text` は `--color-text-muted` の灰色で区別し、文字の大きさは本文と同じ（小さくしない） |
| `class` | 行に足す class（文書の編集画面は `mutate-field-row`） |
| `attr` | 行に足す属性 `[名前 => 値]`（`data-show-if`・`style` など）。値は部品が `hsc()` する |

- HTML を受けるキー（`label`・`label_extra`・`body`・`help`）は、呼び出し側で出力に合わせてエスケープしてから渡す（利用者の入力・DB や環境から来る値は `hsc()`）。部品は差し込んだ値を再評価しないので、値の中の `[+…+]` はそのまま出る。部品の出力を、あとで `parseText()` などのタグ解析に通す画面（文書の編集画面は大きな tpl に差し込む）は、値を `hsc_for_tpl()` で渡す（`template-system.md`）。言語ファイルとコードの定数の HTML はそのまま渡してよい。
- 値のセルの HTML が大きい行は、PHP の画面では `ob_start()` と `ob_get_clean()` で HTML を組んで `body` に渡す（`sprintf()` や heredoc で組まない）。
- 種類ごとの違いは、呼び出し側が配列で表す。部品の中に画面ごとの分岐を足さない。文書の編集画面の値のセルの `.field-inline` の包みは、呼び出し側の `renderFieldTr()`（`mutate_content/functions.php`）が行う。
- 使っている画面: 文書の編集（`renderFieldTr()` 経由）・下書きの公開・グローバル設定とユーザーの設定の表（`EvoSettingsForm`）・システム情報・スタイルガイド・管理ログの検索・サイトの書き出し・バックアップのスナップショット・スニペット・チャンク・プラグイン・テンプレート・モジュール・TV の編集（エレメントの 6 画面。ユーザーの画面は段階 3 で移す）。

## data-field の決まり

行は `tr.field-row[data-field]` で出る。`data-field`・`data-field-part` の決まりと、探す JS（`Evo.fieldRow()`・`Evo.fieldPart()`）は `jquery-migration.md` の「編集画面の行と data-field 属性」を正本とする。部品を使う画面すべてで同じ決まりになる。

## custom_tpl_dir

設定 `custom_tpl_dir` のフォルダに `field_row.tpl` があれば、部品はそれを読む（文書の画面だけでなく、部品を使うすべての画面に効く）。差し替える tpl には、`data-field`・`data-field-part` と、`[+class+]`・`[+attr+]` の置き場所を書く。

## 見出しと値の間

見出しのセルの右の間（`--space-3`）は、共通の CSS（`theme-base.css` の `.field-label`）が決める。画面ごとに `padding-right` を直書きしない。

## 見出しの縦の揃えと見出しの無い行

- 見出しの 1 行目の文字は、値の 1 行目の文字に共通の CSS（`theme-base.css`）でそろえる。値が文字・チェックボックス・ラジオの行は基準線でそろえる。文字を入れる欄（入力欄・セレクト・textarea）のある行は上揃えにし、見出しの上の余白を欄の中の文字の高さに合わせる（textarea の基準線は下端なので、基準線ではそろわない）。複数行の欄でも見出しは上に来る。画面ごとに `padding-top`・`valign` を直書きしない。「文字を入れる欄」は `forms.css` の欄の高さの規則と同じ一覧にする。
- 設定の表（`class="settings"`）は、全幅・見出しは本文と同じ大きさ（列の幅は 200px）・行の間に点線で、`theme-base.css` の `.settings` が決める。行の上下・左右の余白は 1px に変数 `--field-row-gap`（設定の表は 4px）を足した値で、見出しの上の余白にも同じ変数が足されるので、上の揃えは崩れない。設定の表の説明（`EvoSettingsForm` の `desc`）は `help` に入る。
- 見出しの無い行（「ロックする」のチェックボックスなど）は、`label` を空にして渡し、値を入力欄の列にそろえる。部品に「見出しの無い行」の形は足さない。
- 見出しにコロンは付けない。

## 直書きの style の振り分け

画面の HTML の `style="..."` は、次のように扱う。画面専用の class を作って移し替えるだけにしない。

- 共通の規則と同じ値（`.settings` の幅など）は消す。
- 送信用の隠し input の `display:none` は `class="hidden"` にする。
- JS が表示を切り替える行の `display:none`、テンプレートの値で決まる値（z-index など）は残す。
- 余白は行の部品の共通の CSS で決める。余白の段は `theme-spacing.md` の変数から選ぶ。
- クリックできる要素の `cursor:pointer` は `.clickable-label` などの共通の class にする。
- 横並びのためだけの float は flex にする。回り込みの float は残す。
- 文字・色・揃えは既存の class（`.warning`・`.comment`）とテーマの変数（`theme-variables.md`）を使う。

画面ごとに決まった値がどうしても要るときは、画面専用の class を作らず、その理由を行の定義（呼び出し側）で分かる形で残す（例: `EvoSettingsForm::renderRow()` は、条件に合わず初めから隠す行だけ `attr` に `style => display:none` を渡す。`data-show-if` の JS が表示を切り替えるので、class にできない）。
