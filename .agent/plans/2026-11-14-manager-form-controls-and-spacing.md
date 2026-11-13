# ExecPlan: 管理画面のフォーム部品を共通の CSS にまとめ、左右の余白と画面の骨組みをそろえる

Issue: #1446、#1447（この PR で 2 つを閉じる）。続き: #1448

## Purpose / Big Picture

管理画面の入力欄・セレクト・ボタンの見た目と高さが、画面やテーマによってばらばらになっています。ブロックの左右の位置も、画面によって少しずつずれています。この PR のあと、7 つのテーマのどれでも、入力欄・セレクト・ボタンの高さはテーマごとに 1 つの値にそろいます。`h1` と下の箱の左右の端も、すべての画面でそろいます。テーマの CSS は短くなります。

## Progress

- [x] (2026-11-14) 参考の実装（modxjp 3c0169d47）を今の main に当てて、指示の項目ごとに中身を確かめた（土台が同じ 37e91ee なので、そのまま当たった）
- [x] (2026-11-14) 古くなったコメントと打ち消しの指定（selection-dock.css・shell.css・filebrowser.css の `width: 112px` の打ち消し、theme-variables.md の selection-dock の行）と、使われなくなった `$theme`（accesscontrol-not-mgr.inc.php）を直した
- [x] (2026-11-14) 7 テーマに写した説明のコメント・経緯の説明・規則を消した跡のコメントを整理した（配布 zip の増加 5.4KB → 1.5KB）
- [x] (2026-11-14) Docker の検証環境で、php -l（8.4・7.4）、フォーム部品の高さ、左右の余白、子リソース一覧の検索欄と操作の帯、ログイン画面の読み込み順を、main と比べて確かめた
- [ ] 修正前と修正後を並べた画像（7 テーマ分）を付けて、下書きの PR を作る

## Surprises & Discoveries

- 参考の実装は、theme の `button { width: 112px }` を外したが、それを打ち消していた共通の CSS の `width: auto` とコメントは残っていた。同梱の 7 テーマから 112px が無くなったので、打ち消しも消した。
- テーマの CSS は行が 120〜250 行減っても、最初は圧縮前のバイト数がほぼ同じだった。7 テーマに同じ説明のコメント（合計約 9.7KB）を写していたため。消した規則は似た行の繰り返しでよく縮み、足した変数はテーマごとに値が違って縮みにくいので、圧縮後の大きさはほぼ変わらない。

## Decision Log

- 2026-11-14: 参考の実装をそのまま使う。土台のコミットが今の main と同じで、指示の項目（forms.css の構成、8 か所の読み込み、7 テーマの変数と古い規則の削除、画面の骨組み、文書）をすべて満たしているため。
- 2026-11-14: ログイン画面のプレースホルダー `[+theme_css_ver+]` は残さない。利用者が `manager_login_tpl` で独自のテンプレートを使っていても、版の文字列が空になるだけで CSS は読める（互換のための重複を本体に残さない方針）。

## Outcomes & Retrospective

- 左右の余白: 7 テーマ × 35 画面（h1 の無いウェブアクセス権限を除く 245 件）で、h1 と下の箱の左右の差が 0 でない件数は main 43 件 → 0 件
- フォーム部品の高さ（input[type=button] を除く）: main は 1 テーマに 5〜7 種類 → RevoClassic 34px、ほかの 5 テーマ 36px の 1 種類。凍結の RevoStyle は古い規則のまま（24・26・28px）
- 子リソース一覧の検索欄と「検索」ボタン、操作の帯のボタンは、7 テーマとも同じ高さで横に並ぶ
- 配布 zip: 1,657,233 → 1,658,779 バイト（+1,546）（forms.css・RevoStyle の変数・manager_theme_stylesheets() の分）。#1448 で 7 テーマ共通の規則を寄せると減る見込み

## Context and Orientation

- 共通の CSS: `manager/media/style/common/forms.css`（新規）、`common/shell.css`（`#mainPane` の左右の余白）
- テーマの CSS: `manager/media/style/{RevoClassic,AromaHop,AuroraFlow,RubberWhite,SakuraFlow,SoftSlate,RevoStyle}/style.css`
- 読み込み口: `manager/includes/helpers.php` の `manager_theme_stylesheets()`。呼ぶ側は `header.inc.php`、`resource_selector.static.php`、`cache_sync.class.php`、`BrowserView.php`、`document.parser.subparser.trait.php`（2 か所）、tpl 3 か所（プレースホルダー `[+theme_stylesheets+]`）
- 文書: `assets/docs/theme-variables.md`（フォーム部品）、`assets/docs/theme-spacing.md`（横の余白）
- DocumentParser: trait の変更は、エラー画面・警告画面の `<link>` の出力だけ。`executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` には影響しない。キャッシュにも影響しない（管理画面の出力だけ）

カスケードレイヤー（`@layer`）: CSS の規則を名前つきの層に入れる仕組み。層に入れた規則は、層に入れていない規則に、詳細度に関係なく必ず負ける。層の順番は、最初に出てくる `@layer` の宣言で決まる。

## Plan of Work

forms.css に、変数・素の要素の土台（`@layer evo-base`）・ボタンの部品（`@layer evo-components`）を置き、テーマは `:root` で変数の値を決めるだけにする。forms.css はテーマより先に読む必要があるので、読み込みを `manager_theme_stylesheets()` 1 か所にまとめる。左右の余白は `#mainPane` の `padding-inline`（`--page-padding-x`）だけが持ち、ブロックは左右の margin を持たない。外れている画面のマークアップを `h1` → `#actions` → 本体の型に直す。

## Concrete Steps

1. 参考の patch を当てる: `git apply ref.patch`
2. 残りを grep で確かめる（テーマの古い規則、ブロックの左右の margin、`style.css` を直接読む箇所、`theme_css_ver`）
3. Docker の検証環境（adaptive-validation-runner）で `php -l` と画面の測定を main と対象ブランチで流す

## Validation and Acceptance

- 7 テーマ × リソース編集（a=27&id=1）・システムログ（a=114）・検索（a=71&q=parent:0）で、入力欄・select・ボタンの高さが、テーマごとに 1 つの値になる（RevoClassic 34px、ほか 36px、RevoStyle 26px）
- 7 テーマ × 主な画面（約 36 画面）で、`#mainPane` の中の最初の `h1` と、その後ろの最初の枠か地を持つ箱の左右の端の差が 0
- ログイン画面で、forms.css の `<link>` がテーマの style.css より前にある
- 変更した PHP が `php -l` を通る

## Idempotence and Recovery

作業ツリーは `.claude/worktrees/feat-manager-form-controls-and-spacing`。測定は何度流してもよい。テーマを切り替えるスクリプトは最後に RevoClassic へ戻す。

## Artifacts and Notes

- 参考の実装: https://github.com/modxcms-jp/modxjp/commit/3c0169d47759a8c352588f34172832d5842072eb
- 測定スクリプト: 参考の実装のセッションが作った themes.js・blocks.js・order.js

## Interfaces and Dependencies

- 新しい関数 `manager_theme_stylesheets(): string`（`<link>` 2 つを改行区切りで返す）
- テーマの新しい変数: `--control-*`・`--button-*`・`--page-padding-x`（一覧は theme-variables.md・theme-spacing.md）
- 外部のテーマ: 変数を決めなければ forms.css の既定値で表示される。テーマに古い規則が残っていれば、そちらが勝つ（壊れない）
