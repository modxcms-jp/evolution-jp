# ExecPlan: スキルエージェント自己成長運用基盤

## Purpose / Big Picture

スキルエージェントの改善を、場当たり的なプロンプト追記ではなく、ExecPlan 単位の観測・学習・最適化・proposal 化として扱える基盤を整える。これにより、学びを継続的に蓄積しつつ、`SKILL.md` の肥大化や陳腐化を抑えたまま、AI 実装の反復速度と再現性を上げる。

## Progress

- [x] (2026-05-02) 対象範囲と非対象を定義し、Phase 1 を proposal-first に限定する
- [x] (2026-05-02) run 保存先と `trace.jsonl` の軽量スキーマを確定する
- [x] (2026-05-02) 学習トリガー成立を示す軽量通知ファイルの契約を定義する
- [x] (2026-05-02) `learning.json` / `pruning.json` / `proposal.json` の出力契約を定義する
- [x] (2026-05-02) `SKILL.md` 予算ルール、`stale` 判定、`add / move / merge / retire` の運用ルールを確定する
- [x] (2026-05-02) 対象 skill 1 件を使った検証シナリオと復旧手順を確定する
- [x] (2026-05-02) `skill:init` と `skill:validate` の CLI 導線を追加し、完了後のフック手順を定義する
- [x] (2026-05-02) `skill:complete` を追加し、validate と次 run scaffold 準備を 1 コマンドにまとめる
- [x] (2026-05-02) `skill:status` を追加し、run 一覧と状態を確認できるようにする
- [x] (2026-05-02) `skill:prune` を追加し、stale 候補を抽出できるようにする
- [x] (2026-05-02) `skill:archive` を追加し、完了済み run を archive へ移せるようにする
- [x] (2026-05-02) `skill:sync` を追加し、run と archive から skill metadata を再集計できるようにする

## Surprises & Discoveries

- 2026-05-02 時点で、スキル改善を記録・昇格・退役させる専用の SSOT は未整備であり、`.agent/runs/` は主に検証ログ置き場として使われている。
- 毎回 `structured review` を回す案は、短いタスクや手戻りの少ない変更ではトークン効率が悪く、改善対象よりレビュー自体が目的化しやすい。
- チャット全文の常時保存はノイズと保存コストが大きく、誤推論も長く残るため、正本は軽量トレース、全文は必要時の参考資料に分離した方がよい。
- 既存スキル群は `SKILL.md`、`references/`、`scripts/` の分離前提で設計されており、自己成長でもこの責務分離を壊さないことが重要である。
- 2026-05-02 時点で `.agent/runs/` ディレクトリ自体が未作成であり、通知契約は新規導入として自由度がある。
- 2026-05-02 時点で `.agent/runs/` と `.agent/skill-metadata/` の初期雛形を追加し、保存先の構造を実体化した。
- 2026-05-02 時点で `skill:init` コマンドを追加し、isolated include で scaffold 生成を検証した。
- 2026-05-02 時点で `skill:validate` コマンドを追加し、JSON 契約の機械検証を可能にした。
- 2026-05-02 時点で `skill:complete` コマンドを追加し、完了処理の導線を 1 コマンドへ束ねた。
- 2026-05-02 時点で `skill:status` コマンドを追加し、run 一覧と状態を機械可読に確認できるようにした。
- 2026-05-02 時点で `skill:prune` コマンドを追加し、stale 候補の抽出を機械化した。
- 2026-05-02 時点で `skill:archive` コマンドを追加し、完了済み run を archive へ移せるようにした。
- 2026-05-02 時点で `skill:sync` コマンドを追加し、run と archive から skill metadata を再集計できるようにした。
- 2026-05-02 時点でレビュー指摘を受け、`skill:validate` の契約検証と `skill:sync` / `skill:prune` の入力検証を強化した。

## Decision Log

### 2026-05-02: 学び生成の標準トリガーを ExecPlan 完了時に寄せる

- **決定**: 実行ごとの常時レビューではなく、標準運用は ExecPlan 完了時の `learning pass` と `pruning pass` に集約する
- **理由**:
  - タスク完了単位の方が、局所的な試行錯誤よりも再発防止に効く学びを抽出しやすい
  - トークンコストを抑えつつ、完了条件に照らした振り返りができる
  - 既存の `.agent/plans/` と整合し、完了時フックを設計しやすい
- **代替案**: すべての実行後に `structured review` を生成する
- **不採用理由**: コストが高く、短い変更や成功ケースでも冗長な振り返りが増える

### 2026-05-02: 正本は軽量トレースと proposal に分離し、チャット全文は参考資料へ下げる

