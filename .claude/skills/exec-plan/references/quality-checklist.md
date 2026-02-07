# ExecPlan 品質チェックリスト

`.agent/PLANS.md` の非交渉要件に基づく検証項目。

## 必須セクション

- [ ] Purpose / Big Picture: ユーザーにとっての価値が1-2文で明記されている
- [ ] Progress: タイムスタンプ付きチェックリストがある
- [ ] Surprises & Discoveries: セクションが存在する（空でも可、実装前は「なし」と記載）
- [ ] Decision Log: 設計判断が日付付きで記録されている
- [ ] Outcomes & Retrospective: セクションが存在する（実装前は「実装後に記載」と記載）
- [ ] Context and Orientation: 対象コードの場所がリポジトリルート相対パスで記載されている
- [ ] Plan of Work: 実装方針と選定理由が散文で記述されている
- [ ] Concrete Steps: 具体的なコード例またはコマンドが含まれている
- [ ] Validation and Acceptance: 観察可能な動作（ブラウザ確認、コマンド実行等）で定義されている
- [ ] Idempotence and Recovery: 中断時の復帰手順が記載されている
- [ ] Artifacts and Notes: 関連ファイル・URLが記載されている
- [ ] Interfaces and Dependencies: 外部依存・他モジュールとのインターフェースが明記されている

## 非交渉要件

- [ ] **自己完結**: ExecPlanのみで実装完了に必要な情報がすべて含まれている
- [ ] **初心者実行可能**: 専門用語には平易な説明が添えられている
- [ ] **動作する成果物**: 検証手順に具体的なコマンドと期待出力がある
- [ ] **用語定義**: CMS固有の概念（TV、チャンク、スニペット、プラグインイベント等）が説明されている

## フォーマット

- [ ] ファイル名が `YYYY-MM-DD-task-name.md` 形式
- [ ] 保存先が `.agent/plans/`
- [ ] Progress 以外ではチェックボックスを多用せず散文で記述
- [ ] ネストしたコードブロックはインデントで表現
