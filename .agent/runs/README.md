# Run Artifacts

このディレクトリは、スキル改善の実行痕跡を残す正本です。ExecPlan 完了時の学習処理は、ここに保存された軽量トレースと通知ファイルを参照します。
完了 run の整理後は `skill:sync` で skill metadata を再集計する。

## 標準構成

```text
.agent/runs/<YYYY-MM-DD>-<plan-slug>-<seq3>/
├── trace.jsonl
├── learning-request.json
├── chat.md
├── learning.json
├── pruning.json
├── proposal.json
└── notes.md
```

```text
.agent/runs/archive/<run-id>/
├── trace.jsonl
├── learning-request.json
├── chat.md
├── learning.json
├── pruning.json
├── proposal.json
└── notes.md
```

## 使い分け

- `trace.jsonl`: 実行中に追記する軽量イベント列
- `learning-request.json`: 学習トリガー成立を示す通知
- `chat.md`: 必要な場合だけ残す会話抜粋
- `learning.json`: 学び抽出の結果
- `pruning.json`: 肥大化抑制の結果
- `proposal.json`: 昇格前の改善提案
- `notes.md`: 任意の補助メモ

## 命名・検証ルール

- run ID は `<plan_id>-<seq3>`（例: `2026-05-02-agent-skill-growth-loop-001`）。
- `plan_id` / `run_id` / `skill` は英数字開始、以降は `A-Za-z0-9._-` のみ。
- `templates` / `archive` は run ディレクトリの予約名として扱う。
- `learning-request.json` の `evidence` は許可済みファイルのみ記録する。
- `skill:validate --strict` 実行時は、`evidence` に列挙したファイル実体も一致させる。

## 推奨運用フロー

1. run 開始時に `php evo skill:init --plan=... --skill=...` を実行する。
2. 学習結果を更新したら `php evo skill:complete --run-dir=... --strict` で完了処理する。
3. 退避が必要なら `php evo skill:archive --run-dir=... --strict` を実行する。
4. 集計を `php evo skill:sync [--skill=...]` で更新する。
5. stale 候補を `php evo skill:prune [--skill=...]` で確認する。

## テンプレート

雛形は `templates/` 配下に置く。