- **決定**: 実行中は `trace.jsonl` を正本として残し、必要時のみ `chat.md` に抜粋を保存する
- **理由**:
  - 実行エージェント、使った skill、主要コマンド、編集ファイル、失敗分類、方向転換だけあれば学びの抽出に十分なことが多い
  - 自由文のチャット全文を毎回読むより、構造化イベントから集計した方が安定する
  - SSOT を `trace.jsonl` / `learning.json` / `proposal.json` に寄せることで、後続実装が単純になる
- **代替案**: チャット全文を常時保存し、それをもとに後から学びを要約する
- **不採用理由**: ノイズが多く、抽出時のトークン消費と誤学習リスクが高い

### 2026-05-02: 自己成長には pruning を必須工程として含める

- **決定**: `learning pass` の後に必ず `pruning pass` を実行し、改善提案を `add / move / merge / retire` の4種に制限する
- **理由**:
  - 追記だけの改善ループでは `SKILL.md` と `references/` が肥大化し、発火精度と保守性が落ちる
  - `move` と `merge` を標準手段にすることで、追加前に整理を促せる
  - `retire` を明示的に扱うことで、不要項目を運用上の第一級概念にできる
- **代替案**: 学びをまずは `add` のみで扱い、整理は別タスクに任せる
- **不採用理由**: 肥大化が初期から進行し、運用開始後に整理負債を抱える

### 2026-05-02: 学習タイミングの通知は軽量ファイルで表現する

- **決定**: 学習トリガーが成立したことは、対話メッセージ依存ではなく `.agent/runs/<date>-<slug>/learning-request.json` の生成で表現する
- **理由**:
  - エージェント実行経路が増えても、機械可読な通知契約を維持しやすい
  - 後から CLI、dashboard、集計ジョブへ接続しやすい
  - フラグファイル 1 つならコストが軽く、通知未処理も検出しやすい
- **代替案**: チャット応答だけで「学習すべき」と伝える
- **不採用理由**: 実行履歴との結び付きが弱く、後から自動検知や未処理管理をしづらい

### 2026-05-02: 保存先の実体と雛形を先に置く

- **決定**: `.agent/runs/` と `.agent/skill-metadata/` に README とテンプレート雛形を先に作成する
- **理由**:
  - 実装前に保存先の責務を見える化できる
  - 将来の run 生成処理が、雛形をそのままコピーするだけで済む
  - ディレクトリの存在そのものが仕様の確認材料になる
- **代替案**: 実装コードだけ先に作り、ディレクトリは後から作る
- **不採用理由**: 運用者が初回作成時の配置規則を読み取れず、保存先が散りやすい

### 2026-05-02: scaffold 生成コマンドは `skill:init` に寄せる

- **決定**: run scaffold と skill metadata 初期化は `php evo skill:init` にまとめる
- **理由**:
  - CLI の命名規則と整合し、既存の `db:*` / `log:*` と同じ感覚で使える
  - 1 コマンドで `run` と `skill-metadata` を同時初期化できる
  - ファイル名にハイフンが増えすぎるより、コマンド名の見通しがよい
- **代替案**: `skill:run:init` のように多段コマンドへ分解する
- **不採用理由**: dispatcher がファイル名のハイフンをすべてコロンへ変換するため、名前が冗長になる

### 2026-05-02: JSON 契約の検証は `skill:validate` に寄せる

- **決定**: run scaffold と skill metadata の契約検証は `php evo skill:validate` で行う
- **理由**:
  - `learning-request.json` と `proposal.json` を中心に、run 全体の形を機械的に点検できる
  - `skill:init` と対になるコマンドとして覚えやすい
  - strict モードを追加すれば、欠落ファイルも検出できる
- **代替案**: 検証を手動の `php -r` や `jq` に任せる
- **不採用理由**: 再現性が低く、初心者実行可能の要件に合わない

### 2026-05-02: 完了処理は `skill:complete` に寄せる

- **決定**: validate、`learning-request.json` の完了更新、次 run scaffold の準備を `php evo skill:complete` にまとめる
- **理由**:
  - 進捗の終点と次 run の始点を 1 コマンドで結べる
  - 完了時の手順を忘れにくくなる
  - `--skip-next` を付ければ、検証だけで止める運用もできる
- **代替案**: `skill:validate` と `skill:init` を都度手で 2 回叩く
- **不採用理由**: 完了フローの抜け漏れが起きやすい

### 2026-05-02: 状態確認は `skill:status` に寄せる

- **決定**: run の一覧と `learning-request.json` / `proposal.json` の状態確認は `php evo skill:status` で行う
- **理由**:
  - 学習トリガー、proposal-first の状態、次 run の有無を 1 回で把握できる
  - `--skill` / `--plan` で絞り込みやすい
  - `--json` で後続自動化にも使える
