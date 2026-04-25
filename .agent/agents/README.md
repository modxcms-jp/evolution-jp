# Agent Definitions

このディレクトリは、スキルを実行する「担当者」としてのエージェント定義を管理する。
スキルは手順と判断基準、エージェントは責務境界と成果物を定義する。

## 基本方針

- `AGENTS.md` を最優先の開発ルールとする。
- `.codex/skills/*/SKILL.md` は再利用可能な手順として参照する。
- 自動選択の入口は `.codex/skills/agent-orchestrator/SKILL.md` と `.codex/skills/review-agent/SKILL.md` に置く。
- エージェント定義には、スキル本文の詳細を重複記載しない。
- 複数エージェントで並行作業する場合は、編集対象ファイルを明確に分離する。
- 調査担当は原則読み取り専用、実装担当は指定された書き込み範囲のみ編集する。
- レビュー担当は差分・根拠・重大度を優先し、実装のやり直しは担当しない。

## 推奨構成

- `orchestrator.md`: タスク分解、担当割り当て、統合判断
- `explorer.md`: 既存実装・ドキュメント調査
- `planner.md`: ExecPlan 作成・更新
- `worker.md`: 実装
- `reviewer.md`: 差分レビュー
- `tester.md`: 検証、再現、テスト失敗分析
- `roadmap-curator.md`: ロードマップ整合性管理

## 共通成果物

エージェントの作業結果は、必要に応じて `.agent/runs/YYYY-MM-DD-<slug>/` に記録する。
短い作業ではチャット上の要約でよいが、設計判断・検証結果・レビュー指摘は再利用できる形で残す。

## 開始時の宣言

`agent-orchestrator` を入口にする作業では、開始時に以下を1-3行で明示する。

- 使用スキル
- 使用エージェント
- 使わないエージェントと理由

例:

```text
agent-orchestrator を入口にします。今回はURL起点の不具合調査なので explorer を主担当にします。
使用スキルは issue-resolver です。worker と tester は、実装や検証が必要になった時点で使います。
```

## 呼び出し方

通常作業では、ユーザーが「エージェントで分担」「orchestrator」「複数担当」などを依頼した場合に `agent-orchestrator` スキルを入口にする。
レビューでは、ユーザーが「レビュー」「PR確認」「差分確認」などを依頼した場合に `review-agent` スキルを入口にする。

明示的に担当を固定したい場合は、次のように指定する。

```text
explorer と planner の役割で調査と計画を進めてください。
```

```text
reviewer エージェントの方針でこの差分をレビューしてください。
```
