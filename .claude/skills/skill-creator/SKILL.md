---
name: skill-creator
description: Guide for creating effective skills. This skill should be used when users want to create a new skill (or update an existing skill) that extends Claude's capabilities with specialized knowledge, workflows, or tool integrations. Use when: (1) creating a new SKILL.md, (2) initializing a skill directory, (3) packaging a .skill file, (4) improving or iterating on an existing skill, (5) understanding skill best practices and design patterns.
license: Complete terms in LICENSE.txt
---

# Skill Creator

効果的なスキルを作成・改善するためのガイド。

## スキルの基本構造

```
skill-name/
├── SKILL.md          - 必須。frontmatter (name + description) + 指示
├── scripts/          - 実行可能コード（Python/Bash等）
├── references/       - 必要時にロードするドキュメント
└── assets/           - 出力で使用するファイル（テンプレート等）
```

設計原則、各リソースの詳細、プログレッシブディスクロージャーパターンについては [references/skill-design-guide.md](references/skill-design-guide.md) を参照。

## スキル作成プロセス

スキル作成には以下のステップが含まれます：

1. 具体的な例でスキルを理解する
2. 再利用可能なスキルの内容を計画する（スクリプト、リファレンス、アセット）
3. スキルを初期化する（init_skill.pyを実行）
4. スキルを編集する（リソースを実装しSKILL.mdを記述）
5. （オプション）スキルをパッケージ化する（package_skill.pyを実行）
6. 実際の使用に基づいて反復する

これらのステップを順番に従い、適用できない明確な理由がある場合にのみスキップしてください。

### ステップ1：具体的な例でスキルを理解する

使用パターンが明確な場合はスキップ可。ユーザーに以下を質問して具体例を収集する：

- 「このスキルがどのように使用されるかの例をいくつか挙げていただけますか？」
- 「このスキルをトリガーするためにユーザーは何と言うでしょうか？」

1つのメッセージで質問しすぎず、最重要な質問から始める。

### ステップ2：再利用可能なスキルの内容を計画する

具体的な例を効果的なスキルに変えるには、各例を次のように分析します：

1. ゼロから例を実行する方法を検討する
2. これらのワークフローを繰り返し実行する際に役立つスクリプト、リファレンス、アセットを特定する

例：PDO移行を支援する`pdo-migration`スキルの場合：

1. `mysql_*`使用箇所の棚卸しと置換パターンの把握が毎回必要
2. → `references/affected-files.md`（使用箇所一覧）+ `references/patterns.md`（旧→新の置換ルール）

例：jQuery廃止を支援する`jquery-removal`スキルの場合：

1. jQuery使用ファイルの特定とVanilla JS変換パターンが必要
2. → `references/jquery-inventory.md`（ファイル別jQuery使用状況）+ `references/conversion-rules.md`（jQueryパターン→ES6+対応表）

例：DocumentParser周辺の不具合調査を支援する`parser-debugger`スキルの場合：

1. 解析フローの把握とイベント発火順序の確認が毎回必要
2. → `references/parse-flow.md`（処理ステージ一覧・フック箇所）+ デバッグ用ログ挿入パターン

スキルの内容を確立するには、各具体例を分析して、含める再利用可能なリソースのリストを作成します：スクリプト、リファレンス、アセット。

### ステップ3：スキルを初期化する

既存スキルの反復・パッケージ化の場合はスキップ。新規作成時は `init_skill.py` を実行してテンプレートを生成する：

```bash
scripts/init_skill.py <skill-name> --path <output-directory>
```

SKILL.mdテンプレート、`scripts/`・`references/`・`assets/`のサンプルが生成される。不要なサンプルは削除する。

### ステップ4：スキルを編集する

スキルは別のClaudeインスタンスが使用するために作成される。Claudeにとって自明でない手続き的知識やドメイン固有の詳細を含めること。

#### プロジェクト慣例

既存スキル（`project-worker`、`issue-resolver`）のパターンに従う：

- **コマンドベース構造**: `## コマンド` セクションに `/command-name` + 番号付きステップ
- **AGENTS.md参照**: コーディング規約は `AGENTS.md` を参照する旨を記載
- **日本語**: description・ボディともに日本語
- **簡潔さ**: 既存スキルは40-45行。必要最小限の指示に留める

#### デザインパターン参照

- **順次ワークフロー・条件ロジック** → [references/workflows.md](references/workflows.md)
- **出力テンプレート・例パターン** → [references/output-patterns.md](references/output-patterns.md)

#### リソース実装

ステップ2で特定した `scripts/`、`references/`、`assets/` から実装を開始する。追加したスクリプトは実行テストすること。

#### SKILL.mdを更新する

**執筆ガイドライン：** 常に命令形/不定詞形を使用。

##### Frontmatter

- `name`：ハイフンケースのスキル名
- `description`：スキルの内容＋トリガー条件。descriptionにすべての「いつ使用するか」情報を含める（ボディはトリガー後にのみロードされるため）
  - 例：「Evolution CMSのキャッシュ機構の調査・最適化・トラブルシューティングを支援するスキル。キャッシュ関連の不具合修正やパフォーマンス改善時に使用します。」

##### ボディ

スキルとそのバンドルされたリソースの使用方法に関する指示を記述する。

### ステップ5：スキルをパッケージ化する（オプション）

配布が必要な場合のみ実行。.skillファイル（zip形式）を作成する：

```bash
scripts/package_skill.py <path/to/skill-folder> [output-directory]
```

自動検証（frontmatter、命名規則、description）後にパッケージ化される。ローカルで使うだけならこのステップは不要。

### ステップ6：反復する

スキルをテストした後、ユーザーは改善を要求する場合があります。これは多くの場合、スキルの使用直後に、スキルのパフォーマンスに関する新鮮なコンテキストとともに発生します。

**反復ワークフロー：**

1. 実際のタスクでスキルを使用する
2. 苦労や非効率性に気付く
3. SKILL.mdまたはバンドルされたリソースをどのように更新すべきかを特定する
4. 変更を実装して再度テストする