- **代替案**: `ls` と個別 `cat` で毎回確認する
- **不採用理由**: 運用時の確認コストが高く、状態の見落としが起きやすい

### 2026-05-02: stale 抽出は `skill:prune` に寄せる

- **決定**: `stats.json` と `history.jsonl` から stale 候補を抽出する処理は `php evo skill:prune` で行う
- **理由**:
  - `skill:status` が現在地、`skill:prune` が削減候補を分離できる
  - ルールベースのため、まずは提案だけで十分に運用できる
  - `--json` で後続の自動化に渡しやすい
- **代替案**: stale 判定を各 skill の手書き運用に任せる
- **不採用理由**: 肥大化抑制の一貫性が失われる

### 2026-05-02: 完了 run の整理は `skill:archive` に寄せる

- **決定**: 完了済み run の退避と `proposal.json` の `archived` 更新は `php evo skill:archive` で行う
- **理由**:
  - アクティブ run と履歴 run を分離し、`skill:status` の見通しを保てる
  - proposal-first の状態を残したまま履歴化できる
  - `--strict` により、アーカイブ前の契約検証も再利用できる
- **代替案**: 完了 run を手でディレクトリ移動する
- **不採用理由**: 状態更新漏れが起きやすく、履歴管理が壊れやすい

### 2026-05-02: `skill:sync` で skill metadata を再集計する

- **決定**: run と archive の結果から `inventory.json` / `stats.json` / `history.jsonl` を再構築する処理は `php evo skill:sync` に寄せる
- **理由**:
  - `skill:archive` 後の正本更新を明示的な 1 コマンドにできる
  - `skill:prune` が参照する集計値の SSOT を保ちやすい
  - `--dry-run` で更新前の差分確認ができる
- **代替案**: `inventory.json` / `stats.json` / `history.jsonl` を各コマンドで個別更新する
- **不採用理由**: 集計の責務が分散し、同期漏れと二重更新が起きやすい

### 2026-05-02: レビューで露出した契約穴は検証側で塞ぐ

- **決定**: `skill:validate` で `evidence` の必須ファイル、`skill` / `plan_id` / `run_id` の整合、`trace.jsonl` の type ごとの必須キーを検証する
- **理由**:
  - 例示用の README と実装の契約ずれを、実行時検証で早期に検出できる
  - `skill:init` / `skill:validate` / `skill:sync` / `skill:prune` の入力を同じ識別子規則へ寄せられる
  - 失敗が run 生成時点で止まるため、後続の同期・退役判定の汚染を抑えられる
- **代替案**: README とサンプルだけ更新し、CLI 側の検証は最小限に留める
- **不採用理由**: 契約逸脱を実行後まで持ち越してしまい、自己成長基盤の SSOT と検証性が弱くなる

## Outcomes & Retrospective

未着手。完了時には、対象 skill 1 件について proposal-first の改善ループが再現できたか、`SKILL.md` を太らせずに学びと pruning の両方を扱えたか、実運用コストが許容範囲かを振り返る。

## Context and Orientation

本タスクの主対象は、スキル本体そのものではなく、スキル改善を扱う運用基盤である。対象となるファイル群は次の通り。

- `.agent/PLANS.md`
  - ExecPlan の正本。完了時フックや完了同期の前提になる
- `.agent/roadmap.md`
  - タスク管理の SSOT。自己成長基盤もここで追跡する
- `.agent/runs/`
  - 実行ログ保存先。今回の軽量トレース、学習結果、pruning 結果、proposal の配置候補
- `.codex/skills/<skill>/SKILL.md`
  - 常時ロードされる入口。判断基準と分岐だけに寄せ、詳細知識は持ち込みすぎない
- `.codex/skills/<skill>/references/`
  - 詳細知識と例外条件の置き場。`move` の主な受け皿
- `.codex/skills/<skill>/scripts/`
  - 繰り返し手作業の固定化先。`repeated_manual_work` の主な受け皿
- `.codex/skills/<skill>/retired/`
  - 退役項目の退避先。削除判断の履歴保持に使う

本 ExecPlan における用語は次の意味で使う。

- `trace.jsonl`: 実行中に追記する軽量イベント列。1 行 1 JSON で、主要行動・判断・失敗分類・結果だけを残す
- `learning pass`: ExecPlan 完了時に、軽量トレースと必要最小限の補助資料から、再発防止に効く学びを抽出する工程
- `pruning pass`: 既存 skill の未使用・重複・陳腐化を点検し、移管・統合・退役候補を出す工程
- `proposal-first`: 学びを即時反映せず、まず `proposal.json` として保存し、人間または後続フローの確認後にだけ昇格させる運用
- `stale`: 直近の関連 run で使われない、または効果が薄いと判定された項目の状態
- `learning-request.json`: 学習トリガーが成立した run に置く軽量通知ファイル。後続の `learning pass` 実行要求を表す

