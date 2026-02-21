# ExecPlan: ログ閲覧画面のページング配列キー未定義エラー修正

## Purpose / Big Picture

管理画面「ツール→イベントログ」でログが7ページ以上ある場合、1ページ目や最終ページ付近で `Undefined array key` 警告が発生する。この警告を解消し、全ページで正常にページネーションが表示されるようにする。

## Progress

- [x] (2026-02-13) logging.static.php のページングウィンドウに境界チェックを追加
- [x] (2026-02-13) paginate.inc.php の getNumberOfPage() に ceil() を適用
- [x] (2026-02-13) 静的検証（対象2ファイルの `php -l` で構文エラーなし）
- [x] (未実施) 画面検証（イベントログ7ページ以上で 1/2/中間/最終ページの warning 非発生を確認）

## Surprises & Discoveries
- `Paging::getNumberOfPage()` が小数を返す設計のため、`getCurrentPage()` の計算が間接的に小数依存になっていた。`ceil()` + `int` 化で `getPagingRowArray()` のループ境界が明確化された。
## Decision Log

- 2026-02-13 / AI / `messages.static.php` も同じ `Paging` クラスを使用しているが、こちらは `foreach` でループしているため境界チェック不要。影響は `logging.static.php` のみ。
- 2026-02-13 / AI / `logging.static.php` のページ番号表示は「常に5件表示」の既存UIを維持し、先頭・末尾でウィンドウをスライドさせる方式を採用した。

## Outcomes & Retrospective
- `manager/actions/report/logging.static.php` で配列アクセスを固定オフセットから境界付きループへ変更し、`Undefined array key` の発生条件を除去した。
- `manager/includes/paginate.inc.php` で総ページ数を切り上げ整数化し、ページ配列生成の境界を安定化した。
- 画面手動確認（イベントログ7ページ以上の実ブラウザ確認）は未実施。コード上の再発要因は解消済み。
## Context and Orientation

**エラー報告**:

```
PHP Warning: Undefined array key -2
File: manager/actions/report/logging.static.php
Line: 306
Source: $paging .= $array_row_paging[$current_row - 2];
```

**関連ファイル**:

- `manager/actions/report/logging.static.php` — ログ閲覧画面（修正対象）
- `manager/includes/paginate.inc.php` — `Paging` クラス（修正対象）
- `manager/actions/permission/messages.static.php` — 同じ `Paging` クラスを使用（修正不要）

**再現条件**: ログが700件超（デフォルト100件/ページ×7ページ以上）で、1〜2ページ目または末尾1〜2ページ目を表示した場合に発生。

## Plan of Work

2箇所を修正する。

**修正1: logging.static.php（主原因）** — 固定インデックス `$current_row - 2` 〜 `$current_row + 2` でページ番号配列にアクセスしている箇所を、境界クランプ付きのループに置き換える。表示するページ数（5ページ）は変わらず、端のページでもウィンドウがスライドして範囲外アクセスを防ぐ。ここでいう「5件ウィンドウ」は、現在ページを中心に最大5個のページ番号リンクを表示する仕組みを指す。

**修正2: paginate.inc.php（副次的）** — `getNumberOfPage()` が `$nbr_row / $num_result` を返しているが、`ceil()` を使っていないため端数がある場合にページ数が浮動小数点になる。`ceil()` を適用して正確な整数ページ数を返すようにする。また `$current_row` も `(int)` でキャストして型安全を確保する。
この Plan は実装者が過去チャットを参照しなくても実施できるよう、対象ファイル・コマンド・期待観測結果を各手順に明記する。

## Concrete Steps

1. ページング配列アクセスを境界クランプ付きループへ置換する。
   編集対象ファイル: `manager/actions/report/logging.static.php`（305〜315行付近）
   実行コマンド: `php -l manager/actions/report/logging.static.php`
   期待される観測結果: 構文エラーなし。先頭/末尾ページで範囲外アクセス由来の warning が出ない。
2. `$current_row` を整数で扱うよう型安全化する。
   編集対象ファイル: `manager/actions/report/logging.static.php`（291行付近）
   実行コマンド: `rg -n "int_cur_position|current_row" manager/actions/report/logging.static.php`
   期待される観測結果: `$current_row` が整数化され、インデックス計算が小数依存しない。
3. `getNumberOfPage()` を切り上げ整数返却へ修正する。
   編集対象ファイル: `manager/includes/paginate.inc.php`
   実行コマンド: `php -l manager/includes/paginate.inc.php`
   期待される観測結果: 構文エラーなし。総ページ数が整数化され、ページ配列生成のループ境界が安定する。

## Validation and Acceptance

管理画面「ツール→イベントログ」で以下を確認する:

1. `php -l manager/actions/report/logging.static.php manager/includes/paginate.inc.php` を実行し、両ファイルで `No syntax errors detected` が出ること。
2. ログが7ページ以上（700件超）ある状態で検索を実行し、1ページ目で `Undefined array key` 警告が出ないこと。
3. 同条件で2ページ目を表示し、警告が出ないこと。
4. 中間ページを表示し、現在ページ中心の5件ウィンドウでページ番号が表示されること。
5. 最終ページを表示し、範囲外アクセス由来の警告が出ないこと。
6. ログが6ページ以下の条件では、従来どおり全ページ番号が表示されること。
7. 実行したコマンドと観測結果を本 Plan に追記し、過去チャットを見ずに検証を再現できること。

## Idempotence and Recovery

変更は2ファイル・3箇所のみ。再実行しても同じ差分に収束する。中断時は次の順で復帰する。

1. `git diff -- manager/actions/report/logging.static.php manager/includes/paginate.inc.php` で差分を確認する。
2. 作業を破棄する場合は `git restore --source=HEAD -- manager/actions/report/logging.static.php manager/includes/paginate.inc.php` で対象2ファイルのみ復元する。
3. 復元後に `php -l manager/actions/report/logging.static.php manager/includes/paginate.inc.php` を再実行し、構文正常を確認してから再着手する。

## Artifacts and Notes

- `manager/actions/report/logging.static.php` — 管理画面アクション13（Viewing logging）
- `manager/includes/paginate.inc.php` — 汎用ページングクラス（2001年製、外部ライブラリ由来）

## Interfaces and Dependencies

`Paging` クラスの `getNumberOfPage()` は `private` メソッドのため、外部への影響なし。`messages.static.php` は `getPagingRowArray()` の戻り値を `foreach` で使用しているため、配列要素数が変わっても影響なし。
