# Execution Plans (ExecPlans)

ExecPlan の仕様書。複雑なタスクはこの仕様に従い `.agent/plans/YYYY-MM-DD-task-name.md` に作成する。
参考: [Codex Execution Plans](https://developers.openai.com/cookbook/articles/codex_exec_plans/)

## NON-NEGOTIABLE REQUIREMENTS

1. **自己完結**: 予備知識なしで実装完了できる情報量を含むこと
2. **生きたドキュメント**: 進捗・発見・意思決定に応じて継続的に更新すること
3. **初心者実行可能**: 専門用語には平易な説明を添えること
4. **動作する成果物**: 観察可能な動作（ブラウザ確認、CLI実行等）を示すこと
5. **用語定義**: 専門用語は平易に定義するか使用しないこと

## テンプレート

```markdown
# ExecPlan: [タスク名]

## Purpose / Big Picture
## Progress
- [ ] (YYYY-MM-DD) ステップの説明
## Surprises & Discoveries
## Decision Log
## Outcomes & Retrospective
## Context and Orientation
## Plan of Work
## Concrete Steps
## Validation and Acceptance
## Idempotence and Recovery
## Artifacts and Notes
## Interfaces and Dependencies
```

各セクションの記載内容:

- **Purpose**: ユーザーにとっての価値を1–2文で
- **Progress**: タイムスタンプ付きチェックリスト。完了マイルストーンは1行要約に圧縮
- **Surprises & Discoveries**: 想定外の挙動や知見（根拠を添える）
- **Decision Log**: 設計判断（日付・著者・根拠・代替案）
- **Outcomes & Retrospective**: 達成内容と教訓
- **Context and Orientation**: 対象コードの場所・前提条件（パスはリポジトリルート相対）
- **Plan of Work**: 実装方針とその選定理由
- **Concrete Steps**: 手順ごとに「編集対象ファイル」「実行コマンド」「期待される観測結果」を含む具体手順
- **Validation and Acceptance**: 内部実装の説明ではなく、ユーザーが観察可能な動作で定義した完了条件
- **Idempotence and Recovery**: 中断時の復帰手順
- **Artifacts and Notes**: 関連ファイル・URL
- **Interfaces and Dependencies**: 外部依存・他モジュールとのインターフェース

空セクションは見出しのみ残す（プレースホルダ説明は書かない）。

## ルール

- Progress 以外ではチェックボックスを多用せず散文で記述
- 検証手順には具体的なコマンドと期待出力を含める
- ネストしたコードブロックは使わずインデントで表現
- 実装精度と再現性を維持したまま簡潔に書き、同一趣旨の重複記述を避ける
- 長大なコード全文の貼り付けは原則避け、対象箇所・変更意図・確認コマンドを優先する
- 次のマイルストーンへ自律的に進む（「次は？」と確認しない）
- 完了した ExecPlan は削除せずナレッジとして残す

## 成功基準

ステートレスなエージェントまたは初心者が、ExecPlan を通読して動作する成果物を生み出せること。
過去チャットの文脈を知らない実装者でも、同じ結果に再現できること。

## 関連

- `AGENTS.md` — 開発ルール
- `assets/docs/roadmap.md` — ロードマップ