`trace.jsonl` の初期イベントスキーマは、各行を 1 JSON オブジェクトとし、全イベント共通で次の項目を持つ。

```json
{
  "ts": "2026-05-02T15:20:00+09:00",
  "plan_id": "2026-05-02-agent-skill-growth-loop",
  "run_id": "2026-05-02-agent-skill-growth-loop-001",
  "agent": "worker",
  "skill": "issue-resolver",
  "type": "error",
  "summary": "既存責務境界を誤認した"
}
```

共通必須項目は次の 6 つとする。

- `ts`
- `plan_id`
- `run_id`
- `agent`
- `skill`
- `type`
- `summary`

`type` は Phase 1 では次の 5 種に固定する。

- `step`
- `decision`
- `error`
- `feedback`
- `result`

型ごとの追加必須項目は次の通り。

- `step`
  - `action`
  - `status`
- `decision`
  - `category`
- `error`
  - `failure_mode`
  - `status`
- `feedback`
  - `feedback_type`
  - `source`
- `result`
  - `status`

任意項目は次に限定する。

- `files`
- `command`
- `category`
- `failure_mode`
- `feedback_type`
- `reason_code`
- `source`
- `status`
- `action`
- `refs`
- `metadata`

`status` は `started | ok | failed | blocked | done` のいずれかとする。

`agent` は Phase 1 では `worker | explorer | reviewer | planner | user | system` に固定する。

`feedback_type` は次に固定する。

- `direction_change`
- `rework_request`
- `scope_change`
- `priority_change`

`reason_code` は閾値判定と集計のための短い正規化コードで、自由文を入れない。Phase 1 では次を許容する。

- `proposal_first_required`
- `wrong_skill_triggered`
- `reference_not_loaded`
- `validation_missing`
- `scope_too_broad`
- `manual_steps_repeated`

`refs` は相対パスまたは識別子の配列とし、関連する `SKILL.md`、ExecPlan、reference、proposal を指せるようにする。`metadata` は少量の補助キー値を保持する連想配列とするが、巨大な自由文ログや全文チャットの断片を入れてはならない。

`error` イベントでは `failure_mode` を必須とし、同一 run 内の閾値判定に使う正規化値を入れる。例:

```json
{
  "ts": "2026-05-02T15:22:00+09:00",
  "plan_id": "2026-05-02-agent-skill-growth-loop",
  "run_id": "2026-05-02-agent-skill-growth-loop-001",
  "agent": "worker",
  "skill": "issue-resolver",
  "type": "error",
  "status": "failed",
  "failure_mode": "missing_instruction",
  "summary": "proposal-first の運用ルールが弱く即反映しようとした",
  "reason_code": "proposal_first_required",
  "refs": [
    ".codex/skills/issue-resolver/SKILL.md"
  ]
}
```

`feedback` イベントでは差し戻しや方向転換を正規化し、`source` と `feedback_type` を必須とする。例:

```json
{
  "ts": "2026-05-02T15:24:00+09:00",
  "plan_id": "2026-05-02-agent-skill-growth-loop",
  "run_id": "2026-05-02-agent-skill-growth-loop-001",
  "agent": "user",
  "skill": "issue-resolver",
  "type": "feedback",
  "source": "user",
  "feedback_type": "direction_change",
  "summary": "毎回レビューせず ExecPlan 完了時にまとめたい",
  "reason_code": "proposal_first_required"
}
```

集計ルールは次を正本とする。

- 閾値判定に使うのは `error` の `failure_mode` と、`feedback` の `reason_code`
- 同一 run 内で同じ `failure_mode` または `reason_code` が複数回出ても 1 回として数える
- `summary` は人間可読の補助であり、閾値判定のキーに使わない

`learning-request.json` の初期スキーマは次を正本とする。

```json
{
  "version": 1,
  "plan_id": "2026-05-02-agent-skill-growth-loop",
  "run_id": "2026-05-02-agent-skill-growth-loop-001",
  "skill": "issue-resolver",
  "trigger": "execplan_completed",
  "requested_at": "2026-05-02T15:30:00+09:00",
  "status": "pending",
  "priority": "normal",
  "reason_summary": "ExecPlan完了により学び生成対象になった",
  "evidence": [
    "trace.jsonl"
  ]
}
```

各項目の意味は次の通り。

