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

## テンプレート

雛形は `templates/` 配下に置く。