- `version`: 通知契約の版。Phase 1 では `1` 固定
- `plan_id`: 紐づく ExecPlan ID
- `run_id`: run ディレクトリを一意に識別する ID
- `skill`: 学習対象 skill。Phase 1 では 1 skill に限定
- `trigger`: 学習要求を出した理由
- `requested_at`: 通知生成時刻
- `status`: `pending | processing | completed | skipped`
- `priority`: `low | normal | high`
- `reason_summary`: 人間が一覧で読める短い説明
- `evidence`: 学習時に読むべき補助資料の相対ファイル名一覧

`trigger` は Phase 1 では次の 3 種に固定する。

- `execplan_completed`
- `user_feedback`
- `failure_threshold_exceeded`

`priority` は次の基準で決める。

- `normal`: ExecPlan 完了時の標準学習
- `high`: 明示差し戻しが入った、または同種失敗が閾値を超えた
- `low`: 任意観測や試験 run で、後回し可能

`run_id` は `<YYYY-MM-DD>-<plan-slug>-<seq3>` の形式に固定する。例: `2026-05-02-agent-skill-growth-loop-001`。ここで `plan-slug` は ExecPlan ファイル名の日付以降と一致させ、`seq3` は同一 `plan_id` 配下で 3 桁ゼロ埋め連番とする。これにより、run ディレクトリ名、通知ファイル、集計キーを同じ識別子で扱える。

`evidence` は相対ファイル名の配列として扱い、Phase 1 では最低 1 件の `trace.jsonl` を必須とする。追加できる候補は次に限定する。

- `trace.jsonl`
- `chat.md`
- `learning.json`
- `pruning.json`
- `proposal.json`
- `notes.md`

`evidence` の初期ルールは次の通り。

- `execplan_completed`: `trace.jsonl` を必須、`chat.md` は任意
- `user_feedback`: `trace.jsonl` と `chat.md` を必須
- `failure_threshold_exceeded`: `trace.jsonl` を必須、必要なら `notes.md` を追加

同一 run で `learning-request.json` を再生成する場合は、既存ファイルを破棄せず `status` と `reason_summary` を更新し、`evidence` は重複を除いて追記する。`run_id` 自体は変更しない。

`failure_threshold_exceeded` の閾値は、Phase 1 では次の固定ルールを正本とする。

- 集計対象は同一 `skill` の直近 10 run
- 同一 `failure_mode` が 3 回以上出現したら発火
- または同一 `bad_trigger` が 2 回以上出現したら発火
- 同一原因に対するユーザー差し戻しが 2 回以上入ったら発火

ここで使う分類語は `trace.jsonl` または後続集計で次に正規化する。

- `failure_mode`: `bad_assumption | missing_instruction | missing_reference | repeated_manual_work | tool_gap | validation_gap`
- `bad_trigger`: 誤った skill 発火、または読むべき reference を読まずに進んだケース

閾値判定は run 単位で行い、同一 run 内で同じ `failure_mode` が複数回出ても 1 回として数える。これにより、単一 run の長い試行錯誤で閾値を誤って超えないようにする。

`failure_threshold_exceeded` で通知を作るときの `priority` は `high` 固定とし、`reason_summary` には少なくとも `failure_mode`、観測回数、集計窓を含める。例: `missing_instruction が直近10 runで3回発生したため学習対象に昇格`。

`learning.json` の初期スキーマは次を正本とする。

```json
{
  "version": 1,
  "plan_id": "2026-05-02-agent-skill-growth-loop",
  "run_id": "2026-05-02-agent-skill-growth-loop-001",
  "skill": "issue-resolver",
  "generated_at": "2026-05-02T16:10:00+09:00",
  "request_ref": "learning-request.json",
  "outcome": "success_with_rework",
  "findings": [
    {
      "id": "finding-001",
      "kind": "missing_instruction",
      "summary": "proposal-first の運用を SKILL.md で明示すべき",
      "evidence": [
        "trace.jsonl"
      ],
      "suggested_action": "add",
      "target": "SKILL.md",
      "confidence": "high"
    }
  ]
}
```

`learning.json` の必須項目は次とする。

- `version`
- `plan_id`
- `run_id`
- `skill`
- `generated_at`
- `request_ref`
- `outcome`
- `findings`

`outcome` は Phase 1 では次に固定する。

- `success`
- `success_with_rework`
- `partial`
- `failed`
- `cancelled`

`findings[].kind` は次に固定する。

- `missing_instruction`
- `missing_reference`
- `repeated_manual_work`
- `bad_trigger`
- `over_detailed_instruction`
- `obsolete_instruction`

`findings[].suggested_action` は `add | move | merge | retire | script_extraction` を許容する。`findings[].target` は `SKILL.md`、`references/<file>.md`、`scripts/<file>` のいずれかに制限する。`confidence` は `low | medium | high` とする。

1 run から生成する `findings` は最大 5 件までに制限し、同一趣旨の重複を禁止する。局所的な好みや一時的な試行錯誤は finding に昇格させない。

`pruning.json` の初期スキーマは次を正本とする。

```json
{
  "version": 1,
  "plan_id": "2026-05-02-agent-skill-growth-loop",
  "run_id": "2026-05-02-agent-skill-growth-loop-001",
  "skill": "issue-resolver",
  "generated_at": "2026-05-02T16:11:00+09:00",
  "budget": {
    "skill_md_max_lines": 200,
    "max_loaded_references": 3
  },
  "items": [
    {
      "id": "prune-001",
      "action": "move",
      "target": "SKILL.md#proposal-lifecycle",
      "reason": "詳細すぎて常時ロードに不向き",
      "destination": "references/proposal-lifecycle.md",
      "evidence": [
        "trace.jsonl"
      ],
      "confidence": "high"
    }
  ]
}
```

`pruning.json` の必須項目は次とする。

- `version`
- `plan_id`
- `run_id`
- `skill`
- `generated_at`
- `budget`
- `items`

`budget` の初期必須項目は次とする。

- `skill_md_max_lines`
- `max_loaded_references`

`items[].action` は `move | merge | retire` に固定する。`add` は `pruning.json` では扱わず、`learning.json` 側の判断に寄せる。`items[].target` は既存項目だけを指し、`destination` は `move` 時のみ必須、`merge_into` は `merge` 時のみ必須とする。`reason` は人間向けの短文、`evidence` は根拠ファイル配列、`confidence` は `low | medium | high` とする。

1 run から生成する `items` は最大 5 件までとし、`learning.json` に `add` が 1 件ある場合は、原則として `pruning.json` に `move`、`merge`、`retire` のいずれかを 1 件以上含める。

`proposal.json` の初期スキーマは次を正本とする。

```json
{
  "version": 1,
  "plan_id": "2026-05-02-agent-skill-growth-loop",
  "run_id": "2026-05-02-agent-skill-growth-loop-001",
  "skill": "issue-resolver",
  "generated_at": "2026-05-02T16:12:00+09:00",
  "status": "proposed",
  "source_files": [
    "learning.json",
    "pruning.json"
  ],
  "changes": [
    {
      "id": "change-001",
      "action": "add",
      "target": "SKILL.md",
      "summary": "proposal-first を明記する",
      "source_ref": "finding-001",
      "confidence": "high"
    },
    {
      "id": "change-002",
      "action": "move",
      "target": "SKILL.md#proposal-lifecycle",
      "destination": "references/proposal-lifecycle.md",
      "summary": "詳細手順を reference へ移す",
      "source_ref": "prune-001",
      "confidence": "high"
    }
  ]
}
```

`proposal.json` の必須項目は次とする。

- `version`
- `plan_id`
- `run_id`
- `skill`
- `generated_at`
- `status`
- `source_files`
- `changes`

`status` は Phase 1 では `proposed | approved | rejected | applied | archived` に固定する。Phase 1 の完了条件では `proposed` までを対象とし、`approved` 以降は後続フェーズで扱う。

`changes[].action` は `add | move | merge | retire | script_extraction` を許容する。`source_ref` は `learning.json` または `pruning.json` の項目 ID を指し、提案の出どころを追跡できるようにする。`summary` は 1 文に制限し、実装手順ではなく変更意図を書く。

`proposal.json` の生成ルールは次を正本とする。

- `learning.json` の finding と `pruning.json` の item を束ねる
- 同一対象への競合提案がある場合は `move` / `merge` / `retire` を優先し、解消不能なら `changes` へ入れず `notes.md` に退避する
- `status` は生成直後 `proposed`
- Phase 1 では `proposal.json` 生成後に skill 本体を更新しない

対象 skill は Phase 1 では 1 件に限定する。候補は `issue-resolver` または `agent-orchestrator` とし、複数 skill 同時導入は Phase 2 以降に送る。

## Plan of Work

最初に、実運用で残すべき情報を「常時保存」と「完了時にだけ生成」に分離する。常時保存は `trace.jsonl` に絞り、実行者、使用 skill、主要コマンド、編集ファイル、失敗分類、方向転換、最終結果のみに限定する。チャット全文は正本にせず、必要なときだけ `chat.md` へ抜粋する。あわせて、ExecPlan 完了、明示差し戻し、同種失敗の閾値超過といった学習トリガーが成立したときだけ `learning-request.json` を生成し、後続処理へ渡す。

次に、ExecPlan 完了時に走る `learning pass` と `pruning pass` の入出力契約を定義する。`learning pass` は `learning.json` を生成し、`missing_instruction`、`missing_reference`、`repeated_manual_work`、`bad_trigger`、`over_detailed_instruction`、`obsolete_instruction` のような固定分類を返す。`pruning pass` は `pruning.json` を生成し、既存項目に対して `move`、`merge`、`retire` の候補を返す。学習を実行すべき run には先に `learning-request.json` が存在する前提とし、`status: pending` の通知だけを処理対象にする。完了後は `status` を `completed`、学習不要と判断した場合は `skipped` に更新し、最後に両者を束ねて `proposal.json` を作る。proposal の状態は Phase 1 では `proposed` 止まりとし、自動反映へ進めない。Phase 1 ではここで止める。

肥大化対策は、追加時の品質基準ではなく、運用ルールとして設計する。`SKILL.md` は常時ロードされるため、行数とセクション長に予算を持たせる。新規 `add` を採用する場合は、同数以上の `move`、`merge`、`retire` 候補を求める。`stale` 判定はルールベースから始め、直近 N 回の関連 run で未使用、効果率が低い、または reference / script へ移管済みの項目を候補化する。

検証は 1 つの既存 skill を対象に、ダミーではなく実際の改善シナリオで行う。少なくとも、軽量トレースの保存、完了時の `learning.json` と `pruning.json` の生成、`proposal.json` への統合、`SKILL.md` を直接変えない proposal-first の動作を確認する。

## Concrete Steps

1. 現状の skill 構成と `.agent/runs/` の既存用途を棚卸しし、保存先の責務を確定する。  
   編集対象ファイル: `.agent/roadmap.md`, `.agent/runs/`, `.codex/skills/issue-resolver/`, `.codex/skills/agent-orchestrator/`  
   実行コマンド: `rg -n "trace|runs|proposal|retired|references|scripts" .agent .codex/skills/issue-resolver .codex/skills/agent-orchestrator`  
   期待される観測結果: 既存の保存場所と競合しない run ディレクトリ構成、対象 skill 候補、退役先の有無が明確になる。

2. 実行中に残す `trace.jsonl` の最小スキーマを定義する。  
   編集対象ファイル: 対象ドキュメントまたは実装ファイル一式  
   実行コマンド: `rg -n "\"type\"|plan_id|summary|category|files" .agent/plans .agent/runs`  
   期待される観測結果: `step`、`decision`、`error`、`feedback`、`result` の5種、共通必須フィールド、型別必須フィールド、`failure_mode` / `feedback_type` / `reason_code` の正規化ルールが固定され、全文チャット保存に頼らない方針が再現できる。

3. 学習トリガー成立を示す軽量通知ファイルの契約を定義する。  
   編集対象ファイル: 対象ドキュメントまたは実装ファイル一式  
   実行コマンド: `rg -n "learning-request|ExecPlan 完了|差し戻し|閾値" .agent/plans .agent/roadmap.md`  
   期待される観測結果: `learning-request.json` の生成条件、配置先、必須フィールド、`trigger` / `status` / `priority` の許容値、`run_id` 命名規則、`evidence` 必須項目、`failure_threshold_exceeded` の閾値が固定され、対話メッセージに依存せず学習要求を表現できる。

4. `learning.json`、`pruning.json`、`proposal.json` の出力契約を定義する。  
   編集対象ファイル: 対象ドキュメントまたは実装ファイル一式  
   実行コマンド: `rg -n "learning.json|pruning.json|proposal.json|stale|merge|retire" .agent/plans .codex/skills`  
   期待される観測結果: 各 JSON の必須項目、許容値、件数上限、相互参照 ID、proposal の状態遷移が固定され、学びの分類、削減候補の分類、proposal の束ね方がぶれずに実装できる。

5. `SKILL.md` 予算ルールと `stale` 判定ルールを定義する。  
   編集対象ファイル: 対象ドキュメントまたは実装ファイル一式  
   実行コマンド: `rg -n "SKILL.md|references/|scripts/|retired/" .codex/skills`  
   期待される観測結果: 常時ロード範囲と詳細知識の置き場が整理され、追加より `move` と `merge` を優先する基準が定まる。

6. Phase 1 の検証シナリオと復旧手順を定義する。  
   編集対象ファイル: 対象ドキュメントまたは実装ファイル一式  
   実行コマンド: `git diff -- .agent/roadmap.md .agent/plans/2026-05-02-agent-skill-growth-loop.md`  
   期待される観測結果: 1 つの対象 skill に対して proposal-first の流れを再現する受け入れ条件と、中断時の復帰手順が確認できる。

## Validation and Acceptance

受け入れ条件は、自己成長の proposal-first 運用が、低コストかつ肥大化抑制付きで再現できることである。

- 実行中に保存する正本が `trace.jsonl` に限定され、チャット全文を必須前提にしない
- `trace.jsonl` の `error` イベントで `failure_mode`、`feedback` イベントで `feedback_type` と `reason_code` が必須化され、閾値判定に必要な正規化ができる
- 学習トリガー成立時に `learning-request.json` を生成する契約があり、通知が会話ログ依存でない
- `learning-request.json` の必須項目が `version`, `plan_id`, `run_id`, `skill`, `trigger`, `requested_at`, `status`, `priority`, `reason_summary`, `evidence` に固定されている
- `run_id` が `<YYYY-MM-DD>-<plan-slug>-<seq3>` 形式で一意に採番され、`evidence` が少なくとも `trace.jsonl` を含む
- `failure_threshold_exceeded` が「直近10 runで同一 failure_mode 3回、同一 bad_trigger 2回、同一原因の差し戻し2回」の固定閾値で判定される
- ExecPlan 完了時に `learning.json`、`pruning.json`、`proposal.json` の3成果物を生成する設計があり、`proposal.json` は Phase 1 では `proposed` 止まりで skill 本体を更新しない
- 改善提案の操作種別が `add / move / merge / retire` の4種に固定されている
- `SKILL.md` に予算ルールがあり、新規追加時に pruning 候補の同時提示を必須化している
- `stale` 判定と `retire` 条件が定義され、不要項目を明示的に捨てられる
- 対象 skill 1 件で Phase 1 の検証手順が書かれており、自動反映は非対象であることが明示されている

## Idempotence and Recovery

途中で中断した場合は、まず `git diff -- .agent/roadmap.md .agent/plans/2026-05-02-agent-skill-growth-loop.md` で計画差分を確認する。自己成長基盤は概念整理が多く、用語だけが増えて実装契約が曖昧になりやすいため、復帰時は `Context and Orientation` と `Decision Log` を先に読み直し、正本が `trace.jsonl` と proposal 群であることを再確認する。

実装段階へ進んだ後に中断した場合は、対象 skill を 1 件に限定する原則を維持し、複数 skill への横展開は保留する。`SKILL.md` に直接追記したくなった場合でも、先に `proposal.json` へ落とし、`move`、`merge`、`retire` で吸収できないかを見直してから再開する。

## Artifacts and Notes

- ロードマップ: `.agent/roadmap.md`
- ExecPlan 仕様: `.agent/PLANS.md`
- 関連スキル: `.codex/skills/exec-plan/SKILL.md`, `.codex/skills/issue-resolver/SKILL.md`, `.codex/skills/agent-orchestrator/SKILL.md`
- 想定成果物:
  - `.agent/runs/<date>-<slug>/trace.jsonl`
  - `.agent/runs/<date>-<slug>/learning-request.json`
  - `.agent/runs/<date>-<slug>/chat.md`
  - `.agent/runs/<date>-<slug>/learning.json`
  - `.agent/runs/<date>-<slug>/pruning.json`
  - `.agent/runs/<date>-<slug>/proposal.json`
  - `.agent/skill-metadata/<skill>/inventory.json`
  - `.agent/skill-metadata/<skill>/stats.json`
  - `.agent/skill-metadata/<skill>/history.jsonl`
- 想定コミット単位:
  - 1: 運用設計と保存スキーマの導入  
    対象: `.agent/plans/`, `.agent/roadmap.md`, 必要なら `assets/docs/`  
    コミットメッセージ案: `feat(agent): スキル自己成長のExecPlanと保存契約を追加`
  - 2: proposal-first 実装と検証フローの導入  
    対象: 実装ファイル、対象 skill、検証用 run 記録  
    コミットメッセージ案: `feat(agent): スキル改善proposal生成フローを追加`

## Interfaces and Dependencies

- 前提依存: `.agent/PLANS.md` の完了処理プロトコル、`.agent/roadmap.md` のタスク同期ルール
- 関連依存: `.agent/runs/` の保存慣行、既存 skill の `SKILL.md` / `references/` / `scripts/` 構成
- 本タスクの内部インターフェース:
  - `trace.jsonl` は実行中イベントの正本
  - `learning-request.json` は学習トリガー通知の正本
  - `learning.json` は学び抽出の正本
  - `pruning.json` は最適化候補の正本
  - `proposal.json` は昇格前の改善提案の正本
  - `inventory.json` / `stats.json` / `history.jsonl` は skill ごとの寿命管理に使う
- Phase 1 の非対象:
  - skill 本体への自動反映
  - 全文チャットの常時保存
  - 全 skill 同時導入
  - モデル再学習や外部ベクターストア導入
